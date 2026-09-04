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

    /**
     * Phạm vi cơ sở của người đang đăng nhập — null · [] · [id, …].
     *
     * Nhớ trong suốt request: một trang quản trị hỏi phạm vi ở vài chỗ (đếm
     * theo trạng thái, danh sách, phân trang) và mỗi lần hỏi là một lượt đọc
     * vai trò cộng một lượt đọc bảng phân công.
     *
     * DÙNG === null ĐỂ KIỂM. Xem khối chú thích "BA TRẠNG THÁI" ở đầu
     * StaffStoreModel: `if (!$phamVi)` gộp null với [] và biến "chưa gán nên
     * không thấy gì" thành "thấy tất cả".
     *
     * @var string[]|null|false false = chưa hỏi lần nào
     */
    private array|null|false $phamViCoSo = false;

    public function __construct()
    {
        $this->userId = AuthMiddleware::requireStaff();
    }

    /**
     * Phạm vi cơ sở để lọc đơn hàng và lịch hẹn — SNFR-07b, Q12.1–Q12.3.
     *
     * @return string[]|null
     */
    protected function phamViCoSo(): ?array
    {
        if ($this->phamViCoSo === false) {
            $this->phamViCoSo = StaffStoreModel::phamVi($this->userId);
        }

        return $this->phamViCoSo;
    }

    /**
     * Người này có bị giới hạn phạm vi không — để view quyết định có hiện
     * dòng "Bạn chỉ thấy dữ liệu của cơ sở …" hay không.
     */
    protected function biGioiHanCoSo(): bool
    {
        return $this->phamViCoSo() !== null;
    }

    /**
     * Render trang quản trị — dùng layout riêng, không dùng master.php của
     * site bán hàng (khu quản trị không cần header/footer, mega menu, giỏ hàng).
     */
    protected function renderAdmin(string $viewName, array $data = []): void
    {
        /*
         * ─────────────────────────────────────────────────────────────────────
         * LƯỢT LẤY HỘP THOẠI: DỰNG MỖI RUỘT TRANG, KHÔNG DỰNG KHUNG
         *
         * admin-modal.js lấy trang này về chỉ để bóc ra đúng thẻ .amodal trong
         * đó — thanh bên, huy hiệu, thẻ <head>, đống <script> ở cuối đều bị nó
         * vứt đi. Dựng chúng ra là làm không công, mà lại là phần tốn nhất:
         * năm câu COUNT cho huy hiệu, cộng khoảng mười KB HTML mỗi lượt.
         *
         * Nặng gấp bội từ khi JS nạp trước cả nút "Sửa" của từng dòng — một
         * bảng hai mươi dòng là hai mươi lượt, và hai mươi cái thanh bên không
         * ai nhìn thấy.
         *
         * Nhận ra bằng header 'X-Requested-With: fetch' do chính admin-modal.js
         * gửi, và CHỈ với GET. Ai gọi tay bằng curl mà đặt header ấy thì nhận
         * về ruột trang không khung — không lộ thêm gì, vì quyền đã kiểm ở
         * constructor và view vẫn là view ấy.
         *
         * Trả về mảnh HTML chứ không phải tài liệu đầy đủ là CỐ Ý: DOMParser
         * phía JS bọc lại thành tài liệu hợp lệ, còn khi có gì sai (hết phiên,
         * mất quyền, bản ghi vừa bị xoá) thì trang trả về không có .amodal nào
         * và JS chuyển hướng thật — lúc ấy trình duyệt đi một lượt mới, không
         * kèm header này, nên người dùng thấy trang đầy đủ.
         * ─────────────────────────────────────────────────────────────────────
         */
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
            && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
            extract($data);

            require VIEWS_PATH . '/' . $viewName . '.php';

            return;
        }

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

        /* HUY HIỆU "LIÊN HỆ" ĐỔI NGHĨA ngày 2026-08-26.

           Trước: số yêu cầu ở trạng thái 'new' — tức việc CHƯA AI BẤM.
           Nay:   số yêu cầu chưa đẩy được sang Zalo CSKH — tức việc HỆ THỐNG
                  CHƯA LÀM ĐƯỢC.

           Bình thường con số này là 0 và huy hiệu tự ẩn. Khác 0 nghĩa là ZNS
           đang hỏng và có người thật đang chờ gọi lại mà CSKH chưa biết — đúng
           định nghĩa "hàng chờ có NGƯỜI ĐANG ĐỢI ở đầu bên kia" mà thanh bên
           dùng để quyết định mục nào được đeo số. */
        $data['pendingContacts'] = ContactModel::countChuaDayZalo();
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
