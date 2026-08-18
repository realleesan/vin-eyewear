<?php

/**
 * policy/index.php — Chính sách & Hỗ trợ (/chinh-sach)
 *
 * Dựng theo "Chinh Sach & Ho Tro v2.dc.html" (Claude Design):
 *
 *   đầu trang nền hồng phấn (_layout/page-head.php, dùng chung)
 *   → 4 thẻ cam kết
 *   → cột trái MỤC LỤC dính theo cuộn + khối hotline nền đỏ
 *     | cột phải: ô tìm nhanh → TOÀN BỘ nhóm chính sách → khối "Liên hệ hỗ trợ"
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BỎ KIỂU TAB, QUAY VỀ MỘT TRANG CUỘN DÀI
 *
 * Bản trước (port từ Lovable) coi cột trái là TAB: bấm một mục thì bốn nhóm
 * còn lại biến mất khỏi màn hình. Bản thiết kế này in thẳng cả năm nhóm nối
 * tiếp nhau, cột trái chỉ còn là mục lục — bấm thì cuộn tới, và mục đang xem
 * tự sáng lên theo vị trí cuộn (assets/js/policy.js).
 *
 * Được ba thứ:
 *   1. Nội dung chính sách nằm sẵn trong DOM cho máy tìm kiếm và trình đọc
 *      màn hình — trước đây bốn nhóm bị `display:none` sau khi JS chạy.
 *   2. Ctrl+F của trình duyệt tìm được mọi nội dung.
 *   3. Tắt JavaScript vẫn dùng được đủ: mục lục là thẻ <a href="#…"> nên
 *      trình duyệt tự nhảy neo, chỉ mất phần lọc và phần tự sáng.
 *
 * Câu trả lời cũng KHÔNG còn gấp trong <details> nữa — bản thiết kế để ngỏ
 * hết. Người đọc chính sách thường phải đối chiếu vài mục một lúc; bắt bấm
 * mở từng cái là chậm, và nội dung gấp lại cũng khó dò bằng Ctrl+F.
 * ─────────────────────────────────────────────────────────────────────────────
 */

partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Chính sách & Hỗ trợ']],
    'head_title'  => 'Chính Sách & Cam Kết Chất Lượng',
    'head_lead'   => 'Vin Eyewear đồng hành cùng thị lực và phong cách của bạn — bảo hành '
                   . 'trọn đời, đổi mẫu 7 ngày và đồng kiểm khi nhận hàng.',
]);

/* Mục lục = các nhóm chính sách + khối liên hệ ở cuối trang. Gom thành MỘT
   mảng ở đây để cột trái và policy.js (danh sách neo cần theo dõi khi cuộn)
   không bao giờ lệch nhau. */
$policyToc = [];
foreach ($groups as $group) {
    $policyToc[] = ['id' => $group['id'], 'label' => $group['short'] ?? $group['label']];
}
$policyToc[] = ['id' => 'lien-he', 'label' => 'Liên hệ hỗ trợ'];

/* Bốn kênh liên hệ của khối cuối trang — lấy từ config/company.php, nguồn duy
   nhất cho hotline/email toàn site. Bản Lovable từng gõ cứng "1900 1234" ở
   riêng trang này, lệch hẳn với header và chân trang. */
$policyChannels = [
    ['label' => 'Hotline',   'value' => $company['hotline'],              'href' => $company['hotline_href']],
    ['label' => 'Zalo',      'value' => 'zalo.me/19006868',               'href' => $company['channels']['zalo']],
    ['label' => 'Messenger', 'value' => 'm.me/vineyewear',                'href' => $company['channels']['messenger']],
    ['label' => 'Email',     'value' => $company['email'],                'href' => 'mailto:' . $company['email']],
];
?>

<section class="policy">
    <div class="policy__inner">

        <!-- ============================================================
             4 CAM KẾT NỔI BẬT
             ============================================================ -->
        <ul class="policy-highlights" role="list">
            <?php foreach ($highlights as $item): ?>
                <li class="policy-card">
                    <span class="policy-card__ico"><?= icon($item['icon'], '', 17) ?></span>
                    <h2 class="policy-card__title"><?= e($item['title']) ?></h2>
                    <p class="policy-card__desc"><?= e($item['desc']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- ============================================================
             THÂN TRANG — mục lục trái, nội dung phải
             ============================================================ -->
        <div class="policy-body">

            <aside class="policy-side">

                <nav class="policy-toc" aria-labelledby="policyTocLabel">
                    <p class="policy-toc__label" id="policyTocLabel">Trên trang này</p>
                    <div class="policy-toc__list">
                        <?php foreach ($policyToc as $i => $entry): ?>
                            <!-- Thẻ <a> chứ không phải <button> như bản thiết kế: đây là
                                 liên kết neo thật, chia sẻ được và chạy cả khi tắt JS. -->
                            <a href="#<?= e($entry['id']) ?>"
                               class="policy-toc__item<?= $i === 0 ? ' is-active' : '' ?>"
                               data-policy-toc="<?= e($entry['id']) ?>"><?= e($entry['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </nav>

                <div class="policy-hot">
                    <p class="policy-hot__title">Cần hỗ trợ nhanh?</p>
                    <p class="policy-hot__desc">Tư vấn <?= e($company['open_hours']) ?>.</p>
                    <a class="policy-hot__phone" href="<?= e($company['hotline_href']) ?>">
                        Hotline <?= e($company['hotline']) ?>
                    </a>
                    <p class="policy-hot__mail"><?= e($company['email']) ?></p>
                </div>
            </aside>

            <div class="policy-content">

                <!-- Ô tìm kiếm — JavaScript lọc; không có JS thì ẩn đi cho khỏi
                     gây hiểu nhầm là gõ vào sẽ có tác dụng. -->
                <div class="policy-search" data-needs-js hidden>
                    <?= icon('search', 'policy-search__ico', 16) ?>
                    <label class="sr-only" for="policySearch">Tìm nhanh trong chính sách</label>
                    <input
                        type="search"
                        id="policySearch"
                        class="policy-search__input"
                        placeholder="Tìm nhanh trong chính sách (ví dụ: hoàn tiền, COD, phí ship...)"
                        autocomplete="off"
                    >
                </div>

                <p class="policy-result" data-policy-result hidden aria-live="polite"></p>

                <?php foreach ($groups as $group): ?>
                    <section class="policy-group"
                             id="<?= e($group['id']) ?>"
                             data-policy-group="<?= e($group['id']) ?>">

                        <h2 class="policy-group__title"><?= e($group['label']) ?></h2>
                        <p class="policy-group__intro"><?= e($group['intro']) ?></p>

                        <div class="policy-qa">
                            <?php foreach ($group['items'] as $item): ?>
                                <div class="policy-qa__item" data-policy-item>
                                    <p class="policy-qa__q"><?= e($item['q']) ?></p>
                                    <p class="policy-qa__a"><?= e($item['a']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

                <p class="policy-empty" data-policy-empty hidden>
                    <strong>Không tìm thấy nội dung phù hợp</strong>
                    <span>Thử từ khoá khác, hoặc gọi hotline <?= e($company['hotline']) ?> để được hỗ trợ trực tiếp.</span>
                </p>

                <!-- ============================================================
                     LIÊN HỆ HỖ TRỢ — mục cuối của mục lục
                     ============================================================ -->
                <section class="policy-group policy-contact" id="lien-he">
                    <h2 class="policy-group__title">Liên hệ hỗ trợ</h2>
                    <p class="policy-group__intro">
                        Chọn kênh thuận tiện nhất — đội ngũ Vin Eyewear phản hồi
                        <?= e($company['open_hours']) ?>.
                    </p>

                    <ul class="policy-channels" role="list">
                        <?php foreach ($policyChannels as $ch): ?>
                            <li>
                                <?php /* Chỉ tel: và mailto: mới ở lại trang; hai kênh chat mở
                                         tab mới nên cần rel bảo vệ. */ ?>
                                <a class="policy-ch" href="<?= e($ch['href']) ?>"
                                   <?= str_starts_with($ch['href'], 'http') ? 'target="_blank" rel="noreferrer noopener"' : '' ?>>
                                    <span class="policy-ch__label"><?= e($ch['label']) ?></span>
                                    <span class="policy-ch__value"><?= e($ch['value']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <ul class="policy-stores" role="list">
                        <?php foreach ($company['stores'] as $store): ?>
                            <li><?= e($store) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </div>
        </div>
    </div>
</section>
