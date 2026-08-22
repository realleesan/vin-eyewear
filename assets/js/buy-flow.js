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

    /*
     * XIN MẢNH, ĐỪNG XIN CẢ TRANG.
     *
     * Máy chủ thấy header này thì chỉ in ba mảnh mà apply() thực sự dùng —
     * xem khối chú thích đầu _layout/master.php. Trang chủ trả 120KB, ba mảnh
     * đó ~8KB, mà DOMParser phải nhai hết 120KB rồi ta vứt đi 112KB: đo được
     * 680ms trên máy chậm, tức phần lâu nhất của cả cú bấm.
     *
     * Đặt trong send() chứ không ở từng nơi gọi: mọi lượt đi–về của file này
     * đều chỉ cần ba mảnh, kể cả các bước giữa hộp thoại và nút Lùi.
     *
     * Máy chủ cũ không biết header này thì trả nguyên trang như xưa và mọi
     * thứ vẫn chạy — apply() không quan tâm nó nhận được bao nhiêu HTML thừa.
     */
    function withFragmentHeader(options) {
        var opts = {};

        for (var k in options) {
            if (Object.prototype.hasOwnProperty.call(options, k)) opts[k] = options[k];
        }

        opts.headers = { 'X-Buy-Flow': '1' };

        return opts;
    }

    /*
     * `busyEl` là NÚT (hoặc liên kết) vừa được bấm.
     *
     * Không có nó thì giữa lúc bấm và lúc hộp thoại hiện ra, màn hình KHÔNG
     * đổi gì cả — chỉ con trỏ chuột thành hình đồng hồ, mà trên điện thoại thì
     * không có con trỏ nào. Một lượt đi–về vài trăm mili giây im lặng đọc ra
     * đúng như nút hỏng, và người ta bấm lại lần nữa.
     *
     * Đánh dấu ngay tại nút giữ phản hồi ở đúng chỗ mắt đang nhìn, và không
     * phải chờ máy chủ mới có gì để xem.
     */
    function send(url, options, fallback, push, busyEl) {
        busy = true;

        document.documentElement.classList.add('is-buying');
        if (busyEl) busyEl.classList.add('is-buy-busy');

        fetch(url, withFragmentHeader(options))
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
                    /*
                     * ĐỪNG ĐI TỚI MỘT ĐỊA CHỈ CHỈ NHẬN POST.
                     *
                     * /gio-hang/them và /gio-hang/chon từ chối GET và đá về
                     * /gio-hang. Nên khi một bước lỗi 500, res.url trỏ đúng
                     * vào một trong hai địa chỉ đó, và câu lệnh dưới đây từng
                     * biến "hộp thoại lỗi" thành "khách đột nhiên đứng ở giỏ
                     * hàng, không một lời giải thích" — mất luôn dấu vết để
                     * lần ra lỗi thật. Chuyện này đã xảy ra ngày 2026-08-22
                     * với bước nhập số đo.
                     *
                     * Tải lại CHÍNH trang đang đứng thì khách ở nguyên chỗ cũ,
                     * hộp thoại vẽ lại đúng bước dở dang, và họ bấm lại được.
                     * Vẫn là GET nên không có nguy cơ gửi lại form — đúng mối
                     * lo mà nhánh này sinh ra để tránh.
                     */
                    var dest = res.url || '';
                    var postOnly = dest.indexOf('/gio-hang/them') !== -1
                                || dest.indexOf('/gio-hang/chon') !== -1;

                    window.location.href = (dest && !postOnly)
                        ? dest
                        : window.location.href;
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
                /*
                 * ĐỊA CHỈ MỚI: ưu tiên header X-Buy-Url.
                 *
                 * Máy chủ nay trả THẲNG ba mảnh cho chính cú POST, không qua
                 * 302 nữa (xem BaseController::buyFragment) — nhanh gấp đôi
                 * trên hosting chậm, nhưng đổi lại res.url không còn là địa
                 * chỉ của bước mới mà là địa chỉ đã POST tới. Dùng nó để
                 * pushState thì thanh địa chỉ thành /gio-hang/chon, và F5 ở
                 * đó là mất cả hộp thoại.
                 *
                 * Máy chủ chưa lên bản mới thì không có header này và mọi thứ
                 * chạy y như cũ — hai bên không buộc phải lên cùng lúc.
                 */
                var dich = res.headers.get('X-Buy-Url') || res.url;

                if (new URL(dich, window.location.href).pathname !== window.location.pathname) {
                    window.location.href = dich;
                    return;
                }

                return res.text().then(function (html) { apply(html, dich, push !== false); });
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

                /* Nút trong hộp thoại đã bị apply() thay mất — gỡ lớp trên một
                   phần tử không còn trong trang cũng không sao, còn nút ngoài
                   trang (thẻ sản phẩm, trang chi tiết) thì ở nguyên đó và phải
                   trả về trạng thái thường. */
                if (busyEl) busyEl.classList.remove('is-buy-busy');
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
        }, true, btn || form);
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
            function () { window.location.href = link.href; }, true, link);
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

    /* ── Trang được phục hồi từ bộ nhớ đệm lịch sử ──────────────────────────
       Bấm Lùi từ MỘT TRANG KHÁC (ví dụ /thanh-toan sau khi "Mua ngay") không
       phải là popstate: trình duyệt dựng lại cả tài liệu cũ từ bfcache, kèm
       nguyên cái DOM lúc rời đi — tức là kèm cả hộp thoại đang mở. Máy chủ
       không được hỏi câu nào, nên khách mua xong quay lại vẫn thấy màn hình
       "Xác nhận sản phẩm" hiện ra như chưa có gì xảy ra.

       Hỏi lại máy chủ đúng địa chỉ đó rồi ghép ba mảnh: ý định mua đã bị xoá
       lúc món hàng vào giỏ, nên câu trả lời không còn hộp thoại và nó biến
       mất. Huy hiệu giỏ hàng cũng được sửa lại luôn — con số trong bản phục
       hồi là con số của lúc rời trang.

       Chỉ chạy khi trang ĐANG có hộp thoại: không có thì chẳng có gì lệch, và
       một lượt đi–về thừa cho mọi cú bấm Lùi trên cả site là không đáng. */
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted || !modal()) return;

        send(window.location.href, { credentials: 'same-origin' },
            function () { window.location.reload(); }, false);
    });

    /* ── Nút Lùi của trình duyệt ────────────────────────────────────────────
       pushState ở trên tạo ra các mục trong lịch sử; không nghe popstate thì
       bấm Lùi sẽ đổi địa chỉ mà hộp thoại đứng nguyên tại chỗ. */
    window.addEventListener('popstate', function () {
        /* Chốt để mọi cú bấm Lùi trên trang KHÔNG rơi hết vào đây: nhiều trang
           điều hướng bằng dấu thăng — băng ảnh sản phẩm (#anh-N), mục lục
           trang chính sách, liên kết nhảy tới nội dung ở header. Xem ba tấm
           ảnh rồi bấm Lùi mà thành tải lại cả trang qua mạng thì nuốt dải báo
           và cướp con trỏ.

           HAI vế chứ không phải một:

           · pushed — chính file này đã đẩy lịch sử, nên các mục lịch sử quanh
             đây là của nó;
           · đang có hộp thoại mở — lúc đó ?mua= và ?buoc= LÀ thứ quyết định
             màn hình, nên mọi cú Lùi đều phải hỏi lại máy chủ.

           Thiếu vế thứ hai thì có một lỗ: bấm Lùi từ TRANG KHÁC (ví dụ trang
           thanh toán) về đây làm trình duyệt tải lại tài liệu này từ đầu, tức
           `pushed` trở về false — rồi những cú Lùi tiếp theo giữa các bước
           không được xử lý nữa, và hộp thoại đứng im ở bước cũ trong khi địa
           chỉ đã lùi về bước trước, thậm chí đã rời hẳn khỏi luồng mua. */
        if (!pushed && !modal()) return;

        send(window.location.href, { credentials: 'same-origin' },
            function () { window.location.reload(); }, false);
    });
}());
