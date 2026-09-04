<?php

/**
 * SepayAdminController — màn "Giao dịch chưa khớp" (/quan-tri/giao-dich).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MÀN NÀY TỒN TẠI VÌ WEBHOOK KHÔNG BAO GIỜ ĐÚNG 100%
 *
 * SePay gửi về mọi biến động số dư. SepayModel::handle() đọc mã đơn ra khỏi
 * nội dung chuyển khoản và tự khớp — nhưng nội dung ấy là chữ do NGÂN HÀNG
 * ghi lại, và khách thì gõ tay. Mã sai một ký tự, khách gõ "chuyen tien mua
 * kinh", hay khách chuyển từ tài khoản của người nhà: tất cả rơi xuống
 * `applied = 'no_order'` và nằm đó.
 *
 * Không có màn này thì tiền đã về tài khoản mà đơn vẫn "chưa thanh toán", và
 * cách duy nhất để sửa là ai đó vào phpMyAdmin gõ UPDATE — không lý do, không
 * vết, không ai duyệt.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HAI BƯỚC, HAI NGƯỜI — X13, chốt lại 04/09/2026
 *
 *   bước 1  NHÂN VIÊN gán giao dịch vào một đơn, bắt buộc ghi lý do
 *   bước 2  QUẢN LÝ CƠ SỞ xác nhận (hoặc từ chối), bắt buộc ghi lý do
 *
 * Bản chốt vòng 1 giao cả hai việc cho Quản trị viên. Rà soát độc lập chỉ ra
 * rằng đối soát là việc HẰNG NGÀY còn Quản trị viên chỉ có một người — dồn cả
 * hai vào một chỗ biến nó thành nút cổ chai ở đúng khâu tiền.
 *
 * NGƯỜI GÁN KHÔNG TỰ XÁC NHẬN. Chặn ở model (SepayModel::xacNhan) chứ không
 * chỉ ẩn nút: đây đúng là thao tác đáng để ai đó dựng một cú POST bằng tay.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO KHÔNG LỌC THEO PHẠM VI CƠ SỞ
 *
 * Tiền về MỘT tài khoản ngân hàng của doanh nghiệp, không về tài khoản của
 * từng cơ sở. Một giao dịch chưa khớp thì chưa biết nó thuộc đơn nào, nên chưa
 * biết nó thuộc cơ sở nào — lọc theo cơ sở ở đây là lọc theo một thứ chưa tồn
 * tại, và hệ quả là những khoản khó nhất (không đoán được đơn) sẽ không hiện
 * cho ai cả.
 *
 * Bù lại bằng vai trò: chỉ Quản trị viên và Quản lý cơ sở mở được màn này.
 * ⚠ Đây là lựa chọn của nhóm phát triển, ghi lại để BA xác nhận.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class SepayAdminController extends AdminController
{
    private const BASE = '/quan-tri/giao-dich';

    /** Trần số dòng lấy về. Màn đối soát đọc trong ngày, không phải kho lưu trữ. */
    private const TRAN = 100;

    public function index(): void
    {
        $this->requireManager('/quan-tri');

        /* Kéo hàng đợi webhook trước khi vẽ — cùng lý do với màn Đơn hàng:
           hosting không có cron nên "định kỳ" chỉ có thể ăn theo lượt truy cập,
           và người mở đúng màn này là người đang định đối chiếu sao kê. */
        SepayRelay::keo();

        $this->renderAdmin('admin/sepay/index', [
            'pageTitle' => 'Giao dịch chưa khớp — Quản trị',
            'rows'      => SepayModel::canDoiSoat(self::TRAN),
            'coBang'    => SepayModel::available(),
            'coHaiBuoc' => SepayModel::coHaiBuoc(),
            /* Ai đang xem — view dùng để biết vẽ nút nào. CHỈ ĐỂ VẼ; chặn thật
               nằm ở model và ở requireManager() phía trên. */
            'toiLa'     => $this->userId,
            'lyDoToiThieu' => SepayModel::LY_DO_TOI_THIEU,
            'adminStyles'  => ['assets/css/admin-orders.css'],
        ]);
    }

    /** BƯỚC 1 — nhân viên gán giao dịch vào đơn (POST). */
    public function assign(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager('/quan-tri');

        $ket = SepayModel::gan(
            (string) ($_POST['id'] ?? ''),
            (string) ($_POST['ma_don'] ?? ''),
            (string) ($_POST['ly_do'] ?? ''),
            $this->userId
        );

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok']
                ? 'Đã gán vào đơn ' . $ket['ma_don'] . '. Chờ người khác xác nhận thì tiền mới vào đơn.'
                : $ket['error']);

        redirect(self::BASE);
    }

    /** BƯỚC 2 — xác nhận. Đây là chỗ tiền vào đơn (POST). */
    public function confirm(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager('/quan-tri');

        $ket = SepayModel::xacNhan(
            (string) ($_POST['id'] ?? ''),
            (string) ($_POST['ly_do'] ?? ''),
            $this->userId
        );

        if (!$ket['ok']) {
            flash('admin_error', $ket['error']);
            redirect(self::BASE);
        }

        /* NÓI RÕ TIỀN ĐÃ ĐI TỚI ĐÂU. "Đã xác nhận" là câu nói về thao tác, còn
           thứ người bấm cần biết là ĐƠN giờ đứng ở đâu — nhất là nhánh
           'partial', nơi thao tác thành công nhưng đơn vẫn chưa đủ cọc. */
        $noi = [
            'paid'         => 'Đơn đã đủ tiền.',
            'deposit_paid' => 'Đơn đã đủ tiền cọc.',
            'partial'      => 'Khoản tiền đã thuộc về đơn, nhưng VẪN CHƯA ĐỦ CỌC — đơn chưa chuyển bước.',
        ];

        flash('admin_success',
            'Đã xác nhận. ' . ($noi[$ket['trang_thai']] ?? ''));

        redirect(self::BASE);
    }

    /** BƯỚC 2, nhánh từ chối — trả giao dịch về hàng chờ (POST). */
    public function reject(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager('/quan-tri');

        $ket = SepayModel::tuChoi(
            (string) ($_POST['id'] ?? ''),
            (string) ($_POST['ly_do'] ?? ''),
            $this->userId
        );

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok'] ? 'Đã trả giao dịch về hàng chờ để gán lại.' : $ket['error']);

        redirect(self::BASE);
    }
}
