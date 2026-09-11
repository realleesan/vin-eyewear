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
 *
 * ───────────────────────────────────────────────────────────────────────────
 * CHỈ CÓ ICON, KHÔNG CÓ CHỮ
 *
 * Ba nút là ba vòng tròn đen 40px, icon trắng, không nhãn nhìn thấy được.
 *
 * NHÃN VẪN NẰM TRONG DOM, chỉ bị .sr-only giấu đi — đừng xoá nó. Nó là TÊN
 * của liên kết: bỏ đi thì trình đọc màn hình đọc ra ba "liên kết" trơ trọi
 * không biết dẫn đi đâu, vì icon đều mang aria-hidden.
 *
 * Thêm `title` để chuột rê vào có chú giải của trình duyệt — với nút chỉ có
 * icon thì đó là cách duy nhất người dùng chuột biết nó làm gì.
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
         * Cả ba nút nay cùng một màu (trắng trên đen — xem floating.css), nên
         * icon là thứ DUY NHẤT phân biệt chúng. Một bong bóng trơn thì không
         * phân biệt được gì với nút Messenger ngay dưới.
         *
         * ─── CHỮ KHOÉT RỖNG, KHÔNG TÔ XANH ───
         *
         * Bong bóng ăn `currentColor` (trắng); chữ "Zalo" ăn var(--fab-knock)
         * — biến này bằng đúng MÀU NỀN của nút, nên chữ trông như bị khoét
         * thủng qua bong bóng thay vì được tô đè lên.
         *
         * Phải là một biến chứ không phải mã màu gõ cứng: rê chuột thì nền
         * nút đổi #111 -> #333, và floating.css đổi --fab-knock theo. Gõ cứng
         * #111 thì lúc rê chuột chữ hoá ra một vệt đen lạc trên nền xám.
         *
         * ─── CỠ: CÂN THEO CHIỀU CAO, KHÔNG CÂN THEO BỀ NGANG ───
         *
         * Khung 32×24 chứ không 24×24 như hai icon kia — chữ "Zalo" nằm ngang
         * nên cần khung rộng hơn cao. Hệ quả: đặt cùng một con số `width` cho
         * cả ba là logo Zalo to vượt hẳn. Đo thật ở bản trước (width=20): nét
         * vẽ Zalo rộng 19,7px trong khi điện thoại 13,2 và Messenger 12,8 —
         * rộng hơn một nửa, và đó đúng là chỗ trông xấu.
         *
         * Nên chốt theo CHIỀU CAO nét vẽ. 17 × 12,75 cho nét cao 12,4px, khớp
         * với 12,7 và 12,8 của hai icon kia; bề ngang cứ để nó rộng hơn, vì
         * một wordmark bốn chữ cái thì phải rộng hơn một cái bong bóng.
         *
         * ĐỪNG hạ tiếp xuống cho "bằng bề ngang": chữ "Zalo" cao khoảng 40%
         * khung, ở width 15 nó còn chưa tới 4,7px và nhoè thành một vệt.
         */
        'svg'   => '<svg class="fab__ico fab__ico--zalo" width="17" height="12.75" '
                 . 'viewBox="0 0 32 24" fill="none" aria-hidden="true" focusable="false">'
                 . '<path fill="currentColor" d="M5.2 1.5h21.6a5 5 0 0 1 5 5v9.4a5 5 0 0 1-5 5H12.4l-6.6 3.9a.55.55 0 0 1-.83-.53l.37-3.42A5 5 0 0 1 .2 15.9V6.5a5 5 0 0 1 5-5z"/>'
                 . '<path fill="var(--fab-knock)" d="M6.4 6.6h6.5v1.9l-4 4.7h4.1v2H6.1v-1.9l4-4.7H6.4z"/>'
                 . '<path fill="var(--fab-knock)" d="M20.4 5.6h2.1v9.6h-2.1z"/>'
                 . '<path fill="var(--fab-knock)" d="M17.4 8.6c-1.1 0-2 .32-2.7.86l.72 1.4c.46-.33 1-.52 1.6-.52.8 0 1.25.36 1.25.95v.16h-1.4c-1.7 0-2.62.72-2.62 1.9 0 1.15.88 1.92 2.2 1.92.85 0 1.5-.3 1.86-.82v.7h1.9V11.6c0-1.9-1.06-3-2.8-3zm.88 4.5c0 .6-.5 1-1.2 1-.5 0-.83-.25-.83-.63 0-.36.28-.6.95-.6h1.08z"/>'
                 . '<path fill="var(--fab-knock)" d="M26.6 8.6a3.35 3.35 0 1 0 0 6.7 3.35 3.35 0 0 0 0-6.7zm0 4.9a1.55 1.55 0 1 1 0-3.1 1.55 1.55 0 0 1 0 3.1z"/>'
                 . '</svg>',
        'blank' => true,
    ],
    [
        'key'   => 'messenger',
        'href'  => $channels['messenger'],
        'label' => 'Chat Messenger',
        'svg'   => sprintf(
            '<svg class="fab__ico" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" '
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
    <button type="button" class="fab__btn fab__btn--top tap-target" id="fabTop"
            title="Lên đầu trang" hidden>
        <?= icon('arrow-up', 'fab__ico', 18) ?>
        <span class="fab__label sr-only">Lên đầu trang</span>
    </button>

    <ul class="fab__list" id="fabList" role="list">
        <?php foreach ($actions as $a): ?>
            <li>
                <a class="fab__btn fab__btn--<?= e($a['key']) ?> tap-target"
                   href="<?= e($a['href']) ?>"
                   title="<?= e($a['label']) ?>"
                   <?= $a['blank'] ? 'target="_blank" rel="noreferrer noopener"' : '' ?>>
                    <?= $a['svg'] ?>
                    <span class="fab__label sr-only"><?= e($a['label']) ?></span>
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
