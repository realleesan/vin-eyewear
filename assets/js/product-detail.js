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
    o.addEventListener('input', kep);
    o.addEventListener('change', kep);

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
