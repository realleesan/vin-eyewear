<?php

/**
 * core/App.php
 *
 * Bộ khởi động ứng dụng. index.php chỉ còn gọi App::boot() rồi App::run().
 *
 * Thứ tự nạp trong boot() có ràng buộc: helpers.php phải đứng đầu vì nó định
 * nghĩa env() và config(), mà cả bốn bước sau đều cần đọc cấu hình.
 */

class App
{
    private static bool $booted = false;

    public static function boot(): void
    {
        // Chặn khởi động hai lần: một số script CLI (import dữ liệu, cron)
        // nạp App.php rồi mới include index.php.
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        require_once CORE_PATH . '/helpers.php';

        // icons.php dùng e() và config() của helpers.php nên phải nạp sau
        require_once CORE_PATH . '/icons.php';

        self::configureErrors();
        self::configureTimezone();
        self::registerAutoloader();
        self::startSession();
    }

    /**
     * Hiển thị lỗi theo môi trường.
     *
     * Ở production, thông báo lỗi PHP có thể lộ đường dẫn tuyệt đối trên server,
     * tên bảng, thậm chí mật khẩu DB trong stack trace của PDO. Nên: luôn GHI
     * LOG đầy đủ, nhưng chỉ HIỆN ra màn hình khi đang chạy máy dev.
     */
    private static function configureErrors(): void
    {
        $debug = (bool) config('app.debug');

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        // Trang lỗi thân thiện thay cho màn hình trắng khi có exception lọt lưới.
        set_exception_handler(static function (Throwable $e) use ($debug): void {
            error_log(sprintf(
                '[Lỗi chưa bắt] %s: %s tại %s:%d',
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            if (!headers_sent()) {
                http_response_code(500);
            }

            if ($debug) {
                echo '<pre style="padding:24px;font:14px/1.6 monospace;white-space:pre-wrap">';
                echo e($e::class . ': ' . $e->getMessage()) . "\n\n";
                echo e($e->getTraceAsString());
                echo '</pre>';
                return;
            }

            $errorPage = ROOT_PATH . '/errors/500.php';
            if (is_file($errorPage)) {
                require $errorPage;
            } else {
                echo 'Đã có lỗi xảy ra. Vui lòng thử lại sau.';
            }
        });
    }

    /**
     * Đặt múi giờ cho toàn bộ hàm ngày tháng của PHP.
     *
     * Cần thiết vì schema lưu DATETIME không kèm offset. Nếu PHP và MySQL
     * hiểu khác múi giờ, sự kiện "20/08 09:00" sẽ hiện lệch 7 tiếng.
     */
    private static function configureTimezone(): void
    {
        date_default_timezone_set(config('app.timezone', 'Asia/Ho_Chi_Minh'));
    }

    /**
     * Tự nạp class khi được gọi lần đầu, thay cho hàng chục dòng require_once.
     *
     * Dự án không dùng namespace nên bộ nạp tìm lần lượt qua các thư mục đã
     * biết. Thứ tự ưu tiên: core -> models -> controllers -> services ->
     * middleware. Controller trong Admin/ được nhận biết qua tiền tố tên lớp.
     */
    private static function registerAutoloader(): void
    {
        spl_autoload_register(static function (string $class): void {
            $paths = [
                CORE_PATH . '/' . $class . '.php',
                APP_PATH . '/models/' . $class . '.php',
                APP_PATH . '/controllers/' . $class . '.php',
                APP_PATH . '/controllers/Admin/' . $class . '.php',
                APP_PATH . '/services/' . $class . '.php',
                APP_PATH . '/middleware/' . $class . '.php',
            ];

            foreach ($paths as $path) {
                if (is_file($path)) {
                    require_once $path;
                    return;
                }
            }
        });
    }

    /**
     * Mở phiên làm việc với cookie đã siết bảo mật.
     *
     * Ba thuộc tính cookie dưới đây là bắt buộc, không phải tuỳ chọn:
     *   httponly  — JavaScript không đọc được cookie phiên, nên một lỗi XSS
     *               không lập tức thành chiếm tài khoản.
     *   samesite  — trình duyệt không gửi cookie khi request đến từ site khác,
     *               chặn phần lớn tấn công CSRF ngay từ tầng trình duyệt.
     *               'Lax' vẫn cho phép người dùng bấm link từ ngoài vào mà
     *               không bị đăng xuất.
     *   secure    — chỉ gửi cookie qua HTTPS. Bật theo môi trường: đặt cứng
     *               true sẽ làm hỏng đăng nhập khi chạy http://localhost.
     */
    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // lifetime = 0 -> cookie phiên: mất khi đóng trình duyệt.
        //
        // TRƯỚC ĐÂY để 14 ngày cho MỌI người, nghĩa là ai đăng nhập một lần
        // trên máy tiệm net cũng vẫn còn đăng nhập hai tuần sau. Việc "nhớ
        // lâu" nay là LỰA CHỌN của người dùng qua ô "Ghi nhớ đăng nhập", và
        // được thực hiện bằng token riêng có thể thu hồi (RememberModel) chứ
        // không phải bằng cách kéo dài cookie phiên.
        //
        // SESSION_LIFETIME trong .env vẫn dùng cho session.gc_maxlifetime —
        // tức dữ liệu phiên sống bao lâu trên máy chủ.
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        ini_set('session.gc_maxlifetime', (string) config('app.session_lifetime', 1209600));

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('vin_session');
        session_start();
    }

    /**
     * Đọc bảng route rồi giao cho Router xử lý.
     */
    public static function run(): void
    {
        $routes = require CONFIG_PATH . '/routes.php';

        (new Router($routes))->dispatch();
    }
}
