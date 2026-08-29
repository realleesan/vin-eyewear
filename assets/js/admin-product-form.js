/*
 * admin-product-form.js — bốn tiện ích cho hộp thoại thêm/sửa sản phẩm.
 *
 * KHÔNG CÓ FILE NÀY THÌ CHUYỆN GÌ XẢY RA:
 * Form vẫn nhập và lưu được trọn vẹn. Sáu tab chạy bằng CSS thuần (sáu ô radio
 * ẩn, xem .apf__radio trong admin-products.css) nên không tab nào biến mất.
 * Cái mất là bốn thứ đỡ tay:
 *
 *   1. nút "Tự sinh" bên ô SKU      → gõ mã theo quy tắc in ngay dưới ô
 *   2. slug tự chạy theo tên        → gõ tay, hoặc để trống cho máy chủ tự sinh
 *   3. dòng xem trước "52□18-140"   → ba ô số vẫn nhập bình thường
 *   4. nút ✕ xoá dòng biến thể      → còn ô tick "Xoá", gửi lên rồi save() xoá
 *   5. KÉO THẢ ảnh vào tab Hình ảnh → bấm nút "Chọn ảnh từ máy" ngay trong
 *                                     chính cái khung ấy, vẫn tải lên đủ
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO GẮN THEO SỰ KIỆN NỔI LÊN document
 *
 * Hộp thoại này KHÔNG có sẵn trong trang: admin-modal.js fetch địa chỉ
 * ?them=1 / ?sua=<id> rồi gắn ruột hộp vào lúc người dùng bấm. Một vòng
 * querySelectorAll chạy lúc tải trang sẽ không thấy ô nào — và cũng không thấy
 * lần thứ hai, thứ ba khi người dùng mở hộp cho mặt hàng khác.
 *
 * Nghe trên document thì mọi lần mở hộp đều chạy đúng, không cần biết hộp tới
 * lúc nào. Riêng nút "Tự sinh" và dòng xem trước là hai thứ phải CHÈN THÊM vào
 * DOM chứ không chỉ nghe — chúng dựng lười, ngay lần đầu chạm tới cái hộp đang
 * mở.
 */
(function () {
    'use strict';

    /* Bỏ dấu tiếng Việt rồi rút gọn — dùng cho cả SKU lẫn slug.

       Bảng thay thế viết tay chứ không dùng normalize('NFD'): trình duyệt cũ
       trên máy cửa hàng không có nó, và một hàm trả về chuỗi rỗng thì sinh ra
       SKU rỗng chứ không báo lỗi gì. */
    var CO_DAU   = 'àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ';
    var KHONG    = 'aaaaaaaaaaaaaaaaaeeeeeeeeeeeiiiiiooooooooooooooooouuuuuuuuuuuyyyyyd';

    function boDau(chuoi) {
        var ra = '';
        var thuong = String(chuoi).toLowerCase();

        for (var i = 0; i < thuong.length; i++) {
            var vt = CO_DAU.indexOf(thuong[i]);
            ra += vt >= 0 ? KHONG[vt] : thuong[i];
        }

        return ra;
    }

    function slugHoa(chuoi) {
        return boDau(chuoi)
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function hop() {
        return document.querySelector('.apf');
    }

    // ── 1 · NÚT "TỰ SINH" BÊN Ô SKU ─────────────────────────────────────────
    //
    // Quy tắc in ngay dưới ô: THƯƠNG HIỆU-MẪU-MÀU-SIZE. Ghép từ bốn ô đang có
    // trên form; ô nào trống thì bỏ hẳn đoạn đó thay vì để lại gạch nối đôi.
    function sinhSku(form) {
        var lay = function (ten) {
            var el = form.querySelector('[name="' + ten + '"]');

            return el ? String(el.value || '').trim() : '';
        };

        var phan = [
            boDau(lay('brand')).replace(/[^a-z0-9]/g, '').slice(0, 4),
            boDau(lay('name')).replace(/[^a-z0-9]/g, '').slice(0, 4),
            boDau(lay('color')).replace(/[^a-z0-9]/g, '').slice(0, 3),
            lay('lens_width_mm')
        ];

        return phan.filter(function (p) { return p !== ''; })
                   .join('-')
                   .toUpperCase();
    }

    function dungNutSku(form) {
        var o = form.querySelector('[data-sku-pair]');

        if (o === null || o.querySelector('[data-sku-gen]') !== null) {
            return;
        }

        var nut = document.createElement('button');
        // type="button" BẮT BUỘC: mặc định của <button> trong form là submit,
        // nên thiếu nó thì bấm "Tự sinh" là gửi luôn cả form.
        nut.type = 'button';
        nut.className = 'astatus__save astatus__save--ghost apf__gen';
        nut.textContent = 'Tự sinh';
        nut.setAttribute('data-sku-gen', '');

        nut.addEventListener('click', function () {
            var ma = sinhSku(form);
            var o  = form.querySelector('[name="sku"]');

            if (ma !== '' && o !== null) {
                o.value = ma;
                o.focus();
            }
        });

        o.appendChild(nut);
    }

    // ── 2 · SLUG CHẠY THEO TÊN ──────────────────────────────────────────────
    //
    // CHỈ khi ô slug còn trống hoặc chưa bị sửa tay. Ghi đè một slug người ta
    // vừa gõ là chuyện tệ hơn hẳn việc không tự sinh: slug đã phát ra ngoài thì
    // đổi là làm chết mọi đường dẫn cũ.
    function noiSlug(form) {
        var oTen  = form.querySelector('[name="name"]');
        var oSlug = form.querySelector('[name="slug"]');

        if (oTen === null || oSlug === null || oSlug.hasAttribute('data-noi')) {
            return;
        }

        oSlug.setAttribute('data-noi', '');

        // Đã có slug từ trước (đang sửa một mặt hàng) thì không đụng tới.
        var tuDo = String(oSlug.value || '').trim() === '';

        oSlug.addEventListener('input', function () { tuDo = false; });

        oTen.addEventListener('input', function () {
            if (tuDo) {
                oSlug.value = slugHoa(oTen.value);
            }
        });
    }

    // ── 3 · DÒNG XEM TRƯỚC CỠ GỌNG ──────────────────────────────────────────
    //
    // "52□18-140" là cách con số được in trên càng kính, tức là cách người
    // nhập đang đọc nó từ vật thật trên tay. Ba ô số tách rời thì lọc được
    // bằng SQL nhưng mất cách đọc ấy — dòng này trả nó lại.
    function dungXemTruoc(form) {
        var dai = form.querySelector('[data-size-preview]');

        if (dai === null || dai.querySelector('.apf__size-out') !== null) {
            return;
        }

        var ra = document.createElement('span');
        ra.className = 'apf__size-out';
        dai.appendChild(ra);

        var ve = function () {
            var so = [].map.call(
                dai.querySelectorAll('input[type="number"]'),
                function (o) { return String(o.value || '').trim(); }
            );

            // Thiếu một trong ba thì KHÔNG vẽ nửa vời: "52□–" đọc ra như một
            // phép đo sai chứ không đọc ra là chưa nhập xong.
            ra.textContent = so[0] && so[1] && so[2]
                ? so[0] + '□' + so[1] + '-' + so[2]
                : '';
        };

        dai.addEventListener('input', ve);
        ve();
    }

    // ── 4 · NÚT ✕ THAY Ô TÍCH XOÁ ───────────────────────────────────────────
    //
    // Ô tick "Xoá" là bản chạy-được-không-JS: nó gửi lên và save() xoá dòng.
    // Có JS thì đổi thành nút ✕ đúng bản vẽ — nút vẫn LẬT chính ô tick đó chứ
    // không gỡ dòng khỏi DOM, nên một cú bấm nhầm bấm lại là xong, và dòng vẫn
    // gửi lên bình thường.
    function dungNutXoa(form) {
        [].forEach.call(form.querySelectorAll('.apf__vdel'), function (nhan) {
            if (nhan.hasAttribute('data-x')) {
                return;
            }

            nhan.setAttribute('data-x', '');

            var chu = nhan.querySelector('span');

            if (chu !== null) {
                chu.textContent = '×';
                chu.setAttribute('aria-hidden', 'true');
            }

            nhan.classList.add('apf__vdel--x');
        });
    }

    /* Dựng lười: mỗi lần chuột chạm hoặc bàn phím đi vào hộp thì kiểm lại. Rẻ
       vì cả bốn hàm đều tự thoát ngay khi thấy dấu đã dựng. */
    function dung() {
        var form = hop();

        if (form === null) {
            return;
        }

        dungNutSku(form);
        noiSlug(form);
        dungXemTruoc(form);
        dungNutXoa(form);
    }

    document.addEventListener('pointerover', dung);
    document.addEventListener('focusin', dung);
    document.addEventListener('DOMContentLoaded', dung);

    // Hộp mở bằng ?sua=<id> trên địa chỉ thì nó đã có sẵn lúc script chạy.
    if (document.readyState !== 'loading') {
        dung();
    }

    /*
     * ════════════════════════════════════════════════════════════════════
     * 5 · KÉO THẢ ẢNH VÀO HAI KHUNG CỦA TAB HÌNH ẢNH
     *
     * Bản vẽ "Quản lý sản phẩm.dc.html" vẽ cả hai khung là vùng thả được.
     * Đây là TĂNG CƯỜNG thuần: mỗi khung đã có sẵn một <input type="file">
     * bên trong, nên tắt JS thì vẫn chọn được ảnh bằng nút, chỉ là phải qua
     * hộp chọn file của hệ điều hành.
     *
     * KHÔNG tự gửi form sau khi thả. Ảnh chỉ thật sự lên máy chủ khi bấm
     * "Lưu" — giống hệt mọi ô khác trong form này. Thả xong mà form tự gửi
     * thì người dùng mất những gì đang gõ dở ở năm tab kia.
     *
     * DataTransfer để gán file vào input: đây là cách duy nhất đặt được
     * `input.files` bằng script. Trình duyệt nào không có nó thì khối này im
     * lặng bỏ qua và nút chọn file vẫn nguyên đó.
     * ════════════════════════════════════════════════════════════════════
     */
    function oFile(khung) {
        return khung.querySelector('input[type="file"]');
    }

    function chanMacDinh(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    document.addEventListener('dragover', function (e) {
        var khung = e.target.closest && e.target.closest('[data-apf-drop]');

        if (khung === null || oFile(khung) === null) {
            return;
        }

        /* preventDefault ở dragover mới là thứ cho phép thả — thiếu nó thì
           trình duyệt giữ hành vi mặc định: mở luôn tấm ảnh trong tab hiện
           tại, và người dùng mất cả cái form đang điền dở. */
        chanMacDinh(e);
        khung.classList.add('is-over');
    });

    document.addEventListener('dragleave', function (e) {
        var khung = e.target.closest && e.target.closest('[data-apf-drop]');

        if (khung !== null) {
            khung.classList.remove('is-over');
        }
    });

    document.addEventListener('drop', function (e) {
        var khung = e.target.closest && e.target.closest('[data-apf-drop]');

        if (khung === null) {
            return;
        }

        var o = oFile(khung);

        if (o === null || typeof DataTransfer === 'undefined') {
            return;
        }

        chanMacDinh(e);
        khung.classList.remove('is-over');

        var tep = (e.dataTransfer && e.dataTransfer.files) || [];
        var kho = new DataTransfer();
        /* Ô ảnh chính KHÔNG có `multiple`: thả năm tấm vào đó thì lấy tấm đầu
           chứ không phải im lặng bỏ hết. */
        var toiDa = o.multiple ? tep.length : Math.min(1, tep.length);

        for (var i = 0; i < toiDa; i++) {
            if (tep[i].type.indexOf('image/') === 0) {
                kho.items.add(tep[i]);
            }
        }

        if (kho.files.length === 0) {
            return;
        }

        o.files = kho.files;

        /* Báo cho phần còn lại của trang biết ô vừa đổi. Không có dòng này thì
           gán `files` bằng script là một thay đổi câm — không sự kiện nào nổi
           lên, và bất cứ thứ gì nghe `change` trên form đều bỏ lỡ. */
        o.dispatchEvent(new Event('change', { bubbles: true }));
    });

}());
