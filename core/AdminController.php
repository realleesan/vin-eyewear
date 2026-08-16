<?php

/**
 * core/AdminController.php
 *
 * Lớp cha của MỌI controller trong khu quản trị.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KIỂM QUYỀN Ở CONSTRUCTOR — CHỦ Ý
 *
 * Đặt AuthMiddleware::requireStaff() trong constructor nghĩa là mọi controller
 * kế thừa lớp này đều được chặn, kể cả action mới thêm sau này. Nếu để mỗi
 * action tự gọi, chỉ cần một người quên một dòng là trang quản trị đó mở cho
 * tất cả — và không có gì báo cho ai biết.
 *
 * Đây là nơi thay thế policy "admin products", "staff orders"… của Postgres.
 * ─────────────────────────────────────────────────────────────────────────────
 */

abstract class AdminController extends BaseController
{
    /** Id nhân viên đang đăng nhập. */
    protected string $userId;

    public function __construct()
    {
        $this->userId = AuthMiddleware::requireStaff();
    }

    /**
     * Render trang quản trị — dùng layout riêng, không dùng master.php của
     * site bán hàng (khu quản trị không cần header/footer, mega menu, giỏ hàng).
     */
    protected function renderAdmin(string $viewName, array $data = []): void
    {
        $data['viewName']    = $viewName;
        $data['adminUser']   = UserModel::profile($this->userId);
        $data['adminRoles']  = UserModel::roles($this->userId);
        // Huy hiệu "liên hệ chưa xử lý" trên menu — tính một lần cho mọi trang
        $data['pendingContacts'] = ContactModel::countNew();
        // Yêu cầu quên mật khẩu chưa xử lý — trả 0 khi chưa chạy file nâng cấp
        $data['pendingResets']   = PasswordResetModel::countPending();
        // Đánh giá chờ duyệt — trả 0 khi chưa chạy file nâng cấp
        $data['pendingReviews']  = ReviewModel::countPending();

        extract($data);

        require VIEWS_PATH . '/admin/_layout/master.php';
    }

    /**
     * Chặn thao tác ghi không phải POST kèm token CSRF hợp lệ.
     */
    protected function requirePost(string $fallback): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            redirect($fallback);
        }

        if (!csrfCheck($_POST['_token'] ?? null)) {
            flash('admin_error', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
            redirect($fallback);
        }
    }

    /**
     * Chỉ admin/manager mới được sửa dữ liệu catalog.
     *
     * Khớp policy gốc: "admin products/categories/events/stores" giới hạn ở
     * admin và manager, còn staff chỉ xem được đơn hàng và lịch hẹn.
     */
    protected function requireManager(string $fallback): void
    {
        if (!UserModel::hasRole($this->userId, 'admin')
            && !UserModel::hasRole($this->userId, 'manager')) {
            flash('admin_error', 'Bạn không có quyền thực hiện thao tác này.');
            redirect($fallback);
        }
    }
}
