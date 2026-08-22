<?php

/**
 * errors/404.php — TRANG KHÔNG TÌM THẤY, bản trần.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐỪNG NHẦM VỚI app/views/errors/404.php
 *
 * Y HỆT cặp 500: bản trong app/views/ là bản đẹp, đi qua ErrorController và
 * khung layout đầy đủ — đó là thứ khách gặp ở gần như mọi lần gõ sai địa chỉ.
 * File này là LƯỚI CUỐI, chỉ được nạp khi chính ErrorController cũng hỏng
 * (xem Router::renderError).
 *
 * Nên nó theo đúng luật của errors/500.php: KHÔNG cơ sở dữ liệu, KHÔNG layout,
 * KHÔNG hàm trợ giúp, KHÔNG phông chữ tải từ ngoài. Lý do file này chạy chính
 * là vì thứ khác đã hỏng. Chú thích đầy đủ nằm ở errors/500.php.
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO VIẾT LẠI: bản trước là trang mặc định chưa port — nền xám, chữ đỏ
 * #e74c3c, nút xanh dương #3498db, phông Arial. Không một màu nào thuộc về
 * thương hiệu. Khách đi lạc một lần đã đủ khó chịu; hạ cánh xuống một trang
 * trông như của website khác thì họ đóng tab luôn.
 */

if (!headers_sent()) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Không tìm thấy trang — Vin Eyewear</title>
<style>
    /* Dùng CHUNG bộ lớp .vin-err với errors/500.php: hai trang lỗi phải trông
       như anh em, và sửa dáng một chỗ thì nên đổi cả hai. */
    .vin-err, .vin-err * { box-sizing: border-box; margin: 0; padding: 0; }

    /* position:fixed phủ kín — xem lý do ở errors/500.php. */
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

    /* Con số 404 to — KHÁC trang 500, và đó là chủ ý.
       404 là lỗi vô hại, in số ra thì khách hiểu ngay "mình gõ sai địa chỉ".
       Còn 500 là lỗi của cửa hàng: phô một con số kỹ thuật ở đó chỉ khiến người
       đang gặp rắc rối cảm thấy mình vừa làm sai điều gì. */
    .vin-err__code {
        display: block;
        font-size: 54px;
        font-weight: 600;
        line-height: 1;
        letter-spacing: 0.04em;
        color: #e3cfc4;
    }

    .vin-err__title {
        margin-top: 14px;
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
        font-family: inherit;
    }

    .vin-err__btn:hover { background: #6f1c28; border-color: #6f1c28; }

    .vin-err__btn--ghost {
        background: transparent;
        border-color: #e3cfc4;
        color: #33272a;
    }

    .vin-err__btn--ghost:hover { background: transparent; border-color: #8a2432; color: #8a2432; }

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

        <span class="vin-err__code">404</span>

        <h1 class="vin-err__title">Không tìm thấy trang này</h1>

        <p class="vin-err__desc">
            Địa chỉ bạn vừa mở không tồn tại, hoặc sản phẩm đã được gỡ khỏi cửa hàng.
        </p>

        <?php /* HAI LỐI RA, và "Xem sản phẩm" đứng trước. Người đi lạc trên một
                 trang bán hàng thường đang tìm một món cụ thể; đẩy thẳng họ vào
                 danh sách sản phẩm có ích hơn là trả về trang chủ rồi để họ tự
                 bấm tiếp một lần nữa. */ ?>
        <div class="vin-err__acts">
            <a class="vin-err__btn" href="/san-pham">Xem sản phẩm</a>
            <a class="vin-err__btn vin-err__btn--ghost" href="/">Về trang chủ</a>
        </div>

    </div>
</div>
</body>
</html>
