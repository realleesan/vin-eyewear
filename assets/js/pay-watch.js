/**
 * pay-watch.js — màn QR tự biết lúc nào tiền về (/thanh-toan/chuyen-khoan)
 *
 * Thay cho nút "Tôi đã chuyển khoản" đã bỏ. Nút đó chỉ là lời khách nói; file
 * này đi hỏi máy chủ, và máy chủ chỉ trả lời "đã trả" khi
 * orders.payment_status đã thật sự đổi — xem OrderController::payStatus().
 *
 * CHỈ LÀ TĂNG CƯỜNG. Không có file này thì khối chờ trong HTML vẫn nói đúng
 * việc đang xảy ra, và lối ra (.js-watch-slow — xem đơn hàng, nhắn Zalo) hiện
 * sẵn thay vì bị ẩn đi. Khách không bao giờ kẹt trước một trang không có nút.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HỎI THƯA DẦN, VÀ NGỦ KHI KHÔNG AI NHÌN
 *
 * Chuyển khoản liên ngân hàng về trong khoảng một phút, nên phút đầu hỏi dày.
 * Sau đó thưa dần: người còn ngồi đó sau năm phút gần như chắc chắn đang chờ
 * một nhân viên đối chiếu tay, mà việc đó tính bằng chục phút chứ không phải
 * bằng giây. Hỏi mỗi 4 giây suốt nửa tiếng chỉ để chờ một thứ đổi mỗi giờ là
 * 450 request rác cho một khách — trên hosting miễn phí thì đó là cách nhanh
 * nhất để bị bóp băng thông.
 *
 * Tab bị giấu (khách chuyển sang app ngân hàng — tức LÚC NÀO CŨNG XẢY RA ở
 * màn này) thì dừng hẳn, quay lại là hỏi ngay một phát. Nhờ vậy khoảnh khắc
 * khách bấm về trình duyệt sau khi chuyển tiền xong luôn được hỏi tức thì,
 * không phải chờ hết nhịp.
 * ─────────────────────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    var box = document.querySelector('[data-pay-watch]');
    if (!box) return;

    var url = box.getAttribute('data-watch-url');
    if (!url) return;

    /* Thiếu một trong hai thứ này thì không có cách nào hỏi cho tử tế — để
       nguyên trang tĩnh, lối ra vẫn hiện sẵn. */
    if (!window.fetch || !window.JSON) return;

    var slow = box.querySelector('.js-watch-slow');
    var text = box.querySelector('.coqr__watchtext');

    /* Lối ra chỉ ẩn khi CHẮC CHẮN có người đang canh — tức ngay đây, sau khi
       mọi điều kiện trên đã qua. Ẩn sớm hơn (trong HTML) là đánh cược rằng
       file này chạy được. */
    if (slow) slow.hidden = true;

    var NHIP = [
        /* tới giây  ,  cách nhau */
        [2 * 60000,   4000],   // hai phút đầu: tiền về trong khoảng này
        [5 * 60000,   8000],   // tới phút thứ năm
        [30 * 60000, 20000]    // sau đó: đang chờ người đối chiếu tay
    ];

    var CHO_TOI_DA = 30 * 60000;   // nửa tiếng thì thôi, không hỏi nữa
    var HIEN_LOI_RA = 3 * 60000;   // ba phút chưa thấy gì thì đưa lối ra

    var batDau  = Date.now();
    var timer   = null;
    var hong    = 0;      // số lần hỏi liên tiếp không ra kết quả
    var xong    = false;  // đã có câu trả lời cuối cùng, đừng hỏi nữa

    function nhip() {
        var troi = Date.now() - batDau;

        for (var i = 0; i < NHIP.length; i++) {
            if (troi < NHIP[i][0]) return NHIP[i][1];
        }

        return 0;   // hết giờ
    }

    function loiRa() {
        if (slow) slow.hidden = false;
    }

    function dung(loi) {
        xong = true;
        clearTimeout(timer);

        if (loi && text) text.textContent = loi;

        loiRa();
    }

    function hen() {
        if (xong) return;

        clearTimeout(timer);

        if (Date.now() - batDau >= HIEN_LOI_RA) loiRa();

        var cho = nhip();

        if (cho === 0) {
            dung('Chưa nhận được xác nhận tự động.');
            return;
        }

        /* Tab đang bị giấu thì không hẹn gì cả — khối visibilitychange bên
           dưới sẽ đánh thức. */
        if (document.hidden) return;

        timer = setTimeout(hoi, cho);
    }

    function hoi() {
        if (xong || document.hidden) return;

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) {
                if (!res.ok) throw new Error('http ' + res.status);

                return res.json();
            })
            .then(function (data) {
                hong = 0;

                if (data && data.paid && data.href) {
                    /* replace chứ không assign: màn QR không đáng nằm lại
                       trong lịch sử. Bấm Back từ biên nhận mà rơi về đúng cái
                       mã QR vừa trả xong là mời khách chuyển tiền lần hai. */
                    window.location.replace(data.href);
                    xong = true;
                    return;
                }

                if (data && data.stop) {
                    if (data.href) {
                        window.location.replace(data.href);
                        xong = true;
                        return;
                    }

                    dung();
                    return;
                }

                hen();
            })
            .catch(function () {
                /* Mất mạng, hoặc máy chủ trả về thứ không phải JSON (hosting
                   miễn phí thỉnh thoảng chèn trang chặn bot vào giữa). Không
                   coi là câu trả lời — thử lại, nhưng đừng thử mãi. */
                hong++;

                if (hong >= 5) {
                    dung('Không kết nối được để kiểm tra tự động.');
                    return;
                }

                hen();
            });
    }

    document.addEventListener('visibilitychange', function () {
        if (xong) return;

        if (document.hidden) {
            clearTimeout(timer);
            return;
        }

        /* Vừa quay lại — đây là khoảnh khắc đáng hỏi nhất trong cả phiên:
           khách vừa từ app ngân hàng bấm về. Hỏi ngay, đừng chờ hết nhịp. */
        if (nhip() === 0) {
            dung('Chưa nhận được xác nhận tự động.');
            return;
        }

        hoi();
    });

    hen();
}());
