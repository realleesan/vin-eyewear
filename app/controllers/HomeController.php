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
         * Bốn truy vấn cho mười khối của bản thiết kế. Bộ sưu tập, gói tròng,
         * quy trình đo mắt và đánh giá đều đọc thẳng từ config nên không tốn
         * thêm lần nào chạm DB.
         */
        $this->renderView('home/index', [
            'pageTitle'  => 'Vin Eyewear — Kính mắt chính hãng, đo khúc xạ miễn phí',
            'metaDesc'   => 'Gọng kính, kính mát và tròng kính chính hãng tại Hà Nội. '
                          . 'Đo khúc xạ miễn phí, thử kính AR trực tuyến, bảo hành trọn đời.',

            /*
             * Ưu đãi đang chạy — dải đếm ngược trong hero. Trả null khi không
             * còn ưu đãi nào trong hạn, và hero tự ẩn dải đó đi: một chiếc
             * đồng hồ đếm ngược tới hư không còn tệ hơn là không có nó.
             */
            'promo' => EventModel::currentPromo(),

            /*
             * Hàng vừa lên kệ. 8 chứ không phải 4 (bản thiết kế xếp đúng một
             * hàng 4 thẻ): hai lưới sản phẩm của trang chủ nay là BĂNG TRƯỢT
             * có mũi tên tới/lui, mà một băng chỉ đủ lấp đúng một khung nhìn
             * thì hai mũi tên chẳng đưa đi đâu được.
             *
             * 8 = hai khung nhìn ở màn rộng. Hạ xuống dưới 5 thì hai mũi tên
             * vẫn in ra nhưng nằm mờ, vì không còn gì để trượt.
             */
            'newArrivals' => ProductModel::newest(8),

            // Danh mục kèm số sản phẩm, dùng cho lưới danh mục
            'categories' => CategoryModel::withProductCounts(),

            // Bán chạy: ưu tiên hàng được đánh dấu nổi bật. 8 vì cùng lý do
            // với 'newArrivals' ở trên — khối này cũng là một băng trượt.
            'bestSellers' => ProductModel::featured(8),
        ]);
    }
}
