<?php

/**
 * core/Otp.php — mã xác minh 6 số cho luồng đăng ký bằng số điện thoại.
 *
 * Dựng theo "Dang ky.dc.html" (Claude Design): nhập số → chọn kênh gửi →
 * nhập mã → tạo mật khẩu.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MÃ CHƯA ĐI TỚI TAY KHÁCH ĐƯỢC — ĐỌC KỸ TRƯỚC KHI ĐƯA LÊN PRODUCTION
 *
 * Dự án CHƯA có nhà cung cấp gửi tin: không SMS gateway, không Zalo ZNS. Mục
 * `zalo` trong config/company.php chỉ là đường dẫn chat, không gửi được gì.
 * Nên send() dưới đây chỉ GHI MÃ RA ERROR LOG của máy chủ.
 *
 * Hệ quả, nói thẳng: bật luồng này trên production khi chưa cắm nhà cung cấp
 * là khách thật KHÔNG đăng ký được — họ không có cách nào biết mã. Cách duy
 * nhất lấy mã lúc này là đọc error log (hoặc bật app.debug ở máy phát triển,
 * lúc đó mã hiện thẳng trên màn hình).
 *
 * CHỖ CẮM NHÀ CUNG CẤP là đúng một hàm: send(). Nối eSMS/SpeedSMS/Twilio hay
 * Zalo ZNS vào đó, trả true khi gửi được — không phần nào khác của luồng phải
 * sửa. Sinh mã, băm, hạn dùng, số lần thử đều đã nằm ở đây và chạy thật.
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

    /** Mã sống bao lâu (giây). */
    public const TTL = 300;

    /** Chờ bao lâu mới được bấm "Gửi lại" — cùng con số 60 giây của bản thiết kế. */
    public const RESEND_AFTER = 60;

    /**
     * Nhập sai bao nhiêu lần thì huỷ mã.
     *
     * Không có chốt này thì một triệu khả năng của mã 6 số là thứ máy dò cạn
     * được: mỗi lần thử là một request, để mở là mời người ta chạy vòng lặp.
     */
    public const MAX_TRIES = 5;

    /** Ba kênh của bản thiết kế. Giá trị lạ bị đẩy về 'zalo'. */
    public const METHODS = ['zalo', 'sms', 'voice'];

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
     * "Gửi" mã đi. Xem khối chú thích đầu file: hiện chỉ ghi ra log.
     *
     * Trả về true/false theo nghĩa "đã bàn giao cho nhà cung cấp chưa", để
     * khi cắm dịch vụ thật vào thì nơi gọi biết đường báo lỗi cho khách.
     */
    public static function send(string $phone, string $code, string $method): bool
    {
        error_log(sprintf(
            '[Otp] Mã cho %s qua %s: %s (chưa có nhà cung cấp — xem core/Otp.php)',
            $phone,
            $method,
            $code
        ));

        return true;
    }

    /** Tên kênh để in ra màn hình. */
    public static function methodLabel(string $method): string
    {
        return [
            'zalo'  => 'Zalo',
            'sms'   => 'SMS',
            'voice' => 'cuộc gọi',
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
