/**
 * search-suggest.js — phần động của LỚP PHỦ TÌM KIẾM ở đầu trang.
 *
 * Căn cứ: SRS v1.3.1, quyết định X29 / Q10 (chốt 04/09/2026) — "tìm kiếm gần
 * đúng" của giai đoạn 1 gồm bỏ dấu, không phân biệt hoa thường, CỘNG THÊM gợi
 * ý từ khoá khi đang gõ. Không bao gồm dung sai lỗi chính tả.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐỢT 09/09/2026: TỪ "GỢI Ý TỪ KHOÁ" THÀNH "KẾT QUẢ TẠI CHỖ"
 *
 * Bản trước chỉ đổ <option> vào <datalist>; gõ xong bấm Tìm là chuyển sang
 * /tim-kiem. Nay file này làm BỐN việc, và việc 1 giữ nguyên:
 *
 *   1. gợi ý từ khoá vào <datalist>                     (/tim-kiem/goi-y)
 *   2. KẾT QUẢ HIỆN NGAY TRONG LỚP PHỦ khi gõ ≥ 2 ký tự hoặc bấm Tìm — nạp
 *      ngầm /tim-kiem?q= với header X-Search, lấy khối .srch ra chèn vào
 *      [data-search-results]. Không rời trang. (master.php có nhánh X-Search.)
 *   3. "đã xem gần đây" — đọc localStorage, vẽ vào [data-recent-list]
 *   4. ghi nhớ mẫu đang xem: trang chi tiết mang [data-recent] (JSON), file
 *      này đẩy nó vào đầu danh sách. Cùng file vì nó nạp ở MỌI trang khung đầy
 *      đủ — không cần thêm một <script> nữa cho trang chi tiết.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TĂNG CƯỜNG, KHÔNG PHẢI PHỤ THUỘC
 *
 * Ô tìm kiếm là một <form> GET bình thường: JS lỗi giữa đường thì bấm Tìm vẫn
 * sang /tim-kiem đủ kết quả. Nạp ngầm hỏng (mạng, máy chủ) thì cũng RƠI VỀ
 * đúng đường đó — window.location — chứ không hiện một dải lỗi cho một việc
 * có sẵn đường lui.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO CÓ HOÃN VÀ CÓ HUỶ LƯỢT CŨ
 *
 * Gõ "kính mát" là tám lần sự kiện input. Không hoãn thì đó là tám lượt gọi
 * máy chủ cho một lần tìm. Gợi ý hoãn 200ms (nhẹ, JSON); kết quả hoãn 350ms
 * (nặng hơn — cả trang view) — người gõ không cảm thấy chậm vì 350ms vẫn ngắn
 * hơn khoảng nghỉ giữa hai từ. AbortController huỷ lượt đang bay khi có lượt
 * mới: hai lượt về không đúng thứ tự là chuyện thường trên mạng chậm.
 */

(function () {
    'use strict';

    var HOAN_GOI_Y_MS   = 200;
    var HOAN_KET_QUA_MS = 350;
    var TOI_THIEU       = 2;   // dưới hai ký tự thì không hỏi — xem SearchController
    var KHOA_DA_XEM     = 'vin.recent';
    var TOI_DA_DA_XEM   = 6;

    /* ================================================================
       4. GHI NHỚ MẪU ĐANG XEM — chạy ở mọi trang, sớm nhất có thể
       ================================================================ */

    function docDaXem() {
        try {
            var raw = window.localStorage.getItem(KHOA_DA_XEM);
            var ds  = raw ? JSON.parse(raw) : [];
            return Array.isArray(ds) ? ds : [];
        } catch (e) {
            return [];   // localStorage bị chặn (riêng tư, hết chỗ) -> coi như rỗng
        }
    }

    function ghiDaXem(ds) {
        try { window.localStorage.setItem(KHOA_DA_XEM, JSON.stringify(ds)); } catch (e) {}
    }

    (function nhoMauDangXem() {
        var the = document.querySelector('[data-recent]');
        if (!the) return;

        var mau;
        try { mau = JSON.parse(the.getAttribute('data-recent')); } catch (e) { return; }
        if (!mau || !mau.slug || !mau.name) return;

        var ds = docDaXem().filter(function (m) { return m && m.slug !== mau.slug; });
        ds.unshift({ slug: mau.slug, name: mau.name, image: mau.image || '', price: mau.price || '' });
        ghiDaXem(ds.slice(0, TOI_DA_DA_XEM));
    })();

    /* ================================================================
       Phần còn lại cần ô tìm kiếm ở đầu trang
       ================================================================ */

    var o = document.getElementById('headerSearch');
    if (!o) return;

    var ds       = document.getElementById(o.getAttribute('list'));
    var duongGoiY = o.getAttribute('data-suggest');
    var duongTim  = o.getAttribute('data-search-url') || '/tim-kiem';

    var form     = o.closest('[data-search-form]') || o.form;
    var khungKQ  = document.querySelector('[data-search-results]');
    var khungMD  = document.querySelector('[data-search-default]');
    var nhomDX   = document.querySelector('[data-recent-viewed]');
    var listDX   = document.querySelector('[data-recent-list]');
    var xoaDX    = document.querySelector('[data-recent-clear]');

    /* ================================================================
       3. ĐÃ XEM GẦN ĐÂY
       ================================================================ */

    function veDaXem() {
        if (!nhomDX || !listDX) return;

        var ds = docDaXem();
        listDX.textContent = '';

        if (ds.length === 0) {
            nhomDX.hidden = true;
            return;
        }

        ds.forEach(function (m) {
            var li = document.createElement('li');
            li.className = 'srchmini__item';

            var a = document.createElement('a');
            a.className = 'srchmini__link';
            a.href = '/san-pham/' + encodeURIComponent(m.slug);

            var thumb = document.createElement('span');
            thumb.className = 'srchmini__thumb';
            if (m.image) {
                var img = document.createElement('img');
                img.src = m.image;
                img.alt = '';
                img.width = 200;
                img.height = 200;
                img.loading = 'lazy';
                thumb.appendChild(img);
            }

            var name = document.createElement('span');
            name.className = 'srchmini__name';
            name.setAttribute('lang', 'vi');
            name.textContent = m.name;

            a.appendChild(thumb);
            a.appendChild(name);

            if (m.price) {
                var price = document.createElement('span');
                price.className = 'srchmini__price';
                price.textContent = m.price;
                a.appendChild(price);
            }

            li.appendChild(a);
            listDX.appendChild(li);
        });

        nhomDX.hidden = false;
    }

    veDaXem();

    if (xoaDX) {
        xoaDX.addEventListener('click', function () {
            ghiDaXem([]);
            veDaXem();
        });
    }

    /* ================================================================
       1. GỢI Ý TỪ KHOÁ — nguyên bản
       ================================================================ */

    var henGoiY   = null;
    var bayGoiY   = null;
    var demGoiY   = Object.create(null);

    function veGoiY(danhSach) {
        if (!ds) return;
        ds.textContent = '';
        for (var i = 0; i < danhSach.length; i++) {
            var op = document.createElement('option');
            op.value = danhSach[i];
            ds.appendChild(op);
        }
    }

    function hoiGoiY(q) {
        if (!duongGoiY || !ds) return;

        if (demGoiY[q]) { veGoiY(demGoiY[q]); return; }

        if (bayGoiY) bayGoiY.abort();
        bayGoiY = typeof AbortController === 'function' ? new AbortController() : null;

        fetch(duongGoiY + '?q=' + encodeURIComponent(q), {
            signal: bayGoiY ? bayGoiY.signal : undefined,
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !Array.isArray(data.goi_y)) return;
                demGoiY[q] = data.goi_y;
                if (o.value.trim() === q) veGoiY(data.goi_y);
            })
            .catch(function () { /* cố ý im lặng — xem đầu file */ });
    }

    /* ================================================================
       2. KẾT QUẢ TẠI CHỖ
       ================================================================ */

    var henKQ  = null;
    var bayKQ  = null;
    var demKQ  = Object.create(null);   // q -> HTML của .srch, sống trong một lần tải trang

    function hienMacDinh() {
        if (khungKQ) { khungKQ.hidden = true; khungKQ.textContent = ''; }
        if (khungMD) khungMD.hidden = false;
    }

    function hienKetQua(html, q) {
        if (!khungKQ) return;

        khungKQ.innerHTML = html;

        /* Lối ra trang đầy đủ — người muốn lọc, phân trang, hay chỉ muốn một
           địa chỉ để gửi đi. Máy chủ không in dòng này vì trang /tim-kiem đã
           LÀ trang đầy đủ. */
        var all = document.createElement('a');
        all.className = 'srchov__all';
        all.href = duongTim + '?q=' + encodeURIComponent(q);
        all.textContent = khungKQ.getAttribute('data-all-label') || 'Xem tất cả kết quả';
        khungKQ.appendChild(all);

        khungKQ.hidden = false;
        if (khungMD) khungMD.hidden = true;
    }

    function timTaiCho(q) {
        if (!khungKQ || !window.fetch || !window.DOMParser) {
            window.location.href = duongTim + '?q=' + encodeURIComponent(q);
            return;
        }

        if (demKQ[q]) { hienKetQua(demKQ[q], q); return; }

        if (bayKQ) bayKQ.abort();
        bayKQ = typeof AbortController === 'function' ? new AbortController() : null;

        // Báo đang tìm — không phải để đẹp, mà để ô không "chết" 300ms trên mạng chậm.
        if (khungKQ.hidden || khungKQ.textContent === '') {
            khungKQ.innerHTML = '<p class="srchov__busy">'
                + (khungKQ.getAttribute('data-busy-label') || 'Đang tìm…') + '</p>';
            khungKQ.hidden = false;
            if (khungMD) khungMD.hidden = true;
        }

        fetch(duongTim + '?q=' + encodeURIComponent(q), {
            signal: bayKQ ? bayKQ.signal : undefined,
            headers: { 'X-Search': '1', 'Accept': 'text/html' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
            .then(function (text) {
                var doc = new DOMParser().parseFromString(text, 'text/html');
                var srch = doc.querySelector('.srch');
                if (!srch) return Promise.reject('no .srch');

                demKQ[q] = srch.outerHTML;

                // Ô có thể đã đổi nội dung trong lúc chờ — không vẽ câu trả lời cũ.
                if (o.value.trim() === q) hienKetQua(srch.outerHTML, q);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                /* Đường lui: trang tìm kiếm thật. Chỉ khi người ta ĐÃ BẤM Tìm —
                   gõ dở mà bị bốc sang trang khác thì tệ hơn là không thấy gì. */
                if (form && form.dataset.submitted === '1') {
                    window.location.href = duongTim + '?q=' + encodeURIComponent(q);
                }
            });
    }

    o.addEventListener('input', function () {
        var q = o.value.trim();

        if (henGoiY) clearTimeout(henGoiY);
        if (henKQ)   clearTimeout(henKQ);

        if (q.length < TOI_THIEU) {
            veGoiY([]);
            if (bayKQ) bayKQ.abort();
            hienMacDinh();
            return;
        }

        henGoiY = setTimeout(function () { hoiGoiY(q); }, HOAN_GOI_Y_MS);
        henKQ   = setTimeout(function () { timTaiCho(q); }, HOAN_KET_QUA_MS);
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            var q = o.value.trim();
            if (q.length < TOI_THIEU) return;   // để trình duyệt gửi form như thường

            e.preventDefault();
            form.dataset.submitted = '1';
            if (henKQ) clearTimeout(henKQ);
            timTaiCho(q);
        });
    }
})();
