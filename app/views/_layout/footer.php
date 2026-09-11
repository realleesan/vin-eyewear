<?php
/*
 * ═══════════════════════════════════════════════════════════════════════════
 * CHÂN TRANG — dựng 1:1 từ mẫu "Eyewear Collection"
 *
 * BA CỘT KHÔNG ĐỀU NHAU, HAI ĐƯỜNG KẺ DỌC:
 *
 *   ┌──────────────┬────────────────────────────┬──────────────┐
 *   │  wordmark    │  Sản phẩm · Thương hiệu    │  Cần tư vấn  │
 *   │  câu trích   │  · Hỗ trợ                  │  ba kênh     │
 *   │  flex 1 220  │  flex 2 380                │  flex 1 240  │
 *   └──────────────┴────────────────────────────┴──────────────┘
 *              ────────────  ◆ ◆ ◆ ◆  ────────────
 *   © 2026 …                                    Ngôn ngữ : VI / EN
 *
 * Cột 1 có border-right, cột 3 có border-left — hai đường kẻ ấy là thứ chia
 * ba khối chứ không phải khoảng trống, nên đừng thay chúng bằng gap.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * HAI THỨ DỜI TỪ THANH ĐẦU TRANG XUỐNG ĐÂY, vì mẫu bỏ hẳn dải tiện ích:
 *
 *   HOTLINE  → cột 3, thành một trong ba kênh có icon.
 *   BỘ CHUYỂN NGÔN NGỮ → hàng cuối, nép phải cạnh dòng bản quyền. Đúng chỗ
 *              mẫu đặt "Country : Vietnam", và đúng vai: một lựa chọn cấu
 *              hình, không phải một mục điều hướng.
 *
 * Bộ chuyển vẫn là hai <a> mang URL đầy đủ tới chính trang đang đứng — chạy
 * khi tắt JavaScript, mở được ở tab mới, máy tìm kiếm đi theo được. Xem
 * i18nXuLyChuyenNgonNgu() trong core/i18n.php.
 * ═══════════════════════════════════════════════════════════════════════════
 */

$company = config('company');

/* ┌─ ICON MẠNG XÃ HỘI ────────────────────────────────────────────────────
   │ Đường path lấy nguyên từ mẫu. Để thành mảng tra theo khoá 'icon' của
   │ config/company.php: thêm/bớt một mạng thì sửa config, không sửa file
   │ này; icon nào chưa có path thì bỏ qua im lặng thay vì in ô trống.
   └──────────────────────────────────────────────────────────────────────── */
$socialIcons = [
    'facebook'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H7v4h3v8h4v-8h3l1-4h-4V8z"/></svg>',
    'instagram' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>',
    'youtube'   => '<svg width="16" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M22 8.5s-.2-1.6-.9-2.3c-.8-.9-1.8-.9-2.2-1C15.8 5 12 5 12 5s-3.8 0-6.9.2c-.4.1-1.4.1-2.2 1C2.2 6.9 2 8.5 2 8.5S1.8 10.3 1.8 12v1.9c0 1.8.2 3.5.2 3.5s.2 1.6.9 2.3c.8.9 1.9.9 2.4 1 1.8.2 6.7.2 6.7.2s3.8 0 6.9-.2c.4-.1 1.4-.1 2.2-1 .7-.7.9-2.3.9-2.3s.2-1.8.2-3.5V12c0-1.7-.2-3.5-.2-3.5zM9.9 15.5V9.1l6 3.2-6 3.2z"/></svg>',
    'tiktok'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M16.5 3c.3 2.3 1.7 3.8 4 4v3.4c-1.5 0-2.9-.5-4-1.3v6.4a5.6 5.6 0 1 1-4.8-5.5v3.5a2.2 2.2 0 1 0 1.5 2.1V3h3.3z"/></svg>',
];
?>
<footer class="oa-footer">

    <div class="oa-footer__top">

        <?php /* ── CỘT 1 — thương hiệu ───────────────────────────────── */ ?>
        <div class="oa-footer__brand">
            <a href="/" class="oa-footer__mark">Vin Eyewear</a>
            <p class="oa-footer__quote">“<?= e(t('footer.statement')) ?>”</p>
            <p class="oa-muted"><?= e(t('footer.blurb')) ?></p>
        </div>

        <?php /* ── CỘT 2 — ba nhóm liên kết ──────────────────────────── */ ?>
        <nav class="oa-footer__links" aria-label="<?= e(t('footer.legal_nav')) ?>">

            <div class="oa-footer__col">
                <span class="oa-eyebrow oa-eyebrow--ink"><?= e(t('footer.products')) ?></span>
                <div class="oa-footer__list">
                    <a href="/san-pham/gong-kinh"><?= e(t('nav.frames')) ?></a>
                    <a href="/san-pham/trong-kinh"><?= e(t('nav.lenses')) ?></a>
                    <a href="/bo-suu-tap"><?= e(t('nav.collections')) ?></a>
                    <a href="/san-pham?sap-xep=moi-nhat"><?= e(t('footer.new')) ?></a>
                    <a href="/san-pham?sap-xep=ban-chay"><?= e(t('footer.best')) ?></a>
                </div>
            </div>

            <div class="oa-footer__col">
                <span class="oa-eyebrow oa-eyebrow--ink"><?= e(t('footer.brand_col')) ?></span>
                <div class="oa-footer__list">
                    <a href="/gioi-thieu"><?= e(t('nav.about')) ?></a>
                    <a href="/lien-he"><?= e(t('footer.stores')) ?></a>
                    <a href="/dat-lich"><?= e(t('cta.book')) ?></a>
                    <?php if ((bool) config('ar.nav_enabled')): ?>
                        <a href="/thu-ar"><?= e(t('nav.ar')) ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="oa-footer__col">
                <span class="oa-eyebrow oa-eyebrow--ink"><?= e(t('footer.support')) ?></span>
                <div class="oa-footer__list">
                    <a href="/lien-he"><?= e(t('nav.contact')) ?></a>
                    <a href="/chinh-sach"><?= e(t('footer.care')) ?></a>
                    <a href="/tai-khoan?muc=don-hang"><?= e(t('footer.tracking')) ?></a>
                    <a href="/chinh-sach#bao-hanh"><?= e(t('footer.warranty')) ?></a>
                    <a href="/chinh-sach#bao-mat"><?= e(t('footer.privacy')) ?></a>
                </div>
            </div>

        </nav>

        <?php /* ── CỘT 3 — ba kênh hỗ trợ ────────────────────────────── */ ?>
        <div class="oa-footer__help">
            <span class="oa-footer__helptitle"><?= e(t('footer.help_title')) ?></span>
            <p class="oa-muted"><?= e(t('footer.help_text')) ?></p>

            <div class="oa-footer__channels">
                <a class="oa-footer__channel" href="<?= e($company['hotline_href']) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
                        <path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>
                    </svg>
                    <span><?= e($company['hotline']) ?></span>
                </a>

                <a class="oa-footer__channel" href="mailto:<?= e($company['email']) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
                        <rect x="3" y="5" width="18" height="14" rx="1"/>
                        <path d="M3 7l9 6 9-6"/>
                    </svg>
                    <span><?= e($company['email']) ?></span>
                </a>

                <a class="oa-footer__channel" href="<?= e($company['channels']['zalo']) ?>" target="_blank" rel="noopener">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
                        <path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>
                    </svg>
                    <span><?= e(t('footer.zalo')) ?></span>
                </a>
            </div>
        </div>

    </div>

    <?php /* ── HÀNG ICON MẠNG XÃ HỘI, hai đường kẻ chạy ra hai mép ───── */ ?>
    <div class="oa-footer__social">
        <div class="oa-footer__rule"></div>
        <div class="oa-footer__icons">
            <?php foreach ($company['socials'] as $s): ?>
                <?php if (!isset($socialIcons[$s['icon']])) { continue; } ?>
                <a href="<?= e($s['href']) ?>" target="_blank" rel="noopener"
                   aria-label="<?= e($s['label']) ?>"><?= $socialIcons[$s['icon']] ?></a>
            <?php endforeach; ?>
        </div>
        <div class="oa-footer__rule"></div>
    </div>

    <?php /* ── HÀNG CUỐI — bản quyền · bộ chuyển ngôn ngữ ─────────────── */ ?>
    <div class="oa-footer__legal">
        <span>© <?= date('Y') ?> <?= e($company['short_name']) ?> · <?= e($company['name']) ?> · <?= e(t('footer.tax')) ?> <?= e($company['tax_code']) ?></span>

        <?php /* aria-current="true" chứ không "page": ngôn ngữ đang chọn không
                 phải là "trang hiện tại". */ ?>
        <nav class="oa-footer__lang" aria-label="<?= e(t('lang.label')) ?>">
            <span><?= e(t('footer.lang')) ?> :</span>
            <?php foreach (I18N_NGON_NGU as $ma => $ten): ?>
                <?php $dang = $ma === currentLang(); ?>
                <a class="oa-u<?= $dang ? ' is-active' : '' ?>"
                   href="<?= e(langUrl($ma)) ?>"
                   lang="<?= e($ma) ?>"
                   <?= $dang ? 'aria-current="true"' : '' ?>><?= e(strtoupper($ma)) ?><span class="sr-only"> — <?= e($ten) ?></span></a>
            <?php endforeach; ?>
        </nav>
    </div>

</footer>
