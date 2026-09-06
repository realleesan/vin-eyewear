<?php

/**
 * Admin/EmailAdminController.php — hai màn của module thư.
 *
 *   GET  /quan-tri/email             index()   hàng chờ và sổ kết quả gửi
 *   POST /quan-tri/email/gui-lai     guiLai()  đưa một lá hỏng về lại hàng chờ
 *   POST /quan-tri/email/bo          bo()      thôi không gửi lá này nữa
 *   GET  /quan-tri/mau-thu           mau()     danh sách mẫu, ?sua=<key> để sửa
 *   POST /quan-tri/mau-thu/luu       luuMau()  ghi một mẫu
 *
 * SRS v2.1.0 — FR-EM-05 (*"Khu quản trị xem được nhật ký gửi, lý do hỏng và
 * gửi lại được"*) và FR-EM-09 (*"Mẫu email do cửa hàng sửa được"*).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỘT CONTROLLER CHO HAI MÀN
 *
 * Chúng là hai mặt của cùng một câu hỏi. Người mở màn Mẫu thư gần như luôn vừa
 * đọc một lá trong hàng chờ và thấy câu chữ chưa ổn; người mở hàng chờ thường
 * muốn xem lá thư vừa gửi đi trông ra sao sau khi sửa mẫu. Tách hai file là
 * chép lại cùng một khối chú thích về quyền và cùng một lối trả về.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CHỈ 'admin', VÀ CHẶN THẬT BẰNG 403 — không chỉ giấu khỏi thanh bên
 *
 * Cùng mức với Lịch sử thao tác, vì cùng một lý do: `email_queue` chứa NGUYÊN
 * VĂN mọi lá thư đã gửi cho khách — địa chỉ email, tên, mã đơn, số tiền. Đó là
 * một bản sao dữ liệu khách hàng dưới dạng dễ đọc nhất có thể, và nó không nằm
 * sau bất kỳ ô tìm kiếm nào: mở trang ra là thấy trăm lá gần nhất.
 *
 * Màn Mẫu thư thì khác lý do nhưng cùng kết luận: sửa một mẫu là đổi thứ HÀNG
 * NGHÌN khách sẽ đọc, và không có bước duyệt nào ở giữa.
 *
 * Đúng nguyên tắc 4 của CLAUDE.md — ẩn nút không phải là phân quyền.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class EmailAdminController extends AdminController
{
    private const VE_EMAIL = '/quan-tri/email';
    private const VE_MAU    = '/quan-tri/mau-thu';

    /**
     * Chỉ 'admin'. Xem khối chú thích đầu lớp.
     *
     * Trả 403 chứ không chuyển hướng kèm flash: chuyển hướng nói "bạn vừa làm
     * sai", còn 403 nói "trang này không dành cho bạn" — và đó mới là sự thật.
     * Cùng cách làm với AuditLogAdminController.
     */
    private function chanNeuKhongPhaiAdmin(): void
    {
        if (!UserModel::hasRole($this->userId, 'admin')) {
            http_response_code(403);
            (new ErrorController())->forbidden();
            exit;
        }
    }

    // ========================================================================
    // HÀNG CHỜ — FR-EM-05
    // ========================================================================

    public function index(): void
    {
        $this->chanNeuKhongPhaiAdmin();

        $loc = (string) ($_GET['loc'] ?? '');

        if (!isset(EmailQueueModel::TRANG_THAI[$loc])) {
            $loc = '';
        }

        /* NGĂN KÉO MỞ BẰNG ?xem=<id>, cùng lối với mọi màn khác trong khu quản
           trị. Không phải một route riêng: ruột ngăn kéo là một lá thư của
           chính danh sách đang xem, và giữ nó trong cùng URL nghĩa là đóng
           ngăn kéo lại là về đúng bộ lọc và đúng chỗ đang đứng. */
        $xemId = (string) ($_GET['xem'] ?? '');

        $trang = EmailQueueModel::danhSach($loc, max(1, (int) ($_GET['page'] ?? 1)));

        $this->renderAdmin('admin/email/index', [
            'pageTitle'  => 'Hàng chờ thư',
            'rows'       => $trang['rows'],
            'total'      => $trang['total'],
            'totalPages' => $trang['totalPages'],
            'page'       => $trang['page'],
            'dem'       => EmailQueueModel::demTheoTrangThai(),
            'loc'       => $loc,
            'xem'       => $xemId !== '' ? EmailQueueModel::chiTiet($xemId) : null,
            /* Bảng thiếu thì chỉ hiện một dải nhắc, không để trang đổ — cùng
               cách làm với màn Chờ hàng và Nhật ký thao tác. Danh sách rỗng vì
               THIẾU BẢNG và danh sách rỗng vì CHƯA CÓ THƯ NÀO cần hai hành
               động khác hẳn nhau. */
            'coBang'    => EmailQueueModel::available(),
            /* ĐƯỜNG GỬI CÓ THẬT CHƯA — thứ quan trọng nhất trên màn này.

               Hosting hiện tại (InfinityFree gói miễn phí) không gửi được thư
               nào: mail() bị vô hiệu hoá và cổng SMTP ra ngoài bị chặn. Không
               nói ra thì người vận hành nhìn thấy một hàng chờ mấy trăm lá ở
               'Đang chờ gửi' và kết luận hệ thống hỏng — trong khi nó đang làm
               đúng thứ được thiết kế. */
            'guiDuoc'   => Mailer::canDeliver(),
            'mailDriver' => (string) config('mail.driver', 'log'),
        ]);
    }

    /** Đưa một lá về lại hàng chờ — nút "Gửi lại" của FR-EM-05. */
    public function guiLai(): void
    {
        $this->chanNeuKhongPhaiAdmin();
        $this->requirePost(self::VE_EMAIL);

        $id = (string) ($_POST['id'] ?? '');
        $kq = EmailQueueModel::guiLai($id);

        if ($kq['ok']) {
            /* VẾT VỚI user_id CỦA CHỦ LÁ THƯ, không phải NULL.

               `email_queue`.`user_id` đã ghi sẵn khách nào là người nhận, và
               màn Lịch sử thao tác dựng liên kết sang hồ sơ khách từ đúng cột
               ấy. Bỏ NULL vào đây là làm một thao tác VỀ MỘT KHÁCH thành một
               dòng không tra ngược được về họ. NULL với thư gửi cho khách vãng
               lai — đúng, họ không có hồ sơ nào để gắn. */
            $thu = EmailQueueModel::chiTiet($id);

            AuditLogModel::write(
                $thu['user_id'] ?? null,
                'email.resend',
                'Gửi lại thư "' . (string) ($thu['loai'] ?? '') . '" tới '
                    . (string) ($thu['nguoi_nhan'] ?? '')
            );

            flash('admin_success', 'Đã đưa lá thư về lại hàng chờ.');
        } else {
            flash('admin_error', $kq['error']);
        }

        redirect($this->veDanhSach());
    }

    /** Thôi không gửi lá này nữa — nó vẫn nằm trong sổ. */
    public function bo(): void
    {
        $this->chanNeuKhongPhaiAdmin();
        $this->requirePost(self::VE_EMAIL);

        $id  = (string) ($_POST['id'] ?? '');
        $thu = EmailQueueModel::chiTiet($id);
        $kq  = EmailQueueModel::bo($id);

        if ($kq['ok']) {
            AuditLogModel::write(
                $thu['user_id'] ?? null,
                'email.discard',
                'Bỏ thư "' . (string) ($thu['loai'] ?? '') . '" tới '
                    . (string) ($thu['nguoi_nhan'] ?? '')
            );

            flash('admin_success', 'Đã bỏ lá thư này.');
        } else {
            flash('admin_error', $kq['error']);
        }

        redirect($this->veDanhSach());
    }

    /**
     * Về đúng bộ lọc đang xem sau một thao tác.
     *
     * Người dùng màn này lọc 'Gửi hỏng' rồi xử lý từng dòng một. Trả họ về
     * danh sách đầy đủ sau mỗi lần bấm là bắt lọc lại từ đầu ở mỗi dòng.
     *
     * KHÔNG mang theo ?xem: ngăn kéo vừa nói về một lá thư mà thao tác vừa
     * xong đã đổi trạng thái, nên mở lại nó là bày một cảnh cũ.
     */
    private function veDanhSach(): string
    {
        $tham = array_filter([
            'loc'  => isset(EmailQueueModel::TRANG_THAI[(string) ($_POST['loc'] ?? '')])
                ? (string) $_POST['loc'] : '',
            'page' => ($t = max(1, (int) ($_POST['page'] ?? 1))) > 1 ? (string) $t : '',
        ], static fn (string $v): bool => $v !== '');

        return self::VE_EMAIL . ($tham !== [] ? '?' . http_build_query($tham) : '');
    }

    // ========================================================================
    // MẪU THƯ — FR-EM-09
    // ========================================================================

    public function mau(): void
    {
        $this->chanNeuKhongPhaiAdmin();

        $key = (string) ($_GET['sua'] ?? '');

        $sua = $key !== '' ? EmailTemplateModel::tim($key) : null;

        /* Bản nháp vừa bị từ chối đè lên bản trong CSDL — xem luuMau().

           Qua JSON vì flash() chỉ nhận và trả CHUỖI (xem core/helpers.php);
           ném thẳng một mảng vào đó là một TypeError, và nó sẽ nổ đúng lúc
           người dùng vừa gõ sai một ô — tức là biến một lời nhắc thành một
           trang 500.

           Chỉ đè bốn ô nội dung và cờ bật; `nhan`, `mo_ta`, `bien` luôn lấy
           từ CSDL vì chúng không sửa được. */
        $nhap = flash('mau_thu_nhap');
        $nhap = $nhap !== null ? json_decode($nhap, true) : null;

        if ($sua !== null && is_array($nhap)) {
            $sua = array_merge($sua, $nhap);
        }

        $this->renderAdmin('admin/email/mau', [
            'pageTitle' => 'Mẫu thư',
            'rows'      => EmailTemplateModel::all(),
            'sua'       => $sua,
            'coBang'    => EmailTemplateModel::available(),
        ]);
    }

    public function luuMau(): void
    {
        $this->chanNeuKhongPhaiAdmin();
        $this->requirePost(self::VE_MAU);

        $key = (string) ($_POST['key'] ?? '');
        $kq  = EmailTemplateModel::luu($key, $_POST, $this->userId);

        if ($kq['ok']) {
            /* GHI CẢ CHUYỆN BẬT/TẮT VÀO DÒNG VẾT, không chỉ mã mẫu.

               Tắt một mẫu là thao tác có hậu quả lớn nhất ở màn này và cũng
               là thao tác im lặng nhất: từ lúc ấy hệ thống KHÔNG sinh lá thư
               nào cho sự kiện đó, mà hàng chờ thì trông y hệt một tuần vắng
               khách. Một dòng vết chỉ nói 'Sửa mẫu thư "don.huy"' không trả
               lời được câu "vì sao từ tháng Mười không khách nào nhận thư báo
               huỷ đơn" — mà đó đúng là câu sẽ được hỏi.

               user_id NULL: mẫu thư không thuộc về khách nào. actor_id — do
               write() tự lấy từ phiên — mới là thứ cần ở đây, cùng lối với
               vết điều chỉnh kho. */
            $doiCo = ($kq['bat_cu'] ?? null) !== ($kq['bat_moi'] ?? null);

            AuditLogModel::write(null, 'email.template', sprintf(
                'Sửa mẫu thư "%s"%s',
                $key,
                $doiCo ? (($kq['bat_moi'] ?? 0) === 1 ? ' — BẬT lại' : ' — TẮT, thôi gửi loại thư này') : ''
            ));

            flash('admin_success', 'Đã lưu mẫu thư.');
            redirect(self::VE_MAU);
        }

        flash('admin_error', $kq['error']);

        /* GIỮ LẠI THỨ VỪA GÕ. Không có dòng này thì màn sửa dựng lại form từ
           CSDL, tức là mười dòng ruột thư vừa viết biến mất chỉ vì bỏ trống ô
           tiêu đề. flash() sống đúng một lượt nên nó tự dọn sau khi vẽ xong. */
        flash('mau_thu_nhap', (string) json_encode([
            'subject_vi' => (string) ($_POST['subject_vi'] ?? ''),
            'body_vi'    => (string) ($_POST['body_vi'] ?? ''),
            'subject_en' => (string) ($_POST['subject_en'] ?? ''),
            'body_en'    => (string) ($_POST['body_en'] ?? ''),
            'bat'        => ($_POST['bat'] ?? '') === '1' ? 1 : 0,
        ], JSON_UNESCAPED_UNICODE));

        // Về LẠI FORM đang sửa, không về danh sách.
        redirect(self::VE_MAU . '?sua=' . rawurlencode($key));
    }
}
