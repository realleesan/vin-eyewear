<?php

/**
 * LensOptionAdminController — thuộc tính tròng kính (/quan-tri/thuoc-tinh-trong).
 *
 * Bốn danh sách dựng nên bộ lọc của trang /san-pham/trong-kinh: loại tròng,
 * chiết suất, tính năng/lớp phủ, màu tròng. Trước 2026-08-30 cả bốn gõ cứng
 * trong config/eyewear.php và config/taxonomy.php.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA LUẬT CỦA MÀN NÀY, VÀ CẢ BA ĐỀU BẢO VỆ CÙNG MỘT THỨ: KHOÁ
 *
 * `option_key` không phải cái nhãn — nó nằm trong CSV của MỌI sản phẩm đã gắn
 * mục đó (products.lens_types, lens_indexes, lens_coatings, lens_color). Nên:
 *
 *   1. SỬA thì chỉ sửa được NHÃN và ghi chú. Ô mã bị khoá, và save() chốt theo
 *      ô `cu` chứ không theo ô `id` — readonly là chuyện của trình duyệt, ai
 *      cũng gửi POST khác đi được.
 *   2. GỠ một mục là ẨN (`is_visible = 0`), không phải xoá. Ẩn thì hàng cũ giữ
 *      nguyên khoá, chỉ mục đó rút khỏi bộ lọc và khỏi form nhập.
 *   3. Bảng in kèm SỐ SẢN PHẨM đang gắn mỗi khoá, để người bấm "Ẩn" nhìn thấy
 *      hậu quả trước khi bấm chứ không phải sau.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class LensOptionAdminController extends AdminController
{
    private const BASE = '/quan-tri/thuoc-tinh-trong';

    public function index(): void
    {
        /* Chưa chạy file nâng cấp thì KHÔNG vẽ form. Bày một cái form ghi vào
           một bảng không tồn tại là mời người dùng gõ xong rồi nhận lỗi 1146 —
           cùng nếp với màn gói chiết suất và module Khách hàng. */
        if (!LensOptionModel::editable()) {
            $this->renderAdmin('admin/lens-options/chua-nang-cap', [
                'pageTitle' => 'Thuộc tính tròng — Quản trị',
            ]);

            return;
        }

        /*
         * Nhóm đang mở. Bốn danh sách nằm trên bốn tab của cùng một trang chứ
         * không bốn trang: người sửa thường vào để thêm một màu rồi thêm luôn
         * một lớp phủ, và bốn URL rời nhau bắt họ quay ra menu giữa chừng.
         */
        $group = (string) ($_GET['nhom'] ?? '');

        if (!isset(LensOptionModel::GROUPS[$group])) {
            $group = array_key_first(LensOptionModel::GROUPS);
        }

        $rows = LensOptionModel::ofGroup($group);

        /*
         * Số sản phẩm đang gắn từng khoá. Một truy vấn cho mỗi dòng — chấp
         * nhận được vì mỗi nhóm nhiều nhất vài chục dòng và đây là màn quản
         * trị, không phải trang khách. Gộp thành một câu thì phải dựng LIKE
         * động cho từng khoá, đổi lấy một câu SQL không ai đọc nổi.
         */
        $usage = [];

        foreach ($rows as $r) {
            $usage[(string) $r['option_key']] = LensOptionModel::usageCount($group, (string) $r['option_key']);
        }

        $this->renderAdmin('admin/lens-options/index', [
            'pageTitle' => LensOptionModel::GROUPS[$group] . ' — Thuộc tính tròng',
            'group'     => $group,
            'groups'    => LensOptionModel::GROUPS,
            'rows'      => $rows,
            'usage'     => $usage,
            'editing'   => isset($_GET['sua']) ? LensOptionModel::findRow((string) $_GET['sua']) : null,
        ]);
    }

    /** Thêm mới hoặc sửa một lựa chọn (POST). */
    public function save(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        if (!LensOptionModel::editable()) {
            flash('admin_error', 'Cơ sở dữ liệu chưa được nâng cấp cho phần này.');
            redirect(self::BASE);
        }

        $group = (string) ($_POST['nhom'] ?? '');

        if (!isset(LensOptionModel::GROUPS[$group])) {
            flash('admin_error', 'Không tìm thấy nhóm thuộc tính.');
            redirect(self::BASE);
        }

        $ve    = self::BASE . '?nhom=' . rawurlencode($group);
        $label = trim((string) ($_POST['label'] ?? ''));
        $note  = trim((string) ($_POST['note'] ?? ''));

        if (utf8Length($label) < 1 || utf8Length($label) > 120) {
            flash('admin_error', 'Tên hiển thị phải từ 1 đến 120 ký tự.');
            redirect($ve);
        }

        if (utf8Length($note) > 255) {
            flash('admin_error', 'Ghi chú không được quá 255 ký tự.');
            redirect($ve);
        }

        // ---- Sửa ----
        /* Chốt theo `cu` chứ không theo `id`: xem luật 1 ở đầu file. */
        $cu = trim((string) ($_POST['cu'] ?? ''));

        if ($cu !== '') {
            if (LensOptionModel::findRow($cu) === null) {
                flash('admin_error', 'Không tìm thấy lựa chọn.');
                redirect($ve);
            }

            LensOptionModel::updateLabel($cu, $label, $note === '' ? null : $note);

            flash('admin_success', 'Đã cập nhật lựa chọn.');
            redirect($ve);
        }

        // ---- Thêm mới ----
        $key = strtolower(trim((string) ($_POST['option_key'] ?? '')));

        /*
         * MÃ LÀ KHOÁ THẬT, KHÔNG PHẢI MỘT CÁI NHÃN — cùng luật với mã gói
         * chiết suất. Chữ thường, số, gạch nối và DẤU CHẤM; bắt đầu bằng chữ
         * hoặc số.
         *
         * Dấu chấm là ngoại lệ so với mã gói, và có lý do: khoá của nhóm chiết
         * suất chính là con số — '1.61'. Bắt gõ '1-61' thì nhãn và khoá lệch
         * nhau ở đúng nhóm mà người ta hay đọc thẳng khoá nhất, và bảng giá
         * tròng (`lens_prices`) cũng đang khoá theo chuỗi có dấu chấm.
         *
         * Không cho chữ hoa vì so khớp ở PHP là so chuỗi phân biệt hoa thường:
         * 'Xam-khoi' và 'xam-khoi' sẽ thành hai lựa chọn khác nhau mà nhìn
         * bằng mắt thì y hệt.
         */
        if (!preg_match('/^[a-z0-9][a-z0-9.-]{0,63}$/', $key)) {
            flash('admin_error', 'Mã chỉ gồm chữ thường không dấu, số, dấu chấm và gạch nối (tối đa 64 ký tự).');
            redirect($ve);
        }

        if (LensOptionModel::findByKey($group, $key) !== null) {
            flash('admin_error', sprintf('Mã "%s" đã có trong nhóm này.', $key));
            redirect($ve);
        }

        LensOptionModel::create($group, $key, $label, $note === '' ? null : $note);

        flash('admin_success', 'Đã thêm lựa chọn mới.');
        redirect($ve);
    }

    /** Ẩn / hiện một lựa chọn (POST). Thay cho xoá — xem luật 2 ở đầu file. */
    public function toggle(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        /* Kiểm bảng TRƯỚC khi kiểm bản ghi. findRow() trả null cho cả hai
           trường hợp, nên bỏ bước này thì máy chưa nâng cấp nhận câu "Không
           tìm thấy lựa chọn." — đúng về kỹ thuật, sai hẳn về việc cần làm tiếp. */
        if (!LensOptionModel::editable()) {
            flash('admin_error', 'Cơ sở dữ liệu chưa được nâng cấp cho phần này.');
            redirect(self::BASE);
        }

        $row = LensOptionModel::findRow((string) ($_POST['id'] ?? ''));

        if ($row === null) {
            flash('admin_error', 'Không tìm thấy lựa chọn.');
            redirect(self::BASE);
        }

        $hien = empty($row['is_visible']);

        LensOptionModel::setVisible((string) $row['id'], $hien);

        flash('admin_success', $hien ? 'Đã hiện lại lựa chọn.' : 'Đã ẩn lựa chọn khỏi bộ lọc và form nhập hàng.');
        redirect(self::BASE . '?nhom=' . rawurlencode((string) $row['group_key']));
    }

    /** Đổi chỗ một lựa chọn với mục liền kề (POST). */
    public function move(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        if (!LensOptionModel::editable()) {
            flash('admin_error', 'Cơ sở dữ liệu chưa được nâng cấp cho phần này.');
            redirect(self::BASE);
        }

        $row = LensOptionModel::findRow((string) ($_POST['id'] ?? ''));

        if ($row === null) {
            flash('admin_error', 'Không tìm thấy lựa chọn.');
            redirect(self::BASE);
        }

        /* Hướng lạ thì coi như 'xuong'. Không báo lỗi: giá trị này đến từ nút
           bấm của chính trang này, sai nghĩa là ai đó sửa POST tay — và hậu
           quả xấu nhất của việc đoán sai là một mục xê dịch một chỗ. */
        LensOptionModel::move((string) $row['id'], ($_POST['huong'] ?? '') === 'len' ? 'len' : 'xuong');

        redirect(self::BASE . '?nhom=' . rawurlencode((string) $row['group_key']));
    }
}
