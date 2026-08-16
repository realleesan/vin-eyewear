<?php

/**
 * _layout/home/newsletter.php — S20 đăng ký nhận tin.
 *
 * Đặt cuối trang chủ, ngay trên footer (xem thứ tự render trong
 * docs/prototype/home-sections.md).
 *
 * Partial TỰ ĐỌC flash chứ không nhận qua controller: flash() đọc một lần là
 * xoá, nếu HomeController đọc trước thì khối này không còn gì để hiện. Đọc
 * ngay tại đây cũng là điều kiện để sau này nhúng thêm ô đăng ký ở footer mà
 * không phải sửa từng controller.
 */

$success = flash('newsletter_success');
$error   = flash('newsletter_error');

// Địa chỉ vừa nhập bị từ chối -> đổ lại vào ô để khách sửa, không gõ lại
$oldEmail = $_SESSION['_old_newsletter'] ?? '';
unset($_SESSION['_old_newsletter']);
?>

<section class="nletter" id="dang-ky-nhan-tin" data-section="s20" aria-labelledby="nletter-title">
    <div class="nletter__inner">

        <div class="nletter__text">
            <p class="eyebrow">Bản tin Vin Eyewear</p>
            <h2 id="nletter-title" class="section-h2">Nhận mẫu mới và ưu đãi trước</h2>
            <p class="nletter__lead">
                Mỗi tháng một thư: bộ sưu tập vừa về, ưu đãi dành riêng cho người đăng ký
                và mẹo chọn gọng theo khuôn mặt. Huỷ nhận bất cứ lúc nào.
            </p>
        </div>

        <div class="nletter__form">
            <?php if ($success !== null): ?>
                <p class="alert alert--ok" role="status"><?= e($success) ?></p>
            <?php endif; ?>
            <?php if ($error !== null): ?>
                <p class="alert alert--err" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <form method="post" action="/nhan-tin/dang-ky" class="nform">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="source" value="home">
                <!-- Quay lại đúng trang đang đứng; controller lọc qua safeRedirectPath() -->
                <input type="hidden" name="redirect" value="<?= e(currentPath()) ?>">

                <div class="field nform__field">
                    <label for="nletter-email">Email của bạn</label>
                    <input type="email" id="nletter-email" name="email" required
                           maxlength="255" autocomplete="email"
                           placeholder="ban@vidu.com"
                           value="<?= e($oldEmail) ?>">
                </div>

                <button type="submit" class="btn-primary btn-inline nform__submit">Đăng ký</button>
            </form>

            <p class="nletter__note">
                <?= icon('shield', 'nletter__ico', 14) ?>
                Chúng tôi không chia sẻ email của bạn cho bên thứ ba.
            </p>
        </div>
    </div>
</section>
