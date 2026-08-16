<?php

/**
 * PasswordResetAdminController — xử lý tay yêu cầu quên mật khẩu.
 *
 * Đây là ĐƯỜNG DỰ PHÒNG cho hosting không gửi được email (InfinityFree bản
 * miễn phí chặn cả mail() lẫn cổng SMTP). Quy trình:
 *
 *   1. Khách bấm "Quên mật khẩu", nhập email hoặc số điện thoại.
 *   2. Yêu cầu hiện ở trang này, trạng thái "Chờ xử lý".
 *   3. Nhân viên GỌI ĐIỆN xác minh đúng người — bước này là bảo mật thật sự
 *      của cả luồng, không phải thủ tục.
 *   4. Bấm "Tạo liên kết". Liên kết hiện ra ĐÚNG MỘT LẦN để đọc qua điện
 *      thoại hoặc gửi Zalo. Tải lại trang là không xem lại được.
 *
 * Yêu cầu quyền quản lý (không chỉ nhân viên): tạo được liên kết đặt lại mật
 * khẩu nghĩa là chiếm được tài khoản bất kỳ.
 */

class PasswordResetAdminController extends AdminController
{
    private const BASE = '/quan-tri/quen-mat-khau';

    public function index(): void
    {
        $this->renderAdmin('admin/resets/index', [
            'pageTitle' => 'Quên mật khẩu — Quản trị',
            'requests'  => PasswordResetModel::pending(),
            'available' => PasswordResetModel::available(),
            'canIssue'  => $this->canIssue(),
            'canDeliver'=> Mailer::canDeliver(),
            'mailDriver'=> (string) config('mail.driver', 'log'),
            // Liên kết vừa tạo — chỉ tồn tại trong đúng lượt tải này
            'freshLink' => flash('reset_link'),
            'freshFor'  => flash('reset_for'),
            'error'     => flash('admin_error'),
        ]);
    }

    public function issue(): void
    {
        $this->requirePost(self::BASE);

        // Tạo được liên kết đặt lại nghĩa là chiếm được tài khoản bất kỳ, nên
        // giới hạn ở quản lý/quản trị — nhân viên bán hàng chỉ xem danh sách.
        $this->requireManager(self::BASE);

        $result = PasswordResetModel::issueByStaff(
            (string) ($_POST['id'] ?? ''),
            $this->userId
        );

        if (!$result['ok']) {
            flash('admin_error', $result['error']);
            redirect(self::BASE);
        }

        // Qua flash chứ không qua query string: liên kết này là chìa khoá đổi
        // mật khẩu, không nên nằm trong địa chỉ trình duyệt — chỗ đó bị lưu
        // vào lịch sử, vào log máy chủ, và lộ ra khi chụp màn hình.
        flash('reset_link', $result['link']);
        flash('reset_for', (string) ($_POST['contact'] ?? ''));

        redirect(self::BASE);
    }

    private function canIssue(): bool
    {
        return UserModel::hasRole($this->userId, 'admin')
            || UserModel::hasRole($this->userId, 'manager');
    }
}
