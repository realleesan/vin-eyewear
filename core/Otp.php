<?php

/**
 * core/Otp.php — mã xác minh 6 số.
 *
 * Dùng ở HAI luồng, cùng một bộ quy tắc sinh/băm/hạn dùng/số lần thử:
 *
 *   Đăng ký bằng số điện thoại  — dựng theo "Dang ky.dc.html" (Claude Design):
 *                                 nhập số → chọn kênh gửi → nhập mã → tạo mật khẩu.
 *   Quên mật khẩu               — PasswordResetModel::requestOtp(): gõ email thì
 *                                 mã đi qua Mailer, gõ số thì đi qua send() dưới đây.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MÃ ĐI QUA ZALO — KIỂM TRA CẤU HÌNH TRƯỚC KHI ĐƯA LÊN PRODUCTION
 *
 * Kênh `zalo` đã cắm thật: send() gọi Zalo::sendOtp(), đi bằng ZNS. Nhưng nó
 * chỉ chạy khi đã khai đủ app_id, secret_key, refresh_token và mã mẫu tin OTP
 * — xem config/zalo.php. Chưa khai đủ thì mã chỉ GHI RA ERROR LOG, và khách
 * thật KHÔNG đăng ký được vì không có cách nào biết mã. Ở máy phát triển
 * (app.debug) mã hiện thẳng trên màn hình, xem AuthController::signupSend().
 *
 * Dùng ready() để hỏi "gửi được chưa" thay vì đoán: trang
 * /quan-tri/quen-mat-khau đọc giá trị đó để biết yêu cầu bằng số điện thoại
 * đang tự chạy hay đang chờ nhân viên gọi điện.
 *
 * HAI KÊNH CÒN LẠI CHƯA CÓ ĐƯỜNG RA. SMS cần một gateway (eSMS, SpeedSMS,
 * Twilio…), gọi thoại cần dịch vụ voice OTP — cả hai đều là hợp đồng riêng,
 * chưa ký thì send() ghi log rồi trả false. Cửa hàng chọn Zalo trước chính vì
 * rẻ hơn SMS. Chỗ cắm cho chúng vẫn là đúng một hàm: send().
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MÃ ĐƯỢC BĂM TRƯỚC KHI CẤT, KHÔNG CẤT NGUYÊN VĂN
 *
 * Mã sống trong $_SESSION, mà session lưu thành file trên đĩa máy chủ. Cất
 * nguyên văn thì bất kỳ ai đọc được thư mục session — một lỗi cấu hình, một
 * bản sao lưu để hớ — là đọc được mã của mọi phiên đang mở. Băm rồi thì thứ
 * đọc được không dùng vào việc gì.
 *
 * password_hash chứ không phải hash('sha256'): mã chỉ có 6 chữ số, tức một
 * triệu khả năng — sha256 quét cạn trong tích tắc trên máy thường. Hàm băm
 * chậm biến một triệu khả năng đó thành hàng giờ.
 */
class Otp
{
    /** Số chữ số của mã. Đúng số ô trên màn hình nhập mã. */
    public const LENGTH = 6;

    /**
     * Mã sống bao lâu (giây).
     *
     * 120 GIÂY, KHÔNG PHẢI 300. Con số này do SRS chốt (mục 3.3.2, SNFR-06:
     * "OTP có hiệu lực 120 giây"), không phải lựa chọn kỹ thuật — đừng nới ra
     * cho "khách đỡ vội" mà chưa hỏi BA. Trước đây để 300 giây, tức mã sống
     * gấp rưỡi thời gian đặc tả cho phép: một mã 6 số nằm chờ 5 phút là 5 phút
     * để máy dò, trong khi chốt MAX_TRIES chỉ chặn được theo phiên.
     *
     * PasswordResetModel dựng câu "Mã có hiệu lực trong N phút" bằng cách chia
     * hằng số này cho 60, nên sửa ở đây là câu chữ trong email/ZNS tự đúng theo.
     * Giữ giá trị chia hết cho 60 để câu đó không ra số lẻ.
     */
    public const TTL = 120;

    /** Chờ bao lâu mới được bấm "Gửi lại" — cùng con số 60 giây của bản thiết kế. */
    public const RESEND_AFTER = 60;

    /**
     * Nhập sai bao nhiêu lần thì huỷ mã.
     *
     * Không có chốt này thì một triệu khả năng của mã 6 số là thứ máy dò cạn
     * được: mỗi lần thử là một request, để mở là mời người ta chạy vòng lặp.
     */
    public const MAX_TRIES = 5;

    /**
     * Kênh BẤM ĐƯỢC trong luồng đăng ký. Giá trị lạ bị đẩy về 'zalo'.
     *
     * CHỈ CÒN ZALO. SMS và gọi thoại đã bị gỡ khỏi danh sách này: dự án chưa
     * ký với gateway nào, nên send() của hai kênh đó chỉ ghi log rồi trả false.
     * Một nút bấm được mà mã không bao giờ tới còn tệ hơn không có nút — khách
     * ngồi chờ một tin nhắn không tồn tại, hết mã, rồi bỏ dở việc đăng ký, và
     * không có gì trên màn hình nói cho họ biết vì sao.
     *
     * KÝ XONG THÌ THÊM LẠI MỘT DÒNG VÀO ĐÂY, trong chính lần sửa mà nối gateway
     * vào send() — không sớm hơn. Màn "chọn phương thức" và các nút "Phương
     * thức khác" tự hiện lại theo, xem choices() và auth/_signup.php.
     *
     * KHÔNG có 'email': đăng ký chỉ hỏi số điện thoại, chưa biết email của
     * khách để mà gửi. Kênh email chỉ xuất hiện ở luồng quên mật khẩu, nơi
     * chính khách gõ địa chỉ ra — nó có nhãn trong methodLabel()/sentVia()
     * nhưng không phải một lựa chọn bấm được.
     */
    public const METHODS = ['zalo'];

    /**
     * Nhãn và câu mô tả của từng kênh trên màn "chọn phương thức".
     *
     * Giữ đủ ba dòng kể cả khi hai kênh dưới đang bị ẩn: đây là phần trình bày,
     * và nó phải sẵn sàng cho ngày METHODS dài trở lại. Thứ quyết định kênh nào
     * hiện ra là METHODS, không phải bảng này.
     */
    public const CHANNELS = [
        'zalo'  => ['Zalo',      'Nhận mã trong ứng dụng Zalo'],
        'sms'   => ['SMS',       'Tin nhắn tới số điện thoại'],
        'voice' => ['Gọi thoại', 'Tổng đài đọc mã cho bạn'],
    ];

    /**
     * Các kênh bấm được kèm nhãn — thứ màn "chọn phương thức" lặp qua.
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function choices(): array
    {
        return array_intersect_key(self::CHANNELS, array_flip(self::METHODS));
    }

    /**
     * CÓ ĐÁNG HIỆN MÀN "CHỌN PHƯƠNG THỨC" KHÔNG?
     *
     * Còn đúng một kênh thì không: một danh sách một dòng chẳng cho ai chọn gì,
     * và cái nút "Phương thức khác" dẫn tới nó chỉ tổ làm khách tưởng còn đường
     * khác rồi thất vọng. Cả ba chỗ dẫn vào màn đó đều hỏi hàm này.
     */
    public static function hasChoice(): bool
    {
        return count(self::METHODS) > 1;
    }

    /**
     * GỬI ĐƯỢC MÃ QUA SỐ ĐIỆN THOẠI CHƯA?
     *
     * Trả lời theo CẤU HÌNH thật, không theo một hằng bật tay: khai đủ token và
     * mẫu tin ZNS là bật, xoá đi là tắt. Một công tắc riêng thì sớm muộn cũng
     * lệch với cấu hình, mà lệch ở đây thì mọi yêu cầu đặt lại mật khẩu bằng số
     * điện thoại biến khỏi hàng chờ ở /quan-tri/quen-mat-khau trong khi khách
     * chẳng nhận được gì và không còn ai biết mà gọi lại cho họ.
     *
     * $method để dành cho ngày cắm SMS/gọi thoại — lúc đó hai kênh kia có điều
     * kiện riêng, và nơi gọi đã hỏi đúng câu từ bây giờ.
     */
    public static function ready(string $method = 'zalo'): bool
    {
        return $method === 'zalo' && Zalo::otpReady();
    }

    public static function generate(): string
    {
        // random_int chứ không phải rand/mt_rand: hai hàm kia đoán được dãy
        // tiếp theo khi biết vài giá trị trước, mà đây là thứ chặn người lạ
        // chiếm số điện thoại của người khác.
        return str_pad((string) random_int(0, 999999), self::LENGTH, '0', STR_PAD_LEFT);
    }

    public static function hash(string $code): string
    {
        return password_hash($code, PASSWORD_DEFAULT);
    }

    public static function matches(string $code, string $hash): bool
    {
        return $hash !== '' && password_verify($code, $hash);
    }

    /**
     * Gửi mã đi bằng kênh của SỐ ĐIỆN THOẠI.
     *
     * Mã gửi qua EMAIL không đi lối này — nó đã có Mailer, và nội dung thư là
     * việc của nơi phát sinh mã (xem PasswordResetModel::otpEmailHtml). Hàm
     * này chỉ dành cho ba kênh của số điện thoại.
     *
     * Trả về true/false theo nghĩa "nhà cung cấp đã nhận chưa", để nơi gọi biết
     * đường báo cho khách hay đẩy yêu cầu về cho nhân viên.
     */
    public static function send(string $phone, string $code, string $method): bool
    {
        if ($method === 'zalo') {
            $sent = Zalo::sendOtp($phone, $code);

            /* Zalo đã tự ghi log lý do hỏng rồi, nhưng chưa ghi MÃ — mà mã mới
               là thứ nhân viên cần để đọc qua điện thoại cho khách khi ZNS
               chưa cắm xong. Chỉ ghi khi gửi hỏng: gửi được rồi mà vẫn để mã
               nằm trong log là tự tay rải mật khẩu một lần ra đĩa. */
            if (!$sent) {
                self::log($phone, $code, $method);
            }

            return $sent;
        }

        /* SMS và gọi thoại chưa có nhà cung cấp — xem khối chú thích đầu file.
           Chỗ cắm là ngay đây, và chỉ cần trả true khi gửi được. */
        self::log($phone, $code, $method);

        return false;
    }

    /** Mã nằm lại trong error log khi không gửi đi được. */
    private static function log(string $phone, string $code, string $method): void
    {
        error_log(sprintf(
            '[Otp] Mã cho %s qua %s: %s (không gửi đi được — xem core/Otp.php)',
            $phone,
            $method,
            $code
        ));
    }

    /** Tên kênh để in ra màn hình. */
    public static function methodLabel(string $method): string
    {
        return [
            'zalo'  => 'Zalo',
            'sms'   => 'SMS',
            'voice' => 'cuộc gọi',
            'email' => 'email',
        ][$method] ?? 'Zalo';
    }

    /**
     * Câu "Mã xác minh đã được gửi qua … đến" của bản thiết kế.
     */
    public static function sentVia(string $method): string
    {
        return [
            'zalo'  => 'Mã xác minh đã được gửi qua Zalo đến',
            'sms'   => 'Mã xác minh đã được gửi qua SMS đến',
            'voice' => 'Mã xác minh đã được gửi qua cuộc gọi đến',
            'email' => 'Mã xác minh đã được gửi qua email đến',
        ][$method] ?? 'Mã xác minh đã được gửi qua Zalo đến';
    }

    /**
     * Số điện thoại dạng "(+84) 912 345 678" — đúng cách bản thiết kế hiển thị.
     *
     * Nhận số đã chuẩn hoá của normalizePhone() (dạng 0xxxxxxxxx). Chuỗi lạ
     * thì trả về nguyên văn: đây là hàm trình bày, không phải hàm kiểm tra.
     */
    public static function displayPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) < 9) {
            return $phone;
        }

        return '(+84) ' . trim(implode(' ', [
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6),
        ]));
    }
}
