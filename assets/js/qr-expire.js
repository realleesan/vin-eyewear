/**
 * qr-expire.js — đồng hồ 5 phút của mã QR chuyển khoản.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * "HẾT HẠN" Ở ĐÂY NGHĨA LÀ GÌ — đọc trước khi sửa
 *
 * Mã QR của SePay KHÔNG có thời hạn ở phía ngân hàng: đối soát chạy theo NỘI
 * DUNG chuyển khoản (mã đơn), nên tiền chuyển sau 5 phút vẫn khớp đúng đơn ấy
 * và vẫn được ghi nhận. Thời hạn này là của TRANG, không phải của ngân hàng.
 *
 * Nó làm đúng hai việc có thật:
 *   · dừng vòng hỏi máy chủ của pay-watch.js — không để một tab bỏ quên hỏi
 *     mãi, hàng giờ, sau lưng khách;
 *   · buộc khách bấm một cái để xác nhận họ vẫn ở đây, rồi trang nạp lại và
 *     mở một cửa sổ 5 phút mới.
 *
 * ⚠ ĐỪNG ĐỔI CHỮ thành "mã không còn dùng được" hay "ngân hàng đã từ chối".
 *   Cả hai đều sai, và sai theo hướng làm một khách vừa chuyển tiền hoảng lên.
 *
 * ⚠ ĐỪNG CHẶN Ở MÁY CHỦ. Chặn nghĩa là từ chối tiền khách đã chuyển thật.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * TẮT JAVASCRIPT THÌ KHÔNG CÓ GÌ ĐỔI
 *
 * Dòng đồng hồ in ra kèm `hidden` và chỉ file này bỏ `hidden` đi. Không có JS
 * thì không có đồng hồ nào chạy — mà cũng không có lớp phủ nào che mã, và
 * pay-watch.js cũng không chạy nên chẳng có vòng hỏi nào cần dừng. Trang lùi
 * về đúng hành vi trước ngày 13/09/2026.
 */
(function () {
    'use strict';

    var o = document.querySelector('[data-qr-expire]');
    if (!o) { return; }

    var dongHo = o.querySelector('[data-qr-clock]');
    var giay   = parseInt(o.getAttribute('data-qr-expire'), 10);

    if (!dongHo || !(giay > 0)) { return; }

    /* Tới đây mới bỏ `hidden`: có JS thật, có đồng hồ thật. */
    o.hidden = false;

    var conLai = giay;
    var nhip   = null;

    var hai = function (n) { return (n < 10 ? '0' : '') + n; };

    var ve = function () {
        var p = Math.floor(conLai / 60), g = conLai % 60;
        dongHo.textContent = hai(p) + ':' + hai(g);
        /* datetime đi theo để trình đọc màn hình đọc ra một quãng thời gian
           chứ không phải hai con số rời. */
        dongHo.setAttribute('datetime', 'PT' + p + 'M' + g + 'S');
    };

    var hetGio = function () {
        clearInterval(nhip);
        document.documentElement.classList.add('is-qr-expired');

        /* DỪNG VÒNG HỎI. pay-watch.js tự thoát khi không còn [data-pay-watch],
           nên gỡ thuộc tính là đủ — không phải bày ra một kênh nói chuyện thứ
           hai giữa hai file. Khối vẫn ở nguyên trong trang, chỉ mất cái móc. */
        var hop = document.querySelector('[data-pay-watch]');
        if (hop) { hop.removeAttribute('data-watch-url'); }

        o.textContent = 'Mã QR đã hết hạn hiển thị.';
    };

    ve();

    nhip = setInterval(function () {
        conLai -= 1;
        if (conLai <= 0) { conLai = 0; ve(); hetGio(); return; }
        ve();
    }, 1000);

    /* "Tạo mã mới" = nạp lại chính trang này. Máy chủ in lại đúng mã QR ấy
       (cùng số tài khoản, cùng số tiền, cùng nội dung) và đồng hồ bắt đầu
       lại từ 5:00. Không dựng một đường /tao-ma-moi riêng: sẽ không có gì
       để nó làm ngoài việc vẽ lại đúng trang này. */
    document.addEventListener('click', function (e) {
        if (!(e.target instanceof Element)) { return; }
        if (!e.target.closest('[data-qr-again]')) { return; }
        window.location.reload();
    });
})();
