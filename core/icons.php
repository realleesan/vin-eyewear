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
    // Khay hứng + mũi tên xuống — nút "Tải biên nhận" ở trang biên nhận.
    // Mũi tên CẮM XUỐNG khay chứ không nổi lơ lửng: cùng hình này quay ngược
    // lên là "tải lên", và hai nghĩa trái nhau thì không được nhìn giống nhau.
    'download'    => '<path d="M4 15.5V19a1.5 1.5 0 001.5 1.5h13A1.5 1.5 0 0020 19v-3.5"/><path d="M8 11l4 4 4-4"/><path d="M12 15V3.5"/>',
    // Ba nút nối nhau — nút "Chia sẻ". Không dùng biểu tượng chia sẻ của iOS
    // (hộp có mũi tên bay lên): nó trùng gần hết với icon 'download' lật ngược,
    // mà hai nút này đứng cạnh nhau trên cùng một hàng.
    'share'       => '<circle cx="18" cy="5.5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="18.5" r="2.5"/><path d="M8.2 10.8l7.6-3.6M8.2 13.2l7.6 3.6"/>',
    'layers'      => '<path d="M12 3.5l8 4-8 4-8-4z"/><path d="M4 12l8 4 8-4"/><path d="M4 16.5l8 4 8-4"/>',
    'ruler'       => '<path d="M4 14.5L14.5 4l5.5 5.5L9.5 20z"/><path d="M8 10.5l1.8 1.8M11 7.5l1.8 1.8M13.8 13.3l1.8 1.8"/>',
    'filter'      => '<path d="M3.5 5.5h17l-6.5 7.5V19l-4 2v-8z"/>',
    // ── Thanh bên khu quản trị ──────────────────────────────────────────
    // Lấy nguyên đường dẫn từ bản thiết kế "Vin Eyewear Admin.dc.html": mỗi
    // mục điều hướng một hình riêng. Trước đây thanh bên dùng lại icon của
    // trang bán hàng nên có hai mục cùng đeo 'layers' — nhìn lướt không phân
    // biệt được "Sản phẩm" với "Tổng quan".
    'grid'        => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/>',
    'cart'        => '<path d="M3 4h2l2.3 11.5h11.4L21 8H6"/><circle cx="9.5" cy="19.5" r="1.3"/><circle cx="16.5" cy="19.5" r="1.3"/>',
    'clock'       => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 1.8"/>',
    'chat'        => '<path d="M21 12a8 8 0 0 1-8 8H4l1.6-3.2A8 8 0 1 1 21 12z"/>',
    'box'         => '<path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
    'crate'       => '<path d="M4 8h16v12H4zM4 8l2-4h12l2 4M9 12h6"/>',
    'tag'         => '<path d="M3 3h8l10 10-8 8L3 11V3z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
    'calendar'    => '<path d="M4 6h16v15H4zM4 10.5h16M8 3v4M16 3v4"/>',
    'percent'     => '<path d="M5 19L19 5"/><circle cx="7.5" cy="7.5" r="2.2"/><circle cx="16.5" cy="16.5" r="2.2"/>',
    'star'        => '<path d="M12 3l2.7 5.5 6 .9-4.3 4.3 1 6-5.4-2.9-5.4 2.9 1-6L3.3 9.4l6-.9L12 3z"/>',
    'users'       => '<circle cx="9" cy="8" r="3.5"/><path d="M3 20a6.2 6.2 0 0 1 12 0M15.5 5a3.5 3.5 0 0 1 0 6.6M16.5 14.6a6 6 0 0 1 4.5 5.4"/>',
    /* MỘT người, khác hẳn 'users' (nhóm) ngay trên. Hai mục trong thanh bên
       quản trị nói về người: "Khách hàng" và "Tài khoản nội bộ". Đeo chung một
       icon thì nhìn lướt không phân biệt được, mà đó đúng là hai danh sách
       không được nhầm với nhau. */
    'user'        => '<circle cx="12" cy="8" r="3.6"/><path d="M5 20a7 7 0 0 1 14 0"/>',
    'key'         => '<circle cx="8" cy="15" r="4"/><path d="M11.2 11.8L19 4M16 5.5L18.5 8M13.5 8L15.5 10"/>',

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

/**
 * ─────────────────────────────────────────────────────────────────────────────
 * ICON THƯƠNG HIỆU
 *
 * Bộ ICONS ở trên cố tình đơn sắc: nét `currentColor` để icon ăn theo màu chữ.
 * Logo của Gmail, Messenger, Zalo thì NGƯỢC LẠI — màu là một phần của nhận
 * dạng thương hiệu, đổi màu là sai logo. Nên chúng nằm riêng ở đây, tô bằng
 * `fill` màu cứng, và `brandIcon()` KHÔNG đặt stroke/currentColor.
 *
 * Vì sao không nhét chung vào ICONS: hàm icon() bọc mọi thứ trong một <svg>
 * viewBox 0 0 24 24, không stroke-width 1.5, không fill. Logo gốc mỗi cái một
 * khung toạ độ (Gmail vẽ trên 512), nhiều lớp màu, có cả gradient — gộp chung
 * thì icon() phải cõng thêm ba tham số chỉ để phục vụ bốn cái logo.
 *
 * Nguồn hình:
 *   - Gmail: bộ nhận diện Google, 5 mảnh màu chuẩn (xanh · lục · vàng · đỏ ·
 *     đỏ sẫm) trên nền phong bì trắng.
 *   - Messenger: dáng bong bóng + tia chớp của Meta, tô gradient chính thức.
 *   - Zalo: chữ "Zalo" trắng trên nền bo góc #0068FF — đúng icon ứng dụng.
 *   - Hotline KHÔNG phải một ứng dụng nên không có logo gốc. Dùng nút gọi
 *     quen mắt nhất: ống nghe trắng trong vòng tròn xanh lá, giống nút nhấc
 *     máy của iOS/Android.
 *
 * `{id}` trong nội dung là chỗ dành cho một mã duy nhất; brandIcon() thay nó
 * bằng số đếm tăng dần. Nếu để id cố định mà một trang in cùng logo hai lần
 * (ví dụ ở phần liên hệ và ở chân trang) thì hai <svg> trùng id gradient —
 * trình duyệt lấy cái đầu tiên, cái sau mất màu.
 * ─────────────────────────────────────────────────────────────────────────────
 */
const BRAND_ICONS = [
    'gmail' => [
        'box'  => '0 0 512 512',
        'body' =>
            // Thân phong bì màu trắng, nằm dưới cùng — nếu thiếu, nền hồng của
            // thẻ sẽ lọt qua khoảng giữa chữ M.
            '<path fill="#ffffff" d="M116.36 448h279.28V99.56L256 204.33 116.36 99.56z"/>'
            . '<path fill="#4285f4" d="M34.91 448h81.45V251.11L0 163.45V413.09C0 432.36 15.64 448 34.91 448z"/>'
            . '<path fill="#34a853" d="M395.64 448h81.45c19.27 0 34.91-15.64 34.91-34.91V163.45l-116.36 87.66V448z"/>'
            . '<path fill="#fbbc04" d="M395.64 99.56v151.55L512 163.45v-46.54c0-43.15-49.25-67.75-83.75-41.89l-32.61 24.54z"/>'
            . '<path fill="#ea4335" d="M116.36 251.11V99.56L256 204.33l139.64-104.77v151.55L256 355.88z"/>'
            . '<path fill="#c5221f" d="M0 116.91v46.54l116.36 87.66V99.56L83.75 75.02C49.19 49.16 0 73.76 0 116.91z"/>',
    ],

    'messenger' => [
        'box'  => '0 0 24 24',
        'body' =>
            // Hai lớp cùng một dáng bong bóng: lớp trắng ở dưới để tia chớp —
            // vốn là lỗ khoét trên lớp gradient — hiện ra màu trắng thay vì
            // để lọt màu nền thẻ.
            '<defs><radialGradient id="mess{id}" cx="11.087%" cy="97.14%" r="121.416%">'
            . '<stop offset="0%" stop-color="#0099ff"/>'
            . '<stop offset="60.975%" stop-color="#a033ff"/>'
            . '<stop offset="93.482%" stop-color="#ff5280"/>'
            . '<stop offset="100%" stop-color="#ff7061"/>'
            . '</radialGradient></defs>'
            . '<path fill="#ffffff" d="M12 0C5.24 0 0 4.952 0 11.64c0 3.499 1.434 6.521 3.769 8.61a.96.96 0 0 1 .323.683l.065 2.135a.96.96 0 0 0 1.347.85l2.381-1.053a.96.96 0 0 1 .641-.046A13 13 0 0 0 12 23.28c6.76 0 12-4.952 12-11.64S18.76 0 12 0z"/>'
            . '<path fill="url(#mess{id})" d="M12 0C5.24 0 0 4.952 0 11.64c0 3.499 1.434 6.521 3.769 8.61a.96.96 0 0 1 .323.683l.065 2.135a.96.96 0 0 0 1.347.85l2.381-1.053a.96.96 0 0 1 .641-.046A13 13 0 0 0 12 23.28c6.76 0 12-4.952 12-11.64S18.76 0 12 0m6.806 7.44c.522-.03.971.567.63 1.094l-4.178 6.457a.707.707 0 0 1-.977.208l-3.87-2.504a.44.44 0 0 0-.49.007l-4.363 3.01c-.637.438-1.415-.317-.995-.966l4.179-6.457a.706.706 0 0 1 .977-.21l3.87 2.505c.15.097.344.094.491-.007l4.362-3.008a.7.7 0 0 1 .364-.13"/>',
    ],

    'zalo' => [
        'box'  => '0 0 24 24',
        'body' =>
            '<rect width="24" height="24" rx="5.6" fill="#0068ff"/>'
            // Chữ "Zalo" gốc vẽ tràn khung 24×24; thu 0.68 rồi dời vào giữa để
            // chừa lề trong cho nền bo góc — không thu thì chữ chạm mép.
            . '<g transform="translate(3.84 3.84) scale(0.68)"><path fill="#ffffff" '
            . 'd="M12.49 10.2722v-.4496h1.3467v6.3218h-.7704a.576.576 0 01-.5763-.5729l-.0006.0005a3.273 3.273 0 01-1.9372.6321c-1.8138 0-3.2844-1.4697-3.2844-3.2823 0-1.8125 1.4706-3.2822 3.2844-3.2822a3.273 3.273 0 011.9372.6321l.0006.0005zM6.9188 7.7896v.205c0 .3823-.051.6944-.2995 1.0605l-.03.0343c-.0542.0615-.1815.206-.2421.2843L2.024 14.8h4.8948v.7682a.5764.5764 0 01-.5767.5761H0v-.3622c0-.4436.1102-.6414.2495-.8476L4.8582 9.23H.1922V7.7896h6.7266zm8.5513 8.3548a.4805.4805 0 01-.4803-.4798v-7.875h1.4416v8.3548H15.47zM20.6934 9.6C22.52 9.6 24 11.0807 24 12.9044c0 1.8252-1.4801 3.306-3.3066 3.306-1.8264 0-3.3066-1.4808-3.3066-3.306 0-1.8237 1.4802-3.3044 3.3066-3.3044zm-10.1412 5.253c1.0675 0 1.9324-.8645 1.9324-1.9312 0-1.065-.865-1.9295-1.9324-1.9295s-1.9324.8644-1.9324 1.9295c0 1.0667.865 1.9312 1.9324 1.9312zm10.1412-.0033c1.0737 0 1.945-.8707 1.945-1.9453 0-1.073-.8713-1.9436-1.945-1.9436-1.0753 0-1.945.8706-1.945 1.9436 0 1.0746.8697 1.9453 1.945 1.9453z"/></g>',
    ],

    'hotline' => [
        'box'  => '0 0 24 24',
        'body' =>
            '<circle cx="12" cy="12" r="12" fill="#22c55e"/>'
            . '<path fill="#ffffff" d="M8.62 5.5a1.35 1.35 0 011.86.5l1.05 1.86a1.35 1.35 0 01-.32 1.7l-.93.76a8.3 8.3 0 003.4 3.4l.76-.93a1.35 1.35 0 011.7-.32l1.86 1.05a1.35 1.35 0 01.5 1.86l-.6 1.03a2.2 2.2 0 01-2.5 1.02C11.5 17.4 6.6 12.5 5.07 7.1a2.2 2.2 0 011.02-2.5z"/>',
    ],
];

/**
 * In một logo thương hiệu.
 *
 *   <?= brandIcon('zalo', 'cchan__logo', 34) ?>
 *
 * Khác icon(): không stroke, không currentColor — màu nằm sẵn trong hình.
 *
 * @param string $name  khoá trong BRAND_ICONS
 * @param string $class class CSS gắn vào thẻ <svg>
 * @param int    $size  cạnh của icon, tính bằng px
 */
function brandIcon(string $name, string $class = '', int $size = 24): string
{
    // Cùng lý lẽ với icon(): gõ sai tên thì la lên khi gỡ lỗi, im lặng khi chạy thật.
    if (!isset(BRAND_ICONS[$name])) {
        if (config('app.debug')) {
            throw new InvalidArgumentException("Không có logo tên '{$name}' trong core/icons.php");
        }
        return '';
    }

    // Đếm theo lượt gọi trong một request — đủ để hai lần in cùng một logo
    // không đụng id gradient. Không cần duy nhất toàn cục.
    static $luot = 0;
    $luot++;

    return sprintf(
        '<svg class="%s" width="%d" height="%d" viewBox="%s" fill="none" '
        . 'aria-hidden="true" focusable="false">%s</svg>',
        e($class),
        $size,
        $size,
        e(BRAND_ICONS[$name]['box']),
        str_replace('{id}', (string) $luot, BRAND_ICONS[$name]['body'])
    );
}
