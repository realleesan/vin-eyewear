<?php

/**
 * errors/500.php — TRANG LỖI CUỐI CÙNG, chạy khi mọi thứ khác đã hỏng.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐỪNG NHẦM VỚI app/views/errors/500.php
 *
 *   app/views/errors/500.php   bản ĐẸP, đi qua ErrorController + khung layout
 *                              + errors.css. Chạy khi ứng dụng còn khoẻ, chỉ là
 *                              route không tồn tại hoặc controller trả lỗi 500
 *                              một cách có kiểm soát.
 *
 *   errors/500.php  (file này) bản TRẦN, là lưới cuối. Được nạp khi:
 *                                · một exception không ai bắt lọt tới bộ xử lý
 *                                  chung ở core/App.php, và APP_DEBUG đang tắt
 *                                · hoặc chính ErrorController cũng ném lỗi
 *                                  (xem Router::renderError)
 *
 * File này TRƯỚC ĐÂY RỖNG 0 BYTE. Hệ quả: mọi lỗi 500 trên production hiện ra
 * một trang trắng không một chữ nào — khách không biết chuyện gì xảy ra, không
 * biết gọi ai, và cửa hàng cũng không biết là vừa có người gặp lỗi.
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG PHỤ THUỘC VÀO BẤT CỨ THỨ GÌ — ĐÓ LÀ TOÀN BỘ THIẾT KẾ CỦA FILE NÀY
 *
 * Lý do file này được nạp CHÍNH LÀ vì có thứ gì đó đã hỏng. Nên nó không được
 * gọi tới:
 *
 *   · cơ sở dữ liệu   — hỏng CSDL là nguyên nhân thường gặp nhất dẫn tới đây
 *   · khung layout    — master.php kéo theo header, footer, giỏ hàng, phiên
 *   · hàm trợ giúp    — e(), asset(), t()… nằm trong core/helpers.php, mà nếu
 *                       App::boot() chết giữa chừng thì chúng chưa tồn tại
 *   · phông chữ ngoài — một lượt gọi Google Fonts lúc máy chủ đang quá tải là
 *                       thêm vài giây trắng màn hình trước khi thấy chữ
 *
 * Toàn bộ CSS nhúng thẳng, chữ dùng phông hệ thống, không một request nào ra
 * ngoài. Trang này phải hiện được cả khi máy chủ chỉ còn thở thoi thóp.
 * ─────────────────────────────────────────────────────────────────────────────
 */

/*
 * Đặt mã 500 nếu chưa ai đặt.
 *
 * Hai nơi gọi file này đều đã đặt sẵn, nhưng chúng đặt TRƯỚC khi require —
 * còn nếu ai đó mở thẳng /errors/500.php thì chưa có mã nào. Trả 200 cho một
 * trang báo lỗi là nói dối cả trình duyệt lẫn Google.
 *
 * headers_sent() là bắt buộc: exception có thể nổ khi trang đã in ra được một
 * nửa, và lúc đó header đã đi rồi — gọi tiếp chỉ sinh thêm một warning nữa
 * chồng lên lỗi đang có.
 */
if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
}

/*
 * Số hotline: ưu tiên đọc từ config để không thành nguồn thứ hai, nhưng CÓ
 * ĐƯỜNG LUI.
 *
 * config() nằm trong core/helpers.php. Trường hợp thường gặp (controller ném
 * lỗi) thì ứng dụng đã nạp xong và hàm này chạy bình thường. Trường hợp hiếm
 * (App::boot chết) thì chưa có hàm nào cả — function_exists() lo đúng chỗ đó.
 *
 * Con số gõ cứng bên dưới là bản sao dự phòng của config/company.php -> hotline.
 * Đổi số ở đó thì đổi luôn ở đây.
 */
$hotline = function_exists('config') ? (string) config('company.hotline', '1900 6868') : '1900 6868';
$hotline = $hotline !== '' ? $hotline : '1900 6868';

/* Giờ xảy ra lỗi — thứ DUY NHẤT khách cần đọc cho nhân viên.
   Nó khớp với dòng tương ứng trong error log của máy chủ, nên "lúc 15:42" đủ
   để tìm ra đúng lỗi thay vì hỏi khách "anh bấm vào đâu ạ". */
$luc = date('H:i') . ' ngày ' . date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Hệ thống đang gặp sự cố — Vin Eyewear</title>
<style>
    /* Phông HỆ THỐNG, không tải gì từ ngoài — xem khối chú thích đầu file. */
    .vin-err, .vin-err * { box-sizing: border-box; margin: 0; padding: 0; }

    /*
     * position:fixed PHỦ KÍN MÀN HÌNH, không phải một khối nằm trong luồng.
     *
     * Exception có thể nổ khi trang đã in ra được một nửa (header, vài dòng
     * sản phẩm), và file này bị NỐI THÊM vào cuối cái nửa đó. Một khối thường
     * sẽ nằm lửng phía dưới đống markup dở dang, đọc như trang bị lỗi hiển thị.
     * Lớp phủ thì che hết, nên dù rơi vào tình huống nào khách cũng thấy đúng
     * một trang sạch.
     */
    .vin-err {
        position: fixed;
        inset: 0;
        z-index: 2147483647;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        overflow: auto;
        background: #faf6f0;
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        font-size: 15px;
        line-height: 1.65;
        color: #33272a;
        -webkit-font-smoothing: antialiased;
    }

    .vin-err__box {
        width: 100%;
        max-width: 480px;
        padding: 40px 36px;
        border-radius: 6px;
        background: #fff;
        box-shadow: 0 2px 14px rgba(90, 40, 40, 0.07);
        text-align: center;
    }

    .vin-err__brand {
        font-size: 20px;
        font-weight: 600;
        letter-spacing: 0.02em;
        color: #8a2432;
    }

    .vin-err__brand em { font-style: italic; font-weight: 400; }

    .vin-err__rule {
        width: 40px;
        height: 1px;
        margin: 22px auto;
        background: #e3cfc4;
    }

    .vin-err__title {
        font-size: 21px;
        font-weight: 600;
        line-height: 1.35;
        color: #33272a;
    }

    .vin-err__desc {
        margin-top: 12px;
        font-size: 14.5px;
        color: #6f5f5a;
    }

    /* Giờ xảy ra lỗi — nhỏ, nhưng là thứ nhân viên cần khi khách gọi tới. */
    .vin-err__when {
        margin-top: 20px;
        padding: 10px 14px;
        border-radius: 4px;
        background: #f7efe6;
        font-size: 13px;
        color: #6f5f5a;
    }

    .vin-err__when strong { color: #33272a; }

    .vin-err__acts {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: center;
        margin-top: 26px;
    }

    .vin-err__btn {
        display: inline-block;
        padding: 12px 26px;
        border: 1px solid #8a2432;
        border-radius: 4px;
        background: #8a2432;
        color: #fff;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        font-family: inherit;
    }

    .vin-err__btn:hover { background: #6f1c28; border-color: #6f1c28; }

    .vin-err__btn--ghost {
        background: transparent;
        border-color: #e3cfc4;
        color: #33272a;
    }

    .vin-err__btn--ghost:hover { background: transparent; border-color: #8a2432; color: #8a2432; }

    .vin-err__help {
        margin-top: 22px;
        font-size: 13.5px;
        color: #6f5f5a;
    }

    .vin-err__help a {
        color: #8a2432;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
    }

    .vin-err__help a:hover { text-decoration: underline; }

    @media (max-width: 420px) {
        .vin-err__box { padding: 32px 22px; }
        .vin-err__acts .vin-err__btn { width: 100%; }
    }
</style>
</head>
<body>
<div class="vin-err">
    <div class="vin-err__box">

        <div class="vin-err__brand">Vin <em>Eyewear</em></div>

        <div class="vin-err__rule"></div>

        <h1 class="vin-err__title">Hệ thống đang gặp sự cố</h1>

        <?php /* KHÔNG in chi tiết kỹ thuật. Khách đọc "SQLSTATE[42S22]" thì
                 vừa không hiểu gì, vừa để lộ tên bảng tên cột ra ngoài. Chi
                 tiết đã nằm trong error log của máy chủ. */ ?>
        <p class="vin-err__desc">
            Rất xin lỗi bạn. Trang này tạm thời không tải được.
            Chúng tôi đã ghi nhận sự cố và đang xử lý.
        </p>

        <p class="vin-err__when">
            Xảy ra lúc <strong><?php echo $luc; ?></strong> — đọc giúp chúng tôi
            mốc giờ này khi liên hệ để tra cứu nhanh hơn.
        </p>

        <div class="vin-err__acts">
            <?php /* "Thử lại" trước "Về trang chủ": phần lớn lỗi 500 là nhất
                     thời (mất kết nối CSDL trong chốc lát, máy chủ quá tải), và
                     tải lại là thứ chữa được nhiều nhất trong hai. */ ?>
            <button type="button" class="vin-err__btn" onclick="location.reload()">Thử lại</button>
            <a class="vin-err__btn vin-err__btn--ghost" href="/">Về trang chủ</a>
        </div>

        <p class="vin-err__help">
            <?php /* ĐANG ĐẶT HÀNG DỞ là tình huống đáng lo nhất khi gặp trang
                     này: khách không biết tiền đã trừ chưa, đơn đã vào chưa.
                     Nói thẳng ra rằng có người nghe máy, thay vì để họ tự đoán. */ ?>
            Đang đặt hàng mà gặp lỗi? Gọi
            <a href="tel:<?php echo preg_replace('/\D+/', '', $hotline); ?>"><?php echo $hotline; ?></a>
            để chúng tôi kiểm tra giúp bạn.
        </p>

    </div>
</div>
</body>
</html>
