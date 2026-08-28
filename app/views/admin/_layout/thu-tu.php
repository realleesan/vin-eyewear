<?php

/**
 * admin/_layout/thu-tu.php — cặp nút ↑↓ đổi chỗ một dòng với dòng liền kề.
 *
 * Nhận qua partial():
 *   $base — đường POST, vd '/quan-tri/danh-muc/thu-tu'
 *   $id   — id của dòng
 *   $dau  — true nếu đây là dòng ĐẦU (khoá nút ↑)
 *   $cuoi — true nếu đây là dòng CUỐI (khoá nút ↓)
 *   $ten  — tên dòng, dùng cho nhãn trợ năng
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HAI FORM RIÊNG, KHÔNG PHẢI MỘT FORM HAI NÚT
 *
 * Một form với hai nút submit khác `value` thì gọn hơn — nhưng nút submit chỉ
 * gửi giá trị của nút ĐƯỢC BẤM, và trình duyệt cũ (cùng vài trình đọc màn hình
 * khi kích hoạt bằng Enter) không phải lúc nào cũng gửi kèm. Lúc đó server
 * nhận một cú POST không có hướng và không biết phải làm gì.
 *
 * Hai form thì mỗi cú bấm mang sẵn hướng trong một ô ẩn — không phụ thuộc vào
 * việc trình duyệt có gửi tên nút hay không.
 *
 * KHOÁ NÚT BẰNG `disabled`, và server VẪN kiểm lại: ThuTuService trả về false
 * khi không có gì để đổi. Ẩn nút trên giao diện không phải là phân quyền, cũng
 * không phải là kiểm dữ liệu (CLAUDE.md mục 4).
 * ─────────────────────────────────────────────────────────────────────────────
 */
$ten = $ten ?? '';
?>
<div class="aord">
    <?php foreach ([
        ['len',   '↑', 'lên', $dau],
        ['xuong', '↓', 'xuống', $cuoi],
    ] as [$huong, $mui, $chu, $khoa]): ?>
        <form method="post" action="<?= e($base) ?>">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= e($id) ?>">
            <input type="hidden" name="huong" value="<?= e($huong) ?>">
            <button type="submit" class="aord__btn"<?= $khoa ? ' disabled' : '' ?>
                    aria-label="Chuyển <?= e($ten) ?> <?= e($chu) ?> một bậc"><?= $mui ?></button>
        </form>
    <?php endforeach; ?>
</div>
