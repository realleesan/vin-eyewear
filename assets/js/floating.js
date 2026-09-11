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
       NGƯỠNG HIỆN NÚT — ĐO THEO QUÃNG CUỘN CỦA CHÍNH TRANG

       Yêu cầu: ở đỉnh trang thì ẩn, cuộn xuống thì hiện.

       ═══ ĐỪNG QUAY LẠI MỘT CON SỐ CỨNG ═══

       Bản đầu dùng `window.innerHeight * 1.5`. Nó hỏng im lặng trên mọi trang
       ngắn, vì ngưỡng có thể CAO HƠN CẢ QUÃNG CUỘN CÓ THẬT. Đo trên trang chủ
       ở khung nhìn 900px: trang cao 1827 nên cuộn hết cỡ cũng chỉ tới 927,
       trong khi ngưỡng là 1350 — nút không bao giờ hiện, và không có gì báo.

       Cùng cái bẫy ấy với '300px', '400px' hay 'một màn hình': trang nào ngắn
       hơn ngưỡng là nút chết.

       Nên ngưỡng lấy theo CHÍNH QUÃNG CUỘN của trang: một phần tư quãng ấy,
       chặn trên ở 600px cho trang rất dài.

         trang dài   (quãng 5000) -> 600   nút hiện sau khi cuộn một đoạn thật
         trang chủ   (quãng  927) -> 232   hiện sớm hơn, vì trang vốn ngắn
         trang ngắn  (quãng  100) ->  25   vẫn hiện được
         không cuộn được (quãng 0) ->  0   scrollY luôn 0 nên vẫn ẩn, đúng ý

       KHÔNG đặt sàn tối thiểu (kiểu `Math.max(120, ...)`): sàn chính là thứ
       dựng lại cái bẫy — trang có quãng cuộn 100px mà sàn 120 thì lại tắc.

       Đọc scrollHeight mỗi lần cuộn chứ không đo sẵn một lần: ảnh tải xong,
       mục gập bung ra, băng sản phẩm dựng thêm — chiều cao trang đổi suốt.
       -------------------------------------------------------------------- */

    function nguong() {
        var quangCuon = Math.max(
            0,
            document.documentElement.scrollHeight - window.innerHeight
        );

        return Math.min(quangCuon * 0.25, 600);
    }

    var shown   = false;
    var ticking = false;

    function sync() {
        var next = window.scrollY > nguong();
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

    /* Chiều cao trang còn đổi SAU khi tải xong — ảnh lazy vào chỗ, font thay
       thế đổi số dòng. Ngưỡng tính theo chiều cao nên phải đo lại, nếu không
       một trang vừa dài ra vẫn giữ ngưỡng cũ tính trên chiều cao lúc trống. */
    window.addEventListener('resize', sync, { passive: true });

    if (window.ResizeObserver) {
        new ResizeObserver(sync).observe(document.documentElement);
    }

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
