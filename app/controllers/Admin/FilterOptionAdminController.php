<?php

/**
 * FilterOptionAdminController — tiêu chí lọc (/quan-tri/tieu-chi-loc).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * MÀN NÀY KHÔNG KHAI RA TIÊU CHÍ — NÓ TUỲ BIẾN TIÊU CHÍ ĐÃ CÓ
 *
 * Đây là chỗ dễ hiểu nhầm nhất, nên nói trước: bảng bên dưới liệt kê những
 * tiêu chí mà bộ lọc ĐANG có, rút ra từ chữ người nhập hàng gõ vào ô dáng gọng,
 * chất liệu, giới tính và màu của từng biến thể. Nhập một gọng "Pantos" là mục
 * "Pantos" tự xuất hiện ở đây, không phải khai trước.
 *
 * Việc của màn này là sửa lại những mục ấy: đặt tên khác, gộp hai mục làm một,
 * ẩn mục không muốn bày, ghim mục hay dùng lên đầu.
 *
 * Khác hẳn màn "Thuộc tính tròng" (/quan-tri/thuoc-tinh-trong): ở đó cửa hàng
 * KHAI danh sách rồi sản phẩm tick vào, nên xoá một mục là làm mồ côi hàng đã
 * tick. Ở đây xoá một dòng chỉ là bỏ phần tuỳ biến — tiêu chí quay về tên máy
 * tự dựng, hàng hoá không suy suyển. Vì thế màn này có nút XOÁ thật.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BỐN VIỆC LÀM ĐƯỢC
 *
 *   SỬA   đổi tên hiện ra. Khoá giữ nguyên nên liên kết cũ vẫn trúng.
 *   GỘP   trỏ mục này vào mục khác. Hai cách viết cùng một thứ nhập làm một.
 *   ẨN    thôi bày ra như một lựa chọn. Hàng mang nó KHÔNG biến mất và địa
 *         chỉ ?shape[]=… cũ vẫn lọc được.
 *   THÊM  khai đồng nghĩa để kéo chữ người nhập gõ về một mục có sẵn. Thêm
 *         một mục trống không khớp món nào chỉ tạo ra một dòng đếm 0 mờ tịt,
 *         nên ô đồng nghĩa mới là thứ làm cho "thêm" có tác dụng thật.
 * ═════════════════════════════════════════════════════════════════════════════
 */

class FilterOptionAdminController extends AdminController
{
    private const BASE = '/quan-tri/tieu-chi-loc';

    public function index(): void
    {
        /* Chưa chạy file nâng cấp thì KHÔNG vẽ form — cùng nếp với màn thuộc
           tính tròng: bày một form ghi vào bảng không tồn tại là mời người
           dùng gõ xong rồi nhận lỗi 1146. */
        if (!FilterOverrideModel::editable()) {
            $this->renderAdmin('admin/filter-options/chua-nang-cap', [
                'pageTitle' => 'Tiêu chí lọc — Quản trị',
            ]);

            return;
        }

        $group = (string) ($_GET['nhom'] ?? '');

        if (!isset(FilterOverrideModel::GROUPS[$group])) {
            $group = array_key_first(FilterOverrideModel::GROUPS);
        }

        /*
         * TIÊU CHÍ ĐANG SỐNG lấy từ chính bộ máy của trang danh mục, không
         * dựng lại bằng một câu SQL riêng.
         *
         * Dựng lại là có ngày màn này liệt kê một đằng còn bộ lọc ngoài kia
         * hiện một nẻo — mà cả điểm của màn này là sửa đúng cái khách nhìn
         * thấy. Đổi lại, nó nặng: catalog() đọc cả kho rồi tính số đếm cho mọi
         * nhóm. Chấp nhận được vì đây là màn quản trị, mở vài lần một tháng.
         *
         * Lọc rỗng để lấy TOÀN KHO, không phải riêng một danh mục.
         */
        $ket = ProductModel::catalog(
            ['q' => '', 'category' => ''],
            ProductController::PRICE_RANGES,
            1
        );

        $dangSong = $ket['groups'][$group] ?? [];
        $de       = [];

        foreach (FilterOverrideModel::ofGroup($group) as $r) {
            $de[(string) $r['option_key']] = $r;
        }

        /*
         * Dòng đè trỏ tới một khoá KHÔNG còn món nào mang (hàng đã bán hết,
         * hoặc người nhập đã sửa lại chữ) vẫn phải hiện ra — nếu không thì nó
         * nằm lại trong CSDL mà không ai xoá được, và cửa hàng không hiểu vì
         * sao một mục cũ vẫn gộp lung tung.
         */
        $coTrongDs = [];

        foreach ($dangSong as $o) {
            $coTrongDs[$o['key']] = true;
        }

        $moCoi = [];

        foreach ($de as $khoa => $r) {
            if (!isset($coTrongDs[$khoa])) {
                $moCoi[] = ['key' => $khoa, 'label' => (string) ($r['label'] ?? $khoa), 'count' => 0, 'on' => false];
            }
        }

        /*
         * HỘP THÊM / SỬA mở theo địa chỉ: ?them=1 hoặc ?sua=<mã>.
         *
         * $ed gom đủ ba thứ hộp cần — mã, tên mặc định (để làm placeholder cho
         * ô "Tên hiển thị") và dòng đè hiện có (có thể null). Tính ở đây chứ
         * không để view tự mò trong $dangSong: view sẽ phải lặp cả bảng một
         * lần nữa chỉ để tìm một dòng.
         */
        $ed = null;

        if (isset($_GET['them'])) {
            $ed = ['key' => '', 'label' => '', 'row' => null, 'them' => true];
        } elseif (($sua = (string) ($_GET['sua'] ?? '')) !== '') {
            $nhanMacDinh = null;

            foreach ($dangSong as $o) {
                if ((string) $o['key'] === $sua) {
                    $nhanMacDinh = (string) $o['label'];
                    break;
                }
            }

            /* Mã lạ (gõ tay vào địa chỉ, hoặc hàng vừa bán hết) vẫn mở được hộp
               — miễn là nó CÓ dòng đè để sửa, hoặc đang là một mục sống. Không
               thì đóng hộp lại thay vì bày một form ghi vào hư không. */
            if ($nhanMacDinh !== null || isset($de[$sua])) {
                $ed = [
                    'key'   => $sua,
                    'label' => $nhanMacDinh ?? (string) ($de[$sua]['label'] ?? $sua),
                    'row'   => $de[$sua] ?? null,
                    'them'  => false,
                ];
            }
        }

        $this->renderAdmin('admin/filter-options/index', [
            'pageTitle' => 'Tiêu chí lọc — Quản trị',
            'nhom'      => $group,
            'cacNhom'   => FilterOverrideModel::GROUPS,
            'dangSong'  => $dangSong,
            'moCoi'     => $moCoi,
            'de'        => $de,
            'ed'        => $ed,
            'base'      => self::BASE,
            /* Cùng mức quyền với ba đường ghi bên dưới: đây là dữ liệu catalog,
               chỉ Quản trị viên sửa được (SRS v2.1.0, ma trận 5.2.2). Đây CHỈ
               để vẽ — chặn thật vẫn nằm ở requireAdmin() trong từng action. */
            'canEdit'   => UserModel::hasRole($this->userId, 'admin'),
        ]);
    }

    /** Lưu tuỳ biến của MỘT tiêu chí (POST). */
    public function save(): void
    {
        $this->requirePost(self::BASE);
        $this->requireAdmin(self::BASE);

        if (!FilterOverrideModel::editable()) {
            flash('admin_error', 'Cơ sở dữ liệu chưa được nâng cấp cho phần này.');
            redirect(self::BASE);
        }

        $group = (string) ($_POST['nhom'] ?? '');

        if (!isset(FilterOverrideModel::GROUPS[$group])) {
            flash('admin_error', 'Không tìm thấy nhóm tiêu chí.');
            redirect(self::BASE);
        }

        $ve  = self::BASE . '?nhom=' . rawurlencode($group);
        $key = strtolower(trim((string) ($_POST['option_key'] ?? '')));

        /* Khoá là thứ đi vào URL (?shape[]=cat-eye) — chữ thường không dấu,
           số, gạch nối và dấu chấm, cùng luật với mã thuộc tính tròng. */
        if (!preg_match('/^[a-z0-9][a-z0-9.-]{0,63}$/', $key)) {
            flash('admin_error', 'Mã tiêu chí không hợp lệ.');
            redirect($ve);
        }

        $label = trim((string) ($_POST['label'] ?? ''));
        $gop   = strtolower(trim((string) ($_POST['merge_into'] ?? '')));
        $syn   = trim((string) ($_POST['synonyms'] ?? ''));

        if (utf8Length($label) > 120) {
            flash('admin_error', 'Tên hiển thị không được quá 120 ký tự.');
            redirect($ve);
        }

        if (utf8Length($syn) > 500) {
            flash('admin_error', 'Danh sách đồng nghĩa không được quá 500 ký tự.');
            redirect($ve);
        }

        /* GỘP VÀO CHÍNH MÌNH là một vòng lặp: mục sẽ chuyển thành chính nó mãi
           mãi, và người dùng thấy nút Lưu không có tác dụng gì. Chặn tại đây
           với một câu nói rõ, thay vì để nó lặng lẽ không làm gì. */
        if ($gop !== '' && $gop === $key) {
            flash('admin_error', 'Không gộp một tiêu chí vào chính nó.');
            redirect($ve);
        }

        if ($gop !== '' && !preg_match('/^[a-z0-9][a-z0-9.-]{0,63}$/', $gop)) {
            flash('admin_error', 'Mã gộp vào không hợp lệ.');
            redirect($ve);
        }

        FilterOverrideModel::save(
            $group,
            $key,
            $label !== '' ? $label : null,
            $gop !== '' ? $gop : null,
            $syn !== '' ? $syn : null
        );

        flash('admin_success', 'Đã lưu tuỳ biến cho tiêu chí.');
        redirect($ve);
    }

    /** Ẩn / hiện một tiêu chí khỏi bộ lọc (POST). */
    public function toggle(): void
    {
        $this->requirePost(self::BASE);
        $this->requireAdmin(self::BASE);

        if (!FilterOverrideModel::editable()) {
            flash('admin_error', 'Cơ sở dữ liệu chưa được nâng cấp cho phần này.');
            redirect(self::BASE);
        }

        $group = (string) ($_POST['nhom'] ?? '');
        $key   = strtolower(trim((string) ($_POST['option_key'] ?? '')));

        if (!isset(FilterOverrideModel::GROUPS[$group]) || $key === '') {
            flash('admin_error', 'Không tìm thấy tiêu chí.');
            redirect(self::BASE);
        }

        $ve  = self::BASE . '?nhom=' . rawurlencode($group);
        $row = FilterOverrideModel::findByKey($group, $key);

        /* Chưa có dòng đè thì tạo một dòng rồi ẩn: cửa hàng bấm "Ẩn" trên một
           tiêu chí máy tự rút ra, và họ không cần biết rằng phía sau vừa có
           một dòng mới ra đời. */
        if ($row === null) {
            FilterOverrideModel::save($group, $key, null, null, null);
            $row = FilterOverrideModel::findByKey($group, $key);
        }

        if ($row === null) {
            flash('admin_error', 'Không lưu được. Vui lòng thử lại.');
            redirect($ve);
        }

        $hien = empty($row['is_visible']);

        FilterOverrideModel::setVisible((string) $row['id'], $hien);

        flash('admin_success', $hien
            ? 'Đã hiện lại tiêu chí trong bộ lọc.'
            : 'Đã ẩn tiêu chí khỏi bộ lọc. Sản phẩm mang nó không bị ảnh hưởng.');
        redirect($ve);
    }

    /** Xoá phần tuỳ biến, trả tiêu chí về tên máy tự dựng (POST). */
    public function delete(): void
    {
        $this->requirePost(self::BASE);
        $this->requireAdmin(self::BASE);

        $row = FilterOverrideModel::findRow((string) ($_POST['id'] ?? ''));

        if ($row === null) {
            flash('admin_error', 'Không tìm thấy dòng tuỳ biến.');
            redirect(self::BASE);
        }

        FilterOverrideModel::remove((string) $row['id']);

        flash('admin_success', 'Đã bỏ tuỳ biến. Tiêu chí quay về tên mặc định.');
        redirect(self::BASE . '?nhom=' . rawurlencode((string) $row['group_key']));
    }

    /** Đổi chỗ một tiêu chí đã ghim với mục liền kề (POST). */
    public function move(): void
    {
        $this->requirePost(self::BASE);
        $this->requireAdmin(self::BASE);

        $row = FilterOverrideModel::findRow((string) ($_POST['id'] ?? ''));

        if ($row === null) {
            flash('admin_error', 'Không tìm thấy dòng tuỳ biến.');
            redirect(self::BASE);
        }

        FilterOverrideModel::move((string) $row['id'], ($_POST['huong'] ?? '') === 'len' ? 'len' : 'xuong');

        redirect(self::BASE . '?nhom=' . rawurlencode((string) $row['group_key']));
    }
}
