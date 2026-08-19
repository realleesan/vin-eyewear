/**
 * buy-flow.js — mua hàng không tải lại trang.
 *
 * CHỈ LÀ TĂNG CƯỜNG. Không có file này thì mọi thứ chạy y như cũ: form POST
 * sang /gio-hang/them, máy chủ chuyển hướng về, trình duyệt vẽ lại trang. Ở
 * đây chỉ thay bước "vẽ lại cả trang" bằng "thay đúng ba mảnh đã đổi".
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO KHÔNG DỰNG HỘP THOẠI BẰNG JAVASCRIPT
 *
 * Cách thường thấy là để JS tự vẽ hộp thoại từ dữ liệu JSON. Ở đây thì không:
 * hộp thoại có bốn bước, giá tiền, gói tròng, biến thể, tồn kho — dựng lại
 * bằng JS nghĩa là chép toàn bộ luật đó sang phía trình duyệt, và từ đó mỗi
 * lần sửa luật phải sửa hai nơi, sai một nơi là khách thấy giá khác giá thật.
 *
 * Nên file này KHÔNG dựng gì cả. Nó gửi đúng cái form mà trình duyệt vẫn gửi,
 * nhận về đúng trang HTML mà máy chủ vẫn trả, rồi lấy từ trang đó ra ba mảnh:
 *
 *     .bmodal          hộp thoại (mở, sang bước kế, hoặc biến mất khi đóng)
 *     .toast           dải báo "Đã thêm … vào giỏ hàng"
 *     [data-cart]      huy hiệu số lượng và dòng đếm trong bảng xổ giỏ hàng
 *
 * Máy chủ vẫn là nơi duy nhất quyết định mọi thứ. Trình duyệt chỉ đỡ phải vẽ
 * lại phần không đổi — nên không còn cú nháy trắng và không mất vị trí cuộn.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG THAY THẺ [data-hpop-trigger], CHỈ THAY RUỘT NÓ
 *
 * header.js gắn sự kiện click thẳng lên thẻ đó (không uỷ quyền qua document).
 * Thay cả thẻ là bảng xổ giỏ hàng chết ngay sau lần thêm hàng đầu tiên.
 */
(function () {
    'use strict';

    // Thiếu bất kỳ mảnh nào thì đứng yên, để trình duyệt gửi form như thường.
    if (!window.fetch || !window.DOMParser || !window.history || !history.pushState) return;

    /* e.submitter cho biết NÚT NÀO vừa được bấm, mà cả luồng mua dựa vào nó:
       "Mua ngay" và "Thêm vào giỏ" là hai nút của CÙNG một form, khác nhau
       đúng ở name/value của nút. Trình duyệt không có nó (Safari ≤ 15.3) mà
       vẫn chạy tiếp thì mọi cú bấm đều gửi thiếu — khách bấm "Mua ngay" lại
       ra "đã thêm vào giỏ". Thà lùi hẳn về cách cũ. */
    if (typeof SubmitEvent === 'undefined' || !('submitter' in SubmitEvent.prototype)) return;

    /* Hai nút ngoài trang gửi tới đây. Các bước GIỮA hộp thoại thì gửi sang
       /gio-hang/chon — không liệt kê từng địa chỉ mà bắt theo "form nằm trong
       hộp thoại", để thêm bước mới sau này không phải nhớ sửa file JS. */
    var BUY_ACTION = '/gio-hang/them';
    var busy = false;

    /** Thẻ đã mở hộp thoại — để trả con trỏ bàn phím về đúng chỗ khi đóng. */
    var opener = null;

    /** Đã có lần nào chính file này đẩy lịch sử chưa — xem khối popstate. */
    var pushed = false;

    function modal()  { return document.querySelector('.bmodal'); }

    /* ── Thay ba mảnh đã đổi ────────────────────────────────────────────── */
    function apply(html, url, push) {
        var doc = new DOMParser().parseFromString(html, 'text/html');

        // 1. HỘP THOẠI
        var oldModal = modal();
        var newModal = doc.querySelector('.bmodal');

        if (newModal) {
            var adopted = document.importNode(newModal, true);
            if (oldModal) { oldModal.replaceWith(adopted); } else { document.body.appendChild(adopted); }
        } else if (oldModal) {
            oldModal.remove();
        }

        // 2. DẢI BÁO. Xoá cái cũ trước rồi mới thêm cái mới: cùng một phần tử
        //    thì hiệu ứng mờ dần trong CSS không chạy lại, khách bấm thêm lần
        //    hai sẽ không thấy gì nhấp nháy và tưởng nút hỏng.
        var oldToast = document.querySelector('.toast');
        if (oldToast) oldToast.remove();

        var newToast = doc.querySelector('.toast');
        if (newToast) document.body.appendChild(document.importNode(newToast, true));

        // 3. GIỎ HÀNG TRÊN HEADER — chỉ ruột, xem khối chú thích đầu file.
        var oldCart = document.querySelector('[data-cart]');
        var newCart = doc.querySelector('[data-cart]');

        if (oldCart && newCart) {
            var oldTrigger = oldCart.querySelector('[data-hpop-trigger]');
            var newTrigger = newCart.querySelector('[data-hpop-trigger]');

            if (oldTrigger && newTrigger) {
                oldTrigger.innerHTML = newTrigger.innerHTML;
                oldTrigger.setAttribute('aria-label', newTrigger.getAttribute('aria-label') || '');
            }

            /* Thay CẢ bảng xổ chứ không riêng dòng đếm: danh sách liên kết
               trong đó đổi theo giỏ — giỏ rỗng chỉ có "Mua sắm", có hàng mới
               hiện "Xem giỏ hàng" và "Thanh toán". Chỉ chép dòng đếm thì sau
               khi thêm món đầu tiên, bảng xổ nói "1 sản phẩm" mà không có lối
               nào sang giỏ hàng cho tới lần tải trang thật.
               header.js chỉ nhớ [data-hpop] và thẻ mở, không nhớ bảng xổ. */
            var oldPanel = oldCart.querySelector('.hpop__panel');
            var newPanel = newCart.querySelector('.hpop__panel');
            if (oldPanel && newPanel) oldPanel.innerHTML = newPanel.innerHTML;
        }

        // 4. ĐỊA CHỈ TRÊN THANH URL. Phải đổi theo: ?mua= và ?buoc= là thứ
        //    quyết định hộp thoại đang mở ở bước nào, nên tải lại trang hay
        //    chia sẻ đường dẫn đều phải ra đúng cảnh khách đang nhìn.
        //
        //    KHÔNG đẩy khi chính nút Lùi vừa gọi tới đây: lúc đó trình duyệt
        //    đã tự lùi trong lịch sử rồi, đẩy thêm một mục nữa là bấm Lùi lần
        //    sau lại quay về đúng chỗ vừa rời — khách kẹt trong một vòng.
        if (url && push) { history.pushState(null, '', url); pushed = true; }

        focusModal();
    }

    /* Mở ra thì đưa con trỏ bàn phím vào trong; đóng lại thì trả về nút đã mở.
       Không có bước này, người dùng bàn phím bấm "Mua ngay" xong vẫn đứng ở
       đâu đó giữa trang trong khi màn hình đã phủ một lớp hộp thoại. */
    function focusModal() {
        var m = modal();

        if (!m) {
            if (opener && document.contains(opener)) opener.focus();
            opener = null;
            return;
        }

        var panel = m.querySelector('.bmodal__panel');
        if (!panel) return;

        if (!panel.hasAttribute('tabindex')) panel.setAttribute('tabindex', '-1');
        panel.focus();
    }

    /* ── Một lượt đi–về với máy chủ ─────────────────────────────────────── */
    function send(url, options, fallback, push) {
        busy = true;

        document.documentElement.classList.add('is-buying');

        fetch(url, options)
            .then(function (res) {
                /*
                 * MÁY CHỦ ĐÃ TRẢ LỜI THÌ TUYỆT ĐỐI KHÔNG GỬI LẠI.
                 *
                 * Nhánh dự phòng gửi lại form, mà thêm vào giỏ KHÔNG phải thao
                 * tác lặp lại được: add() đã ghi vào $_SESSION['cart'] xong rồi
                 * mới chuyển hướng. Trang đích lỗi 500 -> res.ok false -> gửi
                 * lại -> món vào giỏ HAI lần. Nhận được câu trả lời rồi thì
                 * chỉ còn việc đi tới đó bằng GET để khách thấy đúng trạng
                 * thái máy chủ đang có.
                 */
                if (!res.ok) {
                    window.location.href = res.url || window.location.href;
                    return;
                }

                /*
                 * MÁY CHỦ ĐƯA SANG TRANG KHÁC THÌ ĐI THẬT, ĐỪNG GHÉP MẢNH.
                 *
                 * "Mua ngay" kết thúc bằng chuyển hướng sang /thanh-toan. Cứ
                 * ghép ba mảnh như thường thì khách vẫn đứng ở trang danh mục
                 * mà mang hộp thoại và giỏ hàng của trang thanh toán — nhìn
                 * như không có gì xảy ra, trong khi đơn đã sẵn sàng ở nơi khác.
                 *
                 * So theo ĐƯỜNG DẪN, không so cả địa chỉ: các bước trong hộp
                 * thoại chỉ đổi ?mua= và ?buoc= trên chính trang đang đứng.
                 */
                if (new URL(res.url).pathname !== window.location.pathname) {
                    window.location.href = res.url;
                    return;
                }

                return res.text().then(function (html) { apply(html, res.url, push !== false); });
            })
            .catch(function () {
                /* Tới đây chỉ còn lỗi MẠNG (chưa có câu trả lời nào) hoặc HTML
                   không đọc nổi — giao lại cho trình duyệt làm theo cách cũ.
                   Khách vẫn mua được, chỉ là trang tải lại. */
                fallback();
            })
            .then(function () {
                busy = false;
                document.documentElement.classList.remove('is-buying');
            });
    }

    /* ── Gửi form: hai nút mua, và mọi form trong hộp thoại ─────────────── */
    document.addEventListener('submit', function (e) {
        var form = e.target;

        if (!(form instanceof HTMLFormElement)) return;

        var inModal = !!form.closest('.bmodal');

        if (!inModal && (form.getAttribute('action') || '').indexOf(BUY_ACTION) !== 0) return;

        /* Đang chờ một lượt khác thì KHÔNG chặn — để trình duyệt gửi form theo
           cách cũ. Chặn rồi bỏ qua là nuốt mất cú bấm: bấm "Thêm vào giỏ" ở
           thẻ A rồi thẻ B ngay sau đó, thẻ B không có gì xảy ra cả. Thà tải
           lại trang còn hơn im lặng không làm gì. */
        if (busy) return;

        e.preventDefault();

        // Nút vừa bấm cũng là dữ liệu của form (name/value), mà FormData(form)
        // không kèm nó — không thêm tay thì máy chủ mất mất thông tin nút nào.
        var data = new FormData(form);
        var btn  = e.submitter;

        if (btn && btn.name) data.append(btn.name, btn.value);

        opener = btn || form;

        /*
         * getAttribute('action') CHỨ KHÔNG PHẢI form.action.
         *
         * Nút "Mua ngay" mang name="action" (xem _layout/product-card.php), mà
         * mọi ô nhập trong form đều trở thành thuộc tính cùng tên của chính
         * form — nên form.action trả về CÁI NÚT, không phải địa chỉ. Ghép nó
         * vào fetch cho ra "/[object HTMLButtonElement]" và một cú 404, rồi
         * nhánh dự phòng gửi lại form theo lối cũ: trang vẫn tải lại, đúng
         * cái mà file này sinh ra để tránh.
         */
        var action = form.getAttribute('action') || window.location.pathname;

        send(action, { method: 'POST', body: data, credentials: 'same-origin' }, function () {
            /*
             * form.submit() KHÔNG kèm name/value của nút đã bấm — trình duyệt
             * chỉ gửi nút khi chính nó kích hoạt việc gửi. Thiếu chỗ này thì
             * lúc mạng hỏng: "Mua ngay" mất action=buy nên thành "thêm vào
             * giỏ" rồi đứng lại trang cũ, và trong hộp thoại thì mất che_do,
             * act — khách chọn "cắt thêm tròng" lại nhận gọng trần.
             */
            if (btn && btn.name) {
                var carry = document.createElement('input');

                carry.type  = 'hidden';
                carry.name  = btn.name;
                carry.value = btn.value;
                form.appendChild(carry);
            }

            form.submit();
        });
    });

    /* ── Bấm link trong hộp thoại: đóng, quay lại, đổi bước ─────────────── */
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        var link = e.target instanceof Element ? e.target.closest('.bmodal a[href]') : null;
        if (!link) return;

        // Mở tab mới hoặc đi ra ngoài site thì để trình duyệt lo.
        if (link.target && link.target !== '_self') return;
        if (link.origin !== window.location.origin) return;

        e.preventDefault();
        send(link.href, { credentials: 'same-origin' },
            function () { window.location.href = link.href; });
    });

    /* ── Esc để đóng ────────────────────────────────────────────────────────
       Bấm đúng nút đóng có sẵn thay vì tự dựng đường đóng riêng: nút đó đã
       mang địa chỉ "trang này, bỏ ?mua và ?buoc", nên URL và hộp thoại luôn
       nói cùng một điều. */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;

        var m = modal();
        if (!m) return;

        var close = m.querySelector('.bmodal__close');
        if (close) close.click();
    });

    /* ── Nút Lùi của trình duyệt ────────────────────────────────────────────
       pushState ở trên tạo ra các mục trong lịch sử; không nghe popstate thì
       bấm Lùi sẽ đổi địa chỉ mà hộp thoại đứng nguyên tại chỗ. */
    window.addEventListener('popstate', function () {
        /* CHỈ khi file này từng đẩy lịch sử. Không có chốt đó thì mọi cú bấm
           Lùi trên trang đều rơi vào đây — mà nhiều trang điều hướng bằng
           dấu thăng: băng ảnh sản phẩm (#anh-N), mục lục trang chính sách,
           liên kết nhảy tới nội dung ở header. Xem ba tấm ảnh rồi bấm Lùi sẽ
           thành tải lại cả trang qua mạng, nuốt dải báo và cướp con trỏ. */
        if (!pushed) return;

        send(window.location.href, { credentials: 'same-origin' },
            function () { window.location.reload(); }, false);
    });
}());
