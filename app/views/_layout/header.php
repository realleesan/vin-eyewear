<?php
// Xác định trang hiện tại để đánh dấu nav-link active
$currentPage = strtok($_SERVER['REQUEST_URI'], '?');
$currentPage = rtrim($currentPage, '/') ?: '/home';
?>
<header>
  <nav class="navbar">
    <div class="navbar-inner">

      <a href="/home" class="navbar-logo">VR<span>EYE</span>WEAR</a>

      <ul class="navbar-nav" id="navMenu">
        <li><a href="/home"    class="nav-link <?= $currentPage === '/home'    ? 'active' : '' ?>">Home</a></li>
        <li><a href="/product" class="nav-link <?= $currentPage === '/product' ? 'active' : '' ?>">Product</a></li>
        <li><a href="/about"   class="nav-link <?= $currentPage === '/about'   ? 'active' : '' ?>">About</a></li>
        <li><a href="/event"   class="nav-link <?= $currentPage === '/event'   ? 'active' : '' ?>">Event</a></li>
        <li><a href="/ar"      class="nav-link <?= $currentPage === '/ar'      ? 'active' : '' ?>">Try AR</a></li>
        <li><a href="/contact" class="nav-link <?= $currentPage === '/contact' ? 'active' : '' ?>">Contact</a></li>
      </ul>

      <button class="navbar-toggle" id="navToggle" aria-label="Mở menu" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
      </button>

    </div>
  </nav>
</header>