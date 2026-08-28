<?php

/**
 * admin/_layout/modal-head.php — mở một hộp thoại: lớp nền mờ, khung, đầu hộp.
 *
 * Đi CẶP với modal-foot.php. Giữa hai lần gọi là ruột hộp — thường là một
 * <form> đầy đủ, kể cả thẻ đóng </form> phải nằm trước modal-foot nếu nút Lưu
 * dùng thuộc tính form=.
 *
 * Nhận qua partial():
 *   $tieuDe  — nhan đề hộp
 *   $phu     — dòng phụ dưới nhan đề (tuỳ chọn)
 *   $dongUrl — địa chỉ khi đóng: chính trang này, bỏ ?them / ?sua
 *   $rong    — '' | 'sm' | 'lg' | 'xl' | 'xxl' (tuỳ chọn)
 *
 * VÌ SAO TÁCH LÀM HAI FILE thay vì một partial nhận sẵn nội dung: dự án không
 * có template engine tự escape, nên một tham số "HTML dựng sẵn" là cái lỗ duy
 * nhất trong nếp escape mọi thứ bằng e() — và nó sẽ được dùng lại. Hai file mở
 * và đóng thì ruột hộp vẫn là PHP thường, escape như mọi chỗ khác.
 */
$phu   = $phu ?? '';
$rong  = ($rong ?? '') !== '' ? ' amodal--' . $rong : '';
?>
<div class="amodal<?= e($rong) ?>" role="dialog" aria-modal="true" aria-label="<?= e($tieuDe) ?>">
    <?php /* Lớp nền mờ là một <a> phủ kín — bấm ra ngoài để đóng, chạy cả khi
             tắt JS. aria-hidden vì nút ✕ ngay bên trong đã nói đúng việc ấy
             cho trình đọc màn hình; hai lối "đóng" liền nhau thì thừa. */ ?>
    <a class="amodal__dim" href="<?= e($dongUrl) ?>" data-modal-close aria-hidden="true" tabindex="-1"></a>

    <div class="amodal__panel">
        <div class="amodal__head">
            <div>
                <h2 class="amodal__title"><?= e($tieuDe) ?></h2>
                <?php if ($phu !== ''): ?>
                    <p class="amodal__sub"><?= e($phu) ?></p>
                <?php endif; ?>
            </div>

            <a class="amodal__x" href="<?= e($dongUrl) ?>" data-modal-close aria-label="Đóng">&times;</a>
        </div>

        <div class="amodal__body">
