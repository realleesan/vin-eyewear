<?php

/**
 * core/GoogleAuth.php — đăng nhập bằng tài khoản Google (OAuth 2.0 + OpenID Connect).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DÙNG LUỒNG "AUTHORIZATION CODE", KHÔNG DÙNG NÚT JAVASCRIPT CỦA GOOGLE
 *
 * Google có sẵn thư viện JS vẽ nút và trả thẳng ID token về trình duyệt. Ở đây
 * không dùng, vì hai lẽ:
 *
 *   1. Nút đó KHÔNG chạy khi tắt JavaScript, mà cả site này dựng theo lối chạy
 *      được không cần JS — xem chú thích đầu assets/js/buy-flow.js. Luồng dưới
 *      đây chỉ là một thẻ <a> trỏ sang Google.
 *   2. Token đi qua trình duyệt là token do trình duyệt đưa cho ta. Ở luồng
 *      này, máy chủ TỰ đổi mã lấy token bằng một cú POST thẳng tới Google —
 *      thứ nhận về đến từ đúng nơi mình gọi, qua TLS mình tự mở.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VẪN KIỂM CHỮ KÝ ID TOKEN, DÙ NÓ ĐẾN TỪ CHÍNH GOOGLE
 *
 * Đặc tả OpenID Connect nói luồng code có thể bỏ qua bước kiểm chữ ký, vì token
 * lấy trực tiếp qua kênh TLS đã xác thực. Ở đây vẫn kiểm, vì bỏ qua nghĩa là
 * đặt toàn bộ niềm tin vào một dòng cấu hình CURLOPT_SSL_VERIFYPEER — một lần
 * ai đó tắt nó đi "cho chạy được trên máy dev" là mở toang cửa mà không ai
 * thấy. Kiểm chữ ký thì token giả không qua được, kể cả khi TLS đã hỏng.
 *
 * Khoá công khai lấy ở JWKS của Google và nhớ trong session theo `kid` — Google
 * xoay khoá vài ngày một lần, tải lại mỗi lần đăng nhập là một lượt mạng thừa.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CẤU HÌNH: GOOGLE_CLIENT_ID và GOOGLE_CLIENT_SECRET trong .env
 *
 * Chưa đặt thì isConfigured() trả false và trang đăng nhập KHÔNG vẽ nút Google
 * — thà không có nút còn hơn có một cái bấm vào ra trang lỗi của Google.
 */
class GoogleAuth
{
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const JWKS_URL  = 'https://www.googleapis.com/oauth2/v3/certs';

    /** Google tự nhận là ai trong ID token. Cả hai chuỗi đều hợp lệ. */
    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /** Lệch đồng hồ cho phép giữa máy chủ này và Google, tính bằng giây. */
    private const LEEWAY = 60;

    public static function isConfigured(): bool
    {
        return config('auth.google.client_id') !== ''
            && config('auth.google.client_secret') !== '';
    }

    /**
     * Địa chỉ để đẩy khách sang Google.
     *
     * $state là chuỗi ngẫu nhiên lưu trong session; Google trả lại nguyên văn
     * ở bước sau. Không có nó thì kẻ tấn công dụ được nạn nhân mở một địa chỉ
     * callback mang mã của CHÍNH KẺ ĐÓ, và nạn nhân lặng lẽ đăng nhập vào tài
     * khoản của kẻ tấn công — mọi thứ họ làm tiếp sau đó nằm trong tay người khác.
     */
    public static function authUrl(string $state, ?string $redirectAfter = null): string
    {
        $_SESSION['_google_state'] = $state;

        if ($redirectAfter !== null && $redirectAfter !== '') {
            $_SESSION['_google_after'] = $redirectAfter;
        }

        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => config('auth.google.client_id'),
            'redirect_uri'  => config('auth.google.redirect'),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            // Hỏi lại tài khoản mỗi lần: máy dùng chung là chuyện thường ở
            // Việt Nam, và mặc định của Google là đăng nhập thẳng bằng tài
            // khoản đang mở sẵn — người sau ngồi vào sẽ vào nhầm tài khoản
            // người trước mà không kịp thấy gì.
            'prompt'        => 'select_account',
        ]);
    }

    /**
     * Đổi mã Google trả về lấy thông tin tài khoản.
     *
     * @return array{ok:bool, error?:string, sub?:string, email?:?string, name?:?string, verified?:bool}
     */
    public static function exchange(string $code, string $state): array
    {
        $expected = (string) ($_SESSION['_google_state'] ?? '');
        unset($_SESSION['_google_state']);

        // hash_equals: so bằng '==' dừng ở ký tự đầu khác nhau, để lộ độ dài
        // phần trùng khớp qua thời gian phản hồi. Cùng lý do đã ghi ở install.php.
        if ($expected === '' || !hash_equals($expected, $state)) {
            return ['ok' => false, 'error' => 'Phiên đăng nhập Google không khớp — vui lòng thử lại.'];
        }

        $res = self::post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => config('auth.google.client_id'),
            'client_secret' => config('auth.google.client_secret'),
            'redirect_uri'  => config('auth.google.redirect'),
            'grant_type'    => 'authorization_code',
        ]);

        if ($res === null || !isset($res['id_token'])) {
            error_log('[Google] Đổi mã thất bại: ' . json_encode($res));

            return ['ok' => false, 'error' => 'Không lấy được thông tin từ Google. Vui lòng thử lại.'];
        }

        return self::readIdToken((string) $res['id_token']);
    }

    /**
     * Đọc và KIỂM ID token: chữ ký, người phát hành, đối tượng nhận, hạn dùng.
     */
    private static function readIdToken(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return ['ok' => false, 'error' => 'Token của Google không đúng định dạng.'];
        }

        [$rawHead, $rawBody, $rawSig] = $parts;

        $head = json_decode(self::b64($rawHead) ?? '', true);
        $body = json_decode(self::b64($rawBody) ?? '', true);
        $sig  = self::b64($rawSig);

        if (!is_array($head) || !is_array($body) || $sig === null) {
            return ['ok' => false, 'error' => 'Token của Google không đọc được.'];
        }

        if (($head['alg'] ?? '') !== 'RS256') {
            // Chặn "alg: none" và mọi thuật toán lạ. Đây là lỗ hổng kinh điển
            // của JWT: token tự khai mình không cần chữ ký, thư viện nào tin
            // theo là ai cũng đăng nhập được thành bất kỳ ai.
            return ['ok' => false, 'error' => 'Token của Google dùng thuật toán không được chấp nhận.'];
        }

        $key = self::publicKey((string) ($head['kid'] ?? ''));

        if ($key === null) {
            return ['ok' => false, 'error' => 'Không lấy được khoá công khai của Google.'];
        }

        $ok = openssl_verify($rawHead . '.' . $rawBody, $sig, $key, OPENSSL_ALGO_SHA256);

        if ($ok !== 1) {
            return ['ok' => false, 'error' => 'Chữ ký token của Google không hợp lệ.'];
        }

        if (!in_array((string) ($body['iss'] ?? ''), self::ISSUERS, true)) {
            return ['ok' => false, 'error' => 'Token không phải do Google phát hành.'];
        }

        // aud phải là CHÍNH ứng dụng này. Thiếu bước này thì một token Google
        // hợp lệ cấp cho ứng dụng khác cũng đăng nhập được vào đây.
        if ((string) ($body['aud'] ?? '') !== (string) config('auth.google.client_id')) {
            return ['ok' => false, 'error' => 'Token được cấp cho ứng dụng khác.'];
        }

        if ((int) ($body['exp'] ?? 0) + self::LEEWAY < time()) {
            return ['ok' => false, 'error' => 'Token của Google đã hết hạn — vui lòng thử lại.'];
        }

        $sub = (string) ($body['sub'] ?? '');

        if ($sub === '') {
            return ['ok' => false, 'error' => 'Token của Google thiếu định danh tài khoản.'];
        }

        return [
            'ok'       => true,
            'sub'      => $sub,
            'email'    => isset($body['email']) ? strtolower((string) $body['email']) : null,
            'name'     => isset($body['name']) ? (string) $body['name'] : null,
            // Google có thể trả email CHƯA xác minh (tài khoản Workspace tự
            // quản). Chỉ email đã xác minh mới được dùng để nối vào tài khoản
            // mật khẩu có sẵn — xem UserModel::findOrCreateGoogle().
            'verified' => !empty($body['email_verified']),
        ];
    }

    /** Khoá công khai theo `kid`, nhớ trong session cho lần sau. */
    private static function publicKey(string $kid): mixed
    {
        if ($kid === '') {
            return null;
        }

        $cached = $_SESSION['_google_jwks'] ?? null;

        if (!isset($cached[$kid])) {
            $jwks = self::get(self::JWKS_URL);

            if (!isset($jwks['keys']) || !is_array($jwks['keys'])) {
                return null;
            }

            $cached = [];

            foreach ($jwks['keys'] as $k) {
                if (isset($k['kid'], $k['n'], $k['e'])) {
                    $cached[(string) $k['kid']] = $k;
                }
            }

            $_SESSION['_google_jwks'] = $cached;
        }

        if (!isset($cached[$kid])) {
            return null;
        }

        return self::rsaKey(self::b64($cached[$kid]['n']) ?? '', self::b64($cached[$kid]['e']) ?? '');
    }

    /**
     * Dựng khoá công khai RSA từ hai số `n` và `e` của JWKS.
     *
     * PHP không có hàm nào nhận thẳng cặp số đó, nên phải gói lại thành DER
     * theo đúng khuôn SubjectPublicKeyInfo rồi bọc PEM. Dài dòng nhưng chỉ là
     * việc đóng gói — không có phép tính mật mã nào ở đây.
     */
    private static function rsaKey(string $n, string $e): mixed
    {
        $int = static function (string $bytes): string {
            // Bỏ byte 0 thừa ở đầu, rồi thêm lại một byte 0 nếu bit cao nhất
            // đang bật — DER hiểu số nguyên có dấu, thiếu byte đó thành số âm.
            $bytes = ltrim($bytes, "\x00");

            if ($bytes === '' || ord($bytes[0]) > 0x7f) {
                $bytes = "\x00" . $bytes;
            }

            return "\x02" . self::len(strlen($bytes)) . $bytes;
        };

        $seq    = $int($n) . $int($e);
        $seq    = "\x30" . self::len(strlen($seq)) . $seq;
        $bits   = "\x03" . self::len(strlen($seq) + 1) . "\x00" . $seq;
        $oid    = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $der    = "\x30" . self::len(strlen($oid) + strlen($bits)) . $oid . $bits;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END PUBLIC KEY-----\n";

        return openssl_pkey_get_public($pem) ?: null;
    }

    /** Độ dài theo lối DER. */
    private static function len(int $n): string
    {
        if ($n < 0x80) {
            return chr($n);
        }

        $out = '';

        while ($n > 0) {
            $out = chr($n & 0xff) . $out;
            $n >>= 8;
        }

        return chr(0x80 | strlen($out)) . $out;
    }

    /** base64url -> chuỗi byte, null khi hỏng. */
    private static function b64(string $s): ?string
    {
        $out = base64_decode(strtr($s, '-_', '+/'), true);

        return $out === false ? null : $out;
    }

    private static function get(string $url): ?array
    {
        return self::request($url, null);
    }

    private static function post(string $url, array $fields): ?array
    {
        return self::request($url, http_build_query($fields));
    }

    /**
     * Một lượt gọi HTTP, trả mảng đã giải mã JSON hoặc null.
     *
     * ĐI QUA cURL NẾU CÓ, KHÔNG THÌ DÙNG file_get_contents. Bản đầu chỉ có
     * cURL và chết bằng fatal error "Call to undefined function curl_init()"
     * trên bản PHP không bật extension đó — đo được ngay trên PHP CLI của máy
     * phát triển này. Hosting thật có cả hai (đã đo), nhưng một tính năng
     * đăng nhập không nên phụ thuộc vào một extension tuỳ chọn khi cách còn
     * lại đã bật sẵn.
     *
     * Cả hai đường đều KIỂM chứng chỉ TLS: cURL bật sẵn hai tuỳ chọn đó, còn
     * luồng https:// của PHP mặc định verify_peer = true. Không tắt ở đâu cả.
     */
    private static function request(string $url, ?string $body): ?array
    {
        $raw = extension_loaded('curl')
            ? self::viaCurl($url, $body)
            : self::viaStream($url, $body);

        if ($raw === null) {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    private static function viaCurl(string $url, ?string $body): ?string
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 6,
            // Hai dòng này là mặc định của cURL, viết rõ ra để người sửa sau
            // thấy chúng tồn tại — tắt đi là mọi thứ vẫn "chạy" mà không còn
            // gì đảm bảo đầu bên kia đúng là Google.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            error_log('[Google] cURL gọi ' . $url . ' lỗi: ' . $err);

            return null;
        }

        return (string) $raw;
    }

    private static function viaStream(string $url, ?string $body): ?string
    {
        if (!ini_get('allow_url_fopen')) {
            error_log('[Google] Máy chủ không có cURL và cũng tắt allow_url_fopen.');

            return null;
        }

        $http = ['timeout' => 10, 'ignore_errors' => true];

        if ($body !== null) {
            $http['method']  = 'POST';
            $http['header']  = "Content-Type: application/x-www-form-urlencoded\r\n";
            $http['content'] = $body;
        }

        $raw = @file_get_contents($url, false, stream_context_create([
            'http' => $http,
            // Viết rõ ra dù đây là mặc định — cùng lý do với hai dòng của cURL.
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]));

        if ($raw === false) {
            error_log('[Google] Gọi ' . $url . ' thất bại (stream).');

            return null;
        }

        return $raw;
    }
}
