<?php

/**
 * _layout/translator.php — nhúng widget dịch Elfsight (Website Translator).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ TOÀN BỘ PHẦN SONG NGỮ CỦA SITE
 *
 * Trước 2026-08-30 site có một hệ dịch viết tay: config/lang/vi.php + en.php,
 * hàm t(), cookie vin_lang, route /ngon-ngu. Hệ đó chỉ phủ được KHUNG giao
 * diện — nav, footer, mega-menu, nút nổi. Tên sản phẩm, mô tả, bài viết, đánh
 * giá… tức là gần hết chữ trên màn hình, vẫn tiếng Việt ở cả hai ngôn ngữ.
 *
 * Nay đổi sang Elfsight: nó dịch bằng máy TOÀN BỘ text node trên trang, kể cả
 * dữ liệu từ CSDL. Đổi lại, bản dịch là bản máy — không ai biên tập được từng
 * câu như trong config/lang/en.php cũ.
 *
 * KHÔNG ĐỂ HAI HỆ CÙNG CHẠY. Widget này tự vẽ nút đổi ngôn ngữ riêng và không
 * biết gì về cookie vin_lang; giữ cả hai thì khách bấm nút này được nội dung
 * tiếng Anh với thanh nav tiếng Việt, bấm nút kia thì ngược lại.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA ĐIỀU PHẢI BIẾT TRƯỚC KHI SỬA FILE NÀY
 *
 * 1. MẢNH HTML NẠP NGẦM KHÔNG TỰ CÓ BẢN DỊCH.
 *    assets/js/catalog.js thay ruột .cfilter và .catmain khi khách bấm bộ lọc;
 *    assets/js/buy-flow.js thay .bmodal, .toast và [data-cart] sau mỗi cú
 *    "Thêm vào giỏ". HTML mới do máy chủ dựng nên LUÔN là tiếng Việt. Widget
 *    có bắt kịp hay không phụ thuộc vào việc nó có theo dõi DOM — Elfsight
 *    không công bố API "dịch lại", nên đừng gọi hàm nội bộ của họ ở đây: bản
 *    sau họ đổi tên là gãy im lặng. Nếu thấy lưới sản phẩm nhảy về tiếng Việt
 *    sau khi bấm lọc, đó là giới hạn của cách này, không phải lỗi cấu hình.
 *
 * 2. MÁY TÌM KIẾM CHỈ THẤY BẢN TIẾNG VIỆT.
 *    Dịch xảy ra trong trình duyệt của khách, sau khi HTML đã rời máy chủ.
 *    Google lập chỉ mục cái máy chủ trả về. Muốn có trang tiếng Anh cho SEO
 *    thì phải là URL riêng do máy chủ dựng — widget không làm được việc đó.
 *    (Hệ viết tay cũ cũng không, nên đây không phải bước lùi.)
 *
 * 3. HẠN MỨC LƯỢT XEM LÀ THẬT.
 *    Gói miễn phí 200 lượt/tháng; vượt là Elfsight tắt widget cho tới đầu
 *    tháng sau. Widget tắt thì trang vẫn chạy bình thường, chỉ mất nút đổi
 *    ngôn ngữ — không có màn hình lỗi nào. Xem config/app.php để biết vì sao
 *    mã widget nằm ở .env và vì sao máy dev nên để trống.
 * ─────────────────────────────────────────────────────────────────────────────
 */

/*
 * CHẶN KHU QUẢN TRỊ TRƯỚC MỌI THỨ KHÁC.
 *
 * /quan-tri có layout riêng (app/views/admin/_layout/master.php) nên tưởng như
 * không cần dòng này — nhưng HAI TRANG lọt lưới: /quan-tri/dang-nhap và
 * /quan-tri/quen-mat-khau dựng bằng CHÍNH master.php của site bán hàng, chỉ
 * thay đầu/chân trang qua $bareHeader (xem AdminAuthController). Thiếu điều
 * kiện này thì màn hình đăng nhập của nhân viên mọc ra một nút dịch của bên
 * thứ ba, và mỗi lần ai đó mở trang ấy là trừ một lượt vào hạn mức 200
 * lượt/tháng của trang bán hàng.
 *
 * Kiểm theo ĐƯỜNG DẪN chứ không theo $bareHeader: sau này có thêm trang quản
 * trị nào mượn khung này nữa thì nó tự được che, không phải nhớ sửa ở đây.
 *
 * Đây cũng đúng giả định B1 trong CLAUDE.md — khu quản trị chỉ tiếng Việt.
 */
if (str_starts_with(currentPath(), '/quan-tri')) {
    return;
}

$elfsightId = trim((string) config('app.elfsight_translator', ''));

/*
 * LỌC KÝ TỰ chứ không tin thẳng .env. Chuỗi này đi vào thuộc tính class của
 * một thẻ div — một dấu nháy lọt qua là thoát ra khỏi thuộc tính. e() đã chặn
 * được chuyện đó, nhưng ở đây chặn sớm hơn một tầng vì còn một lý do thứ hai:
 * mã widget của Elfsight chỉ gồm chữ, số và gạch nối. Gõ nhầm cả dòng
 * `<div class="elfsight-app-abc">` vào .env — lỗi rất dễ mắc khi chép từ trang
 * của họ — thì trượt điều kiện dưới đây và widget lặng lẽ không hiện, thay vì
 * in ra một thẻ div hỏng mà không ai để ý.
 */
if ($elfsightId === '' || !preg_match('/^[A-Za-z0-9-]+$/', $elfsightId)) {
    return;
}
?>
<?php
/*
 * defer + data-use-service-core: đúng nguyên văn đoạn nhúng Elfsight sinh ra.
 * defer để script của bên thứ ba không chặn lúc trang vẽ ra — nó là tiện ích
 * phụ, không phải nội dung.
 */
?>
<script src="https://static.elfsight.com/platform/platform.js" data-use-service-core defer></script>
<?php
/*
 * KHÔNG có data-elfsight-app-lazy, dù đoạn mẫu của Elfsight luôn kèm.
 *
 * Lazy nghĩa là widget chỉ khởi động khi thẻ div này cuộn vào tầm nhìn. Thẻ
 * nằm cuối <body> nên trên trang dài nó ở tận đáy: khách vào trang chủ, nhìn
 * đúng màn hình đầu rồi bỏ đi sẽ KHÔNG BAO GIỜ thấy nút đổi ngôn ngữ. Với một
 * widget nổi phải có mặt ngay từ đầu thì lazy là sai.
 *
 * ĐẶT CUỐI <body> CHỨ KHÔNG NHÉT VÀO ĐẦU TRANG: cụm nút bên phải header là một
 * hàng flex có JS bảng xổ riêng (assets/js/header.js) — chèn một khối do bên
 * thứ ba dựng vào giữa đó thì kích thước nhảy sau khi trang đã vẽ xong. Nên
 * trong trình soạn của Elfsight phải chọn kiểu hiển thị NỔI (floating), để
 * widget tự neo mình vào một góc màn hình. Chọn kiểu inline thì nó rơi xuống
 * đáy trang, dưới cả chân trang, và gần như không ai thấy.
 */
?>
<div class="elfsight-app-<?= e($elfsightId) ?>"></div>
