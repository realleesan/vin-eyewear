/**
 * contact.js — bản đồ cơ sở ở trang Liên hệ.
 *
 * Hai việc, đều là TĂNG CƯỜNG chứ không phải điều kiện để trang chạy:
 *
 *   1. Đổi cơ sở trên bản đồ mà không tải lại trang. Mỗi thẻ cơ sở là một
 *      <a href="?cs=MÃ"> thật; tắt JavaScript thì bấm vào vẫn đổi được bản đồ,
 *      chỉ là qua một lượt tải trang.
 *
 *   2. Nút "Chỉ đường" xin vị trí hiện tại của khách rồi mở Google Maps với
 *      lộ trình từ đúng chỗ khách đang đứng tới cơ sở. Không có JS — hoặc
 *      khách từ chối chia sẻ vị trí — thì vẫn mở Google Maps như cũ, chỉ có
 *      điểm đến, và Google tự hỏi điểm đi.
 *
 * Mọi dữ liệu cần thiết đã nằm sẵn trong thuộc tính data-* của từng thẻ, nên
 * không phải gọi mạng lần nào.
 */

(function () {
    'use strict';

    var list  = document.querySelector('.cstores__list');
    var frame = document.getElementById('storeMap');

    if (!list || !frame) return;

    var card = {
        name:    document.querySelector('[data-map-name]'),
        address: document.querySelector('[data-map-address]'),
        link:    document.querySelector('[data-map-link]'),
    };

    if (!card.name || !card.address || !card.link) return;

    /* ────────────────────────────────────────────────────────────────────
       CHỈ ĐƯỜNG TỪ VỊ TRÍ HIỆN TẠI
       ──────────────────────────────────────────────────────────────────── */

    /**
     * Chèn điểm xuất phát vào link chỉ đường của Google Maps.
     *
     * Link gốc do PHP dựng đã có sẵn ?api=1&destination=..., ở đây chỉ nối
     * thêm origin — nhờ vậy hàm này không cần biết cơ sở nào đang được chọn.
     */
    function withOrigin(url, coords) {
        return url
            + '&origin=' + coords.latitude.toFixed(6) + ',' + coords.longitude.toFixed(6)
            + '&travelmode=driving';
    }

    /**
     * Mở Google Maps ở TAB MỚI — trang Liên hệ đang xem phải giữ nguyên.
     *
     * Không truyền 'noreferrer' (hay 'noopener') vào tham số features của
     * window.open: hai từ khoá đó cắt luôn tham chiếu trả về, nên window.open
     * trả null NGAY CẢ KHI tab mới đã mở bình thường. Coi null là "bị chặn"
     * rồi chuyển window.location là lý do trước đây một cú bấm mở cả hai chỗ:
     * tab mới hiện bản đồ, mà trang Liên hệ cũng bỏ đi theo.
     *
     * Nên mở trần rồi tự cắt liên kết ngược bằng win.opener = null, và chỉ khi
     * KHÔNG có tham chiếu thật (popup bị chặn) mới dựng một <a target="_blank">
     * bấm hộ — trình duyệt xét kiểu bấm-liên-kết nên thường vẫn cho qua. Hỏng
     * nốt thì cũng chỉ là không mở được gì, tab hiện tại vẫn còn nguyên.
     */
    function openMaps(url) {
        var win = window.open(url, '_blank');

        if (win) {
            win.opener = null; // thay cho rel="noreferrer" đã bỏ ở trên
            win.focus();
            return;
        }

        var a = document.createElement('a');

        a.href          = url;
        a.target        = '_blank';
        a.rel           = 'noopener noreferrer';
        a.style.display = 'none';

        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    /**
     * Trạng thái "đang định vị" của nút.
     *
     * Xin quyền vị trí có thể mất vài giây (hộp thoại của trình duyệt, rồi GPS),
     * mà trong khoảng đó màn hình không đổi gì cả thì khách sẽ tưởng nút hỏng và
     * bấm tiếp. Đổi chữ trên nút là cách báo rẻ nhất, đồng thời trình đọc màn
     * hình cũng đọc được vì đây là chữ thật chứ không phải hiệu ứng CSS.
     */
    function busy(el) {
        if (!el || el.dataset.label) return false; // đang chạy rồi, bỏ qua cú bấm thừa

        el.dataset.label = el.textContent;
        el.textContent   = 'Đang định vị…';
        el.classList.add('is-locating');

        return true;
    }

    function idle(el) {
        if (!el || !el.dataset.label) return;

        el.textContent = el.dataset.label;
        el.classList.remove('is-locating');
        delete el.dataset.label;
    }

    /**
     * Xin vị trí rồi mở chỉ đường.
     *
     * @param url  Link chỉ đường chỉ-có-điểm-đến (cũng là đường lui khi hỏng).
     * @param el   Phần tử vừa bấm, để hiện trạng thái chờ.
     */
    function directions(url, el) {
        // Không có API (trình duyệt cũ) hoặc trang chạy trên HTTP thường:
        // Chrome/Firefox chặn geolocation ngoài HTTPS. Mở luôn bản chỉ có
        // điểm đến, Google Maps sẽ tự hỏi điểm đi.
        if (!navigator.geolocation) {
            openMaps(url);
            return;
        }

        if (!busy(el)) return;

        // Chỉ một lần duy nhất. Cần cờ này vì hộp thoại xin quyền của trình
        // duyệt KHÔNG tính vào `timeout` bên dưới: khách để hộp thoại đó mở
        // rồi bấm sau, thì hàng chờ dự phòng đã chạy mất rồi.
        var done = false;

        function finish(origin) {
            if (done) return;
            done = true;

            idle(el);
            openMaps(origin ? withOrigin(url, origin) : url);
        }

        // Hàng chờ dự phòng: quá 20 giây thì trả nút về như cũ và mở bản chỉ
        // có điểm đến, để nút không kẹt mãi ở "Đang định vị…".
        window.setTimeout(function () { finish(null); }, 20000);

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                finish(pos.coords);
            },
            function () {
                // Từ chối quyền, hết giờ, hoặc máy không định vị được. Không
                // báo lỗi gì cả: khách vẫn tới được Google Maps, chỉ là phải
                // tự nhập điểm đi — báo lỗi ở đây chỉ làm phiền chứ không cho
                // khách thêm lựa chọn nào.
                finish(null);
            },
            {
                enableHighAccuracy: true,
                timeout:            10000,
                // Vị trí cũ trong vòng 5 phút vẫn dùng được: đi bộ 5 phút
                // không đủ đổi lộ trình, mà đỡ được cả lượt bật GPS.
                maximumAge:         300000,
            }
        );
    }

    /* ────────────────────────────────────────────────────────────────────
       ĐỔI CƠ SỞ + BẮT NÚT CHỈ ĐƯỜNG
       ──────────────────────────────────────────────────────────────────── */

    list.addEventListener('click', function (event) {
        var store = event.target.closest('.cstore');

        if (!store || !list.contains(store)) return;

        // Ctrl/Cmd/giữa chuột = người dùng cố ý mở tab mới -> để trình duyệt lo
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;

        event.preventDefault();

        // "Chỉ đường" nằm LỒNG trong thẻ <a> của cơ sở nên không thể tự là một
        // liên kết riêng (HTML cấm <a> trong <a>). Vì vậy phải tách ở đây, và
        // phải tách TRƯỚC nhánh "đang xem sẵn" bên dưới — bấm chỉ đường trên
        // chính cơ sở đang mở là trường hợp hay gặp nhất.
        if (event.target.closest('.cstore__go')) {
            directions(store.getAttribute('data-directions'), event.target.closest('.cstore__go'));
            return;
        }

        // Đang xem sẵn rồi thì không làm gì — tránh nạp lại iframe vô ích
        if (store.classList.contains('is-on')) return;

        list.querySelectorAll('.cstore').forEach(function (el) {
            el.classList.remove('is-on');
            el.removeAttribute('aria-current');
        });

        store.classList.add('is-on');
        store.setAttribute('aria-current', 'true');

        var name = store.getAttribute('data-name');

        frame.src = store.getAttribute('data-map');
        frame.title = 'Bản đồ ' + name;

        card.name.textContent    = name;
        card.address.textContent = store.getAttribute('data-address');
        card.link.href           = store.getAttribute('data-directions');

        // Địa chỉ trên thanh URL đi theo nội dung đang xem, để sao chép gửi
        // cho người khác vẫn ra đúng cơ sở này. replaceState chứ không
        // pushState: chọn qua lại giữa hai cơ sở không đáng để nút Back phải
        // lùi từng bước một.
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', store.getAttribute('href'));
        }
    });

    // Nút chỉ đường trên thẻ nổi của bản đồ. Đây là <a> thật với target=_blank,
    // nên không có JS thì bấm vẫn ra Google Maps — chỉ thiếu điểm xuất phát.
    card.link.addEventListener('click', function (event) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;

        // Không định vị được thì chẳng có gì để thêm vào link: cứ để trình
        // duyệt tự mở tab mới theo target="_blank" — đường đó không bao giờ
        // bị chặn, chặn cú bấm lại rồi mở bằng JS chỉ tổ rủi ro hơn.
        if (!navigator.geolocation) return;

        event.preventDefault();
        directions(card.link.getAttribute('href'), card.link);
    });
})();
