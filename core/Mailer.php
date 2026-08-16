<?php

/**
 * Mailer — gửi email, không phụ thuộc thư viện ngoài.
 *
 * Dự án không dùng Composer nên không có PHPMailer. Ba chế độ, chọn bằng
 * MAIL_DRIVER trong .env:
 *
 *   log   Không gửi đi đâu cả, ghi toàn bộ thư vào storage/mail/. Dùng khi
 *         phát triển: xem được đúng nội dung và liên kết mà khách sẽ nhận,
 *         không cần cấu hình gì, không lỡ tay gửi cho người thật.
 *   mail  Gọi hàm mail() của PHP. Chạy trên hosting có sendmail (cPanel,
 *         VPS). InfinityFree bản miễn phí VÔ HIỆU HOÁ hàm này.
 *   smtp  Nối thẳng tới máy chủ SMTP bên ngoài (Gmail, Brevo, SendGrid…).
 *         Cần MAIL_HOST/PORT/USERNAME/PASSWORD. InfinityFree bản miễn phí
 *         chặn cổng ra ngoài nên chế độ này cũng không dùng được ở đó.
 *
 * send() KHÔNG bao giờ ném lỗi ra ngoài — trả về false. Lý do: nơi gọi nó là
 * luồng quên mật khẩu, và ở đó việc không gửi được mail phải rơi sang đường
 * dự phòng (nhân viên xử lý tay), chứ không phải ném lỗi 500 vào mặt khách.
 */

class Mailer
{
    /** Thư gửi đi lâu nhất bao nhiêu giây trước khi bỏ cuộc. */
    private const TIMEOUT = 12;

    /** Lý do thất bại gần nhất, để controller ghi log hoặc hiện cho quản trị. */
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @param string $to      Địa chỉ nhận
     * @param string $subject Tiêu đề (UTF-8, sẽ tự mã hoá)
     * @param string $html    Nội dung HTML
     * @param string $text    Bản chữ thuần; để trống thì tự rút từ $html
     */
    public static function send(string $to, string $subject, string $html, string $text = ''): bool
    {
        self::$lastError = null;

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'Địa chỉ nhận không hợp lệ: ' . $to;

            return false;
        }

        if ($text === '') {
            $text = self::htmlToText($html);
        }

        try {
            return match (strtolower((string) config('mail.driver', 'log'))) {
                'smtp'  => self::sendSmtp($to, $subject, $html, $text),
                'mail'  => self::sendMailFunction($to, $subject, $html, $text),
                default => self::sendLog($to, $subject, $html, $text),
            };
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Cấu hình đã đủ để thật sự gửi được thư chưa?
     *
     * Luồng quên mật khẩu hỏi câu này để biết nên gửi mail hay chuyển sang
     * đường nhân viên xử lý. Chế độ 'log' tính là KHÔNG gửi được: thư nằm
     * trong một file trên máy chủ thì khách không bao giờ thấy.
     */
    public static function canDeliver(): bool
    {
        $driver = strtolower((string) config('mail.driver', 'log'));

        if ($driver === 'mail') {
            // Nhiều hosting miễn phí để hàm mail() tồn tại nhưng vô hiệu hoá
            // qua disable_functions — kiểm cả hai.
            $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

            return function_exists('mail') && !in_array('mail', $disabled, true);
        }

        if ($driver === 'smtp') {
            return config('mail.host') !== '' && config('mail.username') !== '';
        }

        return false;
    }

    // ========================================================================
    // CÁC CHẾ ĐỘ
    // ========================================================================

    private static function sendLog(string $to, string $subject, string $html, string $text): bool
    {
        $dir = ROOT_PATH . '/storage/mail';

        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            self::$lastError = 'Không tạo được thư mục ' . $dir;

            return false;
        }

        $file = sprintf('%s/%s-%s.html', $dir, date('Ymd-His'), substr(sha1($to . $subject), 0, 8));

        $body = "<!-- Đến:     {$to}\n"
              . "     Tiêu đề: {$subject}\n"
              . "     Lúc:     " . date('c') . "\n"
              . "     (MAIL_DRIVER=log — thư KHÔNG được gửi đi đâu cả) -->\n\n"
              . $html
              . "\n\n<!-- Bản chữ thuần:\n" . $text . "\n-->\n";

        if (@file_put_contents($file, $body) === false) {
            self::$lastError = 'Không ghi được ' . $file;

            return false;
        }

        return true;
    }

    private static function sendMailFunction(string $to, string $subject, string $html, string $text): bool
    {
        $boundary = 'vin-' . bin2hex(random_bytes(12));
        $from     = self::fromHeader();

        $headers = implode("\r\n", [
            'From: ' . $from,
            'Reply-To: ' . config('mail.from_address'),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: Vin Eyewear',
        ]);

        $ok = @mail($to, self::encodeHeader($subject), self::multipartBody($boundary, $html, $text), $headers);

        if (!$ok) {
            self::$lastError = 'Hàm mail() trả về false — hosting có thể đã chặn.';
        }

        return $ok;
    }

    /**
     * Nói chuyện trực tiếp với máy chủ SMTP.
     *
     * Chỉ dùng AUTH LOGIN — cách mọi dịch vụ phổ thông (Gmail, Brevo,
     * SendGrid, Mailgun, Zoho) đều nhận. Không làm AUTH PLAIN/CRAM-MD5 cho
     * gọn; thiếu cái nào thì thà báo lỗi rõ còn hơn im lặng gửi hụt.
     */
    private static function sendSmtp(string $to, string $subject, string $html, string $text): bool
    {
        $host = (string) config('mail.host');
        $port = (int) config('mail.port', 587);
        $enc  = strtolower((string) config('mail.encryption', 'tls'));
        $user = (string) config('mail.username');
        $pass = (string) config('mail.password');

        if ($host === '') {
            self::$lastError = 'Chưa đặt MAIL_HOST trong .env';

            return false;
        }

        // ssl:// nối mã hoá ngay từ đầu (cổng 465); tls:// nối thường rồi
        // nâng cấp bằng STARTTLS (cổng 587) — xử lý ở dưới.
        $target = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

        $ctx = stream_context_create(['ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ]]);

        $sock = @stream_socket_client($target, $errno, $errstr, self::TIMEOUT,
                                      STREAM_CLIENT_CONNECT, $ctx);

        if (!$sock) {
            self::$lastError = sprintf('Không nối được %s (%d: %s)', $target, $errno, $errstr);

            return false;
        }

        stream_set_timeout($sock, self::TIMEOUT);

        try {
            self::expect($sock, 220);

            $ehlo = self::cmd($sock, 'EHLO ' . self::hostname(), 250);

            if ($enc === 'tls') {
                if (stripos($ehlo, 'STARTTLS') === false) {
                    throw new RuntimeException('Máy chủ không hỗ trợ STARTTLS.');
                }
                self::cmd($sock, 'STARTTLS', 220);

                if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Bắt tay TLS thất bại.');
                }

                // Sau STARTTLS phải EHLO lại: danh sách khả năng của máy chủ
                // trước và sau khi mã hoá có thể khác nhau.
                self::cmd($sock, 'EHLO ' . self::hostname(), 250);
            }

            if ($user !== '') {
                self::cmd($sock, 'AUTH LOGIN', 334);
                self::cmd($sock, base64_encode($user), 334);
                self::cmd($sock, base64_encode($pass), 235);
            }

            self::cmd($sock, 'MAIL FROM:<' . config('mail.from_address') . '>', 250);
            self::cmd($sock, 'RCPT TO:<' . $to . '>', 250);
            self::cmd($sock, 'DATA', 354);

            $boundary = 'vin-' . bin2hex(random_bytes(12));

            $message = implode("\r\n", [
                'From: ' . self::fromHeader(),
                'To: <' . $to . '>',
                'Subject: ' . self::encodeHeader($subject),
                'Date: ' . date('r'),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . self::hostname() . '>',
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                '',
                self::multipartBody($boundary, $html, $text),
            ]);

            // Dấu chấm đầu dòng phải nhân đôi, nếu không một dòng chỉ có "."
            // trong nội dung sẽ kết thúc thư giữa chừng (RFC 5321).
            $message = preg_replace('/^\./m', '..', $message) ?? $message;

            fwrite($sock, $message . "\r\n.\r\n");
            self::expect($sock, 250);

            self::cmd($sock, 'QUIT', 221);

            return true;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();

            return false;
        } finally {
            @fclose($sock);
        }
    }

    // ========================================================================
    // NỘI BỘ
    // ========================================================================

    /** Gửi một lệnh, đòi mã trả lời đúng như mong đợi. */
    private static function cmd($sock, string $line, int $expect): string
    {
        fwrite($sock, $line . "\r\n");

        return self::expect($sock, $expect);
    }

    /**
     * Đọc câu trả lời của máy chủ (có thể nhiều dòng) và kiểm mã.
     *
     * SMTP trả nhiều dòng theo dạng "250-…" cho dòng giữa và "250 …" cho dòng
     * cuối — dấu cách ở vị trí thứ tư là thứ phân biệt.
     */
    private static function expect($sock, int $code): string
    {
        $all = '';

        while (($line = fgets($sock, 8192)) !== false) {
            $all .= $line;

            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        if ($all === '') {
            throw new RuntimeException('Máy chủ SMTP không trả lời (có thể bị chặn cổng).');
        }

        if ((int) substr($all, 0, 3) !== $code) {
            throw new RuntimeException('SMTP trả về: ' . trim($all));
        }

        return $all;
    }

    private static function multipartBody(string $boundary, string $html, string $text): string
    {
        return implode("\r\n", [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($text)),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($html)),
            '--' . $boundary . '--',
            '',
        ]);
    }

    private static function fromHeader(): string
    {
        return sprintf('%s <%s>',
            self::encodeHeader((string) config('mail.from_name', 'Vin Eyewear')),
            (string) config('mail.from_address'));
    }

    /**
     * Tiêu đề tiếng Việt phải mã hoá: header của email chỉ được chứa ASCII.
     * Không mã hoá thì "Đặt lại mật khẩu" tới nơi thành một chuỗi rác.
     */
    private static function encodeHeader(string $text): string
    {
        return preg_match('/[\x80-\xFF]/', $text) === 1
            ? '=?UTF-8?B?' . base64_encode($text) . '?='
            : $text;
    }

    private static function hostname(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    private static function htmlToText(string $html): string
    {
        // Giữ lại đường dẫn của các liên kết: bản chữ thuần mà mất link thì
        // khách đọc bằng ứng dụng mail chặn HTML sẽ không bấm được gì.
        $s = preg_replace('/<a\b[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $html) ?? $html;
        $s = preg_replace('/<(br|\/p|\/div|\/h[1-6])[^>]*>/i', "\n", $s) ?? $s;
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('/[ \t]+/', ' ', $s) ?? $s;
        $s = preg_replace('/\n{3,}/', "\n\n", $s) ?? $s;

        return trim($s);
    }
}
