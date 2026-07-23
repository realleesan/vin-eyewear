<!-- ============================================================
     JOIN THE FAMILY — dải đen + đăng ký email (mọi trang)
     Form đăng ký duy nhất của site; footer bên dưới không lặp lại.
     ============================================================ -->
<section class="join">
    <div class="join__info">
        <h2 class="join__title">CẬP NHẬT CÙNG VIN EYEWEAR</h2>
        <p class="join__desc">Đón đầu những ưu đãi mới nhất và cập nhật phong cách kính cùng Vin Eyewear</p>
    </div>
    <form class="join__form" action="#" method="post">
        <input type="email" name="email" placeholder="nhập email" aria-label="Email đăng ký nhận tin" required>
        <button type="submit">Đăng ký</button>
    </form>
</section>

<footer class="site-footer">

    <!-- ============================================================
         FOOTER TOP — grid 3 cột link, hairline dọc ngăn cách
         ============================================================ -->
    <div class="footer-top">

        <!-- Cột 1: Khám phá — gom toàn bộ link điều hướng của site -->
        <div class="footer-col">
            <h3 class="footer-col__heading">Khám phá</h3>
            <a href="/">Trang chủ</a>
            <a href="/product">Sản phẩm</a>
            <a href="/about">Giới thiệu</a>
            <a href="/event">Sự kiện</a>
            <a href="/ar">Thử kính AR</a>
            <a href="/contact">Liên hệ</a>
        </div>

        <!-- Cột 2: Hỗ trợ — chính sách & điều khoản
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

        <!-- Cột 3: Liên hệ
             Địa chỉ 2 cơ sở lấy từ trang Contact — nguồn chuẩn là $stores
             trong ContactController::index(). Sửa ở đó thì sửa cả ở đây. -->
        <div class="footer-col footer-col--contact">
            <h3 class="footer-col__heading">Liên hệ</h3>

            <div class="footer-store">
                <p class="footer-store__name">CS1 &middot; Long Biên</p>
                <p class="footer-store__line">261 Ngọc Lâm, P. Bồ Đề, Q. Long Biên, TP. Hà Nội</p>
                <a class="footer-store__phone" href="tel:0912345678">0912 345 678</a>
            </div>

            <div class="footer-store">
                <p class="footer-store__name">CS2 &middot; Tây Hồ</p>
                <p class="footer-store__line">46 Hoàng Hoa Thám, P. Thụy Khuê, Q. Tây Hồ, TP. Hà Nội</p>
                <a class="footer-store__phone" href="tel:0987654321">0987 654 321</a>
            </div>

            <a class="footer-store__email" href="mailto:info@vineyewear.com">info@vineyewear.com</a>
        </div>

    </div>

    <!-- ============================================================
         FOOTER BOTTOM — dải đen: copyright | social | made in
         ============================================================ -->
    <div class="footer-bottom">
        <p class="footer-bottom__copy">&copy; <?= date('Y') ?> Vin Eyewear</p>

        <ul class="footer-bottom__social" role="list">
            <li>
                <a href="#" aria-label="Facebook">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M14 8.5h2.2V5.6h-2.4c-2.5 0-3.9 1.5-3.9 3.9v1.6H8v3h1.9V21h3v-6.9h2.2l.4-3h-2.6V9.8c0-.9.3-1.3 1.1-1.3z"/>
                    </svg>
                </a>
            </li>
            <li>
                <a href="#" aria-label="Instagram">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M12 4.6c2.4 0 2.7 0 3.6.05 2.5.1 3.6 1.3 3.7 3.7.05.9.05 1.2.05 3.6s0 2.7-.05 3.6c-.1 2.4-1.2 3.6-3.7 3.7-.9.05-1.2.05-3.6.05s-2.7 0-3.6-.05c-2.5-.1-3.6-1.3-3.7-3.7C4.6 14.7 4.6 14.4 4.6 12s0-2.7.05-3.6c.1-2.4 1.2-3.6 3.7-3.7C9.3 4.6 9.6 4.6 12 4.6zm0 3.4a4 4 0 100 8 4 4 0 000-8zm0 6.6a2.6 2.6 0 110-5.2 2.6 2.6 0 010 5.2zm4.2-6.7a.94.94 0 100-1.9.94.94 0 000 1.9z"/>
                    </svg>
                </a>
            </li>
            <li>
                <a href="#" aria-label="YouTube">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M21.6 8.2a2.5 2.5 0 00-1.8-1.8C18.2 6 12 6 12 6s-6.2 0-7.8.4A2.5 2.5 0 002.4 8.2 26 26 0 002 12c0 1.3.1 2.6.4 3.8a2.5 2.5 0 001.8 1.8c1.6.4 7.8.4 7.8.4s6.2 0 7.8-.4a2.5 2.5 0 001.8-1.8c.3-1.2.4-2.5.4-3.8s-.1-2.6-.4-3.8zM10.2 14.8V9.2l5 2.8z"/>
                    </svg>
                </a>
            </li>
            <li>
                <a href="#" aria-label="TikTok">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M16.5 3h-2.8v11.4a2.3 2.3 0 11-1.9-2.3V9.2a5.2 5.2 0 105 5.2V9.1a6.3 6.3 0 003.4 1V7.4a3.6 3.6 0 01-3.7-3.7z"/>
                    </svg>
                </a>
            </li>
        </ul>

        <p class="footer-bottom__made">Made in Hà Nội</p>
    </div>

</footer>
