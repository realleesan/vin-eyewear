<?php

/**
 * HomeController — trang chủ (/).
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * BỐN BĂNG HÀNG, MỖI BĂNG ĐÚNG MỘT HÀNG BỐN THẺ
 *
 * Mẫu "Eyewear Collection" xếp trang chủ thành: hero tràn màn hình, rồi bốn
 * băng nền xám — Sản phẩm mới về · Sản phẩm bán chạy · Gọng kính · Tròng kính
 * — mỗi băng một lưới 4 cột, rồi khối "Ghé thăm cửa hàng".
 *
 * LẤY ĐÚNG 4, KHÔNG PHẢI 8. Bản cũ lấy 8 vì hai khối ấy là BĂNG TRƯỢT có mũi
 * tên tới/lui, mà một băng chỉ đủ lấp một khung nhìn thì hai mũi tên chẳng đưa
 * đi đâu. Mẫu bỏ hẳn băng trượt: lưới tĩnh 4 thẻ, muốn xem tiếp thì bấm "More"
 * bên dưới tiêu đề băng. Nên 4 thẻ là vừa đủ, và 4 thẻ thừa mỗi lượt xem trang
 * chủ là 4 lần decode() ảnh + thông số cho thứ không ai vẽ ra.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * NĂM TRUY VẤN, VÀ VÌ SAO KHÔNG ÍT HƠN ĐƯỢC
 *
 *   newest(4)                       hàng mới về
 *   featured(4)                     hàng bán chạy
 *   filter(category: gong-kinh, 4)  băng gọng kính
 *   filter(category: trong-kinh, 4) băng tròng kính
 *   VariantModel::forProducts(...)  ô màu cho CẢ BỐN băng, gom làm MỘT câu
 *
 * Câu thứ năm là chỗ dễ làm sai nhất: thẻ sản phẩm của mẫu có hàng ô màu, và
 * hỏi biến thể ngay trong vòng lặp thẻ là 16 câu truy vấn cho một trang chủ.
 * forProducts() nhận cả mảng id và trả về mảng gom theo product_id — gọi MỘT
 * lần ở đây, view chỉ việc tra. Mọi trang có lưới hàng đều phải theo nếp này.
 * ═══════════════════════════════════════════════════════════════════════════
 */

class HomeController extends BaseController
{
    /** Số thẻ trên mỗi băng — mẫu xếp đúng một hàng bốn cột. */
    private const PER_BAND = 4;

    public function index(): void
    {
        $newArrivals = ProductModel::newest(self::PER_BAND);
        $bestSellers = ProductModel::featured(self::PER_BAND);

        $frames = ProductModel::filter(['category' => 'gong-kinh'], 1, self::PER_BAND)['items'];
        $lenses = ProductModel::filter(['category' => 'trong-kinh'], 1, self::PER_BAND)['items'];

        /* Gom id của cả bốn băng rồi hỏi biến thể MỘT lần. array_unique vì một
           mặt hàng rất dễ có mặt ở hai băng cùng lúc (vừa mới về vừa bán chạy),
           và hỏi trùng id chỉ làm câu IN(...) dài ra. */
        $ids = [];

        foreach ([$newArrivals, $bestSellers, $frames, $lenses] as $band) {
            foreach ($band as $p) {
                $ids[] = $p['id'];
            }
        }

        $this->renderView('home/index', [
            'pageTitle' => 'Vin Eyewear — Kính mắt chính hãng, đo khúc xạ miễn phí',
            'metaDesc'  => 'Gọng kính, kính mát và tròng kính chính hãng tại Hà Nội. '
                         . 'Đo khúc xạ miễn phí, thử kính AR trực tuyến, bảo hành trọn đời.',

            'newArrivals' => $newArrivals,
            'bestSellers' => $bestSellers,
            'frames'      => $frames,
            'lenses'      => $lenses,

            'variants' => VariantModel::forProducts(array_values(array_unique($ids))),
        ]);
    }
}
