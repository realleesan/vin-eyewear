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

    /*
     * BA VAI TRÒ — SRS v2.1.0, mục 5.2.
     *
     * Khách hàng · Nhân viên · Quản trị viên. Khu quản trị chỉ còn HAI bậc.
     *
     * 'technician' (Kỹ thuật viên) và 'manager' (Quản lý cơ sở) đã gỡ. Việc
     * của Kỹ thuật viên — nhập và đính chính hồ sơ đo mắt — nay là việc của
     * mọi Nhân viên; quyền của Quản lý cơ sở phần lớn chuyển lên Quản trị
     * viên, trừ ba thao tác đi ngược xuống Nhân viên (bấm mốc mài, đánh dấu đã
     * báo hàng về, và nhóm hồ sơ đo mắt). Xem ma trận ở mục 5.2.2 của SRS.
     *
     * ⚠ HAI HẰNG NÀY PHẢI KHỚP với ENUM `user_roles.role` trong CSDL. Bỏ một
     * vai trò ở đây mà quên ALTER ENUM thì dữ liệu cũ vẫn mang giá trị đó và
     * người ấy KHÔNG vào được khu quản trị mà không có thông báo nào nói vì
     * sao — isStaff() chỉ lặng lẽ trả false. Vì thế migration đợt 2 phải
     * CHUYỂN DỮ LIỆU TRƯỚC rồi mới thu ENUM.
     */
    public const ROLES = ['customer', 'staff', 'admin'];

    /** Vai trò được vào khu quản trị. */
    public const STAFF_ROLES = ['staff', 'admin'];

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
    public static function register(
        string $phone,
        string $password,
        string $fullName,
        string $email = '',
        string $termsVersion = ''
    ): array {
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
            return ['ok' => false, 'error' => 'Email không hợp lệ. Vui lòng kiểm tra lại.'];
        }

        /* CHỐT TẦNG MODEL, dùng ĐÚNG hàm kiểm mà controller dùng.

           AuthController::signupSubmit() đã gọi passwordProblem() trước khi
           tới đây, nên trước mắt không lộ ra gì. Nhưng đây là chốt SÂU NHẤT —
           chốt mà đường đặt mật khẩu thứ tư thêm sau này sẽ dựa vào — và để nó
           yếu hơn tầng trên thì hai tầng nói hai luật khác nhau: chỗ này nhận
           một mật khẩu 40 ký tự không có ký tự đặc biệt mà controller từ chối.

           Cùng một lý lẽ đã ghi ở changePassword() và createStaff(): mọi đường
           đặt mật khẩu phải đi qua đúng một hàm kiểm. */
        if (($loiMatKhau = passwordProblem($password)) !== null) {
            return ['ok' => false, 'error' => $loiMatKhau];
        }

        if ($email !== '' && static::exists(['email' => $email])) {
            return ['ok' => false, 'error' =>
                'Email này đã được đăng ký. Vui lòng sử dụng Email khác hoặc đăng nhập.'];
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
                    'Số điện thoại không hợp lệ. Vui lòng kiểm tra lại.'];
            }

            if (Database::fetchValue('SELECT COUNT(*) FROM profiles WHERE phone = :p',
                                     ['p' => $normalized]) > 0) {
                return ['ok' => false, 'error' =>
                    'Số điện thoại này đã được đăng ký. Vui lòng đăng nhập.'];
            }

            $phone = $normalized;
        }

        $userId = uuid();

        try {
            Database::transaction(static function () use ($userId, $email, $password, $fullName, $phone, $termsVersion): void {
                /*
                 * VẾT ĐỒNG Ý ĐIỀU KHOẢN nằm trong CHÍNH câu INSERT tạo tài khoản.
                 *
                 * Không tách thành một UPDATE chạy sau: câu ấy hỏng thì còn lại
                 * đúng thứ không được phép tồn tại — một tài khoản đã tạo xong
                 * mà không có vết nào cho biết người ta đã đồng ý gì.
                 *
                 * Phiên bản rỗng -> ghi NULL cả hai cột. Rỗng nghĩa là nơi gọi
                 * KHÔNG đi qua form đăng ký (làm test, hoặc một luồng nội bộ),
                 * và "không biết" phải trông khác hẳn "đã đồng ý bản rỗng".
                 */
                Database::execute(
                    'INSERT INTO users (id, email, password_hash, terms_accepted_at, terms_version)
                     VALUES (:id, :email, :hash, :accepted_at, :terms_version)',
                    [
                        'id'    => $userId,
                        // Rỗng thành NULL chứ không phải chuỗi rỗng: cột có
                        // khoá duy nhất, mà '' thì chỉ một tài khoản dùng được.
                        'email' => $email !== '' ? $email : null,
                        // PASSWORD_DEFAULT để PHP tự nâng thuật toán ở bản
                        // sau mà không phải sửa dòng này
                        'hash'  => password_hash($password, PASSWORD_DEFAULT),
                        'accepted_at'   => $termsVersion !== '' ? date('Y-m-d H:i:s') : null,
                        'terms_version' => $termsVersion !== '' ? $termsVersion : null,
                    ]
                );

                Database::execute(
                    'INSERT INTO profiles (id, full_name, phone) VALUES (:id, :name, :phone)',
                    /* Tên rỗng thành NULL chứ không phải chuỗi rỗng: một ô
                       trống thì nên nói là "chưa có" thay vì "có, và nó rỗng".
                       Form đăng ký nay BẮT BUỘC họ tên (xem signupErrors), nên
                       nhánh rỗng chỉ còn dành cho những nơi gọi register()
                       không đi qua form — test, hoặc một luồng nội bộ. */
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

            /* EF-13 — giao dịch đã tự lùi lại, không còn dòng dở dang nào.
               Câu chữ là nguyên văn đặc tả: khách không cần biết hỏng ở đâu,
               chi tiết nằm trong error log cho người sửa. */
            return ['ok' => false, 'error' => 'Có lỗi xảy ra. Vui lòng thử lại sau.'];
        }

        return ['ok' => true, 'id' => $userId];
    }

    /**
     * Tìm hoặc tạo tài khoản từ thông tin Google đã xác minh.
     *
     * ─────────────────────────────────────────────────────────────────────
     * BA NHÁNH — ĐÚNG AF-01, AF-02, AF-03 CỦA UC-USER-01
     *
     *   1. Đã có `google_id` này        -> chính chủ, đăng nhập, KHÔNG tạo mới.
     *   2. Chưa có + email chưa tồn tại -> tạo tài khoản mới và liên kết.
     *   3. Chưa có + email ĐÃ tồn tại   -> DỪNG. Không tạo mới, không liên kết.
     *
     * ─────────────────────────────────────────────────────────────────────
     * NHÁNH 3 TRƯỚC ĐÂY TỰ ĐỘNG NỐI GOOGLE VÀO TÀI KHOẢN TRÙNG EMAIL — ĐÃ GỠ
     *
     * BR-UC.USER.01-07 chốt: "Google User ID là khoá xác định liên kết với Vin
     * Eyewear (không dùng Email để xác định liên kết)", và AF-03 nói thẳng
     * "KHÔNG tự động liên kết Google với tài khoản đã tồn tại".
     *
     * Lý do không chỉ là chữ nghĩa: nối tự động là MỞ THÊM MỘT CHÌA cho một
     * cánh cửa đang khoá, dựa trên một lời khai của bên thứ ba. Google báo
     * "email này đã xác minh" là đủ tin để tạo tài khoản MỚI mang email ấy,
     * nhưng không đủ để trao quyền vào một tài khoản đã có sẵn đơn hàng, hồ sơ
     * đo mắt và địa chỉ giao hàng của người khác.
     *
     * Đường liên kết đúng vì thế đi ngược lại: khách đăng nhập bằng SĐT/mật
     * khẩu — tức là chứng minh mình là chủ tài khoản — rồi mới nối Google vào.
     * ⚠ CHỖ NỐI ẤY CHƯA CÓ trong trang Hồ sơ; câu báo dưới đây hứa một việc mà
     * hiện khách chưa tự làm được, và đó là việc còn thiếu của Phase 1.
     *
     * Tài khoản tạo ở nhánh 2 KHÔNG có số điện thoại và có mật khẩu ngẫu
     * nhiên không ai biết: khách đăng nhập bằng Google, không bằng mật khẩu.
     * Cột password_hash NOT NULL nên vẫn phải điền một giá trị — điền chuỗi
     * ngẫu nhiên 32 byte, chứ để rỗng thì một ngày nào đó có người so sánh
     * hash rỗng và mở cửa cho cả thiên hạ.
     *
     * $allowCreate = false thì nhánh 2 KHÔNG chạy, chỉ báo về 'need_consent'.
     * Đó là cách BR-UC.USER.01-05 được giữ: ô tick Điều khoản nằm ở màn đăng
     * ký, nên một cú bấm Google không đi qua ô tick ấy chỉ được phép ĐĂNG NHẬP
     * vào tài khoản có sẵn, không được phép tạo tài khoản mới.
     *
     * @return array{ok:bool, error?:string, code?:string, id?:string, created?:bool}
     */
    public static function findOrCreateGoogle(
        string $sub,
        ?string $email,
        ?string $name,
        bool $emailVerified,
        bool $allowCreate = true
    ): array {
        $existing = Database::fetchOne('SELECT id FROM users WHERE google_id = :g', ['g' => $sub]);

        if ($existing !== null) {
            /* KHOÁ VÀ XOÁ MỀM PHẢI CHẶN CẢ ĐƯỜNG GOOGLE.

               attempt() ở trên đã chặn đường mật khẩu, nhưng đây là một cửa
               khác vào cùng một tài khoản — và nó không đi qua attempt() một
               dòng nào. Bỏ sót chỗ này thì nút khoá chỉ khoá được nửa số
               khách, đúng nửa mà người bấm nút không nghĩ tới. */
            $chan = self::chanNeuKhongVaoDuoc($existing['id']);

            if ($chan !== null) {
                return $chan;
            }

            return ['ok' => true, 'id' => $existing['id'], 'created' => false];
        }

        $email = $email !== null ? strtolower(trim($email)) : null;

        /*
         * ─────────────────────────────────────────────────────────────────
         * AF-03 — EMAIL ĐÃ THUỘC MỘT TÀI KHOẢN KHÁC: DỪNG HẲN.
         *
         * Không phân biệt email đã xác minh hay chưa, vì kết luận giống nhau ở
         * cả hai: tài khoản này không được tạo, và Google không được nối vào
         * tài khoản kia. Bản trước chia hai ngả — xác minh thì nối tự động,
         * chưa xác minh thì bỏ email đi rồi vẫn tạo tài khoản — và cả hai ngả
         * đều lệch đặc tả.
         *
         * "Bỏ email đi rồi vẫn tạo" đặc biệt tệ: khách bấm Google, thấy đăng
         * ký thành công, và có một tài khoản thứ hai không email, không số
         * điện thoại, không dính gì tới tài khoản cũ của họ. Lần sau bấm
         * Google lại vào đúng tài khoản rỗng ấy và không hiểu đơn hàng cũ đi
         * đâu mất.
         *
         * Câu báo dưới đây là nguyên văn AF-03 bước 4.
         * ─────────────────────────────────────────────────────────────────
         */
        if ($email !== null && $email !== '') {
            $byEmail = Database::fetchOne(
                'SELECT id, google_id FROM users WHERE email = :e',
                ['e' => $email]
            );

            if ($byEmail !== null) {
                return [
                    'ok'    => false,
                    'code'  => 'email_taken',
                    'error' => 'Email này đã được đăng ký. Vui lòng đăng nhập bằng '
                             . 'Số điện thoại/Mật khẩu để liên kết tài khoản Google.',
                ];
            }
        }

        /* Chưa khớp gì cả -> AF-02, tạo mới. Nhưng chỉ khi lượt này có mang
           theo cú tick Điều khoản — xem $allowCreate ở khối chú thích đầu hàm
           và AuthController::googleStart(). */
        if (!$allowCreate) {
            return [
                'ok'    => false,
                'code'  => 'need_consent',
                'error' => 'Số điện thoại hoặc Email này chưa có tài khoản. Vui lòng đồng ý '
                         . 'với Điều khoản và Chính sách rồi bấm "Tiếp tục với Google" để đăng ký.',
            ];
        }

        $userId = uuid();

        try {
            $termsVersion = (string) config('auth.consent.version', '');

            Database::transaction(static function () use ($userId, $sub, $email, $name, $emailVerified, $termsVersion): void {
                /*
                 * TÀI KHOẢN TẠO QUA GOOGLE CŨNG GHI VẾT ĐỒNG Ý — BR-UC.USER.01-05.
                 *
                 * Và nay là một cú tick THẬT, không phải đồng ý ngầm: nút "Tiếp
                 * tục với Google" ở màn đăng ký là nút submit của chính form
                 * đăng ký, nên nó mang theo ô tick Điều khoản/Chính sách của
                 * form ấy. Không tick thì $allowCreate về false và dòng này
                 * không bao giờ chạy — xem AuthController::googleStart().
                 */
                Database::execute(
                    'INSERT INTO users (id, email, google_id, password_hash, email_verified,
                                        terms_accepted_at, terms_version)
                     VALUES (:id, :email, :google, :hash, :verified, :accepted_at, :terms_version)',
                    [
                        'id'       => $userId,
                        'email'    => ($email !== null && $email !== '') ? $email : null,
                        'google'   => $sub,
                        'hash'     => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                        'verified' => $emailVerified ? 1 : 0,
                        'accepted_at'   => $termsVersion !== '' ? date('Y-m-d H:i:s') : null,
                        'terms_version' => $termsVersion !== '' ? $termsVersion : null,
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

            return ['ok' => false, 'error' => 'Có lỗi xảy ra. Vui lòng thử lại sau.'];
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

        /* LOẠI TÀI KHOẢN ĐÃ XOÁ MỀM NGAY TẠI ĐÂY, không phải ở nơi gọi.

           Hàm này là cửa duy nhất mà mọi đường "tìm người dùng theo thứ họ gõ"
           đi qua: đăng nhập, quên mật khẩu, tra cứu ở quầy. Lọc ở đây thì thêm
           một đường mới sau này cũng tự được lọc; lọc ở từng nơi gọi thì chỉ
           cần một chỗ quên là tài khoản đã xoá vẫn đăng nhập được — mà không
           có gì báo cho ai biết.

           Cột `deleted_at` chỉ có từ migration 2026-08-26. Máy chưa chạy file
           đó thì nhắc tới nó là lỗi 1054 và KHÔNG AI ĐĂNG NHẬP ĐƯỢC NỮA — nên
           phải hỏi trước. Xem Database::columnExists(). */
        $locXoa = Database::columnExists('users', 'deleted_at')
            ? ' AND u.deleted_at IS NULL'
            : '';

        if (looksLikePhone($login)) {
            $phone = normalizePhone($login);

            if ($phone === null) {
                return null;
            }

            return Database::fetchOne(
                'SELECT u.*, p.full_name, p.phone
                   FROM profiles p
                   JOIN users u ON u.id = p.id
                  WHERE p.phone = :phone' . $locXoa,
                ['phone' => $phone]
            );
        }

        return Database::fetchOne(
            'SELECT u.*, p.full_name, p.phone
               FROM users u
               LEFT JOIN profiles p ON p.id = u.id
              WHERE u.email = :email' . $locXoa,
            ['email' => strtolower($login)]
        );
    }

    /**
     * Tài khoản này có bị chặn không (khoá hoặc đã xoá mềm)?
     *
     * @return array|null Mảng lỗi để trả thẳng ra, hoặc null nếu vào được.
     *
     * MỘT CHỖ ĐỊNH NGHĨA "KHÔNG VÀO ĐƯỢC", dùng cho mọi cửa vào. Chép điều
     * kiện này ra từng nhánh thì mỗi lần thêm một trạng thái mới lại phải nhớ
     * hết các nhánh — và cái quên sẽ là một cánh cửa mở.
     *
     * Câu chữ trả về giống hệt nhau cho cả hai ca: khách bị xoá không cần biết
     * mình bị xoá hay bị khoá, họ chỉ cần biết phải gọi cho cửa hàng.
     */
    private static function chanNeuKhongVaoDuoc(string $userId): ?array
    {
        return self::coTheDangNhap($userId)
            ? null
            : ['ok' => false, 'error' => 'Tài khoản đã bị khoá. Vui lòng liên hệ cửa hàng.'];
    }

    /**
     * Tài khoản còn đăng nhập được không (chưa khoá, chưa xoá mềm)?
     *
     * Công khai vì RememberModel cũng phải hỏi: cookie "ghi nhớ đăng nhập" là
     * một cửa vào thứ ba, không đi qua attempt() lẫn findOrCreateGoogle().
     *
     * Trả TRUE khi không tra được: cột `status` chỉ có từ migration
     * 2026-08-26, và trên máy chưa chạy file đó thì không có trạng thái nào để
     * kiểm. Mở cửa trong ca đó là đúng — đóng lại nghĩa là một file nâng cấp
     * chưa chạy sẽ khoá toàn bộ khách hàng ra khỏi tài khoản của họ.
     */
    public static function coTheDangNhap(string $userId): bool
    {
        // Hỏi trước khi nhắc tới cột: thiếu nó thì câu SELECT đổ lỗi 1054 ngay
        // trên đường đăng nhập, và cả site đóng cửa chứ không riêng một tính năng.
        if (!Database::columnExists('users', 'status')) {
            return true;
        }

        $row = Database::fetchOne(
            'SELECT status, deleted_at FROM users WHERE id = :id',
            ['id' => $userId]
        );

        if ($row === null) {
            return true;
        }

        return $row['deleted_at'] === null && ($row['status'] ?? 'active') !== 'locked';
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
        /* KHOÁ TẠM THỜI SAU 5 LẦN SAI — SNFR-06.

           Kiểm TRƯỚC password_verify, khác hẳn nhánh kiểm status = 'locked' ở
           dưới. Hai cái khoá này phục vụ hai việc khác nhau:

             · status = 'locked' là quyết định của cửa hàng về một con người,
               nên chỉ người chứng minh được mình là chủ (gõ đúng mật khẩu)
               mới đáng được nghe. Kiểm sau.
             · khoá tạm thời là chốt chặn máy dò. Kiểm sau password_verify thì
               nó không chặn gì cả — mỗi lượt dò vẫn được băm và so khớp đầy
               đủ, tức là vẫn dò được, chỉ tốn thêm một câu báo lỗi.

           Chỗ này KHÔNG rò rỉ việc tài khoản có tồn tại hay không, vì
           LoginAttemptModel đếm theo chuỗi định danh chứ không theo hàng trong
           `users`: gõ sai 6 lần vào một email không tồn tại cũng nhận đúng câu
           trả lời này. Lý do đầy đủ ghi ở đầu app/models/LoginAttemptModel.php. */
        $conKhoa = LoginAttemptModel::conKhoa($login);

        if ($conKhoa > 0) {
            $phut = (int) ceil($conKhoa / 60);

            return [
                'ok'     => false,
                // Cờ riêng để nơi gọi phân biệt "bị khoá tạm" với "sai thông
                // tin". Cổng quản trị cố tình gộp mọi ca hỏng vào một câu để
                // không rò rỉ danh sách nhân viên — nhưng câu khoá tạm thì
                // KHÔNG rò rỉ gì (bộ đếm chạy theo chuỗi định danh, kể cả
                // định danh không có tài khoản), nên nó đáng được nói thẳng.
                'locked' => true,
                'error'  => "Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau {$phut} phút.",
            ];
        }

        $user = static::findByLogin($login);

        /* HAI CA HỎNG, MỘT CHỖ GHI NHẬN.

           Ghi nhận ở hai chỗ riêng thì dễ sót một chỗ — và lần đầu viết hàm
           này đã sót thật: phép dọn thưa thớt chỉ nằm ở nhánh "không tìm thấy
           tài khoản", nên trên site chạy ổn định (nơi phần lớn lượt hỏng là
           khách gõ nhầm mật khẩu của chính mình) bảng đếm gần như không bao
           giờ được dọn. Hosting không có cron nên đây là cơ chế dọn DUY NHẤT. */
        $hong = $user === null || !password_verify($password, $user['password_hash']);

        if ($user === null) {
            // Vẫn băm một chuỗi giả khi không tìm thấy tài khoản, để thời gian
            // phản hồi của hai nhánh xấp xỉ nhau. Trả về ngay lập tức sẽ nhanh
            // hơn hẳn nhánh có password_verify, và chênh lệch đó đủ để dò email.
            password_verify($password, '$2y$12$' . str_repeat('.', 53));
        }

        if ($hong) {
            LoginAttemptModel::ghiNhanHong($login);

            // Dọn thưa thớt, 1/50 lượt hỏng — cùng nếp với
            // RememberModel::purgeExpired().
            if (random_int(1, 50) === 1) {
                LoginAttemptModel::donCu();
            }

            return ['ok' => false, 'error' => 'Thông tin đăng nhập không đúng.'];
        }

        /* TÀI KHOẢN BỊ KHOÁ — KIỂM SAU password_verify, KHÔNG PHẢI TRƯỚC.

           Đặt trước thì ô đăng nhập thành công cụ dò: gõ một email bất kỳ, thấy
           câu "đã bị khoá" là biết địa chỉ đó có tài khoản ở đây và đang bị
           khoá — thông tin không nên cho người chưa chứng minh được mình là
           chủ. Đặt sau thì chỉ người gõ ĐÚNG mật khẩu mới đọc được câu đó.

           Câu chữ CỐ TÌNH không nói lý do khoá: lý do là ghi chú nội bộ, và
           người quyết định nên nói gì với khách là người nhấc điện thoại chứ
           không phải màn hình đăng nhập. */
        if (($user['status'] ?? 'active') === 'locked') {
            return ['ok' => false, 'error' => 'Tài khoản đã bị khoá. Vui lòng liên hệ cửa hàng.'];
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

        // Vào được thì xoá bộ đếm: SNFR-06 nói "5 lần sai LIÊN TIẾP", nên một
        // lần đúng cắt đứt chuỗi đó.
        LoginAttemptModel::xoa($login);

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

        /* DÙNG CHUNG passwordProblem() VỚI LUỒNG ĐĂNG KÝ.

           Trước đây chỗ này chỉ đo độ dài 8 ký tự, nên đăng ký thì bị bắt đủ
           bốn điều kiện của SNFR-09 còn đổi mật khẩu thì lọt: gõ "12345678" là
           qua. Một chính sách mật khẩu có một cửa hậu thì không phải chính
           sách. Mọi đường đặt mật khẩu phải đi qua đúng một hàm kiểm. */
        if (($loi = passwordProblem($new)) !== null) {
            return ['ok' => false, 'error' => $loi];
        }

        Database::execute(
            'UPDATE users SET password_hash = :hash WHERE id = :id',
            ['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => $userId]
        );

        return ['ok' => true];
    }

    /**
     * Sinh mật khẩu ngẫu nhiên ĐẠT ĐỦ bốn điều kiện của SNFR-09.
     *
     * random_int() lấy từ nguồn ngẫu nhiên của hệ điều hành và phân bố đều.
     * KHÔNG dùng rand()/mt_rand() (sinh dãy đoán được nếu biết seed), cũng
     * không dùng `random_bytes() % 62` (lệch về các ký tự đầu bảng chữ).
     *
     * ─────────────────────────────────────────────────────────────────────
     * VÌ SAO NAY CÓ KÝ TỰ ĐẶC BIỆT, DÙ TRƯỚC ĐÂY CỐ Ý BỎ
     *
     * Bản cũ chỉ dùng chữ và số, với lý do chính đáng: mật khẩu tạm hay được
     * đọc qua điện thoại hoặc chép tay cho đồng nghiệp, mà ký tự đặc biệt thì
     * dễ nghe nhầm. Về mặt độ mạnh thì 20 ký tự chữ-số đã là ~119 bit, thừa.
     *
     * Nhưng SNFR-09 áp cho TÀI KHOẢN NỘI BỘ: "tối thiểu 8 ký tự, bao gồm ít
     * nhất 01 chữ hoa, 01 chữ thường, 01 chữ số và 01 ký tự đặc biệt". Từ khi
     * createStaff() kiểm bằng passwordProblem(), một mật khẩu chỉ chữ-số sinh
     * ra từ đây sẽ bị chính hệ thống từ chối ngay ở form thêm nhân viên — hàm
     * sinh và hàm kiểm không được phép nói hai thứ khác nhau.
     *
     * Bộ ký tự đặc biệt CỐ TÌNH HẸP (!@#$%*-_): bỏ những ký tự đọc lên dễ
     * nhầm hoặc gõ khác nhau giữa các bố cục bàn phím — dấu nháy, gạch chéo,
     * ngoặc, dấu chấm câu. Giữ lại phần lớn lợi ích của bản cũ.
     *
     * BẢNG CHỮ CÁI CŨNG BỎ KÝ TỰ NHÌN GIỐNG NHAU (0/O, 1/l/I): chép tay sai
     * một ký tự là gọi lại hỏi, và đó là chi phí thật ở quầy.
     * ─────────────────────────────────────────────────────────────────────
     *
     * GHÉP MỖI NHÓM MỘT KÝ TỰ RỒI XÁO, chứ không sinh ngẫu nhiên rồi cầu may:
     * rút 12 ký tự từ một rổ trộn sẵn thì vẫn có xác suất khác 0 là không có
     * chữ số nào — hiếm, nhưng "hiếm" nghĩa là một ngày nào đó form thêm nhân
     * viên báo lỗi không ai hiểu vì sao.
     *
     * ĐÂY LÀ ĐỊNH NGHĨA DUY NHẤT của "mật khẩu do hệ thống sinh".
     * database/make-admin.php từng có bản sao riêng; nay nó gọi hàm này, để
     * độ dài và bộ ký tự không thể lệch nhau giữa hai đường cấp mật khẩu.
     */
    public static function randomPassword(int $length = 20): string
    {
        $hoa      = 'ABCDEFGHJKLMNPQRSTUVWXYZ';   // bỏ I, O
        $thuong   = 'abcdefghijkmnpqrstuvwxyz';   // bỏ l, o
        $so       = '23456789';                   // bỏ 0, 1
        $dacBiet  = '!@#$%*-_';

        // Ngắn hơn 8 thì không thể vừa đủ bốn nhóm vừa đúng SNFR-09; nâng lên
        // thay vì trả về một chuỗi mà hàm kiểm sẽ từ chối.
        $length = max(8, $length);

        $kho = $hoa . $thuong . $so . $dacBiet;
        $max = strlen($kho) - 1;

        // Bốn ký tự bắt buộc trước, phần còn lại rút tự do.
        $kyTu = [
            $hoa[random_int(0, strlen($hoa) - 1)],
            $thuong[random_int(0, strlen($thuong) - 1)],
            $so[random_int(0, strlen($so) - 1)],
            $dacBiet[random_int(0, strlen($dacBiet) - 1)],
        ];

        for ($i = count($kyTu); $i < $length; $i++) {
            $kyTu[] = $kho[random_int(0, $max)];
        }

        /* Xáo Fisher-Yates bằng random_int, KHÔNG dùng shuffle(): shuffle()
           chạy trên bộ sinh giả ngẫu nhiên của PHP, nên thứ tự bốn ký tự bắt
           buộc — vốn luôn nằm ở bốn vị trí đầu trước khi xáo — đoán được nếu
           biết seed. Ở đây thì đó là thông tin về chính mật khẩu. */
        for ($i = count($kyTu) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$kyTu[$i], $kyTu[$j]] = [$kyTu[$j], $kyTu[$i]];
        }

        return implode('', $kyTu);
    }

    /**
     * Mọi tài khoản CÓ QUYỀN VÀO KHU QUẢN TRỊ, kèm vai trò gộp thành chuỗi.
     *
     * JOIN chứ không LEFT JOIN trên user_roles: khách hàng thường không thuộc
     * danh sách này. Họ có đường riêng để lấy lại mật khẩu (/quen-mat-khau,
     * và PasswordResetAdminController cho ca không nhận được mã).
     *
     * LEFT JOIN sang profiles vì hồ sơ CÓ THỂ thiếu — tài khoản dựng tay bằng
     * SQL trên phpMyAdmin hay quên mất bảng đó. Thiếu hồ sơ thì cột tên rỗng
     * chứ cả dòng không được biến mất, nếu không thì đúng những tài khoản dựng
     * ẩu lại vô hình ở chính trang dùng để soát chúng.
     *
     * @return array<int, array{id:string, email:?string, full_name:?string,
     *                          roles:string, last_login_at:?string}>
     */
    public static function staffAccounts(): array
    {
        // STAFF_ROLES là hằng gõ sẵn trong file này, nhưng vẫn đi qua tham số
        // ràng buộc: đổi hằng đó thành thứ đọc từ cấu hình sau này thì câu SQL
        // ở đây không trở thành lỗ chèn mã.
        $keys   = [];
        $params = [];

        foreach (self::STAFF_ROLES as $i => $role) {
            $keys[] = ':r' . $i;
            $params['r' . $i] = $role;
        }

        /* Cột khoá đọc qua columnExists: chúng ra đời cùng module Khách hàng
           (migration 2026-08-26), sau trang này. Máy chưa nâng cấp thì bảng vẫn
           dựng được và mọi dòng coi như đang hoạt động — thiếu một cột nhãn còn
           hơn cả trang đổ lỗi 1054. */
        $coKhoa = Database::columnExists('users', 'status');
        $cotKhoa = $coKhoa
            ? "u.status, u.locked_at, u.locked_reason,"
            : "'active' AS status, NULL AS locked_at, NULL AS locked_reason,";
        $gomKhoa = $coKhoa ? ', u.status, u.locked_at, u.locked_reason' : '';

        return Database::fetchAll(
            'SELECT u.id, u.email, u.last_login_at, ' . $cotKhoa . ' p.full_name,
                    GROUP_CONCAT(r.role ORDER BY r.role SEPARATOR \', \') AS roles
               FROM users u
               JOIN user_roles r ON r.user_id = u.id AND r.role IN (' . implode(', ', $keys) . ')
               LEFT JOIN profiles p ON p.id = u.id
              GROUP BY u.id, u.email, u.last_login_at, p.full_name' . $gomKhoa . '
              ORDER BY p.full_name IS NULL, p.full_name, u.email',
            $params
        );
    }

    /**
     * Đặt lại mật khẩu của MỘT tài khoản nội bộ — KHÔNG cần biết mật khẩu cũ.
     *
     * Khác hẳn changePassword() ngay trên: hàm kia là người ta tự đổi mật khẩu
     * của chính mình và phải chứng minh bằng mật khẩu hiện tại. Hàm này là
     * quản trị viên cấp lại cho NGƯỜI KHÁC, nên không có mật khẩu cũ nào để
     * hỏi — chính vì thế nơi gọi phải tự kiểm quyền trước.
     *
     * Mật khẩu mới do hệ thống sinh, trả về ĐÚNG MỘT LẦN cho nơi gọi in ra.
     * Không nhận mật khẩu do người dùng gõ: quản trị viên tự đặt hộ một chuỗi
     * là chuỗi đó đi qua bàn phím, ô nhập và có khi cả một tin nhắn — mà
     * người ta hay đặt lại cùng một mật khẩu cho mọi nhân viên.
     *
     * @return array{ok:bool, error?:string, password?:string}
     */
    public static function resetPasswordFor(string $userId): array
    {
        if (static::find($userId) === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy tài khoản.'];
        }

        if (!self::isStaff($userId)) {
            // Chốt thứ hai sau chốt ở controller. Trang này chỉ để cấp lại
            // mật khẩu NỘI BỘ; một id khách hàng gửi tay lên không được biến
            // nó thành cách chiếm tài khoản khách bỏ qua bước gọi xác minh
            // mà PasswordResetAdminController bắt buộc.
            return ['ok' => false, 'error' => 'Tài khoản này không phải tài khoản nội bộ.'];
        }

        $password = self::randomPassword();

        Database::execute(
            'UPDATE users SET password_hash = :hash WHERE id = :id',
            ['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]
        );

        /* Đá mọi thiết bị đang "ghi nhớ đăng nhập" của người đó ra ngoài —
           cùng lý do với changePassword(). Đặt lại mật khẩu hộ ai đó thường
           xảy ra vì họ mất máy hoặc nghi bị lộ; để cookie cũ vẫn vào được thì
           việc đặt lại gần như vô nghĩa.

           KHÔNG giết được phiên đang mở của họ: phiên nằm trong file session
           của PHP, khoá theo session id chứ không theo user id. Nếu cần chắc
           chắn thì bảo người đó bấm Đăng xuất, hoặc chờ phiên hết hạn. */
        RememberModel::forgetAllFor($userId);

        return ['ok' => true, 'password' => $password];
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
     * Địa chỉ giao hàng của khách, ĐÚNG HÌNH DẠNG mà trang thanh toán chờ.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VÌ SAO LÀ MỘT HÀM RIÊNG CHỨ KHÔNG ĐỌC THẲNG $profile
     *
     * Chỗ gọi là OrderController::checkout(), và trước 2026-09-12 nó nhận
     * AddressModel::defaultFor() — một dòng của bảng `addresses` với các khoá
     * recipient_name / phone / line1 / province_* / ward_*. Cả
     * app/views/order/checkout.php đọc theo đúng bộ tên ấy, ở chừng chục chỗ.
     *
     * Hàm này dựng lại đúng bộ tên đó từ hồ sơ, nên bảng đi mà trang thanh
     * toán không phải sửa một dòng nào. Đổi tên khoá ở đây là phải sửa cả
     * checkout.php — đừng làm nếu không có lý do.
     *
     * recipient_name và phone lấy từ chính hồ sơ: địa chỉ nay thuộc về tài
     * khoản, nên người nhận mặc nhiên là chủ tài khoản. Khách gửi cho người
     * khác vẫn sửa được — ô "Người nhận" ở trang thanh toán còn nguyên, và
     * $fillCo() ở đó ưu tiên thứ khách vừa gõ.
     *
     * TRẢ null KHI CHƯA CÓ ĐỊA CHỈ, không phải một mảng rỗng: checkout.php
     * kiểm bằng `$address === null` để biết có gì điền sẵn hay không, và một
     * mảng toàn chuỗi rỗng sẽ lọt qua phép kiểm ấy rồi xoá trắng các ô mà
     * khách vừa gõ hỏng.
     *
     * "Có địa chỉ" đo bằng CHI TIẾT (`address`) chứ không bằng tỉnh/phường:
     * chọn mỗi tỉnh rồi bỏ dở thì chưa giao được tới đâu cả.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return array{recipient_name:string, phone:string, line1:string,
     *               province_code:?int, province_name:string,
     *               ward_code:?int, ward_name:string}|null
     */
    public static function diaChi(string $userId): ?array
    {
        $p = self::profile($userId);

        if ($p === null || trim((string) ($p['address'] ?? '')) === '') {
            return null;
        }

        return [
            'recipient_name' => (string) ($p['full_name'] ?? ''),
            'phone'          => (string) ($p['phone'] ?? ''),
            'line1'          => trim((string) $p['address']),
            'province_code'  => $p['province_code'] !== null ? (int) $p['province_code'] : null,
            'province_name'  => (string) ($p['province_name'] ?? ''),
            'ward_code'      => $p['ward_code'] !== null ? (int) $p['ward_code'] : null,
            'ward_name'      => (string) ($p['ward_name'] ?? ''),
        ];
    }

    // ========================================================================
    // "HỒ SƠ ĐÃ HOÀN THIỆN" — Q72, chốt 04/09/2026
    // ========================================================================

    /**
     * Đã có kênh xác thực số điện thoại nào chạy được chưa.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * ĐÂY LÀ MỘT LỐI THOÁT CÓ CHỦ Ý, KHÔNG PHẢI MỘT CHỖ CHƯA LÀM XONG
     *
     * Q72 định nghĩa hồ sơ hoàn thiện = HỌ TÊN + SỐ ĐIỆN THOẠI ĐÃ XÁC THỰC.
     * Vế thứ hai đòi Zalo OTP (mục 3.2.1), mà luồng ấy chưa nối — nên hôm nay
     * KHÔNG bản ghi nào có `profiles.phone_verified_at`.
     *
     * Áp Q72 nguyên văn lúc này nghĩa là mọi khách đều "chưa hoàn thiện" mãi
     * mãi: đúng từng chữ của quyết định, và làm hỏng website. Một quy tắc mà
     * không ai có đường thoả mãn thì không phải quy tắc, nó là một cái bẫy.
     *
     * Nên chừng nào chưa có kênh xác thực nào, một số điện thoại HỢP LỆ được
     * tính là đủ. Khi Zalo OTP lên, đổi hằng này thành true là luật Q72 có
     * hiệu lực đầy đủ — không phải sửa chỗ nào khác.
     *
     * ⚠ Đây là lựa chọn của nhóm phát triển, ghi lại để BA xác nhận.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public const CO_KENH_XAC_THUC = false;

    /**
     * Hồ sơ đã hoàn thiện chưa — Q72.
     *
     * Điều kiện gồm ĐÚNG HAI thứ: họ tên và số điện thoại đã xác thực. Email,
     * ngày sinh và địa chỉ mặc định KHÔNG nằm trong điều kiện — Q72 nói rõ, và
     * đó là chỗ dễ tự tiện thêm vào nhất ("có địa chỉ nữa thì mới thật là đầy
     * đủ chứ"). Thêm một điều kiện ở đây là giữ khách lại một màn hình nữa cho
     * một thứ họ chưa cần.
     *
     * @param array|null $profile kết quả profile(), truyền vào khi nơi gọi đã đọc
     */
    public static function hoSoDayDu(string $userId, ?array $profile = null): bool
    {
        $profile ??= self::profile($userId);

        if ($profile === null) {
            return false;
        }

        if (utf8Length(trim((string) ($profile['full_name'] ?? ''))) < 2) {
            return false;
        }

        $phone = trim((string) ($profile['phone'] ?? ''));

        if ($phone === '' || normalizePhone($phone) === null) {
            return false;
        }

        // Chưa có kênh xác thực nào -> số hợp lệ là đủ. Xem CO_KENH_XAC_THUC.
        if (!self::CO_KENH_XAC_THUC) {
            return true;
        }

        return trim((string) ($profile['phone_verified_at'] ?? '')) !== '';
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
        /* 'address' và bốn cột tỉnh/phường: từ 2026-09-12 địa chỉ nằm THẲNG
           trong hồ sơ, không còn bảng `addresses` nào giữ bản gốc rồi đồng bộ
           ngược về đây. Xem migration 2026-09-12-dia-chi-vao-ho-so.sql. */
        $allowed = ['full_name', 'phone', 'address', 'province_code', 'province_name',
                    'ward_code', 'ward_name', 'date_of_birth', 'gender', 'avatar_path'];
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

        /* ─────────────────────────────────────────────────────────────────
           BỐN CỘT ĐỊA CHỈ: RỖNG PHẢI THÀNH NULL, MÃ PHẢI LÀ SỐ

           Ô trống gửi lên là chuỗi rỗng. Nhét '' vào province_code (SMALLINT)
           thì MySQL ở chế độ STRICT từ chối cả câu UPDATE — tức khách bỏ trống
           địa chỉ là không lưu nổi cả họ tên. Ở chế độ lỏng thì tệ hơn: nó
           lặng lẽ thành 0, một mã tỉnh không tồn tại, và address-picker.js
           mở form ra không chọn được mục nào.

           Mã đi kèm TÊN: có mã mà không tên là thứ không hiển thị được (ứng
           dụng luôn in theo tên), nên mất tên thì bỏ luôn mã. Chiều ngược lại
           thì được — JavaScript tắt hoặc API chết thì khách gõ tay, có tên mà
           không có mã, và đó là trạng thái hợp lệ.
           ───────────────────────────────────────────────────────────────── */
        foreach (['address', 'province_name', 'ward_name'] as $cot) {
            if (array_key_exists($cot, $patch)) {
                $patch[$cot] = trim((string) $patch[$cot]) !== ''
                    ? trim((string) $patch[$cot]) : null;
            }
        }

        foreach (['province' => 'province_code', 'ward' => 'ward_code'] as $vung => $cot) {
            if (!array_key_exists($cot, $patch)) {
                continue;
            }

            $ma = trim((string) $patch[$cot]);
            $co = ($patch[$vung . '_name'] ?? null) !== null;

            $patch[$cot] = ($ma !== '' && ctype_digit($ma) && $co) ? (int) $ma : null;
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

    /**
     * Địa chỉ email này có đang thuộc về một tài khoản NỘI BỘ không?
     *
     * Hỏi bằng email chứ không bằng id vì có nơi cần biết ĐIỀU NÀY TRƯỚC KHI
     * chạm vào tài khoản: luồng "Tiếp tục với Google" khớp người theo email,
     * và thao tác khớp ấy GHI — nó gắn google_id vào dòng users tìm được. Đợi
     * tới lúc có id trong tay mới kiểm thì tài khoản nội bộ đã bị gắn với một
     * tài khoản Google, dù cuối cùng ta vẫn từ chối cho vào.
     *
     * Không tìm thấy email, hoặc email rỗng/null, thì trả false: "không phải
     * tài khoản nội bộ" là câu trả lời đúng cho một địa chỉ không thuộc về ai.
     */
    public static function isStaffEmail(?string $email): bool
    {
        $email = strtolower(trim((string) $email));

        if ($email === '') {
            return false;
        }

        $id = Database::fetchValue(
            'SELECT id FROM users WHERE email = :e',
            ['e' => $email]
        );

        return $id !== null && $id !== false && self::isStaff((string) $id);
    }

    // ========================================================================
    // HỒ SƠ KHÚC XẠ
    // ========================================================================

    /**
     * Thông số đo mắt gần nhất của khách, kèm tên cơ sở đã đo.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * ĐỌC TỪ SỔ CHỈ-THÊM, KHÔNG CÒN BẢNG TÓM TẮT — SRS v2.1.0, FR-DM-01
     *
     * Trước đây số đo nằm ở HAI nơi: bảng `prescriptions` giữ đúng một dòng mỗi
     * khách (bản tóm tắt phía khách đọc), và bảng `customer_prescriptions` giữ
     * toàn bộ lịch sử theo cơ chế chỉ-thêm. Một hàm mirrorLatest() chép từ sổ
     * sang bảng tóm tắt sau mỗi lần ghi.
     *
     * Hai nơi lưu cùng một sự thật thì sớm muộn lệch nhau, và khi lệch thì
     * không có cách nào biết bên nào đúng — trong khi đây là con số đem đi mài
     * tròng. Chủ đầu tư đã bỏ bảng tóm tắt; sổ chỉ-thêm là nguồn duy nhất.
     *
     * LẤY BẢN MỚI NHẤT theo ngày đo, rồi tới thời điểm ghi. Hai mức sắp xếp
     * chứ không một: hai bản cùng ngày đo (đo lại trong ngày, hoặc một lần
     * đính chính) thì `created_at` mới là thứ phân định.
     *
     * LEFT JOIN chứ không JOIN: `store_id` được phép NULL (khách tự khai, toa
     * từ nơi khác, hoặc cơ sở cũ đã đóng cửa và khoá ngoại đã SET NULL). JOIN
     * thường sẽ làm cả bản ghi biến mất trong đúng những trường hợp đó.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public static function prescription(string $userId): ?array
    {
        if (!PrescriptionRecordModel::available()) {
            return null;
        }

        /* LIỆT KÊ CỘT, KHÔNG DÙNG `c.*`.
 
           Sổ chỉ-thêm có hai cột NỘI BỘ mà khách không được đọc: `tech_note`
           (nhận định chuyên môn — schema ghi rõ "không hiện cho khách") và
           `ly_do` (lý do đính chính). `c.*` đổ cả hai vào biến mà view khách
           dùng; hôm nay chưa in ra nên chưa lộ, nhưng một vòng lặp in bảng
           thêm vào sau là lộ ngay, và người thêm nó sẽ không biết.
 
           `note` xuất ra dưới tên `recommendation` để view khách không phải
           đổi: hai cột này cùng một thứ — ô ghi chú KHÁCH ĐỌC ĐƯỢC — chỉ khác
           tên giữa bảng tóm tắt cũ và sổ. */
        return Database::fetchOne(
            'SELECT c.id, c.source, c.measured_at, c.store_id,
                    c.od_sph, c.od_cyl, c.od_axis, c.od_va,
                    c.os_sph, c.os_cyl, c.os_axis, c.os_va,
                    c.pd, c.pd_od, c.pd_os, c.od_add, c.os_add,
                    c.note AS recommendation, c.note,
                    c.created_at, c.updated_at,
                    s.name AS store_name
               FROM customer_prescriptions c
               LEFT JOIN stores s ON s.id = c.store_id
              WHERE c.user_id = :id
              ORDER BY c.measured_at DESC, c.created_at DESC
              LIMIT 1',
            ['id' => $userId]
        );
    }

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * HIỆU LỰC 12 THÁNG CỦA HỒ SƠ ĐO MẮT ĐÃ GỠ — SRS v2.1.0, H09
     *
     * Trước đây có hằng số PRESCRIPTION_VALID_MONTHS = 12 và hàm
     * prescriptionIsValid(), dùng để gắn nhãn "Còn hiệu lực" / "Nên đo lại"
     * lên hồ sơ đo mắt mới nhất.
     *
     * Chủ đầu tư đã bỏ khái niệm này: hệ thống KHÔNG kết luận một số đo còn
     * dùng được hay không. Việc đó thuộc về người đo, không thuộc về một phép
     * trừ ngày.
     *
     * NGÀY ĐO VẪN PHẢI HIỆN Ở MỌI NƠI hiển thị hồ sơ — nó là dữ kiện để cả
     * khách lẫn nhân viên tự đánh giá. Chỉ có LỜI KẾT LUẬN là bỏ.
     *
     * Đừng dựng lại một phép kiểm tương đương ở tầng view.
     * ─────────────────────────────────────────────────────────────────────────
     */

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * MỤC "KÍNH ĐANG ĐEO" ĐÃ GỠ — SRS v2.1.0, A20
     *
     * Trước đây hồ sơ đo mắt có thêm năm trường khách tự khai về cặp kính họ
     * ĐANG dùng: kiểu tròng, tính chất tròng, loại gọng, đã dùng bao lâu, và
     * một câu ghi chú. Kèm theo là hằng WEAR_NONE và năm hàm wear*().
     *
     * Chủ đầu tư đã bỏ toàn bộ: đây là dữ liệu khách tự khai, không ai đối
     * chiếu, và trên thực tế gần như không được điền. Năm cột `wear_*` trên
     * bảng `prescriptions` gỡ bằng migration đợt 1.
     *
     * Ba danh sách taxonomy.wear_* trong config/taxonomy.php cũng gỡ theo.
     * ─────────────────────────────────────────────────────────────────────────
     */

    /**
     * Thị lực có đúng dạng phân số hợp lệ không — "9/10", "10/10", "6/10"…
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VÌ SAO CHẶN Ở TRÊN 10
     *
     * Thang thập phân dùng ở Việt Nam đọc "mấy phần mười": 10/10 là thị lực
     * tối đa. Một con số lớn hơn 10 ở vế nào cũng là gõ nhầm — thường là thừa
     * một chữ số ("100/10") hoặc gõ theo thang Snellen của Mỹ ("20/20").
     *
     * ĐÂY LÀ DỮ LIỆU Y TẾ, nên gõ nhầm không được phép lọt: nhân viên đọc con
     * số này để tư vấn, và "100/10" nằm im trong hồ sơ thì không ai đoán được
     * ý người nhập là gì.
     *
     * SỐ NGUYÊN, KHÔNG NHẬN THẬP PHÂN. Thang này vốn đi theo bước một phần
     * mười; "8.5/10" không phải cách viết của nó. Nếu cửa hàng cần thì nới
     * biểu thức dưới đây, KHÔNG phải bỏ hẳn phép kiểm.
     *
     * Chuỗi RỖNG là hợp lệ: khách chưa đo thì để trống, không phải điền bừa.
     *
     * CHƯA PHỦ được các ký hiệu thị lực rất thấp mà ngành mắt vẫn dùng — ĐNT
     * (đếm ngón tay), BBT (bóng bàn tay), ST(+). Chúng không phải phân số nên
     * không lọt qua đây. Ngày cửa hàng cần ghi chúng thì thêm một danh sách
     * giá trị cho phép bên cạnh biểu thức, đừng nới biểu thức cho lỏng.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public static function vaHopLe(string $raw): bool
    {
        $raw = trim($raw);

        if ($raw === '') {
            return true;
        }

        /* 0..10 ở CẢ HAI vế. Viết '10|[0-9]' chứ không '\d{1,2}': dạng sau cho
           lọt 11..99, mà đó đúng là thứ cần chặn. Dấu "/" bắt buộc và không
           cho khoảng trắng quanh nó — một chuỗi đã chuẩn hoá thì bảng thông số
           in ra đều một kiểu. */
        return preg_match('#^(10|[0-9])/(10|[0-9])$#', $raw) === 1;
    }

    /**
     * Khách TỰ KHAI số đo — ghi thẳng vào sổ chỉ-thêm.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * MỘT ĐƯỜNG GHI DUY NHẤT — SRS v2.1.0, FR-DM-01 và FR-DM-03
     *
     * Trước đây hàm này dựng lấy câu INSERT ... ON DUPLICATE KEY UPDATE của
     * riêng nó vào bảng tóm tắt `prescriptions`, kèm một bộ kiểm giá trị riêng.
     * Tức là hệ thống có HAI đường ghi số đo với HAI bộ luật:
     *
     *   · đường của khách   ghi đè bản cũ, kiểm sơ (chỉ ép kiểu và chặn trục
     *                       ngoài 0–180)
     *   · đường của nhân viên  chỉ-thêm, kiểm đầy đủ miền giá trị và bước nhảy
     *
     * Hai bộ luật cho cùng một loại dữ liệu y tế là thứ sẽ lệch: một con số bị
     * đường này từ chối lại lọt qua đường kia, và không ai biết cho tới khi nó
     * đi vào một đơn cắt tròng.
     *
     * Nay chỉ còn PrescriptionRecordModel::save() — nó kiểm đủ miền giá trị,
     * bước nhảy 0,25, quan hệ trụ/trục, dạng thị lực, và ngày đo không ở tương
     * lai (FR-DM-13). Hàm này còn lại đúng việc gắn nhãn NGUỒN.
     *
     * NGUỒN LÀ 'customer', KHÔNG PHẢI 'store'. Con số khách chép từ tờ đơn
     * thuốc cũ không đáng tin bằng con số kỹ thuật viên vừa đo, và người đọc
     * bảng phải phân biệt được trước khi đem đi mài — CLAUDE.md điểm A1.
     *
     * KHÔNG CÒN GHI ĐÈ. Mỗi lần khách lưu là một bản ghi MỚI trong sổ, không
     * phải một lần sửa bản cũ: đó là hai lần khai khác nhau ở hai thời điểm,
     * và giữ cả hai thì còn đối chiếu được. Muốn ĐÍNH CHÍNH một bản đã khai
     * thì truyền $id — nhưng trang tài khoản hiện không mở đường đó.
     *
     * `actorId` = chính khách. Vết kiểm toán nhờ đó phân biệt được "khách tự
     * khai" với "nhân viên nhập hộ", dù cả hai cùng nằm trên một tài khoản.
     *
     * TRẢ VỀ MẢNG chứ không void như trước: đường ghi mới TỪ CHỐI được, và nơi
     * gọi phải nói lại cho người dùng biết vì sao. Bỏ qua giá trị trả về ở đây
     * là để khách bấm Lưu, thấy trang tải lại, và tin rằng đã lưu xong.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return array{ok:bool, error?:string, id?:string}
     */
    public static function savePrescription(string $userId, array $values): array
    {
        $values['source'] = 'customer';

        /* TÊN Ô GHI CHÚ KHÁC NHAU GIỮA HAI BÊN.
 
           Bảng tóm tắt cũ gọi ô "khách đọc được" là `recommendation`; sổ
           chỉ-thêm gọi nó là `note` (và dành `tech_note` cho ghi chú nội bộ).
           Không ánh xạ ở đây thì khoá `recommendation` rơi vào khoảng không:
           validate() bỏ qua khoá lạ không một tiếng động, nên khách gõ khuyến
           nghị, thấy báo "đã lưu", và chữ đó không vào CSDL ở đâu cả. */
        if (!isset($values['note']) && isset($values['recommendation'])) {
            $values['note'] = $values['recommendation'];
        }

        /*
         * ─────────────────────────────────────────────────────────────────────
         * LƯU LẠI LÀ ĐÍNH CHÍNH BẢN TỰ KHAI CŨ, KHÔNG PHẢI KHAI THÊM MỘT LẦN
         *
         * Trang tài khoản là màn SỬA: form điền sẵn số cũ, có nút Huỷ. Người
         * dùng đang sửa. Nếu mỗi lần bấm Lưu đều CHÈN một bản ghi mới thì sửa
         * một lỗi gõ trục sinh ra hai "lần đo" cùng ngày với số khác nhau, và
         * nhân viên đọc sổ trước khi mài tròng không có cách nào biết bản nào
         * thay bản nào. Sửa năm lần là năm dòng.
         *
         * Nên truyền id của bản TỰ KHAI mới nhất của chính khách: sổ ghi nó
         * thành một PHIÊN BẢN trong cùng nhóm (FR-DM-04), bản cũ vẫn tra cứu
         * được đầy đủ, và bảng lịch sử hiện đúng một dòng "đã sửa N lần".
         *
         * CHỈ nhóm với bản do CHÍNH KHÁCH khai. Bản do kỹ thuật viên đo là một
         * phép đo khác của một người khác, ở một thời điểm khác — chồng phiên
         * bản của khách lên đó là ghi đè lời của người có chuyên môn bằng lời
         * khách tự nhớ, đúng thứ CLAUDE.md điểm A1 cấm.
         * ─────────────────────────────────────────────────────────────────────
         */
        $banTuKhai = PrescriptionRecordModel::banTuKhaiMoiNhat($userId);

        return PrescriptionRecordModel::save($banTuKhai, $userId, $values, $userId);
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
     * `measured_at` = HÔM NAY, không để trống. FR-DM-13 bắt buộc phải có ngày
     * đo, mà luồng mua hàng không hỏi ngày — khách chỉ gõ độ vào hộp thoại chọn
     * tròng. Hôm nay là câu trả lời trung thực nhất có thể cho một bản TỰ KHAI:
     * nó nói đúng "khách khai con số này vào ngày này". Lý do đầy đủ ở chỗ
     * truyền tham số trong thân hàm.
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

        /* NGÀY ĐO = HÔM NAY.
 
           FR-DM-13 bắt buộc phải có ngày đo, mà luồng mua hàng không hỏi ngày:
           khách gõ độ vào hộp thoại chọn tròng, không khai mình đo hôm nào.
 
           Hôm nay là câu trả lời trung thực nhất có thể cho một bản TỰ KHAI:
           nó nói đúng "khách khai con số này vào ngày này", chứ không giả vờ
           biết buổi đo thật diễn ra lúc nào. Để trống thì bản ghi bị đường ghi
           từ chối; đoán một ngày trong quá khứ thì tệ hơn — đó là bịa ra một
           dữ kiện y tế. */
        $ket = self::savePrescription($userId, [
            'od_sph'      => $od['sph']  ?? '',
            'od_cyl'      => $od['cyl']  ?? '',
            'od_axis'     => $od['axis'] ?? '',
            'os_sph'      => $os['sph']  ?? '',
            'os_cyl'      => $os['cyl']  ?? '',
            'os_axis'     => $os['axis'] ?? '',
            'measured_at' => date('Y-m-d'),
        ]);

        /* GHI LOG KHI HỎNG — đường ghi mới TRẢ VỀ lỗi chứ không NÉM.
 
           CartController bọc lời gọi này trong try/catch và ghi error_log, với
           lời hứa "vẫn ghi log để còn biết". Từ đợt 3 đường ghi không ném nữa
           nên khối catch đó thành mã chết, và ba tình huống hỏng thật — bảng
           chưa tồn tại, giá trị rớt miền, ngày sai định dạng — trở nên hoàn
           toàn im lặng: hồ sơ không được dựng và không ai biết.
 
           Vẫn KHÔNG chặn luồng mua hàng: dựng sẵn hồ sơ là việc phụ, mất nó thì
           khách vẫn khai lại được ở trang tài khoản, còn mất đơn hàng thì không
           lấy lại được. Cùng lý lẽ với khối catch bên CartController. */
        if (!$ket['ok']) {
            error_log('seedPrescription: ' . ($ket['error'] ?? 'không rõ lý do'));
        }
    }

    /**
     * Tạo MỘT tài khoản nội bộ — email + mật khẩu tạm + đúng một vai trò.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * KHÔNG DÙNG LẠI register()
     *
     * Hàm đó dựng tài khoản KHÁCH: nó bắt buộc có số điện thoại (thứ để khách
     * đăng nhập), gán vai trò 'customer', và ghi mốc đồng ý điều khoản. Ba thứ
     * đó đều sai ở đây — nhân viên đăng nhập bằng email ở cổng riêng, không mua
     * hàng, và không có điều khoản nào để đồng ý.
     *
     * Nhồi thêm nhánh vào register() thì hàm ấy phải mang theo hai bộ quy tắc
     * trái nhau, và mỗi lần sửa một bộ là phải nhớ tới bộ kia.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * MẬT KHẨU DO NƠI GỌI ĐƯA VÀO, KHÔNG SINH Ở ĐÂY
     *
     * Khác resetPasswordFor() ngay dưới. Ở đó người bấm không được chọn mật
     * khẩu — sinh sẵn để không ai đặt cùng một chuỗi cho cả cửa hàng. Ở đây thì
     * bản vẽ in sẵn một chuỗi ngẫu nhiên vào ô nhưng vẫn cho sửa, vì tài khoản
     * mới hay được lập trong lúc người sắp dùng nó đang đứng ngay cạnh.
     *
     * @return array{ok:bool, error?:string, id?:string}
     */
    public static function createStaff(
        string $email,
        string $password,
        string $fullName,
        string $role
    ): array {
        $email    = strtolower(trim($email));
        $fullName = trim($fullName);

        if (!in_array($role, self::STAFF_ROLES, true)) {
            return ['ok' => false, 'error' => 'Vai trò không hợp lệ.'];
        }

        if ($fullName === '') {
            return ['ok' => false, 'error' => 'Vui lòng nhập tên hiển thị.'];
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'error' => 'Email không hợp lệ.'];
        }

        /* SNFR-09 ÁP CHO CẢ TÀI KHOẢN NỘI BỘ, không riêng khách: "Mật khẩu tài
           khoản nội bộ tối thiểu 8 ký tự, bao gồm ít nhất 01 chữ hoa, 01 chữ
           thường, 01 chữ số và 01 ký tự đặc biệt."

           Trước đây chỗ này chỉ đo độ dài, nên tài khoản có quyền vào khu quản
           trị lại được đặt mật khẩu YẾU HƠN tài khoản khách — ngược hẳn mức độ
           nhạy cảm của hai bên. Một nhân viên xoá ô mật khẩu tạm rồi gõ
           "vineyewear2026" là qua.

           randomPassword() đã được sửa cùng lần này để mật khẩu tạm in sẵn
           trong form luôn thoả bốn điều kiện — nếu không thì chính ô điền sẵn
           của hệ thống sẽ bị hàm kiểm từ chối. */
        if (($loiMatKhau = passwordProblem($password)) !== null) {
            return ['ok' => false, 'error' => 'Mật khẩu tạm: ' . lcfirst($loiMatKhau)];
        }

        /* Email trùng thì DỪNG, không âm thầm gắn thêm vai trò cho tài khoản
           sẵn có: địa chỉ đó có thể đang là tài khoản KHÁCH, và biến một khách
           thành nhân viên bằng cách gõ trùng email là cách tệ nhất để việc đó
           xảy ra. Muốn nâng quyền cho một tài khoản có sẵn thì đó là thao tác
           khác, và phải nhìn thấy mình đang làm gì. */
        if (Database::fetchValue('SELECT id FROM users WHERE email = :e', ['e' => $email]) !== null) {
            return ['ok' => false, 'error' => 'Email này đã có tài khoản.'];
        }

        $id = uuid();

        Database::transaction(static function () use ($id, $email, $password, $fullName, $role): void {
            Database::execute(
                'INSERT INTO users (id, email, password_hash, email_verified)
                 VALUES (:id, :email, :hash, 1)',
                [
                    'id'    => $id,
                    'email' => $email,
                    'hash'  => password_hash($password, PASSWORD_DEFAULT),
                ]
            );

            Database::execute(
                'INSERT INTO profiles (id, full_name) VALUES (:id, :ten)',
                ['id' => $id, 'ten' => utf8Substr($fullName, 0, 120)]
            );

            Database::execute(
                'INSERT INTO user_roles (id, user_id, role) VALUES (:rid, :uid, :role)',
                ['rid' => uuid(), 'uid' => $id, 'role' => $role]
            );
        });

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Sửa tên hiển thị và vai trò của một tài khoản nội bộ.
     *
     * KHÔNG sửa email: nó là thứ người ta dùng để đăng nhập, và đổi nó ở đây
     * nghĩa là một người đang ngồi trước máy có thể lặng lẽ chuyển tài khoản
     * quản trị sang một hòm thư khác. Bản vẽ khoá ô email khi sửa, đúng vậy.
     *
     * VAI TRÒ ĐƯỢC THAY, KHÔNG PHẢI THÊM: xoá mọi vai trò nội bộ cũ rồi ghi
     * một cái mới. Chỉ thêm thì một tài khoản hạ từ Quản trị xuống Nhân viên
     * vẫn giữ nguyên vai trò cũ và vẫn làm được mọi thứ — đúng thứ người bấm
     * tưởng mình vừa ngăn.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function updateStaff(string $userId, string $fullName, string $role): array
    {
        $fullName = trim($fullName);

        if (!in_array($role, self::STAFF_ROLES, true)) {
            return ['ok' => false, 'error' => 'Vai trò không hợp lệ.'];
        }

        if ($fullName === '') {
            return ['ok' => false, 'error' => 'Vui lòng nhập tên hiển thị.'];
        }

        if (!self::isStaff($userId)) {
            return ['ok' => false, 'error' => 'Không tìm thấy tài khoản nội bộ này.'];
        }

        Database::transaction(static function () use ($userId, $fullName, $role): void {
            /* Hồ sơ có thể chưa tồn tại — tài khoản dựng bằng make-admin.php
               trước bản này không chắc đã có dòng `profiles`. */
            Database::execute(
                'INSERT INTO profiles (id, full_name) VALUES (:id, :ten)
                 ON DUPLICATE KEY UPDATE full_name = VALUES(full_name)',
                ['id' => $userId, 'ten' => utf8Substr($fullName, 0, 120)]
            );

            /*
             * ─────────────────────────────────────────────────────────────
             * XOÁ THEO DANH SÁCH CÓ CẢ HAI VAI TRÒ ĐÃ GỠ — SRS v2.1.0
             *
             * Trước đây câu này dựng danh sách từ STAFF_ROLES. Sau khi hằng đó
             * thu còn ['staff','admin'], một dòng 'manager' hay 'technician'
             * còn sót trong CSDL sẽ KHÔNG bị xoá — và người vừa được hạ xuống
             * "Nhân viên" vẫn giữ nguyên dòng cũ bên cạnh.
             *
             * Hậu quả không lộ ra ngay: migration đợt 2 sau đó đổi 'manager'
             * thành 'admin', nên người vừa bị hạ quyền lại thành Quản trị
             * viên. Không lỗi, không cảnh báo, và phép đếm tổng số tài khoản
             * nội bộ cũng không bắt được vì số người không đổi.
             *
             * Nên danh sách ở đây gõ thẳng và có cả hai giá trị di sản. Nó trả
             * lời câu "dọn sạch mọi vai trò nội bộ của người này", không phải
             * "liệt kê những vai trò hệ thống còn dùng" — hai câu khác nhau,
             * và chỉ câu đầu mới đúng việc câu lệnh này đang làm.
             * ─────────────────────────────────────────────────────────────
             */
            $moiVaiTroNoiBo = ['staff', 'technician', 'manager', 'admin'];

            $keys   = [];
            $params = ['uid' => $userId];

            foreach ($moiVaiTroNoiBo as $i => $r) {
                $keys[] = ':r' . $i;
                $params['r' . $i] = $r;
            }

            Database::execute(
                'DELETE FROM user_roles
                  WHERE user_id = :uid AND role IN (' . implode(', ', $keys) . ')',
                $params
            );

            Database::execute(
                'INSERT INTO user_roles (id, user_id, role) VALUES (:rid, :uid2, :role)',
                ['rid' => uuid(), 'uid2' => $userId, 'role' => $role]
            );
        });

        return ['ok' => true];
    }

    /**
     * Khoá hoặc mở khoá một tài khoản nội bộ.
     *
     * Dùng CHUNG cột `users`.`status` với tài khoản khách, nên coTheDangNhap()
     * đã chặn sẵn cả hai cổng đăng nhập — không phải viết thêm chốt nào.
     *
     * KHÁC CustomerModel::lock ở hai chỗ, cả hai đều theo bản vẽ:
     *
     *   · KHÔNG bắt buộc lý do. Hộp xác nhận của bản vẽ chỉ hỏi có/không.
     *     Khoá một khách là việc phải giải trình được về sau (khách sẽ gọi
     *     điện hỏi); khoá một đồng nghiệp thì người khoá đang ngồi cùng phòng
     *     với người bị khoá.
     *   · KHÔNG ghi vào customer_audit_logs — bảng đó dành cho dữ liệu khách,
     *     và một dòng "xem hồ sơ nhân viên" lẫn vào đó làm nhiễu chính thứ nó
     *     sinh ra để soi.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function setStaffLock(string $userId, bool $khoa, string $actorId): array
    {
        if (!self::isStaff($userId)) {
            return ['ok' => false, 'error' => 'Không tìm thấy tài khoản nội bộ này.'];
        }

        if ($userId === $actorId) {
            return ['ok' => false, 'error' => 'Không tự khoá tài khoản của chính mình được.'];
        }

        if (!Database::columnExists('users', 'status')) {
            return ['ok' => false, 'error' => 'Cơ sở dữ liệu chưa có cột trạng thái — chạy database/migrate.sh.'];
        }

        if ($khoa) {
            Database::execute(
                "UPDATE users
                    SET status = 'locked', locked_at = NOW(), locked_by = :boi,
                        locked_reason = NULL
                  WHERE id = :id",
                ['boi' => $actorId, 'id' => $userId]
            );

            /* CẮT MỌI PHIÊN "GHI NHỚ ĐĂNG NHẬP". Không có dòng này thì khoá chỉ
               chặn được người chưa đăng nhập: ai đang giữ cookie ghi nhớ vẫn
               vào thẳng, và vào được hàng tháng. Đúng cái nút khoá phải ngăn. */
            RememberModel::forgetAllFor($userId);

            return ['ok' => true];
        }

        Database::execute(
            "UPDATE users
                SET status = 'active', locked_at = NULL, locked_by = NULL,
                    locked_reason = NULL
              WHERE id = :id",
            ['id' => $userId]
        );

        return ['ok' => true];
    }

}
