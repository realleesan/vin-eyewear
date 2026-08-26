<?php

/**
 * CollectionAdminController — bộ sưu tập (/quan-tri/bo-suu-tap).
 *
 * Dựng theo đúng lối của CategoryAdminController: một trang vừa là danh sách vừa
 * là form, mở form sửa bằng ?sua=<id>.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SLUG LÀ THỨ NGUY HIỂM NHẤT Ở ĐÂY
 *
 * `collections.slug` nối với `products.collection` và với mọi link
 * /san-pham?collection=… đã phát ra ngoài. Đổi slug của một bộ ĐÃ CÓ HÀNG là
 * cắt đứt cả hai cùng lúc, mà không có gì báo: trang bộ sưu tập vẫn hiện, bấm
 * vào ra lưới trắng.
 *
 * Nên save() ĐẾM số sản phẩm đang gắn slug cũ và từ chối nếu còn hàng. Muốn
 * đổi thì gỡ hàng ra trước — một việc cố ý, không phải một cú lỡ tay.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class CollectionAdminController extends AdminController
{
    private const BASE = '/quan-tri/bo-suu-tap';

    public function index(): void
    {
        $collections = CollectionModel::allOrdered();

        /*
         * Đếm sản phẩm theo từng bộ, MỘT câu lệnh cho cả bảng.
         *
         * Con số này làm hai việc: cho nhân viên biết bộ nào đang thật sự có
         * hàng, và giải thích tại chỗ vì sao nút xoá / ô slug của một bộ lại
         * bị khoá — thay vì để họ bấm rồi nhận một câu từ chối không rõ vì sao.
         */
        $counts = array_column(
            Database::fetchAll(
                'SELECT collection, COUNT(*) AS n
                   FROM products
                  WHERE collection IS NOT NULL AND collection <> ""
                  GROUP BY collection'
            ),
            'n',
            'collection'
        );

        $this->renderAdmin('admin/collections/index', [
            'pageTitle'   => 'Bộ sưu tập — Quản trị',
            'collections' => $collections,
            'counts'      => $counts,
            'canEdit'     => UserModel::hasRole($this->userId, 'admin')
                          || UserModel::hasRole($this->userId, 'manager'),
            'editing'     => isset($_GET['sua']) ? CollectionModel::find((string) $_GET['sua']) : null,
        ]);
    }

    public function save(): void
    {
        $this->guardPostSize(self::BASE);
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id   = (string) ($_POST['id'] ?? '');
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));

        if (utf8Length($name) < 2) {
            flash('admin_error', 'Tên bộ sưu tập phải có ít nhất 2 ký tự.');
            redirect(self::BASE);
        }

        $slug = $slug !== '' ? slugify($slug) : slugify($name);

        if ($slug === '') {
            flash('admin_error', 'Không tạo được slug từ tên này, vui lòng nhập slug thủ công.');
            redirect(self::BASE);
        }

        $clash = CollectionModel::findBy('slug', $slug);

        if ($clash !== null && $clash['id'] !== $id) {
            flash('admin_error', sprintf('Slug "%s" đã được dùng cho bộ sưu tập khác.', $slug));
            redirect(self::BASE);
        }

        /*
         * ĐANG SỬA và slug ĐỔI: chặn nếu bộ cũ còn hàng.
         *
         * products.collection lưu SLUG chứ không lưu id, nên đổi slug ở đây là
         * bỏ rơi toàn bộ sản phẩm đang trỏ tới chuỗi cũ. Chúng không biến mất
         * khỏi danh mục, nhưng rơi khỏi mọi bộ sưu tập — và cột lọc thì lấy
         * nhãn từ bảng này nên chuỗi mồ côi kia hiện ra dưới một cái tên suy
         * từ slug, trông như dữ liệu rác.
         *
         * Sửa kèm theo (UPDATE products SET collection = mới) thì gọn hơn cho
         * người dùng, nhưng đó là sửa hàng loạt dữ liệu bán hàng từ một ô nhập
         * trông vô hại. Chặn lại và nói rõ số hàng đang vướng.
         */
        if ($id !== '') {
            $cu = CollectionModel::find($id);

            if ($cu !== null && $cu['slug'] !== $slug) {
                $dangDung = (int) Database::fetchValue(
                    'SELECT COUNT(*) FROM products WHERE collection = :s',
                    ['s' => $cu['slug']]
                );

                if ($dangDung > 0) {
                    flash('admin_error', sprintf(
                        'Không đổi được slug: còn %d sản phẩm đang thuộc bộ "%s". '
                        . 'Hãy chuyển các sản phẩm đó sang bộ khác trước.',
                        $dangDung,
                        $cu['slug']
                    ));
                    redirect(self::BASE . '?sua=' . rawurlencode($id));
                }
            }
        }

        // Ảnh bìa xử lý SAU mọi phép kiểm có redirect: file đã move_uploaded_file()
        // thì nằm lại trên đĩa, mà redirect không quay lại đây để dọn.
        [$cover, $coverError] = $this->cover($id);

        $data = [
            'slug'        => $slug,
            'name'        => $name,
            'tagline'     => trim((string) ($_POST['tagline'] ?? '')) ?: null,
            'intro'       => trim((string) ($_POST['intro'] ?? '')) ?: null,
            'cover_image' => $cover,
            'launched_at' => $this->toDate($_POST['launched_at'] ?? ''),
            'sort_order'  => (int) ($_POST['sort_order'] ?? 0),
            'is_visible'  => isset($_POST['is_visible']) ? 1 : 0,
        ];

        if ($id !== '' && CollectionModel::exists(['id' => $id])) {
            CollectionModel::update($id, $data);
            flash('admin_success', 'Đã cập nhật bộ sưu tập.');
        } else {
            CollectionModel::insert($data);
            flash('admin_success', 'Đã thêm bộ sưu tập mới.');
        }

        // Ảnh hỏng KHÔNG huỷ cả lần lưu: mọi thứ khác đã hợp lệ và đã ghi xuống.
        if ($coverError !== null) {
            flash('admin_error', $coverError);
        }

        redirect(self::BASE);
    }

    public function delete(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id  = (string) ($_POST['id'] ?? '');
        $row = CollectionModel::find($id);

        if ($row === null) {
            flash('admin_error', 'Không tìm thấy bộ sưu tập.');
            redirect(self::BASE);
        }

        /*
         * CÒN HÀNG THÌ KHÔNG XOÁ.
         *
         * Xoá bản ghi không đụng gì tới products.collection — chuỗi slug nằm
         * lại đó và thành mồ côi. Ẩn đi (is_visible = 0) là thứ nhân viên
         * thường thật sự muốn khi hết mùa, nên nói thẳng ra để họ chọn đúng
         * việc thay vì tìm cách lách.
         */
        $dangDung = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM products WHERE collection = :s',
            ['s' => $row['slug']]
        );

        if ($dangDung > 0) {
            flash('admin_error', sprintf(
                'Không xoá được: còn %d sản phẩm thuộc bộ này. '
                . 'Hết mùa thì bỏ tick "Đang hiển thị" để ẩn đi, hàng vẫn giữ nguyên bộ.',
                $dangDung
            ));
            redirect(self::BASE);
        }

        CollectionModel::delete($id);
        CollectionCoverStorage::remove($row['cover_image'] ?? null);

        flash('admin_success', 'Đã xoá bộ sưu tập.');
        redirect(self::BASE);
    }

    /**
     * Ảnh bìa cuối cùng của bộ sưu tập.
     *
     * Ba tình huống, đúng thứ tự ưu tiên:
     *   1. Có chọn file mới  -> dùng file mới, xoá file cũ.
     *   2. Tick "Bỏ ảnh bìa" -> về null, xoá file cũ.
     *   3. Không đụng gì     -> giữ nguyên ảnh đang có.
     *
     * @return array{0: string|null, 1: string|null} [đường dẫn ảnh, lỗi để báo lại]
     */
    private function cover(string $id): array
    {
        // Ảnh hiện tại đọc TỪ CSDL, không lấy từ form: form chỉ được quyền nói
        // "thay" hoặc "bỏ". Nhận thẳng đường dẫn do form gửi thì ai vào được
        // trang này cũng nhét được URL lạ vào cột in ra <img src>.
        $old = $id !== '' ? (CollectionModel::find($id)['cover_image'] ?? null) : null;

        $stored = CollectionCoverStorage::store($_FILES['cover_file'] ?? []);

        if ($stored['ok']) {
            CollectionCoverStorage::remove($old);

            return [$stored['path'], null];
        }

        // error = null nghĩa là KHÔNG CHỌN file nào — không phải lỗi.
        if (($stored['error'] ?? null) !== null) {
            // Ảnh mới hỏng thì giữ nguyên ảnh cũ: người dùng định thay, không định xoá.
            return [$old, $stored['error']];
        }

        if (isset($_POST['cover_remove'])) {
            CollectionCoverStorage::remove($old);

            return [null, null];
        }

        return [$old, null];
    }

    /**
     * Đổi giá trị <input type="date"> sang định dạng DATE của MySQL.
     *
     * Trình duyệt đã gửi đúng "2026-03-14", nhưng vẫn phải lọc: form không
     * phải đường vào duy nhất, và một chuỗi lạ lọt xuống cột DATE sẽ thành
     * '0000-00-00' hoặc làm câu lệnh đổ tuỳ chế độ SQL đang bật.
     */
    private function toDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }
}
