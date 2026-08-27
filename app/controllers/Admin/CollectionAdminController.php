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
            // Cột `story` có thể chưa tồn tại trên máy chưa chạy migration —
            // xem khối chú thích trong save(). Form giấu ô nhập khi chưa có.
            'hasStory'    => Database::columnExists('collections', 'story'),
            /*
             * MỘT PHÉP THĂM DÒ CHO CẢ 14 CỘT của khung ba lớp.
             *
             * Cả 14 ra đời trong cùng một migration nên chúng có hoặc không có
             * cùng lúc — hỏi từng cột là 14 lượt truy vấn để trả lời cùng một
             * câu. `season_code` là cột đầu tiên trong câu ALTER ấy.
             */
            'hasFrame'    => Database::columnExists('collections', 'season_code'),
            'hasFaq'      => Database::tableExists('collection_faqs'),
            'faqs'        => $this->faqsOf(isset($_GET['sua']) ? (string) $_GET['sua'] : ''),
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

        /*
         * `story` CHỈ ghi khi cột đã có thật.
         *
         * Cột này ra đời cùng migration 2026-08-27-bo-suu-tap-trang-chi-tiet,
         * mà mã lên máy chủ bằng FTP TỰ ĐỘNG còn migration thì phải mở
         * phpMyAdmin bấm tay — khoảng giữa hai việc đó dài hàng giờ là chuyện
         * thường. Trong khoảng ấy, nhét 'story' vào câu INSERT/UPDATE là lỗi
         * 1054 và nhân viên không lưu nổi một bộ sưu tập nào, kể cả những thứ
         * chẳng liên quan gì tới trang chi tiết.
         *
         * Cùng lối mà ProductModel dùng cho cột `collection` (xem chỗ gọi
         * SHOW COLUMNS trong đó). Bỏ được đoạn này khi mọi máy đã chạy xong
         * migration — nhưng đừng vội, giá của việc quên là cả trang quản trị
         * bộ sưu tập.
         *
         * Form cũng tự giấu ô nhập trong cùng tình huống, nên không có ca
         * "gõ xong bấm lưu rồi chữ biến mất".
         */
        if (Database::columnExists('collections', 'story')) {
            // ?: null chứ không để chuỗi rỗng: view công khai ẩn cả khối khi
            // rỗng, mà "chưa ai viết" nói bằng NULL thì đọc lại trong CSDL rõ hơn.
            $data['story'] = trim((string) ($_POST['story'] ?? '')) ?: null;
        }

        /*
         * 14 CỘT CỦA KHUNG BA LỚP — cùng phép thăm dò, cùng lý do như `story`.
         *
         * Gộp bằng array_merge chứ không viết thẳng vào $data ở trên: máy chưa
         * chạy migration thì $data giữ nguyên đúng hình dạng cũ, và câu
         * INSERT/UPDATE vẫn chạy như trước ngày hôm nay.
         */
        if (Database::columnExists('collections', 'season_code')) {
            $data = array_merge($data, $this->khungBaLop());
        }

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

    // ========================================================================
    // KHUNG THÔNG TIN BA LỚP — 14 cột thêm ngày 2026-08-27
    // ========================================================================

    /**
     * Mười một ô chữ thường + ba cột JSON, đọc từ $_POST.
     *
     * @return array<string,mixed>
     */
    private function khungBaLop(): array
    {
        $chu = static fn (string $key): ?string => trim((string) ($_POST[$key] ?? '')) ?: null;

        return [
            'season_code'      => $chu('season_code'),
            'season_label'     => $chu('season_label'),
            'brand'            => $chu('brand'),
            'product_line'     => $chu('product_line'),
            'designed_in'      => $chu('designed_in'),
            'made_in'          => $chu('made_in'),
            'design_style'     => $chu('design_style'),
            'launch_offer'     => $chu('launch_offer'),
            'channels'         => $chu('channels'),
            'meta_title'       => $chu('meta_title'),
            'meta_description' => $chu('meta_description'),

            'audience'  => $this->jsonCot($this->docAudience((string) ($_POST['audience'] ?? ''))),
            'palette'   => $this->jsonCot($this->docPalette((string) ($_POST['palette'] ?? ''))),
            'signature' => $this->jsonCot(
                preg_split('/\R+/', trim((string) ($_POST['signature'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: []
            ),
        ];
    }

    /**
     * Mảng rỗng -> NULL, chứ không phải chuỗi "[]".
     *
     * View phân biệt hai thứ đó: NULL là "chưa ai viết" nên cả khối không ra
     * đời, còn "[]" cũng cho ra khối rỗng nhưng đọc lại trong CSDL thì không
     * ai biết là cố ý hay hỏng. Cùng luật với `story` và `tagline`.
     */
    private function jsonCot(array $items): ?string
    {
        return $items === [] ? null : json_encode($items, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Ô "Bộ này hợp với ai": mỗi dòng "Tiêu đề | Giá trị | Ghi chú".
     *
     * Dấu gạch đứng chứ không dấu hai chấm, khác ô Thông số của sản phẩm: ghi
     * chú ở đây là câu văn ("Đã qua tuổi chạy theo mốt, nhưng...") và câu văn
     * thì có dấu hai chấm trong đó. Gạch đứng gần như không bao giờ xuất hiện
     * trong tiếng Việt viết thường.
     *
     * Thiếu vế nào thì vế đó rỗng — view tự bỏ. Dòng KHÔNG có tiêu đề lẫn giá
     * trị thì bỏ hẳn: một ô chỉ có ghi chú không đọc ra cái gì.
     */
    private function docAudience(string $tho): array
    {
        $ra = [];

        foreach (preg_split('/\R+/', trim($tho), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $dong) {
            $ve = array_map('trim', explode('|', $dong, 3));

            $tieuDe = $ve[0] ?? '';
            $giaTri = $ve[1] ?? '';

            if ($tieuDe === '' && $giaTri === '') {
                continue;
            }

            $ra[] = [
                'tieu_de' => $tieuDe,
                'gia_tri' => $giaTri,
                'ghi_chu' => $ve[2] ?? '',
            ];
        }

        return $ra;
    }

    /**
     * Ô "Bảng màu": mỗi dòng "Tên | #rrggbb".
     *
     * MÃ MÀU SAI THÌ BỎ CẢ DÒNG, không cứu vãn. Giá trị này đi thẳng vào
     * thuộc tính style của thẻ in ra, nên nó phải là mã màu và chỉ là mã màu —
     * view cũng kiểm lại lần nữa, nhưng chặn ngay từ lúc ghi thì cột không bao
     * giờ chứa thứ phải đi kiểm.
     */
    private function docPalette(string $tho): array
    {
        $ra = [];

        foreach (preg_split('/\R+/', trim($tho), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $dong) {
            $ve = array_map('trim', explode('|', $dong, 2));
            $ma = $ve[1] ?? '';

            if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $ma)) {
                continue;
            }

            $ra[] = ['ten' => $ve[0] !== '' ? $ve[0] : $ma, 'ma_mau' => $ma];
        }

        return $ra;
    }

    /** Câu hỏi của bộ đang sửa — rỗng khi chưa chạy migration hoặc đang thêm mới. */
    private function faqsOf(string $collectionId): array
    {
        if ($collectionId === '' || !Database::tableExists('collection_faqs')) {
            return [];
        }

        return CollectionFaqModel::forCollection($collectionId);
    }

    // ========================================================================
    // CÂU HỎI THƯỜNG GẶP
    //
    // Đường riêng chứ không gộp vào save(): form bộ sưu tập là một <form>, mà
    // mỗi câu hỏi cần nút xoá riêng của nó — HTML không cho lồng <form>. Cùng
    // hoàn cảnh mà giỏ hàng đã gặp với bốn nút trên một dòng (xem routes.php).
    // ========================================================================

    public function saveFaq(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $collectionId = (string) ($_POST['collection_id'] ?? '');
        $quay         = self::BASE . '?sua=' . rawurlencode($collectionId) . '#faq';

        if (!Database::tableExists('collection_faqs')) {
            flash('admin_error', 'Chưa chạy nâng cấp cơ sở dữ liệu cho phần câu hỏi thường gặp.');
            redirect(self::BASE);
        }

        if (!CollectionModel::exists(['id' => $collectionId])) {
            flash('admin_error', 'Không tìm thấy bộ sưu tập.');
            redirect(self::BASE);
        }

        $hoi  = trim((string) ($_POST['question'] ?? ''));
        $dap  = trim((string) ($_POST['answer'] ?? ''));

        if (utf8Length($hoi) < 5 || utf8Length($dap) < 5) {
            flash('admin_error', 'Câu hỏi và câu trả lời đều phải có ít nhất 5 ký tự.');
            redirect($quay);
        }

        $data = [
            'collection_id' => $collectionId,
            'question'      => $hoi,
            'answer'        => $dap,
            'sort_order'    => (int) ($_POST['sort_order'] ?? 0),
        ];

        $id = (string) ($_POST['id'] ?? '');

        if ($id !== '' && CollectionFaqModel::exists(['id' => $id])) {
            CollectionFaqModel::update($id, $data);
            flash('admin_success', 'Đã cập nhật câu hỏi.');
        } else {
            CollectionFaqModel::insert($data);
            flash('admin_success', 'Đã thêm câu hỏi.');
        }

        redirect($quay);
    }

    public function deleteFaq(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id  = (string) ($_POST['id'] ?? '');
        $row = Database::tableExists('collection_faqs') ? CollectionFaqModel::find($id) : null;

        if ($row === null) {
            flash('admin_error', 'Không tìm thấy câu hỏi.');
            redirect(self::BASE);
        }

        CollectionFaqModel::delete($id);
        flash('admin_success', 'Đã xoá câu hỏi.');

        redirect(self::BASE . '?sua=' . rawurlencode((string) $row['collection_id']) . '#faq');
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
