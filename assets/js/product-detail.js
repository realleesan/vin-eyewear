/**
 * product-detail.js — ô số lượng ở trang chi tiết sản phẩm.
 *
 * CHỈ LÀ TĂNG CƯỜNG. Không có file này thì ô số lượng vẫn là <input type="number">
 * với min/max thật: mũi tên lên xuống vẫn dừng đúng chỗ, và số gõ tay vượt tồn
 * kho vẫn bị máy chủ kẹp lại kèm câu báo (xem CartController::add). Mất đúng
 * hai thứ: sửa số ngay khi gõ, và dòng chữ giải thích tại chỗ.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VIỆC NÓ SINH RA ĐỂ LÀM: ĐUỔI BONG BÓNG CỦA TRÌNH DUYỆT
 *
 * Ô số có max="3". Gõ 31 rồi bấm mua thì trình duyệt tự chặn và bung bong bóng
 * "Value must be less than or equal to 3." — tiếng Anh giữa một trang tiếng
 * Việt, kiểu dáng của hệ điều hành chứ không phải của cửa hàng, và CSS không
 * với tới được (::-webkit-validation-bubble đã bị bỏ, Firefox chưa từng có).
 *
 * Tệ hơn: nó CHẶN LUÔN câu báo tử tế mà máy chủ đã có sẵn. Form không được gửi
 * đi, nên '"Gọng T01" chỉ còn 3 sản phẩm. Đã thêm tối đa vào giỏ hàng.' không
 * bao giờ có cơ hội hiện ra.
 *
 * Cách chữa KHÔNG phải là đổi lời cho bong bóng (setCustomValidity chỉ đổi được
 * CHỮ, còn cái hộp vẫn là hộp của trình duyệt). Cách chữa là để nó không bao
 * giờ có việc: kẹp giá trị về đúng khoảng NGAY KHI GÕ, nên tới lúc bấm mua thì
 * ô luôn hợp lệ và trình duyệt không có gì để phàn nàn. Chỗ trống đó dành cho
 * một dòng chữ của chính trang, đặt ngay dưới ô, tiếng Việt, đúng bộ màu.
 * ─────────────────────────────────────────────────────────────────────────────
 */

(function () {
    'use strict';

    var o = document.getElementById('so-luong');

    if (!o) return;

    var note = document.querySelector('[data-qty-note]');
    var max  = parseInt(o.getAttribute('max'), 10);
    var min  = parseInt(o.getAttribute('min'), 10) || 1;

    if (!max || max < 1) return;

    /* Hẹn giờ xoá dòng chữ. Giữ tham chiếu để mỗi lần gõ lại thì hẹn giờ cũ bị
       huỷ — không thì lần gõ thứ hai bị lần đầu xoá chữ giữa chừng. */
    var hen = null;

    /**
     * Nói ra, rồi tự lặng đi sau 4 giây.
     *
     * KHÔNG để dòng chữ nằm mãi: nó đứng ngay trên nút "Mua ngay", và một lời
     * cảnh báo còn đó sau khi người ta đã sửa xong sẽ đọc như một lỗi chưa
     * được giải quyết.
     */
    function noi(chu) {
        if (!note) return;

        note.textContent = chu;
        note.hidden = false;

        if (hen) window.clearTimeout(hen);

        hen = window.setTimeout(function () {
            note.hidden = true;
            note.textContent = '';
        }, 4000);
    }

    /**
     * Kẹp về khoảng [min, max].
     *
     * BỎ QUA Ô TRỐNG. Người ta xoá hết để gõ số mới là chuyện thường; nhảy vào
     * điền "1" ngay lúc ô vừa trống là cướp mất chữ họ đang gõ dở. Ô trống lúc
     * gửi form đã có min="1" và máy chủ lo — quantity rỗng thành 1.
     */
    function kep() {
        var chu = o.value.trim();

        if (chu === '') return;

        var so = parseInt(chu, 10);

        if (isNaN(so)) return;

        if (so > max) {
            o.value = max;
            noi('Chỉ còn ' + max + ' sản phẩm.');
        } else if (so < min) {
            o.value = min;
            noi('Số lượng tối thiểu là ' + min + '.');
        }
    }

    /* 'input' bắt cả gõ phím lẫn bấm mũi tên; 'change' bắt nốt đường dán chuột
       phải và vài trình duyệt bắn muộn. Kẹp hai lần không hại gì — lần sau
       thấy số đã đúng thì không làm gì cả. */
    o.addEventListener('input', function () { kep(); dongBoNut(); });
    o.addEventListener('change', function () { kep(); dongBoNut(); });

    /* ------------------------------------------------------------------
       HAI NÚT − / +

       Chúng chỉ tồn tại khi có JS (CSS giấu chúng cho tới khi <html> mang lớp
       .js), nên toàn bộ đoạn này không cần nhánh dự phòng nào.
       ------------------------------------------------------------------ */

    var nut = Array.prototype.slice.call(
        document.querySelectorAll('[data-qty-step]')
    );

    nut.forEach(function (b) {
        b.addEventListener('click', function () {
            var buoc = parseInt(b.getAttribute('data-qty-step'), 10) || 0;
            var hienTai = parseInt(o.value, 10);

            if (isNaN(hienTai)) hienTai = min;

            /* Kẹp NGAY ở đây chứ không nhờ kep(): kep() còn kèm việc hiện lời
               nhắc, mà bấm vào một nút ĐANG MỜ thì không xảy ra — nút mờ không
               nhận cú bấm. Bấm nút sáng thì luôn ra số hợp lệ, không có gì để
               nhắc. */
            var moi = Math.min(max, Math.max(min, hienTai + buoc));

            if (moi === hienTai) return;

            o.value = moi;
            dongBoNut();

            /* Báo cho phần còn lại của trang biết giá trị vừa đổi bằng mã.
               Gán .value KHÔNG tự phát sự kiện 'input' — thiếu dòng này thì
               bất kỳ thứ gì nghe ô số (nay chưa có, mai thì có) sẽ im lặng bỏ
               qua mọi lần bấm ± mà không ai hiểu vì sao. */
            o.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    /**
     * Mờ nút nào không còn chỗ để đi.
     *
     * "+" mờ khi đã chạm trần tồn kho — đúng thứ khách cần thấy: không phải
     * bấm thêm một cái nữa rồi mới biết là hết. "−" mờ ở số 1 vì dưới nữa là
     * số 0, mà bỏ món khỏi giỏ là việc của trang giỏ hàng, không phải của ô
     * số lượng ở đây.
     *
     * Dùng thuộc tính `disabled` thật chứ không chỉ tô nhạt bằng CSS: nút mờ
     * mà vẫn bấm được là lời nói dối, và người dùng bàn phím sẽ Tab vào một
     * nút không làm gì cả.
     */
    function dongBoNut() {
        if (o.disabled) return;   // hết hàng: máy chủ đã tắt cả ba, đừng bật lại

        var v = parseInt(o.value, 10);

        if (isNaN(v)) v = min;

        nut.forEach(function (b) {
            var buoc = parseInt(b.getAttribute('data-qty-step'), 10) || 0;

            b.disabled = buoc > 0 ? v >= max : v <= min;
        });
    }

    dongBoNut();

    /* ------------------------------------------------------------------
       ĐỔI PHƯƠNG ÁN -> ĐỔI TRẦN

       Trần in ra từ máy chủ là tồn của phương án DỒI DÀO NHẤT, vì trang tĩnh
       không biết khách sẽ chọn cái nào (xem $maxMua trong product/detail.php).
       Có JS thì biết: mỗi ô chọn mang sẵn data-stock của chính nó.

       Không làm bước này thì nút "+" mờ ở một con số không đúng với phương án
       đang chọn — hứa 12 chiếc trong khi màu khách chọn chỉ còn 2.
       ------------------------------------------------------------------ */

    var pa = Array.prototype.slice.call(
        document.querySelectorAll('input[name="variant_id"][data-stock]')
    );

    if (pa.length) {
        pa.forEach(function (r) {
            r.addEventListener('change', function () {
                if (!r.checked) return;

                var ton = parseInt(r.getAttribute('data-stock'), 10);

                if (isNaN(ton) || ton < 1) return;

                max = ton;
                o.setAttribute('max', ton);

                /* Đang chọn 5 chiếc màu này rồi đổi sang màu chỉ còn 2: hạ số
                   xuống 2 và NÓI RA. Im lặng sửa số của khách ở đây khác hẳn
                   lúc gõ — họ không đụng vào ô số, họ đổi màu. */
                if (parseInt(o.value, 10) > ton) {
                    o.value = ton;
                    noi('Phương án này chỉ còn ' + ton + ' sản phẩm.');
                }

                dongBoNut();
            });
        });

        // Phương án đang chọn sẵn lúc mở trang cũng phải áp trần của nó.
        var dangChon = pa.filter(function (r) { return r.checked; })[0];

        if (dangChon) dangChon.dispatchEvent(new Event('change'));
    }

    /* Lưới cuối: ô trống hoặc số lạ lúc bấm mua. Không chặn form — chỉ sửa giá
       trị rồi để nó đi tiếp, vì tới đây khách đã bấm mua và việc của mình là
       cho họ mua được, không phải dựng thêm một cái chặn nữa. */
    if (o.form) {
        o.form.addEventListener('submit', function () {
            if (o.value.trim() === '') o.value = min;
            kep();
        });
    }
})();
