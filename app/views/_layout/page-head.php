<?php

/**
 * _layout/page-head.php — đầu trang, BA PHẦN RỜI NHAU.
 *
 * Dựng theo "Vin Eyewear Category.dc.html" và "Vin Eyewear Contact.dc.html":
 * hai bản thiết kế vẽ khối này GIỐNG HỆT NHAU tới từng con số (khối bo 36px
 * nền #f2e4dc, lề 8px/32px, đệm 56px 72px 60px, breadcrumb rồi tiêu đề 46px
 * bên trái với đoạn mô tả căn đáy bên phải) — chỉ khác chữ.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG PHẢI MỘT KHỐI BẬT/TẮT NGUYÊN CỤC
 *
 * Ba phần dưới đây độc lập, mỗi trang lấy đúng phần mình cần; thiếu tham số
 * nào thì phần đó KHÔNG in ra (không để lại khoảng trắng thừa):
 *
 *   $head_crumbs — mảng [['label' => …, 'url' => …], …], các bậc SAU "Trang
 *                  chủ". Bậc cuối thường không có 'url' — nó là trang hiện tại.
 *                  Cần khi trang nằm sâu trong cây điều hướng và người dùng có
 *                  thể vào thẳng từ Google: nó vừa định vị vừa có giá trị SEO.
 *
 *   $head_title  — tiêu đề <h1>. CHỈ truyền khi thân trang chưa tự có <h1>.
 *                  Trang chi tiết sản phẩm đã lấy tên sản phẩm làm <h1> nên nó
 *                  chỉ truyền breadcrumb — thêm dải tiêu đề nữa là nói hai lần
 *                  và đẩy ảnh sản phẩm xuống dưới màn hình đầu tiên.
 *
 *   $head_lead   — đoạn mô tả bên phải. Chỉ truyền khi THẬT SỰ có gì để nói.
 *                  Một câu viết cho có chỉ tốn màn hình.
 *
 *   $head_badge  — nhãn tròn nhỏ trên tiêu đề (bản bài viết).
 *
 * Không có tiêu đề lẫn mô tả thì khối nền hồng cũng không còn lý do tồn tại:
 * component tự bỏ nền, chỉ để lại hàng breadcrumb (`.pagehead--bare`).
 * ─────────────────────────────────────────────────────────────────────────────
 */

$head_crumbs = $head_crumbs ?? [];
$head_title  = $head_title  ?? null;
$head_lead   = $head_lead   ?? null;
$head_badge  = $head_badge  ?? null;

$hasTitle = $head_title !== null && $head_title !== '';
$hasLead  = $head_lead  !== null && $head_lead  !== '';
$hasBadge = $head_badge !== null && $head_badge !== '';

/* ┌─ ĐÃ BỎ BREADCRUMB TOÀN SITE (09/09/2026) ───────────────────────────────
   │ Khối chú thích dài bên dưới lập luận vì sao nên GIỮ nó; lập luận ấy đúng
   │ cho một site tiện ích, và đã thua khi site đi theo ngôn ngữ nhà mốt:
   │ "Home / Kính đổi màu / Chroma Đổi Màu Đa Dụng" là dòng đầu tiên mắt chạm
   │ trên trang sản phẩm, và nó đọc ra là sơ đồ thư mục, không phải trang
   │ trưng bày. Lối quay ra vẫn còn: wordmark, bảng xổ "Eyewear" có mặt ở mọi
   │ trang, và nút Lùi.
   │
   │ Tham số $head_crumbs VẪN NHẬN để không caller nào gãy — nó chỉ không
   │ còn được in ra. Trang chỉ truyền crumbs (trang sản phẩm, `--bare`) vì
   │ thế không còn gì để vẽ → thoát hẳn, không in ra một <section> rỗng có
   │ đệm.
   └──────────────────────────────────────────────────────────────────────── */
if (!$hasTitle && !$hasLead) {
    return;
}

/* ┌─ MỘT DÁNG DUY NHẤT, CĂN GIỮA ─────────────────────────────────────────
   │ Mẫu vẽ đầu trang y hệt nhau ở /san-pham, /lien-he, /gioi-thieu và
   │ /tai-khoan: tiêu đề 13px IN HOA giãn .06em, dưới nó một câu 10px màu
   │ #333, cả khối căn giữa với đệm 64px trên / 24px dưới.
   │
   │ Nên bỏ hẳn ba biến thể cũ (--article, --bare và huy hiệu chuyên mục):
   │ chúng là ba dáng khác nhau cho cùng một vai trò, tức là ba chỗ để lệch
   │ dần. $head_badge VẪN NHẬN để không caller nào gãy, nhưng nó in ra thành
   │ dòng nhãn nhỏ TRÊN tiêu đề — đúng chỗ mẫu đặt "01 — VỀ CHÚNG TÔI".
   │
   │ Kiểu dáng nằm trong .oa-pagehead của oa.css. Không còn
   │ components/page-head.css — file ấy không còn nơi nào nạp.
   └──────────────────────────────────────────────────────────────────────── */
?>
<section class="oa-pagehead">
    <?php if ($hasBadge): ?>
        <span class="oa-eyebrow"><?= e($head_badge) ?></span>
    <?php endif; ?>

    <?php if ($hasTitle): ?>
        <h1 class="oa-title"><?= e($head_title) ?></h1>
    <?php endif; ?>

    <?php if ($hasLead): ?>
        <p><?= e($head_lead) ?></p>
    <?php endif; ?>
</section>
