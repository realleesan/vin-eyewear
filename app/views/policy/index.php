<?php

/**
 * policy/index.php — Chính sách & Cam kết chất lượng
 * Port từ src/routes/chinh-sach.tsx.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÁC BẢN REACT MỘT ĐIỂM CÓ CHỦ Ý
 *
 * Bản React chỉ hiện MỘT nhóm chính sách tại một thời điểm (state activeId),
 * bốn nhóm còn lại không có trong DOM. Ở đây server in ra TOÀN BỘ 5 nhóm, rồi
 * assets/js/policy.js mới ẩn bớt.
 *
 * Lý do:
 *   1. Chính sách bảo hành, đổi trả là thứ người dùng tìm bằng Google. Nội
 *      dung chỉ tồn tại sau khi JavaScript chạy thì công cụ tìm kiếm và trình
 *      đọc màn hình khó thấy.
 *   2. Ctrl+F của trình duyệt tìm được mọi nội dung, không phụ thuộc ô tìm kiếm.
 *   3. Tắt JavaScript vẫn đọc đủ — trang chỉ mất phần lọc, không mất nội dung.
 * ─────────────────────────────────────────────────────────────────────────────
 */

partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Chính sách & Hỗ trợ']],
    'head_title'  => 'Chính Sách & Cam Kết Chất Lượng',
    'head_lead'   => 'Vin Eyewear đồng hành cùng thị lực và phong cách của bạn — bảo hành '
                   . 'trọn đời, đổi mẫu 7 ngày và đồng kiểm khi nhận hàng.',
]);
?>

<section class="policy">
    <div class="policy__inner">

        <!-- ============================================================
             4 CAM KẾT NỔI BẬT
             ============================================================ -->
        <ul class="policy-highlights" role="list">
            <?php foreach ($highlights as $item): ?>
                <li class="policy-card">
                    <span class="policy-card__ico"><?= icon($item['icon'], '', 20) ?></span>
                    <h2 class="policy-card__title"><?= e($item['title']) ?></h2>
                    <p class="policy-card__desc"><?= e($item['desc']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- ============================================================
             NỘI DUNG CHÍNH — cột trái điều hướng, cột phải nội dung
             ============================================================ -->
        <div class="policy-body">

            <aside class="policy-nav">
                <p class="policy-nav__label">Danh mục chính sách</p>
                <nav class="policy-nav__list" aria-label="Danh mục chính sách">
                    <?php foreach ($groups as $i => $group): ?>
                        <!-- Là thẻ <a> chứ không phải <button>: không có JavaScript
                             thì đây vẫn là liên kết neo nhảy đúng tới nhóm. -->
                        <a href="#<?= e($group['id']) ?>"
                           class="policy-nav__item<?= $i === 0 ? ' is-active' : '' ?>"
                           data-policy-tab="<?= e($group['id']) ?>">
                            <?= icon($group['icon'], 'policy-nav__ico', 16) ?>
                            <span><?= e($group['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </aside>

            <div class="policy-content">

                <!-- Ô tìm kiếm — JavaScript lọc; không có JS thì ẩn đi cho khỏi
                     gây hiểu nhầm là gõ vào sẽ có tác dụng. -->
                <div class="policy-search" data-needs-js hidden>
                    <?= icon('search', 'policy-search__ico', 16) ?>
                    <label class="sr-only" for="policySearch">Tìm kiếm chính sách</label>
                    <input
                        type="search"
                        id="policySearch"
                        class="policy-search__input"
                        placeholder="Tìm kiếm chính sách (ví dụ: bảo hành, đổi trả, ship...)"
                        autocomplete="off"
                    >
                </div>

                <p class="policy-result" data-policy-result hidden aria-live="polite"></p>

                <?php foreach ($groups as $i => $group): ?>
                    <section class="policy-group<?= $i === 0 ? ' is-active' : '' ?>"
                             id="<?= e($group['id']) ?>"
                             data-policy-group="<?= e($group['id']) ?>"
                             data-policy-label="<?= e($group['label']) ?>">

                        <h2 class="policy-group__title"><?= e($group['label']) ?></h2>
                        <p class="policy-group__intro"><?= e($group['intro']) ?></p>

                        <div class="policy-faq">
                            <?php foreach ($group['items'] as $item): ?>
                                <!-- <details> thay accordion của React: trình duyệt lo sẵn
                                     đóng mở, điều khiển bằng phím và vai trò ARIA. -->
                                <details class="policy-faq__item" data-policy-item>
                                    <summary class="policy-faq__q">
                                        <span class="policy-faq__badge" data-policy-badge hidden><?= e($group['label']) ?></span>
                                        <span class="policy-faq__text"><?= e($item['q']) ?></span>
                                        <?= icon('chevron-down', 'policy-faq__chevron', 18) ?>
                                    </summary>
                                    <div class="policy-faq__a"><?= e($item['a']) ?></div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

                <p class="policy-empty" data-policy-empty hidden>
                    <strong>Không tìm thấy nội dung phù hợp</strong>
                    <span>Thử từ khoá khác hoặc liên hệ hotline để được hỗ trợ trực tiếp.</span>
                </p>
            </div>
        </div>

        <!-- ============================================================
             KHỐI HỖ TRỢ

             Bản Lovable gõ cứng hotline "1900 1234" và Zalo "0901234567" ở
             trang này, LỆCH với trust-data.ts của chính nó (1900 6868). Ở đây
             lấy theo config/company.php — nguồn duy nhất, để header, footer và
             trang này không bao giờ hiện ba số khác nhau.
             ============================================================ -->
        <div class="policy-help">
            <div class="policy-help__text">
                <h2 class="policy-help__title">Bạn vẫn còn thắc mắc về chính sách?</h2>
                <p>Đội ngũ chuyên viên Vin Eyewear sẵn sàng tư vấn từ 8:30 - 21:00 mỗi ngày.</p>
            </div>
            <div class="policy-help__actions">
                <a href="<?= e($company['channels']['zalo']) ?>"
                   class="btn-primary btn-inline"
                   target="_blank" rel="noreferrer noopener">
                    <?= icon('message', '', 16) ?> Chat Zalo Tư Vấn
                </a>
                <a href="<?= e($company['hotline_href']) ?>" class="btn-outline btn-inline">
                    <?= icon('phone', '', 16) ?> Gọi Hotline: <?= e($company['hotline']) ?>
                </a>
            </div>
        </div>
    </div>
</section>
