<?php

/**
 * app/models/AddressModel.php — sổ địa chỉ của khách hàng.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FILE NÀY TỪNG BỊ GỠ, VÀ ĐƯỢC DỰNG LẠI — 2026-09-10, theo UC-USER-05
 *
 * Ngày 12/09 sổ địa chỉ bị thu về ĐÚNG MỘT địa chỉ nằm trong form hồ sơ
 * (migration 2026-09-12-dia-chi-vao-ho-so.sql), và file này biến mất cùng ba
 * tuyến `tai-khoan/dia-chi/*`. UC-USER-05 — Giao diện, Khu vực 2 — đòi lại một
 * SỔ: danh sách nhiều địa chỉ, mỗi cái có người nhận và số điện thoại riêng,
 * kèm bốn thao tác Thêm · Sửa · Xoá · Đặt làm mặc định.
 *
 * Dựng lại được mà không mất dữ liệu vì bước gỡ CỐ Ý làm hai nhịp: migration
 * 12/09 chỉ THÊM bốn cột vào `profiles` rồi chép sang, còn lệnh DROP nằm ở file
 * riêng 2026-09-12-go-bang-addresses.sql và file đó CHƯA từng chạy — nó không
 * được khai trong mảng MIGRATIONS của migrate.sh, và bản thân migrate.sh có một
 * nhánh từ chối chạy nó. Nên bảng `addresses` vẫn còn nguyên với đủ dữ liệu cũ.
 *
 * ⚠ ĐỪNG CHẠY 2026-09-12-go-bang-addresses.sql. Từ nay nó là một file chết —
 * xem khối cảnh báo đã thêm ở đầu file ấy.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * QUAN HỆ VỚI `profiles` — SỔ LÀ BẢN GỐC, HỒ SƠ LÀ BẢN SAO
 *
 * Năm cột `profiles.address / province_* / ward_*` KHÔNG bị bỏ đi. Chúng giữ
 * bản sao của địa chỉ ĐANG ĐƯỢC ĐẶT MẶC ĐỊNH, và dongBoHoSo() ghi đè chúng sau
 * mỗi lần sổ thay đổi.
 *
 * Vì sao không để trang thanh toán đọc thẳng bảng này: OrderController::checkout()
 * và app/views/order/checkout.php đọc UserModel::diaChi(), hàm ấy dựng dữ liệu
 * từ năm cột trên, và chừng chục chỗ trong checkout.php gọi tên khoá theo đúng
 * hình dạng đó. Giữ bản sao thì luồng thanh toán không phải sửa một dòng nào,
 * và nó vẫn chạy đúng cả khi bảng `addresses` chưa có trên máy chưa migrate.
 *
 * Chiều đồng bộ chỉ có MỘT: sổ -> hồ sơ. Không có đường ngược lại. Khách sửa
 * địa chỉ ở đâu cũng là sửa trong sổ.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỌI CÂU ĐỀU KÈM user_id
 *
 * Không có hàm nào ở đây nhận một `id` trần rồi tin nó. Địa chỉ là dữ liệu
 * riêng tư (tên, số điện thoại, nơi ở của người khác), và `id` thì nằm ngay
 * trên URL — thiếu vế `AND user_id = :u` là một khách sửa được sổ của khách
 * khác chỉ bằng cách đổi một chuỗi. Xem findOwned().
 */

class AddressModel extends BaseModel
{
    protected static string $table = 'addresses';

    /**
     * Nhãn địa chỉ — cột `nhan`, VARCHAR chứ không ENUM (xem schema.sql).
     *
     * Không phải trang trí: địa chỉ công ty chỉ nhận hàng trong giờ hành chính,
     * và người sắp lịch giao cần biết trước khi gọi xe.
     */
    public const NHAN = [
        'nha'     => 'Nhà riêng',
        'cong_ty' => 'Công ty',
    ];

    /**
     * Trần số địa chỉ mỗi khách.
     *
     * Không phải luật nghiệp vụ, là hàng rào: form này gửi POST không cần
     * captcha, nên một vòng lặp gửi tay có thể bơm hàng nghìn dòng vào bảng.
     * Mười là con số không ai đụng tới trong đời thật — người nhiều địa chỉ
     * nhất cũng chỉ có nhà, công ty, nhà bố mẹ, và vài nơi gửi quà.
     */
    public const TOI_DA = 10;

    // ========================================================================
    // ĐỌC
    // ========================================================================

    /**
     * Bảng đã có trên CSDL này chưa?
     *
     * Máy chưa chạy migration (hoặc đã lỡ chạy file DROP) thì mọi câu bên dưới
     * ném lỗi 1146. Nếp của dự án là model tự hỏi trước và trả về rỗng — xem
     * khối "Model & DB" trong CLAUDE.md — để trang tài khoản còn hiện được
     * phần hồ sơ thay vì đổ 500 nguyên trang.
     */
    public static function coBang(): bool
    {
        return Database::tableExists('addresses');
    }

    /**
     * Sổ của một khách, địa chỉ mặc định đứng đầu.
     *
     * Thứ tự phụ là created_at ASC chứ không DESC: sổ địa chỉ là thứ người ta
     * đọc để NHẬN RA cái mình cần, nên nó nên đứng yên. Xếp mới nhất lên trước
     * thì mỗi lần thêm một địa chỉ là cả danh sách nhảy chỗ.
     */
    public static function forUser(string $userId): array
    {
        if (!self::coBang()) {
            return [];
        }

        return Database::fetchAll(
            'SELECT * FROM addresses WHERE user_id = :u
              ORDER BY is_default DESC, created_at ASC',
            ['u' => $userId]
        );
    }

    /**
     * Một địa chỉ, CHỈ khi nó thuộc về đúng khách đang hỏi.
     *
     * Trả null cho cả hai trường hợp "không có mã này" và "mã này của người
     * khác" — cố ý gộp làm một. Phân biệt hai câu trả lời là nói cho người
     * đang dò biết mã nào có thật.
     */
    public static function findOwned(string $id, string $userId): ?array
    {
        if (!self::coBang()) {
            return null;
        }

        return Database::fetchOne(
            'SELECT * FROM addresses WHERE id = :id AND user_id = :u LIMIT 1',
            ['id' => $id, 'u' => $userId]
        );
    }

    /**
     * Địa chỉ đang được đặt mặc định, hoặc null khi sổ trống.
     *
     * LIMIT 1 dù luật là "đúng một mặc định": luật ấy do datMacDinh() giữ
     * trong một transaction chứ không phải một ràng buộc UNIQUE (schema.sql
     * giải thích vì sao không thể là UNIQUE). Dữ liệu lệch thì hàm này vẫn
     * phải trả về một dòng chứ không được ném lỗi.
     */
    public static function defaultFor(string $userId): ?array
    {
        if (!self::coBang()) {
            return null;
        }

        return Database::fetchOne(
            'SELECT * FROM addresses WHERE user_id = :u AND is_default = 1
              ORDER BY updated_at DESC LIMIT 1',
            ['u' => $userId]
        );
    }

    public static function demCua(string $userId): int
    {
        if (!self::coBang()) {
            return 0;
        }

        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM addresses WHERE user_id = :u',
            ['u' => $userId]
        );
    }

    // ========================================================================
    // GHI
    //
    // Cả bốn hàm dưới đây trả về cùng một hình: ['ok' => bool, 'error' => ?string].
    // Controller chỉ việc đọc 'ok' rồi flash 'error' — không hàm nào ném ra
    // ngoài, và không hàm nào tự chuyển hướng.
    // ========================================================================

    /**
     * Thêm một địa chỉ vào sổ.
     *
     * ĐỊA CHỈ ĐẦU TIÊN TỰ THÀNH MẶC ĐỊNH. Không hỏi: một sổ có đúng một địa chỉ
     * mà không có cái nào được đánh dấu thì trang thanh toán không điền sẵn
     * được gì, và khách không hiểu vì sao phải bấm thêm một nút nữa cho một
     * lựa chọn chỉ có một.
     */
    public static function them(string $userId, array $data): array
    {
        if (!self::coBang()) {
            return ['ok' => false, 'error' => 'Sổ địa chỉ chưa sẵn sàng. Vui lòng thử lại sau.'];
        }

        $sach = self::locVaKiem($data);

        if (!$sach['ok']) {
            return $sach;
        }

        if (self::demCua($userId) >= self::TOI_DA) {
            return ['ok' => false, 'error' => sprintf(
                'Sổ địa chỉ chỉ lưu được tối đa %d địa chỉ. Hãy xoá bớt trước khi thêm mới.',
                self::TOI_DA
            )];
        }

        /* Ô tick "đặt làm mặc định" trên form thêm, HOẶC sổ đang trống. */
        $macDinh = !empty($data['is_default']) || self::demCua($userId) === 0;

        try {
            Database::transaction(static function () use ($userId, $sach, $macDinh) {
                if ($macDinh) {
                    self::boMacDinhCu($userId);
                }

                self::insert($sach['data'] + [
                    'user_id'    => $userId,
                    'is_default' => $macDinh ? 1 : 0,
                ]);
            });
        } catch (Throwable $e) {
            error_log('[AddressModel::them] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không lưu được địa chỉ. Vui lòng thử lại.'];
        }

        self::dongBoHoSo($userId);

        return ['ok' => true];
    }

    /**
     * Sửa một địa chỉ đã có.
     *
     * KHÔNG đụng tới `is_default` ở đây. Form sửa không có ô tick ấy: đặt mặc
     * định là một thao tác riêng có nút riêng (UC-USER-05, Giao diện Khu vực 2),
     * và gộp vào đây thì mỗi lần sửa số nhà cũng là một lần âm thầm đổi nơi
     * nhận hàng mặc định.
     */
    public static function sua(string $id, string $userId, array $data): array
    {
        $cu = self::findOwned($id, $userId);

        if ($cu === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy địa chỉ này trong sổ của bạn.'];
        }

        $sach = self::locVaKiem($data);

        if (!$sach['ok']) {
            return $sach;
        }

        try {
            self::update($id, $sach['data']);
        } catch (Throwable $e) {
            error_log('[AddressModel::sua] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không lưu được địa chỉ. Vui lòng thử lại.'];
        }

        /* Chỉ đồng bộ khi vừa sửa đúng cái đang mặc định — hồ sơ chỉ giữ bản
           sao của cái đó, sửa một địa chỉ phụ không đổi gì bên ấy. */
        if ((int) $cu['is_default'] === 1) {
            self::dongBoHoSo($userId);
        }

        return ['ok' => true];
    }

    /**
     * Xoá một địa chỉ.
     *
     * XOÁ CÁI ĐANG MẶC ĐỊNH THÌ PHẢI CHỌN NGƯỜI THAY. Để sổ còn ba địa chỉ mà
     * không cái nào mặc định là đẩy khách vào trang thanh toán với các ô trống,
     * ngay sau một thao tác họ tưởng chỉ dọn dẹp. Người thay là địa chỉ CŨ NHẤT
     * còn lại — cùng thứ tự mà forUser() xếp, nên nó là cái đứng đầu danh sách
     * khách vừa nhìn thấy.
     */
    public static function xoa(string $id, string $userId): array
    {
        $cu = self::findOwned($id, $userId);

        if ($cu === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy địa chỉ này trong sổ của bạn.'];
        }

        try {
            Database::transaction(static function () use ($id, $userId, $cu) {
                Database::execute(
                    'DELETE FROM addresses WHERE id = :id AND user_id = :u',
                    ['id' => $id, 'u' => $userId]
                );

                if ((int) $cu['is_default'] !== 1) {
                    return;
                }

                $thay = Database::fetchValue(
                    'SELECT id FROM addresses WHERE user_id = :u
                      ORDER BY created_at ASC LIMIT 1',
                    ['u' => $userId]
                );

                if ($thay !== null) {
                    Database::execute(
                        'UPDATE addresses SET is_default = 1 WHERE id = :id',
                        ['id' => $thay]
                    );
                }
            });
        } catch (Throwable $e) {
            error_log('[AddressModel::xoa] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không xoá được địa chỉ. Vui lòng thử lại.'];
        }

        if ((int) $cu['is_default'] === 1) {
            self::dongBoHoSo($userId);
        }

        return ['ok' => true];
    }

    /**
     * Đặt một địa chỉ làm mặc định.
     *
     * HAI CÂU TRONG MỘT TRANSACTION. Bỏ cờ cũ rồi mới đặt cờ mới; đứt gánh giữa
     * hai câu mà không có transaction thì khách còn lại một sổ KHÔNG có địa chỉ
     * mặc định nào — chính trạng thái mà cả UNIQUE lẫn ứng dụng đều không bắt
     * được, vì nó hợp lệ về mặt cấu trúc.
     */
    public static function datMacDinh(string $id, string $userId): array
    {
        $dc = self::findOwned($id, $userId);

        if ($dc === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy địa chỉ này trong sổ của bạn.'];
        }

        if ((int) $dc['is_default'] === 1) {
            return ['ok' => true];   // đã là mặc định — không có việc gì để làm
        }

        try {
            Database::transaction(static function () use ($id, $userId) {
                self::boMacDinhCu($userId);

                Database::execute(
                    'UPDATE addresses SET is_default = 1 WHERE id = :id AND user_id = :u',
                    ['id' => $id, 'u' => $userId]
                );
            });
        } catch (Throwable $e) {
            error_log('[AddressModel::datMacDinh] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không đặt được địa chỉ mặc định. Vui lòng thử lại.'];
        }

        self::dongBoHoSo($userId);

        return ['ok' => true];
    }

    // ========================================================================
    // NỘI BỘ
    // ========================================================================

    private static function boMacDinhCu(string $userId): void
    {
        Database::execute(
            'UPDATE addresses SET is_default = 0 WHERE user_id = :u AND is_default = 1',
            ['u' => $userId]
        );
    }

    /**
     * Chép địa chỉ mặc định sang năm cột của `profiles`.
     *
     * Đi qua UserModel::updateProfile() chứ không viết câu UPDATE thứ hai ở
     * đây: hàm đó đã có sẵn phần dọn dữ liệu mà năm cột ấy đòi — chuỗi rỗng
     * thành NULL, và mã tỉnh/phường bị bỏ nếu không đi kèm tên (mã lạc một
     * mình là thứ address-picker.js mở form ra không chọn được mục nào).
     *
     * Sổ TRỐNG thì xoá trắng cả năm cột, không giữ lại địa chỉ vừa xoá: trang
     * thanh toán đọc chúng để điền sẵn, và điền sẵn một nơi khách vừa cố ý bỏ
     * đi là cách chắc nhất để hàng đi nhầm chỗ.
     */
    public static function dongBoHoSo(string $userId): void
    {
        $dc = self::defaultFor($userId);

        UserModel::updateProfile($userId, [
            'address'       => (string) ($dc['line1']         ?? ''),
            'province_name' => (string) ($dc['province_name'] ?? ''),
            'province_code' => (string) ($dc['province_code'] ?? ''),
            'ward_name'     => (string) ($dc['ward_name']     ?? ''),
            'ward_code'     => (string) ($dc['ward_code']     ?? ''),
        ]);
    }

    /**
     * Dọn và kiểm dữ liệu một địa chỉ gửi lên từ form.
     *
     * BỐN Ô BẮT BUỘC: người nhận, số điện thoại, tỉnh/thành, phường/xã, và địa
     * chỉ chi tiết — tức mọi thứ cần để một kiện hàng tới được nơi. Bảng cho
     * phép NULL ở tỉnh/phường (JavaScript tắt hoặc API danh mục chết thì khách
     * gõ tay, khi đó có TÊN mà không có MÃ), nhưng "không có mã" khác hẳn
     * "không có tên" — form vẫn phải đòi đủ tên.
     *
     * KHÔNG có ô Quận/Huyện, dù UC-USER-05 liệt kê nó. Từ 01/07/2025 Việt Nam
     * bỏ cấp huyện, địa chỉ còn hai cấp tỉnh/thành -> phường/xã, và
     * provinces.open-api.vn v2 cũng chỉ trả hai cấp — thêm ô ấy vào đây thì
     * không có nguồn nào đổ dữ liệu cho nó.
     *
     * @return array{ok:bool, error?:string, data?:array}
     */
    private static function locVaKiem(array $data): array
    {
        $ten = trim((string) ($data['recipient_name'] ?? ''));
        $sdt = trim((string) ($data['phone'] ?? ''));
        $so  = trim((string) ($data['line1'] ?? ''));
        $tinh = trim((string) ($data['province_name'] ?? ''));
        $phuong = trim((string) ($data['ward_name'] ?? ''));

        if (utf8Length($ten) < 2) {
            return ['ok' => false, 'error' => 'Vui lòng nhập tên người nhận.'];
        }

        $phone = normalizePhone($sdt);

        if ($phone === null) {
            return ['ok' => false, 'error' =>
                'Số điện thoại người nhận không hợp lệ. Ví dụ đúng: 0912345678.'];
        }

        if ($tinh === '' || $phuong === '') {
            return ['ok' => false, 'error' =>
                'Vui lòng chọn đầy đủ Tỉnh/Thành phố và Phường/Xã.'];
        }

        if ($so === '') {
            return ['ok' => false, 'error' =>
                'Vui lòng nhập địa chỉ chi tiết (số nhà, tên đường).'];
        }

        /* Mã đi kèm TÊN, cùng luật với UserModel::updateProfile(): có mã mà
           không tên là thứ không hiển thị được, nên mất tên thì bỏ luôn mã.
           Chiều ngược lại thì được — gõ tay có tên mà không mã là hợp lệ. */
        $ma = static function (mixed $raw): ?int {
            $s = trim((string) $raw);

            return ($s !== '' && ctype_digit($s)) ? (int) $s : null;
        };

        $nhan = (string) ($data['nhan'] ?? '');

        return ['ok' => true, 'data' => [
            'recipient_name' => utf8Substr($ten, 0, 255),
            'phone'          => $phone,
            'line1'          => utf8Substr($so, 0, 255),
            'province_name'  => utf8Substr($tinh, 0, 120),
            'province_code'  => $ma($data['province_code'] ?? ''),
            'ward_name'      => utf8Substr($phuong, 0, 120),
            'ward_code'      => $ma($data['ward_code'] ?? ''),
            'ghi_chu'        => ($g = trim((string) ($data['ghi_chu'] ?? ''))) !== ''
                ? utf8Substr($g, 0, 255) : null,
            'nhan'           => isset(self::NHAN[$nhan]) ? $nhan : null,
        ]];
    }
}
