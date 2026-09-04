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

    /**
     * NHÃN ĐỊA CHỈ — Q75.1, chốt 04/09/2026.
     *
     * Hai giá trị cho giai đoạn 1. Không phải trang trí: địa chỉ công ty chỉ
     * nhận hàng trong giờ hành chính, và người sắp lịch giao cần biết điều đó
     * trước khi gọi xe — chứ không phải sau khi shipper đã tới nơi lúc 7 giờ
     * tối và gọi lại báo "toà nhà đóng cửa rồi".
     *
     * Cột trong CSDL là VARCHAR nên thêm mục về sau ("Nhà bố mẹ", "Kho") chỉ
     * là thêm vào mảng này.
     */
    public const NHAN = [
        'nha'     => 'Nhà riêng',
        'cong_ty' => 'Công ty',
    ];

    /** Nhãn để hiển thị; địa chỉ chưa gắn nhãn thì không in gì. */
    public static function tenNhan(?array $address): string
    {
        return self::NHAN[(string) ($address['nhan'] ?? '')] ?? '';
    }

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
     * Chuỗi "phường/xã, tỉnh/thành phố" để hiển thị.
     *
     * Địa chỉ được lưu thành hai cột riêng, nhưng mọi chỗ ĐỌC nó — thẻ trong
     * sổ địa chỉ, form thanh toán, profiles.address — đều cần một dòng chữ.
     * Gom phép ghép vào đây để ba nơi đó không tự nối chuỗi mỗi nơi một kiểu.
     *
     * Bỏ qua phần rỗng: địa chỉ cũ chuyển từ bản một-ô sang có thể chỉ có
     * tỉnh/thành mà chưa có phường/xã.
     */
    public static function areaText(?array $address): string
    {
        if ($address === null) {
            return '';
        }

        $parts = array_filter([
            trim((string) ($address['ward_name'] ?? '')),
            trim((string) ($address['province_name'] ?? '')),
        ], static fn (string $p): bool => $p !== '');

        return implode(', ', $parts);
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

        /* Câu SET dựng TỪ CHÍNH mảng values chứ không viết cứng danh sách cột.

           Trước 08/09/2026 danh sách ấy viết tay, và thêm hai cột Q75.1 nghĩa
           là phải nhớ sửa đúng chỗ này nữa — quên thì thêm địa chỉ mới lưu
           được ghi chú, còn SỬA địa chỉ cũ thì lặng lẽ không lưu, và không có
           lỗi nào báo. Dựng từ mảng thì cột nào validate() trả về là cột ấy
           được ghi, ở cả hai đường. */
        $dat = [];

        foreach (array_keys($values) as $cot) {
            $dat[] = '`' . $cot . '` = :' . $cot;
        }

        Database::execute(
            'UPDATE addresses SET ' . implode(', ', $dat)
            . ' WHERE id = :id AND user_id = :uid',
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

        $area = self::areaText($default);

        $text = $default === null
            ? null
            : trim($default['line1'] . ($area !== '' ? ', ' . $area : ''));

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
        $province = trim((string) ($input['province_name'] ?? ''));
        $ward     = trim((string) ($input['ward_name'] ?? ''));

        if (utf8Length($name) < 2) {
            return ['error' => 'Vui lòng nhập tên người nhận.'];
        }

        /* Số người nhận KHÁC số đăng nhập, nên chỉ chuẩn hoá cho gọn chứ không
           đòi phải là số chưa ai dùng — hai người cùng nhà dùng chung một số
           là chuyện bình thường.

           normalizeContactPhone() chứ KHÔNG normalizePhone(): từ 04/09/2026
           hàm sau chỉ còn nhận di động (Quyết định Q8), vì số đăng nhập phải
           nhận được Zalo OTP. Ô này thì không — nó để shipper gọi, và giao tới
           văn phòng rồi để số bàn lễ tân là chuyện thường. Lý do đầy đủ ghi ở
           đầu normalizeContactPhone() trong core/helpers.php. */
        $normalized = normalizeContactPhone($phone);

        if ($normalized === null) {
            return ['error' => 'Số điện thoại người nhận không hợp lệ.'];
        }

        if (utf8Length($line1) < 5) {
            return ['error' => 'Vui lòng nhập địa chỉ (số nhà, ngõ, đường).'];
        }

        /*
         * BẮT BUỘC CẢ HAI CẤP. Bản trước để ô này tuỳ chọn vì nó là một ô gõ
         * tay, đòi hỏi thì phiền mà cũng không kiểm được gì. Nay là danh sách
         * chọn sẵn nên đòi được: thiếu tỉnh/thành thì đơn hàng không giao nổi,
         * mà thiếu phường/xã thì shipper phải gọi lại hỏi.
         */
        if ($province === '') {
            return ['error' => 'Vui lòng chọn tỉnh / thành phố.'];
        }

        if ($ward === '') {
            return ['error' => 'Vui lòng chọn phường / xã.'];
        }

        return [
            'values' => [
                'recipient_name' => utf8Substr($name, 0, 255),
                'phone'          => $normalized,
                'line1'          => utf8Substr($line1, 0, 255),
                /*
                 * MÃ chỉ nhận khi là số dương, không thì để NULL.
                 *
                 * Hai giá trị này do JavaScript điền vào ô ẩn từ dữ liệu API,
                 * nên chúng vẫn là dữ liệu người dùng gửi lên và sửa được bằng
                 * công cụ nhà phát triển. Không đối chiếu ngược với API ở đây
                 * (một lượt gọi mạng cho mỗi lần lưu, mà hosting miễn phí thì
                 * chặn kết nối ra ngoài): mã sai chỉ làm form sửa chọn trượt
                 * một mục, còn thứ hiển thị và in lên đơn luôn là TÊN.
                 */
                'province_code'  => self::code($input['province_code'] ?? null),
                'province_name'  => utf8Substr($province, 0, 120),
                'ward_code'      => self::code($input['ward_code'] ?? null),
                'ward_name'      => utf8Substr($ward, 0, 120),
            ] + self::truongQ751($input),
        ];
    }

    /**
     * Hai trường Q75.1 — ghi chú giao hàng và nhãn.
     *
     * TÁCH RA để cả create() lẫn updateOwned() cùng gọi, và để nơi này là chỗ
     * DUY NHẤT quyết định "CSDL đã có hai cột ấy chưa". Nhét thẳng vào mảng
     * trên thì trên một máy chưa chạy migration, câu INSERT nhắc tới `ghi_chu`
     * và ném 1054 đúng lúc khách bấm Lưu địa chỉ — mất cả thao tác để đổi lấy
     * hai ô phụ.
     */
    private static function truongQ751(array $input): array
    {
        if (!self::coTruongQ751()) {
            return [];
        }

        $ghiChu = trim((string) ($input['ghi_chu'] ?? ''));
        $nhan   = (string) ($input['nhan'] ?? '');

        return [
            'ghi_chu' => $ghiChu !== '' ? utf8Substr($ghiChu, 0, 255) : null,
            /* Nhãn lạ -> NULL, không phải -> 'nha'. Ô này là <select> nên giá
               trị lạ chỉ đến từ một form dựng tay; im lặng đổi nó thành một
               giá trị hợp lệ là ghi vào sổ một điều khách không chọn. */
            'nhan'    => isset(self::NHAN[$nhan]) ? $nhan : null,
        ];
    }

    /** CSDL đã có hai cột Q75.1 chưa (migration 2026-09-08). */
    public static function coTruongQ751(): bool
    {
        return Database::columnExists('addresses', 'ghi_chu');
    }

    /** Mã hành chính hợp lệ (số nguyên dương) hoặc NULL. */
    private static function code(mixed $raw): ?int
    {
        $code = filter_var($raw, FILTER_VALIDATE_INT);

        return ($code === false || $code <= 0) ? null : $code;
    }
}
