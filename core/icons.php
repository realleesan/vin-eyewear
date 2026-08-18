<?php

/**
 * core/icons.php
 *
 * Bộ icon SVG nội tuyến, thay cho gói npm `lucide-react` của bản Lovable.
 *
 * Vì sao nội tuyến chứ không dùng sprite hay font icon:
 *   - Không thêm một request mạng nào; icon đi kèm HTML.
 *   - `stroke="currentColor"` khiến icon tự ăn theo màu chữ của phần tử cha,
 *     nên đổi màu bằng CSS mà không phải sinh lại file.
 *   - Không cần bước build — đúng ràng buộc của dự án này.
 *
 * Đường dẫn lấy từ bộ Lucide (giấy phép ISC), vẽ trên khung 24×24,
 * nét 1.5, đầu và khớp nét bo tròn.
 *
 * Thêm icon mới: chèn một dòng vào ICONS rồi gọi icon('ten-icon').
 */

/**
 * Kho đường dẫn SVG. Khoá là tên icon dùng trong view.
 *
 * Mỗi giá trị là phần BÊN TRONG thẻ <svg> — có thể gồm nhiều phần tử.
 */
const ICONS = [
    // Bảo hành, sửa chữa
    'wrench'      => '<path d="M14.7 6.3a4 4 0 01-5 5L5 16a2.1 2.1 0 003 3l4.7-4.7a4 4 0 005-5l-2.4 2.4-2.1-.6-.6-2.1z"/>',
    'shield'      => '<path d="M12 3l7.5 3v5.2c0 4.4-3.1 8.5-7.5 9.8-4.4-1.3-7.5-5.4-7.5-9.8V6z"/><path d="M9.2 12l2 2 3.6-3.6"/>',
    'refresh'     => '<path d="M20 11a8 8 0 00-13.7-5.3L4 8"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0013.7 5.3L20 16"/><path d="M20 20v-4h-4"/>',
    'eye'         => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
    'truck'       => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/>',
    'award'       => '<circle cx="12" cy="9" r="5.5"/><path d="M8.6 13.7L7.5 21l4.5-2.4 4.5 2.4-1.1-7.3"/>',
    'handshake'   => '<path d="M11 6.5L8.5 9a2 2 0 000 2.8l.2.2a2 2 0 002.8 0L13 10.5"/><path d="M13 10.5l3.2 3.2a1.7 1.7 0 01-2.4 2.4L13 15.3"/><path d="M13.5 15.8l1.3 1.3a1.6 1.6 0 01-2.3 2.3l-1.3-1.3"/><path d="M2.5 8.5L6 5.5l5 1 3-1 3.5 3M21.5 8.5L18 5.5"/>',

    // Giao diện
    'search'      => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L20 20"/>',
    'chevron-down' => '<path d="M7 10l5 5 5-5"/>',
    'phone'       => '<path d="M6.5 4h3l1.5 3.5-2 1.5a10 10 0 006 6l1.5-2 3.5 1.5v3a1.5 1.5 0 01-1.7 1.5A16 16 0 015 6.7 1.5 1.5 0 016.5 4z"/>',
    'message'     => '<path d="M20.5 12a8 8 0 01-8.5 8 9 9 0 01-3.7-.8L3.5 20.5l1.3-4.6A8 8 0 0112 4a8 8 0 018.5 8z"/>',
    'map-pin'     => '<path d="M12 21s-6.5-5.6-6.5-10a6.5 6.5 0 1113 0c0 4.4-6.5 10-6.5 10z"/><circle cx="12" cy="11" r="2.4"/>',
    'timer'       => '<circle cx="12" cy="13" r="7.5"/><path d="M12 9.5V13l2.5 1.5"/><path d="M9.5 2.5h5"/>',
    'zap'         => '<path d="M13 2.5L5 13h6l-1 8.5L19 11h-6z"/>',
    'sun'         => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.2 5.2l1.4 1.4M17.4 17.4l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"/>',
    'scan-eye'    => '<path d="M3.5 8V5.5A2 2 0 015.5 3.5H8M16 3.5h2.5a2 2 0 012 2V8M20.5 16v2.5a2 2 0 01-2 2H16M8 20.5H5.5a2 2 0 01-2-2V16"/><circle cx="12" cy="12" r="2.2"/><path d="M7 12s2-3 5-3 5 3 5 3-2 3-5 3-5-3-5-3z"/>',
    'glasses'     => '<circle cx="6.5" cy="14" r="3.5"/><circle cx="17.5" cy="14" r="3.5"/><path d="M10 14h4"/><path d="M3 11l2-4.5M21 11l-2-4.5"/>',
    'arrow-left'  => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
    'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'arrow-up'    => '<path d="M12 19V5M6 11l6-6 6 6"/>',
    'close'       => '<path d="M6 6l12 12M18 6L6 18"/>',
    'mail'        => '<rect x="3.5" y="5.5" width="17" height="13" rx="1"/><path d="M4 6.5l8 6 8-6"/>',
    'sparkles'    => '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M18 15.5l.8 2.1 2.2.9-2.2.9-.8 2.1-.8-2.1-2.2-.9 2.2-.9z"/>',
    'newspaper'   => '<path d="M4 5.5h13v13H5.5A1.5 1.5 0 014 17z"/><path d="M17 8.5h3v8.5a1.5 1.5 0 01-3 0z"/><path d="M7 8.5h7M7 11.5h7M7 14.5h4"/>',
    'quote'       => '<path d="M9.5 6.5C7 7.6 5.5 9.9 5.5 12.6V17h5v-5H8c0-1.9.6-3.3 2.3-4.2z"/><path d="M18 6.5c-2.5 1.1-4 3.4-4 6.1V17h5v-5h-2.5c0-1.9.6-3.3 2.3-4.2z"/>',
    'check'       => '<path d="M4.5 12.5l5 5 10-10"/>',
    // Hai tờ giấy xếp lệch — nút "sao chép mã đơn" ở trang xác nhận đặt hàng
    'copy'        => '<rect x="9" y="9" width="12" height="12" rx="1.5"/><path d="M5 15V4.5A1.5 1.5 0 016.5 3H15"/>',
    'layers'      => '<path d="M12 3.5l8 4-8 4-8-4z"/><path d="M4 12l8 4 8-4"/><path d="M4 16.5l8 4 8-4"/>',
    'ruler'       => '<path d="M4 14.5L14.5 4l5.5 5.5L9.5 20z"/><path d="M8 10.5l1.8 1.8M11 7.5l1.8 1.8M13.8 13.3l1.8 1.8"/>',
    'filter'      => '<path d="M3.5 5.5h17l-6.5 7.5V19l-4 2v-8z"/>',
    'badge-check' =>'<path d="M12 3l2.2 1.8 2.8-.2.6 2.8 2.3 1.7-1.3 2.5 1.3 2.5-2.3 1.7-.6 2.8-2.8-.2L12 21l-2.2-1.8-2.8.2-.6-2.8L4.1 15l1.3-2.5L4.1 10l2.3-1.7.6-2.8 2.8.2z"/><path d="M9 12l2 2 4-4"/>',
];

/**
 * Ngôi sao đánh giá — tách riêng vì cần TÔ ĐẶC khi sáng và để rỗng khi tắt,
 * trong khi icon() ở trên luôn dựng SVG dạng nét (fill="none").
 *
 * @param bool $filled sao sáng hay sao mờ
 */
function starIcon(bool $filled, string $class = '', int $size = 16): string
{
    return sprintf(
        '<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" '
        . 'fill="%s" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">'
        . '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.7l5.9-.9z"/>'
        . '</svg>',
        e($class),
        $size,
        $size,
        $filled ? 'currentColor' : 'none'
    );
}

/**
 * In một icon SVG.
 *
 *   <?= icon('shield', 'policy-card__ico') ?>
 *
 * @param string $name  khoá trong ICONS
 * @param string $class class CSS gắn vào thẻ <svg>
 * @param int    $size  cạnh của icon, tính bằng px
 */
function icon(string $name, string $class = '', int $size = 24): string
{
    // Tên sai thường do gõ nhầm. Ở chế độ gỡ lỗi thì báo to để sửa ngay;
    // trên production thì im lặng bỏ qua, vì một icon thiếu không đáng làm
    // sập cả trang.
    if (!isset(ICONS[$name])) {
        if (config('app.debug')) {
            throw new InvalidArgumentException("Không có icon tên '{$name}' trong core/icons.php");
        }
        return '';
    }

    return sprintf(
        '<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.5" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
        e($class),
        $size,
        $size,
        ICONS[$name]
    );
}
