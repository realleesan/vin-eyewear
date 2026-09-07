<?php

/**
 * tools/kiem-tra-mail.php — thử cấu hình gửi email, TRƯỚC khi đi qua luồng thật.
 *
 *     php tools/kiem-tra-mail.php                       # chỉ soi cấu hình, không gửi gì
 *     php tools/kiem-tra-mail.php ban@gmail.com         # gửi một thư thử tới địa chỉ đó
 *     php tools/kiem-tra-mail.php --otp ban@gmail.com   # chạy ĐÚNG luồng quên mật khẩu
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO CẦN FILE NÀY
 *
 * Đường duy nhất để biết mail có đi được không, trước đây, là bấm qua cả bốn
 * chặng của /quen-mat-khau rồi ngồi đoán. Mà Mailer::send() cố ý NUỐT mọi
 * exception và chỉ trả false (xem khối chú thích đầu core/Mailer.php) — đúng
 * cho trang khách, vì ở đó lỗi mail phải rơi sang đường nhân viên chứ không
 * phải ném 500 vào mặt người ta. Hệ quả là lý do thật nằm trong
 * Mailer::lastError() mà không màn hình nào in ra.
 *
 * File này in ra đúng chuỗi đó, kèm ba thứ hay hỏng trước cả khi tới SMTP:
 * thiếu extension openssl, cổng bị chặn ngoài đường, và MAIL_FROM_ADDRESS
 * không khớp tài khoản đã xác thực (Gmail từ chối thẳng ca này).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CHẾ ĐỘ --otp: KHI SMTP ĐÃ THÔNG MÀ MÃ VẪN KHÔNG TỚI
 *
 * SMTP chạy được không có nghĩa là /quen-mat-khau gửi mã. Giữa hai thứ đó còn
 * cả PasswordResetModel::requestOtp(), và hàm ấy có BA cửa im lặng — im lặng
 * là CỐ Ý, vì màn hình không được phép hé ra ai có tài khoản ở đây (xem khối
 * chú thích trong hàm). Cả ba đều hiện ra y hệt nhau trên trình duyệt:
 *
 *   · chuỗi vừa gõ không khớp tài khoản nào  -> không gửi đi đâu cả
 *   · khớp một tài khoản NỘI BỘ              -> cố ý coi như không tìm thấy
 *   · khớp, nhưng tài khoản KHÔNG CÓ email   -> rơi về Zalo, mà ZNS chưa cắm
 *
 * Chế độ này gọi thẳng requestOtp() rồi in ra cửa nào đã đóng. Ở dòng lệnh
 * thì nói thật được: người chạy nó đã ngồi trên chính máy chủ.
 *
 * TỐN MỘT LƯỢT trong hạn 5 yêu cầu/giờ của tooMany(), và có gửi mail thật.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CHẠY BẰNG PHP CỦA XAMPP
 *
 *     /opt/lampp/bin/php tools/kiem-tra-mail.php ban@gmail.com
 *
 * PHP hệ thống và PHP trong XAMPP là hai bản khác nhau, và openssl có thể bật
 * ở bản này mà tắt ở bản kia. Site chạy bằng bản nào thì thử bằng đúng bản đó,
 * nếu không kết quả ở đây không nói lên điều gì về site.
 *
 * CHỈ CHẠY BẰNG DÒNG LỆNH. tools/ đã bị .htaccess và server.php chặn khỏi web,
 * nhưng chốt dưới đây là hàng rào cuối: file này in ra host, cổng, tên đăng
 * nhập SMTP — không phải thứ để hở ra một địa chỉ URL.
 *
 * KHÔNG BAO GIỜ IN MẬT KHẨU, chỉ in độ dài. Cùng lối với kiem-tra-db.php.
 * ─────────────────────────────────────────────────────────────────────────────
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Chỉ chạy được bằng dòng lệnh.\n");
}

define('ROOT_PATH',   dirname(__DIR__));
define('APP_PATH',    ROOT_PATH . '/app');
define('CORE_PATH',   ROOT_PATH . '/core');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('VIEWS_PATH',  APP_PATH . '/views');

$otpMode = ($argv[1] ?? '') === '--otp';
$den     = $otpMode ? ($argv[2] ?? '') : ($argv[1] ?? '');

/* Chế độ thường chỉ cần env()/config() và lớp Mailer — nạp tay cho nhẹ.
   Chế độ --otp thì phải có autoloader và kết nối CSDL, tức là cả App::boot().
   boot() có mở session; ở CLI việc đó chạy im, chỉ để lại một file phiên mồ
   côi trong thư mục session, không ảnh hưởng gì tới site. */
require_once CORE_PATH . '/helpers.php';
require_once CORE_PATH . '/Mailer.php';

if ($otpMode) {
    require_once CORE_PATH . '/App.php';
    App::boot();
}

echo "\n";
echo "══ CẤU HÌNH MAIL ĐANG DÙNG ═══════════════════════════════════════════\n";

$driver = strtolower((string) config('mail.driver', 'log'));
$host   = (string) config('mail.host', '');
$port   = (int) config('mail.port', 587);
$enc    = strtolower((string) config('mail.encryption', 'tls'));
$user   = (string) config('mail.username', '');
$pass   = (string) config('mail.password', '');
$from   = (string) config('mail.from_address', '');

printf("  MAIL_DRIVER        %s\n", $driver);
printf("  MAIL_HOST          %s\n", $host !== '' ? $host : '(trống)');
printf("  MAIL_PORT          %d\n", $port);
printf("  MAIL_ENCRYPTION    %s\n", $enc !== '' ? $enc : '(trống — không mã hoá)');
printf("  MAIL_USERNAME      %s\n", $user !== '' ? $user : '(trống)');
printf("  MAIL_PASSWORD      %s\n", $pass !== '' ? strlen($pass) . ' ký tự' : '(trống)');
printf("  MAIL_FROM_ADDRESS  %s\n", $from !== '' ? $from : '(trống)');

echo "\n";
echo "══ CÁC CHỐT TRƯỚC KHI TỚI SMTP ═══════════════════════════════════════\n";

$loi = [];

/* Chế độ log không gửi đi đâu cả — nói thẳng, vì đây đúng là ca hay làm người
   ta mất buổi chiều: mọi thứ "thành công" mà hộp thư trống trơn. */
if ($driver === 'log') {
    echo "  ⚠ MAIL_DRIVER=log — thư KHÔNG gửi đi đâu, chỉ ghi ra storage/mail/.\n";
    echo "    Đổi sang smtp trong .env nếu muốn mã OTP về hộp thư thật.\n";
}

if ($driver === 'smtp') {
    if (!extension_loaded('openssl')) {
        $loi[] = 'Bản PHP này KHÔNG có extension openssl — không bắt tay TLS được. '
               . 'Bật extension=openssl trong php.ini rồi chạy lại.';
    } else {
        echo "  ✓ extension openssl có sẵn\n";
    }

    if ($host === '' || $user === '') {
        $loi[] = 'Thiếu MAIL_HOST hoặc MAIL_USERNAME — Mailer::canDeliver() trả false, '
               . 'nên PasswordResetModel bỏ qua đường email luôn.';
    }

    /* Gmail (và phần lớn nhà cung cấp) từ chối MAIL FROM khác tài khoản vừa
       xác thực. Lỗi trả về là "553 / 5.7.60 SMTP; Client does not have
       permissions to send as this sender" — đọc một mình thì rất khó đoán ra
       nguyên nhân là một dòng .env cách đó ba dòng. */
    if ($user !== '' && $from !== '' && strcasecmp($user, $from) !== 0) {
        echo "  ⚠ MAIL_FROM_ADDRESS ($from) khác MAIL_USERNAME ($user).\n";
        echo "    Gmail/Outlook sẽ từ chối: người gửi phải đúng tài khoản đã xác thực\n";
        echo "    (hoặc một bí danh đã khai trong tài khoản đó).\n";
    }

    if ($host !== '' && $loi === []) {
        $target = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $sock   = @stream_socket_client($target, $errno, $errstr, 8);

        if ($sock) {
            $chao = fgets($sock, 1024);
            fclose($sock);
            printf("  ✓ nối được %s — máy chủ chào: %s", $target, $chao ?: "(im lặng)\n");
        } else {
            $loi[] = sprintf(
                "Không nối được %s (%d: %s). Thường là mạng/tường lửa chặn cổng ra, "
                . "hoặc sai MAIL_HOST/MAIL_PORT.",
                $target,
                $errno,
                $errstr
            );
        }
    }
}

printf("  %s Mailer::canDeliver() = %s\n",
    Mailer::canDeliver() ? '✓' : '✗',
    Mailer::canDeliver() ? 'true (cấu hình đủ để gửi thật)' : 'false (sẽ KHÔNG gửi thật)');

foreach ($loi as $l) {
    echo "  ✗ $l\n";
}

if ($loi !== []) {
    echo "\nDừng ở đây — sửa các dòng ✗ bên trên rồi chạy lại.\n\n";
    exit(1);
}

if ($den === '') {
    echo "\nMuốn gửi thử một thư thật:  php tools/kiem-tra-mail.php ban@gmail.com\n";
    echo "Mã OTP không tới dù SMTP đã thông:  php tools/kiem-tra-mail.php --otp ban@gmail.com\n\n";
    exit(0);
}

// ============================================================================
// CHẾ ĐỘ --otp: đi đúng con đường mà /quen-mat-khau đi
// ============================================================================

if ($otpMode) {
    echo "\n";
    echo "══ LUỒNG QUÊN MẬT KHẨU VỚI '$den' ═══════════════════════════════════\n";

    if (!PasswordResetModel::available()) {
        fwrite(STDERR, "  ✗ Bảng password_resets chưa có. Chạy migration trước.\n\n");
        exit(1);
    }

    /* Đi lại từng bước của requestOtp() để nói ra được cửa nào đóng, TRƯỚC khi
       gọi hàm thật — hàm thật cố ý trả về giống hệt nhau ở cả ba ca. */
    $kenhGo = PasswordResetModel::channelOf($den);
    printf("  · chuỗi vừa gõ nhận là: %s\n", $kenhGo ?? 'KHÔNG RA THỨ GÌ (sẽ bị từ chối ngay)');

    $u = UserModel::findByLogin($den);

    if ($u === null) {
        echo "  ✗ KHÔNG có tài khoản nào khớp chuỗi này.\n";
        echo "    requestOtp() vẫn trả 'ok' và màn nhập mã vẫn hiện ra — cố ý, để\n";
        echo "    trang này không thành máy dò khách hàng — nhưng KHÔNG gửi mã đi đâu.\n";
        echo "    Kiểm lại email trong bảng users, hoặc tài khoản đã bị xoá mềm\n";
        echo "    (users.deleted_at khác NULL thì findByLogin() bỏ qua).\n\n";
        exit(1);
    }

    printf("  ✓ tìm thấy tài khoản id=%s\n", (string) $u['id']);
    printf("    users.email  = %s\n", ($u['email'] ?? '') !== '' ? (string) $u['email'] : '(NULL — đây là vấn đề)');
    printf("    profiles.phone = %s\n", ($u['phone'] ?? '') !== '' ? (string) $u['phone'] : '(trống)');

    if (UserModel::isStaff((string) $u['id'])) {
        echo "  ✗ Đây là TÀI KHOẢN NỘI BỘ. Luồng quên mật khẩu của khách cố ý coi\n";
        echo "    như không tìm thấy — nếu không, ai chiếm được hộp thư nội bộ là đổi\n";
        echo "    được mật khẩu vào khu quản trị. Đường của nhân viên là\n";
        echo "    /quan-tri/nhan-vien. Thử lại bằng một tài khoản KHÁCH.\n\n";
        exit(1);
    }

    $mail = trim((string) ($u['email'] ?? ''));

    if ($mail === '' || filter_var($mail, FILTER_VALIDATE_EMAIL) === false) {
        echo "  ✗ Tài khoản này KHÔNG có email hợp lệ, nên mã rơi về Zalo — mà ZNS\n";
        echo "    chưa khai cấu hình, tức là mã chỉ nằm trong error log.\n";
        echo "    Thêm email cho tài khoản (trang hồ sơ, hoặc /quan-tri/khach-hang).\n\n";
        exit(1);
    }

    echo "\n  → Gọi PasswordResetModel::requestOtp() thật…\n";

    $kq = PasswordResetModel::requestOtp($den);

    if (!($kq['ok'] ?? false)) {
        printf("  ✗ Bị từ chối: %s\n\n", $kq['error'] ?? '(không rõ)');
        exit(1);
    }

    printf("    kênh hiện ra màn hình : %s\n", (string) ($kq['channel'] ?? '?'));
    printf("    chuỗi hiện ra màn hình: %s\n", (string) ($kq['display'] ?? '?'));
    printf("    gửi được chưa (sent)  : %s\n", !empty($kq['sent']) ? 'true' : 'FALSE');

    if (($kq['code'] ?? null) !== null) {
        printf("    mã vừa sinh           : %s  (chỉ in vì APP_DEBUG=true)\n", (string) $kq['code']);
    }

    if (empty($kq['sent'])) {
        printf("\n  ✗ Mail KHÔNG đi được.\n     Lý do: %s\n\n", Mailer::lastError() ?? '(không rõ)');
        exit(1);
    }

    printf("\n  ✓ Mã đã gửi tới %s. Mở hộp thư (cả mục Spam).\n", $mail);
    echo "    Mã sống 120 giây — nhập kịp giờ.\n\n";
    exit(0);
}

if (!filter_var($den, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "\n✗ '$den' không phải một địa chỉ email hợp lệ.\n\n");
    exit(1);
}

echo "\n";
echo "══ GỬI THƯ THỬ TỚI $den ═══\n";

$ok = Mailer::send(
    $den,
    'Thử cấu hình mail — Vin Eyewear',
    '<p style="font-family:system-ui,sans-serif">Cấu hình mail của Vin Eyewear '
        . 'đang hoạt động. Mã OTP quên mật khẩu sẽ đi theo đúng đường này.</p>',
    'Cấu hình mail của Vin Eyewear đang hoạt động.'
);

if ($ok) {
    echo "  ✓ Máy chủ SMTP đã nhận thư. Kiểm tra hộp thư (cả mục Spam).\n\n";
    exit(0);
}

printf("  ✗ Không gửi được.\n     Lý do: %s\n\n", Mailer::lastError() ?? '(không rõ)');
exit(1);
