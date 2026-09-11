/**
 * floating.js — S22 cụm nút nổi hỗ trợ.
 *
 * Hai việc:
 *   1. Mở/đóng cụm kênh liên hệ ở bề ngang hẹp.
 *   2. Hiện nút "lên đầu trang" sau khi cuộn đủ xa.
 *
 * Markup: app/views/_layout/floating-actions.php · Giao diện: components/floating.css
 * Chạy được với thuộc tính `defer`.
 */

(function () {
    'use strict';

    var root   = document.getElementById('fabRoot');
    if (!root) return;

    var toggle = document.getElementById('fabToggle');
    var list   = document.getElementById('fabList');
    var top    = document.getElementById('fabTop');

    /* ====================================================================
       1. MỞ/ĐÓNG CỤM KÊNH
       ==================================================================== */

    if (toggle && list) {
        // HTML để sẵn hidden cho trường hợp không có JavaScript — tới đây thì
        // đã chắc chắn có, nên gỡ ra.
        toggle.hidden = false;

        var setOpen = function (open) {
            root.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Đóng kênh hỗ trợ nhanh' : 'Mở kênh hỗ trợ nhanh');
        };

        toggle.addEventListener('click', function () {
            setOpen(!root.classList.contains('is-open'));
        });

        // Bấm ra ngoài -> đóng. Loại trừ chính cụm nút, nếu không cú click mở
        // cũng tự đóng ngay trong cùng một lần bắt sự kiện.
        document.addEventListener('click', function (e) {
            if (!root.classList.contains('is-open')) return;
            if (e.target.closest('#fabRoot')) return;
            setOpen(false);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !root.classList.contains('is-open')) return;
            setOpen(false);
            toggle.focus();
        });

        // Chuyển sang bề ngang rộng khi đang mở -> trả về trạng thái đóng:
        // ở đó danh sách luôn hiện, để nguyên is-open thì icon nút vẫn là dấu X.
        var wide = window.matchMedia('(min-width: 900px)');
        wide.addEventListener('change', function (e) {
            if (e.matches) setOpen(false);
        });
    }

    /* ====================================================================
       2. LÊN ĐẦU TRANG
       ==================================================================== */

    if (!top) return;

    /* --------------------------------------------------------------------
       NÚT LUÔN HIỆN — ĐÃ BỎ NGƯỠNG THEO VỊ TRÍ CUỘN

       Bản trước chỉ hiện nút khi đã cuộn quá MỘT MÀN HÌNH RƯỠI
       (`window.innerHeight * 1.5`). Hai vấn đề với con số đó:

       1. Nó cao hơn cả chiều cuộn của nhiều trang. Đo trên trang chủ ở khung
          nhìn 900px: trang cao 1827px nên cuộn hết cỡ cũng chỉ tới scrollY
          927 — trong khi ngưỡng là 1350. Nút KHÔNG BAO GIỜ hiện ra. Nó cũng
          không báo lỗi gì; chỉ là một nút không ai từng thấy.

       2. Nút hiện rồi biến mất theo vị trí cuộn làm cụm bốn nút góc phải lúc
          có lúc không — thứ mà người dùng để ý ra ngay.

       Nên nay hiện thẳng, không điều kiện. Cái giá: ở đúng đỉnh trang, bấm
       vào nút không đưa đi đâu cả (đã ở đỉnh rồi). Chấp nhận được — nó là
       một nút không gây hại, còn một nút không bao giờ xuất hiện thì vô dụng
       hoàn toàn.

       VẪN GỠ `hidden` BẰNG JAVASCRIPT chứ không bỏ thuộc tính đó khỏi HTML:
       không có JavaScript thì nút này không làm được gì (cả hành vi cuộn nằm
       ở handler bên dưới), và một nút chết nằm sẵn ở góc màn hình chỉ tổ gây
       bấm hụt. Xem chú thích cạnh #fabTop trong _layout/floating-actions.php.
       -------------------------------------------------------------------- */

    top.hidden = false;

    top.addEventListener('click', function () {
        /*
         * Trả tiêu điểm về đầu trang TRƯỚC khi cuộn: cuộn lên mà tiêu điểm vẫn
         * nằm ở nút dưới đáy thì nhấn Tab tiếp là nhảy ngược xuống.
         *
         * Thứ tự quan trọng — gọi focus() SAU khi bắt đầu cuộn mượt thì trình
         * duyệt huỷ hoạt ảnh đang chạy và trang dừng lại giữa chừng (đo được:
         * đứng ở scrollY = 17 thay vì 0).
         */
        var main = document.getElementById('noi-dung-chinh');
        if (main) main.focus({ preventScroll: true });

        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduce) {
            window.scrollTo({ top: 0, behavior: 'auto' });
            return;
        }

        /*
         * Không cần chốt lại đúng 0 nữa.
         *
         * Bản trước phải làm: header bung dải thông báo trở lại khi tới gần
         * đỉnh, trang cao thêm vài chục pixel, cơ chế neo cuộn bù lại và hoạt
         * ảnh dừng ở scrollY = 17 chứ không phải 0. Dải thông báo nay nằm
         * ngoài header và header cao cố định (xem components/header.css), nên
         * không còn cú đổi chiều cao nào để phải bù.
         */
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
