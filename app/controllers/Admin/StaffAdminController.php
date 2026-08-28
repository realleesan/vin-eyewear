<?php

/**
 * Admin/StaffAdminController.php — tài khoản nội bộ, và đặt lại mật khẩu cho
 * người khác.
 *
 *   GET  /quan-tri/nhan-vien          index()        (?them=1 · ?sua=<id> mở hộp)
 *   POST /quan-tri/nhan-vien/luu      save()         thêm mới / sửa
 *   POST /quan-tri/nhan-vien/khoa     toggleLock()   khoá / mở khoá
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

    /**
     * Ba vai trò nội bộ, nhãn tiếng Việt.
     *
     * Bản vẽ chỉ vẽ hai ('Nhân viên' và 'Quản trị') nhưng 'manager' đã tồn tại
     * trong ENUM của `user_roles` và trong ma trận quyền ở mục 3.A.2 của đặc
     * tả — bỏ nó khỏi ô chọn thì tài khoản quản lý đang có sẽ không sửa được
     * nữa mà không có gì báo.
     */
    private const VAI_TRO = [
        'staff'   => 'Nhân viên',
        'manager' => 'Quản lý',
        'admin'   => 'Quản trị',
    ];

    public function index(): void
    {
        $this->renderAdmin('admin/staff/index', [
            'pageTitle' => 'Tài khoản nội bộ — Quản trị',
            'accounts'  => UserModel::staffAccounts(),
            'me'        => $this->userId,
            'canReset'  => UserModel::hasRole($this->userId, 'admin'),
            /* Bản ghi đang sửa. Đọc qua staffAccounts() chứ không find() thẳng:
               chỉ tài khoản NỘI BỘ mới lọt qua đó, nên ?sua=<id khách> không mở
               ra được form sửa cho một khách hàng. */
            'editing'   => $this->timTaiKhoan((string) ($_GET['sua'] ?? '')),
            'roles'     => self::VAI_TRO,
            /* Mật khẩu tạm in sẵn vào ô khi mở form thêm mới — bản vẽ làm vậy,
               và nó đúng: để trống thì người ta gõ "123456" cho nhanh. Sinh ở
               controller chứ không ở view vì view không nên có logic sinh bí
               mật, và cũng để form giữ nguyên chuỗi cũ khi lưu hỏng. */
            'newPass'   => UserModel::randomPassword(12),

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

    /**
     * Thêm mới hoặc sửa một tài khoản nội bộ.
     *
     * MỘT endpoint cho cả hai, phân biệt bằng ô `id` rỗng hay không — cùng nếp
     * với mọi màn CRUD khác của khu quản trị (xem ProductAdminController::save).
     */
    public function save(): void
    {
        $this->requirePost(self::BASE);
        $this->requireAdmin();

        $id    = trim((string) ($_POST['id'] ?? ''));
        $ten   = trim((string) ($_POST['full_name'] ?? ''));
        $vaiTro = (string) ($_POST['role'] ?? '');

        if ($id !== '') {
            /* SỬA CHÍNH MÌNH THÌ KHÔNG ĐƯỢC ĐỔI VAI TRÒ.
               Người đang là quản trị duy nhất mà tự hạ mình xuống nhân viên là
               khoá cả cửa hàng ra ngoài trang này — không ai còn quyền nâng
               ai lên nữa. Tên thì đổi thoải mái. */
            if ($id === $this->userId) {
                $vaiTro = $this->vaiTroChinh($id);
            }

            $ket = UserModel::updateStaff($id, $ten, $vaiTro);

            if (!$ket['ok']) {
                flash('admin_error', $ket['error']);
                redirect(self::BASE);
            }

            flash('admin_success', 'Đã lưu thay đổi.');
            redirect(self::BASE);
        }

        $email  = trim((string) ($_POST['email'] ?? ''));
        $matKhau = (string) ($_POST['password'] ?? '');

        $ket = UserModel::createStaff($email, $matKhau, $ten, $vaiTro);

        if (!$ket['ok']) {
            flash('admin_error', $ket['error']);
            redirect(self::BASE);
        }

        /* HIỆN LẠI MẬT KHẨU TẠM ĐÚNG MỘT LƯỢT, cùng đường với resetPassword().

           Người vừa tạo tài khoản có thể không đọc kịp chuỗi mình vừa gõ hoặc
           vừa để hệ thống sinh — mà sau lượt tải này thì không có cách nào lấy
           lại, chỉ còn đặt lại một lần nữa. */
        flash('staff_password', $matKhau);
        flash('staff_password_for', $email);
        flash('admin_success', 'Đã tạo tài khoản. Đọc mật khẩu tạm cho người đó rồi bảo họ đổi lại ngay.');

        redirect(self::BASE);
    }

    /**
     * Khoá hoặc mở khoá một tài khoản nội bộ.
     *
     * Hướng đi kèm trong `khoa` (1 = khoá, 0 = mở) chứ không tự lật trạng thái
     * đang có: form được dựng lúc trang tải, và giữa lúc dựng với lúc bấm có
     * thể một người khác đã đổi trạng thái rồi. Tự lật thì cú bấm làm ngược
     * hẳn thứ nhãn trên nút đang hứa.
     */
    public function toggleLock(): void
    {
        $this->requirePost(self::BASE);
        $this->requireAdmin();

        $id   = (string) ($_POST['id'] ?? '');
        $khoa = (string) ($_POST['khoa'] ?? '') === '1';

        $ket = UserModel::setStaffLock($id, $khoa, $this->userId);

        if (!$ket['ok']) {
            flash('admin_error', $ket['error']);
            redirect(self::BASE);
        }

        $ai = UserModel::profile($id);
        $ten = (string) ($ai['full_name'] ?? $ai['email'] ?? 'tài khoản');

        flash('admin_success', $khoa
            ? 'Đã khoá tài khoản ' . $ten
            : 'Đã mở khoá tài khoản ' . $ten);

        redirect(self::BASE);
    }

    /**
     * Chốt quyền dùng chung cho ba thao tác ghi.
     *
     * requireManager() KHÔNG đủ — xem khối "AI ĐƯỢC ĐẶT LẠI CHO AI" đầu file.
     * Quản lý cửa hàng cấp lại được mật khẩu cho khách, nhưng không nên tự tạo
     * cho mình một tài khoản quản trị mới.
     */
    private function requireAdmin(): void
    {
        if (!UserModel::hasRole($this->userId, 'admin')) {
            flash('admin_error', 'Chỉ tài khoản quản trị mới quản lý được tài khoản nội bộ.');
            redirect(self::BASE);
        }
    }

    /**
     * Vai trò nội bộ CAO NHẤT của một tài khoản.
     *
     * Bản vẽ cho mỗi tài khoản đúng một vai trò, nhưng dữ liệu cũ có thể mang
     * nhiều dòng trong `user_roles` (make-admin.php gán thêm chứ không thay).
     * Lấy cái cao nhất vì đó là cái đang thật sự quyết định người ấy làm được
     * gì — hiện "Nhân viên" cho một tài khoản vẫn còn vai trò admin là nói sai
     * về quyền nó đang có.
     */
    private function vaiTroChinh(string $userId): string
    {
        foreach (['admin', 'manager', 'staff'] as $vt) {
            if (UserModel::hasRole($userId, $vt)) {
                return $vt;
            }
        }

        return 'staff';
    }

    /**
     * Một tài khoản NỘI BỘ theo id, hoặc null.
     *
     * @return array<string,mixed>|null
     */
    private function timTaiKhoan(string $id): ?array
    {
        if ($id === '') {
            return null;
        }

        foreach (UserModel::staffAccounts() as $a) {
            if ($a['id'] === $id) {
                return $a;
            }
        }

        return null;
    }

}
