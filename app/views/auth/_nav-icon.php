<?php

/**
 * auth/_nav-icon.php — icon của một mục trong cột điều hướng tài khoản.
 *
 * KHÔNG dùng icon() của core/icons.php: bốn hình dưới đây vẽ riêng cho
 * "Vin Eyewear Account.dc.html" trên khung 16×16 nét 1.8, còn kho ICONS dùng
 * chung là khung 24×24 nét 1.5. Trộn hai bộ thì bốn mục cạnh nhau có bốn độ
 * dày nét khác nhau.
 *
 * Nhận qua partial():
 *   $key — khoá mục ('don-hang' | 'do-mat' | 'uu-dai' | 'lich-hen')
 */
?>
<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
    <?php switch ($key):
        case 'don-hang': ?>
            <path d="M16 3H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"></path>
            <path d="M9 7h6M9 11h6M9 15h4"></path>
            <?php break;

        case 'do-mat': ?>
            <circle cx="6.5" cy="12" r="4"></circle>
            <circle cx="17.5" cy="12" r="4"></circle>
            <path d="M10.5 12h3M2.5 10.5L1 9M21.5 10.5L23 9"></path>
            <?php break;

        case 'uu-dai': ?>
            <rect x="3" y="8" width="18" height="4" rx="1"></rect>
            <path d="M5 12v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8M12 8v13M12 8s-4.5.2-5.5-2C5.8 4.4 7.5 3 9 3.5c2 .7 3 4.5 3 4.5s1-3.8 3-4.5c1.5-.5 3.2.9 2.5 2.5-1 2.2-5.5 2-5.5 2z"></path>
            <?php break;

        case 'lich-hen': ?>
            <path d="M7 3v3M17 3v3"></path>
            <path d="M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"></path>
            <path d="M8.5 13h2M8.5 16.5h7"></path>
            <?php break;
    endswitch; ?>
</svg>
