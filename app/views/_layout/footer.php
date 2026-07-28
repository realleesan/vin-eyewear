<?php
/**
 * _layout/footer.php
 * Footer 4 cột (mọi trang) — bố cục theo footer.jpeg, màu giữ nguyên design
 * system "Lower East Heritage": nền vàng, chữ đen, dải bottom đen.
 *
 * Section .join cũ (dải đen "CẬP NHẬT CÙNG VIN EYEWEAR" + form email) đã bỏ;
 * nội dung đăng ký nhận tin chuyển thành cột 4 "Đăng ký nhận tin" bên dưới.
 * Cụm social cũng chuyển từ .footer-bottom lên cột 4 -> dải bottom chỉ còn
 * 2 vế: copyright | made in.
 */
?>
<footer class="site-footer">

    <!-- ============================================================
         FOOTER TOP — grid 4 cột: Thương hiệu | Khám phá | Hỗ trợ | Nhận tin
         Hairline dọc ngăn cách giữ nguyên idiom cũ của site.
         ============================================================ -->
    <div class="footer-top">

        <!-- Cột 1: Thương hiệu + thông tin liên hệ
             Địa chỉ 2 cơ sở lấy từ trang Contact — nguồn chuẩn là $stores
             trong ContactController::index(). Sửa ở đó thì sửa cả ở đây. -->
        <div class="footer-brand">

            <a class="footer-logo" href="/">
                <!-- Mark: 2 mắt kính vuông + cầu nối — gọng vuông đúng tinh thần
                     sharp 0px của design system, không bo tròn. -->
                <span class="footer-logo__mark" aria-hidden="true">
                    <svg viewBox="0 0 44 24" focusable="false">
                        <rect x="2" y="6" width="16" height="12"/>
                        <rect x="26" y="6" width="16" height="12"/>
                        <path d="M18 12h8"/>
                    </svg>
                </span>
                <span class="footer-logo__word">VIN EYEWEAR</span>
            </a>

            <!-- MOCKUP: tên pháp nhân tạm đặt, thay bằng tên trên GPKD khi có. -->
            <p class="footer-brand__legal">Công ty Cổ phần Vin Eyewear Việt Nam</p>

            <ul class="footer-contact" role="list">
                <li class="footer-contact__item">
                    <span class="footer-contact__ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M12 21s7-5.6 7-11a7 7 0 10-14 0c0 5.4 7 11 7 11z"/>
                            <circle cx="12" cy="10" r="2.6"/>
                        </svg>
                    </span>
                    <span>CS1 &middot; 261 Ngọc Lâm, P. Bồ Đề, Q. Long Biên, TP. Hà Nội</span>
                </li>
                <li class="footer-contact__item">
                    <span class="footer-contact__ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M12 21s7-5.6 7-11a7 7 0 10-14 0c0 5.4 7 11 7 11z"/>
                            <circle cx="12" cy="10" r="2.6"/>
                        </svg>
                    </span>
                    <span>CS2 &middot; 46 Hoàng Hoa Thám, P. Thụy Khuê, Q. Tây Hồ, TP. Hà Nội</span>
                </li>
                <li class="footer-contact__item">
                    <span class="footer-contact__ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M6.5 3.5h3l1.5 4-2 1.4a12 12 0 006.1 6.1l1.4-2 4 1.5v3a2 2 0 01-2.2 2A16.5 16.5 0 014.5 5.7a2 2 0 012-2.2z"/>
                        </svg>
                    </span>
                    <a href="tel:0912345678">0912 345 678</a>
                </li>
                <li class="footer-contact__item">
                    <span class="footer-contact__ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <rect x="3" y="5.5" width="18" height="13"/>
                            <path d="M3.5 6.5l8.5 6.5 8.5-6.5"/>
                        </svg>
                    </span>
                    <a href="mailto:info@vineyewear.com">info@vineyewear.com</a>
                </li>
            </ul>
        </div>

        <!-- Cột 2: Khám phá — toàn bộ link điều hướng của site -->
        <nav class="footer-col" aria-label="Liên kết trang">
            <h3 class="footer-col__heading">Khám phá</h3>
            <a href="/">Trang chủ</a>
            <a href="/product">Sản phẩm</a>
            <a href="/about">Giới thiệu</a>
            <a href="/event">Sự kiện</a>
            <a href="/ar">Thử kính AR</a>
            <a href="/contact">Liên hệ</a>
        </nav>

        <!-- Cột 3: Hỗ trợ — chính sách & điều khoản
             MOCKUP: chưa có route cho các trang này nên tạm để href="#". -->
        <div class="footer-col">
            <h3 class="footer-col__heading">Hỗ trợ</h3>
            <a href="#">Chính sách bảo hành</a>
            <a href="#">Chính sách đổi trả</a>
            <a href="#">Chính sách bảo mật</a>
            <a href="#">Chính sách vận chuyển</a>
            <a href="#">Điều khoản sử dụng</a>
            <a href="#">Câu hỏi thường gặp</a>
        </div>

        <!-- Cột 4: Đăng ký nhận tin — form email duy nhất của site + social -->
        <div class="footer-col footer-news">
            <h3 class="footer-col__heading">Đăng ký nhận tin</h3>
            <p class="footer-news__desc">Bộ sưu tập mới và ưu đãi riêng, gửi thẳng vào hộp thư của bạn.</p>

            <form class="footer-news__form" action="#" method="post">
                <input
                    type="email"
                    name="email"
                    placeholder="Nhập email của bạn"
                    aria-label="Email đăng ký nhận tin"
                    required
                >
                <button type="submit" aria-label="Đăng ký nhận tin">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M4 12h15M13 6l6 6-6 6"/>
                    </svg>
                </button>
            </form>

            <ul class="footer-social" role="list">
                <li>
                    <a href="https://www.facebook.com/vineyewear" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M14 8.5h2.2V5.6h-2.4c-2.5 0-3.9 1.5-3.9 3.9v1.6H8v3h1.9V21h3v-6.9h2.2l.4-3h-2.6V9.8c0-.9.3-1.3 1.1-1.3z"/>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="https://shopee.vn/vineyewear" target="_blank" rel="noopener noreferrer" aria-label="Shopee">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path stroke="currentColor" stroke-width="1.6" fill="none" d="M8.5 7a3.5 3.5 0 017 0"/>
                            <path d="M4.5 7h15l-1 12.5a1.5 1.5 0 01-1.5 1.4H7a1.5 1.5 0 01-1.5-1.4z"/>
                            <path d="M10 15.2c.5.7 1.3 1 2.1 1 1.2 0 2-.6 2-1.5 0-2-3.8-1.2-3.8-3.1 0-.8.8-1.4 1.8-1.4.8 0 1.4.3 1.8.8"/>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="https://www.instagram.com/vineyewear" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M12 4.6c2.4 0 2.7 0 3.6.05 2.5.1 3.6 1.3 3.7 3.7.05.9.05 1.2.05 3.6s0 2.7-.05 3.6c-.1 2.4-1.2 3.6-3.7 3.7-.9.05-1.2.05-3.6.05s-2.7 0-3.6-.05c-2.5-.1-3.6-1.3-3.7-3.7C4.6 14.7 4.6 14.4 4.6 12s0-2.7.05-3.6c.1-2.4 1.2-3.6 3.7-3.7C9.3 4.6 9.6 4.6 12 4.6zm0 3.4a4 4 0 100 8 4 4 0 000-8zm0 6.6a2.6 2.6 0 110-5.2 2.6 2.6 0 010 5.2zm4.2-6.7a.94.94 0 100-1.9.94.94 0 000 1.9z"/>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="https://www.youtube.com/@vineyewear" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M21.6 8.2a2.5 2.5 0 00-1.8-1.8C18.2 6 12 6 12 6s-6.2 0-7.8.4A2.5 2.5 0 002.4 8.2 26 26 0 002 12c0 1.3.1 2.6.4 3.8a2.5 2.5 0 001.8 1.8c1.6.4 7.8.4 7.8.4s6.2 0 7.8-.4a2.5 2.5 0 001.8-1.8c.3-1.2.4-2.5.4-3.8s-.1-2.6-.4-3.8zM10.2 14.8V9.2l5 2.8z"/>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="https://www.tiktok.com/@vineyewear" target="_blank" rel="noopener noreferrer" aria-label="TikTok">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M16.5 3h-2.8v11.4a2.3 2.3 0 11-1.9-2.3V9.2a5.2 5.2 0 105 5.2V9.1a6.3 6.3 0 003.4 1V7.4a3.6 3.6 0 01-3.7-3.7z"/>
                        </svg>
                    </a>
                </li>
            </ul>
        </div>

    </div>

    <!-- ============================================================
         FOOTER BOTTOM — dải đen: copyright | made in
         ============================================================ -->
    <div class="footer-bottom">
        <p class="footer-bottom__copy">&copy; <?= date('Y') ?> Vin Eyewear. Đã đăng ký bảo hộ.</p>
        <p class="footer-bottom__made">Made in Hà Nội</p>
    </div>

</footer>
