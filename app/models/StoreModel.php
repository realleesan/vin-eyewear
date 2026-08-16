<?php

/**
 * StoreModel — cơ sở cửa hàng.
 *
 * Port từ getStores trong src/lib/shop.functions.ts.
 *
 * Thay cho policy "public stores" của Postgres: active() lọc is_active = 1.
 * Cơ sở đã đóng cửa vẫn giữ trong bảng vì lịch hẹn và đơn hàng cũ còn tham
 * chiếu tới, nhưng không được hiện ra cho khách chọn nữa.
 */

class StoreModel extends BaseModel
{
    protected static string $table = 'stores';

    /**
     * Các cơ sở đang hoạt động, thứ tự theo mã để danh sách ổn định giữa
     * các lần tải trang.
     */
    public static function active(): array
    {
        return static::where(['is_active' => 1], 'code ASC');
    }

    public static function findActiveByCode(string $code): ?array
    {
        return static::firstWhere(['code' => $code, 'is_active' => 1]);
    }

    /**
     * Kiểm tra một id cơ sở có hợp lệ để đặt lịch không.
     *
     * Dùng khi nhận form: id đến từ ô <select> mà người dùng sửa được, nên
     * phải đối chiếu lại với DB chứ không tin dữ liệu gửi lên.
     */
    public static function isBookable(string $storeId): bool
    {
        return static::exists(['id' => $storeId, 'is_active' => 1]);
    }
}
