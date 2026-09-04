<?php

/**
 * OrderAdminController — đơn hàng (/quan-tri/don-hang).
 *
 * Port từ src/routes/_authenticated/quan-tri/don-hang.tsx, dựng lại giao diện
 * theo "Tab Đơn hàng.dc.html" (Claude Design).
 *
 * Nhân viên (staff) xem và đổi trạng thái đơn được — khớp policy gốc
 * "staff orders" vốn cho cả admin, manager và staff toàn quyền trên bảng này.
 */

class OrderAdminController extends AdminController
{
    /** Số đơn trên một trang. */
    private const PER_PAGE = 20;

    /**
     * Trần số đơn nhận trong MỘT lần thao tác hàng loạt.
     *
     * Một trang chỉ hiện 20 đơn nên người dùng thật không bao giờ chạm tới con
     * số này. Nó chặn cú POST dựng tay gửi lên vài nghìn id — mỗi id là một
     * transaction có ghi lịch sử và có thể hoàn kho, đủ để treo cả tiến trình
     * PHP trên hosting miễn phí.
     */
    private const BULK_MAX = 100;

    public function index(): void
    {
        /*
         * Nhân viên mở danh sách đơn là lúc thứ hai đáng kéo hàng đợi về —
         * chỗ thứ nhất là màn QR của khách (OrderController::payStatus).
         *
         * Hosting không có cron, nên "định kỳ" chỉ có thể ăn theo lượt truy
         * cập có sẵn. Mà lượt truy cập đúng nhất là lượt này: người mở trang
         * đang định đối chiếu xem tiền về chưa, nên danh sách phải mới nhất
         * có thể vào đúng khoảnh khắc họ nhìn. Xem core/SepayRelay.php.
         */
        SepayRelay::keo();

        $status = (string) ($_GET['status'] ?? '');

        // Chỉ nhận trạng thái có thật; giá trị lạ coi như không lọc
        if ($status !== '' && !isset(OrderModel::STATUSES[$status])) {
            $status = '';
        }

        $range = (string) ($_GET['ngay'] ?? '');

        // Cùng lẽ đó cho khoảng ngày — xem OrderModel::DATE_RANGES.
        if (!isset(OrderModel::DATE_RANGES[$range])) {
            $range = '';
        }

        $q      = trim((string) ($_GET['q'] ?? ''));
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        /* Phạm vi cơ sở đi vào TRUY VẤN, không đi vào view.

           Lọc ở PHP sau khi đã lấy về là sai theo cả hai nghĩa: phân trang
           đếm nhầm (trang 1 hiện 6 đơn vì 14 đơn bị loại sau khi đếm), và dữ
           liệu của cơ sở khác vẫn rời khỏi cơ sở dữ liệu — chỉ là không in ra. */
        $result = OrderModel::paginateAdmin(
            $status, $page, self::PER_PAGE, $q, $range, $this->phamViCoSo()
        );

        // Dòng hàng của các đơn đang hiện, gộp MỘT câu lệnh thay vì truy vấn
        // trong vòng lặp (N+1) — 20 đơn sẽ thành 21 câu lệnh.
        $itemsByOrder = OrderModel::itemsForOrders(array_column($result['items'], 'id'));

        $this->renderAdmin('admin/orders/index', [
            'pageTitle' => 'Đơn hàng — Quản trị',
            'orders'    => $result['items'],
            // View dùng cờ này để nói rõ vì sao danh sách ngắn hơn mong đợi.
            'gioiHanCoSo' => $this->biGioiHanCoSo(),
            'items'     => $itemsByOrder,
            'total'     => $result['total'],
            'page'      => $result['page'],
            'totalPages'=> $result['totalPages'],
            'status'    => $status,
            'statuses'  => OrderModel::STATUSES,
            // Nhãn trạng thái TIỀN. Truyền vào như 'statuses' thay vì để view gọi
            // thẳng hằng của model — cùng một lối cho cả hai trục trạng thái.
            'payStatuses' => OrderModel::PAYMENT_STATUSES,
            /* Đếm TRONG PHẠM VI, cùng phạm vi với paginateAdmin() ở trên —
               nếu không thì viên lọc hiện số của toàn hệ thống trong khi bấm
               vào chỉ ra vài dòng. Xem OrderModel::statusCounts(). */
            'counts'    => OrderModel::statusCounts($this->phamViCoSo()),
            'q'         => $q,
            'range'     => $range,
            'ranges'    => OrderModel::DATE_RANGES,
            'drawer'    => $this->drawer(),
            'undo'      => $this->undoPayload(),
            /* Địa chỉ để quay về sau mỗi cú POST — kèm nguyên bộ lọc, số trang
               và cả ?xem= đang mở. Không có nó, đổi trạng thái một đơn ở trang
               3 sau khi lọc "Đang giao" là bị ném về đầu danh sách chưa lọc,
               và nhân viên phải dựng lại bộ lọc sau MỖI thao tác. */
            'quayLai'   => currentUrlWithout([]),
            'adminStyles'  => ['assets/css/admin-orders.css'],
            'adminScripts' => ['assets/js/admin-orders.js'],
        ]);
    }

    /**
     * Đổi trạng thái MỘT đơn.
     */
    public function updateStatus(): void
    {
        $this->requirePost('/quan-tri/don-hang');

        $back   = $this->back();
        $id     = (string) ($_POST['id'] ?? '');
        $status = (string) ($_POST['status'] ?? '');

        if (!isset(OrderModel::STATUSES[$status])) {
            flash('admin_error', 'Trạng thái không hợp lệ.');
            redirect($back);
        }

        $this->chanNgoaiPhamVi($id, $back);

        $order = OrderModel::find($id);

        if ($order === null) {
            flash('admin_error', 'Không tìm thấy đơn hàng.');
            redirect($back);
        }

        /*
         * ─────────────────────────────────────────────────────────────────────
         * MỞ LẠI ĐƠN ĐÃ HUỶ — Q3.1, chốt 04/09/2026
         *
         * Quyết định này SỬA yêu cầu ST-16 vốn đặt "Đã huỷ" là trạng thái kết
         * thúc. Lý do BA đưa ra: nhân viên bấm nhầm nút Huỷ là chuyện xảy ra
         * thật ở quầy, và bắt tạo đơn mới thì mất luôn số đo, mã giảm giá và
         * mốc thời gian của đơn cũ.
         *
         * Nhưng "mở lại được" không có nghĩa là "mở lại như mọi thao tác
         * khác". Huỷ đơn đã trả hàng về kho và đã cắt doanh thu; đi ngược lại
         * là chạm vào cả hai con số đó. Nên Q3.1 kèm hai điều kiện, và cả hai
         * đều kiểm ở đây:
         *
         *   ai      chỉ Quản trị viên. Đường của người vừa bấm nhầm là THANH
         *           HOÀN TÁC ngay dưới (Q3.2) — nó không đòi lý do vì nó chỉ
         *           sống vài phút và chỉ cho chính người ấy.
         *   lý do   bắt buộc. Không phải để làm khó: một đơn có một lần huỷ
         *           rồi mở lại mà không kèm chữ nào thì người đọc sổ sáu tháng
         *           sau chỉ thấy hai dòng mâu thuẫn nhau.
         * ─────────────────────────────────────────────────────────────────────
         */
        $moLaiDon = (string) $order['status'] === 'cancelled' && $status !== 'cancelled';
        $lyDo     = trim((string) ($_POST['ly_do'] ?? ''));

        if ($moLaiDon) {
            if (!UserModel::hasRole($this->userId, 'admin')) {
                flash('admin_error',
                    'Chỉ Quản trị viên mở lại được đơn đã huỷ. '
                    . 'Nếu vừa bấm nhầm, dùng thanh Hoàn tác ngay sau thao tác.');
                redirect($back);
            }

            if (utf8Length($lyDo) < OrderModel::LY_DO_TOI_THIEU) {
                flash('admin_error',
                    'Mở lại đơn đã huỷ thì phải ghi lý do, tối thiểu '
                    . OrderModel::LY_DO_TOI_THIEU . ' ký tự.');
                redirect($back);
            }
        }

        // Mọi luật đi kèm việc đổi trạng thái nằm trong model: ghi lịch sử
        // (thanh tiến trình của khách đọc bảng đó), hoàn kho khi huỷ, và đánh
        // dấu đã thu tiền khi đơn COD hoàn tất. Xem OrderModel::changeStatus.
        OrderModel::changeStatus(
            $id, $status, AuthMiddleware::staffId(), $moLaiDon ? $lyDo : null
        );

        $this->ghiHoanTac([$id => (string) $order['status']], $status);

        flash('admin_success', 'Đã cập nhật trạng thái đơn hàng.');
        redirect($back);
    }

    /**
     * Ghi nhận đã nhận được tiền, hoặc gỡ đánh dấu nếu bấm nhầm
     * (POST /quan-tri/don-hang/thanh-toan).
     *
     * Đây là bước ĐỐI CHIẾU TAY cho đơn chuyển khoản: nhân viên xem sao kê, thấy
     * tiền vào với nội dung là mã đơn thì bấm. Đơn COD không cần bấm — thu tiền
     * và giao hàng là cùng một việc, nên changeStatus() tự đánh dấu khi đơn sang
     * "Hoàn tất".
     *
     * Khi nối cổng thanh toán, webhook sẽ gọi thẳng OrderModel::markPaid() và
     * nút này còn lại để xử lý những ca cổng không bắt được (khách chuyển từ
     * ngân hàng khác, sai nội dung…).
     */
    public function updatePayment(): void
    {
        $this->requirePost('/quan-tri/don-hang');

        $back = $this->back();
        $id   = (string) ($_POST['id'] ?? '');
        $paid = ($_POST['paid'] ?? '') === '1';

        $this->chanNgoaiPhamVi($id, $back);

        $changed = $paid ? OrderModel::markPaid($id) : OrderModel::markUnpaid($id);

        // Nói rõ "không có gì đổi" thay vì báo thành công: hai nhân viên cùng
        // xem một sao kê và cùng bấm thì người thứ hai phải biết là mình không
        // vừa ghi thêm một lần thu tiền nào.
        if (!$changed) {
            flash('admin_error', 'Đơn hàng đã ở đúng trạng thái thanh toán đó.');
            redirect($back);
        }

        flash('admin_success', $paid
            ? 'Đã ghi nhận thanh toán cho đơn hàng.'
            : 'Đã gỡ đánh dấu thanh toán.');
        redirect($back);
    }

    /**
     * Thao tác HÀNG LOẠT trên các đơn đã tick (POST /quan-tri/don-hang/hang-loat).
     *
     * ─────────────────────────────────────────────────────────────────────────
     * MỘT ĐƯỜNG, HAI VIỆC — VÌ MỘT FORM CHỈ CÓ MỘT `action`
     *
     * Thanh nổi ở đáy trang có hai nút ("Áp dụng" cho ô chọn trạng thái và "Đã
     * nhận tiền"), mà cả hai phải gửi lên CÙNG danh sách ô tick — tức cùng một
     * <form>. Tách thành hai đường thì phải hoặc lồng form (HTML cấm), hoặc
     * chép danh sách id sang một form thứ hai bằng JavaScript — và lúc đó tắt
     * JS là mất một nửa thanh công cụ.
     *
     * `act` phân việc, `formaction` không cần tới. Xem thêm khối chú thích về
     * thuộc tính `form=` trong app/views/admin/orders/index.php.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function bulk(): void
    {
        $this->requirePost('/quan-tri/don-hang');

        $back = $this->back();

        /* Chỉ giữ chuỗi: `ids[]` là mảng đến thẳng từ HTTP nên phần tử có thể
           là mảng lồng nếu ai đó gửi `ids[0][0]=…`, và lúc đó (string) ném ra
           lỗi thay vì lọc bỏ. */
        $ids = array_values(array_filter(
            (array) ($_POST['ids'] ?? []),
            static fn ($v): bool => is_string($v) && $v !== ''
        ));

        if ($ids === []) {
            flash('admin_error', 'Chưa chọn đơn hàng nào.');
            redirect($back);
        }

        $ids = array_slice(array_unique($ids), 0, self::BULK_MAX);

        /* PHẠM VI CƠ SỞ ÁP TRƯỚC KHI PHÂN VIỆC — SNFR-07b.

           Lọc ở ĐÂY chứ không trong từng nhánh: hai nhánh tự lọc là hai chỗ có
           thể quên, và nhánh quên thì thao tác hàng loạt trở thành đúng cái lỗ
           mà thao tác đơn lẻ vừa bịt. Lọc chứ không từ chối cả lô — xem
           OrderModel::locTheoPhamVi(). */
        $truoc = count($ids);
        $ids   = OrderModel::locTheoPhamVi($ids, $this->phamViCoSo());

        if ($ids === []) {
            flash('admin_error', 'Không có đơn nào trong phạm vi cơ sở của bạn.');
            redirect($back);
        }

        if (count($ids) < $truoc) {
            /* NÓI RA số đơn bị bỏ. Im lặng làm 6 trên 20 đơn rồi báo thành
               công là để người dùng tin rằng cả 20 đã xong. */
            flash('admin_error', sprintf(
                'Bỏ qua %d đơn ngoài phạm vi cơ sở của bạn.', $truoc - count($ids)
            ));
        }

        match ((string) ($_POST['act'] ?? '')) {
            'trang-thai' => $this->bulkStatus($ids, (string) ($_POST['status'] ?? ''), $back),
            'thanh-toan' => $this->bulkPayment($ids, $back),
            default      => $this->bad('Thao tác không hợp lệ.', $back),
        };
    }

    /**
     * Trả các đơn vừa đổi về trạng thái cũ (POST /quan-tri/don-hang/hoan-tac).
     *
     * ─────────────────────────────────────────────────────────────────────────
     * TRẠNG THÁI CŨ ĐI TRONG CHÍNH FORM, KHÔNG NẰM TRONG SESSION
     *
     * Cách kia phải giữ một "việc vừa làm" trong session và quyết định khi nào
     * thì nó hết hạn — mà hai tab cùng mở thì hai tab ghi đè nhau, và người
     * bấm Hoàn tác ở tab này có thể lùi mất thao tác của tab kia.
     *
     * Ở đây danh sách `truoc[<id>] = <trạng thái cũ>` được in thẳng vào thanh
     * hoàn tác lúc dựng trang, nên mỗi thanh chỉ lùi đúng thao tác đã sinh ra
     * nó. Không có rủi ro thêm về quyền: người bấm được nút này thì cũng đang
     * đổi trạng thái tuỳ ý được bằng ô chọn ngay trên bảng.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function undoStatus(): void
    {
        $this->requirePost('/quan-tri/don-hang');

        $back  = $this->back();
        $truoc = (array) ($_POST['truoc'] ?? []);
        $soDon = 0;

        /* CỬA SỔ ĐƯỢC KIỂM Ở MÁY CHỦ, KHÔNG CHỈ Ở CHỖ VẼ THANH — Q3.2.

           Ẩn thanh đi là đủ cho người dùng thật, nhưng từ 07/09/2026 đường
           này còn là một lối MỞ LẠI ĐƠN ĐÃ HUỶ mà không phải ghi lý do. Q3.1
           chỉ cho Quản trị viên làm việc đó và bắt kèm lý do; nếu cửa sổ chỉ
           tồn tại ở lớp giao diện thì một cú POST dựng tay đi vòng qua cả hai
           điều kiện ấy. Trong cửa sổ thì miễn lý do là ĐÚNG Ý Q3.2 — nhưng
           đúng ý ấy chỉ kéo dài vài phút. */
        $luc = (int) ($_POST['luc'] ?? 0);

        if ($luc <= 0 || (time() - $luc) > OrderModel::RUT_LAI_GIAY) {
            flash('admin_error',
                'Đã quá cửa sổ hoàn tác ' . (int) (OrderModel::RUT_LAI_GIAY / 60)
                . ' phút. Đổi trạng thái bằng ô chọn trên bảng; '
                . 'riêng đơn đã huỷ thì cần Quản trị viên mở lại kèm lý do.');
            redirect($back);
        }

        foreach (array_slice($truoc, 0, self::BULK_MAX, true) as $id => $status) {
            if (!is_string($id) || !is_string($status) || !isset(OrderModel::STATUSES[$status])) {
                continue;
            }

            /* Phạm vi cơ sở — SNFR-07b. Hoàn tác cũng là một đường GHI, và
               danh sách `truoc[]` đi trong chính form nên sửa được. */
            if (!OrderModel::trongPhamVi($id, $this->phamViCoSo())) {
                continue;
            }

            OrderModel::changeStatus($id, $status, AuthMiddleware::staffId());
            $soDon++;
        }

        if ($soDon === 0) {
            flash('admin_error', 'Không còn gì để hoàn tác.');
            redirect($back);
        }

        /* KHÔNG ghi hoàn tác cho chính cú hoàn tác. Một thanh "Hoàn tác" hiện
           lên ngay sau khi vừa hoàn tác thì bấm hai lần là quay về đúng chỗ
           xuất phát, và không ai biết mình đang ở đâu trong chuỗi ấy. */
        flash('admin_success', sprintf('Đã hoàn tác %d đơn hàng.', $soDon));
        redirect($back);
    }

    // ========================================================================
    // BÊN TRONG
    // ========================================================================

    /** Đổi trạng thái nhiều đơn cùng lúc. */
    private function bulkStatus(array $ids, string $status, string $back): void
    {
        if (!isset(OrderModel::STATUSES[$status])) {
            $this->bad('Trạng thái không hợp lệ.', $back);
        }

        $truoc = [];

        foreach ($ids as $id) {
            $order = OrderModel::find($id);

            // Bỏ qua đơn không còn (ai đó xoá giữa lúc trang đang mở) thay vì
            // dừng cả loạt: 19 đơn còn lại vẫn phải được xử lý.
            if ($order === null) {
                continue;
            }

            OrderModel::changeStatus($id, $status, AuthMiddleware::staffId());
            $truoc[$id] = (string) $order['status'];
        }

        if ($truoc === []) {
            $this->bad('Không tìm thấy đơn hàng.', $back);
        }

        $this->ghiHoanTac($truoc, $status);

        flash('admin_success', sprintf(
            'Đã chuyển %d đơn sang «%s».',
            count($truoc),
            OrderModel::STATUSES[$status]
        ));
        redirect($back);
    }

    /** Ghi nhận đã nhận tiền cho nhiều đơn cùng lúc. */
    private function bulkPayment(array $ids, string $back): void
    {
        $doi = 0;

        foreach ($ids as $id) {
            if (OrderModel::markPaid($id)) {
                $doi++;
            }
        }

        /* KHÔNG có chiều ngược lại ở đây. Gỡ đánh dấu là thao tác sửa lỗi bấm
           nhầm trên MỘT đơn, làm hàng loạt thì mỗi lần bấm nhầm là xoá sạch
           mốc tiền về của một loạt đơn — thứ nhân viên kế toán không dựng lại
           được từ đâu cả. */
        if ($doi === 0) {
            $this->bad('Các đơn đã chọn đều đã được ghi nhận thanh toán.', $back);
        }

        flash('admin_success', sprintf('Đã ghi nhận thanh toán cho %d đơn.', $doi));
        redirect($back);
    }

    /**
     * Cất trạng thái CŨ của loạt đơn vừa đổi, để trang sau in ra thanh hoàn tác.
     *
     * Đi bằng flash (một lần rồi mất) chứ không phải session dài hạn: thanh
     * hoàn tác chỉ có nghĩa ngay sau thao tác. Tải lại trang lần nữa là người
     * dùng đã đi tiếp việc khác.
     *
     * @param array<string, string> $truoc [id đơn => trạng thái cũ]
     */
    /**
     * Chặn thao tác ghi lên một đơn NGOÀI phạm vi cơ sở của người bấm.
     *
     * Gộp cả phép kiểm tồn tại vào đây và ĐI THẲNG tới redirect: bốn action
     * đơn lẻ đều cần đúng một câu hỏi, và bốn chỗ tự hỏi là bốn cơ hội quên —
     * mà cái quên đó không gây lỗi, nó chỉ lặng lẽ cho người ta sửa đơn của
     * cơ sở khác. Đây chính là chỗ đã bị bỏ sót suốt từ 05/09 tới 09/09.
     *
     * MỘT CÂU BÁO CHO CẢ HAI TRƯỜNG HỢP (không tồn tại · ngoài phạm vi): trả
     * lời khác nhau là nói cho người dò biết id nào có thật.
     */
    private function chanNgoaiPhamVi(string $id, string $back): void
    {
        if (OrderModel::trongPhamVi($id, $this->phamViCoSo())) {
            return;
        }

        flash('admin_error', 'Không tìm thấy đơn hàng trong phạm vi cơ sở của bạn.');
        redirect($back);
    }

    private function ghiHoanTac(array $truoc, string $moi): void
    {
        if ($truoc === []) {
            return;
        }

        flash('don_hang_hoan_tac', (string) json_encode([
            'truoc' => $truoc,
            /* MỐC THỜI GIAN LÀM THANH NÀY THÀNH MỘT CỬA SỔ THẬT — Q3.2.

               Trước 07/09/2026 thanh hoàn tác sống tới khi người dùng tải lại
               trang, không giới hạn. Một tab để quên qua đêm rồi sáng hôm sau
               ai đó bấm Hoàn tác là lùi một trạng thái đã cũ mười hai tiếng,
               và không có gì trên màn hình nói rằng nó cũ.

               Dấu thời gian đi kèm ngay trong gói, không nằm trong session:
               cùng lý lẽ với danh sách `truoc` — xem chú thích undoStatus(). */
            'luc'   => time(),
            'msg'   => sprintf(
                'Đã chuyển %d đơn sang «%s»',
                count($truoc),
                OrderModel::STATUSES[$moi] ?? $moi
            ),
        ], JSON_UNESCAPED_UNICODE));
    }

    // ========================================================================
    // MỐC "BẮT ĐẦU MÀI" — Q2.2 · X07
    // ========================================================================

    /**
     * Bấm "Bắt đầu mài" (POST /quan-tri/don-hang/bat-dau-mai).
     *
     * X07: Quản lý cơ sở TRỞ LÊN, không phải Kỹ thuật viên. Cửa hàng tự mài
     * tại chỗ và người trực tiếp mài chính là Quản lý cơ sở; vai trò Kỹ thuật
     * viên vẫn tồn tại theo X31 nhưng phạm vi của nó là hồ sơ khúc xạ (Q77.2).
     */
    public function startLens(): void
    {
        $this->requirePost('/quan-tri/don-hang');

        $back = $this->back();
        $id   = (string) ($_POST['id'] ?? '');

        $this->requireManager($back);
        $this->chanNgoaiPhamVi($id, $back);

        $ket = OrderModel::batDauMai($id, $this->userId);

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok']
                ? 'Đã ghi mốc bắt đầu mài. Rút lại được trong '
                  . (int) (OrderModel::RUT_LAI_GIAY / 60) . ' phút.'
                : $ket['error']);

        redirect($back);
    }

    /**
     * Gỡ mốc "Bắt đầu mài" (POST /quan-tri/don-hang/huy-mai).
     *
     * MỘT ACTION, HAI ĐƯỜNG. Tách thành hai route thì hai chỗ cùng phải nhớ
     * luật "trong cửa sổ thì khỏi lý do", và chỗ quên là chỗ Q2.2 lặng lẽ mất.
     *
     *   trong cửa sổ  chính người vừa bấm, không cần lý do, không cần chức vụ
     *                 — họ vừa bấm được thì cũng vừa gỡ được
     *   quá cửa sổ    Quản lý cơ sở trở lên, BẮT BUỘC ghi lý do
     *
     * Model kiểm lại luật lý do một lần nữa (OrderModel::daoMai) để một đường
     * gọi mới trong tương lai không đi vòng qua chỗ này.
     */
    public function undoLens(): void
    {
        $this->requirePost('/quan-tri/don-hang');

        $back = $this->back();
        $id   = (string) ($_POST['id'] ?? '');

        $this->chanNgoaiPhamVi($id, $back);

        $order = OrderModel::find($id);

        if ($order === null) {
            flash('admin_error', 'Không tìm thấy đơn hàng.');
            redirect($back);
        }

        if (!OrderModel::trongCuaSoRutLai($order, $this->userId)) {
            $this->requireManager($back);
        }

        $ket = OrderModel::daoMai($id, $this->userId, (string) ($_POST['ly_do'] ?? ''));

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok'] ? 'Đã gỡ mốc bắt đầu mài.' : $ket['error']);

        redirect($back);
    }

    /** Đọc lại gói hoàn tác của thao tác vừa rồi, hoặc null. */
    private function undoPayload(): ?array
    {
        $raw = flash('don_hang_hoan_tac');

        if ($raw === null) {
            return null;
        }

        $goi = json_decode($raw, true);

        if (!is_array($goi) || !is_array($goi['truoc'] ?? null) || $goi['truoc'] === []) {
            return null;
        }

        /* QUÁ CỬA SỔ THÌ KHÔNG HIỆN THANH — Q3.2.

           Gói cũ (sinh trước 07/09/2026) không có khoá 'luc'. Coi nó là hết
           hạn chứ không phải còn hạn: một gói không biết mình sinh lúc nào thì
           không chứng minh được là còn ngắn, và mặc định an toàn ở đây là
           không cho lùi. Cùng lắm người dùng mất một thanh hoàn tác đúng một
           lần, ngay sau khi triển khai. */
        $luc = (int) ($goi['luc'] ?? 0);

        return $luc > 0 && (time() - $luc) <= OrderModel::RUT_LAI_GIAY ? $goi : null;
    }

    /**
     * Đơn đang mở trong ngăn kéo chi tiết (?xem=<id>), kèm dòng hàng.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * NGĂN KÉO LÀ MỘT ĐỊA CHỈ THẬT, KHÔNG PHẢI MỘT TRẠNG THÁI CỦA JAVASCRIPT
     *
     * Bản thiết kế mở ngăn kéo bằng state trong trình duyệt. Ở đây nó đi bằng
     * ?xem=<id> vì ba lẽ, theo đúng nếp "tắt JS thì mọi luồng vẫn chạy":
     *
     *   · không có JS thì bấm vào mã đơn vẫn xem được chi tiết;
     *   · nhân viên gửi được đường dẫn tới đúng một đơn cho đồng nghiệp;
     *   · đổi trạng thái ngay trong ngăn kéo xong, trang tải lại vẫn còn đang
     *     mở đúng đơn đó — vì `quayLai` mang theo tham số này.
     *
     * Đọc riêng MỘT câu thay vì tìm trong $orders đang hiện: đơn muốn xem có
     * thể nằm ở trang khác, hoặc rơi ra ngoài bộ lọc hiện tại.
     * ─────────────────────────────────────────────────────────────────────────
     */
    private function drawer(): ?array
    {
        $id = (string) ($_GET['xem'] ?? '');

        if ($id === '') {
            return null;
        }

        $order = Database::fetchOne(
            'SELECT o.*, s.name AS store_name
               FROM orders o
               LEFT JOIN stores s ON s.id = o.store_id
              WHERE o.id = :id',
            ['id' => $id]
        );

        if ($order === null) {
            /* KHÔNG báo lỗi và KHÔNG chuyển hướng: ?xem= trỏ vào một đơn đã bị
               xoá thì việc đúng là hiện danh sách như thường. Ném một dải đỏ
               vào mặt người vừa mở lại một đường dẫn cũ là phạt họ vì một
               chuyện không phải lỗi của họ. */
            return null;
        }

        $order['items'] = OrderModel::items($id);

        /* Bốn câu trả lời mà ngăn kéo cần để biết vẽ những nút nào. Tính ở đây
           chứ không trong view: view mà tự hỏi UserModel::hasRole() thì phép
           kiểm quyền nằm rải ở lớp vẽ, và lớp vẽ là chỗ dễ quên nhất. Đây chỉ
           là để VẼ — chặn thật nằm ở startLens()/undoLens()/updateStatus(). */
        $order['co_trong']    = OrderModel::coTrong($id);
        $order['da_mai']      = OrderModel::daBatDauMai($order);
        $order['rut_lai_duoc'] = OrderModel::trongCuaSoRutLai($order, $this->userId);
        $order['la_admin']    = UserModel::hasRole($this->userId, 'admin');
        $order['la_quan_ly']  = $order['la_admin'] || UserModel::hasRole($this->userId, 'manager');

        /*
         * ─────────────────────────────────────────────────────────────────────
         * MỞ MỘT ĐƠN CÓ SỐ ĐO LÀ MỘT LẦN ĐỌC DỮ LIỆU Y TẾ — PHẢI CÓ VẾT
         *
         * CLAUDE.md mục 5 và spec 3.L: mọi thao tác đọc và ghi đơn thuốc kính
         * đều phải ghi lại. Đây đúng là cái tab "Đơn thuốc kính" của trang
         * khách hàng đã làm (CustomerAdminController, action 'rx.read'), chỉ
         * khác đường vào.
         *
         * CHỈ GHI KHI ĐƠN THẬT SỰ CÓ SỐ ĐO. Đơn mua gọng trần hay kính mát
         * không mang dữ liệu y tế nào, ghi vết cho chúng chỉ làm loãng bảng và
         * khiến lần đọc thật lẫn vào giữa hàng trăm dòng vô nghĩa.
         *
         * `user_id` để NULL với khách vãng lai — cột cho phép NULL đúng vì thế.
         * `detail` chỉ mang MÃ ĐƠN, tuyệt đối không mang con số nào: bảng vết
         * mà chứa chính số đo thì nó thành bản sao thứ hai của thứ đang cần
         * bảo vệ. Xem chú thích của AuditLogModel::write().
         *
         * CÒN MỘT LỖ HỔNG ĐÃ BIẾT: bảng danh sách cũng in số đo mà không ghi
         * vết. Ghi ở đó nghĩa là mỗi lần tải trang sinh tới hai mươi dòng vết
         * cho hai mươi khách khác nhau, và bảng vết mất hết ý nghĩa. Cách xử
         * lý đúng (ẩn số đo trong bảng? ghi một dòng gộp?) là câu hỏi cho BA,
         * chưa tự quyết ở đây.
         * ─────────────────────────────────────────────────────────────────────
         */
        foreach ($order['items'] as $dong) {
            if (!empty($dong['prescription'])) {
                AuditLogModel::write(
                    $order['user_id'],
                    'rx.read',
                    'Mở đơn hàng ' . $order['code']
                );
                break;
            }
        }

        return $order;
    }

    /** Địa chỉ quay về sau một cú POST — luôn là một đường nội bộ. */
    private function back(): string
    {
        return safeRedirectPath($_POST['quay_lai'] ?? null, '/quan-tri/don-hang');
    }

    /** Báo lỗi rồi quay lại — gói lại vì bốn nhánh ở trên đều làm đúng hai việc này. */
    private function bad(string $message, string $back): never
    {
        flash('admin_error', $message);
        redirect($back);
    }
}
