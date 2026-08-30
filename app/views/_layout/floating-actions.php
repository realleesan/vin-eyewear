<?php

/**
 * _layout/floating-actions.php — S22 cụm nút nổi hỗ trợ.
 *
 * Gọi · Zalo · Messenger · lên đầu trang, ghim góc phải dưới MỌI trang
 * (include ở master.php sau footer, không phải một section của trang chủ).
 *
 * Đặc tả (docs/prototype/home-sections.md, S22) yêu cầu trên bề ngang hẹp
 * gộp lại thành một nút mở ra thay vì xếp bốn nút rời chồng lên nội dung:
 *   - Từ 900px trở lên: ba kênh xếp dọc, luôn thấy.
 *   - Dưới 900px: một nút mở/đóng, ba kênh bung lên trên khi mở.
 * Nút "lên đầu trang" đứng riêng ở cả hai bề ngang — nó chỉ hiện sau khi
 * cuộn nên không chiếm chỗ lúc đầu, và gộp vào trong nút mở ra thì mất đi
 * cái lợi duy nhất của nó là bấm một chạm.
 *
 * Số điện thoại và link kênh đọc từ config/company.php — nguồn duy nhất.
 */

$channels = config('company.channels');
$hotline  = config('company.hotline');

/*
 * Logo thương hiệu là hình TÔ ĐẶC, khác hẳn bộ icon nét trong core/icons.php
 * (fill=none, stroke=currentColor), nên để riêng ở đây thay vì nhét vào
 * ICONS — trộn vào đó thì mọi icon nét kế thừa stroke sẽ vẽ sai.
 *
 * Chỉ còn Messenger ở đây. Logo Zalo dựng nguyên cả thẻ <svg> ngay trong
 * $actions bên dưới vì nó cần khung 32×24 và hai màu, không vừa cái khuôn
 * 24×24 một màu mà mảng này phục vụ.
 */
$brandMarks = [
    'messenger' => '<path d="M12 3.5c-4.7 0-8.5 3.5-8.5 7.9 0 2.5 1.2 4.7 3.1 6.1v3l2.9-1.6c.8.2 1.6.3 2.5.3 4.7 0 8.5-3.5 8.5-7.8S16.7 3.5 12 3.5zm.9 10.5l-2.2-2.3-4.2 2.3 4.6-4.9 2.2 2.3 4.2-2.3z"/>',
];

$actions = [
    [
        'key'   => 'call',
        'href'  => $channels['hotline'],
        'label' => sprintf('Gọi %s', $hotline),
        'svg'   => icon('phone', 'fab__ico', 20),
        'blank' => false,
    ],
    [
        'key'   => 'zalo',
        'href'  => $channels['zalo'],
        'label' => 'Nhắn Zalo',
        /*
         * LOGO ZALO THẬT, không phải bong bóng chat chung chung.
         *
         * Bản trước vẽ đúng một bong bóng trơn, giống hệt nút Messenger ngay
         * dưới — hai nút chỉ khác nhau ở sắc xanh. Nay cả ba nút cùng một màu
         * (xem floating.css) nên icon là thứ DUY NHẤT phân biệt chúng, và một
         * bong bóng trơn thì không phân biệt được gì.
         *
         * Khung 32×24 chứ không phải 24×24 như hai icon kia: chữ "Zalo" nằm
         * ngang nên cần khung rộng hơn cao, ép vào ô vuông là chữ co lại tới
         * mức không đọc được.
         *
         * Bong bóng ăn `currentColor` (trắng), chữ ăn var(--fab-blue) — cùng
         * biến với nền nút, nên đổi màu nút là chữ đi theo, không lệch tông.
         */
        'svg'   => '<svg class="fab__ico fab__ico--zalo" width="24" height="18" '
                 . 'viewBox="0 0 32 24" fill="none" aria-hidden="true" focusable="false">'
                 . '<path fill="currentColor" d="M5.2 1.5h21.6a5 5 0 0 1 5 5v9.4a5 5 0 0 1-5 5H12.4l-6.6 3.9a.55.55 0 0 1-.83-.53l.37-3.42A5 5 0 0 1 .2 15.9V6.5a5 5 0 0 1 5-5z"/>'
                 . '<path fill="var(--fab-blue)" d="M6.4 6.6h6.5v1.9l-4 4.7h4.1v2H6.1v-1.9l4-4.7H6.4z"/>'
                 . '<path fill="var(--fab-blue)" d="M20.4 5.6h2.1v9.6h-2.1z"/>'
                 . '<path fill="var(--fab-blue)" d="M17.4 8.6c-1.1 0-2 .32-2.7.86l.72 1.4c.46-.33 1-.52 1.6-.52.8 0 1.25.36 1.25.95v.16h-1.4c-1.7 0-2.62.72-2.62 1.9 0 1.15.88 1.92 2.2 1.92.85 0 1.5-.3 1.86-.82v.7h1.9V11.6c0-1.9-1.06-3-2.8-3zm.88 4.5c0 .6-.5 1-1.2 1-.5 0-.83-.25-.83-.63 0-.36.28-.6.95-.6h1.08z"/>'
                 . '<path fill="var(--fab-blue)" d="M26.6 8.6a3.35 3.35 0 1 0 0 6.7 3.35 3.35 0 0 0 0-6.7zm0 4.9a1.55 1.55 0 1 1 0-3.1 1.55 1.55 0 0 1 0 3.1z"/>'
                 . '</svg>',
        'blank' => true,
    ],
    [
        'key'   => 'messenger',
        'href'  => $channels['messenger'],
        'label' => 'Chat Messenger',
        'svg'   => sprintf(
            '<svg class="fab__ico" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" '
            . 'aria-hidden="true" focusable="false">%s</svg>',
            $brandMarks['messenger']
        ),
        'blank' => true,
    ],
];
?>

<div class="fab" id="fabRoot" data-section="s22">

    <!--
      Lên đầu trang — JS bỏ thuộc tính hidden khi đã cuộn đủ xa.
      Để sẵn `hidden` trong HTML chứ không ẩn bằng CSS: tắt JavaScript thì nút
      này vô dụng (không có gì gắn vào nó), hiện ra chỉ gây bấm hụt.
    -->
    <button type="button" class="fab__btn fab__btn--top tap-target" id="fabTop" hidden>
        <?= icon('arrow-up', 'fab__ico', 20) ?>
        <span class="fab__label">Lên đầu trang</span>
    </button>

    <ul class="fab__list" id="fabList" role="list">
        <?php foreach ($actions as $a): ?>
            <li>
                <a class="fab__btn fab__btn--<?= e($a['key']) ?> tap-target"
                   href="<?= e($a['href']) ?>"
                   <?= $a['blank'] ? 'target="_blank" rel="noreferrer noopener"' : '' ?>>
                    <?= $a['svg'] ?>
                    <span class="fab__label"><?= e($a['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!--
      Nút mở/đóng: chỉ hiện dưới 900px (CSS). Có `hidden` sẵn và JS bỏ ra —
      không có JavaScript thì bấm vào không mở được gì, trong khi danh sách
      kênh lúc đó vẫn hiện thường (xem .fab__list trong floating.css).
    -->
    <button type="button" class="fab__toggle tap-target" id="fabToggle"
            aria-expanded="false" aria-controls="fabList"
            aria-label="Mở kênh hỗ trợ" hidden>
        <span class="fab__toggle-ico fab__toggle-ico--open"><?= icon('message', '', 22) ?></span>
        <span class="fab__toggle-ico fab__toggle-ico--close"><?= icon('close', '', 22) ?></span>
    </button>
</div>
