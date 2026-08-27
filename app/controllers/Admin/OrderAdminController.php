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
        $result = OrderModel::paginateAdmin($status, $page, self::PER_PAGE, $q, $range);

        // Dòng hàng của các đơn đang hiện, gộp MỘT câu lệnh thay vì truy vấn
        // trong vòng lặp (N+1) — 20 đơn sẽ thành 21 câu lệnh.
        $itemsByOrder = OrderModel::itemsForOrders(array_column($result['items'], 'id'));

        $this->renderAdmin('admin/orders/index', [
            'pageTitle' => 'Đơn hàng — Quản trị',
            'orders'    => $result['items'],
            'items'     => $itemsByOrder,
            'total'     => $result['total'],
            'page'      => $result['page'],
            'totalPages'=> $result['totalPages'],
            'status'    => $status,
            'statuses'  => OrderModel::STATUSES,
            // Nhãn trạng thái TIỀN. Truyền vào như 'statuses' thay vì để view gọi
            // thẳng hằng của model — cùng một lối cho cả hai trục trạng thái.
            'payStatuses' => OrderModel::PAYMENT_STATUSES,
            'counts'    => OrderModel::statusCounts(),
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

        $order = OrderModel::find($id);

        if ($order === null) {
            flash('admin_error', 'Không tìm thấy đơn hàng.');
            redirect($back);
        }

        // Mọi luật đi kèm việc đổi trạng thái nằm trong model: ghi lịch sử
        // (thanh tiến trình của khách đọc bảng đó), hoàn kho khi huỷ, và đánh
        // dấu đã thu tiền khi đơn COD hoàn tất. Xem OrderModel::changeStatus.
        OrderModel::changeStatus($id, $status, AuthMiddleware::staffId());

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

        if (!OrderModel::exists(['id' => $id])) {
            flash('admin_error', 'Không tìm thấy đơn hàng.');
            redirect($back);
        }

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

        foreach (array_slice($truoc, 0, self::BULK_MAX, true) as $id => $status) {
            if (!is_string($id) || !is_string($status) || !isset(OrderModel::STATUSES[$status])) {
                continue;
            }

            if (!OrderModel::exists(['id' => $id])) {
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
    private function ghiHoanTac(array $truoc, string $moi): void
    {
        if ($truoc === []) {
            return;
        }

        flash('don_hang_hoan_tac', (string) json_encode([
            'truoc' => $truoc,
            'msg'   => sprintf(
                'Đã chuyển %d đơn sang «%s»',
                count($truoc),
                OrderModel::STATUSES[$moi] ?? $moi
            ),
        ], JSON_UNESCAPED_UNICODE));
    }

    /** Đọc lại gói hoàn tác của thao tác vừa rồi, hoặc null. */
    private function undoPayload(): ?array
    {
        $raw = flash('don_hang_hoan_tac');

        if ($raw === null) {
            return null;
        }

        $goi = json_decode($raw, true);

        return is_array($goi) && is_array($goi['truoc'] ?? null) && $goi['truoc'] !== []
            ? $goi
            : null;
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
