/**
 * admin-dashboard.js — thanh lọc kỳ và cơ sở ở trang Tổng quan.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * KHÔNG CÓ FILE NÀY THÌ CHUYỆN GÌ XẢY RA
 *
 * Thanh lọc vẫn chạy đủ. Nó là một <form method="get"> thật với nút "Áp dụng"
 * thật: chọn kỳ, chọn cơ sở, gõ hai ô ngày rồi bấm — trình duyệt gửi form,
 * máy chủ đọc $_GET, trang dựng lại với đúng bộ lọc ấy trên thanh địa chỉ.
 *
 * File này bỏ đúng hai chỗ vướng:
 *
 *   1. Đổi ô chọn là đi luôn, không phải với tay sang nút Áp dụng.
 *   2. Hai ô ngày thu lại khi kỳ không phải "Tuỳ chọn" — chúng chỉ có tác dụng
 *      với kỳ ấy, để bày ra thì người dùng gõ vào rồi không hiểu vì sao không
 *      ăn.
 *
 * Vì lẽ đó hai ô ngày KHÔNG bị ẩn sẵn bằng CSS. Ẩn sẵn thì người tắt JS mất
 * hẳn khả năng chọn khoảng ngày riêng — đổi một tiện nghi lấy một tính năng.
 * ────────────────────────────────────────────────────────────────────────────
 */

(function () {
    'use strict';

    var form = document.getElementById('adash-loc');

    if (!form) return;

    var oKy   = form.querySelector('[data-adash-ky]');
    var oNgay = form.querySelectorAll('[data-adash-ngay]');
    var oNhac = form.querySelector('[data-adash-nhac]');
    var oCoSo = form.querySelector('#adash-coso');

    /* Thu hoặc bày hai ô ngày theo kỳ đang chọn.

       Dùng thuộc tính `hidden` chứ không style.display: nó là cách chuẩn để
       nói "phần tử này không áp dụng lúc này", và trình đọc màn hình bỏ qua nó
       đúng như mắt. */
    function capNhatONgay() {
        var tuyChon = oKy && oKy.value === 'tuy-chon';

        Array.prototype.forEach.call(oNgay, function (o) {
            o.hidden = !tuyChon;
        });

        if (oNhac) oNhac.hidden = !tuyChon;
    }

    if (oKy) {
        oKy.addEventListener('change', function () {
            capNhatONgay();

            /* CHỌN "TUỲ CHỌN" THÌ KHÔNG GỬI NGAY — đây là cả điểm của nhánh này.
               Gửi luôn nghĩa là nhảy sang một kỳ tuỳ chọn dựng từ hai ô ngày cũ
               mà người dùng chưa kịp nhìn, rồi họ phải sửa ngày và gửi lần nữa.
               Bốn kỳ dựng sẵn thì ngược lại: chọn xong là đã đủ ý, chờ thêm một
               cú bấm nữa chỉ là bắt làm hai lần một việc. */
            if (oKy.value === 'tuy-chon') {
                var oTu = form.querySelector('#adash-tu');

                if (oTu) oTu.focus();

                return;
            }

            form.submit();
        });
    }

    if (oCoSo) {
        oCoSo.addEventListener('change', function () { form.submit(); });
    }

    /* NÚT "ÁP DỤNG" VẪN Ở NGUYÊN, KHÔNG ẨN ĐI.

       Đã cân nhắc ẩn nó khi có JS, như vài thanh lọc khác vẫn làm. Bỏ vì kỳ
       tuỳ chọn CẦN nó: hai ô ngày không có "lúc chọn xong" rõ ràng để tự gửi —
       gõ dở ngày bắt đầu mà form đã đi thì trang tải lại giữa chừng. Ẩn nút đi
       thì đúng cái nhánh phức tạp nhất của thanh lọc lại không còn đường gửi. */

    capNhatONgay();
})();
