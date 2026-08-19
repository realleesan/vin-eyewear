<?php

/**
 * UserModel — tài khoản, mật khẩu, phân quyền.
 *
 * Thay cho toàn bộ phần `auth` của Supabase, vốn nằm ngoài ứng dụng.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VAI TRÒ TÁCH KHỎI HỒ SƠ — VÀ KHÔNG BAO GIỜ ĐỌC TỪ SESSION
 *
 * Vai trò lưu ở bảng user_roles, đọc lại từ DB mỗi lần cần kiểm tra. KHÔNG
 * nhét vào $_SESSION rồi tin: session sống hàng tuần, nên một người bị gỡ
 * quyền admin vẫn giữ quyền cho tới khi tự đăng xuất. Truy vấn thêm một câu
 * cho mỗi lần kiểm quyền là cái giá rẻ để tránh chuyện đó.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class UserModel extends BaseModel
{
    protected static string $table = 'users';

    public const ROLES = ['customer', 'staff', 'manager', 'admin'];

    /** Vai trò được vào khu quản trị. */
    public const STAFF_ROLES = ['staff', 'manager', 'admin'];

    /**
     * Giới tính — khoá lưu vào `profiles.gender`, giá trị là nhãn hiện ra.
     *
     * Ba lựa chọn đúng như ba nút trong "Vin Eyewear Account.dc.html". Để ở
     * PHP chứ không phải ENUM của MySQL: thêm/sửa lựa chọn ở đây là một dòng,
     * còn sửa ENUM cần ALTER TABLE khoá bảng.
     */
    public const GENDERS = ['nu' => 'Nữ', 'nam' => 'Nam', 'khac' => 'Khác'];

    // ========================================================================
    // ĐĂNG KÝ / ĐĂNG NHẬP
    // ========================================================================

    /**
     * Tạo tài khoản khách mới.
     *
     * @return array ['ok'=>true,'id'=>...] | ['ok'=>false,'error'=>...]
     */
    public static function register(string $phone, string $password, string $fullName, string $email = ''): array
    {
        /*
         * SỐ ĐIỆN THOẠI THAY EMAIL LÀM THỨ ĐỂ ĐĂNG NHẬP.
         *
         * Form đăng ký không còn hỏi email. Đăng nhập vốn đã nhận cả hai (xem
         * findByLogin), nên bỏ email đi không mất lối vào nào — nhưng vì thế
         * số điện thoại từ TUỲ CHỌN thành BẮT BUỘC: thiếu cả hai thì tài khoản
         * tạo ra xong không ai đăng nhập vào được nữa.
         *
         * $email nay là tham số cuối và mặc định rỗng: chỉ luồng Google truyền
         * vào, và đó là địa chỉ Google xác nhận chứ không phải chữ khách gõ.
         */
        $email = strtolower(trim($email));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email không hợp lệ.'];
        }

        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'Mật khẩu phải có ít nhất 8 ký tự.'];
        }

        if ($email !== '' && static::exists(['email' => $email])) {
            return ['ok' => false, 'error' => 'Email này đã được đăng ký.'];
        }

        if (trim($phone) === '') {
            return ['ok' => false, 'error' => 'Vui lòng nhập số điện thoại.'];
        }

        // Số điện thoại là MỘT trong hai cách đăng nhập, nên phải chuẩn hoá
        // trước khi ghi và phải là duy nhất. Ghi nguyên văn thì "0912345678"
        // và "+84912345678" thành hai tài khoản của cùng một người, và đăng
        // nhập bằng số sẽ hên xui theo cách gõ.
        $phone = trim($phone);

        if ($phone !== '') {
            $normalized = normalizePhone($phone);

            if ($normalized === null) {
                return ['ok' => false, 'error' =>
                    'Số điện thoại không hợp lệ. Ví dụ đúng: 0912345678 hoặc +84912345678.'];
            }

            if (Database::fetchValue('SELECT COUNT(*) FROM profiles WHERE phone = :p',
                                     ['p' => $normalized]) > 0) {
                return ['ok' => false, 'error' => 'Số điện thoại này đã được đăng ký.'];
            }

            $phone = $normalized;
        }

        $userId = uuid();

        try {
            Database::transaction(static function () use ($userId, $email, $password, $fullName, $phone): void {
                Database::execute(
                    'INSERT INTO users (id, email, password_hash) VALUES (:id, :email, :hash)',
                    [
                        'id'    => $userId,
                        // Rỗng thành NULL chứ không phải chuỗi rỗng: cột có
                        // khoá duy nhất, mà '' thì chỉ một tài khoản dùng được.
                        'email' => $email !== '' ? $email : null,
                        // PASSWORD_DEFAULT để PHP tự nâng thuật toán ở bản
                        // sau mà không phải sửa dòng này
                        'hash'  => password_hash($password, PASSWORD_DEFAULT),
                    ]
                );

                Database::execute(
                    'INSERT INTO profiles (id, full_name, phone) VALUES (:id, :name, :phone)',
                    ['id' => $userId, 'name' => $fullName, 'phone' => $phone ?: null]
                );

                // Mọi tài khoản đăng ký từ ngoài đều là 'customer'.
                // Quyền cao hơn chỉ cấp bằng database/make-admin.php.
                Database::execute(
                    'INSERT INTO user_roles (id, user_id, role) VALUES (:id, :user_id, :role)',
                    ['id' => uuid(), 'user_id' => $userId, 'role' => 'customer']
                );
            });
        } catch (Throwable $e) {
            error_log('[UserModel] Không tạo được tài khoản: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không tạo được tài khoản, vui lòng thử lại.'];
        }

        return ['ok' => true, 'id' => $userId];
    }

    /**
     * Tìm hoặc tạo tài khoản từ thông tin Google đã xác minh.
     *
     * ─────────────────────────────────────────────────────────────────────
     * BA NHÁNH, THEO ĐÚNG THỨ TỰ NÀY
     *
     *   1. Đã có `google_id` này  -> chính chủ, đăng nhập.
     *   2. Chưa có, nhưng email TRÙNG một tài khoản mật khẩu sẵn có
     *                             -> NỐI Google vào tài khoản đó.
     *   3. Không khớp gì          -> tạo tài khoản mới.
     *
     * Nhánh 2 chỉ chạy khi Google báo email ĐÃ XÁC MINH. Tài khoản Google
     * Workspace do doanh nghiệp tự quản có thể khai một email bất kỳ mà chưa
     * chứng minh sở hữu; tin theo là ai đó tạo tài khoản Workspace mang email
     * của khách rồi nối thẳng vào tài khoản người ta.
     *
     * Tài khoản tạo ở nhánh 3 KHÔNG có số điện thoại và có mật khẩu ngẫu
     * nhiên không ai biết: khách đăng nhập bằng Google, không bằng mật khẩu.
     * Cột password_hash NOT NULL nên vẫn phải điền một giá trị — điền chuỗi
     * ngẫu nhiên 32 byte, chứ để rỗng thì một ngày nào đó có người so sánh
     * hash rỗng và mở cửa cho cả thiên hạ.
     *
     * @return array{ok:bool, error?:string, id?:string, created?:bool}
     */
    public static function findOrCreateGoogle(string $sub, ?string $email, ?string $name, bool $emailVerified): array
    {
        $existing = Database::fetchOne('SELECT id FROM users WHERE google_id = :g', ['g' => $sub]);

        if ($existing !== null) {
            return ['ok' => true, 'id' => $existing['id'], 'created' => false];
        }

        $email = $email !== null ? strtolower(trim($email)) : null;

        if ($email !== null && $email !== '' && $emailVerified) {
            $byEmail = Database::fetchOne('SELECT id, google_id FROM users WHERE email = :e', ['e' => $email]);

            if ($byEmail !== null) {
                // Email này đã gắn với MỘT tài khoản Google KHÁC -> dừng.
                // Trường hợp hiếm nhưng có thật khi doanh nghiệp đổi tên miền;
                // ghi đè là đá tài khoản Google cũ ra khỏi chính tài khoản đó.
                if (($byEmail['google_id'] ?? null) !== null) {
                    return ['ok' => false, 'error' => 'Email này đã liên kết với một tài khoản Google khác.'];
                }

                Database::execute(
                    'UPDATE users SET google_id = :g, email_verified = 1 WHERE id = :id',
                    ['g' => $sub, 'id' => $byEmail['id']]
                );

                return ['ok' => true, 'id' => $byEmail['id'], 'created' => false];
            }
        }

        $userId = uuid();

        try {
            Database::transaction(static function () use ($userId, $sub, $email, $name, $emailVerified): void {
                Database::execute(
                    'INSERT INTO users (id, email, google_id, password_hash, email_verified)
                     VALUES (:id, :email, :google, :hash, :verified)',
                    [
                        'id'       => $userId,
                        'email'    => ($email !== null && $email !== '') ? $email : null,
                        'google'   => $sub,
                        'hash'     => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                        'verified' => $emailVerified ? 1 : 0,
                    ]
                );

                Database::execute(
                    'INSERT INTO profiles (id, full_name, phone) VALUES (:id, :name, NULL)',
                    ['id' => $userId, 'name' => $name !== null && $name !== '' ? $name : 'Khách hàng']
                );

                Database::execute(
                    'INSERT INTO user_roles (id, user_id, role) VALUES (:id, :user_id, :role)',
                    ['id' => uuid(), 'user_id' => $userId, 'role' => 'customer']
                );
            });
        } catch (Throwable $e) {
            error_log('[UserModel] Không tạo được tài khoản Google: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không tạo được tài khoản, vui lòng thử lại.'];
        }

        return ['ok' => true, 'id' => $userId, 'created' => true];
    }

    /**
     * Tìm tài khoản theo EMAIL hoặc SỐ ĐIỆN THOẠI.
     *
     * Một hàm cho cả hai vì gần như chỗ nào cần "tìm người dùng theo thứ họ
     * gõ vào" cũng cần cả hai: đăng nhập, quên mật khẩu, tra cứu ở quầy.
     *
     * Trả về dòng `users` kèm `full_name` và `phone` lấy từ `profiles`.
     */
    public static function findByLogin(string $login): ?array
    {
        $login = trim($login);

        if ($login === '') {
            return null;
        }

        if (looksLikePhone($login)) {
            $phone = normalizePhone($login);

            if ($phone === null) {
                return null;
            }

            return Database::fetchOne(
                'SELECT u.*, p.full_name, p.phone
                   FROM profiles p
                   JOIN users u ON u.id = p.id
                  WHERE p.phone = :phone',
                ['phone' => $phone]
            );
        }

        return Database::fetchOne(
            'SELECT u.*, p.full_name, p.phone
               FROM users u
               LEFT JOIN profiles p ON p.id = u.id
              WHERE u.email = :email',
            ['email' => strtolower($login)]
        );
    }

    /**
     * Kiểm tra thông tin đăng nhập.
     *
     * $login nhận CẢ HAI: email hoặc số điện thoại Việt Nam ở bất kỳ cách gõ
     * nào (0912…, +84912…, có dấu cách/chấm). looksLikePhone() quyết định tra
     * cột nào; normalizePhone() lo phần khác biệt về cách gõ.
     *
     * Thông điệp lỗi CỐ TÌNH giống nhau cho "không tồn tại" và "sai mật khẩu".
     * Phân biệt hai trường hợp sẽ biến ô đăng nhập thành công cụ dò xem địa
     * chỉ hay số nào có tài khoản ở đây.
     */
    public static function attempt(string $login, string $password): array
    {
        $user = static::findByLogin($login);

        // Vẫn băm một chuỗi giả khi không tìm thấy tài khoản, để thời gian
        // phản hồi của hai nhánh xấp xỉ nhau. Trả về ngay lập tức sẽ nhanh
        // hơn hẳn nhánh có password_verify, và chênh lệch đó đủ để dò email.
        if ($user === null) {
            password_verify($password, '$2y$12$' . str_repeat('.', 53));

            return ['ok' => false, 'error' => 'Thông tin đăng nhập không đúng.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Thông tin đăng nhập không đúng.'];
        }

        // Nâng cấp hash khi PHP đổi thuật toán mặc định hoặc đổi độ khó.
        // Đây là lúc duy nhất có mật khẩu thô trong tay để băm lại.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::execute(
                'UPDATE users SET password_hash = :hash WHERE id = :id',
                ['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]
            );
        }

        Database::execute(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id',
            ['id' => $user['id']]
        );

        return ['ok' => true, 'id' => $user['id']];
    }

    /**
     * Đổi mật khẩu, yêu cầu biết mật khẩu cũ.
     */
    public static function changePassword(string $userId, string $current, string $new): array
    {
        $user = static::find($userId);

        if ($user === null || !password_verify($current, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Mật khẩu hiện tại không đúng.'];
        }

        if (strlen($new) < 8) {
            return ['ok' => false, 'error' => 'Mật khẩu mới phải có ít nhất 8 ký tự.'];
        }

        Database::execute(
            'UPDATE users SET password_hash = :hash WHERE id = :id',
            ['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => $userId]
        );

        return ['ok' => true];
    }

    // ========================================================================
    // HỒ SƠ & VAI TRÒ
    // ========================================================================

    /**
     * Hồ sơ khách, gộp email từ bảng users.
     */
    public static function profile(string $userId): ?array
    {
        return Database::fetchOne(
            'SELECT p.*, u.email, u.last_login_at
               FROM profiles p
               JOIN users u ON u.id = p.id
              WHERE p.id = :id',
            ['id' => $userId]
        );
    }

    /**
     * Cập nhật hồ sơ. Chỉ nhận đúng các cột được phép sửa — không đổ nguyên
     * $_POST vào, nếu không người dùng gửi thêm `id` là ghi đè khoá chính.
     */
    /**
     * @return array{ok:bool, error?:string}
     *
     * Trả về mảng chứ không phải số dòng đã sửa: từ khi số điện thoại thành
     * một cách đăng nhập, hàm này có thể TỪ CHỐI (số sai định dạng, hoặc số
     * đã thuộc về người khác) và nơi gọi cần biết lý do để hiện cho người dùng.
     */
    public static function updateProfile(string $userId, array $data): array
    {
        $allowed = ['full_name', 'phone', 'address', 'date_of_birth', 'gender', 'avatar_path'];
        $patch   = array_intersect_key($data, array_flip($allowed));

        if ($patch === []) {
            return ['ok' => true];
        }

        if (array_key_exists('gender', $patch)) {
            $g = (string) $patch['gender'];

            // Giá trị lạ thành NULL chứ không phải lỗi: ba nút giới tính không
            // có nút "bỏ chọn", nên NULL là cách duy nhất quay về trạng thái
            // chưa chọn — và cũng là thứ trả về khi ai đó sửa tay giá trị gửi lên.
            $patch['gender'] = isset(self::GENDERS[$g]) ? $g : null;
        }

        if (array_key_exists('phone', $patch)) {
            $raw = trim((string) $patch['phone']);

            if ($raw === '') {
                // Ô trống phải là NULL, không phải chuỗi rỗng: khoá UNIQUE coi
                // nhiều NULL là khác nhau, nhưng hai chuỗi rỗng thì trùng —
                // người thứ hai bỏ trống số sẽ không lưu được hồ sơ.
                $patch['phone'] = null;
            } else {
                $phone = normalizePhone($raw);

                if ($phone === null) {
                    return ['ok' => false, 'error' =>
                        'Số điện thoại không hợp lệ. Ví dụ đúng: 0912345678 hoặc +84912345678.'];
                }

                $taken = Database::fetchValue(
                    'SELECT COUNT(*) FROM profiles WHERE phone = :p AND id <> :id',
                    ['p' => $phone, 'id' => $userId]
                );

                if ((int) $taken > 0) {
                    return ['ok' => false, 'error' =>
                        'Số điện thoại này đã gắn với một tài khoản khác.'];
                }

                $patch['phone'] = $phone;
            }
        }

        try {
            Database::execute(
                'UPDATE profiles SET ' .
                implode(', ', array_map(static fn ($c) => "`{$c}` = :{$c}", array_keys($patch))) .
                ' WHERE id = :__id',
                $patch + ['__id' => $userId]
            );
        } catch (Throwable $e) {
            error_log('[UserModel] Không cập nhật được hồ sơ: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không lưu được hồ sơ, vui lòng thử lại.'];
        }

        return ['ok' => true];
    }

    /**
     * Vai trò của một tài khoản.
     */
    public static function roles(string $userId): array
    {
        $rows = Database::fetchAll(
            'SELECT role FROM user_roles WHERE user_id = :id',
            ['id' => $userId]
        );

        return array_column($rows, 'role');
    }

    /**
     * Truy vấn thẳng bảng user_roles.
     *
     * KHÔNG dùng static::exists() ở đây: các hàm kế thừa từ BaseModel đều
     * chạy trên static::$table, tức bảng `users` — mà bảng đó không có cột
     * user_id, nên câu lệnh sẽ lỗi ngay.
     */
    public static function hasRole(string $userId, string $role): bool
    {
        return Database::fetchValue(
            'SELECT 1 FROM user_roles WHERE user_id = :id AND role = :role LIMIT 1',
            ['id' => $userId, 'role' => $role]
        ) !== null;
    }

    /**
     * Có được vào khu quản trị không.
     */
    public static function isStaff(string $userId): bool
    {
        return array_intersect(self::roles($userId), self::STAFF_ROLES) !== [];
    }

    // ========================================================================
    // HỒ SƠ KHÚC XẠ
    // ========================================================================

    /**
     * Thông số đo mắt gần nhất, kèm tên cơ sở đã đo.
     *
     * LEFT JOIN chứ không JOIN: `store_id` được phép NULL (đo ở nơi khác, hoặc
     * cơ sở cũ đã đóng cửa và khoá ngoại đã SET NULL). JOIN thường sẽ làm cả
     * bản ghi biến mất trong đúng những trường hợp đó.
     */
    public static function prescription(string $userId): ?array
    {
        return Database::fetchOne(
            'SELECT p.*, s.name AS store_name
               FROM prescriptions p
               LEFT JOIN stores s ON s.id = p.store_id
              WHERE p.user_id = :id',
            ['id' => $userId]
        );
    }

    /**
     * Thông số đo mắt còn hiệu lực không?
     *
     * Khuyến cáo nhãn khoa là đo lại sau mỗi 6–12 tháng, nên mốc hết hiệu lực
     * lấy 12 tháng kể từ NGÀY ĐO. Chưa ghi ngày đo thì coi như không kết luận
     * được — trả false, giao diện sẽ hiện "Cần đo lại" thay vì "Còn hiệu lực".
     */
    public const PRESCRIPTION_VALID_MONTHS = 12;

    public static function prescriptionIsValid(?array $prescription): bool
    {
        $measured = $prescription['measured_at'] ?? null;

        if ($measured === null || $measured === '') {
            return false;
        }

        $date = date_create($measured);

        if ($date === false) {
            return false;
        }

        $date->modify('+' . self::PRESCRIPTION_VALID_MONTHS . ' months');

        return $date >= new DateTimeImmutable('today');
    }

    /**
     * Lưu hồ sơ khúc xạ. Mỗi khách đúng một bản ghi nên dùng
     * INSERT ... ON DUPLICATE KEY UPDATE thay vì tự kiểm tồn tại rồi rẽ nhánh.
     *
     * Ba nhóm cột được xử lý khác nhau nên không gộp thành một vòng lặp:
     *   số thực  sph, cyl, pd     — (float), ô trống thành NULL
     *   số nguyên axis            — kẹp về 0..180, ngoài khoảng là NULL
     *   chuỗi    va, recommendation, measured_at, store_id
     */
    public static function savePrescription(string $userId, array $values): void
    {
        $params = ['user_id' => $userId];

        foreach (['od_sph', 'od_cyl', 'os_sph', 'os_cyl', 'pd'] as $f) {
            $params[$f] = ($values[$f] ?? '') === '' ? null : (float) $values[$f];
        }

        foreach (['od_axis', 'os_axis'] as $f) {
            $raw = $values[$f] ?? '';
            // Trục loạn thị chỉ có nghĩa trong 0..180 độ; số ngoài khoảng là
            // gõ nhầm, và lưu vào thì bảng thông số in ra một góc không tồn tại.
            $params[$f] = ($raw === '' || (int) $raw < 0 || (int) $raw > 180)
                ? null : (int) $raw;
        }

        foreach (['od_va', 'os_va', 'recommendation', 'measured_at', 'store_id'] as $f) {
            $raw = trim((string) ($values[$f] ?? ''));
            $params[$f] = $raw === '' ? null : $raw;
        }

        Database::execute(
            'INSERT INTO prescriptions
                (user_id, od_sph, od_cyl, od_axis, od_va,
                         os_sph, os_cyl, os_axis, os_va,
                         pd, measured_at, store_id, recommendation)
             VALUES
                (:user_id, :od_sph, :od_cyl, :od_axis, :od_va,
                           :os_sph, :os_cyl, :os_axis, :os_va,
                           :pd, :measured_at, :store_id, :recommendation)
             ON DUPLICATE KEY UPDATE
                od_sph = VALUES(od_sph), od_cyl = VALUES(od_cyl),
                od_axis = VALUES(od_axis), od_va = VALUES(od_va),
                os_sph = VALUES(os_sph), os_cyl = VALUES(os_cyl),
                os_axis = VALUES(os_axis), os_va = VALUES(os_va),
                pd = VALUES(pd), measured_at = VALUES(measured_at),
                store_id = VALUES(store_id), recommendation = VALUES(recommendation)',
            $params
        );
    }
}
