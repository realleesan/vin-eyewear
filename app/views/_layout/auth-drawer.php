<?php

/**
 * _layout/auth-drawer.php — KHUÔN của ngăn kéo đăng nhập/đăng ký.
 *
 * CSS: assets/css/components/auth-drawer.css
 * JS:  assets/js/auth-drawer.js
 * Nội dung: chính trang /auth, nạp ngầm qua nhánh `X-Auth` ở master.php
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * VÌ SAO Ở ĐÂY, KHÔNG PHẢI TRONG <header>
 *
 * File này in ra ở CẤP <body>, cạnh #modal-root, chứ không nằm trong cây DOM
 * của thanh đầu trang. Lý do là kỹ thuật chứ không phải gu:
 *
 *   · thanh đầu trang là `position: sticky` và có lúc mang `transform`
 *     (hiệu ứng ẩn/hiện khi cuộn). Một tổ tiên có transform biến mọi
 *     `position: fixed` con cháu thành "fixed so với tổ tiên đó" — tấm sẽ
 *     bị neo vào thanh nav thay vì vào khung nhìn;
 *   · thanh còn có `overflow` ở vài nhánh, và tấm bị cắt cụt ở đó;
 *   · z-index của tấm phải so với cả trang, không phải chỉ trong thanh.
 *
 * Đây đúng là điều mà React gọi là portal; ở đây không cần thư viện nào —
 * chỉ cần in ở đúng chỗ.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * <template> CHỨ KHÔNG PHẢI MỘT KHỐI ẨN
 *
 * Ruột của <template> KHÔNG nằm trong DOM thật: trình duyệt không dựng bố
 * cục, không tải ảnh, không cho tiêu điểm bàn phím đi vào, trình đọc màn
 * hình không thấy. auth-drawer.js nhân bản nó vào #modal-root lúc mở và GỠ
 * HẲN khi đóng xong.
 *
 * Khác hẳn cách cũ (tấm nằm sẵn trong DOM, chỉ ẩn bằng visibility): ở cách
 * cũ, mọi trang của site đều mang theo một hộp thoại đăng nhập vô hình mà
 * Tab vẫn có thể đi lạc vào.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * TẮT JAVASCRIPT VẪN ĐI ĐƯỢC
 *
 * Thẻ mở là <a href="/auth"> THẬT (xem _layout/header-auth.php). Không có JS
 * thì bấm vào là sang trang /auth như thường — file này chỉ nằm im.
 */
?>

<?php /* Cửa ra của mọi lớp phủ dựng bằng JS. Rỗng trong HTML gốc; đặt CUỐI
         <body> để nó luôn vẽ sau mọi nội dung trang. */ ?>
<div id="modal-root"></div>

<template id="authDrawerTpl">
    <div class="authov" data-authov>

        <?php /* Nền mờ là <button> chứ không phải <div>: nó bấm được, nên nó
                 phải là một nút thật — trình đọc màn hình gọi đúng tên thay
                 vì đọc "hình ảnh".

                 tabindex="-1" để nó KHÔNG nằm trong vòng Tab: người dùng bàn
                 phím đã có nút ✕ ngay đầu tấm, và hai nút cùng tên "Đóng"
                 trong một vòng Tab bốn phần tử là tiếng ồn. Bấm chuột vẫn
                 chạy — tabindex chỉ đụng tới bàn phím. */ ?>
        <button type="button" class="authov__backdrop" data-authov-close
                tabindex="-1" aria-label="<?= e(t('ui.close')) ?>"></button>

        <div class="authov__panel" role="dialog" aria-modal="true"
             aria-labelledby="authovTitle" tabindex="-1" data-authov-panel>

            <?php
            /* ┌─ THANH ĐẦU TẤM ──────────────────────────────────────────────
               │ Tên màn bên trái, ✕ bên phải — bản thiết kế 12/09/2026.
               │
               │ Ô chữ để TRỐNG trong khuôn: tấm này sống qua bốn màn (định
               │ danh · mật khẩu · đăng ký · nhập mã), mỗi màn một tên, mà
               │ khuôn thì được nhân bản MỘT lần rồi dùng lại. auth-drawer.js
               │ điền nó sau mỗi lượt nạp mảnh, lấy từ data-short của
               │ <h1 id="authovTitle"> — xem datTenMan() bên đó.
               │
               │ aria-hidden: tên màn này là bản RÚT GỌN của chính cái h1 nằm
               │ ngay dưới, mà hộp thoại đã lấy h1 ấy làm nhãn (aria-labelledby
               │ bên trên). Không giấu thì trình đọc màn hình đọc "Đăng nhập,
               │ Đăng nhập hoặc tạo tài khoản" — một cái tên nói hai lần.
               │
               │ Quãng ngắn trước khi mảnh về, ô chữ rỗng và tấm chỉ có dòng
               │ "Đang tải…"; aria-busy đã nói đúng trạng thái ấy. */
            ?>
            <div class="authov__head">
                <span class="authov__title" data-authov-title aria-hidden="true"></span>

                <button type="button" class="authov__x" data-authov-close
                        aria-label="<?= e(t('ui.close')) ?>">✕</button>
            </div>

            <?php /* CHỈ KHỐI NÀY CUỘN, không phải cả tấm — nhờ vậy thanh đầu
                     tấm và nút ✕ đứng yên khi nội dung dài. */ ?>
            <div class="authov__scroll">
                <div class="authov__body" data-auth-body aria-live="polite" aria-busy="true">
                    <p class="authov__wait"><?= e(t('ui.loading')) ?></p>
                </div>
            </div>
        </div>
    </div>
</template>
