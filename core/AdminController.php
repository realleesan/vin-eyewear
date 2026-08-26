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
        /*
         * HUY HIỆU TRÊN THANH BÊN — theo "Vin Eyewear Admin.dc.html".
         *
         * Bản thiết kế đeo số cho bốn mục: Đơn hàng, Lịch hẹn, Liên hệ, Quên
         * mật khẩu. Điểm chung là cả bốn đều là HÀNG CHỜ CÓ NGƯỜI ĐANG ĐỢI ở
         * đầu bên kia. Sản phẩm, danh mục, cơ sở thì không — chúng có bao
         * nhiêu dòng cũng không ai phải làm gì cả, đeo số vào chỉ là nhiễu.
         *
         * Đơn hàng và Lịch hẹn gộp MỘT câu lệnh: hai bảng này luôn có trong
         * schema gốc nên không cần lối thoát "chưa chạy file nâng cấp" như ba
         * dòng dưới, và một vòng đi-về tới DB rẻ hơn hai.
         */
        $queues = Database::fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM orders WHERE status = 'new')           AS orders,
                (SELECT COUNT(*) FROM appointments WHERE status = 'pending') AS appointments"
        );

        $data['pendingOrders']       = (int) ($queues['orders'] ?? 0);
        $data['pendingAppointments'] = (int) ($queues['appointments'] ?? 0);

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
     * Chặn trường hợp gói POST vượt post_max_size của PHP.
     *
     * Khi đó PHP vứt SẠCH $_POST và $_FILES trước khi một dòng mã nào của ta
     * chạy — kể cả _token — nên requirePost() sẽ kết luận là hết hạn phiên và
     * báo "Phiên làm việc đã hết hạn, vui lòng thử lại.". Câu đó sai, và nó
     * đẩy người dùng đi đăng nhập lại thay vì bớt ảnh đi. Dấu hiệu nhận ra:
     * đúng là POST, CONTENT_LENGTH lớn hơn trần, mà $_POST lại rỗng.
     *
     * Gọi TRƯỚC requirePost() ở mọi màn có ô tải file lên.
     */
    protected function guardPostSize(string $fallback): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $_POST !== []) {
            return;
        }

        $limit = self::iniBytes((string) ini_get('post_max_size'));

        if ($limit > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit) {
            flash('admin_error', sprintf(
                'Tổng dung lượng gửi lên vượt giới hạn của máy chủ (%s). Hãy tải ít ảnh hơn trong một lần.',
                (string) ini_get('post_max_size')
            ));
            redirect($fallback);
        }
    }

    /** Đổi giá trị php.ini kiểu "8M", "128M", "1G" ra byte. */
    private static function iniBytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => $number,
        };
    }

    /**
     * Chỉ admin/manager mới được sửa dữ liệu catalog.
     *
     * Khớp policy gốc: "admin products/categories/events/stores" giới hạn ở
     * admin và manager, còn staff chỉ xem được đơn hàng và lịch hẹn.
     *
     * Trích nguyên văn policy nên vẫn có chữ "events" — bảng đó đã bỏ
     * 2026-08-26, luật còn lại áp cho ba nhóm kia và cho bộ sưu tập.
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
