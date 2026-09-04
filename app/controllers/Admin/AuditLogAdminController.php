<?php

/**
 * Admin/AuditLogAdminController.php — màn Lịch sử thao tác.
 *
 *   GET /quan-tri/nhat-ky           index()  danh sách có lọc và phân trang
 *   GET /quan-tri/nhat-ky/xuat      xuat()   tải về CSV theo đúng bộ lọc đang xem
 *
 * Hiện thực UC-3.2.10.2 (Theo dõi lịch sử thao tác) và vế "đọc được" của
 * SNFR-11.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO MÀN NÀY ĐÁNG LÀM TRƯỚC CÁC MODULE CÒN THIẾU KHÁC
 *
 * Hệ thống đã ghi vết vào `customer_audit_logs` từ lâu, và ngày 02/09/2026 còn
 * mở rộng thêm sáu mã hành động cho thao tác tiền và kho. Nhưng cho tới trước
 * file này, KHÔNG CÓ MÀN NÀO ĐỌC bảng ấy — AuditLogModel::forUser() chỉ được
 * gọi từ tab Hoạt động của một khách cụ thể, còn vết của thao tác kho hay thao
 * tác tiền thì không có đường nào xem.
 *
 * Một bảng vết ghi ra mà không ai đọc được là thứ tệ hơn không có bảng vết:
 * nếu việc ghi vết hỏng, sẽ không ai phát hiện. Đó đúng là "cảm giác an toàn
 * giả" mà khối chú thích đầu AuditLogModel cảnh báo.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * PHẠM VI QUYỀN — TẠM CHỐT Ở 'admin', CHỜ BẢNG PHÂN QUYỀN
 *
 * Phương án mặc định đã gửi BA (mục M40 trong phiếu làm rõ yêu cầu) là:
 * "Quản trị viên và Chủ doanh nghiệp xem toàn bộ, Quản lý cơ sở chỉ xem cơ sở
 * mình."
 *
 * Vế sau CHƯA LÀM ĐƯỢC: hệ thống chưa biết nhân viên nào thuộc cơ sở nào
 * (bảng `user_roles` không có `store_id` — đang chờ câu Q12), và bản thân bảng
 * vết cũng không có cột cơ sở. Cho Quản lý cơ sở vào lúc này nghĩa là họ xem
 * được vết của cả hai cơ sở, tức là rộng hơn hẳn thứ BA được hỏi.
 *
 * Nên tạm siết ở 'admin'. Đây là hướng an toàn: nới quyền ra sau khi có câu
 * trả lời chỉ là sửa một dòng, còn thu quyền lại sau khi người ta đã quen xem
 * thì vừa khó vừa mất lòng.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class AuditLogAdminController extends AdminController
{
    /** Số dòng mỗi trang. Vết đọc theo lô nên để dày hơn các màn CRUD. */
    private const MOI_TRANG = 30;

    /**
     * Chỉ 'admin'.
     *
     * Kiểm ở đây chứ không dựa vào việc giấu mục khỏi thanh bên: giấu là
     * chuyện gọn mắt, chặn mới là phân quyền — đúng nguyên tắc 4 của CLAUDE.md.
     */
    private function chanNeuKhongPhaiAdmin(): void
    {
        if (!UserModel::hasRole($this->userId, 'admin')) {
            http_response_code(403);
            (new ErrorController())->forbidden();
            exit;
        }
    }

    /**
     * Đọc bộ lọc từ địa chỉ, bỏ qua mọi giá trị lạ.
     *
     * Giá trị lạ thì coi như KHÔNG LỌC chứ không trả trang lỗi: một đường dẫn
     * bị sửa tay hoặc một liên kết cũ nên dẫn về danh sách đầy đủ, không nên
     * dựng lên một trang 400 mà người đọc không biết sửa gì.
     */
    private function boLoc(): array
    {
        $nhom   = (string) ($_GET['nhom'] ?? '');
        $action = (string) ($_GET['hanh-dong'] ?? '');
        $tu     = (string) ($_GET['tu'] ?? '');
        $den    = (string) ($_GET['den'] ?? '');

        if (!isset(AuditLogModel::NHOM[$nhom])) {
            $nhom = '';
        }

        if (!isset(AuditLogModel::ACTIONS[$action])) {
            $action = '';
        }

        // Chỉ nhận đúng dạng YYYY-MM-DD. Ô <input type="date"> luôn gửi đúng
        // dạng này; thứ khác là do sửa địa chỉ tay.
        $ngay = static fn (string $v): string
            => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : '';

        $tu  = $ngay($tu);
        $den = $ngay($den);

        // Gõ ngược khoảng ngày là lỗi gõ, không phải ý định. Đảo lại giúp được
        // mà không cần bắt người dùng đọc thông báo lỗi rồi sửa tay.
        if ($tu !== '' && $den !== '' && $tu > $den) {
            [$tu, $den] = [$den, $tu];
        }

        return [
            'nhom'   => $nhom,
            'action' => $action,
            'actor'  => trim((string) ($_GET['nguoi'] ?? '')),
            'tu'     => $tu,
            'den'    => $den,
            'q'      => trim((string) ($_GET['q'] ?? '')),
        ];
    }

    public function index(): void
    {
        $this->chanNeuKhongPhaiAdmin();

        $loc  = $this->boLoc();
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $kq = AuditLogModel::paginateAll($page, self::MOI_TRANG, $loc);

        $this->renderAdmin('admin/audit-logs/index', [
            'pageTitle'  => 'Lịch sử thao tác — Quản trị',
            'logs'       => $kq['items'],
            'total'      => $kq['total'],
            'page'       => $kq['page'],
            'totalPages' => $kq['totalPages'],
            'loc'        => $loc,
            'demNhom'    => AuditLogModel::demTheoNhom($loc),
            'nguoiList'  => AuditLogModel::dsNguoiThaoTac(),
            /* Chưa chạy migration hoặc bảng bị xoá thì cả trang vẫn mở được,
               chỉ hiện một dải cảnh báo. Cùng cách làm với cột zalo_sent_at ở
               màn Liên hệ: một bảng thiếu không được phép làm đổ trang. */
            'coBang'     => AuditLogModel::available(),
            /* CHÍNH SÁCH LƯU GIỮ NÓI RA TRÊN MÀN HÌNH — X28 / Q80.3.

               Người mở màn này thường đang trả lời một câu hỏi có mốc thời
               gian ("hồi tháng 3 ai sửa cái này?"), và câu trả lời phụ thuộc
               vào việc dữ liệu tháng 3 còn hay không. Bắt họ mở SRS để biết là
               bắt họ đoán. Hai con số, một chính sách một thực tế:

                 giữ tối thiểu   cam kết — 24 tháng
                 vết cũ nhất     thực tế đang có trong bảng */
            'giuThang'   => AuditLogModel::GIU_TOI_THIEU_THANG,
            'vetCuNhat'  => AuditLogModel::vetCuNhat(),
        ]);
    }

    /**
     * Tải về CSV theo ĐÚNG bộ lọc đang xem.
     *
     * Dùng lại boLoc() nên file tải về luôn khớp thứ đang hiển thị. Nếu đọc bộ
     * lọc riêng ở đây thì sớm muộn hai chỗ lệch nhau, và người đối soát cầm về
     * một file không đúng thứ họ nhìn thấy trên màn hình.
     *
     * CSV chứ không phải .xlsx: hosting không cài được thư viện đọc/ghi Excel
     * (xem câu Q22 trong phiếu làm rõ yêu cầu). CSV mở thẳng bằng Excel.
     */
    public function xuat(): void
    {
        $this->chanNeuKhongPhaiAdmin();

        $loc   = $this->boLoc();
        $dong  = AuditLogModel::deXuat($loc);
        $ten   = 'nhat-ky-thao-tac-' . date('Ymd-His') . '.csv';

        /* Ghi thẳng ra output chứ không dựng chuỗi trong bộ nhớ: 5000 dòng
           gộp thành một chuỗi là vài MB, và trên gói hosting hạn chế bộ nhớ
           thì đó là ranh giới giữa "tải được" và "trang trắng". */
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $ten . '"');

        $out = fopen('php://output', 'w');

        /* BOM UTF-8. Không có nó thì Excel trên Windows đọc file này thành
           "Sá»­a há»“ sÆ¡" — tiếng Việt vỡ hết, và người nhận sẽ báo là
           file hỏng chứ không đoán ra là do phần mở đầu thiếu ba byte. */
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, ['Thời điểm', 'Hành động', 'Người thực hiện', 'Khách hàng', 'Số điện thoại', 'Chi tiết', 'Địa chỉ IP']);

        foreach ($dong as $d) {
            fputcsv($out, [
                $d['created_at'],
                AuditLogModel::ACTIONS[$d['action']] ?? $d['action'],
                $d['actor_name'] ?? 'Hệ thống',
                $d['khach_ten'] ?? '',
                $d['khach_sdt'] ?? '',
                $d['detail'] ?? '',
                $d['ip'] ?? '',
            ]);
        }

        /* Nói rõ khi file bị cắt vì chạm trần, ngay trong chính file. Im lặng
           cắt bớt một file dùng để ĐỐI SOÁT là cách chắc chắn để ai đó kết
           luận sai từ một bảng thiếu dòng. */
        if (count($dong) >= AuditLogModel::TRAN_XUAT) {
            fputcsv($out, []);
            fputcsv($out, [
                'Ghi chú: file đã đạt trần ' . AuditLogModel::TRAN_XUAT
                . ' dòng và có thể còn thiếu. Vui lòng thu hẹp khoảng ngày rồi xuất lại.',
            ]);
        }

        fclose($out);
        exit;
    }
}
