<?php

require_once APP_PATH . '/models/ProductModel.php';

/**
 * HomeController.php
 * Route: GET /
 * View:  home/index
 *
 * Trang chủ dựng theo layout Moscot (xem screencapture-moscot-*.png):
 * hero → signature sunnies → 2 tile → feature → heritage → tints
 * → optical shop → craftsmanship → stories → visit → join.
 * Ảnh hotlink từ CDN Moscot (giai đoạn mockup).
 */
class HomeController extends BaseController
{
    private const CDN = 'https://moscot.com/cdn/shop/files/';

    public function index(): void
    {
        // Tab "Gọng ký hiệu" + lưới "Tiệm kính" đều lấy sản phẩm theo id từ ProductModel.
        $sunniesTabs = [
            [
                'id'       => 'nhe',
                'label'    => 'Gọng siêu nhẹ',
                'desc'     => 'Từ dáng tròn đến dáng vuông, chúng tôi tuyển chọn những gọng nhẹ nhất của '
                    . 'nhà Vin Eyewear. Bộ sưu tập dành cho người đeo cả ngày mà quên mất mình đang đeo kính.',
                'products' => $this->pick([1, 2, 12, 7]),
            ],
            [
                'id'       => 'ban',
                'label'    => 'Gọng bản dày',
                'desc'     => 'Acetate nguyên khối, bản dày, đường nét dứt khoát. Dành cho những ai muốn '
                    . 'chiếc kính lên tiếng trước khi mình kịp mở lời.',
                'products' => $this->pick([3, 5, 9, 11]),
            ],
        ];

        $this->renderView('home/index', [
            'pageTitle'   => 'Vin Eyewear - Kính Mắt Cao Cấp',
            'currentPage' => 'home',

            // --- Hero: ảnh full-bleed + CTA ---
            'hero' => [
                'image' => self::CDN . '1920x860_-HP_BANNER-DESKTOP-CUSTOM_MADE_TINT_2_1.jpg?v=1783603402&width=1920',
                'alt'   => 'Bộ sưu tập kính râm tròng pha màu thủ công',
                'cta'   => ['label' => 'Mua kính râm', 'link' => '/product'],
            ],

            // --- Signature Sunnies: 2 tab, mỗi tab 4 sản phẩm ---
            'sunnies' => [
                'icon'  => self::CDN . 'MOSCOT-Web_Illustrations-Lemtosh_CMT_1.png?v=1782326601&width=140',
                'title' => 'Gọng Ký Hiệu',
                'tabs'  => $sunniesTabs,
            ],

            // --- 2 tile danh mục ---
            'tiles' => [
                [
                    'title' => 'Gọng cận',
                    'link'  => '/product',
                    'image' => self::CDN . '920x920_-PROMO_GRID-EYEGLASSES-02_2_1.jpg?v=1782759499&width=920',
                ],
                [
                    'title' => 'Kính râm',
                    'link'  => '/product',
                    'image' => self::CDN . '920x920_-PROMO_GRID-SUNGLASSES-02_2_1.jpg?v=1782759503&width=920',
                ],
            ],

            // --- Feature: 1 sản phẩm nổi bật + gallery ---
            'feature' => [
                'name'    => 'Square Tortoise',
                'price'   => 1_450_000,
                'rating'  => 5,
                'reviews' => 15,
                'desc'    => 'Gọng dựa trên dáng vuông bo tròn kinh điển nhưng thanh hơn, gọng mảnh hơn '
                    . 'và cầu kính key-hole cho vẻ chỉn chu hơn một chút. Người ta bảo...',
                'cta'     => ['label' => 'Khám phá Square Tortoise', 'link' => '/product/detail'],
                'thumb'   => 'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-tortoise-pos-1_51a51dc4-f52a-4ebf-ae8c-53394cb8720c.jpg?v=1705433402&width=300',
                'gallery' => [
                    'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-tortoise-pos-2_3d0284ce-bd3e-4c66-84bb-49bd189f2988.jpg?v=1705433402&width=1000',
                    'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-tortoise-pos-1_51a51dc4-f52a-4ebf-ae8c-53394cb8720c.jpg?v=1705433402&width=1000',
                    'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-light-blue-pos-1.jpg?v=1705433402&width=1000',
                    'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-burgundy-pos-1.jpg?v=1705433402&width=1000',
                    'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-flesh-pos-1_ba8a10e6-8412-4ad5-b7cf-254ecf409829.jpg?v=1699903559&width=1000',
                ],
            ],

            // --- Heritage: ảnh B&W + panel vàng ---
            'heritage' => [
                'image'  => self::CDN . '1915-Hyman-in-Shop_1c15aa8d-1722-4b99-b9c6-bcc144d9e2e2.jpg?v=1772055310&width=1200',
                'quote'  => 'Bạn không chỉ đang đeo một chiếc kính khi chọn Vin Eyewear — bạn đang khoác lên mình một lát cắt của lịch sử, của tinh thần phố thị.',
                'author' => 'Vin Eyewear',
                'role'   => 'Thế hệ thứ năm',
                'cta'    => ['label' => 'Cội nguồn', 'link' => '/about'],
            ],

            // --- Custom Made Tints: panel xám + accordion ---
            'tints' => [
                'title' => 'Tròng Pha Màu Thủ Công™',
                'body'  => 'Tròng kính pha màu thủ công, nhúng tay từng chiếc với hơn 20 sắc độ riêng biệt. '
                    . 'Biến gọng Vin Eyewear bạn yêu thích thành kính pha màu của riêng bạn.',
                'link'  => ['label' => 'Xưởng Vin Lab', 'href' => '/ar'],
                'cta'   => ['label' => 'Xem bộ sưu tập', 'link' => '/product'],
                'image' => self::CDN . '1400x900_-FLOW-CMT_2_1.jpg?v=1783603988&width=1200',
                'rows'  => [
                    ['label' => 'Tròng đổi màu', 'link' => '/product'],
                    ['label' => 'Kính kẹp',      'link' => '/product'],
                ],
            ],

            // --- The Optical Shop: 4 sản phẩm gọng cận ---
            'optical' => [
                'icon'  => self::CDN . 'MOSCOT-Web_Illustrations-MannequinHead_46caad3d-b555-4ef1-8b00-837d8d26bb71.png?v=1776891281&width=140',
                'title' => 'Tiệm Kính Vin',
                'desc'  => [
                    'Vin Eyewear kết hợp hơn một thế kỷ kinh nghiệm quang học và tay nghề thủ công với '
                        . 'tinh thần phố thị để tạo nên những chiếc gọng không bao giờ lỗi mốt.',
                    'Dù đã có mặt ở nhiều nơi, Vin Eyewear vẫn giữ đúng cái gốc của mình: một tiệm kính của khu phố.',
                ],
                'products' => $this->pick([3, 6, 9, 11]),
            ],

            // --- Dải ảnh craftsmanship ---
            'craftsmanship' => [
                'image' => self::CDN . '1080x1080_CRAFTSMANSHIP_-_HOMEPAGE_BANNER_MOBILE_1.jpg?v=1742324666&width=1920',
                'alt'   => 'Bàn làm việc của thợ kính Vin Eyewear',
            ],

            // --- Stories ---
            'stories' => [
                'title' => 'Câu chuyện',
                'desc'  => 'Mỗi chiếc gọng đều có một câu chuyện. Đây là nơi chúng tôi kể chuyện của mình — '
                    . 'di sản, những khoảnh khắc, và cộng đồng đã làm nên tất cả.',
                'items' => [
                    [
                        'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-light-blue-pos-1.jpg?v=1705433402&width=900',
                        'title' => 'Minh Quân | Square Tortoise',
                        'body'  => 'Tay đua Minh Quân xuất hiện cùng Square Tortoise màu Light Grey với tròng pha '
                            . 'Aqua Sunrise. Chế tác từ acetate Ý, gọng nổi bật với chi tiết đinh tán kim cương…',
                        'link'  => '/product/detail',
                    ],
                    [
                        'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-tortoise-pos-1_51a51dc4-f52a-4ebf-ae8c-53394cb8720c.jpg?v=1705433402&width=900',
                        'title' => 'Hà Linh | Square Tortoise và Browline Havana pha màu thủ công',
                        'body'  => 'Vận động viên bóng rổ chuyên nghiệp Hà Linh biết cách chiến thắng cả trong lẫn '
                            . 'ngoài sân đấu — với Square Tortoise màu Tortoise cùng tròng pha Amber…',
                        'link'  => '/product/detail',
                    ],
                ],
            ],

            // --- Visit us in shop ---
            'visit' => [
                'title' => 'Ghé cửa hàng',
                'desc'  => 'Từ Ngọc Lâm đến Hoàng Hoa Thám, chúng tôi rất mong được gặp bạn trực tiếp.',
                'image' => self::CDN . '2953x2953_-SHOP-PARIS_1.jpg?v=1781041319&width=900',
                'ctas'  => [
                    ['label' => 'Đặt lịch đo mắt', 'link' => '/contact', 'style' => 'yellow'],
                    ['label' => 'Tìm cửa hàng',    'link' => '/contact', 'style' => 'black'],
                ],
            ],
        ]);
    }

    /**
     * Đổ danh sách id thành mảng sản phẩm đầy đủ cho view.
     * Bỏ qua id không tồn tại thay vì render thẻ rỗng.
     */
    private function pick(array $ids): array
    {
        $out = [];

        foreach ($ids as $id) {
            $product = ProductModel::find($id);
            if ($product !== null) {
                $out[] = $product;
            }
        }

        return $out;
    }
}
