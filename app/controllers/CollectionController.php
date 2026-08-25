<?php

/**
 * CollectionController — bộ sưu tập theo mùa (/bo-suu-tap).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TRANG NÀY KHÔNG CÓ TRANG CHI TIẾT
 *
 * Nút "Xem chi tiết" của mỗi bộ dẫn thẳng sang /san-pham?collection=<slug> —
 * tức là danh mục sản phẩm đã bật sẵn đúng bộ lọc ấy.
 *
 * Đã cân nhắc một trang /bo-suu-tap/{slug} riêng rồi bỏ: nó sẽ phải liệt kê
 * lại đúng những sản phẩm mà trang danh mục đã liệt kê, nhưng thiếu toàn bộ
 * phần lọc, sắp xếp và phân trang có sẵn ở đó. Người xem một bộ sưu tập rồi
 * thì việc kế tiếp gần như luôn là "lọc thêm cho hợp mắt tôi" — đưa thẳng họ
 * tới nơi làm được việc đó tốt hơn là dựng một bản sao nghèo hơn.
 *
 * Kéo theo: KHÔNG có route /bo-suu-tap/{slug}. Muốn thêm sau thì phải trả lời
 * được trang đó làm gì mà /san-pham?collection= không làm được.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class CollectionController extends BaseController
{
    public function index(): void
    {
        $collections = CollectionModel::visible();

        $this->renderView('collection/index', [
            'pageTitle'   => 'Bộ sưu tập — Vin Eyewear',
            'metaDesc'    => 'Các bộ sưu tập kính mắt theo mùa của Vin Eyewear: '
                           . 'gọng, tròng và phong cách được chọn theo từng nhu cầu.',
            'collections' => $collections,
        ]);
    }
}
