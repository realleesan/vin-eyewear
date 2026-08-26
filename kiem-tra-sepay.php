<?php

/**
 * kiem-tra-sepay.php — CHẨN ĐOÁN TÍCH HỢP SEPAY. XOÁ SAU KHI BẬT XONG.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO CẦN FILE NÀY
 *
 * Webhook là đường DUY NHẤT trong site mà người gọi không phải trình duyệt của
 * ai cả. Hỏng ở đâu cũng không ai thấy: không có màn hình trắng, không có lỗi
 * đỏ, chỉ có tiền về tài khoản mà đơn nằm im ở 'unpaid'. Mà bốn nguyên nhân
 * hỏng đầu tiên đều nằm ở HOSTING chứ không nằm trong mã:
 *
 *     .env trên hosting thiếu SEPAY_WEBHOOK_KEY   -> webhook trả 403
 *     khoá hai bên lệch nhau                      -> 401
 *     chưa chạy migration                         -> 503
 *     Apache chạy PHP dưới CGI nuốt mất header    -> 401, không dấu vết
 *
 * InfinityFree không có SSH nên không chạy được `php -r` để tự dò. File này
 * thay việc đó: mở bằng trình duyệt là biết đang kẹt ở đâu.
 *
 * Cùng nếp với kiem-tra-db.php: text/plain, khoá bằng INSTALL_TOKEN, và
 * KHÔNG IN BÍ MẬT RA MÀN HÌNH.
 * ─────────────────────────────────────────────────────────────────────────────
 * KHOÁ ĐƯỢC IN DƯỚI DẠNG VÂN TAY, KHÔNG PHẢI NGUYÊN VĂN
 *
 * Câu hỏi cần trả lời là "khoá trên hosting có TRÙNG với khoá đã dán bên
 * sepay.vn không" — mà để trả lời thì không cần nhìn thấy khoá. In 8 ký tự đầu
 * của SHA-256 là đủ so, và một màn hình chẩn đoán lỡ bị chụp lại hay chia sẻ
 * thì cũng không làm lộ thứ gì.
 *
 * Đây đúng là bài học vừa trả giá: khoá hiện nguyên văn ra ngoài .env một lần
 * là phải sinh lại từ đầu.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * CÁCH DÙNG
 *
 *   Trên máy:     php kiem-tra-sepay.php
 *   Trên hosting: https://<tên-miền>/kiem-tra-sepay.php?token=<INSTALL_TOKEN>
 *
 *   Thử luôn cả đường header (mục 5, chỉ có nghĩa trên hosting):
 *     curl "https://<tên-miền>/kiem-tra-sepay.php?token=<TOKEN>" \
 *          -H "Authorization: Apikey <khoá>"
 *
 * KHÔNG mở được ở localhost:8000 — server.php chỉ cho index.php và install.php
 * đi qua, mọi file .php khác ở gốc đều bị chặn (cùng cảnh với kiem-tra-db.php).
 * Ở máy thì chạy bằng dòng lệnh.
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

define('ROOT_PATH',   __DIR__);
define('APP_PATH',    ROOT_PATH . '/app');
define('CORE_PATH',   ROOT_PATH . '/core');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('VIEWS_PATH',  APP_PATH . '/views');

require_once CORE_PATH . '/App.php';
App::boot();

/*
 * Chạy bằng dòng lệnh thì bỏ qua token: người gõ được `php kiem-tra-sepay.php`
 * là người đã đọc được cả .env rồi, một lớp khoá nữa không thêm gì.
 * Qua web thì bắt buộc — file này in ra cấu trúc bên trong của tích hợp.
 */
$quaWeb = PHP_SAPI !== 'cli';

if ($quaWeb) {
    $expected = (string) env('INSTALL_TOKEN', '');
    $given    = (string) ($_GET['token'] ?? '');

    if ($expected === '' || !hash_equals($expected, $given)) {
        http_response_code(403);
        exit("Thiếu hoặc sai token. Mở kèm ?token=<INSTALL_TOKEN trong .env>\n");
    }
}

/** Các việc còn phải làm, gom lại in ở cuối. */
$canLam = [];

/*
 * Căn lề bằng tay chứ không dùng %-38s của printf: printf đếm BYTE, mà "Mã
 * ngân hàng" trong UTF-8 tốn 14 byte cho 11 ký tự — mọi nhãn tiếng Việt sẽ bị
 * đẩy lệch một khoảng đúng bằng số dấu trong nhãn đó.
 *
 * Đếm bằng utf8Length() của helpers.php chứ không mb_strlen(): hosting này
 * KHÔNG bật mbstring (chính lý do dự án tự viết hàm đó).
 */
function le(string $chuoi, int $rong): string
{
    $thieu = $rong - utf8Length($chuoi);

    return $chuoi . ($thieu > 0 ? str_repeat(' ', $thieu) : '');
}

function ok(string $nhan, bool $dat, string $chiTiet = '', string $viecCanLam = ''): bool
{
    global $canLam;

    echo '  [', $dat ? '✓' : '✗', '] ', le($nhan, 38), ' ', $chiTiet, "\n";

    if (!$dat && $viecCanLam !== '') {
        $canLam[] = $viecCanLam;
    }

    return $dat;
}

function muc(string $tieuDe): void
{
    echo "\n", $tieuDe, "\n", str_repeat('-', 70), "\n";
}

echo "CHẨN ĐOÁN TÍCH HỢP SEPAY\n";
echo str_repeat('=', 70), "\n";
printf("Môi trường : %s\n", config('app.env', '?'));
printf("Chạy qua   : %s\n", $quaWeb ? 'trình duyệt' : 'dòng lệnh');

// ---------------------------------------------------------------------------
// 1. TÀI KHOẢN NHẬN TIỀN
//
// Không khai ở config/sepay.php mà ở company.php -> bank, để mã QR và webhook
// dùng chung MỘT nguồn sự thật. Thiếu thì màn QR tự ẩn mã đi, và khách không
// có cách nào chuyển đúng nội dung.
// ---------------------------------------------------------------------------
muc('1. TÀI KHOẢN NHẬN TIỀN  (config/company.php -> bank)');

$bank    = (array) config('company.bank', []);
$duTaiKhoan = true;

foreach (['number' => 'Số tài khoản', 'holder' => 'Chủ tài khoản',
          'bin' => 'Mã ngân hàng (BIN)', 'name' => 'Tên ngân hàng'] as $khoa => $nhan) {
    $giaTri = trim((string) ($bank[$khoa] ?? ''));
    $duTaiKhoan = ok($nhan, $giaTri !== '', $giaTri !== '' ? $giaTri : 'CHƯA ĐIỀN',
        'Điền ' . $nhan . ' vào config/company.php -> bank') && $duTaiKhoan;
}

if ($duTaiKhoan) {
    echo "\n  Tài khoản này phải TRÙNG với tài khoản đã liên kết bên sepay.vn.\n";
    echo "  Lệch nhau thì QR trỏ một nơi còn webhook nghe một nơi khác.\n";
}

// ---------------------------------------------------------------------------
// 2. CẤU HÌNH SEPAY TRONG .env
// ---------------------------------------------------------------------------
muc('2. CẤU HÌNH  (.env trên chính máy chủ này)');

$batRoi = (bool) config('sepay.enabled', false);
$khoa   = trim((string) config('sepay.webhook_key', ''));

ok('SEPAY_WEBHOOK_KEY', $khoa !== '',
    $khoa !== ''
        ? sprintf('%d ký tự · vân tay %s', strlen($khoa), substr(hash('sha256', $khoa), 0, 8))
        : 'CHƯA KHAI — webhook đang trả 403 cho mọi request',
    'Thêm SEPAY_WEBHOOK_KEY=<khoá> vào .env TRÊN HOSTING (không phải .env.production ở máy)');

ok('SEPAY_ENABLED', $batRoi,
    $batRoi ? 'true' : 'false — trang QR không hứa xác nhận tự động',
    'Đặt SEPAY_ENABLED=true trong .env sau khi test webhook xanh');

echo "\n  Vân tay ở trên dùng để SO, không phải để dán.\n";
echo "  Chạy file này ở cả máy và hosting: hai vân tay khác nhau = hai khoá khác nhau.\n";

if ($khoa !== '' && strlen($khoa) < 32) {
    echo "\n  ⚠ Khoá ngắn bất thường (" . strlen($khoa) . " ký tự). Nên dùng `openssl rand -hex 32`.\n";
}

if ($batRoi && $khoa === '') {
    echo "\n  ⚠ MÂU THUẪN: đã bật nhưng chưa có khoá. Trang QR đang hứa với khách\n";
    echo "    \"xác nhận sau 1-2 phút\" trong khi webhook từ chối mọi request.\n";
}

// ---------------------------------------------------------------------------
// 3. CƠ SỞ DỮ LIỆU
// ---------------------------------------------------------------------------
muc('3. SỔ GIAO DỊCH  (bảng sepay_transactions)');

$noiDuoc = Database::isConnected();
ok('Kết nối CSDL', $noiDuoc,
    $noiDuoc ? config('database.name') . ' @ ' . config('database.host') : 'KHÔNG NỐI ĐƯỢC',
    'Chạy kiem-tra-db.php để dò nguyên nhân');

$coBang = false;

if ($noiDuoc) {
    $coBang = SepayModel::available();
    ok('Bảng sepay_transactions', $coBang,
        $coBang ? 'đã tạo' : 'CHƯA CÓ — webhook đang trả 503',
        'Chạy database/migrations/2026-08-22-sepay-doi-soat.sql qua phpMyAdmin');
}

// ---------------------------------------------------------------------------
// 4. ĐƯỜNG WEBHOOK
// ---------------------------------------------------------------------------
muc('4. ĐỊA CHỈ ĐỂ DÁN VÀO SEPAY');

$goc = rtrim((string) config('app.url', ''), '/');

echo "  URL nhận webhook   " . ($goc !== '' ? $goc . '/webhook/sepay' : '(APP_URL chưa khai trong .env)') . "\n";
echo "  Kiểu xác thực      API Key\n";
echo "  Sự kiện            Có tiền vào\n";
echo "  Bộ lọc tiền tố     ĐỂ TRỐNG\n\n";
echo "  Để trống bộ lọc là cố ý: lọc theo tiền tố 'DH' thì giao dịch của khách\n";
echo "  gõ sai nội dung sẽ không bao giờ tới website — mà đó đúng là ca cần\n";
echo "  thấy nhất, vì tiền đã về thật mà không biết của đơn nào.\n";

if ($goc !== '' && !str_starts_with($goc, 'https://') && config('app.env') === 'production') {
    echo "\n  ⚠ APP_URL không phải https. SePay gửi khoá bí mật trong header —\n";
    echo "    đi qua http là để lộ nó trên đường truyền.\n";
}

// ---------------------------------------------------------------------------
// 5. HEADER AUTHORIZATION CÓ TỚI ĐƯỢC PHP KHÔNG
//
// Đây là lỗi khó lần nhất trong cả tích hợp. Apache chạy PHP dưới CGI/FastCGI
// KHÔNG truyền header Authorization vào $_SERVER — luật E=HTTP_AUTHORIZATION ở
// .htaccess sinh ra để chữa đúng việc này. Nếu luật không ăn (hosting tắt
// mod_rewrite, hoặc bỏ qua .htaccess), triệu chứng là webhook trả 401 vĩnh
// viễn trong khi khoá hai bên GIỐNG HỆT NHAU.
// ---------------------------------------------------------------------------
muc('5. HEADER AUTHORIZATION  (chỗ hay hỏng nhất, không để lại dấu vết)');

if (!$quaWeb) {
    /*
     * Chỉ kiểm được TRÊN HOSTING, và cũng chỉ ở đó mới có nghĩa: lỗi này là
     * lỗi của Apache chạy PHP dưới CGI. Server built-in của PHP luôn truyền
     * header, nên chạy thử ở máy có xanh cũng không nói lên điều gì.
     *
     * Thêm nữa server.php chỉ cho /index.php và /install.php đi qua, nên
     * localhost:8000/kiem-tra-sepay.php trả 404 chứ không chạy.
     */
    echo "  Bỏ qua — mục này chỉ kiểm được trên hosting, chạy qua web.\n\n";
    echo "  Sau khi đã tải file này lên hosting:\n\n";
    echo "    curl \"https://<tên-miền>/kiem-tra-sepay.php?token=<INSTALL_TOKEN>\" \\\n";
    echo "         -H \"Authorization: Apikey <khoá trong .env trên hosting>\"\n";
} else {
    $nguon = [];

    if (isset($_SERVER['HTTP_AUTHORIZATION']))          { $nguon[] = 'HTTP_AUTHORIZATION'; }
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) { $nguon[] = 'REDIRECT_HTTP_AUTHORIZATION'; }

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $ten => $gt) {
            if (strcasecmp($ten, 'Authorization') === 0) {
                $nguon[] = 'getallheaders()';
                break;
            }
        }
    }

    if ($nguon === []) {
        echo "  Request này KHÔNG kèm header Authorization.\n\n";
        echo "  Chưa kết luận được gì — mở bằng trình duyệt thì vốn không có header nào.\n";
        echo "  Muốn biết chắc, gọi lại bằng curl (dán cả header vào):\n\n";
        echo "    curl \"" . ($goc !== '' ? $goc : 'https://<tên-miền>') . "/kiem-tra-sepay.php?token=<TOKEN>\" \\\n";
        echo "         -H \"Authorization: Apikey thu-mot-chuoi-bat-ky\"\n";
    } else {
        echo "  ✓ Header tới được PHP qua: " . implode(', ', $nguon) . "\n\n";

        /*
         * So khoá gửi kèm với khoá trong .env — nhưng CHỈ so vân tay và chỉ
         * nói đúng/sai. In ra phần trùng khớp là tự tay dựng một kênh dò khoá
         * từng ký tự cho bất kỳ ai có token.
         */
        $raw  = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $guiKem = trim(preg_replace('/^\s*api\s*key\s+/i', '', trim((string) $raw)) ?? '');

        if ($guiKem !== '' && $khoa !== '') {
            $trung = hash_equals($khoa, $guiKem);
            printf("  Khoá gửi kèm: vân tay %s — %s\n",
                substr(hash('sha256', $guiKem), 0, 8),
                $trung ? '✓ TRÙNG với .env, webhook sẽ qua được cửa này'
                       : '✗ KHÁC với .env, webhook sẽ trả 401');
        }
    }
}

// ---------------------------------------------------------------------------
// 6. SỔ ĐÃ NHẬN ĐƯỢC GÌ
//
// Đây là chỗ trả lời câu "webhook ăn chưa" bằng dữ liệu chứ không bằng suy đoán,
// và tạm thời thay cho màn đối soát trong khu quản trị (chưa làm).
// ---------------------------------------------------------------------------
muc('6. SỔ ĐÃ NHẬN ĐƯỢC GÌ');

if (!$coBang) {
    echo "  Chưa có bảng — bỏ qua.\n";
} else {
    $tong = (int) Database::fetchValue('SELECT COUNT(*) FROM sepay_transactions');

    if ($tong === 0) {
        echo "  Sổ trống: chưa nhận được giao dịch nào.\n\n";
        echo "  Chưa kết luận là hỏng. Nếu vừa cấu hình xong thì đây là điều bình thường —\n";
        echo "  bắn thử một phát ở my.sepay.vn: Giao dịch -> Giả lập giao dịch.\n";
    } else {
        printf("  Tổng: %d giao dịch\n\n", $tong);

        $theoTrangThai = Database::fetchAll(
            'SELECT applied, COUNT(*) AS soLuong, SUM(amount) AS tongTien
               FROM sepay_transactions GROUP BY applied ORDER BY soLuong DESC'
        );

        $nghia = [
            'paid'         => 'đủ tiền -> đơn sang đã thanh toán',
            'deposit_paid' => 'đủ cọc -> đơn sang đã đặt cọc',
            'partial'      => 'tiền về nhưng chưa đủ ngưỡng nào',
            'no_order'     => 'KHÔNG khớp đơn nào',
            'ignored'      => 'tiền ra, hoặc đơn đã ở trạng thái cao hơn',
        ];

        foreach ($theoTrangThai as $dong) {
            echo '    ', le((string) $dong['applied'], 14),
                 sprintf(' %4d giao dịch  ', (int) $dong['soLuong']),
                 le(money((int) $dong['tongTien']), 14),
                 '   ', $nghia[$dong['applied']] ?? '', "\n";
        }

        /*
         * Hai loại này là tiền THẬT đã về mà đơn chưa đổi — không ai được báo
         * vì chưa có màn đối soát. In thẳng ra đây để chúng không nằm im.
         */
        $canNguoiXem = Database::fetchAll(
            "SELECT sepay_id, order_code, amount, content, transaction_date, applied
               FROM sepay_transactions
              WHERE applied IN ('no_order', 'partial') AND transfer_type = 'in'
              ORDER BY transaction_date DESC LIMIT 15"
        );

        if ($canNguoiXem !== []) {
            echo "\n  ⚠ CẦN NGƯỜI XEM — tiền đã về nhưng đơn chưa đổi:\n\n";

            foreach ($canNguoiXem as $d) {
                echo '    ', le('#' . (int) $d['sepay_id'], 11),
                     le((string) $d['applied'], 12), ' ',
                     le(money((int) $d['amount']), 14), '  ',
                     (string) ($d['transaction_date'] ?? ''), "\n";
                printf("      nội dung: %s\n", utf8Substr((string) ($d['content'] ?? ''), 0, 60));

                if ($d['applied'] === 'no_order') {
                    echo "      -> đọc không ra mã đơn. Xem khách gõ nhầm gì, gán tay vào đơn.\n";
                }
            }
        }

        $ganNhat = Database::fetchOne(
            'SELECT created_at FROM sepay_transactions ORDER BY created_at DESC LIMIT 1'
        );

        if ($ganNhat !== null) {
            printf("\n  Giao dịch gần nhất ghi vào sổ: %s\n", $ganNhat['created_at']);
        }
    }
}

// ---------------------------------------------------------------------------
// KẾT LUẬN
// ---------------------------------------------------------------------------
muc('KẾT LUẬN');

$sanSang = $duTaiKhoan && $khoa !== '' && $coBang;

if ($sanSang) {
    echo "  ✓ Phía website đã sẵn sàng nhận webhook.\n\n";
    echo "  Còn lại là việc bên sepay.vn — những thứ không kiểm được từ đây:\n";
    echo "    - đã liên kết đúng tài khoản ngân hàng chưa\n";
    echo "    - webhook đã trỏ đúng URL ở mục 4 chưa, xác thực có chọn API Key không\n";
    echo "    - khoá dán bên đó có trùng vân tay ở mục 2 không\n\n";
    echo "  Thử thật: my.sepay.vn -> Giao dịch -> Giả lập giao dịch.\n";
    echo "  Bắn xong chạy lại file này, mục 6 phải có thêm một dòng.\n";
} else {
    echo "  ✗ CHƯA sẵn sàng. Việc phải làm, theo thứ tự:\n\n";

    foreach ($canLam as $i => $viec) {
        printf("    %d. %s\n", $i + 1, $viec);
    }
}

echo "\n";
echo "  Vẫn không thấy gì tới dù đã làm hết: mở Nhật ký WebHooks ở my.sepay.vn\n";
echo "  xem SePay nhận về mã HTTP nào. Không có dòng nào trong nhật ký nghĩa là\n";
echo "  request chưa tới được PHP — thủ phạm thường là lớp chống bot của hosting\n";
echo "  miễn phí. SePay bắn từ các IP: 172.236.138.20, 172.233.83.68, 171.244.35.2,\n";
echo "  151.158.108.68, 151.158.109.79, 103.255.238.139.\n";

echo "\n" . str_repeat('=', 70) . "\n";
echo "XOÁ FILE NÀY sau khi webhook đã chạy. Nó in ra cấu trúc bên trong của\n";
echo "tích hợp, và một file chẩn đoán bỏ quên trên hosting là một cánh cửa hé.\n";
