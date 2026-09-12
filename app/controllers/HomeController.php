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

    /**
     * Băng đánh giá — HAI CON SỐ, và chúng trả lời hai câu hỏi khác nhau.
     *
     *   REVIEW_VIS  = 5   số thẻ NHÌN THẤY trong một khung (khớp --hrev-vis
     *                     của assets/css/home.css). Nó cũng là số thẻ tối
     *                     thiểu view in ra: chưa đủ 5 đánh giá đã duyệt thì
     *                     phần còn lại là thẻ trống, để hàng thẻ không hụt
     *                     một khoảng giữa chừng.
     *   REVIEW_TAKE = 10  số đánh giá LẤY VỀ.
     *
     * ─────────────────────────────────────────────────────────────────────
     * VÌ SAO LẤY NHIỀU HƠN SỐ NHÌN THẤY (12/09/2026, theo yêu cầu chủ dự án)
     *
     * Trước đây lấy đúng 5 — bằng số thẻ một khung — nên ở khổ máy tính năm
     * thẻ lấp vừa khít và KHÔNG CÒN GÌ ĐỂ LƯỚT: bấm giữ kéo không nhúc
     * nhích, hai mũi tên tự ẩn vì đúng là chẳng có đâu để đi. Một băng
     * trượt chỉ sống khi có nhiều thẻ hơn số thẻ nhìn thấy.
     *
     * 10 chứ không phải "lấy hết": trang chủ không phải trang đánh giá. Đủ
     * một khung dôi ra để lướt, mà vẫn là một câu LIMIT nhỏ.
     *
     * HỆ QUẢ PHẢI BIẾT: cửa hàng mới duyệt được 3 đánh giá thì băng vẫn là
     * ba thẻ thật + hai thẻ trống, vừa khít khung, và lúc ấy KHÔNG lướt
     * được — không phải lỗi, mà vì chưa có gì ở phía sau để lướt tới.
     */
    private const REVIEW_VIS  = 5;
    private const REVIEW_TAKE = 10;

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

            /* Băng đánh giá. MỘT câu cho cả khối, và nó rỗng là chuyện BÌNH
               THƯỜNG chứ không phải lỗi: cửa hàng mới mở, hoặc đánh giá đang
               chờ duyệt trong khu quản trị. View lo phần "chưa có gì". */
            'reviews'  => ReviewModel::latestPublished(self::REVIEW_TAKE),
            'reviewSlots' => self::REVIEW_VIS,
        ]);
    }
}
