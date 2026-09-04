<?php

/**
 * core/helpers.php
 *
 * Các hàm tiện ích dùng chung toàn site. File này được nạp sớm nhất trong
 * App::boot() nên mọi nơi khác (config, model, controller, view) đều gọi được.
 *
 * Quy ước đặt tên: hàm ngắn, không tiền tố, vì đây là tầng thấp nhất và
 * được gọi rất dày trong view (e(), money(), asset()...).
 */

// ============================================================================
// BIẾN MÔI TRƯỜNG
// ============================================================================

/**
 * Đọc file .env vào một mảng tĩnh.
 *
 * Cố tình KHÔNG dùng putenv()/$_ENV: trên nhiều hosting PHP-FPM dùng chung,
 * biến môi trường của tiến trình bị rò rỉ giữa các site trên cùng máy chủ.
 * Giữ trong biến static của hàm thì phạm vi gói gọn trong một request.
 *
 * Định dạng hỗ trợ: KEY=value, bỏ qua dòng trống và dòng bắt đầu bằng '#'.
 * Giá trị có thể bọc trong nháy đơn/kép — nháy sẽ được bóc ra.
 */
function env(string $key, mixed $default = null): mixed
{
    static $vars = null;

    if ($vars === null) {
        $vars = [];
        $path = ROOT_PATH . '/.env';

        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);

                // Bỏ qua chú thích và dòng không có dấu '='
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);
                $name  = trim($name);
                $value = trim($value);

                // Bóc nháy bao ngoài nếu có: DB_PASS="a b c" -> a b c
                if (strlen($value) >= 2) {
                    $first = $value[0];
                    $last  = $value[strlen($value) - 1];
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $value = substr($value, 1, -1);
                    }
                }

                $vars[$name] = $value;
            }
        }
    }

    if (!array_key_exists($key, $vars)) {
        return $default;
    }

    $value = $vars[$key];

    // Chuỗi trong .env luôn là text; quy đổi các giá trị đặc biệt sang kiểu PHP
    // để `if (env('APP_DEBUG'))` không bị chuỗi "false" đánh lừa thành true.
    return match (strtolower($value)) {
        'true', '(true)'   => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        ''                 => $default,
        default            => $value,
    };
}

/**
 * Đọc giá trị cấu hình theo ký hiệu chấm: config('database.host').
 * File cấu hình nằm ở config/<tên>.php và trả về một mảng.
 */
function config(string $key, mixed $default = null): mixed
{
    static $loaded = [];

    $segments = explode('.', $key);
    $file     = array_shift($segments);

    if (!isset($loaded[$file])) {
        $path = CONFIG_PATH . '/' . $file . '.php';
        $loaded[$file] = is_readable($path) ? require $path : [];
    }

    $value = $loaded[$file];

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

// ============================================================================
// XUẤT RA HTML
// ============================================================================

/**
 * Escape chuỗi trước khi in ra HTML. Dùng ở MỌI chỗ in dữ liệu từ DB
 * hoặc từ người dùng: <?= e($product['name']) ?>
 *
 * ENT_QUOTES xử lý cả ' lẫn " nên dùng được trong thuộc tính HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Định dạng tiền VND: 2890000 -> "2.890.000₫"
 *
 * Dùng dấu chấm phân cách nghìn theo chuẩn Việt Nam (khác chuẩn Anh - Mỹ),
 * và ký hiệu ₫ đặt sau số.
 */
function money(int|float|string|null $amount): string
{
    if ($amount === null || $amount === '') {
        return '—';
    }

    return number_format((float) $amount, 0, ',', '.') . '₫';
}

/**
 * Phần trăm giảm giá, làm tròn xuống: (2890000, 3500000) -> 17
 * Trả về null khi không có giá gốc hoặc giá gốc không cao hơn giá bán,
 * để view chỉ cần `if ($pct = discount(...))` là biết có hiện nhãn sale hay không.
 */
function discount(int|float|null $price, int|float|null $compareAt): ?int
{
    if (empty($compareAt) || empty($price) || $compareAt <= $price) {
        return null;
    }

    return (int) floor((($compareAt - $price) / $compareAt) * 100);
}

/**
 * Đường dẫn tới file tĩnh, kèm tham số chống cache theo thời điểm sửa file.
 *
 * Trình duyệt giữ CSS/JS trong cache rất lâu; thêm ?v=<mtime> khiến URL đổi
 * mỗi khi file đổi, nên người dùng luôn nhận bản mới mà không phải Ctrl+F5.
 */
function asset(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $file = ROOT_PATH . $path;

    if (is_file($file)) {
        return $path . '?v=' . filemtime($file);
    }

    return $path;
}

/**
 * Ảnh của bản thiết kế nếu đã có trong assets/images/home/, không thì ảnh dự phòng.
 *
 * Bản thiết kế "Vin Eyewear Home.dc.html" gắn ảnh vào từng ô `<image-slot>`
 * theo id (hero-photo, cat-gong, store-photo…). Những tấm đó nằm trong dự án
 * Claude Design chứ không nằm trong repo, và phải tải về tay — xem
 * assets/images/home/README.md để biết tên file phải đặt là gì.
 *
 * Hàm này để trang chủ chạy được ở CẢ HAI trạng thái: chưa có ảnh thiết kế
 * thì dùng ảnh cũ trong repo, thả file đúng tên vào là tự đổi, không phải
 * sửa view. Thử lần lượt .webp → .jpg → .png vì mỗi ô tải về một định dạng
 * khác nhau tuỳ lúc tạo.
 *
 * @param string $slot     id của ô ảnh trong bản thiết kế, không kèm đuôi
 * @param string $fallback đường dẫn ảnh dùng khi chưa có ảnh thiết kế
 */
function designImage(string $slot, string $fallback): string
{
    foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
        $path = '/assets/images/home/' . $slot . '.' . $ext;

        if (is_file(ROOT_PATH . $path)) {
            return asset($path);
        }
    }

    return asset($fallback);
}

// ============================================================================
// VIEW
// ============================================================================

/**
 * Nạp một view con trong PHẠM VI RIÊNG.
 *
 *     partial('_layout/page-head', ['head_title' => 'Chính sách']);
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO PHẢI CÓ HÀM NÀY, KHÔNG DÙNG require THẲNG
 *
 * `require` chèn file vào ĐÚNG phạm vi biến của nơi gọi. Mọi biến file con
 * đặt ra sẽ đè lên biến trùng tên của file cha, âm thầm.
 *
 * Đã dính thật: mega-menu.php đặt $groups (các nhóm của menu). master.php nạp
 * header.php — kéo theo mega-menu.php — TRƯỚC khi nạp view. Tới lượt view
 * trang chính sách đọc $groups thì đó đã là dữ liệu menu, không còn là 5 nhóm
 * chính sách; cả phần thân trang biến mất mà không có lỗi nào rõ ràng.
 *
 * Đặt trong thân hàm thì biến của file con là biến cục bộ của hàm, hết hàm là
 * hết. File con chỉ thấy đúng những gì được truyền qua $data.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * @param string $name đường dẫn tương đối trong app/views, không có đuôi .php
 * @param array  $data biến truyền vào view con
 */
function partial(string $name, array $data = []): void
{
    $__path = VIEWS_PATH . '/' . $name . '.php';

    if (!is_file($__path)) {
        if (config('app.debug')) {
            throw new RuntimeException("Không tìm thấy view con: {$name}");
        }
        error_log("[partial] Thiếu file view: {$__path}");
        return;
    }

    // EXTR_SKIP: không cho $data ghi đè $__path, nếu không require sẽ trỏ sai file
    extract($data, EXTR_SKIP);

    require $__path;
}

// ============================================================================
// NGÀY THÁNG
// ============================================================================

/**
 * Định dạng ngày kiểu Việt Nam: "2026-08-20 09:00:00" -> "20/08/2026"
 * Trả về chuỗi rỗng nếu giá trị trống hoặc không phân tích được, để view
 * không phải kiểm tra null trước khi gọi.
 */
function formatDate(?string $datetime, string $format = 'd/m/Y'): string
{
    if (empty($datetime)) {
        return '';
    }

    $timestamp = strtotime($datetime);

    return $timestamp === false ? '' : date($format, $timestamp);
}

/* dateRange() ĐÃ BỎ 2026-08-26. Nó ghép "20/08/2026 – 31/08/2026" cho khoảng
   ngày của một bài sự kiện, và cả bốn nơi gọi (trang danh sách, trang chi
   tiết, dòng nhắc ở /dat-lich, thẻ kết quả tìm kiếm) đều đi cùng tính năng sự
   kiện. Bỏ luôn thay vì để lại: một helper không ai gọi thì lần sau có người
   sửa formatDate() sẽ phải cân nhắc cả nó mà không biết nó dùng ở đâu. */

// ============================================================================
// CHUỖI
// ============================================================================

// ============================================================================
// NGÔN NGỮ — ĐÃ GỠ 2026-08-30
//
// Ở đây từng có LANG_CODES, LANG_DEFAULT, LANG_COOKIE, currentLang() và t():
// một hệ dịch viết tay đọc bảng chuỗi trong config/lang/. Nó chỉ phủ được
// KHUNG giao diện, nên nội dung trang và dữ liệu CSDL vẫn tiếng Việt kể cả khi
// khách đã chọn English.
//
// SITE HIỆN CHỈ CÓ TIẾNG VIỆT. Không còn hàm nào cho ngôn ngữ, và đó là
// trạng thái đã chọn chứ không phải việc bỏ dở.
//
// Giữa 30/08/2026 có thử một widget dịch của bên thứ ba (Elfsight) thay cho
// hệ này, và đã gỡ trong cùng ngày. Lý do đáng ghi lại vì nó áp cho MỌI widget
// dịch chạy phía trình duyệt, không riêng Elfsight:
//
//   · Bản tiếng Anh sinh ra sau khi DOM đã dựng, không có URL riêng, không có
//     hreflang — Google không lập chỉ mục được. Với một site bán hàng, index
//     được bản dịch chính là toàn bộ lý do làm song ngữ.
//   · Một script bên thứ ba gánh chức năng cốt lõi. Khác hẳn Google Fonts:
//     font hỏng thì trang xấu, dịch hỏng thì mất hẳn một ngôn ngữ.
//   · Bản miễn phí có trần lượt xem hàng tháng, vượt là widget tự tắt.
//
// LÀM SONG NGỮ THẬT thì phải là máy chủ dựng: mỗi ngôn ngữ một URL, hreflang
// đầy đủ, và chuỗi dịch cho cả nội dung trang lẫn dữ liệu CSDL. Đó là làm lại
// từ đầu, không phải hồi sinh t() — hàm cũ chỉ phủ được khung giao diện và
// cũng dùng chung một URL cho cả hai ngôn ngữ, tức là cũng không index được.
// ============================================================================

/**
 * Bảng bỏ dấu tiếng Việt: ký tự đích => mọi biến thể có dấu của nó.
 *
 * Liệt kê CẢ HOA LẪN THƯỜNG thay vì hạ chữ thường trước. Lý do: hạ chữ thường
 * cho ký tự Unicode cần mb_strtolower(), mà extension mbstring không phải máy
 * chủ nào cũng bật (máy dev của dự án này đang thiếu). Gộp cả hai dạng vào
 * bảng tra thì slugify() chỉ còn cần strtolower() cho phần ASCII.
 */
const VN_ACCENT_MAP = [
    'a' => 'àáạảãâầấậẩẫăằắặẳẵÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴ',
    'e' => 'èéẹẻẽêềếệểễÈÉẸẺẼÊỀẾỆỂỄ',
    'i' => 'ìíịỉĩÌÍỊỈĨ',
    'o' => 'òóọỏõôồốộổỗơờớợởỡÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠ',
    'u' => 'ùúụủũưừứựửữÙÚỤỦŨƯỪỨỰỬỮ',
    'y' => 'ỳýỵỷỹỲÝỴỶỸ',
    'd' => 'đĐ',
];

/**
 * Chuyển tiêu đề tiếng Việt thành slug URL:
 *   "Gọng kính Titan Vin T01" -> "gong-kinh-titan-vin-t01"
 *
 * Bỏ dấu bằng bảng tra thay vì iconv()//TRANSLIT: iconv phụ thuộc locale của
 * máy chủ, cùng một chuỗi có thể ra kết quả khác nhau giữa máy dev và server.
 */
function slugify(string $text): string
{
    $text = trim($text);

    foreach (VN_ACCENT_MAP as $plain => $accented) {
        // Tách chuỗi ký tự có dấu thành từng ký tự UTF-8 rồi thay đồng loạt.
        // str_replace() làm việc trên byte, nhưng ký tự UTF-8 nhiều byte vẫn
        // an toàn vì không ký tự nào là tiền tố byte của ký tự khác.
        $chars = preg_split('//u', $accented, -1, PREG_SPLIT_NO_EMPTY);
        $text  = str_replace($chars, $plain, $text);
    }

    // Sau khi bỏ dấu, phần còn lại chỉ còn ASCII nên strtolower() là đủ
    $text = strtolower($text);

    // Gom mọi ký tự không phải chữ/số thành một dấu gạch ngang
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);

    return trim($text, '-');
}

/**
 * Đếm số KÝ TỰ (không phải byte) của chuỗi UTF-8.
 *
 * Dùng PCRE thay cho mb_strlen() để không phụ thuộc extension mbstring:
 * strlen('Gọng') trả 5 vì 'ọ' chiếm 3 byte, trong khi ta cần 4.
 */
function utf8Length(string $text): int
{
    return preg_match_all('/./us', $text);
}

/**
 * Cắt chuỗi UTF-8 theo ký tự, thay cho mb_substr().
 */
function utf8Substr(string $text, int $start, ?int $length = null): string
{
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    return implode('', array_slice($chars, $start, $length));
}

/**
 * Bảng chữ HOA -> chữ THƯỜNG cho 67 chữ cái tiếng Việt có dấu.
 *
 * strtolower() chỉ hạ được A–Z; mọi chữ có dấu nó để nguyên (nó làm việc trên
 * byte, mà 'Ơ' là hai byte). Bảng này bù đúng phần còn thiếu đó.
 *
 * KHÁC VN_ACCENT_MAP ở trên: bảng kia BỎ DẤU để dựng slug ('Ơ' -> 'o'), bảng
 * này GIỮ DẤU và chỉ đổi hoa thành thường ('Ơ' -> 'ơ'). Không dùng lẫn được:
 * ô lọc thương hiệu so chuỗi với `String.prototype.toLowerCase()` bên JS, vốn
 * giữ nguyên dấu — bỏ dấu ở một vế thì gõ "Ơ" không khớp gì cả.
 */
const VN_LOWER_MAP = [
    'À' => 'à', 'Á' => 'á', 'Ạ' => 'ạ', 'Ả' => 'ả', 'Ã' => 'ã',
    'Â' => 'â', 'Ầ' => 'ầ', 'Ấ' => 'ấ', 'Ậ' => 'ậ', 'Ẩ' => 'ẩ', 'Ẫ' => 'ẫ',
    'Ă' => 'ă', 'Ằ' => 'ằ', 'Ắ' => 'ắ', 'Ặ' => 'ặ', 'Ẳ' => 'ẳ', 'Ẵ' => 'ẵ',
    'È' => 'è', 'É' => 'é', 'Ẹ' => 'ẹ', 'Ẻ' => 'ẻ', 'Ẽ' => 'ẽ',
    'Ê' => 'ê', 'Ề' => 'ề', 'Ế' => 'ế', 'Ệ' => 'ệ', 'Ể' => 'ể', 'Ễ' => 'ễ',
    'Ì' => 'ì', 'Í' => 'í', 'Ị' => 'ị', 'Ỉ' => 'ỉ', 'Ĩ' => 'ĩ',
    'Ò' => 'ò', 'Ó' => 'ó', 'Ọ' => 'ọ', 'Ỏ' => 'ỏ', 'Õ' => 'õ',
    'Ô' => 'ô', 'Ồ' => 'ồ', 'Ố' => 'ố', 'Ộ' => 'ộ', 'Ổ' => 'ổ', 'Ỗ' => 'ỗ',
    'Ơ' => 'ơ', 'Ờ' => 'ờ', 'Ớ' => 'ớ', 'Ợ' => 'ợ', 'Ở' => 'ở', 'Ỡ' => 'ỡ',
    'Ù' => 'ù', 'Ú' => 'ú', 'Ụ' => 'ụ', 'Ủ' => 'ủ', 'Ũ' => 'ũ',
    'Ư' => 'ư', 'Ừ' => 'ừ', 'Ứ' => 'ứ', 'Ự' => 'ự', 'Ử' => 'ử', 'Ữ' => 'ữ',
    'Ỳ' => 'ỳ', 'Ý' => 'ý', 'Ỵ' => 'ỵ', 'Ỷ' => 'ỷ', 'Ỹ' => 'ỹ',
    'Đ' => 'đ',
];

/**
 * Hạ chữ thường cho chuỗi UTF-8 tiếng Việt, thay cho mb_strtolower().
 *
 * strtr() làm việc trên byte nhưng vẫn an toàn với UTF-8: không ký tự nào
 * trong bảng là tiền tố byte của ký tự khác, nên không thể khớp nhầm vào giữa
 * chừng một ký tự nhiều byte. Cùng lý lẽ với str_replace() trong slugify().
 *
 * Phần ASCII để strtolower() lo — nó nhanh hơn hẳn và không đụng byte ≥ 0x80.
 */
function utf8Lower(string $text): string
{
    return strtolower(strtr($text, VN_LOWER_MAP));
}

/**
 * Cắt bớt văn bản dài, cắt theo ranh giới từ để không đứt giữa chữ.
 */
function excerpt(?string $text, int $limit = 160, string $suffix = '…'): string
{
    $text = trim(strip_tags($text ?? ''));

    if ($text === '' || utf8Length($text) <= $limit) {
        return $text;
    }

    $cut = utf8Substr($text, 0, $limit);

    // Lùi về khoảng trắng cuối cùng để không cắt đứt giữa một từ.
    // strrpos() dùng được ở đây vì dấu cách là ASCII (1 byte), không thể
    // trùng với byte giữa chừng của một ký tự UTF-8 nhiều byte.
    $space = strrpos($cut, ' ');

    if ($space !== false) {
        $cut = substr($cut, 0, $space);
    }

    return rtrim($cut, ' ,.;:-') . $suffix;
}

// ============================================================================
// ĐỊNH DANH
// ============================================================================

/**
 * Sinh UUID v4 để làm khoá chính, thay cho gen_random_uuid() của Postgres.
 *
 * Sinh ở tầng PHP (không dùng DEFAULT (UUID()) của MySQL) vì cần biết id
 * NGAY TRƯỚC khi INSERT: đơn hàng phải có sẵn id để chèn order_items trong
 * cùng một transaction, không thể đợi lastInsertId() (vốn chỉ dùng được với
 * khoá AUTO_INCREMENT, không áp dụng cho CHAR(36)).
 */
function uuid(): string
{
    $data = random_bytes(16);

    // Đặt bit phiên bản (4) và bit variant (10xx) theo RFC 4122
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Sinh mã đơn hàng / mã lịch hẹn dễ đọc cho khách: "DH-260814-A3F2".
 *
 * Có phần ngẫu nhiên ở đuôi để mã không đoán được — nếu chỉ đánh số tăng dần
 * thì ai cũng có thể dò đơn của người khác qua URL tra cứu.
 */
function generateCode(string $prefix): string
{
    $random = strtoupper(bin2hex(random_bytes(2)));

    return sprintf('%s-%s-%s', $prefix, date('ymd'), $random);
}

// ============================================================================
// ĐIỀU HƯỚNG & PHIÊN
// ============================================================================

/**
 * Chuyển hướng rồi dừng hẳn script.
 *
 * exit là bắt buộc: header() chỉ xếp hàng đợi header, PHP vẫn chạy tiếp phần
 * code phía dưới. Thiếu exit thì một trang "đã redirect" vẫn có thể lỡ tay
 * chạy tiếp lệnh xoá/ghi dữ liệu.
 */
function redirect(string $url, int $status = 302): never
{
    /*
     * MẶC ĐỊNH 302 vì gần hết chỗ gọi là chuyển hướng SAU MỘT THAO TÁC (đặt
     * hàng xong, đăng nhập xong, đổi ngôn ngữ) — địa chỉ cũ vẫn còn nghĩa, chỉ
     * là lần này đi tiếp chỗ khác.
     *
     * 301 dành cho ĐỔI ĐỊA CHỈ VĨNH VIỄN, và phải cân nhắc trước khi dùng:
     * trình duyệt CACHE 301 rất lâu, có bản còn không hỏi lại server cho tới
     * khi người dùng xoá dữ liệu duyệt web. Đặt nhầm 301 lên một đường tạm là
     * tự khoá mình, sửa mã cũng không gỡ được cho những người đã ghé.
     */
    http_response_code($status);
    header('Location: ' . $url);
    exit;
}

/**
 * Đường dẫn tới trang danh mục sản phẩm.
 *
 *   danhMucUrl('gong-kinh')  ->  /san-pham/gong-kinh      (có trang con)
 *   danhMucUrl('kinh-mat')   ->  /san-pham?category=kinh-mat
 *
 * VÌ SAO CẦN MỘT HÀM CHO MỘT DÒNG NỐI CHUỖI: bốn chỗ trong site dựng liên kết
 * danh mục từ một vòng lặp (chân trang, mega menu, mega menu mobile, khối danh
 * mục ngoài trang chủ) cộng hai chỗ gõ cứng slug. Không gom lại thì mỗi lần
 * một danh mục được lên trang con là sáu chỗ phải nhớ sửa, và chỗ nào quên sẽ
 * đẻ ra một chuyển hướng 301 thừa ở mỗi cú bấm — chạy đúng nên không ai phát
 * hiện ra.
 *
 * Danh sách slug có trang con nằm ở ProductController::SUB_PAGES; đọc qua
 * hằng đó chứ không chép lại ở đây.
 */
function danhMucUrl(string $slug): string
{
    return in_array($slug, ProductController::SUB_PAGES, true)
        ? '/san-pham/' . rawurlencode($slug)
        : '/san-pham?category=' . rawurlencode($slug);
}

/**
 * Thông báo một lần (flash): lưu vào session, đọc ra là tự xoá.
 * Dùng để báo "Đặt hàng thành công" sau khi redirect.
 *
 *   flash('success', 'Đã lưu');           // đặt
 *   $msg = flash('success');              // đọc + xoá
 */
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $value;
}

/**
 * Sinh (hoặc lấy lại) token CSRF của phiên hiện tại.
 *
 * Mọi form POST phải nhúng: <input type="hidden" name="_token" value="<?= csrfToken() ?>">
 * Không có nó, người khác có thể dựng form trên site của họ khiến trình duyệt
 * đang đăng nhập của khách gửi lệnh sang đây mà khách không hay biết.
 */
function csrfToken(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

/**
 * Đối chiếu token CSRF do form gửi lên.
 *
 * hash_equals() so sánh trong thời gian không đổi — so bằng '==' sẽ dừng ngay
 * ở ký tự đầu khác nhau, để lộ độ dài phần trùng khớp qua thời gian phản hồi.
 */
function csrfCheck(?string $token): bool
{
    return !empty($_SESSION['_csrf'])
        && is_string($token)
        && hash_equals($_SESSION['_csrf'], $token);
}

/**
 * Chỉ cho phép chuyển hướng tới đường dẫn nội bộ.
 *
 * Giá trị đến từ ?redirect= trên URL, tức là do người ngoài kiểm soát. Nếu
 * nhận bừa, kẻ tấn công gửi link /auth?redirect=https://site-gia.example —
 * khách đăng nhập xong bị đá sang trang giả mạo mà vẫn tưởng còn ở site này.
 * Chỉ chấp nhận chuỗi bắt đầu bằng đúng một dấu '/'.
 */
function safeRedirectPath(?string $path, string $fallback = '/'): string
{
    if (!is_string($path) || $path === '') {
        return $fallback;
    }

    // '//example.com' là URL giao thức tương đối -> vẫn dẫn ra ngoài
    if ($path[0] !== '/' || str_starts_with($path, '//')) {
        return $fallback;
    }

    return $path;
}

/**
 * Đường dẫn (không kèm query string) của request hiện tại.
 */
function currentPath(): string
{
    return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
}

/**
 * URL của request hiện tại (đường dẫn + query), sau khi BỎ một số tham số.
 *
 * Dùng cho hai việc trong luồng "Chọn hình thức mua":
 *   ô ẩn `back` của form thêm giỏ  — chỗ cần quay về sau khi chọn xong
 *   nút ✕ và nền mờ của hộp thoại  — chính trang này, nhưng đã đóng hộp thoại
 *
 * Vì sao không dùng currentPath() cho hai việc đó: nó cắt mất query, nên đóng
 * hộp thoại khi đang ở /san-pham?category=gong-kinh&sort=price sẽ ném khách về
 * danh sách chưa lọc. Còn giữ nguyên query thì tham số ?mua= ở lại và hộp
 * thoại mở lại ngay lập tức — nên phải bỏ ĐÚNG những tham số của nó.
 *
 * @param string[] $drop tên tham số cần bỏ
 */
function currentUrlWithout(array $drop = []): string
{
    parse_str($_SERVER['QUERY_STRING'] ?? '', $params);

    foreach ($drop as $key) {
        unset($params[$key]);
    }

    $query = http_build_query($params);

    return currentPath() . ($query === '' ? '' : '?' . $query);
}

/**
 * Đoạn đầu tiên của đường dẫn: '/product/detail' -> 'product'.
 * Dùng để đánh dấu mục nav đang mở, kể cả ở route con.
 */
function currentSegment(): string
{
    return explode('/', trim(currentPath(), '/'))[0];
}

/**
 * Chuẩn hoá số điện thoại Việt Nam về DUY NHẤT một dạng: 0 + 9 hoặc 10 chữ số.
 *
 * Cùng một thuê bao được người ta gõ ra rất nhiều kiểu, và tất cả đều đúng
 * theo cảm nhận của họ:
 *
 *     0912 345 678      +84 912 345 678      84912345678
 *     0912.345.678      (+84) 912-345-678    0912345678
 *
 * Nếu lưu nguyên văn thì cùng một người đăng ký hai lần ra hai tài khoản, và
 * đăng nhập bằng số sẽ hên xui theo đúng cách gõ lúc đăng ký. Nên MỌI chỗ ghi
 * số điện thoại xuống cơ sở dữ liệu đều phải đi qua hàm này trước.
 *
 * Trả về null khi không phải số Việt Nam hợp lệ — người gọi tự quyết định đó
 * là lỗi nhập liệu hay chỉ là ô để trống.
 *
 * Đầu số hợp lệ (theo quy hoạch sau đợt chuyển đổi 11 -> 10 số):
 *   03, 05, 07, 08, 09  di động, tổng 10 chữ số
 *
 * SỐ CỐ ĐỊNH (đầu 02) KHÔNG CÒN ĐƯỢC NHẬN — BA chốt ngày 04/09/2026, câu Q8.
 *
 * Trước đây hàm này nhận cả 02x. Nó rộng hơn quy tắc SRS mục 3.2.1.1 (chỉ nêu
 * 03/05/07/08/09), và cái rộng đó không vô hại: số điện thoại là kênh xác thực
 * DUY NHẤT của tài khoản khách — Zalo OTP gửi tới đó, và đơn hàng gọi tới đó.
 * Máy bàn không nhận được tin Zalo, nên một tài khoản đăng ký bằng số cố định
 * là một tài khoản KHÔNG BAO GIỜ kích hoạt được, và cũng không đặt lại được
 * mật khẩu. Nhận vào rồi chặn ở bước sau là để người ta gõ xong mới biết hỏng.
 *
 * Số cố định vẫn NHẬP ĐƯỢC ở những chỗ không dùng để đăng nhập — số điện thoại
 * cửa hàng trong cấu hình, số người nhận trên đơn giao hàng — vì các chỗ đó
 * không gọi hàm này để kiểm tính hợp lệ của một danh tính.
 */
function normalizePhone(?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }

    // Bỏ mọi thứ không phải chữ số, trừ dấu + ở ngay đầu chuỗi
    $s = trim($raw);
    $hasPlus = str_starts_with($s, '+');
    $digits  = preg_replace('/\D+/', '', $s) ?? '';

    if ($digits === '') {
        return null;
    }

    // +84xxxxxxxxx hoặc 84xxxxxxxxx -> 0xxxxxxxxx
    //
    // Chỉ cắt tiền tố 84 khi phần còn lại KHÔNG bắt đầu bằng 0. "840..." là
    // số nội địa bắt đầu bằng 84 chứ không phải mã quốc gia — không có đầu số
    // nội địa nào là 84 nên trường hợp này chỉ xảy ra khi người dùng gõ nhầm,
    // nhưng cắt bừa thì biến số sai thành số của người khác.
    if (str_starts_with($digits, '84') && !str_starts_with($digits, '840')) {
        if ($hasPlus || strlen($digits) === 11 || strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }
    }

    // Thiếu số 0 đầu (hay gặp khi chép từ Excel, Excel nuốt số 0 dẫn đầu)
    if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
        $digits = '0' . $digits;
    }

    // CHỈ di động. Xem khối chú thích trên về việc bỏ đầu số 02.
    return preg_match('/^0[35789]\d{8}$/', $digits) ? $digits : null;
}

/**
 * Chuẩn hoá số điện thoại LIÊN HỆ — nhận cả số cố định.
 *
 * VÌ SAO CẦN HÀM THỨ HAI. Ngày 04/09/2026 normalizePhone() bị siết chỉ còn
 * nhận di động (Quyết định Q8), và đó là đúng cho DANH TÍNH: số đăng nhập
 * phải nhận được Zalo OTP. Nhưng cùng một hàm ấy còn được gọi ở chỗ hoàn toàn
 * khác — SỐ NGƯỜI NHẬN HÀNG trong sổ địa chỉ. Số đó không đăng nhập, không
 * nhận OTP; nó để shipper gọi. Giao hàng tới văn phòng và để lại số bàn của
 * lễ tân là chuyện bình thường, và chặn nó đi là siết nhầm chỗ: khách mất một
 * cách giao hàng mà chẳng đổi lấy được điều gì về bảo mật.
 *
 * Cùng một phép chuẩn hoá dạng (+84 -> 0, bỏ dấu cách và dấu chấm) nên hai
 * hàm cho ra cùng một chuỗi với cùng một số di động; chỉ khác ở tập số được
 * chấp nhận.
 *
 * Đầu số nhận thêm: 02 — cố định, tổng 10 hoặc 11 chữ số.
 */
function normalizeContactPhone(?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }

    // Di động thì đã xong: normalizePhone() lo trọn phần chuẩn hoá dạng.
    if (($mobile = normalizePhone($raw)) !== null) {
        return $mobile;
    }

    $s       = trim($raw);
    $hasPlus = str_starts_with($s, '+');
    $digits  = preg_replace('/\D+/', '', $s) ?? '';

    if ($digits === '') {
        return null;
    }

    if (str_starts_with($digits, '84') && !str_starts_with($digits, '840')) {
        if ($hasPlus || strlen($digits) === 11 || strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }
    }

    return preg_match('/^02\d{8,9}$/', $digits) ? $digits : null;
}

/**
 * Nhóm số điện thoại cho dễ đọc: "0868890120" -> "0868 890 120".
 *
 * CHỈ ĐỂ HIỂN THỊ. Số lưu trong CSDL vẫn là chuỗi liền do normalizePhone()
 * chuẩn hoá — thêm dấu cách vào đó là phá luôn khoá duy nhất trên cột
 * `profiles.phone` và làm hỏng việc đăng nhập bằng số.
 *
 * Vì sao đáng có: số điện thoại là thứ người ta ĐỌC LẠI TỪNG SỐ khi gọi cho
 * shipper hay đối chiếu với cửa hàng, và mười chữ số dính liền thì mắt phải tự
 * đếm để không đọc sót. Nhóm 4-3-3 là cách người Việt đọc số di động.
 *
 * Thứ không phải 10 chữ số bắt đầu bằng 0 thì TRẢ NGUYÊN VĂN: số cố định, số
 * có mã quốc gia (+84…), hay chuỗi khách gõ lạ — đoán cách nhóm cho chúng là
 * dễ tách sai hơn là để yên.
 */
function groupPhone(?string $phone): string
{
    $phone = trim((string) $phone);

    if (!preg_match('/^0\d{9}$/', $phone)) {
        return $phone;
    }

    return substr($phone, 0, 4) . ' ' . substr($phone, 4, 3) . ' ' . substr($phone, 7);
}

/**
 * Chuỗi người dùng nhập vào ô đăng nhập là số điện thoại hay email?
 *
 * Chỉ cần một phép đoán thô để biết nên tra cột nào; việc kiểm tính hợp lệ
 * do normalizePhone() và filter_var() làm.
 */
function looksLikePhone(string $login): bool
{
    return !str_contains($login, '@')
        && preg_match('/^[\d\s.\-()+]+$/', trim($login)) === 1;
}

/**
 * Mật khẩu mới có đạt yêu cầu không? Trả về câu báo lỗi, hoặc null nếu đạt.
 *
 * MỘT NƠI DUY NHẤT ĐỊNH NGHĨA "MẬT KHẨU HỢP LỆ" — dùng ở cả ba màn đặt mật
 * khẩu: đăng ký (AuthController::signupFinish), quên mật khẩu bằng mã OTP, và
 * đặt lại bằng liên kết của nhân viên (PasswordResetModel::applyNewPassword).
 * Trước đây bộ quy tắc nằm chép tay trong màn đăng ký, còn hai màn kia chỉ đòi
 * 8 ký tự — nghĩa là ai đi đường quên mật khẩu thì đặt được mật khẩu yếu hơn
 * mức site tự đặt ra cho mình, và chỗ yếu nhất mới là chỗ quyết định.
 *
 * Bốn dòng quy tắc in trên màn hình phải khớp với hàm này; chúng có ở
 * auth/_password-rules.php.
 *
 * strlen() đếm BYTE chứ không đếm ký tự — cố ý. Giới hạn của password_hash()
 * là 72 byte, và với mật khẩu thì "dài" nên hiểu theo byte: một chuỗi tiếng
 * Việt 8 chữ có dấu là 16-24 byte, đếm theo ký tự sẽ thả lọt thứ ngắn hơn
 * mức ta tưởng.
 */
function passwordProblem(string $password): ?string
{
    /*
     * BỐN ĐIỀU KIỆN + TRẦN 32 KÝ TỰ LÀ NGUYÊN VĂN SRS, KHÔNG PHẢI Ý CHÚNG TA.
     * UC-3.2.1.1 (Business rule) và SNFR-09 chốt: "Độ dài từ 8 - 32 ký tự,
     * chứa ít nhất 1 chữ hoa, 1 chữ thường, 1 số và 1 ký tự đặc biệt."
     *
     * Bản cũ thiếu vế ký tự đặc biệt và lấy trần 72 — 72 là giới hạn kỹ thuật
     * của bcrypt (nó cắt cụt từ byte thứ 73), không phải quy định nghiệp vụ.
     * Giữ 32 thì vừa đúng đặc tả vừa nằm an toàn dưới ngưỡng cắt cụt ấy.
     *
     * Đếm bằng utf8Length() chứ không phải strlen(): khách gõ mật khẩu có dấu
     * thì mỗi chữ cái ăn 2-3 byte, "Mật_khẩu1" mới 9 ký tự đã bị strlen tính
     * thành 13 — trần 32 sẽ chặn nhầm những mật khẩu hoàn toàn hợp lệ.
     *
     * utf8Length() CHỨ KHÔNG PHẢI mb_strlen(): máy chủ của cửa hàng không nạp
     * extension mbstring, gọi hàm mb_* là lỗi 500 — xem ghi chú ở utf8Length()
     * ngay trên trong file này. Hàm này nằm trên đường đăng ký, quên mật khẩu,
     * đổi mật khẩu và thêm nhân viên, nên một lỗi 500 ở đây là chặn cả bốn.
     *
     * ─────────────────────────────────────────────────────────────────────
     * HAI TRẦN ĐỘ DÀI, VÀ CẢ HAI ĐỀU CẦN
     *
     *   32 KÝ TỰ   luật nghiệp vụ, nguyên văn SNFR-09 và UC-3.2.1.1.
     *   72 BYTE    chốt kỹ thuật của bcrypt: password_hash() CẮT CỤT từ byte
     *              thứ 73 và không báo gì.
     *
     * Bỏ vế byte đi thì mật khẩu 32 ký tự tiếng Việt có dấu = 96 byte, và
     * bcrypt chỉ băm 72 byte đầu — tức gõ đúng 24 ký tự đầu là đăng nhập được,
     * còn mọi mật khẩu trùng 72 byte đầu thì tương đương nhau. Mật khẩu càng
     * dài, càng có dấu, càng yếu. Đã thử trên PHP 8.4 để chắc.
     *
     * Hai vế dùng CHUNG một câu báo lỗi: người dùng không cần biết bcrypt là
     * gì, và câu "tối đa 32 ký tự" là câu đã nghiệm thu theo SRS.
     * ─────────────────────────────────────────────────────────────────────
     *
     * Ký tự đặc biệt = mọi thứ không phải chữ cái ASCII và không phải chữ số.
     * Cố ý KHÔNG liệt kê một danh sách trắng: liệt kê thì bàn phím tiếng Việt
     * gõ ra ký tự ngoài danh sách sẽ bị báo sai một cách khó hiểu.
     */
    return match (true) {
        utf8Length($password) < 8                => 'Mật khẩu phải có ít nhất 8 ký tự.',
        utf8Length($password) > 32               => 'Mật khẩu quá dài (tối đa 32 ký tự).',
        strlen($password) > 72                   => 'Mật khẩu quá dài (tối đa 32 ký tự).',
        !preg_match('/[A-Z]/', $password)        => 'Mật khẩu phải có ít nhất một chữ hoa.',
        !preg_match('/[a-z]/', $password)        => 'Mật khẩu phải có ít nhất một chữ thường.',
        !preg_match('/[0-9]/', $password)        => 'Mật khẩu phải có ít nhất một chữ số.',
        !preg_match('/[^A-Za-z0-9]/', $password) => 'Mật khẩu phải có ít nhất một ký tự đặc biệt.',
        default                                  => null,
    };
}
