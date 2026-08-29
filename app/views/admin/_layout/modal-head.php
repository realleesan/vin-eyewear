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
 *   $nutUrl  — địa chỉ của nút chính ở đầu hộp (tuỳ chọn)
 *   $nutNhan — nhãn nút ấy; phải có nếu đã truyền $nutUrl
 *   $khoa    — DANH TÍNH của hộp, để JS biết hai lần dựng có phải cùng một hộp
 *              không (tuỳ chọn)
 *   $cao     — true: khoá chiều cao hộp, ruột tự cuộn (tuỳ chọn)
 *
 * $KHOA LÀ THỨ LÀM ĐỔI TAB KHÔNG GIẬT. admin-modal.js so khoá của bản vừa nạp
 * với hộp đang mở: trùng thì nó chỉ thay RUỘT hộp, giữ nguyên khung — nên
 * khung không chạy lại hiệu ứng hiện ra và không nhảy kích thước. Khác (hoặc
 * không có khoá) thì dựng lại cả hộp như cũ.
 *
 * Khoá phải giống nhau giữa các cảnh của CÙNG một hộp và khác nhau giữa hai
 * hộp khác nhau — hồ sơ khách hàng lấy 'khach-<id>', nên bốn tab của một người
 * là một hộp, còn hai người là hai hộp.
 *
 * NÚT CHÍNH Ở ĐẦU HỘP là một <a> có sẵn `data-modal`, tức là nó mở hộp tiếp
 * theo tại chỗ khi có JavaScript và tải trang thật khi không. Nó nhận URL với
 * nhãn chứ không nhận HTML dựng sẵn — xem lý do ngay dưới đây.
 *
 * VÌ SAO TÁCH LÀM HAI FILE thay vì một partial nhận sẵn nội dung: dự án không
 * có template engine tự escape, nên một tham số "HTML dựng sẵn" là cái lỗ duy
 * nhất trong nếp escape mọi thứ bằng e() — và nó sẽ được dùng lại. Hai file mở
 * và đóng thì ruột hộp vẫn là PHP thường, escape như mọi chỗ khác.
 */
$phu   = $phu ?? '';
$rong  = ($rong ?? '') !== '' ? ' amodal--' . $rong : '';
/* Nút chính là TUỲ CHỌN: mười mấy hộp còn lại chỉ có nhan đề với nút ✕, và
   thêm một khối rỗng cho chúng là thêm một chỗ để bố cục lệch đi. */
$nutUrl  = $nutUrl ?? '';
$nutNhan = $nutNhan ?? '';
$khoa    = $khoa ?? '';
/* Chiều cao cố định là NGOẠI LỆ, không phải mặc định: hộp một form ngắn mà
   cao bằng màn hình thì phần dưới trống hoác. Chỉ hộp nhiều cảnh — hồ sơ khách
   với bốn tab dài ngắn khác nhau — mới cần đứng yên một cỡ. */
$rong   .= ($cao ?? false) ? ' amodal--cao' : '';
?>
<div class="amodal<?= e($rong) ?>" role="dialog" aria-modal="true" aria-label="<?= e($tieuDe) ?>"
     <?php if ($khoa !== ''): ?>data-modal-key="<?= e($khoa) ?>"<?php endif; ?>>
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

            <div class="amodal__acts">
                <?php if ($nutUrl !== '' && $nutNhan !== ''): ?>
                    <a class="astatus__save" href="<?= e($nutUrl) ?>" data-modal><?= e($nutNhan) ?></a>
                <?php endif; ?>

                <a class="amodal__x" href="<?= e($dongUrl) ?>" data-modal-close aria-label="Đóng">&times;</a>
            </div>
        </div>

        <div class="amodal__body">
