<?php

/**
 * AddressModel — sổ địa chỉ nhận hàng của khách (/tai-khoan?muc=dia-chi).
 *
 * Dựng theo mục "Sổ địa chỉ" của "Vin Eyewear Account.dc.html".
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỌI CÂU TRUY VẤN ĐỀU KÈM user_id — KHÔNG CÓ NGOẠI LỆ
 *
 * Bảng này không có policy nào của CSDL bảo vệ (MySQL không có RLS), nên điều
 * kiện user_id trong từng câu lệnh là THỨ DUY NHẤT ngăn khách A sửa/xoá địa
 * chỉ của khách B. Vì vậy các hàm ở đây KHÔNG dùng find()/update()/delete()
 * kế thừa từ BaseModel: chúng chỉ nhận khoá chính, và một id đoán trúng là đủ
 * để chạm vào dòng của người khác.
 *
 * Hàm nào nhận $addressId thì cũng nhận $userId, và câu SQL luôn có cả hai.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * ĐÚNG MỘT ĐỊA CHỈ MẶC ĐỊNH
 *
 * Ràng buộc này KHÔNG khai được bằng UNIQUE (xem ghi chú trong schema.sql), nên
 * setDefault() giữ nó: hạ toàn bộ địa chỉ của khách xuống 0 rồi nâng đúng một
 * dòng lên 1, trong cùng một transaction.
 */

class AddressModel extends BaseModel
{
    protected static string $table = 'addresses';

    /** Trần số địa chỉ mỗi khách. Sổ địa chỉ không phải chỗ chứa ghi chú. */
    public const MAX_PER_USER = 10;

    // ========================================================================
    // ĐỌC
    // ========================================================================

    /**
     * Toàn bộ địa chỉ của một khách, mặc định lên đầu.
     */
    public static function forUser(string $userId): array
    {
        return Database::fetchAll(
            'SELECT * FROM addresses
              WHERE user_id = :uid
              ORDER BY is_default DESC, created_at ASC',
            ['uid' => $userId]
        );
    }

    /**
     * Một địa chỉ, CHỈ khi nó thuộc về $userId.
     */
    public static function findOwned(string $id, string $userId): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM addresses WHERE id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $userId]
        );
    }

    /**
     * Địa chỉ mặc định — trang thanh toán dùng để điền sẵn form giao hàng.
     */
    public static function defaultFor(string $userId): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM addresses
              WHERE user_id = :uid
              ORDER BY is_default DESC, created_at ASC
              LIMIT 1',
            ['uid' => $userId]
        );
    }

    /**
     * Tách cột `line2` thành hai mẩu cho form thanh toán.
     *
     * HAI BẢNG GHI ĐỊA CHỈ THEO HAI HÌNH KHÁC NHAU, đây là chỗ nối chúng lại:
     *
     *   sổ địa chỉ   line1 "số nhà, ngách, ngõ, đường"
     *                line2 "phường / xã, tỉnh / thành phố"   ← MỘT ô
     *   thanh toán   address_line · address_ward · address_city  ← BA ô
     *
     * Cắt ở dấu phẩy CUỐI CÙNG chứ không phải dấu đầu tiên: phần tỉnh/thành
     * luôn đứng cuối trong cách người Việt viết địa chỉ, còn phần phường/xã có
     * thể tự nó chứa dấu phẩy ("Phường Tây Hồ, Quận Tây Hồ").
     *
     * Đây là PHỎNG ĐOÁN có kiểm soát, không phải phép biến đổi chắc chắn — nên
     * nó chỉ dùng để ĐIỀN SẴN form, nơi khách nhìn thấy và sửa được ngay. Đừng
     * dùng nó ở chỗ ghi thẳng vào đơn hàng mà không ai xem lại.
     *
     * Không có dấu phẩy thì mẩu duy nhất đó vào ô TỈNH/THÀNH PHỐ: đó là đơn vị
     * rộng nhất và là phần bắt buộc phải có trong mọi địa chỉ, còn phường/xã
     * thì người ta hay bỏ qua. Đoán sai cũng chỉ tốn của khách một ô gõ lại.
     *
     * @return array{0:string, 1:string} [phường/xã, tỉnh/thành phố]
     */
    public static function splitArea(?string $line2): array
    {
        $line2 = trim((string) $line2);

        if ($line2 === '') {
            return ['', ''];
        }

        // strrpos() an toàn với UTF-8 ở đây: dấu phẩy là ký tự ASCII một byte,
        // không thể trùng với byte giữa chừng của một ký tự nhiều byte.
        $cut = strrpos($line2, ',');

        if ($cut === false) {
            return ['', $line2];
        }

        return [trim(substr($line2, 0, $cut)), trim(substr($line2, $cut + 1))];
    }

    // ========================================================================
    // GHI
    // ========================================================================

    /**
     * Thêm địa chỉ mới.
     *
     * @return array{ok:bool, error?:string, id?:string}
     */
    public static function create(string $userId, array $input): array
    {
        $data = self::validate($input);

        if (isset($data['error'])) {
            return ['ok' => false, 'error' => $data['error']];
        }

        if (static::count(['user_id' => $userId]) >= self::MAX_PER_USER) {
            return [
                'ok'    => false,
                'error' => sprintf('Sổ địa chỉ tối đa %d địa chỉ. Hãy xoá bớt trước khi thêm mới.', self::MAX_PER_USER),
            ];
        }

        // Địa chỉ ĐẦU TIÊN luôn là mặc định, dù khách không tick ô nào: có sổ
        // địa chỉ mà không có địa chỉ mặc định thì trang thanh toán không biết
        // điền sẵn cái gì.
        $isFirst   = !static::exists(['user_id' => $userId]);
        $isDefault = $isFirst || !empty($input['is_default']);

        $id = static::insert($data['values'] + [
            'user_id'    => $userId,
            'is_default' => $isDefault ? 1 : 0,
        ]);

        if ($isDefault) {
            self::setDefault($id, $userId);
        }

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Sửa một địa chỉ đang có.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function updateOwned(string $id, string $userId, array $input): array
    {
        if (self::findOwned($id, $userId) === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy địa chỉ.'];
        }

        $data = self::validate($input);

        if (isset($data['error'])) {
            return ['ok' => false, 'error' => $data['error']];
        }

        $values = $data['values'];

        Database::execute(
            'UPDATE addresses
                SET recipient_name = :recipient_name, phone = :phone,
                    line1 = :line1, line2 = :line2
              WHERE id = :id AND user_id = :uid',
            $values + ['id' => $id, 'uid' => $userId]
        );

        if (!empty($input['is_default'])) {
            self::setDefault($id, $userId);
        } else {
            self::syncProfileAddress($userId);
        }

        return ['ok' => true];
    }

    /**
     * Xoá một địa chỉ.
     *
     * Không cho xoá địa chỉ mặc định khi vẫn còn địa chỉ khác — xoá xong thì
     * khách không còn địa chỉ mặc định nào, mà giao diện không có chỗ nào báo
     * điều đó. Muốn xoá thì đặt cái khác làm mặc định trước. Đây cũng là lý do
     * bản thiết kế KHÔNG vẽ nút "Xoá" trên thẻ địa chỉ mặc định.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function deleteOwned(string $id, string $userId): array
    {
        $row = self::findOwned($id, $userId);

        if ($row === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy địa chỉ.'];
        }

        if ((int) $row['is_default'] === 1 && static::count(['user_id' => $userId]) > 1) {
            return [
                'ok'    => false,
                'error' => 'Hãy đặt một địa chỉ khác làm mặc định trước khi xoá địa chỉ này.',
            ];
        }

        Database::execute(
            'DELETE FROM addresses WHERE id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $userId]
        );

        self::syncProfileAddress($userId);

        return ['ok' => true];
    }

    /**
     * Đặt một địa chỉ làm mặc định, hạ tất cả những cái còn lại xuống.
     *
     * Hai câu UPDATE nằm trong CÙNG một transaction: nếu câu thứ hai hỏng mà
     * câu thứ nhất đã ghi, khách mất sạch địa chỉ mặc định.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function setDefault(string $id, string $userId): array
    {
        if (self::findOwned($id, $userId) === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy địa chỉ.'];
        }

        Database::transaction(static function () use ($id, $userId): void {
            Database::execute(
                'UPDATE addresses SET is_default = 0 WHERE user_id = :uid',
                ['uid' => $userId]
            );

            Database::execute(
                'UPDATE addresses SET is_default = 1 WHERE id = :id AND user_id = :uid',
                ['id' => $id, 'uid' => $userId]
            );
        });

        self::syncProfileAddress($userId);

        return ['ok' => true];
    }

    // ========================================================================
    // NỘI BỘ
    // ========================================================================

    /**
     * Chép địa chỉ mặc định sang `profiles.address`.
     *
     * Cột cũ đó KHÔNG bị bỏ khi sổ địa chỉ ra đời: trang thanh toán và
     * UserModel::updateProfile vẫn đang đọc nó, và các đơn hàng cũ đã chép giá
     * trị của nó vào `orders.shipping_address`. Đổi hết những chỗ ấy sang bảng
     * mới là một thay đổi riêng, không thuộc phạm vi trang tài khoản.
     *
     * Nên ở đây giữ hai bên đồng bộ: sổ địa chỉ là nguồn thật, `profiles.address`
     * là bản sao chỉ-đọc của dòng mặc định. Gọi sau MỌI thay đổi sổ địa chỉ.
     */
    private static function syncProfileAddress(string $userId): void
    {
        $default = self::defaultFor($userId);

        $text = $default === null
            ? null
            : trim($default['line1'] . ($default['line2'] !== null && $default['line2'] !== ''
                ? ', ' . $default['line2'] : ''));

        Database::execute(
            'UPDATE profiles SET address = :addr WHERE id = :uid',
            ['addr' => $text, 'uid' => $userId]
        );
    }

    /**
     * Kiểm tra và chuẩn hoá dữ liệu form.
     *
     * @return array{values?:array, error?:string}
     */
    private static function validate(array $input): array
    {
        $name  = trim((string) ($input['recipient_name'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $line1 = trim((string) ($input['line1'] ?? ''));
        $line2 = trim((string) ($input['line2'] ?? ''));

        if (utf8Length($name) < 2) {
            return ['error' => 'Vui lòng nhập tên người nhận.'];
        }

        // Số người nhận KHÁC số đăng nhập, nên chỉ chuẩn hoá cho gọn chứ không
        // đòi phải là số chưa ai dùng — hai người cùng nhà dùng chung một số
        // là chuyện bình thường.
        $normalized = normalizePhone($phone);

        if ($normalized === null) {
            return ['error' => 'Số điện thoại người nhận không hợp lệ.'];
        }

        if (utf8Length($line1) < 5) {
            return ['error' => 'Vui lòng nhập địa chỉ (số nhà, ngõ, đường).'];
        }

        return [
            'values' => [
                'recipient_name' => utf8Substr($name, 0, 255),
                'phone'          => $normalized,
                'line1'          => utf8Substr($line1, 0, 255),
                'line2'          => $line2 === '' ? null : utf8Substr($line2, 0, 255),
            ],
        ];
    }
}
