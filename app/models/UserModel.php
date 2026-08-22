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

    /**
     * Lý do khách chọn khi yêu cầu xoá tài khoản — khoá lưu vào
     * `users.deletion_reason`, giá trị là nhãn hiện ra.
     *
     * Ô "Lý do khác" cho gõ tự do, và chữ khách gõ được lưu NGUYÊN VĂN thay
     * cho khoá. Cột là VARCHAR chứ không phải ENUM: danh sách này là thứ cửa
     * hàng sẽ muốn sửa sau khi đọc vài chục câu trả lời đầu tiên.
     */
    public const DELETION_REASONS = [
        'khong-dung'     => 'Tôi không còn dùng dịch vụ nữa',
        'rieng-tu'       => 'Tôi lo ngại về quyền riêng tư dữ liệu',
        'nhieu-tin'      => 'Tôi nhận quá nhiều tin nhắn / email',
        'tai-khoan-2'    => 'Tôi có tài khoản khác',
        'khong-hai-long' => 'Tôi không hài lòng với sản phẩm hoặc dịch vụ',
        'khac'           => 'Lý do khác',
    ];

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
         * Form đăng ký hỏi SỐ trước, email sau và không bắt buộc. Vì thế số điện
         * thoại từ TUỲ CHỌN thành BẮT BUỘC: thiếu cả hai thì tài khoản tạo ra
         * xong không ai đăng nhập vào được nữa.
         *
         * $email là tham số cuối và mặc định rỗng — ba nơi truyền vào, khác
         * nhau ở mức tin cậy:
         *   · luồng Google      địa chỉ Google đã xác nhận  -> email_verified=1
         *   · ô email khi đăng ký / trang Hồ sơ: chữ khách tự gõ, CHƯA xác
         *     minh -> email_verified giữ 0. Nó vẫn đăng nhập được, vì thứ
         *     chứng minh quyền sở hữu tài khoản là mật khẩu chứ không phải
         *     địa chỉ; cột email_verified chỉ dùng cho việc NỐI tài khoản
         *     Google (xem findOrCreateGoogle).
         */
        $email = strtolower(trim($email));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email không hợp lệ.'];
        }

        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'Mật khẩu phải có ít nhất 8 ký tự.'];
        }

        /* Trùng email thì phải nói rõ TRÙNG VỚI LOẠI TÀI KHOẢN NÀO.
           Một tài khoản đã yêu cầu xoá vẫn giữ chỗ ở cột `email` (dữ liệu gốc
           không bị đụng tới — xem khối "XOÁ TÀI KHOẢN" bên dưới), nên chính
           chủ quay lại đăng ký sẽ nhận câu "đã được đăng ký" và không hiểu vì
           sao: họ vừa xoá xong. taken() trả câu đúng cho từng trường hợp. */
        if ($email !== '') {
            $owner = Database::fetchOne(
                'SELECT deleted_at FROM users WHERE email = :e',
                ['e' => $email]
            );

            if ($owner !== null) {
                return ['ok' => false,
                        'error' => static::taken('Email này', $owner['deleted_at'] !== null)];
            }
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

            $owner = Database::fetchOne(
                'SELECT u.deleted_at
                   FROM profiles p
                   JOIN users u ON u.id = p.id
                  WHERE p.phone = :p',
                ['p' => $normalized]
            );

            if ($owner !== null) {
                return ['ok' => false,
                        'error' => static::taken('Số điện thoại này', $owner['deleted_at'] !== null)];
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
                    /* Tên rỗng thành NULL chứ không phải chuỗi rỗng: luồng
                       đăng ký mới không hỏi họ tên ở bước nào cả (xem
                       auth/_signup.php), và một ô trống thì nên nói là "chưa
                       có" thay vì "có, và nó rỗng". */
                    ['id' => $userId, 'name' => $fullName !== '' ? $fullName : null,
                     'phone' => $phone ?: null]
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
        $existing = Database::fetchOne(
            'SELECT id, deleted_at FROM users WHERE google_id = :g',
            ['g' => $sub]
        );

        if ($existing !== null) {
            /* ĐÃ YÊU CẦU XOÁ THÌ CỬA GOOGLE CŨNG PHẢI ĐÓNG.
               Không có chốt này thì nút "Đăng nhập bằng Google" là một lối
               vòng qua toàn bộ việc khoá tài khoản: attempt() chặn mật khẩu,
               còn đường này đi thẳng tới AuthMiddleware::login(). */
            if ($existing['deleted_at'] !== null) {
                return ['ok' => false, 'error' => static::deletedNotice()];
            }

            return ['ok' => true, 'id' => $existing['id'], 'created' => false];
        }

        $email = $email !== null ? strtolower(trim($email)) : null;

        if ($email !== null && $email !== '' && $emailVerified) {
            $byEmail = Database::fetchOne(
                'SELECT id, google_id, deleted_at FROM users WHERE email = :e',
                ['e' => $email]
            );

            if ($byEmail !== null) {
                /* Không NỐI tài khoản Google vào một tài khoản đã khoá: làm
                   vậy là mở lại nó bằng cửa sau, và lần đăng nhập ngay sau đó
                   sẽ đi qua nhánh google_id ở trên. */
                if ($byEmail['deleted_at'] !== null) {
                    return ['ok' => false, 'error' => static::deletedNotice()];
                }

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

        /*
         * TỚI ĐÂY MÀ EMAIL VẪN TRÙNG NGƯỜI KHÁC THÌ BỎ EMAIL ĐI, ĐỪNG NGÃ.
         *
         * Chỉ xảy ra khi Google KHÔNG xác nhận địa chỉ (nhánh 2 đã nuốt hết
         * trường hợp xác nhận rồi): tài khoản Workspace tự khai một email đang
         * thuộc về khách khác. Không được nối vào tài khoản kia — đó chính là
         * điều nhánh 2 từ chối làm — nhưng cũng không được ném lỗi:
         * `uq_users_email` sẽ chặn lệnh INSERT, và khách chỉ thấy câu "không
         * tạo được tài khoản, vui lòng thử lại", thử lại bao nhiêu lần cũng
         * hỏng y như vậy vì không có gì đổi giữa hai lần.
         *
         * Thứ định danh tài khoản này là `google_id`, không phải email. Bỏ
         * email ra thì khách vẫn đăng nhập bằng Google bình thường, và tự điền
         * một địa chỉ khác ở trang Hồ sơ nếu muốn (chỗ đó cũng kiểm trùng).
         */
        if ($email !== null && $email !== ''
            && (int) Database::fetchValue('SELECT COUNT(*) FROM users WHERE email = :e',
                                          ['e' => $email]) > 0) {
            error_log('[UserModel] Google gửi email chưa xác minh đang thuộc tài khoản khác ('
                      . $email . ') — tạo tài khoản không kèm email.');

            $email         = null;
            $emailVerified = false;
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
     *
     * MẶC ĐỊNH BỎ QUA TÀI KHOẢN ĐÃ YÊU CẦU XOÁ. Với mọi nơi gọi trừ một, đó
     * đúng là điều cần: quên mật khẩu không được gửi mã tới tài khoản đã
     * khoá, và cũng không được để lộ rằng nó từng tồn tại.
     *
     * $includeDeleted = true chỉ dành cho attempt(): ở đó ta cần TÌM THẤY tài
     * khoản để kiểm mật khẩu trước đã, rồi mới nói "tài khoản này đã khoá" —
     * chỉ chính chủ (người gõ đúng mật khẩu) nghe được câu đó.
     */
    public static function findByLogin(string $login, bool $includeDeleted = false): ?array
    {
        $login = trim($login);

        if ($login === '') {
            return null;
        }

        $alive = $includeDeleted ? '' : ' AND u.deleted_at IS NULL';

        if (looksLikePhone($login)) {
            $phone = normalizePhone($login);

            if ($phone === null) {
                return null;
            }

            return Database::fetchOne(
                'SELECT u.*, p.full_name, p.phone
                   FROM profiles p
                   JOIN users u ON u.id = p.id
                  WHERE p.phone = :phone' . $alive,
                ['phone' => $phone]
            );
        }

        return Database::fetchOne(
            'SELECT u.*, p.full_name, p.phone
               FROM users u
               LEFT JOIN profiles p ON p.id = u.id
              WHERE u.email = :email' . $alive,
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
        // includeDeleted: tài khoản đã yêu cầu xoá vẫn phải TÌM THẤY ở đây,
        // nếu không nó rơi vào nhánh "không có tài khoản nào" và chính chủ chỉ
        // nhận được câu "thông tin đăng nhập không đúng" — sai và khó hiểu.
        $user = static::findByLogin($login, true);

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

        /*
         * TÀI KHOẢN ĐÃ YÊU CẦU XOÁ — DỪNG Ở ĐÂY.
         *
         * Chốt đặt SAU password_verify chứ không phải trước, và đó là chủ ý:
         * nếu báo "tài khoản đã khoá" ngay khi tra ra dòng dữ liệu thì ô đăng
         * nhập thành công cụ dò xem số điện thoại nào từng có tài khoản ở đây
         * — đúng thứ mà thông điệp lỗi dùng chung ở trên đang tránh. Đặt sau,
         * chỉ người gõ ĐÚNG mật khẩu mới nghe được câu này.
         *
         * Cũng vì thế mà không đụng tới last_login_at bên dưới: lần này không
         * phải một lần đăng nhập.
         */
        if ($user['deleted_at'] !== null) {
            return ['ok' => false, 'error' => static::deletedNotice()];
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
    // XOÁ TÀI KHOẢN — XOÁ MỀM
    //
    // Luật bảo vệ dữ liệu cá nhân buộc phải có đường cho khách rút lui; cửa
    // hàng thì vẫn cần hồ sơ để bảo hành, đối chiếu đơn cũ và chăm sóc về
    // sau. Hai đòi hỏi đó gặp nhau ở chỗ tách "thấy được" khỏi "còn tồn tại":
    //
    //   PHÍA KHÁCH  tài khoản biến mất — không đăng nhập được (kể cả bằng
    //               Google), không lấy lại mật khẩu được, mọi cookie "ghi
    //               nhớ" bị huỷ, phiên đang mở trên máy khác bị đá ra ở lần
    //               tải trang kế tiếp (xem AuthMiddleware::userId).
    //   PHÍA CSDL   KHÔNG XOÁ MỘT DÒNG NÀO. users, profiles, orders,
    //               appointments, prescriptions, addresses giữ nguyên; chỉ
    //               `users.deleted_at` chuyển từ NULL sang thời điểm yêu cầu.
    //
    // Vì thế cả khối này không có một câu DELETE nào.
    // ========================================================================

    /**
     * Câu khách phải gõ để xác nhận, ở dạng ĐÃ CHUẨN HOÁ BẰNG slugify().
     *
     * Đi qua slugify() nghĩa là mọi cách gõ của cùng một câu đều về đúng
     * chuỗi này: có dấu hay không dấu, hoa hay thường, thừa khoảng trắng —
     * "XOA TAI KHOAN", "Xóa tài khoản", "xoa  tai  khoan" ra cùng một kết quả.
     *
     * Dùng slugify() chứ KHÔNG dùng mb_strtoupper(): máy chủ của cửa hàng
     * không nạp extension mbstring (đó là lý do cả core/helpers.php có sẵn bộ
     * utf8Length/utf8Substr/utf8Lower tự viết), nên mọi hàm mb_* đều là lỗi
     * 500 chờ sẵn.
     */
    private const CONFIRM_SLUG = 'xoa-tai-khoan';

    /**
     * Câu báo dùng CHUNG cho mọi cửa vào của một tài khoản đã khoá: đăng nhập
     * bằng mật khẩu, đăng nhập bằng Google, đăng ký lại bằng chính số cũ.
     *
     * Một hàm chứ không phải hằng vì nó ghép số hotline từ config — khách đọc
     * xong phải biết gọi đâu để mở lại, nếu không đây là cánh cửa một chiều.
     */
    public static function deletedNotice(): string
    {
        return 'Tài khoản này đã được yêu cầu xoá nên không đăng nhập được nữa. '
             . 'Nếu bạn đổi ý, vui lòng gọi ' . config('company.hotline', '')
             . ' để được mở lại.';
    }

    /**
     * Câu báo "định danh này đã có người dùng", tách hai trường hợp.
     *
     * Cùng một sự thật kỹ thuật (khoá UNIQUE chặn) nhưng hai tình huống hoàn
     * toàn khác nhau với người đọc: trùng với tài khoản đang sống là "bạn
     * đăng nhập đi"; trùng với tài khoản đã khoá là "chính bạn vừa xoá nó,
     * gọi hotline để mở lại".
     */
    private static function taken(string $what, bool $byDeleted): string
    {
        if ($byDeleted) {
            return $what . ' thuộc về một tài khoản đã được yêu cầu xoá. '
                 . 'Dữ liệu vẫn được cửa hàng lưu giữ — vui lòng gọi '
                 . config('company.hotline', '') . ' nếu bạn muốn mở lại tài khoản cũ.';
        }

        return $what . ' đã được đăng ký.';
    }

    /**
     * Tài khoản này đã yêu cầu xoá chưa.
     *
     * Trả về false cho id không tồn tại — nơi gọi duy nhất (AuthMiddleware)
     * chỉ cần biết "có phải khoá vì đã xoá không", còn id lạ thì đằng nào
     * cũng không đăng nhập được.
     */
    public static function isDeleted(string $userId): bool
    {
        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM users WHERE id = :id AND deleted_at IS NOT NULL',
            ['id' => $userId]
        ) > 0;
    }

    /**
     * Tài khoản này ra đời từ luồng Google hay không.
     *
     * Chỉ dùng để quyết định ô "mật khẩu hiện tại" ở form xoá tài khoản có
     * `required` hay không — lý do đầy đủ nằm trong requestDeletion(). Đây là
     * PHỎNG ĐOÁN chứ không phải sự thật tuyệt đối: một tài khoản Google về
     * sau có thể tự đặt mật khẩu qua luồng quên mật khẩu. Nên form chỉ nới ô
     * đó thành không bắt buộc, còn mật khẩu ĐÃ GÕ thì vẫn phải đúng.
     */
    public static function isGoogleLinked(string $userId): bool
    {
        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM users WHERE id = :id AND google_id IS NOT NULL',
            ['id' => $userId]
        ) > 0;
    }

    /**
     * Thời điểm và lý do khách rút lui — dành cho nhân viên tra khi khách gọi
     * lại. Trả null nếu tài khoản đang bình thường hoặc id không có thật.
     */
    public static function deletionInfo(string $userId): ?array
    {
        return Database::fetchOne(
            'SELECT u.deleted_at, u.deletion_reason, u.email, p.full_name, p.phone
               FROM users u
               LEFT JOIN profiles p ON p.id = u.id
              WHERE u.id = :id AND u.deleted_at IS NOT NULL',
            ['id' => $userId]
        );
    }

    /**
     * Khách tự yêu cầu khoá/xoá tài khoản của chính mình.
     *
     * BA CHỐT, theo thứ tự rẻ trước đắt sau:
     *
     *   1. Câu xác nhận gõ tay. Chốt ở MÁY CHỦ chứ không chỉ ở JavaScript:
     *      địa chỉ này POST tay được, và nó là thao tác không hoàn tác được
     *      từ phía khách.
     *   2. Mật khẩu hiện tại — cùng lý do như đổi mật khẩu: phiên đăng nhập
     *      sống hàng tuần, một máy quên khoá màn hình không được phép đủ để
     *      xoá tài khoản người khác.
     *   3. Không cho tài khoản NHÂN VIÊN đi đường này. Trang tài khoản là
     *      trang của khách; nhân viên tự khoá mình ở đây là tự khoá cả lối
     *      vào khu quản trị, và không ai trong cửa hàng mở lại được nếu đó là
     *      admin cuối cùng.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function requestDeletion(
        string $userId,
        string $password,
        string $confirm,
        string $reason
    ): array {
        $user = Database::fetchOne(
            'SELECT id, password_hash, google_id, deleted_at FROM users WHERE id = :id',
            ['id' => $userId]
        );

        if ($user === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy tài khoản.'];
        }

        // Đã khoá rồi thì coi như xong, KHÔNG báo lỗi và cũng không ghi đè
        // deleted_at: hai lần bấm nút (F5 trang xác nhận) không được phép làm
        // đổi mốc thời gian mà nhân viên đang dựa vào.
        if ($user['deleted_at'] !== null) {
            return ['ok' => true];
        }

        if (slugify($confirm) !== self::CONFIRM_SLUG) {
            return ['ok' => false, 'error' =>
                'Vui lòng gõ đúng dòng chữ xác nhận để chắc chắn bạn muốn xoá tài khoản.'];
        }

        /*
         * MẬT KHẨU: BẮT BUỘC, TRỪ TÀI KHOẢN TẠO RA TỪ GOOGLE.
         *
         * findOrCreateGoogle() đặt cho tài khoản Google một mật khẩu ngẫu
         * nhiên 32 byte mà chính khách không bao giờ biết. Bắt họ nhập mật
         * khẩu ở đây là khoá luôn quyền rút lui của họ — thứ mà tính năng này
         * sinh ra để bảo đảm. Với những tài khoản đó, phiên Google đang đăng
         * nhập cộng với câu xác nhận gõ tay là đủ.
         *
         * Nhưng nếu họ CÓ gõ mật khẩu (tài khoản Google về sau đặt mật khẩu
         * qua luồng quên mật khẩu) thì mật khẩu đó phải đúng — không im lặng
         * bỏ qua một ô đã điền.
         */
        $needsPassword = $user['google_id'] === null || $password !== '';

        if ($needsPassword && !password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Mật khẩu không đúng.'];
        }

        if (static::isStaff($userId)) {
            return ['ok' => false, 'error' =>
                'Tài khoản nhân viên không tự xoá được ở đây. Vui lòng liên hệ quản trị viên.'];
        }

        $reason = trim($reason);

        if (utf8Length($reason) > 500) {
            $reason = utf8Substr($reason, 0, 500);
        }

        try {
            Database::execute(
                /* `AND deleted_at IS NULL` để hai request song song chỉ có một
                   cái ghi được mốc thời gian. */
                'UPDATE users
                    SET deleted_at = NOW(), deletion_reason = :reason
                  WHERE id = :id AND deleted_at IS NULL',
                ['reason' => $reason !== '' ? $reason : null, 'id' => $userId]
            );
        } catch (Throwable $e) {
            error_log('[UserModel] Không khoá được tài khoản: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không xử lý được yêu cầu, vui lòng thử lại.'];
        }

        // Huỷ mọi cookie "ghi nhớ đăng nhập" trên MỌI máy. Bỏ bước này thì
        // chiếc điện thoại cũ vẫn tự đăng nhập lại ở lần mở trang sau — trong
        // khi khách vừa được báo là tài khoản đã xoá.
        RememberModel::forgetAllFor($userId);

        return ['ok' => true];
    }

    /**
     * Mở lại tài khoản đã khoá — dành cho NHÂN VIÊN, không có đường nào từ
     * giao diện khách gọi tới đây (xem database/mo-lai-tai-khoan.php).
     *
     * `deletion_reason` GIỮ NGUYÊN, không xoá đi: nó là lịch sử, và là thứ
     * duy nhất còn lại cho biết vì sao khách từng rời đi.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function restore(string $userId): array
    {
        $user = Database::fetchOne(
            'SELECT id, deleted_at FROM users WHERE id = :id',
            ['id' => $userId]
        );

        if ($user === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy tài khoản.'];
        }

        if ($user['deleted_at'] === null) {
            return ['ok' => false, 'error' => 'Tài khoản này đang hoạt động bình thường.'];
        }

        Database::execute('UPDATE users SET deleted_at = NULL WHERE id = :id', ['id' => $userId]);

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
     * Đặt hoặc đổi email của tài khoản.
     *
     * TÁCH KHỎI updateProfile() vì email nằm ở bảng `users`, không phải
     * `profiles` — và vì nó là một trong hai định danh đăng nhập, nên cần
     * kiểm tính duy nhất và trả lời được lý do từ chối.
     *
     * CHUỖI RỖNG = XOÁ EMAIL. Cho phép, nhưng chỉ khi tài khoản còn số điện
     * thoại: bỏ nốt cái cuối cùng là tự khoá mình ra ngoài, và không ai lấy
     * lại được vì mọi đường khôi phục đều đi qua một trong hai thứ đó.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function updateEmail(string $userId, string $email): array
    {
        $email = strtolower(trim($email));

        $current = Database::fetchOne(
            'SELECT u.email, p.phone
               FROM users u
               LEFT JOIN profiles p ON p.id = u.id
              WHERE u.id = :id',
            ['id' => $userId]
        );

        if ($current === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy tài khoản.'];
        }

        // Không đổi gì thì thôi — đừng dập email_verified về 0 chỉ vì khách
        // bấm Lưu ở một form còn có bốn ô khác.
        if ($email === strtolower((string) ($current['email'] ?? ''))) {
            return ['ok' => true];
        }

        if ($email === '' && ($current['phone'] ?? null) === null) {
            return ['ok' => false, 'error' =>
                'Cần giữ lại email hoặc số điện thoại để còn đăng nhập được.'];
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email không hợp lệ.'];
        }

        if ($email !== '') {
            $taken = Database::fetchValue(
                'SELECT COUNT(*) FROM users WHERE email = :e AND id <> :id',
                ['e' => $email, 'id' => $userId]
            );

            if ((int) $taken > 0) {
                return ['ok' => false, 'error' => 'Email này đã gắn với một tài khoản khác.'];
            }
        }

        try {
            Database::execute(
                /* email_verified VỀ 0 cùng lúc: địa chỉ mới là chữ khách vừa
                   gõ, chưa ai chứng minh nó là của họ. Giữ cờ cũ thì gõ vào
                   đây email của người khác là chiếm được luôn tài khoản Google
                   mang địa chỉ đó — xem nhánh nối tài khoản trong
                   findOrCreateGoogle(). */
                'UPDATE users SET email = :email, email_verified = 0 WHERE id = :id',
                [
                    // Rỗng thành NULL: cột có khoá duy nhất, mà '' thì chỉ một
                    // tài khoản dùng được.
                    'email' => $email !== '' ? $email : null,
                    'id'    => $userId,
                ]
            );
        } catch (Throwable $e) {
            error_log('[UserModel] Không đổi được email: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không lưu được email, vui lòng thử lại.'];
        }

        return ['ok' => true];
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

    // ------------------------------------------------------------------------
    // KÍNH ĐANG ĐEO
    // ------------------------------------------------------------------------

    /*
     * "Lịch sử loại kính khách đang đeo" — năm cột `wear_*` trên chính bảng
     * `prescriptions`.
     *
     * VÌ SAO KHÔNG PHẢI MỘT BẢNG LỊCH SỬ RIÊNG. Thứ cửa hàng cần khi tư vấn là
     * CẶP KÍNH KHÁCH ĐANG ĐEO, không phải chuỗi mọi cặp họ từng đeo — và cặp
     * đang đeo thì mỗi khách có đúng một, y như hồ sơ khúc xạ. Đặt cạnh số độ
     * trong cùng một bản ghi thì một truy vấn ra đủ thứ cần cho một buổi tư
     * vấn; tách bảng thì phải JOIN và phải tự định nghĩa "bản ghi nào là bản
     * đang đeo".
     *
     * Cần lịch sử thật (đổi kính lần thứ mấy, mỗi lần đổi gì) thì đó là một
     * tính năng khác và phải có bảng riêng có mốc thời gian — không phải thứ
     * nhét thêm vào đây được.
     */

    /** Chưa đeo kính — giá trị riêng, không nằm trong bảng lens_types. */
    public const WEAR_NONE = 'khong';

    /** Tính chất tròng đang dùng, cho ô nhiều lựa chọn. */
    public static function wearLensFeatures(): array
    {
        return config('taxonomy.wear_lens_features') ?? [];
    }

    /** Loại gọng đang dùng. */
    public static function wearFrameTypes(): array
    {
        return config('taxonomy.wear_frame_types') ?? [];
    }

    /** Đã dùng cặp kính hiện tại bao lâu. */
    public static function wearSinceOptions(): array
    {
        return config('taxonomy.wear_since') ?? [];
    }

    /**
     * Tên kiểu tròng đang đeo để in ra ("Đa tròng", "Chưa đeo kính"), hoặc null.
     *
     * Dùng chung danh mục với bước mua hàng (LensModel::types) chứ không dựng
     * một danh sách thứ hai: khách khai "đang đeo đa tròng" rồi vài phút sau
     * chọn "Đa tròng" ở hộp thoại mua — hai chỗ mà gọi tên khác nhau thì không
     * ai ghép được chúng lại.
     */
    public static function wearLensTypeName(?string $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        if ($id === self::WEAR_NONE) {
            return 'Chưa đeo kính';
        }

        return LensModel::findType($id)['name'] ?? null;
    }

    /** Tính chất tròng đã lưu, tách ngược thành mảng để tick lại ô nào đã chọn. */
    public static function wearFeatureList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('|', $raw))));
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

        foreach (['od_va', 'os_va', 'recommendation', 'measured_at', 'store_id', 'wear_note'] as $f) {
            $raw = trim((string) ($values[$f] ?? ''));
            $params[$f] = $raw === '' ? null : $raw;
        }

        /*
         * KÍNH ĐANG ĐEO — ba ô có danh sách cố định, kiểm bằng chính danh sách.
         *
         * Không tin chuỗi gửi lên dù ô là <select> hay <input type=checkbox>:
         * cả hai đều sửa tay được, và cột này là thứ nhân viên đọc để tư vấn.
         * Giá trị lạ thì thành NULL — "khách chưa khai", đúng hơn là ghi vào
         * hồ sơ một loại gọng không tồn tại.
         */
        $pick = static function (?string $raw, array $allowed): ?string {
            $raw = trim((string) $raw);

            return $raw !== '' && in_array($raw, $allowed, true) ? $raw : null;
        };

        $wearType = trim((string) ($values['wear_lens_type'] ?? ''));
        $params['wear_lens_type'] = $wearType !== ''
            && ($wearType === self::WEAR_NONE || LensModel::findType($wearType) !== null)
                ? $wearType : null;

        $params['wear_frame_type'] = $pick($values['wear_frame_type'] ?? null, self::wearFrameTypes());
        $params['wear_since']      = $pick($values['wear_since'] ?? null, self::wearSinceOptions());

        /* Tính chất tròng là ô NHIỀU lựa chọn -> một chuỗi ngăn bằng "|".
           Dấu gạch đứng chứ không phải dấu phẩy: nhãn nào cũng có thể chứa
           dấu phẩy ("Chống trầy, chống loá"), và khi đó tách ngược ra sai. */
        $features = [];

        foreach ((array) ($values['wear_lens_features'] ?? []) as $f) {
            $ok = $pick(is_string($f) ? $f : null, self::wearLensFeatures());

            if ($ok !== null && !in_array($ok, $features, true)) {
                $features[] = $ok;
            }
        }

        $params['wear_lens_features'] = $features === [] ? null : implode('|', $features);

        Database::execute(
            'INSERT INTO prescriptions
                (user_id, od_sph, od_cyl, od_axis, od_va,
                         os_sph, os_cyl, os_axis, os_va,
                         pd, measured_at, store_id, recommendation,
                         wear_lens_type, wear_lens_features, wear_frame_type,
                         wear_since, wear_note)
             VALUES
                (:user_id, :od_sph, :od_cyl, :od_axis, :od_va,
                           :os_sph, :os_cyl, :os_axis, :os_va,
                           :pd, :measured_at, :store_id, :recommendation,
                           :wear_lens_type, :wear_lens_features, :wear_frame_type,
                           :wear_since, :wear_note)
             ON DUPLICATE KEY UPDATE
                od_sph = VALUES(od_sph), od_cyl = VALUES(od_cyl),
                od_axis = VALUES(od_axis), od_va = VALUES(od_va),
                os_sph = VALUES(os_sph), os_cyl = VALUES(os_cyl),
                os_axis = VALUES(os_axis), os_va = VALUES(os_va),
                pd = VALUES(pd), measured_at = VALUES(measured_at),
                store_id = VALUES(store_id), recommendation = VALUES(recommendation),
                wear_lens_type = VALUES(wear_lens_type),
                wear_lens_features = VALUES(wear_lens_features),
                wear_frame_type = VALUES(wear_frame_type),
                wear_since = VALUES(wear_since), wear_note = VALUES(wear_note)',
            $params
        );
    }

    /**
     * LẦN NHẬP ĐẦU TIÊN thì dựng hồ sơ khúc xạ từ số đo khách vừa gõ ở luồng
     * mua hàng. Đã có hồ sơ rồi thì KHÔNG đụng vào.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VÌ SAO CHỈ GHI MỘT LẦN, VÀ VÌ SAO KHÔNG BAO GIỜ GHI ĐÈ
     *
     * Cửa hàng đặt ra đúng hai luật, nghe thì ngược nhau nhưng ăn khớp:
     *
     *   "Hệ thống lưu thông tin ở lần nhập hồ sơ khúc xạ đầu tiên."
     *   "Số độ cụ thể thì khách phải tự nhập lại ở những lần mua sau."
     *
     * Luật thứ hai nói về Ô NHẬP — không được điền sẵn, vì độ mắt đổi theo
     * thời gian. Luật thứ nhất nói về HỒ SƠ — khách nhập độ lần đầu thì cửa
     * hàng có ngay một bản ghi để tư vấn, không phải chờ họ tự vào trang tài
     * khoản khai lại lần nữa.
     *
     * Ghi đè ở những lần sau sẽ phá cả hai: hồ sơ trong trang tài khoản là thứ
     * khách TỰ khai và tự sửa, có ngày đo và cơ sở đo hẳn hoi. Lấy con số gõ
     * vội giữa lúc mua hàng đè lên nó là xoá một bản ghi có nguồn gốc bằng một
     * bản ghi không có, mà không hỏi ai.
     *
     * `measured_at` để NULL: đây là số khách chép từ đơn thuốc, không phải kết
     * quả một buổi đo, nên không có ngày đo nào để ghi. Hệ quả có chủ đích là
     * huy hiệu ở trang tài khoản hiện "Nên đo lại" — đúng, vì cửa hàng chưa
     * từng đo cho người này.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * Khách vãng lai ($userId null) và số đo rỗng đều là no-op.
     *
     * @param array{sph?:?string, cyl?:?string, axis?:?string} $od
     * @param array{sph?:?string, cyl?:?string, axis?:?string} $os
     */
    public static function seedPrescription(?string $userId, array $od, array $os): void
    {
        if ($userId === null) {
            return;
        }

        // Không có độ cầu ở cả hai mắt thì không có gì đáng gọi là hồ sơ —
        // cùng ngưỡng "đủ hay chưa" mà LensModel::formatRx() dùng.
        if (trim((string) ($od['sph'] ?? '')) === ''
            && trim((string) ($os['sph'] ?? '')) === '') {
            return;
        }

        if (self::prescription($userId) !== null) {
            return;
        }

        self::savePrescription($userId, [
            'od_sph'  => $od['sph']  ?? '',
            'od_cyl'  => $od['cyl']  ?? '',
            'od_axis' => $od['axis'] ?? '',
            'os_sph'  => $os['sph']  ?? '',
            'os_cyl'  => $os['cyl']  ?? '',
            'os_axis' => $os['axis'] ?? '',
        ]);
    }
}
