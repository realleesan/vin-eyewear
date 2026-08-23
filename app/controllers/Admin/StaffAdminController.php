<?php

/**
 * Admin/StaffAdminController.php — tài khoản nội bộ, và đặt lại mật khẩu cho
 * người khác.
 *
 *   GET  /quan-tri/nhan-vien          index()
 *   POST /quan-tri/nhan-vien/dat-lai  resetPassword()
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO CÓ TRANG NÀY: ĐỂ BÀN GIAO ĐƯỢC
 *
 * Trước bản này, cấp lại mật khẩu cho một nhân viên chỉ có hai đường, và cả
 * hai đều đòi thứ mà chủ cửa hàng không nên phải có:
 *
 *   · chạy `php database/make-admin.php --reset-password <email>` — cần dòng
 *     lệnh trên máy có mã nguồn và có .env trỏ đúng cơ sở dữ liệu;
 *   · mở phpMyAdmin gõ UPDATE thẳng vào bảng `users`.
 *
 * Đường thứ hai là đường đã phải dùng ngày 2026-08-23 để lấy lại quyền trên
 * hosting. Nó chạy được, nhưng không phải thứ đưa cho người được bàn giao:
 * một cú UPDATE gõ thiếu WHERE là đổi mật khẩu của toàn bộ tài khoản.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * AI ĐƯỢC ĐẶT LẠI CHO AI
 *
 *   · CHỈ vai trò 'admin'. Không phải 'manager' như trang "Quên mật khẩu" của
 *     khách — mật khẩu ở đây mở ra chính khu quản trị, còn mật khẩu bên kia
 *     chỉ mở tài khoản mua hàng của một người khách.
 *
 *   · CHỈ tài khoản NỘI BỘ (staff · manager · admin). Khách hàng đi đường
 *     riêng: /quen-mat-khau, và PasswordResetAdminController cho ca không
 *     nhận được mã — đường đó bắt gọi điện xác minh trước, một bước bảo mật
 *     mà trang này cố tình không có vì nhân viên thì ngồi ngay đó.
 *
 *   · KHÔNG đặt lại cho CHÍNH MÌNH. Đổi mật khẩu của bản thân đi qua
 *     /quan-tri/doi-mat-khau, nơi phải gõ mật khẩu hiện tại. Nếu cho tự đặt
 *     lại ở đây thì một máy bỏ quên chưa khoá màn hình là chiếm được vĩnh
 *     viễn tài khoản đó mà không cần biết mật khẩu cũ — trong khi người chủ
 *     thật vẫn tưởng mình an toàn vì "không ai biết mật khẩu của tôi".
 *
 *     Admin cuối cùng tự khoá mình ra ngoài thì vẫn còn make-admin.php và
 *     phpMyAdmin. Mất một lối tắt, đổi lấy việc bịt một đường chiếm quyền.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class StaffAdminController extends AdminController
{
    private const BASE = '/quan-tri/nhan-vien';

    public function index(): void
    {
        $this->renderAdmin('admin/staff/index', [
            'pageTitle' => 'Tài khoản nội bộ — Quản trị',
            'accounts'  => UserModel::staffAccounts(),
            'me'        => $this->userId,
            'canReset'  => UserModel::hasRole($this->userId, 'admin'),

            /* Mật khẩu vừa cấp — CHỈ sống trong đúng lượt tải này, vì nó đi
               qua flash. Tải lại trang là mất, đúng như mong muốn: đây là
               chìa khoá vào khu quản trị, không nên nằm mãi trên màn hình
               giữa văn phòng. Cùng cách làm với liên kết đặt lại ở
               PasswordResetAdminController. */
            'freshPass' => flash('staff_password'),
            'freshFor'  => flash('staff_password_for'),
        ]);
    }

    public function resetPassword(): void
    {
        $this->requirePost(self::BASE);

        /* requireManager() KHÔNG đủ ở đây — xem khối "AI ĐƯỢC ĐẶT LẠI CHO AI"
           đầu file. Quản lý cửa hàng cấp lại được mật khẩu cho khách, nhưng
           không nên cấp lại được mật khẩu mở chính khu quản trị. */
        if (!UserModel::hasRole($this->userId, 'admin')) {
            flash('admin_error', 'Chỉ tài khoản quản trị mới đặt lại được mật khẩu nội bộ.');
            redirect(self::BASE);
        }

        $target = (string) ($_POST['id'] ?? '');

        if ($target === $this->userId) {
            flash('admin_error',
                'Mật khẩu của chính bạn đổi ở mục "Đổi mật khẩu" — ở đó cần gõ mật khẩu hiện tại.');
            redirect(self::BASE);
        }

        $result = UserModel::resetPasswordFor($target);

        if (!$result['ok']) {
            flash('admin_error', $result['error']);
            redirect(self::BASE);
        }

        $who = UserModel::profile($target);

        flash('staff_password', $result['password']);
        flash('staff_password_for', (string) ($who['email'] ?? $who['full_name'] ?? $target));
        flash('admin_success', 'Đã đặt lại mật khẩu. Đọc cho người đó rồi bảo họ đổi lại ngay.');

        redirect(self::BASE);
    }
}
