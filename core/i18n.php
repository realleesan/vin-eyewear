<?php

/**
 * core/i18n.php — LỚP NGÔN NGỮ CỦA GIAO DIỆN
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ VIỆC CỦA TẦNG TRÌNH BÀY, KHÔNG PHẢI CỦA NGHIỆP VỤ
 *
 * File này chỉ đổi CHỮ TRÊN GIAO DIỆN: nhãn nút, tên mục điều hướng, tiêu đề
 * khối, câu trạng thái. Nó KHÔNG đụng tới:
 *
 *   · dữ liệu trong CSDL   tên sản phẩm, mô tả, tên bộ sưu tập, tên cơ sở vẫn
 *                          là chuỗi tiếng Việt do cửa hàng nhập. Dịch chúng
 *                          cần thêm cột vào bảng — tức đổi lược đồ, thứ nằm
 *                          ngoài phạm vi và cũng không ai yêu cầu.
 *   · thông báo lỗi của model / validation
 *   · nội dung thư, nội dung Zalo
 *   · khu quản trị          giữ nguyên tiếng Việt hoàn toàn
 *
 * TỪ 12/09/2026 SITE CHỈ CÒN MỘT NGÔN NGỮ: tiếng Việt. Xem khối chú thích ở
 * I18N_NGON_NGU bên dưới — tầng dịch và bảng lang/en.php vẫn nguyên vẹn, chỉ
 * là 'en' không còn nằm trong danh sách ngôn ngữ site nhận.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO KHÔNG DÙNG THƯ VIỆN I18N
 *
 * Dự án không có composer. Một bộ i18n đầy đủ (số nhiều, ngày tháng theo
 * locale, ICU message) là thứ site này chưa cần: nó có hai ngôn ngữ và vài
 * trăm nhãn tĩnh. Hai mảng PHP và một hàm tra bảng làm đúng việc đó, không
 * thêm một byte nào vào trình duyệt.
 *
 * DỊCH Ở MÁY CHỦ, KHÔNG DỊCH Ở TRÌNH DUYỆT: trang trả về đã là ngôn ngữ cuối
 * cùng, nên không có nhấp nháy đổi chữ sau khi tải, không tốn thêm một file JS
 * nào, và máy tìm kiếm đọc được đúng thứ người dùng đọc.
 */

/**
 * Các ngôn ngữ site nhận.
 *
 * Khoá là mã ngôn ngữ (đi vào thuộc tính lang của <html> và vào cookie), giá
 * trị là tên tự gọi của ngôn ngữ đó — LUÔN viết bằng chính ngôn ngữ ấy, vì
 * người đang đọc tiếng Anh mà thấy dòng "Vietnamese" thì không chắc bấm vào
 * sẽ ra thứ mình đọc được, còn thấy "Tiếng Việt" thì chắc.
 */
const I18N_NGON_NGU = [
    'vi' => 'Tiếng Việt',
];

/*
 * ┌─ SITE CHỈ CÒN TIẾNG VIỆT (12/09/2026, theo yêu cầu chủ dự án) ────────────
 * │
 * │ HAI DÒNG TRÊN LÀ CHỐT, KHÔNG PHẢI THIẾU SÓT. 'en' đã ra khỏi danh sách
 * │ ngôn ngữ site nhận, nên:
 * │
 * │   · mở ?lang=en  -> không khớp, bị bỏ qua, trang ra tiếng Việt
 * │   · cookie vin_lang=en còn sót từ trước -> cũng không khớp, cùng kết quả
 * │
 * │ VÌ SAO PHẢI GỠ KHỎI DANH SÁCH chứ không chỉ đổi mặc định thành 'vi':
 * │ trước đây mặc định là 'en' và site đã chạy một thời gian như thế, nên
 * │ trình duyệt của khách cũ có thể đang giữ cookie 'en'. Đổi mỗi mặc định
 * │ thì đúng những người đã ghé trước vẫn thấy tiếng Anh — và không còn nút
 * │ nào để họ chuyển về (bộ chuyển ngôn ngữ đã bỏ cùng hàng bản quyền ở chân
 * │ trang; xem chú thích trong _layout/footer.php).
 * │
 * │ KHÔNG XOÁ GÌ CỦA TẦNG DỊCH: lang/en.php, langUrl() và
 * │ i18nXuLyChuyenNgonNgu() giữ nguyên. Muốn mở lại tiếng Anh thì thêm đúng
 * │ dòng 'en' => 'English' vào mảng trên, rồi in một bộ chuyển ở đâu đó.
 * │
 * │ t() vẫn lùi về bảng tiếng Việt khi thiếu khoá — nay nó là cả hai vai:
 * │ ngôn ngữ đang dùng VÀ lưới an toàn.
 * └──────────────────────────────────────────────────────────────────────────
 */

/** Ngôn ngữ mặc định khi khách chưa chọn gì. */
const I18N_MAC_DINH = 'vi';

/** Tên cookie ghi nhớ lựa chọn. Sống một năm. */
const I18N_COOKIE = 'vin_lang';

/** Tên tham số URL của bộ chuyển ngôn ngữ. */
const I18N_COOKIE_PARAM = 'lang';

/**
 * Ngôn ngữ đang dùng cho lượt tải này.
 *
 * Thứ tự ưu tiên, dừng ở cái đầu tiên hợp lệ:
 *
 *   1. ?lang=  trên URL   — cú bấm vừa xảy ra, phải thắng mọi thứ
 *   2. cookie vin_lang    — lựa chọn của những lần trước
 *   3. I18N_MAC_DINH      — tiếng Việt
 *
 * Nay mảng I18N_NGON_NGU chỉ còn 'vi', nên hai mức đầu chỉ khớp được đúng
 * 'vi'; mọi giá trị khác rơi thẳng xuống mức 3. Ba mức vẫn giữ nguyên để ngày
 * nào mở lại ngôn ngữ thứ hai thì không phải viết lại hàm này.
 *
 * KHÔNG đoán theo Accept-Language của trình duyệt: một mặc định cố định thì
 * đoán được. (Lý do này càng đúng từ khi site chỉ còn một ngôn ngữ — đoán để
 * rồi ra cùng một kết quả là công đi vô ích.)
 *
 * KHÔNG lưu vào $_SESSION: phiên là thứ của tầng nghiệp vụ (giỏ hàng, đăng
 * nhập, token CSRF) và đợt này không được đụng tới. Cookie riêng cũng sống lâu
 * hơn phiên, đúng với thứ nó ghi: một sở thích, không phải một trạng thái.
 */
function currentLang(): string
{
    static $lang = null;

    if ($lang !== null) {
        return $lang;
    }

    $ungVien = [
        $_GET[I18N_COOKIE_PARAM] ?? null,
        $_COOKIE[I18N_COOKIE] ?? null,
    ];

    foreach ($ungVien as $ma) {
        if (is_string($ma) && isset(I18N_NGON_NGU[$ma])) {
            return $lang = $ma;
        }
    }

    return $lang = I18N_MAC_DINH;
}

/**
 * Xử lý cú bấm đổi ngôn ngữ, rồi quay lại đúng trang cũ với URL SẠCH.
 *
 * Gọi ở đầu _layout/master.php, trước khi in ra byte đầu tiên — setcookie() và
 * header() đều phải chạy khi chưa có output.
 *
 * VÌ SAO CHUYỂN HƯỚNG chứ không chỉ nhận ?lang= rồi vẽ trang:
 *
 *   · URL sạch chia sẻ được. Không thì mọi liên kết khách gửi cho nhau đều
 *     kéo theo ?lang=, và nó ghi đè lựa chọn của người nhận.
 *   · assets/js/catalog.js và account.js dùng history.pushState với chính URL
 *     đang đứng. Để ?lang= nằm lại là nó đi vào mọi URL sinh ra sau đó.
 *   · Tham số lạ trong URL cũng đi vào ô `back` của các form mua hàng.
 *
 * Chỉ chuyển hướng khi có ?lang= — mọi lượt tải bình thường không tốn gì.
 */
function i18nXuLyChuyenNgonNgu(): void
{
    $chon = $_GET[I18N_COOKIE_PARAM] ?? null;

    if (!is_string($chon) || !isset(I18N_NGON_NGU[$chon]) || headers_sent()) {
        return;
    }

    setcookie(I18N_COOKIE, $chon, [
        'expires'  => time() + 31536000,
        'path'     => '/',
        // Không cần httponly=false: không có dòng JS nào đọc cookie này.
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
    ]);

    // Dựng lại URL hiện tại, bỏ đúng tham số lang.
    $query = $_GET;
    unset($query[I18N_COOKIE_PARAM]);

    $dich = currentPath() . ($query ? '?' . http_build_query($query) : '');

    header('Location: ' . $dich, true, 302);
    exit;
}

/**
 * Đường dẫn để chuyển sang một ngôn ngữ, giữ nguyên trang đang đứng.
 *
 * Dùng cho bộ chuyển ngôn ngữ trên header và chân trang.
 */
function langUrl(string $ma): string
{
    $query = $_GET;
    $query[I18N_COOKIE_PARAM] = $ma;

    return currentPath() . '?' . http_build_query($query);
}

/**
 * Tra một nhãn giao diện.
 *
 * @param string               $key khoá dạng 'nav.products'
 * @param array<string,scalar> $thay chỗ trống cần điền, dạng [':n' => 3]
 *
 * BA MỨC LÙI, theo thứ tự:
 *
 *   1. bảng của ngôn ngữ đang chọn
 *   2. bảng tiếng Việt — nguồn gốc của mọi câu chữ trên site này, nên nó là
 *      lưới an toàn tốt hơn hẳn việc in ra khoá thô
 *   3. chính cái khoá
 *
 * In ra KHOÁ THÔ ('nav.products') là ca xấu nhất và cố ý để nó xấu: một khoá
 * lạ hiện giữa trang thì người đầu tiên nhìn thấy sẽ báo, còn một chuỗi rỗng
 * thì không ai để ý cho tới lúc khách hỏi nút này để làm gì.
 *
 * CHỖ TRỐNG VIẾT ':ten' chứ không %s: câu tiếng Anh và câu tiếng Việt thường
 * đảo thứ tự các mảnh, mà %s thì phụ thuộc thứ tự. Tên gọi thì không.
 *
 * KHÔNG tự escape: trả về chuỗi thô, view gọi e() như với mọi chuỗi khác.
 * Hàm này escape sẵn thì mọi chỗ nối chuỗi sẽ escape hai lần, và dấu ' trong
 * tiếng Anh hiện ra thành &#039;.
 */
function t(string $key, array $thay = []): string
{
    static $bang = [];

    $lang = currentLang();
    $chuoi = null;

    foreach ([$lang, 'vi'] as $ma) {
        if (!isset($bang[$ma])) {
            $file = ROOT_PATH . '/lang/' . $ma . '.php';
            $bang[$ma] = is_file($file) ? (array) require $file : [];
        }

        if (isset($bang[$ma][$key])) {
            $chuoi = $bang[$ma][$key];
            break;
        }
    }

    $chuoi ??= $key;

    return $thay === [] ? $chuoi : strtr($chuoi, $thay);
}
