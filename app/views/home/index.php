<?php

/**
 * home/index.php — trang chủ.
 *
 * Dựng theo "Vin Eyewear Home.dc.html" (Claude Design), CHỈ các khối của bản
 * thiết kế:
 *
 *   hero → danh mục → bộ sưu tập → sản phẩm mới về → bán chạy
 *   → kiểm tra 5 phút → cắt lắp tròng → đo mắt → đánh giá
 *
 * KHỐI "KÊU GỌI HÀNH ĐỘNG" CUỐI TRANG ĐÃ XOÁ theo yêu cầu (03/09/2026):
 * hai nút của nó ("Mua ngay" → /san-pham, "Tìm cửa hàng" → /lien-he) trùng
 * với lối đi đã có ở header, ở khối danh mục và ở chân trang. Trang chủ nay
 * kết thúc bằng khối đánh giá; chân trang tự chừa khoảng trước nó — xem
 * .page-home-index trong components/footer.css.
 *
 * BỐN KHỐI ĐẦU KHÔNG THEO THỨ TỰ CỦA BẢN THIẾT KẾ. Bản thiết kế xếp
 * bộ sưu tập → mới về → danh mục → bán chạy; ở đây danh mục lên trước.
 * Bốn khối này là bốn cách chọn hàng khác nhau, xếp từ RỘNG xuống HẸP:
 *
 *   danh mục     "bạn cần loại kính gì" — sáu lối vào phủ hết kho hàng
 *   bộ sưu tập   "theo phong cách nào"  — vài chủ đề đã tuyển sẵn
 *   mới về       hàng mới nhất
 *   bán chạy     hàng người khác đang mua
 *
 * Khách chưa biết mình muốn gì thì câu hỏi đầu tiên là loại kính, không phải
 * bộ sưu tập nào — nên danh mục đứng ngay dưới hero.
 *
 * ĐỔI SO VỚI BẢN TRƯỚC (tám khối): thêm "bộ sưu tập" và "sản phẩm mới về",
 * và khối "chọn theo khuôn mặt" (_layout/home/style-guide.php) nhường chỗ cho
 * "kiểm tra 5 phút" — cùng vị trí, nhưng từ ba tấm ảnh dẫn sang bộ lọc thành
 * ba việc khách tự làm được ngay tại đây.
 *
 * CHÍN PARTIAL NGOÀI BẢN THIẾT KẾ ĐÃ XOÁ HẲN (authority, brands, press,
 * newsletter, ar-tryon, lens-partners, lens-spotlight, face-shape-guide,
 * trust-bar) cùng CSS và config/brands.php của chúng. Trước đây chúng nằm lại
 * trong thư mục mà không nơi nào include — xem lịch sử git nếu cần dựng lại.
 *
 * Header và footer KHÔNG nằm trong phạm vi trang này — chúng là partial dùng
 * chung cho mọi trang (_layout/header.php, _layout/footer.php).
 */
?>

<?php
/*
 * ═════════════════════════════════════════════════════════════════════════════
 * NHỊP KỂ CHUYỆN CỦA TRANG CHỦ — ĐÃ XẾP LẠI
 *
 * Thứ tự cũ trộn hàng hoá với tiện ích ngay từ đầu:
 *
 *   hero → danh mục → bộ sưu tập → mới về → bán chạy → kiểm tra nhanh →
 *   tròng kính → đo mắt → đánh giá
 *
 * Đọc từ trên xuống, khách gặp một khối chọn hàng, rồi một khối trắc nghiệm,
 * rồi lại một khối bán hàng. Kết quả là trang đọc ra như một BẢNG LIỆT KÊ
 * TÍNH NĂNG của một sàn thương mại điện tử, chứ không như một nhà kính có
 * tiếng nói riêng — dù từng khối một đều sạch.
 *
 * Thứ tự mới gom theo VAI TRÒ, mỗi vai trò một quãng liền mạch:
 *
 *   1. HERO            hình ảnh dẫn dắt
 *   2. BỘ SƯU TẬP      khối ảnh chiến dịch — thứ hai khách thấy là HÌNH, không
 *                      phải một hàng ô danh mục
 *   3. MỚI VỀ          hàng hoá
 *   4. BÁN CHẠY        hàng hoá
 *   5. DANH MỤC        lối duyệt theo loại — sau khi đã thấy hàng thật
 *   6. TRÒNG KÍNH      ─┐
 *   7. ĐO MẮT           ├─ DỊCH VỤ: ba khối tiện ích đứng LIỀN NHAU thành một
 *   8. KIỂM TRA NHANH  ─┘  chương, thay vì rải giữa các khối bán hàng
 *   9. ĐÁNH GIÁ        lời chứng, đóng trang
 *
 * KHÔNG KHỐI NÀO BỊ BỎ và không khối nào đổi nội dung — chỉ đổi thứ tự gọi.
 * ═════════════════════════════════════════════════════════════════════════════
 */
?>

<?php
/*
 * ═════════════════════════════════════════════════════════════════════════════
 * RÚT TỪ CHÍN KHỐI XUỐNG SÁU (09/09/2026)
 *
 * Khối chú thích ngay trên mô tả lần xếp lại TRƯỚC, và câu chốt của nó là
 * "KHÔNG KHỐI NÀO BỊ BỎ". Lần này thì có — nên đọc khối đó như lịch sử, không
 * phải như luật đang hiệu lực.
 *
 * Xếp lại thứ tự đã sửa được nhịp, nhưng không sửa được ĐỘ DÀI: chín chương
 * trên một trang chủ làm nó đọc ra như cổng dịch vụ, không như một nhà kính.
 * Ba khối bỏ đi đều là khối "tiện ích" — chúng cạnh tranh chú ý với hàng hoá:
 *
 *   DANH MỤC        lối duyệt theo loại. Bảng xổ "Eyewear" trên thanh nav đã
 *                   là đúng lối ấy, đầy đủ hơn, và có mặt ở MỌI trang.
 *   KIỂM TRA NHANH  CHUYỂN chỗ, không xoá — nay nằm cuối /dat-lich. Người đã
 *                   quan tâm đo khúc xạ đúng là người cần tư vấn dáng mặt và
 *                   chọn tròng; ở trang chủ nó chặn đường tới hàng.
 *   ĐÁNH GIÁ        lời chứng. Bỏ khỏi trang chủ theo chủ trương biên tập.
 *
 * BA FILE PARTIAL VẪN NẰM NGUYÊN TRÊN ĐĨA. Bật lại khối nào chỉ là bỏ dấu
 * chú thích ở dòng tương ứng — không có gì bị xoá.
 *
 * NHỊP CÒN LẠI, sáu chương, hai chương một vai trò:
 *
 * NHỊP ĐANG CHẠY, ba chương:
 *
 *   1. HERO          hình ảnh dẫn dắt
 *   2. BỘ SƯU TẬP    khối ảnh chiến dịch
 *   3. MỚI VỀ        ─┐ HÀNG HOÁ, hai băng trượt liền nhau
 *   4. BÁN CHẠY      ─┘
 *
 * (Ba chương "TRÒNG KÍNH · ĐO MẮT · ĐÁNH GIÁ" của bản thiết kế cũ vẫn còn file
 *  trên đĩa, xem khối chú thích ở cuối trang này.)
 * ═════════════════════════════════════════════════════════════════════════════
 */
?>

<?php partial('_layout/home/hero'); ?>

<?php
/* BỘ SƯU TẬP ĐÃ TRỞ LẠI, VÀ ĐỨNG NGAY SAU HERO (10/09/2026).

   Thứ tự ba chương chính nay là: Bộ sưu tập → Mới về → Bán chạy.

   Khối này tự gọi CollectionModel::visible() bên trong nên không cần thêm gì
   vào HomeController::index() — khác với 'categories' ngay dưới, khối đó phải
   được truyền dữ liệu vào.

   Đặt trước hai băng hàng hoá là có lý do hình ảnh: hero vừa hết là ba tấm
   ảnh chiến dịch lớn, rồi mới tới lưới thẻ. Để hai băng thẻ dính ngay sau
   hero thì trang mở ra là một bảng hàng, không phải một cửa hàng. */
partial('_layout/home/collections');
?>

<?php partial('_layout/home/new-arrivals', ['products' => $newArrivals]); ?>

<?php partial('_layout/home/best-sellers', ['products' => $bestSellers]); ?>

<?php /* ── NĂM KHỐI CÒN LẠI ĐANG RÚT KHỎI TRANG CHỦ ────────────────────────
         Năm file partial VẪN NẰM NGUYÊN trên đĩa. Bỏ dấu chú thích một dòng là
         khối ấy trở lại đúng chỗ cũ; không có gì bị xoá.

         partial('_layout/home/categories', ['categories' => $categories]);
             → cũng phải trả 'categories' vào HomeController::index().
         partial('_layout/home/lenses');
         partial('_layout/home/eye-exam');
         partial('_layout/home/quick-check');
             → ĐÃ CHUYỂN sang app/views/booking/index.php, đừng bật cả hai chỗ:
               #quickCheck phải là duy nhất trên một trang.
         partial('_layout/home/reviews');

         LỐI ĐI KHÔNG MẤT THEO. Bộ sưu tập vẫn có bảng xổ riêng trên thanh nav
         (có mặt ở mọi trang) và trang /bo-suu-tap; tròng kính, đo mắt và kiểm
         tra nhanh nằm ở /dat-lich; danh mục nằm trong bảng xổ "Eyewear".
         ──────────────────────────────────────────────────────────────────── */ ?>
