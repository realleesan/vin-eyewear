<?php

/**
 * CategoryModel — danh mục sản phẩm.
 *
 * Port từ phần `categories` của src/lib/shop.functions.ts (getCatalog).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THAY CHO ROW LEVEL SECURITY
 *
 * Postgres có policy "public categories" chỉ cho khách thấy hàng is_visible.
 * MySQL không có cơ chế đó, nên điều kiện ấy nằm ở visible() dưới đây.
 * Trang công khai PHẢI gọi visible(); chỉ khu quản trị mới dùng all().
 * ─────────────────────────────────────────────────────────────────────────────
 */

class CategoryModel extends BaseModel
{
    protected static string $table = 'categories';

    /**
     * Danh mục hiện cho khách xem, theo thứ tự người quản trị sắp xếp.
     */
    public static function visible(): array
    {
        return static::where(['is_visible' => 1], 'sort_order ASC, name ASC');
    }

    /**
     * Tìm theo slug, chỉ trả về nếu đang hiển thị.
     *
     * Không dùng findBy('slug', ...) trần: danh mục bị ẩn vẫn giữ nguyên slug,
     * nếu trả về thì người biết địa chỉ cũ vẫn xem được thứ đã gỡ khỏi site.
     */
    public static function findVisibleBySlug(string $slug): ?array
    {
        return static::firstWhere(['slug' => $slug, 'is_visible' => 1]);
    }

    /**
     * Danh mục kèm số sản phẩm đang bán trong đó.
     *
     * Dùng cho trang /danh-muc và bộ lọc sidebar. LEFT JOIN để danh mục chưa
     * có sản phẩm nào vẫn xuất hiện với số đếm 0, thay vì biến mất khỏi danh sách.
     */
    public static function withProductCounts(): array
    {
        return Database::fetchAll(
            'SELECT c.*, COUNT(p.id) AS product_count
               FROM categories c
               LEFT JOIN products p
                 ON p.category_id = c.id
                AND p.is_visible = 1
              WHERE c.is_visible = 1
              GROUP BY c.id
              ORDER BY c.sort_order ASC, c.name ASC'
        );
    }

    /**
     * Bảng tra slug -> tên, dùng khi cần hiện tên danh mục mà không muốn
     * truy vấn lại cho từng sản phẩm trong danh sách.
     */
    public static function slugMap(): array
    {
        $map = [];

        foreach (static::visible() as $row) {
            $map[$row['slug']] = $row['name'];
        }

        return $map;
    }
}
