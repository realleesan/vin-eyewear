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
 */
$brandMarks = [
    // Bong bóng chat — dùng cho Zalo, phân biệt với Messenger bằng màu nền + nhãn
    'zalo'      => '<path d="M12 3.5c-4.9 0-8.9 3.3-8.9 7.4 0 2.3 1.3 4.4 3.3 5.8-.1 1.2-.6 2.4-1.4 3.4 1.6-.2 3.1-.8 4.4-1.7.8.2 1.7.3 2.6.3 4.9 0 8.9-3.3 8.9-7.4S16.9 3.5 12 3.5z"/>',
    'messenger' => '<path d="M12 3.5c-4.7 0-8.5 3.5-8.5 7.9 0 2.5 1.2 4.7 3.1 6.1v3l2.9-1.6c.8.2 1.6.3 2.5.3 4.7 0 8.5-3.5 8.5-7.8S16.7 3.5 12 3.5zm.9 10.5l-2.2-2.3-4.2 2.3 4.6-4.9 2.2 2.3 4.2-2.3z"/>',
];

$actions = [
    [
        'key'   => 'call',
        'href'  => $channels['hotline'],
        'label' => 'Gọi ' . $hotline,
        'svg'   => icon('phone', 'fab__ico', 20),
        'blank' => false,
    ],
    [
        'key'   => 'zalo',
        'href'  => $channels['zalo'],
        'label' => 'Nhắn Zalo',
        'svg'   => sprintf(
            '<svg class="fab__ico" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" '
            . 'aria-hidden="true" focusable="false">%s</svg>',
            $brandMarks['zalo']
        ),
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
            aria-label="Mở kênh hỗ trợ nhanh" hidden>
        <span class="fab__toggle-ico fab__toggle-ico--open"><?= icon('message', '', 22) ?></span>
        <span class="fab__toggle-ico fab__toggle-ico--close"><?= icon('close', '', 22) ?></span>
    </button>
</div>
