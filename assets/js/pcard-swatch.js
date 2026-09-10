/**
 * pcard-swatch.js — XEM TRƯỚC PHỐI MÀU trên thẻ sản phẩm.
 *
 * Rê chuột (hoặc Tab) vào một ô màu dưới giá thì ảnh của thẻ đổi sang đúng ảnh
 * của phối màu ấy; rời ra thì trả về ảnh gốc.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ TĂNG CƯỜNG, KHÔNG PHẢI ĐIỀU KIỆN ĐỂ THẺ CHẠY
 *
 * Mỗi ô màu là một <a href> thật trỏ sang trang chi tiết (xem
 * _layout/product-card.php). Tắt JavaScript thì hàng ô vẫn nói đúng "mẫu này
 * có mấy màu" và vẫn bấm sang được nơi CHỌN màu thật sự. File này chỉ thêm
 * phần xem trước.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * UỶ QUYỀN TỪ document, KHÔNG GẮN VÀO TỪNG Ô
 *
 * Hai lý do, và cái thứ hai mới là cái bắt buộc:
 *   1. một lưới có tới 12 thẻ × 4 ô màu = 48 phần tử phải gắn sự kiện;
 *   2. LƯỚI BỊ THAY MỚI. assets/js/catalog.js nạp ngầm rồi thay cả ruột
 *      `.catmain` sau mỗi cú lọc, còn buy-flow.js thay ruột thẻ sau khi thêm
 *      hàng. Listener gắn thẳng vào ô sẽ chết theo phần tử cũ và tính năng
 *      lặng lẽ biến mất sau cú lọc đầu tiên — đúng cái bẫy mà chú thích ở đầu
 *      catalog.js đã ghi lại.
 *
 * mouseover/mouseout (nổi bọt) chứ không mouseenter/mouseleave (không nổi bọt):
 * uỷ quyền từ document thì chỉ hai cái đầu mới tới nơi.
 */

(function () {
    'use strict';

    /* Thẻ <img> xem trước được TẠO MỘT LẦN cho mỗi thẻ sản phẩm, rồi dùng lại.
       Tạo mới mỗi lần rê chuột nghĩa là trình duyệt phải giải mã lại ảnh và
       hiệu ứng mờ không bao giờ chạy được — phần tử vừa sinh ra thì không có
       trạng thái trước để chuyển từ đó. */
    function layLopPhu(card) {
        var shot = card.querySelector('.pcard__shot');

        if (!shot) return null;

        var img = shot.querySelector('.pcard__swap');

        if (!img) {
            img = document.createElement('img');
            img.className = 'pcard__swap';
            /* alt rỗng + aria-hidden: ảnh này KHÔNG mang thông tin mới cho
               trình đọc màn hình — tên màu đã nằm trong aria-label của chính ô
               màu vừa được rê vào. Đọc thêm một lần nữa là đọc hai lần. */
            img.alt = '';
            img.setAttribute('aria-hidden', 'true');
            img.decoding = 'async';
            shot.appendChild(img);
        }

        return img;
    }

    function bat(swatch) {
        var src = swatch.getAttribute('data-swatch-img') || '';
        var card = swatch.closest('.pcard');

        /* Ô không có ảnh riêng thì KHÔNG đổi gì cả — thà không xem trước còn
           hơn xem trước sai màu. Cửa hàng mới gắn ảnh cho một phần biến thể. */
        if (!card || src === '') return;

        var img = layLopPhu(card);

        if (!img) return;

        /* Chỉ đặt lại src khi thật sự đổi ảnh: gán lại cùng một chuỗi vẫn làm
           trình duyệt bỏ ảnh đang vẽ và tải lại từ cache, đủ để thấy một cú
           nháy khi con trỏ đi qua đi lại giữa hai ô cùng ảnh. */
        if (img.getAttribute('src') !== src) {
            img.setAttribute('src', src);
        }

        img.classList.add('is-on');
    }

    function tat(swatch) {
        var card = swatch.closest('.pcard');
        var img  = card && card.querySelector('.pcard__swap');

        if (img) img.classList.remove('is-on');
    }

    function oMau(target) {
        return target instanceof Element ? target.closest('.pcard__swatch') : null;
    }

    document.addEventListener('mouseover', function (e) {
        var sw = oMau(e.target);
        if (sw) bat(sw);
    });

    document.addEventListener('mouseout', function (e) {
        var sw = oMau(e.target);

        /* relatedTarget là nơi con trỏ ĐANG ĐẾN. Còn nằm trong chính ô ấy thì
           đây chỉ là con trỏ đi qua một phần tử con — chưa rời đi. */
        if (sw && !sw.contains(e.relatedTarget)) tat(sw);
    });

    /* Bàn phím: focusin/focusout là bản nổi bọt của focus/blur. Không có hai
       nhánh này thì người dùng bàn phím Tab qua hàng ô màu mà ảnh đứng im —
       tức là tính năng chỉ tồn tại với người dùng chuột. */
    document.addEventListener('focusin', function (e) {
        var sw = oMau(e.target);
        if (sw) bat(sw);
    });

    document.addEventListener('focusout', function (e) {
        var sw = oMau(e.target);
        if (sw) tat(sw);
    });
})();
