<?php

/**
 * auth/_nav-icon.php — icon của một mục trong cột điều hướng tài khoản.
 *
 * KHÔNG dùng icon() của core/icons.php: các hình dưới đây vẽ riêng cho
 * "Vin Eyewear Account.dc.html" trên khung 16×16 nét 1.8, còn kho ICONS dùng
 * chung là khung 24×24 nét 1.5. Trộn hai bộ thì các mục cạnh nhau có độ dày
 * nét khác nhau.
 *
 * Hình 'do-mat' (cặp kính) đã gỡ cùng mục "Thông số đo mắt".
 *
 * 'ho-so' thêm 2026-09-10: từ khi cột điều hướng bỏ nhóm thu gọn "Tài khoản
 * của tôi" (BR-UC.USER.05-02), Hồ sơ cá nhân là một mục cấp một như ba mục kia
 * nên nó cần icon của riêng mình.
 *
 * 'don-hang' ĐỔI HÌNH cùng ngày: từ tờ giấy có dòng kẻ sang chiếc túi mua hàng,
 * theo đúng chữ trong mục Giao diện của UC-USER-05 ("icon: shopping-bag"). Tờ
 * giấy cũ dễ đọc nhầm thành "hoá đơn" hoặc "tài liệu".
 *
 * Nhận qua partial():
 *   $key — khoá mục ('ho-so' | 'don-hang' | 'lich-hen')
 */
?>
<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <?php switch ($key):
        case 'ho-so': ?>
            <circle cx="12" cy="8" r="4"></circle>
            <path d="M4 21c1.5-3.5 4.5-5 8-5s6.5 1.5 8 5"></path>
            <?php break;

        case 'don-hang': ?>
            <path d="M5 7h14l1 13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1L5 7z"></path>
            <path d="M9 10V6a3 3 0 0 1 6 0v4"></path>
            <?php break;

        case 'lich-hen': ?>
            <path d="M7 3v3M17 3v3"></path>
            <path d="M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"></path>
            <path d="M8.5 13h2M8.5 16.5h7"></path>
            <?php break;
    endswitch; ?>
</svg>
