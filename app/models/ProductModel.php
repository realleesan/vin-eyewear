<?php

/**
 * ProductModel.php
 * Nguồn dữ liệu sản phẩm duy nhất cho Home / Product / Product Detail.
 *
 * GIAI ĐOẠN MOCKUP: dữ liệu tĩnh, ảnh hotlink trực tiếp từ CDN của Moscot
 * (chưa lưu ảnh về local). Khi có DB, chỉ cần thay phần thân catalog()
 * bằng query — các hàm public bên dưới giữ nguyên chữ ký.
 */
class ProductModel
{
    /**
     * Toàn bộ catalog (36 sản phẩm = 3 trang x 12 ở trang /product).
     * Mỗi item: id, name, category, type, price, image, badge, colors[]
     *   category — dáng gọng: tron | vuong | aviator | cat-eye | rimless | sport
     *   type     — kiểu sản phẩm: kinh-ram | kinh-can | kinh-thoi-trang
     *   colors[] — mã hex các màu gọng, hiển thị thành hàng chấm màu ở trang chủ.
     * Filter sidebar trang /product suy ra danh sách lọc từ category/type/price.
     */
    private static function catalog(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Classic Round Gold',
                'category' => 'tron',
                'type' => 'kinh-can',
                'price' => 1_250_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/plotz-color-gold-pos-1.jpg?v=1765207556&width=800',
                'badge' => 'Bán chạy',
                'colors' => ['#C9A227', '#E4D6A7', '#9AA0A6', '#1F3A5F'],
            ],
            [
                'id' => 2,
                'name' => 'Aviator Silver',
                'category' => 'aviator',
                'type' => 'kinh-ram',
                'price' => 990_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/mingle-color-silver-pos-1.jpg?v=1730403716&width=800',
                'badge' => 'Mới',
                'colors' => ['#C4C7CA', '#5A6470', '#2E3338'],
            ],
            [
                'id' => 3,
                'name' => 'Square Tortoise',
                'category' => 'vuong',
                'type' => 'kinh-can',
                'price' => 1_450_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-tortoise-pos-1_51a51dc4-f52a-4ebf-ae8c-53394cb8720c.jpg?v=1705433402&width=800',
                'badge' => '',
                'colors' => ['#8A5A2B', '#C8A165', '#3E2B1B', '#D9C9A3'],
            ],
            [
                'id' => 4,
                'name' => 'Cat Eye Black',
                'category' => 'cat-eye',
                'type' => 'kinh-ram',
                'price' => 850_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/vilda-color-ink-pos-1.jpg?v=1693323973&width=800',
                'badge' => 'Sale',
                'colors' => ['#1B2A41', '#0F0F0F', '#4A5568'],
            ],
            [
                'id' => 5,
                'name' => 'Wayfarer Matte Black',
                'category' => 'vuong',
                'type' => 'kinh-ram',
                'price' => 1_100_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/arthur-color-black-pos-1.jpg?v=1758566608&width=800',
                'badge' => 'Bán chạy',
                'colors' => ['#111111', '#3A3A3A', '#6B6B6B'],
            ],
            [
                'id' => 6,
                'name' => 'Oval Rose Gold',
                'category' => 'tron',
                'type' => 'kinh-can',
                'price' => 1_350_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/zev-color-burgundy-rose-gold-pos-1.jpg?v=1758576598&width=800',
                'badge' => 'Mới',
                'colors' => ['#7B2D3B', '#C98B96', '#E0B7A0'],
            ],
            [
                'id' => 7,
                'name' => 'Rimless Titanium',
                'category' => 'rimless',
                'type' => 'kinh-can',
                'price' => 2_100_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/shav-color-silver-pos-1.jpg?v=1691366113&width=800',
                'badge' => '',
                'colors' => ['#D5D8DB', '#A9AFB5', '#6E767D'],
            ],
            [
                'id' => 8,
                'name' => 'Sport Wrap Gunmetal',
                'category' => 'sport',
                'type' => 'kinh-ram',
                'price' => 780_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/shtarker-color-gunmetal-pos-1.jpg?v=1691366461&width=800',
                'badge' => 'Sale',
                'colors' => ['#4B5157', '#2B2F33', '#8B9197'],
            ],
            [
                'id' => 9,
                'name' => 'Browline Havana',
                'category' => 'vuong',
                'type' => 'kinh-can',
                'price' => 1_180_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/yukel-color-classic-havana-gunmetal-pos-1.jpg?v=1691367929&width=800',
                'badge' => '',
                'colors' => ['#8A5A2B', '#5C4033', '#C8A165', '#2F2A25'],
            ],
            [
                'id' => 10,
                'name' => 'Aviator Gold Mirror',
                'category' => 'aviator',
                'type' => 'kinh-ram',
                'price' => 1_050_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/shtarker-color-gold-pos-1_dc9f1b50-357e-4bc5-8c80-aa9718bc9404.jpg?v=1691366461&width=800',
                'badge' => 'Bán chạy',
                'colors' => ['#C9A227', '#E6C86E', '#8A6D1F'],
            ],
            [
                'id' => 11,
                'name' => 'Cat Eye Marble',
                'category' => 'cat-eye',
                'type' => 'kinh-thoi-trang',
                'price' => 920_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/bren-color-brown-ash-pos-1_ec939b5d-1503-49e3-b3ed-fbb01d5f8c8b.jpg?v=1691338088&width=800',
                'badge' => 'Mới',
                'colors' => ['#9C7B5A', '#C3A98C', '#5E4A38', '#E2D5C3'],
            ],
            [
                'id' => 12,
                'name' => 'Round Clear Lens',
                'category' => 'tron',
                'type' => 'kinh-thoi-trang',
                'price' => 650_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/miltzen-color-blonde-pos-1_9a0ab9c8-6bf0-400c-b088-81dc14863acc.jpg?v=1715367926&width=800',
                'badge' => '',
                'colors' => ['#C8891F', '#E0B25C', '#F0D9A8'],
            ],

            /* ── 6 sản phẩm bổ sung: phủ đều kiểu sản phẩm + dải giá 480k → 3.5tr.
                 Ảnh mockup TÁI SỬ DỤNG URL CDN đã có trong dự án (chưa có ảnh riêng) ── */
            [
                'id' => 13,
                'name' => 'Round Blonde Demi',
                'category' => 'tron',
                'type' => 'kinh-thoi-trang',
                'price' => 480_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/miltzen-color-blonde-pos-1_9a0ab9c8-6bf0-400c-b088-81dc14863acc.jpg?v=1715367926&width=800',
                'badge' => 'Sale',
                'colors' => ['#E0B25C', '#F0D9A8', '#C8891F'],
            ],
            [
                'id' => 14,
                'name' => 'Aviator Polarized Black',
                'category' => 'aviator',
                'type' => 'kinh-ram',
                'price' => 1_680_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/arthur-color-black-pos-1.jpg?v=1758566608&width=800',
                'badge' => '',
                'colors' => ['#0F0F0F', '#2E3338', '#5A6470'],
            ],
            [
                'id' => 15,
                'name' => 'Square Acetate Burgundy',
                'category' => 'vuong',
                'type' => 'kinh-can',
                'price' => 1_850_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-burgundy-pos-1.jpg?v=1705433402&width=800',
                'badge' => 'Mới',
                'colors' => ['#7B2D3B', '#4A1E28', '#A85560'],
            ],
            [
                'id' => 16,
                'name' => 'Cat Eye Crystal Gold',
                'category' => 'cat-eye',
                'type' => 'kinh-thoi-trang',
                'price' => 2_450_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/bren-color-brown-ash-pos-1_ec939b5d-1503-49e3-b3ed-fbb01d5f8c8b.jpg?v=1691338088&width=800',
                'badge' => '',
                'colors' => ['#E6C86E', '#C9A227', '#F2E3B8'],
            ],
            [
                'id' => 17,
                'name' => 'Rimless Pure Gold',
                'category' => 'rimless',
                'type' => 'kinh-can',
                'price' => 2_950_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/shav-color-silver-pos-1.jpg?v=1691366113&width=800',
                'badge' => 'Bán chạy',
                'colors' => ['#C9A227', '#E6C86E', '#D5D8DB'],
            ],
            [
                'id' => 18,
                'name' => 'Sport Shield Titanium',
                'category' => 'sport',
                'type' => 'kinh-ram',
                'price' => 3_500_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/shtarker-color-gunmetal-pos-1.jpg?v=1691366461&width=800',
                'badge' => 'Mới',
                'colors' => ['#4B5157', '#8B9197', '#2B2F33'],
            ],

            /* ── 18 sản phẩm demo bổ sung (id 19–36): fill đủ 3 trang phân trang,
                 phủ đều dáng gọng / kiểu sản phẩm / khoảng giá.
                 Ảnh mockup TÁI SỬ DỤNG URL CDN đã có trong dự án ── */
            [
                'id' => 19,
                'name' => 'Panto Classic Havana',
                'category' => 'tron',
                'type' => 'kinh-can',
                'price' => 1_150_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/yukel-color-classic-havana-gunmetal-pos-1.jpg?v=1691367929&width=800',
                'badge' => '',
                'colors' => ['#8A5A2B', '#5C4033', '#C8A165'],
            ],
            [
                'id' => 20,
                'name' => 'Aviator Rose Tint',
                'category' => 'aviator',
                'type' => 'kinh-thoi-trang',
                'price' => 1_320_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/shtarker-color-gold-pos-1_dc9f1b50-357e-4bc5-8c80-aa9718bc9404.jpg?v=1691366461&width=800',
                'badge' => 'Mới',
                'colors' => ['#C98B96', '#E0B7A0', '#C9A227'],
            ],
            [
                'id' => 21,
                'name' => 'Square Navy Matte',
                'category' => 'vuong',
                'type' => 'kinh-ram',
                'price' => 890_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/arthur-color-black-pos-1.jpg?v=1758566608&width=800',
                'badge' => '',
                'colors' => ['#1F3A5F', '#1B2A41', '#4A5568'],
            ],
            [
                'id' => 22,
                'name' => 'Cat Eye Ruby',
                'category' => 'cat-eye',
                'type' => 'kinh-thoi-trang',
                'price' => 1_750_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/vilda-color-ink-pos-1.jpg?v=1693323973&width=800',
                'badge' => '',
                'colors' => ['#7B2D3B', '#A85560', '#4A1E28'],
            ],
            [
                'id' => 23,
                'name' => 'Rimless Feather Air',
                'category' => 'rimless',
                'type' => 'kinh-can',
                'price' => 1_950_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/shav-color-silver-pos-1.jpg?v=1691366113&width=800',
                'badge' => 'Mới',
                'colors' => ['#D5D8DB', '#A9AFB5'],
            ],
            [
                'id' => 24,
                'name' => 'Sport Runner Volt',
                'category' => 'sport',
                'type' => 'kinh-ram',
                'price' => 1_250_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/shtarker-color-gunmetal-pos-1.jpg?v=1691366461&width=800',
                'badge' => 'Bán chạy',
                'colors' => ['#B4C424', '#2B2F33', '#8B9197'],
            ],
            [
                'id' => 25,
                'name' => 'Round Titan Slim',
                'category' => 'tron',
                'type' => 'kinh-can',
                'price' => 2_250_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/plotz-color-gold-pos-1.jpg?v=1765207556&width=800',
                'badge' => '',
                'colors' => ['#9AA0A6', '#C4C7CA', '#5A6470'],
            ],
            [
                'id' => 26,
                'name' => 'Browline Jet Black',
                'category' => 'vuong',
                'type' => 'kinh-can',
                'price' => 1_580_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-tortoise-pos-1_51a51dc4-f52a-4ebf-ae8c-53394cb8720c.jpg?v=1705433402&width=800',
                'badge' => '',
                'colors' => ['#0F0F0F', '#3A3A3A'],
            ],
            [
                'id' => 27,
                'name' => 'Aviator Storm Grey',
                'category' => 'aviator',
                'type' => 'kinh-ram',
                'price' => 720_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/mingle-color-silver-pos-1.jpg?v=1730403716&width=800',
                'badge' => 'Sale',
                'colors' => ['#5A6470', '#8B9197', '#2E3338'],
            ],
            [
                'id' => 28,
                'name' => 'Cat Eye Pearl White',
                'category' => 'cat-eye',
                'type' => 'kinh-thoi-trang',
                'price' => 980_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/bren-color-brown-ash-pos-1_ec939b5d-1503-49e3-b3ed-fbb01d5f8c8b.jpg?v=1691338088&width=800',
                'badge' => '',
                'colors' => ['#F0EDE6', '#E2D5C3', '#C3A98C'],
            ],
            [
                'id' => 29,
                'name' => 'Round Heritage Gold 24K',
                'category' => 'tron',
                'type' => 'kinh-can',
                'price' => 3_200_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/plotz-color-gold-pos-1.jpg?v=1765207556&width=800',
                'badge' => 'Mới',
                'colors' => ['#C9A227', '#E6C86E', '#8A6D1F'],
            ],
            [
                'id' => 30,
                'name' => 'Sport Hydro Black',
                'category' => 'sport',
                'type' => 'kinh-ram',
                'price' => 1_480_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/arthur-color-black-pos-1.jpg?v=1758566608&width=800',
                'badge' => '',
                'colors' => ['#111111', '#1F3A5F'],
            ],
            [
                'id' => 31,
                'name' => 'Square Smoke Fade',
                'category' => 'vuong',
                'type' => 'kinh-ram',
                'price' => 1_680_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-burgundy-pos-1.jpg?v=1705433402&width=800',
                'badge' => '',
                'colors' => ['#4B5157', '#6B6B6B', '#2B2F33'],
            ],
            [
                'id' => 32,
                'name' => 'Rimless Sky Titanium',
                'category' => 'rimless',
                'type' => 'kinh-can',
                'price' => 3_800_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/shav-color-silver-pos-1.jpg?v=1691366113&width=800',
                'badge' => 'Bán chạy',
                'colors' => ['#A9C7E0', '#D5D8DB', '#6E767D'],
            ],
            [
                'id' => 33,
                'name' => 'Oval Champagne',
                'category' => 'tron',
                'type' => 'kinh-thoi-trang',
                'price' => 560_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/zev-color-burgundy-rose-gold-pos-1.jpg?v=1758576598&width=800',
                'badge' => 'Sale',
                'colors' => ['#E4D6A7', '#E0B7A0', '#C8A165'],
            ],
            [
                'id' => 34,
                'name' => 'Aviator Emerald',
                'category' => 'aviator',
                'type' => 'kinh-ram',
                'price' => 2_650_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/shtarker-color-gold-pos-1_dc9f1b50-357e-4bc5-8c80-aa9718bc9404.jpg?v=1691366461&width=800',
                'badge' => '',
                'colors' => ['#1E5945', '#2BCD83', '#C9A227'],
            ],
            [
                'id' => 35,
                'name' => 'Cat Eye Velvet Wine',
                'category' => 'cat-eye',
                'type' => 'kinh-can',
                'price' => 1_380_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/zev-color-burgundy-rose-gold-pos-1.jpg?v=1758576598&width=800',
                'badge' => '',
                'colors' => ['#7B2D3B', '#4A1E28', '#C98B96'],
            ],
            [
                'id' => 36,
                'name' => 'Sport Apex Carbon',
                'category' => 'sport',
                'type' => 'kinh-ram',
                'price' => 2_850_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/shtarker-color-gunmetal-pos-1.jpg?v=1691366461&width=800',
                'badge' => 'Bán chạy',
                'colors' => ['#2B2F33', '#4B5157', '#B4C424'],
            ],
        ];
    }

    /** Toàn bộ sản phẩm — trang /product */
    public static function all(): array
    {
        return self::catalog();
    }

    /** N sản phẩm đầu — section Best Sellers ở trang chủ */
    public static function bestSellers(int $limit = 8): array
    {
        return array_slice(self::catalog(), 0, $limit);
    }

    /** Tìm sản phẩm theo id, null nếu không có */
    public static function find(int $id): ?array
    {
        foreach (self::catalog() as $product) {
            if ($product['id'] === $id) {
                return $product;
            }
        }

        return null;
    }

    /** N sản phẩm gợi ý, loại trừ $excludeId để không tự gợi ý chính nó */
    public static function related(int $excludeId, int $limit = 4): array
    {
        $others = array_filter(
            self::catalog(),
            fn(array $p): bool => $p['id'] !== $excludeId
        );

        return array_slice(array_values($others), 0, $limit);
    }

    /** Id của sản phẩm hiển thị ở trang detail dùng chung (mockup) */
    public const FEATURED_ID = 3; // Square Tortoise

    /**
     * Sản phẩm cho trang detail.
     * MOCKUP: mọi thẻ sản phẩm đều trỏ về đây nên trả về 1 sản phẩm cố định.
     * Khi có DB, bỏ hàm này và gọi thẳng find($id) với id lấy từ URL.
     */
    public static function featured(): array
    {
        $product = self::find(self::FEATURED_ID);

        // description/specs bên dưới viết riêng cho Square Tortoise. Nếu id này biến mất
        // khỏi catalog thì fail to còn hơn im lặng trả về sản phẩm khác kèm mô tả sai.
        if ($product === null) {
            throw new RuntimeException(
                'ProductModel::FEATURED_ID = ' . self::FEATURED_ID . ' không có trong catalog()'
            );
        }

        $product['description'] = 'Gọng vuông bo tròn dựng từ acetate Ý nguyên khối, hoàn thiện '
            . 'thủ công qua nhiều công đoạn đánh bóng. Vân tortoise được cắt thủ công nên không '
            . 'chiếc nào trùng chiếc nào. Khớp nối thép không gỉ cùng cầu kính key-hole giữ dáng '
            . 'bền theo năm tháng — một thiết kế đi qua hơn một thế kỷ mà chưa từng lỗi mốt.';

        $product['specs'] = [
            'Chất liệu'  => 'Acetate Ý nguyên khối',
            'Kích thước' => '46 - 24 - 145 mm',
            'Dáng gọng'  => 'Vuông bo tròn (Square)',
            'Tròng kính' => 'Chống tia UV400, tùy chọn đổi tròng cận',
            'Bảo hành'   => '12 tháng chính hãng',
        ];

        $product['gallery'] = [
            'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-tortoise-pos-1_51a51dc4-f52a-4ebf-ae8c-53394cb8720c.jpg?v=1705433402&width=1000',
            'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-tortoise-pos-2_3d0284ce-bd3e-4c66-84bb-49bd189f2988.jpg?v=1705433402&width=1000',
            'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-burgundy-pos-1.jpg?v=1705433402&width=1000',
            'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-light-blue-pos-1.jpg?v=1705433402&width=1000',
        ];

        return $product;
    }
}
