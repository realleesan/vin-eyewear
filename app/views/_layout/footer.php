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
 *
 * Chân trang KẾT THÚC Ở ĐÂY. Hàng icon mạng xã hội và hàng bản quyền (kèm bộ
 * chuyển ngôn ngữ) đã bỏ — xem khối chú thích ở cuối file.
 *
 * Cột 1 có border-right, cột 3 có border-left — hai đường kẻ ấy là thứ chia
 * ba khối chứ không phải khoảng trống, nên đừng thay chúng bằng gap.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * HOTLINE DỜI TỪ THANH ĐẦU TRANG XUỐNG ĐÂY, vì mẫu bỏ hẳn dải tiện ích: nó
 * nay là một trong ba kênh có icon ở cột 3.
 * ═══════════════════════════════════════════════════════════════════════════
 */

$company = config('company');

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

    <?php
    /* ┌─ HAI HÀNG CUỐI ĐÃ BỎ (theo yêu cầu chủ dự án) ────────────────────
       │ Chân trang nay kết thúc ngay sau bốn cột bên trên. Hai thứ bị gỡ:
       │
       │   1. HÀNG ICON MẠNG XÃ HỘI — hai đường kẻ chạy ra hai mép, ở giữa
       │      là Facebook · Instagram · YouTube · TikTok.
       │      Dữ liệu VẪN CÒN ở config/company.php khoá 'socials'; chỉ không
       │      còn nơi nào vẽ ra. Mảng $socialIcons chứa path SVG của bốn logo
       │      ấy cũng đã gỡ khỏi đầu file này — xem lịch sử git nếu cần dựng
       │      lại.
       │
       │   2. HÀNG BẢN QUYỀN — dòng "© 2026 · CÔNG TY TNHH … · MST …" và BỘ
       │      CHUYỂN NGÔN NGỮ EN/VI nằm cạnh nó.
       │
       │ ═══ HỆ QUẢ PHẢI BIẾT: SITE KHÔNG CÒN LỐI ĐỔI NGÔN NGỮ ═══
       │
       │ Đây là chỗ DUY NHẤT trên cả site có bộ chuyển ấy (nó vốn ở dải tiện
       │ ích đầu trang, dời xuống đây khi dựng lại theo mẫu, nay bỏ hẳn).
       │
       │ Bản tiếng Anh VẪN SỐNG và vẫn dựng được từ máy chủ: mở bất kỳ trang
       │ nào kèm ?lang=en là core/i18n.php ghi cookie rồi chuyển hướng về URL
       │ sạch. Chỉ là không còn nút nào bấm tới. Toàn bộ lang/en.php,
       │ langUrl() và i18nXuLyChuyenNgonNgu() giữ nguyên, không gỡ gì —
       │ muốn có lại nút thì chỉ cần in ra một chỗ nào đó, không phải dựng
       │ lại tầng dịch.
       └──────────────────────────────────────────────────────────────────── */
    ?>

</footer>
