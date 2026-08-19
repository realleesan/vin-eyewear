/**
 * account.js — trang tài khoản (/tai-khoan)
 *
 * CHỈ LÀ TĂNG CƯỜNG. Không có file này trang vẫn chạy đủ — mỗi khối bên dưới
 * nói rõ nó bỏ được bước nào.
 *
 * Nút "Tải ảnh lên" bị CSS ẩn khi có JS (.js .acct-nav__send), và class .js do
 * <script> đầu master.php đặt — nên không có kịch bản nào mà cả nút lẫn chức
 * năng tự gửi đều vắng mặt.
 */

/* ── Đổi ảnh đại diện: chọn ảnh xong gửi luôn, khỏi bấm nút thứ hai ───────── */
(function () {
    'use strict';

    var input = document.querySelector('.acct-nav__face input[type="file"]');
    if (!input) return;

    var form = input.form;
    if (!form) return;

    input.addEventListener('change', function () {
        if (!input.files || !input.files.length) return;

        // Chặn tại chỗ để khách biết ngay, khỏi chờ một vòng lên máy chủ.
        // Máy chủ VẪN kiểm lại — xem core/AvatarStorage.php; giới hạn ở đây
        // sửa được bằng công cụ nhà phát triển nên nó không phải hàng rào.
        var limit = form.querySelector('input[name="MAX_FILE_SIZE"]');

        if (limit && input.files[0].size > Number(limit.value)) {
            window.alert('Ảnh vượt quá dung lượng cho phép (tối đa 1 MB).');
            input.value = '';
            return;
        }

        form.submit();
    });
}());

/* ── Đổi ngày trong form đổi giờ hẹn: gửi form luôn ───────────────────────────
 *
 * Danh sách giờ trống do máy chủ dựng, nên đổi ngày là phải tải lại. File này bỏ
 * đi cái bấm thứ hai: chọn ngày xong là đi luôn, khỏi bấm "Xem giờ trống".
 *
 * Nút đó bị CSS ẩn khi có JS (.js .acct-resched__go) — nên không có kịch bản nào
 * mà cả nút lẫn chức năng tự gửi đều vắng mặt.
 */
(function () {
    'use strict';

    var day = document.querySelector('.acct-resched__date[data-autosubmit]');
    if (!day || !day.form) return;

    day.addEventListener('change', function () {
        // Ngày rỗng (khách xoá ô) thì đừng gửi: máy chủ sẽ về ngày mặc định và
        // trông như cú bấm bị bỏ qua.
        if (day.value) day.form.submit();
    });
}());

/* ── "Xem chi tiết" / "Thu gọn" đơn hàng: bật tắt tại chỗ ─────────────────────
 *
 * Không có JS thì hai nút này là link thường (?don=<mã>) và trang tải lại —
 * vẫn đúng, chỉ chậm hơn. Ở đây chặn cái tải lại đó: khối chi tiết đã nằm sẵn
 * trong trang (xem chú thích ở app/views/auth/account/don-hang.php), nên chỉ
 * cần gỡ/đặt lại thuộc tính hidden.
 *
 * URL vẫn được cập nhật bằng replaceState để F5 hay chia sẻ link ra đúng cái
 * đang thấy — replaceState chứ không pushState: mở rồi thu gọn cùng một đơn là
 * quay về đúng chỗ ban đầu, ghi lại thì nút "lùi" của trình duyệt phải bấm mấy
 * lần mới ra khỏi trang tài khoản.
 */
(function () {
    'use strict';

    var list = document.querySelector('.acct-list');
    if (!list) return;

    list.addEventListener('click', function (ev) {
        // Bấm giữ Ctrl/Shift/giữa chuột là ý muốn mở tab khác — để nguyên.
        if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || ev.button !== 0) return;

        /* Nút "Xem thông tin chuyển khoản" — CHỈ MỞ, không bao giờ đóng, và
           không đổi chữ trên nút. Khối chuyển khoản nằm trong phần chi tiết của
           thẻ, nên việc của nút là bỏ `hidden` rồi cuộn tới đúng khối đó. */
        var reveal = ev.target.closest('[data-reveal]');

        if (reveal) {
            var target = document.getElementById(reveal.getAttribute('data-reveal'));
            var block  = document.getElementById(reveal.getAttribute('data-reveal-to'));

            if (!target || !block) return;   // thiếu một trong hai thì để link chạy

            ev.preventDefault();
            target.hidden = false;

            // Nút "Xem chi tiết" của cùng thẻ giờ đang nói sai — phần chi tiết đã
            // mở mà nút vẫn mời mở. Đồng bộ lại nhãn và href của nó.
            var more = reveal.closest('.acct-order__acts').querySelector('.acct-order__more');

            if (more) {
                more.setAttribute('aria-expanded', 'true');
                more.textContent = 'Thu gọn';
                more.setAttribute('href', more.getAttribute('data-close-href'));
            }

            block.scrollIntoView({ block: 'center', behavior: 'smooth' });

            return;
        }

        var btn = ev.target.closest('.acct-order__more');
        if (!btn) return;

        var panel = document.getElementById(btn.getAttribute('aria-controls'));
        if (!panel) return;   // không tìm thấy khối chi tiết thì cứ để link chạy

        ev.preventDefault();

        var open = panel.hidden;   // đang ẩn ⇒ lần bấm này là để mở

        panel.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.textContent = open ? 'Thu gọn' : 'Xem chi tiết';

        var here = btn.getAttribute(open ? 'data-open-href' : 'data-close-href');

        if (here && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', here);
        }

        // href giờ phải là hành động NGƯỢC lại, để mở tab mới từ nút này (hoặc
        // một lần bấm nữa khi JS lỗi giữa đường) ra đúng thứ chữ trên nút hứa.
        btn.setAttribute('href', btn.getAttribute(open ? 'data-close-href' : 'data-open-href'));

        // Cả hai href đều trỏ tới #<mã đơn>. Khi mở thì bỏ qua: thẻ đang nằm
        // trong tầm mắt rồi, cuộn thêm chỉ làm giật. Khi thu gọn mới cuộn —
        // đầu thẻ có thể đã bị đẩy lên trên mép màn hình.
        if (!open) {
            var card = btn.closest('.acct-order');
            var top  = card ? card.getBoundingClientRect().top : 0;

            if (card && top < 0) {
                card.scrollIntoView({ block: 'start', behavior: 'smooth' });
            }
        }
    });
}());

/* ── Sổ địa chỉ: nâng hai ô chữ thành danh sách hành chính ────────────────
 *
 * CHỈ LÀ TĂNG CƯỜNG, như mọi khối trong file này. Máy chủ in ra hai ô gõ tay
 * và địa chỉ lưu được bằng chính chúng; khối này chỉ đổi chúng thành <select>
 * đổ dữ liệu từ provinces.open-api.vn. API chết, mạng hỏng hay JavaScript tắt
 * thì khách gõ tay như trước — không có nhánh nào dẫn tới một ô chọn rỗng
 * không lưu nổi.
 *
 * HAI CẤP, KHÔNG PHẢI BA: từ 01/07/2025 Việt Nam bỏ cấp huyện. API v2 trả 34
 * tỉnh thành, và `p/<mã>?depth=2` cho thẳng danh sách phường/xã của tỉnh đó.
 *
 * Ô chữ KHÔNG bị xoá đi mà chỉ chuyển thành type="hidden": nó vẫn là ô mang
 * `name` được gửi lên, còn <select> chỉ là thứ để chọn rồi ghi giá trị vào nó.
 * Nhờ vậy phía máy chủ nhận đúng một hình dữ liệu dù có JavaScript hay không.
 */
(function () {
    'use strict';

    var box = document.querySelector('[data-vnaddr]');
    if (!box || !window.fetch) return;

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
}());
