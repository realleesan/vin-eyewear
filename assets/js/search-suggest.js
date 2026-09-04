/**
 * search-suggest.js — gợi ý từ khoá cho ô tìm kiếm ở đầu trang.
 *
 * Căn cứ: SRS v1.3.1, quyết định X29 / Q10 (chốt 04/09/2026) — "tìm kiếm gần
 * đúng" của giai đoạn 1 gồm bỏ dấu, không phân biệt hoa thường, CỘNG THÊM gợi
 * ý từ khoá khi đang gõ. Không bao gồm dung sai lỗi chính tả.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TĂNG CƯỜNG, KHÔNG PHẢI PHỤ THUỘC
 *
 * Ô tìm kiếm là một <form> GET bình thường và nó hoạt động đầy đủ khi không có
 * file này — tắt JavaScript, mạng hỏng, hay chính file này lỗi thì người dùng
 * vẫn gõ và bấm Tìm được. Mọi thứ ở đây chỉ đổ thêm <option> vào một
 * <datalist> rỗng có sẵn trong HTML.
 *
 * Vì thế KHÔNG có xử lý lỗi nào hiện ra màn hình: gọi hỏng thì không có gợi ý,
 * và không có gợi ý là trạng thái bình thường của ô này. Một dải báo lỗi cho
 * một tính năng phụ là bắt người dùng chú ý tới thứ không cản trở họ.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO CÓ HOÃN 200ms VÀ CÓ HUỶ LƯỢT CŨ
 *
 * Gõ "kính mát" là tám lần sự kiện input. Không hoãn thì đó là tám lượt gọi
 * máy chủ cho một lần tìm, trên hosting miễn phí. Hoãn 200ms gom chúng lại
 * thành một, và 200ms thì ngắn hơn khoảng nghỉ giữa hai từ nên người gõ không
 * cảm thấy chậm.
 *
 * AbortController huỷ lượt đang bay khi có lượt mới: hai lượt về không đúng
 * thứ tự gửi đi là chuyện thường trên mạng chậm, và khi đó danh sách gợi ý
 * hiện kết quả của chuỗi CŨ trong khi ô đã mang chuỗi mới.
 */

(function () {
    'use strict';

    var HOAN_MS  = 200;
    var TOI_THIEU = 2;   // dưới hai ký tự thì không hỏi — xem SearchController

    var o = document.getElementById('headerSearch');

    if (!o) {
        return;
    }

    var ds = document.getElementById(o.getAttribute('list'));
    var duongDan = o.getAttribute('data-suggest');

    if (!ds || !duongDan) {
        return;
    }

    var hen = null;
    var dangBay = null;

    /* Nhớ kết quả theo từ khoá. Xoá một ký tự rồi gõ lại là chuyện xảy ra
       suốt, và không nhớ thì mỗi lần như vậy là một lượt gọi cho một câu trả
       lời vừa nhận được xong. Bộ nhớ này sống trong một lần tải trang. */
    var dem = Object.create(null);

    function ve(danhSach) {
        /* Dựng lại toàn bộ thay vì sửa từng <option>: danh sách tối đa tám
           mục nên dựng lại là rẻ, còn so khớp từng mục là chỗ sinh lỗi lệch
           một dòng mà không ai thấy ngay. */
        ds.textContent = '';

        for (var i = 0; i < danhSach.length; i++) {
            var op = document.createElement('option');
            op.value = danhSach[i];
            ds.appendChild(op);
        }
    }

    function hoi(q) {
        if (dem[q]) {
            ve(dem[q]);
            return;
        }

        if (dangBay) {
            dangBay.abort();
        }

        dangBay = typeof AbortController === 'function' ? new AbortController() : null;

        fetch(duongDan + '?q=' + encodeURIComponent(q), {
            signal: dangBay ? dangBay.signal : undefined,
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !Array.isArray(data.goi_y)) {
                    return;
                }

                dem[q] = data.goi_y;

                /* Ô có thể đã đổi nội dung trong lúc chờ. Vẽ kết quả của một
                   chuỗi không còn nằm trong ô là hiện gợi ý cho câu hỏi cũ. */
                if (o.value.trim() === q) {
                    ve(data.goi_y);
                }
            })
            .catch(function () {
                /* Cố ý im lặng — xem khối chú thích đầu file. */
            });
    }

    o.addEventListener('input', function () {
        var q = o.value.trim();

        if (hen) {
            clearTimeout(hen);
        }

        if (q.length < TOI_THIEU) {
            ve([]);
            return;
        }

        hen = setTimeout(function () { hoi(q); }, HOAN_MS);
    });
})();
