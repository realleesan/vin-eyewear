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
 * AI GỌI FILE NÀY, VÀ VÌ SAO ĐÚNG BA CHỖ
 *
 *   _layout/header.php          cụm tác vụ, ngay trước nút tìm kiếm
 *   _layout/checkout-header.php đầu trang rút gọn của luồng thanh toán
 *   _layout/auth-header.php     đầu trang rút gọn của luồng tài khoản
 *
 * MỘT TRANG CHỈ ĐƯỢC MỘT THẺ. Ba chỗ trên loại trừ nhau: master.php dựng hoặc
 * header đầy đủ, hoặc đúng một đầu trang rút gọn. In hai thẻ cùng class trên
 * một trang thì Elfsight vẽ hai widget và tính hai lượt xem vào hạn mức.
 *
 * VÌ SAO CẢ HAI ĐẦU TRANG RÚT GỌN CŨNG CÓ: widget nhớ ngôn ngữ khách đã chọn
 * giữa các trang, nhưng chỉ dịch được trang nào có thẻ này. Thiếu ở bước thanh
 * toán là khách đang đọc tiếng Anh bỗng gặp một biểu mẫu tiền nong toàn tiếng
 * Việt — đúng cái bước không được phép để họ đọc mò. Bản dịch máy làm lệch vài
 * nhãn ô nhập vẫn hơn mất hẳn ngôn ngữ giữa chừng.
 *
 * KHÔNG có ở _layout/admin-login-header.php, và cũng không cần nhớ điều đó:
 * điều kiện /quan-tri ngay dưới đây tự chặn.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TÊN SẢN PHẨM KHÔNG ĐƯỢC DỊCH
 *
 * Mọi chỗ in tên một món hàng đều mang class="notranslate" và translate="no".
 * Grep 'notranslate' trong app/views/ ra đủ 14 chỗ; thêm chỗ in tên sản phẩm
 * mới thì thêm cả hai vào.
 *
 * VÌ SAO: "Vin T01 Titan" dịch sang tiếng Anh không ra thứ gì tốt hơn, mà lại
 * ra thứ khách đọc xong không tìm thấy trên hoá đơn, trong tin nhắn của cửa
 * hàng, hay khi gọi điện hỏi. Tên riêng thì để nguyên là đúng, ở mọi ngôn ngữ.
 *
 * HAI CÁCH ĐÁNH DẤU VÌ HAI BÊN ĐỌC KHÁC NHAU, không phải viết thừa:
 *   translate="no"        thuộc tính chuẩn HTML — trình dịch cài sẵn trong
 *                         Chrome/Safari đọc cái này
 *   class="notranslate"   Elfsight loại trừ THEO TÊN LỚP, khai trong trình
 *                         soạn widget (Settings -> exclusions). Phải vào đó
 *                         gõ "notranslate" MỘT LẦN, không thì phía Elfsight
 *                         vẫn dịch dù markup đã đánh dấu.
 *
 * TÊN TRÒNG KÍNH THÌ NGƯỢC LẠI — CỐ Ý ĐỂ DỊCH. "Tròng trắng 1.50", "Chống
 * sáng xanh 1.61" là câu mô tả chứ không phải tên riêng; khách nước ngoài cần
 * đọc hiểu để chọn đúng. Kiểu tròng ("Đơn tròng", "Đa tròng") cũng vậy.
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
 *    tháng sau. Widget tắt thì trang vẫn chạy, chỗ này còn lại một khoảng
 *    trống rộng 40px trong cụm tác vụ — không có màn hình lỗi nào. Xem
 *    config/app.php để biết vì sao mã widget nằm ở .env và vì sao máy dev nên
 *    để trống.
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
 *
 * KHÔNG khai mã thì không in gì cả, kể cả thẻ bọc: một ô rỗng rộng 40px nằm
 * giữa cụm tác vụ là thứ không ai giải thích được khi nhìn vào giao diện.
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
 *
 * Thẻ script nằm CẠNH thẻ div chứ không ở cuối <body>: cả hai luôn đi cùng
 * nhau, và tách ra thì thêm một chỗ gọi nữa phải nhớ. defer đã bảo đảm nó
 * không chặn dù đứng ở giữa <header>.
 */
?>
<script src="https://static.elfsight.com/platform/platform.js" data-use-service-core defer></script>
<?php
/*
 * THẺ BỌC .header-lang GIỮ CHỖ SẴN, xem components/header.css.
 *
 * Ruột thẻ div dưới đây do Elfsight dựng, và nó chỉ xuất hiện SAU khi
 * platform.js tải xong — tức là sau khi trang đã vẽ ra một lượt. Không giữ
 * chỗ trước thì cụm tác vụ nở ra giữa chừng và wordmark bên trái bị đẩy, ngay
 * trước mắt người đang đọc.
 *
 * KHÔNG có data-elfsight-app-lazy, dù đoạn mẫu của Elfsight luôn kèm. Lazy
 * nghĩa là widget chỉ khởi động khi thẻ cuộn vào tầm nhìn; thẻ này nằm trong
 * thanh nav dính trên đỉnh nên luôn trong tầm nhìn, và lazy chỉ thêm một nhịp
 * chờ trước khi nút hiện ra.
 */
?>
<div class="header-lang">
    <div class="elfsight-app-<?= e($elfsightId) ?>"></div>
</div>
