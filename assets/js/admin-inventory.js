/*
 * admin-inventory.js — bộ đếm − / + ở cột "Cập nhật" của trang Tồn kho.
 *
 * KHÔNG CÓ FILE NÀY THÌ CHUYỆN GÌ XẢY RA:
 * Hai nút − và + không bao giờ hiện ra (chúng ra đời với thuộc tính `hidden`,
 * xem app/views/admin/inventory/index.php), nên cột "Cập nhật" còn đúng một ô
 * số và nút Lưu. Vẫn gõ được số, vẫn lưu được — chỉ là phải gõ thay vì bấm.
 * Nút Lưu cũng không còn đổi sang dáng "mờ" khi chưa sửa gì; nó luôn bấm được,
 * và bấm khi không đổi gì thì lưu lại đúng con số cũ. Không hỏng gì cả.
 *
 * VÌ SAO HAI NÚT ẤY PHẢI DO JS SINH RA:
 * Chúng sửa giá trị ô nhập TẠI CHỖ, không gửi form. Làm bằng HTML thuần thì
 * chúng phải là nút submit, tức mỗi lần bấm là một lượt tải trang — nhập một
 * thùng 24 cái thành 24 lượt tải. Mà một cái nút bấm vào không làm gì thì tệ
 * hơn là không có nút, nên chúng nằm im cho tới khi file này chạy.
 */
(function () {
    'use strict';

    var forms = document.querySelectorAll('.ainv__form');

    if (forms.length === 0) {
        return;
    }

    Array.prototype.forEach.call(forms, function (form) {
        var input = form.querySelector('.aistep__input');
        var save  = form.querySelector('.aisave');
        var nuts  = form.querySelectorAll('.aistep__btn');

        if (input === null) {
            return;
        }

        // Con số lúc mở trang — mốc để biết dòng này đã bị sửa hay chưa.
        var banDau = input.value;

        function veLai() {
            if (save === null) {
                return;
            }

            /* Nút Lưu mờ đi khi chưa sửa gì. KHÔNG disable nó: một nút bị khoá
               không nhận được tiêu điểm bàn phím, nên người dùng bàn phím đi
               tới cột này là hụt một chặng — và bấm Lưu khi không đổi gì cũng
               chẳng hỏng chuyện gì, chỉ là ghi lại đúng con số cũ. */
            save.classList.toggle('is-clean', input.value === banDau);
        }

        Array.prototype.forEach.call(nuts, function (nut) {
            nut.hidden = false;

            nut.addEventListener('click', function () {
                var buoc = parseInt(nut.getAttribute('data-step'), 10) || 0;
                var moi  = (parseInt(input.value, 10) || 0) + buoc;

                // Không cho âm: kho không nợ được hàng, và ô nhập cũng khai
                // min="0" nên để lọt số âm là gửi lên một giá trị bị chặn ở
                // tầng dưới rồi báo lỗi — vòng vo hơn là chặn ngay ở đây.
                input.value = moi < 0 ? 0 : moi;
                veLai();
            });
        });

        input.addEventListener('input', veLai);
        veLai();
    });
}());
