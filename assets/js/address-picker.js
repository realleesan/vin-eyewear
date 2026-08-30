/**
 * address-picker.js — nâng hai ô "tỉnh/thành" và "phường/xã" thành danh sách chọn.
 *
 * Dùng chung cho HAI form, vì cả hai ghi cùng một thứ và khách không nên phải
 * gõ theo hai kiểu khác nhau:
 *   · sổ địa chỉ    /tai-khoan?muc=dia-chi   (app/views/auth/account/dia-chi.php)
 *   · thanh toán    /thanh-toan              (app/views/order/checkout.php)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HỢP ĐỒNG VỚI HTML — form nào có đủ những thứ này là chạy được, không cần
 * biết tên ô là gì:
 *
 *   [data-vnaddr]                   khối bọc ngoài, mỗi trang đúng một cái
 *   [data-vnaddr-field="province"]  ô chữ mang TÊN tỉnh/thành (có `name`, được gửi lên)
 *   [data-vnaddr-field="ward"]      ô chữ mang TÊN phường/xã
 *   [data-vnaddr-code="province"]   ô ẩn giữ MÃ — KHÔNG bắt buộc phải có
 *   [data-vnaddr-code="ward"]       ô ẩn giữ MÃ — KHÔNG bắt buộc phải có
 *
 * Trang thanh toán không gửi mã lên (đơn hàng chỉ lưu chữ) nên hai ô mã ở đó
 * không mang `name` — chúng chỉ để cụm này chọn lại đúng mục khi điền sẵn.
 * ─────────────────────────────────────────────────────────────────────────────
 * CHỈ LÀ TĂNG CƯỜNG
 *
 * Máy chủ in ra hai ô gõ tay và địa chỉ lưu được bằng chính chúng; file này chỉ
 * đổi chúng thành <select> đổ dữ liệu từ provinces.open-api.vn. API chết, mạng
 * hỏng hay JavaScript tắt thì khách gõ tay như trước — không có nhánh nào dẫn
 * tới một ô chọn rỗng không lưu nổi.
 *
 * Ô chữ KHÔNG bị xoá đi mà chỉ chuyển thành type="hidden": nó vẫn là ô mang
 * `name` được gửi lên, còn <select> chỉ là thứ để chọn rồi ghi giá trị vào nó.
 * Nhờ vậy phía máy chủ nhận đúng một hình dữ liệu dù có JavaScript hay không.
 *
 * HAI CẤP, KHÔNG PHẢI BA: từ 01/07/2025 Việt Nam bỏ cấp huyện. API v2 trả 34
 * tỉnh thành, và `p/<mã>?depth=2` cho thẳng danh sách phường/xã của tỉnh đó.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * GỌI LẠI ĐƯỢC, VÀ VÌ SAO
 *
 * Từ 2026-08-30, đổi mục ở /tai-khoan không tải lại trang nữa: account.js thay
 * ruột .acct-main bằng HTML mới. Cụm này gắn thẳng vào hai thẻ <select> nên
 * chúng chết theo phần tử cũ — vào mục "Sổ địa chỉ" bằng cột trái sẽ ra hai ô
 * gõ tay trơ, không có gợi ý nào, mà cũng không có lỗi nào để thấy.
 *
 * Nên thân hàm chạy lại được, và nghe sự kiện 'vin:acct-moi' account.js phát
 * ra sau mỗi lần thay. Trang /thanh-toan không phát sự kiện đó nên ở đấy hàm
 * chạy đúng một lần như trước — không đổi gì.
 *
 * CHỐT CHỐNG DỰNG HAI LẦN: thoát ngay nếu khối đã có <select>. Không có nó thì
 * một lần thay ruột mà .acct-main không đổi (bấm lại đúng mục đang xem) sẽ
 * chèn thêm một cặp ô chọn nữa lên trên cặp cũ.
 * ─────────────────────────────────────────────────────────────────────────────
 */
document.addEventListener('vin:acct-moi', khoiDongVnAddr);

khoiDongVnAddr();

function khoiDongVnAddr() {
    'use strict';

    var box = document.querySelector('[data-vnaddr]');
    if (!box || !window.fetch) return;

    /* Đã dựng rồi thì thôi — xem "CHỐT CHỐNG DỰNG HAI LẦN" ở trên. */
    if (box.querySelector('select[data-vnaddr-select]')) return;

    var API = 'https://provinces.open-api.vn/api/v2/';

    var field = {
        province: box.querySelector('[data-vnaddr-field="province"]'),
        ward:     box.querySelector('[data-vnaddr-field="ward"]')
    };
    var codeInput = {
        province: box.querySelector('[data-vnaddr-code="province"]'),
        ward:     box.querySelector('[data-vnaddr-code="ward"]')
    };

    if (!field.province || !field.ward) return;

    /* Nhớ trong phiên làm việc: danh sách tỉnh gần 4KB và danh sách phường của
       một tỉnh tới 14KB. Khách sửa vài địa chỉ liên tiếp thì không việc gì phải
       tải lại. try/catch vì sessionStorage ném lỗi khi trình duyệt chặn lưu
       trữ, và một cái kho tạm hỏng thì không đáng để cả khối này chết theo. */
    function cached(key, url) {
        var hit = null;

        try { hit = window.sessionStorage.getItem(key); } catch (e) { hit = null; }

        if (hit) {
            try { return Promise.resolve(JSON.parse(hit)); } catch (e) { /* hỏng thì tải lại */ }
        }

        return fetch(url).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        }).then(function (data) {
            try { window.sessionStorage.setItem(key, JSON.stringify(data)); } catch (e) { /* đầy kho, bỏ qua */ }
            return data;
        });
    }

    /* So tên đã lưu với tên trong danh mục.
       Địa chỉ nhập từ trước bản này là chữ khách tự gõ ("Hà Nội", "TP Hà Nội")
       nên không khớp từng ký tự với "Thành phố Hà Nội". Bỏ tiền tố đơn vị và
       dấu cách thừa là đủ để nhận ra phần lớn, khỏi bắt khách chọn lại. */
    function key(name) {
        return String(name || '')
            .toLowerCase()
            .replace(/^(thành phố|tỉnh|thị xã|quận|huyện|phường|xã|thị trấn|tp\.?|tt\.?)\s+/i, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    /* Đổi ô chữ thành ô ẩn và cắm một <select> vào ngay chỗ nó đứng.
       Chép class để dùng lại kiểu select.acct-field__input có sẵn trong
       account.css, và chuyển `required` sang <select> — ô ẩn thì trình duyệt
       bỏ qua khi kiểm tra form, nên để nguyên trên đó là mất luôn phần bắt buộc. */
    function upgrade(input, placeholder) {
        var select = document.createElement('select');

        select.className = input.className;
        select.required  = input.required;
        select.innerHTML = '<option value="">' + placeholder + '</option>';

        /* Dấu để lần khởi động sau biết khối này đã dựng rồi — xem "CHỐT CHỐNG
           DỰNG HAI LẦN" ở đầu file. Đặt bằng thuộc tính chứ không bằng class:
           class ở đây chép nguyên từ ô chữ và có thể trùng với thứ khác. */
        select.setAttribute('data-vnaddr-select', '');

        input.parentNode.insertBefore(select, input);
        input.type = 'hidden';
        input.required = false;

        return select;
    }

    function fill(select, items, savedName, savedCode) {
        var wanted = key(savedName);
        var picked = '';

        items.forEach(function (item) {
            var opt = document.createElement('option');

            opt.value = String(item.code);
            opt.textContent = item.name;
            opt.dataset.name = item.name;
            select.appendChild(opt);

            if (!picked && (String(item.code) === String(savedCode) || (wanted && key(item.name) === wanted))) {
                picked = String(item.code);
            }
        });

        select.value = picked;
        return picked;
    }

    var provinceSelect = upgrade(field.province, '— Chọn tỉnh / thành phố —');
    var wardSelect     = upgrade(field.ward, '— Chọn phường / xã —');

    wardSelect.disabled = true;

    /* Ghi lựa chọn ngược vào ô ẩn — đó mới là thứ được gửi lên máy chủ. */
    function sync(select, which) {
        var opt = select.selectedOptions[0];

        field[which].value = (opt && opt.value) ? opt.dataset.name : '';

        if (codeInput[which]) {
            codeInput[which].value = (opt && opt.value) ? opt.value : '';
        }
    }

    function loadWards(provinceCode, savedName, savedCode) {
        wardSelect.disabled = true;
        wardSelect.length = 1;
        wardSelect.options[0].textContent = 'Đang tải…';

        return cached('vnaddr:w:' + provinceCode, API + 'p/' + provinceCode + '?depth=2')
            .then(function (province) {
                wardSelect.options[0].textContent = '— Chọn phường / xã —';
                fill(wardSelect, province.wards || [], savedName, savedCode);
                wardSelect.disabled = false;
                sync(wardSelect, 'ward');
            })
            .catch(function () {
                /* Tỉnh tải được mà phường thì không: trả ô phường về gõ tay chứ
                   không để khách kẹt với một danh sách rỗng có dấu sao đỏ. */
                wardSelect.remove();
                field.ward.type = 'text';
                field.ward.required = true;
            });
    }

    provinceSelect.addEventListener('change', function () {
        sync(provinceSelect, 'province');

        // Đổi tỉnh thì phường cũ không còn nghĩa gì nữa.
        field.ward.value = '';
        if (codeInput.ward) codeInput.ward.value = '';

        if (!provinceSelect.value) {
            wardSelect.length = 1;
            wardSelect.disabled = true;
            return;
        }

        loadWards(provinceSelect.value, '', '');
    });

    wardSelect.addEventListener('change', function () {
        sync(wardSelect, 'ward');
    });

    cached('vnaddr:p', API + 'p/')
        .then(function (provinces) {
            var picked = fill(provinceSelect, provinces, field.province.value, codeInput.province && codeInput.province.value);

            if (!picked) return;

            /* Địa chỉ cũ khớp được theo tên nhưng chưa có mã -> ghi mã vào luôn,
               để lần sau không phải dò lại. */
            sync(provinceSelect, 'province');

            return loadWards(picked, field.ward.value, codeInput.ward && codeInput.ward.value);
        })
        .catch(function () {
            /* Không gọi được API: gỡ hai ô chọn, trả lại đúng hai ô gõ tay mà
               máy chủ đã in ra. Khách không biết có chuyện gì xảy ra, và đó là
               điều đúng — họ chỉ cần lưu được địa chỉ. */
            provinceSelect.remove();
            wardSelect.remove();
            field.province.type = 'text';
            field.ward.type = 'text';
            field.province.required = true;
            field.ward.required = true;
        });
}
