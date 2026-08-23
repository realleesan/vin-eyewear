<?php

/**
 * Admin/AccountAdminController.php — nhân viên tự đổi mật khẩu của CHÍNH MÌNH.
 *
 *   GET  /quan-tri/doi-mat-khau       index()
 *   POST /quan-tri/doi-mat-khau/luu   save()
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO KHÔNG CHỈ ĐẶT MỘT LIÊN KẾT SANG /tai-khoan?muc=mat-khau
 *
 * Màn đổi mật khẩu ĐÃ CÓ ở trang tài khoản khách, và nó chạy đúng. Nhưng nó
 * nằm trong giao diện bán hàng: nền be, thanh điều hướng cửa hàng, giỏ hàng,
 * sáu mục tài khoản của người mua kính. Đẩy nhân viên sang đó giữa lúc đang
 * làm việc quản trị là ném họ ra khỏi khu vực họ đang đứng, rồi để họ tự tìm
 * đường quay về.
 *
 * Đây là cùng một lý do đã sinh ra cổng đăng nhập riêng ở
 * Admin/AdminAuthController — xem khối chú thích trong app/views/admin/login.php.
 *
 * Cái KHÔNG chép lại là phần luật: UserModel::changePassword() vẫn là nơi duy
 * nhất quyết định "mật khẩu cũ có đúng không" và "mật khẩu mới có đủ dài
 * không". File này chỉ thêm vỏ giao diện và đường quay lại của riêng nó.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class AccountAdminController extends AdminController
{
    private const BASE = '/quan-tri/doi-mat-khau';

    public function index(): void
    {
        $this->renderAdmin('admin/account/mat-khau', [
            'pageTitle' => 'Đổi mật khẩu — Quản trị',
            'me'        => UserModel::profile($this->userId),
        ]);
    }

    public function save(): void
    {
        $this->requirePost(self::BASE);

        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        /* Ô "nhập lại" có `required` trong HTML, nhưng thuộc tính đó chỉ là
           gợi ý của trình duyệt — request gửi tay không đi qua nó. */
        if ($new !== $confirm) {
            flash('admin_error', 'Hai lần nhập mật khẩu mới không khớp.');
            redirect(self::BASE);
        }

        $result = UserModel::changePassword(
            $this->userId,
            (string) ($_POST['current_password'] ?? ''),
            $new
        );

        if (!$result['ok']) {
            flash('admin_error', $result['error']);
            redirect(self::BASE);
        }

        /* Đá mọi thiết bị đang "ghi nhớ đăng nhập" ra ngoài — người ta đổi mật
           khẩu chủ yếu vì nghi bị lộ, để cookie cũ trên máy lạ vẫn vào được
           thì việc đổi gần như vô nghĩa. Phiên hiện tại không ảnh hưởng, nên
           chính người vừa đổi không bị đăng xuất giữa chừng.

           Lặp lại ở đây thay vì gói vào UserModel::changePassword(): hàm đó
           còn được AuthController dùng cho khách, và gộp vào sẽ là đổi hành vi
           của một đường đang chạy tốt — việc riêng, không làm kèm ở đây. */
        RememberModel::forgetAllFor($this->userId);

        flash('admin_success', 'Đã đổi mật khẩu. Các thiết bị khác đã được đăng xuất.');
        redirect(self::BASE);
    }
}
