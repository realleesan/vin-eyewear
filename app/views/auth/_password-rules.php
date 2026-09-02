<?php

/**
 * auth/_password-rules.php — năm dòng quy tắc dưới ô mật khẩu.
 *
 * Nằm riêng một file vì có mặt ở BA màn: đăng ký, quên mật khẩu bằng mã OTP,
 * và đặt lại bằng liên kết của nhân viên. Chép tay ba bản thì sớm muộn cũng có
 * bản quên sửa theo, và một màn hình hứa nhẹ hơn thứ máy chủ thật sự đòi là
 * cách chắc nhất để khách bị từ chối mà không hiểu vì sao.
 *
 * Năm dòng này phải KHỚP với passwordProblem() trong core/helpers.php — đó mới
 * là nơi quyết định, còn đây chỉ là bản đọc được của nó. Sửa ngày 2026-09-02
 * theo SNFR-09: thêm dòng ký tự đặc biệt, và đổi "tối thiểu 8" thành "8–32" vì
 * hàm kiểm nay có cả trần trên.
 *
 * auth.js chấm xanh từng dòng ngay khi gõ, dựa vào data-rule. Không có
 * JavaScript thì bốn dòng vẫn là bản yêu cầu đọc được, và máy chủ vẫn từ chối
 * mật khẩu thiếu chuẩn — nên không mất gì.
 */
?>

<ul class="arule" role="list">
    <?php foreach ([
        ['len',     'Từ 8 đến 32 ký tự'],
        ['upper',   'Ít nhất một chữ hoa'],
        ['lower',   'Ít nhất một chữ thường'],
        ['digit',   'Ít nhất một chữ số'],
        ['special', 'Ít nhất một ký tự đặc biệt'],
    ] as [$rule, $label]): ?>
        <li class="arule__item" data-rule="<?= e($rule) ?>">
            <span class="arule__dot" aria-hidden="true">✓</span><?= e($label) ?>
        </li>
    <?php endforeach; ?>
</ul>
