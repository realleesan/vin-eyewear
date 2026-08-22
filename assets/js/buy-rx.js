/**
 * buy-rx.js — bảng nhập số đo khúc xạ trong hộp thoại mua hàng.
 *
 * Markup: app/views/_layout/buy-modal.php, bước 'so-do'.
 *
 * Hai việc, cả hai đều CHỈ LÀ TĂNG CƯỜNG:
 *
 *   1. KHOÁ Ô TRỤC khi chưa chọn độ trụ. Trục loạn chỉ có nghĩa khi có độ
 *      loạn; "Trục 90°" cho một mắt không loạn là một con số vô nghĩa đi
 *      thẳng xuống phiếu mài.
 *   2. Ô TÓM TẮT đọc ngược con số vừa chọn ra thành chữ ("Cận 2.00 · Loạn
 *      0.75 · Trục 180°"). Đây là thứ bù lại cho việc dấu nằm lẫn trong một
 *      danh sách 97 dòng: "−2.00" đọc lướt có thể nhầm, "Cận 2.00" thì không.
 *
 * Không có file này thì ô trục mở sẵn (máy chủ vẫn kiểm dải như cũ) và ô tóm
 * tắt đứng yên ở "Chưa nhập" — bảng ngay trên nó đã hiện đủ thứ khách chọn.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NGHE TRÊN `document`, KHÔNG PHẢI TRÊN Ô CHỌN
 *
 * Hộp thoại được buy-flow.js thay ruột bằng fetch sau mỗi bước, nên phần tử
 * gắn sự kiện lúc tải trang sẽ biến mất ngay khi khách sang bước kế. Uỷ quyền
 * lên document thì sống qua mọi lần thay.
 * ─────────────────────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    /** "−2.00" -> "Cận 2.00" · "+1.50" -> "Viễn 1.50" · "0.00" -> "Không độ" */
    function sphText(raw) {
        var n = Number(raw);

        if (raw === '' || isNaN(n)) return '';
        if (n === 0) return 'Không độ';

        return (n < 0 ? 'Cận ' : 'Viễn ') + Math.abs(n).toFixed(2);
    }

    function summarise(form, eye) {
        var out = form.querySelector('[data-sum="' + eye + '"]');
        if (!out) return;

        var sph  = form.querySelector('#' + eye + '-sph');
        var cyl  = form.querySelector('#' + eye + '-cyl');
        var axis = form.querySelector('#' + eye + '-axis');

        var sphV  = sph  ? sph.value  : '';
        var cylV  = cyl  ? cyl.value  : '';
        var axisV = axis ? axis.value : '';

        /* Chưa chạm vào cả độ cầu lẫn độ trụ thì coi như chưa nhập. Trục một
           mình không đủ để nói gì — mà nó cũng đang bị khoá lúc đó. */
        if (sphV === '' && cylV === '') {
            out.textContent = 'Chưa nhập';
            return;
        }

        var parts = [];
        var s = sphText(sphV);

        if (s) parts.push(s);
        if (cylV !== '' && Number(cylV) !== 0) parts.push('Loạn ' + Math.abs(Number(cylV)).toFixed(2));
        if (axisV !== '') parts.push('Trục ' + axisV + '°');

        out.textContent = parts.length ? parts.join(' · ') : 'Chưa nhập';
    }

    /** Mở/khoá ô trục theo ô độ trụ của CÙNG một mắt. */
    function syncAxis(form, eye) {
        var cyl  = form.querySelector('[data-cyl="' + eye + '"]');
        var axis = form.querySelector('[data-axis="' + eye + '"]');

        if (!cyl || !axis) return;

        var locked = cyl.value === '' || Number(cyl.value) === 0;

        axis.disabled = locked;

        /* Khoá thì XOÁ luôn giá trị cũ. Khách chọn trục 90° rồi quay lại đổi
           độ trụ về "không loạn" — để nguyên 90° thì nó vẫn được gửi lên (ô
           disabled không gửi, nhưng người dùng có thể mở lại) và tệ hơn là ô
           tóm tắt vẫn đọc "Trục 90°" cho một mắt không loạn. */
        if (locked) axis.value = '';
    }

    /*
     * ĐỌC LẠI Ô TÓM TẮT NGAY KHI BẢNG XUẤT HIỆN.
     *
     * Bảng số đo nay được máy chủ điền sẵn khi khách bấm Lùi từ bước sau về
     * (xem $rxRaw trong _layout/buy-modal.php). Nếu chỉ tính tóm tắt lúc có
     * sự kiện `change` thì cái bảng đầy số ấy lại nằm dưới một ô tóm tắt ghi
     * "Chưa nhập" — trông như dữ liệu chưa được nhận.
     *
     * Bảng có thể tới theo HAI đường: nằm sẵn trong HTML lúc tải trang, hoặc
     * do buy-flow.js nạp về rồi thay vào .bmodal. MutationObserver bắt được cả
     * hai; cờ data-rx-doc để một bảng không bị quét hai lần.
     */
    function quet(root) {
        var forms = (root || document).querySelectorAll
            ? (root || document).querySelectorAll('.brx')
            : [];

        Array.prototype.forEach.call(forms, function (form) {
            if (form.getAttribute('data-rx-doc')) return;

            form.setAttribute('data-rx-doc', '1');

            ['od', 'os'].forEach(function (eye) { summarise(form, eye); });
        });
    }

    quet(document);

    if (window.MutationObserver) {
        new MutationObserver(function () { quet(document); })
            .observe(document.body, { childList: true, subtree: true });
    }

    document.addEventListener('change', function (ev) {
        var sel = ev.target.closest ? ev.target.closest('.brxsel') : null;
        if (!sel) return;

        var form = sel.closest('.brx');
        if (!form) return;

        ['od', 'os'].forEach(function (eye) {
            syncAxis(form, eye);
            summarise(form, eye);
        });
    });
}());
