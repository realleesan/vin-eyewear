<?php

/**
 * HomeController — trang chủ (/).
 *
 * Port từ src/routes/index.tsx.
 *
 * Bản cũ của controller này gõ cứng gần 240 dòng nội dung (tiêu đề section,
 * ảnh CDN của Moscot, danh sách id sản phẩm…). Nay mọi thứ đọc từ DB hoặc
 * từ config, controller chỉ còn việc lấy dữ liệu.
 */

class HomeController extends BaseController
{
    public function index(): void
    {
        /*
         * Chỉ hai truy vấn. Trang chủ nay chỉ còn chín khối của bản thiết kế,
         * trong đó khối tròng kính, số liệu và đánh giá đọc thẳng từ config —
         * nên bỏ luôn EventModel::upcoming() và config('company') vốn chỉ phục
         * vụ khối sự kiện và phần chân trang cũ.
         */
        $this->renderView('home/index', [
            'pageTitle'  => 'Vin Eyewear — Kính mắt chính hãng, đo khúc xạ miễn phí',
            'metaDesc'   => 'Gọng kính, kính mát và tròng kính chính hãng tại Hà Nội. '
                          . 'Đo khúc xạ miễn phí, thử kính AR trực tuyến, bảo hành trọn đời.',

            // Danh mục kèm số sản phẩm, dùng cho lưới danh mục dưới hero
            'categories' => CategoryModel::withProductCounts(),

            // Bán chạy: ưu tiên hàng được đánh dấu nổi bật.
            // 4 chứ không phải 8 — bản thiết kế xếp đúng một hàng 4 thẻ.
            'bestSellers' => ProductModel::featured(4),
        ]);
    }
}
