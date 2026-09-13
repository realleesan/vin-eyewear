<?php

/**
 * _layout/login-gate.php — popup "chỉ dành cho thành viên".
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * KHI NÀO HIỆN
 *
 * Khách CHƯA ĐĂNG NHẬP bấm "Mua ngay" hay "Thêm vào giỏ".
 * CartController::add() chặn ngay đầu hàm, đặt cờ `cong_dang_nhap` rồi trả
 * khách về đúng trang họ vừa bấm; master.php (và _layout/buy-fragment.php cho
 * đường không tải lại trang) thấy cờ thì gọi tới file này.
 *
 * Cờ mang theo ĐƯỜNG QUAY LẠI, nên nút đăng nhập dưới đây dẫn tới
 * /auth?redirect=… — đăng nhập xong khách về đúng trang sản phẩm đang xem chứ
 * không rơi ra trang chủ.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * KÍNH MỜ LẤY ĐÚNG CÔNG THỨC CỦA BẢNG XỔ MEGA MENU
 *
 * Theo yêu cầu chủ dự án (13/09/2026): "blur như thế nào thì lấy blur theo
 * dropdown của mega menu trên header". Nên .lgate__panel dùng thẳng hai token
 * --glass-bg / --glass-blur — cùng hai token mà .mega__panel và .oa-header
 * dùng. ⚠ ĐỪNG gõ số thẳng vào CSS của popup này: gõ thẳng là cách chắc chắn
 * ba chỗ sẽ lệch nhau lần sau ai đó chỉnh độ mờ. Sửa ở token, cả ba đi theo.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * TẮT JAVASCRIPT VẪN DÙNG ĐƯỢC
 *
 * Không có dòng JS nào là bắt buộc ở đây. Cờ là FLASH — đọc một lần rồi mất —
 * nên nút đóng chỉ cần là một liên kết về chính trang này: tải lại, cờ đã tiêu,
 * popup không vẽ nữa. buy-flow.js chỉ làm việc ấy nhanh hơn (gỡ thẳng khỏi DOM,
 * không đi một vòng máy chủ).
 *
 * Nhận vào: $veLai — đường quay lại, đã qua safeRedirectPath ở controller.
 */

$veLai = (string) ($veLai ?? '/san-pham');

?>
<div class="lgate" data-lgate>

    <?php /* LỚP NỀN BẮT CHUỘT — bấm ra ngoài là đóng.
             aria-hidden + tabindex="-1": với trình đọc màn hình và phím Tab thì
             nó không tồn tại, nếu không mỗi lần mở popup người dùng bàn phím
             phải đi qua một liên kết vô danh phủ kín màn hình. Đường đóng dành
             cho họ là nút ✕ ngay dưới. */ ?>
    <a class="lgate__scrim" href="<?= e($veLai) ?>" aria-hidden="true" tabindex="-1" data-lgate-close></a>

    <div class="lgate__panel" role="dialog" aria-modal="true" aria-labelledby="lgate-msg" tabindex="-1">

        <a class="lgate__close" href="<?= e($veLai) ?>" data-lgate-close
           aria-label="<?= e(t('gate.close')) ?>">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor"
                 stroke-width="1.2" aria-hidden="true">
                <line x1="1" y1="1" x2="11" y2="11"></line>
                <line x1="11" y1="1" x2="1" y2="11"></line>
            </svg>
        </a>

        <p class="lgate__msg" id="lgate-msg"><?= e(t('gate.msg')) ?></p>

        <?php
        /* ┌─ MỞ NGĂN KÉO TẠI CHỖ, KHÔNG CHUYỂN TRANG (13/09/2026) ────────────
           │ Theo yêu cầu chủ dự án: "khi click vào button đó, tôi cần mở cái
           │ overlay đăng ký/đăng nhập ra chứ không phải chuyển sang trang
           │ khác."
           │
           │ [data-authov-open] là toàn bộ phần việc phải làm: assets/js/
           │ auth-drawer.js uỷ quyền từ document và bắt mọi phần tử mang thuộc
           │ tính ấy — đúng cách icon tài khoản trên thanh đầu trang vẫn mở
           │ ngăn kéo. Không thêm một dòng JS nào cho riêng popup này.
           │
           │ VẪN LÀ <a href> THẬT, và đó là lối lùi khi JavaScript không chạy:
           │ bấm vào là sang trang /auth như trước. ⚠ Đừng đổi thành <button>
           │ cho "gọn" — làm thế là bỏ mất lối lùi ấy.
           │
           │ ?redirect= giữ nguyên cho ĐÚNG lối lùi đó. Khi ngăn kéo mở được
           │ thì tham số này không được dùng tới: ruột ngăn kéo do máy chủ in
           │ sẵn (AuthController::hatGiongNganKeo) và nó tự lấy trang đang
           │ đứng làm đích — cùng một trang, cùng một kết quả.
           └──────────────────────────────────────────────────────────────── */
        ?>
        <a class="lgate__cta" href="/auth?redirect=<?= e(rawurlencode($veLai)) ?>"
           data-authov-open aria-haspopup="dialog">
            <?= e(t('gate.cta')) ?>
        </a>
    </div>
</div>
