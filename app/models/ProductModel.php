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
     * Toàn bộ catalog (24 sản phẩm).
     * Mỗi item: id, name, category, price, image, badge, colors[]
     * colors[] = mã hex các màu gọng, hiển thị thành hàng chấm màu ở trang chủ.
     */
    private static function catalog(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Classic Round Gold',
                'category' => 'tron',
                'price' => 1_250_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/plotz-color-gold-pos-1.jpg?v=1765207556&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/plotz-color-gold-pos-2.jpg?v=1765207556&width=800',
                'badge' => 'Bán chạy',
                'colors' => ['#C9A227', '#E4D6A7', '#9AA0A6', '#1F3A5F'],
            ],
            [
                'id' => 2,
                'name' => 'Aviator Silver',
                'category' => 'aviator',
                'price' => 990_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/mingle-color-silver-pos-1.jpg?v=1730403716&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/mingle-color-silver-pos-2.jpg?v=1730403716&width=800',
                'badge' => 'Mới',
                'colors' => ['#C4C7CA', '#5A6470', '#2E3338'],
            ],
            [
                'id' => 3,
                'name' => 'Square Tortoise',
                'category' => 'vuong',
                'price' => 1_450_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-tortoise-pos-1_51a51dc4-f52a-4ebf-ae8c-53394cb8720c.jpg?v=1705433402&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-tortoise-pos-2_3d0284ce-bd3e-4c66-84bb-49bd189f2988.jpg?v=1705433402&width=800',
                'badge' => '',
                'colors' => ['#8A5A2B', '#C8A165', '#3E2B1B', '#D9C9A3'],
            ],
            [
                'id' => 4,
                'name' => 'Cat Eye Black',
                'category' => 'cat-eye',
                'price' => 850_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/baitsim-color-black-pos-1.jpg?v=1723581229&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/baitsim-color-black-pos-2.jpg?v=1723581229&width=800',
                'badge' => 'Sale',
                'colors' => ['#1B2A41', '#0F0F0F', '#4A5568'],
            ],
            [
                'id' => 5,
                'name' => 'Wayfarer Matte Black',
                'category' => 'vuong',
                'price' => 1_100_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/arthur-color-black-pos-1.jpg?v=1758566608&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/arthur-color-black-pos-2.jpg?v=1758566608&width=800',
                'badge' => 'Bán chạy',
                'colors' => ['#111111', '#3A3A3A', '#6B6B6B'],
            ],
            [
                'id' => 6,
                'name' => 'Oval Rose Gold',
                'category' => 'tron',
                'price' => 1_350_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/zev-color-burgundy-rose-gold-pos-1.jpg?v=1758576598&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/zev-color-burgundy-rose-gold-pos-2.jpg?v=1758576598&width=800',
                'badge' => 'Mới',
                'colors' => ['#7B2D3B', '#C98B96', '#E0B7A0'],
            ],
            [
                'id' => 7,
                'name' => 'Rimless Titanium',
                'category' => 'rimless',
                'price' => 2_100_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/shav-color-silver-pos-1.jpg?v=1691366113&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/shav-color-silver-pos-2.jpg?v=1691366113&width=800',
                'badge' => '',
                'colors' => ['#D5D8DB', '#A9AFB5', '#6E767D'],
            ],
            [
                'id' => 8,
                'name' => 'Sport Wrap Gunmetal',
                'category' => 'sport',
                'price' => 780_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/bjorn-color-bamboo-pos-1_655a3a0c-e37b-487e-87ac-e36dc6bba7d8.jpg?v=1758571392&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/bjorn-color-bamboo-pos-2_cffa9de4-9395-48cc-a9c4-c64995c2ea51.jpg?v=1758571392&width=800',
                'badge' => 'Sale',
                'colors' => ['#4B5157', '#2B2F33', '#8B9197'],
            ],
            [
                'id' => 9,
                'name' => 'Browline Havana',
                'category' => 'vuong',
                'price' => 1_180_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/bluma-color-bark-pos-1.jpg?v=1691340895&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/bluma-color-bark-pos-2.jpg?v=1691340895&width=800',
                'badge' => '',
                'colors' => ['#8A5A2B', '#5C4033', '#C8A165', '#2F2A25'],
            ],
            [
                'id' => 10,
                'name' => 'Aviator Gold Mirror',
                'category' => 'aviator',
                'price' => 1_050_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/brandon-color-black-pos-1_cf41e032-9547-48bd-8487-cc03f28d6597.jpg?v=1758586205&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/products/brandon-color-black-pos-2_e76ddefc-d898-46f0-82ab-62052b8d7b9f.jpg?v=1758586205&width=800',
                'badge' => 'Bán chạy',
                'colors' => ['#C9A227', '#E6C86E', '#8A6D1F'],
            ],
            [
                'id' => 11,
                'name' => 'Cat Eye Marble',
                'category' => 'cat-eye',
                'price' => 920_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/cosnic-model-pos-1.jpg?v=1750858208&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/cosnic-model-pos-2.jpg?v=1750858208&width=800',
                'badge' => 'Mới',
                'colors' => ['#9C7B5A', '#C3A98C', '#5E4A38', '#E2D5C3'],
            ],
            [
                'id' => 12,
                'name' => 'Round Clear Lens',
                'category' => 'tron',
                'price' => 650_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/cosnic--burgundy-gold-pos-1.jpg?v=1749667639&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/cosnic--burgundy-gold-pos-2.jpg?v=1749667639&width=800',
                'badge' => '',
                'colors' => ['#C8891F', '#E0B25C', '#F0D9A8'],
            ],
            [
                'id' => 13,
                'name' => 'Classic Round Havana',
                'category' => 'tron',
                'price' => 1_290_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/miltzen-green_-color-citron-tortoise-pos-1.jpg?v=1779994168&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/miltzen-green_-color-citron-tortoise-pos-2.jpg?v=1779994168&width=800',
                'badge' => '',
                'colors' => ['#8A5A2B', '#C8A165', '#3E2B1B'],
            ],
            [
                'id' => 14,
                'name' => 'Aviator Matte Black',
                'category' => 'aviator',
                'price' => 1_020_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-le-color-blue-fade-pos-1.jpg?v=1781275109&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-le-color-blue-fade-pos-2.jpg?v=1781275109&width=800',
                'badge' => 'Bán chạy',
                'colors' => ['#111111', '#3A3A3A', '#6B6B6B'],
            ],
            [
                'id' => 15,
                'name' => 'Square Crystal',
                'category' => 'vuong',
                'price' => 1_390_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/avram-cmt-color-american-grey-fade-pos-1.jpg?v=1781715398&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/avram-cmt-color-american-grey-fade-pos-2.jpg?v=1781715398&width=800',
                'badge' => 'Mới',
                'colors' => ['#D9D9D9', '#B0B7BF', '#8892A0'],
            ],
            [
                'id' => 16,
                'name' => 'Cat Eye Burgundy',
                'category' => 'cat-eye',
                'price' => 880_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/dolt-green-color-khaki-pos-1.jpg?v=1771015388&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/dolt-green-color-khaki-pos-2.jpg?v=1771015388&width=800',
                'badge' => '',
                'colors' => ['#800020', '#A63A50', '#5C1226'],
            ],
            [
                'id' => 17,
                'name' => 'Wayfarer Tortoise',
                'category' => 'vuong',
                'price' => 1_150_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/arthur-monochrome-color-black-pos-1.jpg?v=1773243740&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/arthur-monochrome-color-black-pos-2.jpg?v=1773243740&width=800',
                'badge' => 'Sale',
                'colors' => ['#8A5A2B', '#5C4033', '#C8A165'],
            ],
            [
                'id' => 18,
                'name' => 'Oval Gold Classic',
                'category' => 'tron',
                'price' => 1_320_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/jared-color-spot-tortoise-pos-1.jpg?v=1766590619&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/jared-color-spot-tortoise-pos-2.jpg?v=1766590619&width=800',
                'badge' => '',
                'colors' => ['#C9A227', '#E4D6A7', '#9AA0A6'],
            ],
            [
                'id' => 19,
                'name' => 'Rimless Silver Light',
                'category' => 'rimless',
                'price' => 1_980_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/utz-color-antique-gold-pos-1.jpg?v=1765208065&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/utz-color-antique-gold-pos-2.jpg?v=1765208065&width=800',
                'badge' => 'Mới',
                'colors' => ['#D5D8DB', '#A9AFB5', '#6E767D'],
            ],
            [
                'id' => 20,
                'name' => 'Sport Wrap Black',
                'category' => 'sport',
                'price' => 820_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/aygen-color-bamboo-gold-pos-1.jpg?v=1765206071&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/aygen-color-bamboo-gold-pos-2.jpg?v=1765206071&width=800',
                'badge' => 'Bán chạy',
                'colors' => ['#111111', '#2B2F33', '#4B5157'],
            ],
            [
                'id' => 21,
                'name' => 'Browline Gunmetal',
                'category' => 'vuong',
                'price' => 1_210_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/mizer-color-bamboo-gold-pos-1.jpg?v=1765207058&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/mizer-color-bamboo-gold-pos-2.jpg?v=1765207058&width=800',
                'badge' => '',
                'colors' => ['#5A6470', '#2E3338', '#8B9197'],
            ],
            [
                'id' => 22,
                'name' => 'Aviator Rose Gold',
                'category' => 'aviator',
                'price' => 1_090_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/zissel-color-black-pos-1.jpg?v=1765209443&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/zissel-color-black-pos-2.jpg?v=1765209443&width=800',
                'badge' => 'Sale',
                'colors' => ['#C98B96', '#E0B7A0', '#7B2D3B'],
            ],
            [
                'id' => 23,
                'name' => 'Cat Eye Amber',
                'category' => 'cat-eye',
                'price' => 950_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/zev-color-gold-dark-green-pos-1.jpg?v=1758576598&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/zev-color-gold-dark-green-pos-2.jpg?v=1758576598&width=800',
                'badge' => '',
                'colors' => ['#C8891F', '#E0B25C', '#9C7B5A'],
            ],
            [
                'id' => 24,
                'name' => 'Round Blonde',
                'category' => 'tron',
                'price' => 690_000,
                'image' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/frankie-color-tortoise-pos-1_d8dbb969-66e8-4615-b8b3-f114e506dad6.jpg?v=1758574520&width=800',
                'image2' => 'https://cdn.shopify.com/s/files/1/2403/8187/files/frankie-color-tortoise-pos-2_6603d4b6-8c83-47b2-9570-284e77fbf3c1.jpg?v=1758574520&width=800',
                'badge' => 'Mới',
                'colors' => ['#E0B25C', '#F0D9A8', '#C8891F'],
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
