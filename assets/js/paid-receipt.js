/**
 * paid-receipt.js — hai nút "Tải biên nhận" và "Chia sẻ" ở trang biên nhận
 * (/thanh-toan/thanh-cong).
 *
 * KHÔNG CÓ FILE NÀY THÌ HAI NÚT KHÔNG HIỆN RA.
 *
 * Đó là chủ ý, và nó ngược với mọi file JS khác trong dự án. Nếp chung là "tắt
 * JS thì luồng vẫn chạy, chỉ xấu hơn" — nhưng hai việc này KHÔNG có đường lui
 * nào: in một trang và mở khay chia sẻ của hệ điều hành đều chỉ làm được bằng
 * JS. Vẽ ra một cái nút bấm vào không có gì xảy ra còn tệ hơn là không vẽ.
 *
 * Nên markup để sẵn `hidden` trên khối chứa, file này gỡ ra. Xem khối chú
 * thích cùng chủ đề ở đầu app/views/order/paid.php.
 *
 * (Nút "Sao chép" mã đơn thì theo nếp chung và do copy-btn.js lo: mã đã in ra
 *  dạng chữ ngay cạnh, thiếu JS thì khách bôi đen chép tay.)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * "TẢI" LÀ HỘP THOẠI IN, KHÔNG PHẢI MỘT TỆP PDF
 *
 * Dựng PDF thật cần một thư viện, mà dự án không có phụ thuộc ngoài nào (xem
 * CLAUDE.md). window.print() để chính trình duyệt lo — mọi trình duyệt hiện
 * nay đều có "Lưu thành PDF" ngay trong hộp thoại đó, và bản in đã được tạo
 * dáng riêng ở khối @media print cuối assets/css/order-complete.css.
 * ─────────────────────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    var tools = document.querySelector('[data-receipt-tools]');
    if (!tools) return;

    var btnPrint = tools.querySelector('[data-receipt-print]');
    var btnShare = tools.querySelector('[data-receipt-share]');

    /* Nút chia sẻ cần MỘT trong hai khả năng mới có việc để làm. Không có cả
       hai (trình duyệt cũ, hoặc trang mở bằng http nên không phải ngữ cảnh bảo
       mật) thì bỏ hẳn nút đi thay vì để nó im lặng không phản ứng. */
    var shareDuoc = !!navigator.share
        || !!(navigator.clipboard && window.isSecureContext);

    if (btnShare && !shareDuoc) {
        btnShare.remove();
        btnShare = null;
    }

    /* Không còn nút nào thì đừng gỡ `hidden` — một hàng trống có đệm 14px ở
       trên trông như lỗi dựng trang. */
    if (!btnPrint && !btnShare) return;

    tools.hidden = false;

    if (btnPrint) {
        btnPrint.addEventListener('click', function () {
            window.print();
        });
    }

    if (!btnShare) return;

    var nhan = btnShare.querySelector('[data-share-label]') || btnShare;
    var goc  = nhan.textContent;
    var hen  = null;

    function baoDaChep() {
        nhan.textContent = 'Đã sao chép liên kết ✓';

        clearTimeout(hen);
        hen = setTimeout(function () { nhan.textContent = goc; }, 2000);
    }

    btnShare.addEventListener('click', function () {
        var ma  = document.querySelector('.opaid__code');
        var url = location.href;

        /* navigator.share mở khay chia sẻ THẬT của máy (Zalo, Messenger, tin
           nhắn) — trên điện thoại đây là thứ khách muốn. Nó chỉ có ở ngữ cảnh
           bảo mật và phải gọi từ trong một cú bấm, nên đứng đúng chỗ này. */
        if (navigator.share) {
            navigator.share({
                title: 'Biên nhận ' + (ma ? ma.textContent.trim() : 'đơn hàng'),
                url: url
            }).catch(function () {
                /* Khách bấm Huỷ trong khay chia sẻ cũng rơi vào đây. Im lặng
                   là đúng: họ vừa nói "thôi", báo thêm một dòng là cãi lại. */
            });

            return;
        }

        /* Máy tính để bàn thường không có khay chia sẻ. Chép liên kết vào bộ
           nhớ tạm là thứ gần nhất còn dùng được, và phải NÓI RA là đã chép —
           không có phản hồi thì khách bấm lại lần nữa. */
        navigator.clipboard.writeText(url).then(baoDaChep, function () {
            nhan.textContent = 'Không sao chép được';

            clearTimeout(hen);
            hen = setTimeout(function () { nhan.textContent = goc; }, 2000);
        });
    });
}());
