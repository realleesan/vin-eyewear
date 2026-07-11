<footer class="footer">
  <div class="footer-inner">

    <div>
      <p class="footer-logo">VR<span>EYE</span>WEAR</p>
      <p class="footer-tagline">
        Kính mắt chính hãng — phong cách tối giản.<br>
        Truyền cảm hứng từ di sản thời trang New York.
      </p>
    </div>

    <div>
      <p class="footer-heading">Khám phá</p>
      <ul class="footer-links">
        <li><a href="/product">Sản phẩm</a></li>
        <li><a href="/about">Giới thiệu</a></li>
        <li><a href="/event">Ưu đãi</a></li>
        <li><a href="/ar">Thử kính AR</a></li>
      </ul>
    </div>

    <div>
      <p class="footer-heading">Hỗ trợ</p>
      <ul class="footer-links">
        <li><a href="/contact">Liên hệ</a></li>
        <li><a href="/contact">Tìm cửa hàng</a></li>
      </ul>
    </div>

  </div>

  <div class="footer-bottom">
    <p class="footer-copy">&copy; <?= date('Y') ?> VREyewear. All rights reserved.</p>
    <p class="footer-copy">Hà Nội, Việt Nam</p>
  </div>
</footer>

<script>
  // Mobile menu toggle — JS thuần, không dùng thư viện
  (function () {
    var toggle = document.getElementById('navToggle');
    var menu   = document.getElementById('navMenu');

    if (!toggle || !menu) return;

    toggle.addEventListener('click', function () {
      var isOpen = menu.classList.toggle('open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Đóng menu khi click ra ngoài
    document.addEventListener('click', function (e) {
      if (!toggle.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  })();
</script>