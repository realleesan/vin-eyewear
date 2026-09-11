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
       LUẬT: ẨN KHI Ở ĐỈNH TRANG, HIỆN NGAY KHI ĐÃ CUỘN

       Đúng một câu, không có vùng xám: còn ở đỉnh (không cuộn lên được nữa)
       thì ẩn; rời khỏi đỉnh là hiện.

       ═══ HAI BẢN TRƯỚC ĐỀU SAI, GHI LẠI ĐỂ KHỎI QUAY VÒNG ═══

       Bản 1 — ngưỡng CỨNG `window.innerHeight * 1.5`. Nó cao hơn cả quãng
       cuộn của nhiều trang: trang chủ cuộn hết cỡ mới tới 927px trong khi
       ngưỡng là 1350. Nút không bao giờ hiện, và không báo lỗi gì.

       Bản 2 — ngưỡng THEO TỈ LỆ, 25% quãng cuộn (trần 600px). Chữa được lỗi
       "không bao giờ hiện", nhưng đẻ ra một vùng xám: trên trang dài, suốt
       600px đầu nút vẫn vắng mặt dù người dùng đã cuộn. Nhìn ra thì giống
       như nút không chịu hiện.

       Bản này bỏ hẳn khái niệm ngưỡng. Chỉ còn một câu hỏi: đang ở đỉnh hay
       không.

       VÌ SAO 4px CHỨ KHÔNG PHẢI 0: chuột và trackpad hay để lại scrollY lẻ
       1–2px ở sát đỉnh, và trình duyệt khôi phục vị trí cuộn khi bấm Lùi
       cũng thường lệch vài pixel. So với 0 thì nút nhấp nháy ở đúng chỗ nó
       phải đứng yên. Cùng con số với lớp .is-scrolled trong header.js.

       Trang KHÔNG CUỘN ĐƯỢC (nội dung ngắn hơn khung nhìn) thì scrollY luôn
       bằng 0 nên nút ẩn suốt — đúng ý, không cần luật riêng.
       -------------------------------------------------------------------- */

    /* Vùng chết ở sát đỉnh — xem lý do ngay trên. */
    var DEADZONE = 4;

    var shown   = false;
    var ticking = false;

    function sync() {
        var next = window.scrollY > DEADZONE;
        if (next === shown) return;
        shown = next;
        top.hidden = !next;
    }

    window.addEventListener('scroll', function () {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(function () {
            sync();
            ticking = false;
        });
    }, { passive: true });

    /* ĐÃ GỠ hai trình nghe `resize` và ResizeObserver. Chúng có mặt vì bản
       trước tính ngưỡng theo CHIỀU CAO TRANG, nên trang dài ra là phải đo
       lại. Luật mới chỉ đọc scrollY — chiều cao trang đổi bao nhiêu cũng
       không ảnh hưởng, và một ResizeObserver gắn lên <html> chạy suốt đời
       trang là cái giá không còn lý do để trả. */

    /* Mở trang ở giữa chừng (tải lại trang đã cuộn, link có #anchor) vẫn phải
       thấy nút ngay, không đợi tới lần cuộn đầu tiên. */
    sync();

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
