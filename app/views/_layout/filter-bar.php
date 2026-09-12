<?php

/**
 * _layout/filter-bar.php — tấm lọc ngang, dùng chung cho mọi lưới sản phẩm.
 *
 * CSS: assets/css/components/filter-bar.css (có hình phác bố cục ở đầu file)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * PARTIAL NÀY KHÔNG BIẾT GÌ VỀ BỘ LỌC
 *
 * Nó không biết có những nhóm nào, URL lọc dựng ra sao, đếm thế nào là 0. Nó
 * nhận một mảng đã dọn sẵn và vẽ ra. Mọi luật — tên nhóm, thứ tự cột, cách
 * dựng địa chỉ, nhóm nào hiện ở trang nào — nằm ở NƠI GỌI.
 *
 * Nhờ vậy trang danh mục và trang bộ sưu tập dùng chung đúng một tấm, dù hai
 * bên lấy dữ liệu từ hai chỗ khác nhau. Đừng đọc $_GET hay gọi model ở đây:
 * làm thế là tấm này dính vào một trang cụ thể và trang kia phải có bản sao.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NHẬN VÀO
 *
 *   $fbCols   array  các cột, theo đúng thứ tự sẽ hiện ra:
 *                    [
 *                      'label'   => 'Kiểu dáng',
 *                      'single'  => false,       // true = ô tròn (chọn một)
 *                      'options' => [
 *                        ['label' => 'Vuông', 'url' => '/san-pham?...',
 *                         'on' => false, 'off' => false],
 *                      ],
 *                    ]
 *                    'off' = không còn hàng nào -> làm mờ, bỏ liên kết.
 *   $fbTotal  int    tổng số món đang khớp (số mũ cạnh chữ "Bộ lọc")
 *   $fbClear  string địa chỉ "Xoá tất cả"; chuỗi rỗng = không có gì để xoá
 *   $fbTitle  string nhan đề tấm, mặc định "Bộ lọc"
 *   $fbCount  int    số tiêu chí ĐANG BẬT — in lên nút mở, và quyết định tấm
 *                    có bung sẵn hay không
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TẤM ĐÓNG SẴN, MỞ RA BẰNG NÚT "BỘ LỌC"
 *
 * Mặc định chỉ hiện một nút nhỏ ở mép phải; bấm vào mới bung cả tấm. Đây là
 * yêu cầu của chủ dự án (12/09/2026) và cũng là dáng của bản thiết kế: bảy
 * cột tiêu chí chiếm gần nửa màn hình đầu, mà phần lớn khách vào để NHÌN
 * HÀNG chứ không để lọc.
 *
 * <details> CHỨ KHÔNG PHẢI JAVASCRIPT — cùng lối mà tấm lọc cũ của trang bộ
 * sưu tập đã dùng. Tắt JS thì nút vẫn mở/đóng được.
 *
 * ⚠ KHÔNG có nút ✕ trong tấm nữa. Trước đây nó là một liên kết về trang trần,
 * tức là vừa đóng vừa XOÁ SẠCH bộ lọc — hai việc khác nhau đeo chung một dấu
 * ✕. Nay đóng là bấm lại đúng cái nút vừa mở, còn xoá là "Xoá tất cả bộ lọc"
 * ở chân tấm. Đừng thêm lại ✕: hai lối đóng cạnh nhau là chỗ người dùng đoán
 * sai rồi mất cả bộ lọc đang gõ dở.
 *
 * ⚠ NÚT MỞ NẰM NGOÀI .fbar, còn assets/js/catalog.js thì thay ruột .fbar sau
 * mỗi cú bấm tiêu chí. Cố ý: nhờ vậy trạng thái mở/đóng và cả vị trí cuộn
 * không bị dựng lại. Đổi lại, con số trên nút phải để catalog.js chép sang —
 * xem .fbarwrap__num ở đó.
 */

$fbCols  = $fbCols  ?? [];
$fbTotal = (int) ($fbTotal ?? 0);
$fbClear = (string) ($fbClear ?? '');
$fbTitle = (string) ($fbTitle ?? 'Bộ lọc');
$fbCount = (int) ($fbCount ?? 0);

/* Không còn cột nào có lựa chọn thì không vẽ tấm rỗng. Một tấm lọc trống làm
   người ta tưởng trang hỏng, trong khi sự thật chỉ là kho chưa đủ hàng để có
   gì mà lọc. */
$fbCoGi = false;

foreach ($fbCols as $c) {
    if (!empty($c['options'])) {
        $fbCoGi = true;
        break;
    }
}

if (!$fbCoGi) {
    return;
}
?>

<details class="fbarwrap"<?= $fbCount > 0 ? ' open' : '' ?>>
    <?php /* <summary> mang sẵn tam giác riêng của trình duyệt và nó không
             chỉnh được nét cho khớp phần còn lại — tắt ở cả hai cú pháp trong
             filter-bar.css (list-style + ::-webkit-details-marker). */ ?>
    <summary class="fbarwrap__btn">
        <span><?= e($fbTitle) ?></span>
        <?= icon('sliders', 'fbarwrap__ico', 16) ?>

        <?php /* Ô SỐ LUÔN CÓ MẶT, chỉ `hidden` khi bằng 0 — không phải khi
                 rỗng thì bỏ hẳn thẻ. catalog.js chép con số này sang sau mỗi
                 cú lọc, mà chép vào một thẻ không tồn tại thì con số đứng im
                 ở giá trị lúc nạp trang. */ ?>
        <span class="fbarwrap__num"<?= $fbCount > 0 ? '' : ' hidden' ?>><?= $fbCount ?><span
            class="sr-only"> tiêu chí đang bật</span></span>
    </summary>

<div class="fbar">
    <div class="fbar__head">
        <p class="fbar__title">
            <?= e($fbTitle) ?><span class="fbar__count"><?= $fbTotal ?><span
                class="sr-only"> sản phẩm đang khớp</span></span>
        </p>
    </div>

    <div class="fbar__cols">
        <?php foreach ($fbCols as $col): ?>
            <?php if (empty($col['options'])) { continue; } ?>
            <?php $fbMot = !empty($col['single']); ?>

            <?php /* <fieldset> + <legend> chứ không phải <div> + <p>: đây là
                     một NHÓM lựa chọn có tên, và trình đọc màn hình đọc tên
                     nhóm trước mỗi mục nhờ đúng cặp thẻ này. */ ?>
            <fieldset class="fbar__col">
                <legend class="fbar__legend"><?= e((string) $col['label']) ?></legend>

                <div class="fbar__list">
                    <?php foreach ($col['options'] as $o): ?>
                        <?php
                        $tat = !empty($o['off']) && empty($o['on']);
                        $lop = 'fbar__opt'
                            . ($fbMot ? ' fbar__opt--one' : '')
                            . (!empty($o['on']) ? ' is-on' : '')
                            . ($tat ? ' is-off' : '');
                        ?>
                        <?php if ($tat): ?>
                            <?php /* Hết hàng: KHÔNG phải liên kết nữa. Một thẻ
                                     <a> mờ mà vẫn bấm được sẽ dẫn tới lưới rỗng —
                                     xem khối "MỤC KHÔNG CÒN HÀNG" trong CSS. */ ?>
                            <span class="<?= $lop ?>" aria-disabled="true">
                                <span class="fbar__box" aria-hidden="true"></span>
                                <span><?= e((string) $o['label']) ?><span
                                    class="sr-only"> — không có sản phẩm nào</span></span>
                            </span>
                        <?php else: ?>
                            <?php /* aria-current chứ không phải aria-pressed:
                                     aria-pressed chỉ hợp lệ trên <button>, còn
                                     đây là <a>. Câu sr-only đi kèm vì trạng thái
                                     "đang chọn" ngoài ra chỉ nằm ở màu nền của ô
                                     — người không nhìn thấy màu sẽ nghe mục bật
                                     và mục tắt giống hệt nhau. */ ?>
                            <a class="<?= $lop ?>" href="<?= e((string) $o['url']) ?>" rel="nofollow"
                               <?= !empty($o['on']) ? 'aria-current="true"' : '' ?>>
                                <span class="fbar__box" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 12.5l5.5 5.5L20 7"></path>
                                    </svg>
                                </span>
                                <span><?= e((string) $o['label']) ?><?php
                                    if (!empty($o['on'])): ?><span class="sr-only"> — đang chọn</span><?php endif;
                                ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endforeach; ?>
    </div>

    <?php if ($fbClear !== ''): ?>
        <div class="fbar__foot">
            <a class="fbar__clear" href="<?= e($fbClear) ?>" rel="nofollow">Xoá tất cả bộ lọc</a>
        </div>
    <?php endif; ?>
</div>
</details>
