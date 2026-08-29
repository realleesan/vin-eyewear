/*
 * admin-modal.js — mở hộp thoại NGAY TẠI CHỖ, không tải lại trang.
 *
 * KHÔNG CÓ FILE NÀY THÌ CHUYỆN GÌ XẢY RA:
 * Mọi nút mở hộp vẫn là thẻ <a> trỏ tới chính trang đó kèm ?them=1 / ?sua=<id>
 * / ?xem=<id>, nên bấm vào là trình duyệt tải lại trang và máy chủ dựng sẵn hộp
 * thoại trong HTML. Chậm hơn một lượt tải, còn lại y hệt: mở được, điền được,
 * lưu được, đóng được. Đó cũng là đường duy nhất khi người dùng tắt JavaScript.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO LÀ FETCH CHỨ KHÔNG PHẢI DỰNG SẴN HỘP RỒI ẨN ĐI
 *
 * Cách hiển nhiên là in sẵn mọi hộp thoại vào trang rồi cho JS bật lên — mở là
 * tức thì, không cần mạng. Nó hỏng ở form SỬA: một trang sản phẩm có hai mươi
 * dòng, mà form sửa sản phẩm dài hơn sáu trăm dòng HTML. Dựng sẵn hai mươi bản
 * là nhân trang lên gấp mười lăm lần, tải chậm hẳn cho MỌI lần mở trang — kể cả
 * những lần không ai bấm Sửa.
 *
 * Fetch thì chỉ lấy đúng cái hộp người ta vừa bấm, và lấy từ CHÍNH ĐỊA CHỈ mà
 * thẻ <a> đang trỏ tới. Không có endpoint riêng, không có bản dựng HTML thứ hai
 * ở phía JS — máy chủ vẫn là nơi duy nhất biết cách vẽ cái hộp đó. Sửa form ở
 * view là cả hai đường cùng đổi.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NẠP TRƯỚC — VÌ "BẤM XONG ĐỢI MỘT LÚC" LÀ KHÔNG CHẤP NHẬN ĐƯỢC
 *
 * Bản đầu fetch NGAY LÚC BẤM, nên mỗi lần mở hộp là một lượt dựng trang đầy đủ
 * ở máy chủ rồi mới có gì để hiện. Trên hosting miễn phí, quãng ấy dài tới mức
 * người dùng bấm thêm lần nữa vì tưởng hụt.
 *
 * Nay HTML được cất vào `kho` và lấy trước khi cần:
 *
 *   · rê chuột / chạm / tab tới một nút mở hộp → nạp NGAY, chen lên trước cả
 *     hàng đợi. Người ta mất ít nhất một phần mười giây từ lúc chuột tới nút
 *     đến lúc bấm, thường lâu hơn nhiều — thừa cho một lượt đi về;
 *   · ngoài ra, lúc trình duyệt rảnh thì xếp hàng nạp trước MỌI nút mở hộp
 *     trên trang, nút đầu trang trước rồi tới nút của từng dòng.
 *
 * HÀNG ĐỢI CHỨ KHÔNG PHẢI BẮN MỘT LOẠT: mỗi lúc đúng một lượt đang bay, và
 * lượt sau chỉ đi khi trình duyệt lại rảnh. Đây là việc nền, nó không được
 * giành đường truyền với thứ người dùng đang thật sự chờ. Có trần
 * TRAN_NAP_TRUOC để một bảng hai trăm dòng không thành hai trăm lượt tải; quá
 * trần thì phần còn lại vẫn nạp được khi rê chuột tới.
 *
 * Rẻ hơn hẳn kể từ khi AdminController bỏ khung trang cho lượt lấy hộp thoại:
 * mỗi lượt nay là ruột trang, không thanh bên, không năm câu COUNT cho huy
 * hiệu. Xem khối chú thích ở AdminController::renderAdmin().
 *
 * Có sẵn trong kho thì hộp được dựng NGAY TRONG HÀM XỬ LÝ CÚ BẤM, không qua
 * một Promise nào — mở là thấy, không nháy.
 *
 * Kho chỉ sống trong một lần tải trang, và trong quãng đó dữ liệu không đổi
 * sau lưng: mọi thao tác ghi (thêm, sửa, xoá, đổi thứ tự) đều là form POST rồi
 * chuyển hướng thật, tức là tải lại trang và kho mới tinh.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HỘP CHỒNG HỘP: MỖI LỚP ĐÓNG RIÊNG
 *
 * Trang Giá tròng mở hai hộp chồng nhau — danh mục gói, rồi form một gói nổi
 * lên trên nó. Bản đầu đóng bằng `innerHTML = ''`, tức là đóng form gói thì
 * danh mục bay theo và người dùng bị ném thẳng về bảng giá.
 *
 * Nay mỗi lần dựng, từng hộp được bọc trong một `.amodal-lop` riêng và đóng chỉ
 * gỡ LỚP TRÊN CÙNG. Địa chỉ để quay về lấy từ chính nút đóng của lớp ấy —
 * `[data-modal-close]` của form gói trỏ về danh mục, của danh mục trỏ về bảng
 * giá — nên không phải đoán, và tắt JS thì cũng đúng cái href đó đưa người dùng
 * về đúng chỗ.
 *
 * Mở sâu thêm một lớp thì GIỮ NGUYÊN các lớp đang có, chỉ đắp thêm lớp mới.
 * Dựng lại cả chồng cũng ra hình ấy, nhưng hộp dưới sẽ chạy lại hiệu ứng hiện
 * ra của nó (amodal-fade / amodal-up) — nhìn như trang nháy một cái ngay dưới
 * hộp vừa mở.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CÙNG MỘT HỘP THÌ ĐỪNG DỰNG LẠI — ĐỔI TAB PHẢI THẤY NHƯ ĐỔI TAB
 *
 * Hồ sơ khách hàng có bốn tab, mỗi tab là một địa chỉ (?tab=ho-so, ?tab=dia-chi
 * …). Bản trước dựng lại CẢ hộp cho mỗi lần đổi: thẻ .amodal cũ bị vứt, thẻ mới
 * chạy lại amodal-fade với amodal-up, và hộp nảy một cái theo chiều cao của tab
 * mới. Nhìn ra đúng như trang vừa tải lại — mà đó lại là thứ cả module này sinh
 * ra để tránh.
 *
 * Nay modal-head in ra `data-modal-key`, và khi bản vừa nạp có cùng khoá với
 * hộp đang mở thì chỉ thay RUỘT (.amodal__panel), giữ nguyên khung. Không thẻ
 * nào bị thay nên không hiệu ứng nào chạy lại. Cộng với .amodal--cao khoá chiều
 * cao, đổi tab nay chỉ có phần nội dung đổi.
 *
 * Không có khoá thì dựng lại cả hộp như cũ — hai hộp trông giống nhau chưa chắc
 * là một, mà dựng lại thì bao giờ cũng đúng.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐỊA CHỈ VẪN ĐỔI THEO
 *
 * Mở hộp thì pushState sang đúng địa chỉ của nó, đóng thì history.back(). Nhờ
 * vậy F5 giữa chừng vẫn ra cái hộp đang mở, gửi đường dẫn cho đồng nghiệp cũng
 * vậy, và nút "quay lại" của trình duyệt đóng hộp thay vì rời trang — thói quen
 * mà người dùng mang theo từ mọi hộp thoại khác họ từng gặp.
 */
(function () {
    'use strict';

    var MO_HOST = 'amodal-host';

    /* Trần kho: chặn trên cho lượng HTML giữ trong bộ nhớ. Rộng hơn trần hàng
       đợi vì kho còn nhận cả những địa chỉ nạp theo đường rê chuột. */
    var GIOI_HAN_KHO = 40;

    /* Trần hàng đợi nạp trước: đủ cho một bảng hai mươi dòng cộng vài nút ở
       đầu trang và đầu hộp. Bảng dài hơn thì phần đuôi đợi rê chuột tới —
       người ta cũng phải cuộn xuống mới bấm được tới đó. */
    var TRAN_NAP_TRUOC = 30;

    var kho     = {};   // địa chỉ -> HTML đã nạp
    var dangNap = {};   // địa chỉ -> lượt fetch đang bay, để không nạp hai lần
    var soDaNap = 0;

    /* Địa chỉ ứng với thứ ĐANG hiện trên màn hình. popstate so với nó để biết
       DOM đã đúng chưa — đóng một lớp xong thì đã đúng rồi, dựng lại là thừa. */
    var hienTai = null;

    // ── Bóc hộp ra khỏi trang máy chủ trả về ────────────────────────────────

    /* Trả về DANH SÁCH LỚP, mỗi lớp là danh sách thẻ của một hộp.
       Trang đơn hàng không dùng .amodal mà có cặp .aodim + .aodraw (nền mờ và
       khung, hai thẻ anh em) — hai thẻ ấy là MỘT lớp, không phải hai. */
    function bocLop(doc) {
        var hop = doc.querySelectorAll('.amodal');

        if (hop.length > 0) {
            return [].map.call(hop, function (n) { return [n]; });
        }

        var dim  = doc.querySelector('.aodim');
        var draw = doc.querySelector('.aodraw');

        return (dim !== null && draw !== null) ? [[dim, draw]] : [];
    }

    // ── Kho chứa các lớp đang mở ────────────────────────────────────────────

    function host() {
        var el = document.getElementById(MO_HOST);

        if (el === null) {
            el = document.createElement('div');
            el.id = MO_HOST;
            document.body.appendChild(el);
        }

        return el;
    }

    function cacLop() {
        var el = document.getElementById(MO_HOST);

        return el === null ? [] : [].slice.call(el.children);
    }

    function dangMo() {
        return cacLop().length > 0;
    }

    /* Trả tiêu điểm về đúng cái nút đã mở lớp này. Không làm thì sau khi đóng,
       tiêu điểm rơi về đầu trang và người dùng bàn phím phải đi lại từ đầu
       bảng. Nút được cất trên chính thẻ bọc lớp, nên hộp trong đóng lại là về
       nút "+ Thêm gói" của hộp ngoài, không phải về nút ở đầu trang. */
    function traTieuDiem(boc) {
        var nut = boc.vinNut || null;

        if (nut !== null && document.contains(nut)) {
            nut.focus();
        }
    }

    /** Gỡ lớp trên cùng. Trả về địa chỉ để quay về, lấy từ nút đóng của lớp ấy. */
    function dongLopTren() {
        var ds = cacLop();

        if (ds.length === 0) {
            return null;
        }

        var tren = ds[ds.length - 1];
        var loi  = tren.querySelector('[data-modal-close]');
        var ve   = loi !== null ? loi.getAttribute('href') : null;

        tren.parentNode.removeChild(tren);
        traTieuDiem(tren);

        return ve;
    }

    function dongHet() {
        var ds = cacLop();

        if (ds.length === 0) {
            return;
        }

        var day = ds[0];

        document.getElementById(MO_HOST).innerHTML = '';
        traTieuDiem(day);
    }

    // ── Nạp HTML ────────────────────────────────────────────────────────────

    function nap(href) {
        if (Object.prototype.hasOwnProperty.call(kho, href)) {
            return Promise.resolve(kho[href]);
        }

        if (Object.prototype.hasOwnProperty.call(dangNap, href)) {
            return dangNap[href];
        }

        dangNap[href] = fetch(href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'fetch' }
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }

                return res.text();
            })
            .then(function (html) {
                delete dangNap[href];

                if (soDaNap < GIOI_HAN_KHO) {
                    kho[href] = html;
                    soDaNap++;
                }

                return html;
            }, function (loi) {
                delete dangNap[href];

                throw loi;
            });

        return dangNap[href];
    }

    /* Nạp trước thì HỎNG CŨNG IM. Người dùng chưa yêu cầu gì cả; lúc họ bấm
       thật, đường bấm sẽ tự fetch lại và tự xử lý lỗi ở đó. */
    function napTruoc(a) {
        var href = a.getAttribute('href');

        if (href !== null && href !== '') {
            // Rê chuột tới là CHEN NGANG hàng đợi: cú bấm sắp tới rồi.
            nap(href).catch(function () {});
        }
    }

    function lucRanh(viec) {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(viec, { timeout: 2000 });
        } else {
            setTimeout(viec, 400);
        }
    }

    // ── Hàng đợi nạp trước ──────────────────────────────────────────────────

    var hangDoi  = [];
    var daXep    = {};
    var soDaXep  = 0;
    var dangChay = false;

    /* Sâu bao nhiêu lớp thì thôi đào tiếp. 2 là đủ cho chồng sâu nhất đang có
       (bảng giá → danh mục gói → form một gói); để sâu hơn thì một trang có
       hộp trỏ vòng về chính nó sẽ đào mãi tới khi chạm trần. */
    var SAU_TOI_DA = 2;

    function chayHangDoi() {
        if (dangChay || hangDoi.length === 0) {
            return;
        }

        dangChay = true;

        var muc = hangDoi.shift();

        nap(muc.href)
            .then(function (html) {
                /*
                 * ĐÀO TIẾP NÚT NẰM TRONG CHÍNH CÁI HỘP VỪA NẠP.
                 *
                 * Không có bước này thì nút trong hộp chỉ được xếp hàng SAU khi
                 * người dùng mở hộp ra — mà lúc ấy họ đã đứng trước nó rồi, và
                 * cú bấm tiếp theo vẫn phải đợi mạng. Đo trên đường trễ 600ms:
                 * mở "Quản lý gói" tức thì, nhưng bấm "+ Thêm gói" ngay sau đó
                 * vẫn mất 1,2 giây.
                 *
                 * Nạp ngay từ lúc rảnh thì cả chồng hộp đã nằm sẵn trong kho
                 * trước khi người dùng chạm vào cái đầu tiên. Không tốn thêm
                 * lượt nào so với trước — chỉ là xếp sớm hơn, và vẫn chung một
                 * trần TRAN_NAP_TRUOC.
                 *
                 * CHỈ ĐÀO TRONG .amodal: phần còn lại của trang trả về là ruột
                 * trang nền (bảng giá, danh sách sản phẩm), mà những nút ở đó
                 * đã được xếp hàng từ chính trang người dùng đang đứng.
                 */
                if (muc.sau < SAU_TOI_DA) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');

                    [].forEach.call(
                        doc.querySelectorAll('.amodal a[data-modal], .aodraw a[data-modal]'),
                        function (a) { xepHang(a.getAttribute('href'), muc.sau + 1); }
                    );
                }
            })
            .catch(function () {})
            .then(function () {
                dangChay = false;

                /* Nghỉ tới lượt rảnh kế tiếp mới chạy tiếp. Nối đuôi liên tục
                   thì hàng đợi nền chiếm hết chỗ trong sáu kết nối của trình
                   duyệt, và cú bấm thật phải xếp sau nó. */
                if (hangDoi.length > 0) {
                    lucRanh(chayHangDoi);
                }
            });
    }

    function xepHang(href, sau) {
        if (href === null || href === '' || soDaXep >= TRAN_NAP_TRUOC
            || Object.prototype.hasOwnProperty.call(daXep, href)
            || Object.prototype.hasOwnProperty.call(kho, href)) {
            return;
        }

        daXep[href] = true;
        soDaXep++;
        hangDoi.push({ href: href, sau: sau || 0 });
    }

    /* Xếp cả trang (hoặc cả hộp vừa mở) vào hàng đợi. NÚT NGOÀI DÒNG BẢNG ĐI
       TRƯỚC — "Quản lý gói", "+ Thêm gói", "+ Thêm mới" là thứ được bấm nhiều
       nhất và bấm sớm nhất; nút Sửa của từng dòng thì người ta còn phải đọc
       bảng đã. */
    function xepHangCaTrang(goc) {
        var ds = goc.querySelectorAll('a[data-modal]');

        [].forEach.call(ds, function (a) {
            if (a.closest('td') === null) {
                xepHang(a.getAttribute('href'), 0);
            }
        });

        [].forEach.call(ds, function (a) {
            if (a.closest('td') !== null) {
                xepHang(a.getAttribute('href'), 0);
            }
        });

        chayHangDoi();
    }

    // ── Dựng hộp ────────────────────────────────────────────────────────────

    /**
     * @param html      HTML của cả trang, do máy chủ dựng
     * @param href      địa chỉ đã lấy nó về
     * @param nut       thẻ <a> vừa bấm, để trả tiêu điểm khi đóng
     * @param theoLichSu false khi đang đi theo nút quay lại/tiến — lúc ấy lịch
     *                   sử đã đúng rồi, đụng vào là đẻ thêm mục thừa
     */
    function ve(html, href, nut, theoLichSu) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var moi = bocLop(doc);

        if (moi.length === 0) {
            /* Máy chủ trả về một trang không có hộp thoại nào — hết phiên, mất
               quyền, hoặc bản ghi vừa bị xoá. Đi tiếp bằng đường thường để
               người dùng thấy trang thật (kể cả trang đăng nhập) thay vì một cú
               bấm im lặng không làm gì. */
            window.location.assign(href);

            return;
        }

        var el = host();
        var cu = cacLop();
        var giu = 0;

        /* ĐỔI TAB TRONG CÙNG MỘT HỘP: thay ruột, giữ khung.
           Xem khối "CÙNG MỘT HỘP THÌ ĐỪNG DỰNG LẠI" ở đầu file. */
        if (cu.length > 0 && moi.length === cu.length
            && thayRuotDuoc(cu[cu.length - 1], moi[moi.length - 1][0])) {
            if (theoLichSu !== false) {
                history.replaceState({ vinModal: href }, '', href);
            }

            hienTai = href;
            sauKhiVe(el, href, false, nut);

            return;
        }

        if (moi.length > cu.length && cu.length > 0) {
            /* Mở sâu thêm: giữ nguyên lớp dưới, chỉ đắp lớp mới lên trên. Lớp
               dưới trong bản vừa nạp và lớp dưới đang hiện là cùng một thứ —
               mọi thao tác ĐỔI dữ liệu đều đi qua form POST rồi tải lại trang,
               nên trong một lần tải, danh sách bên dưới không tự đổi. */
            giu = cu.length;
        } else {
            el.innerHTML = '';
        }

        for (var i = giu; i < moi.length; i++) {
            var boc = document.createElement('div');

            boc.className = 'amodal-lop';

            moi[i].forEach(function (n) {
                boc.appendChild(document.importNode(n, true));
            });

            /* Nút đã mở lớp này, để đóng thì trả tiêu điểm về đúng đó. Chỉ lớp
               trên cùng mới do cú bấm này sinh ra. */
            boc.vinNut = (i === moi.length - 1) ? (nut || null) : null;

            el.appendChild(boc);
        }

        if (theoLichSu !== false) {
            /* Sâu thêm một lớp thì ĐẨY một mục lịch sử — để nút quay lại đóng
               đúng một lớp. Cùng độ sâu (đổi tab trong hồ sơ khách, bấm Sửa một
               bản ghi đo) thì THAY: đẩy thêm thì một lần đóng chỉ lùi được một
               tab, và hộp biến mất trong khi địa chỉ vẫn còn tham số của nó —
               F5 lại bật hộp lên. */
            if (moi.length > cu.length) {
                history.pushState({ vinModal: href }, '', href);
            } else {
                history.replaceState({ vinModal: href }, '', href);
            }
        }

        hienTai = href;

        sauKhiVe(el, href, true, nut);
    }

    /**
     * KHUNG GIỮ NGUYÊN, RUỘT THAY MỚI — trả về true nếu đã thay được.
     *
     * Chỉ thay khi hai bên cùng một khoá: `data-modal-key` do modal-head in ra.
     * Không có khoá thì không thay, vì hai hộp trông giống nhau chưa chắc là
     * một — dựng lại cả hộp bao giờ cũng đúng, chỉ là giật.
     *
     * Thay .amodal__panel là đủ: đầu hộp, thanh tab và ruột đều nằm trong đó,
     * còn hiệu ứng hiện ra thì gắn trên chính .amodal và .amodal__panel — giữ
     * hai thẻ ấy nghĩa là không có gì chạy lại.
     */
    function thayRuotDuoc(bocCu, hopMoi) {
        var hopCu = bocCu.querySelector('.amodal');

        if (hopCu === null || !hopMoi.classList || !hopMoi.classList.contains('amodal')) {
            return false;
        }

        var khoa = hopCu.getAttribute('data-modal-key');

        if (khoa === null || khoa === '' || khoa !== hopMoi.getAttribute('data-modal-key')) {
            return false;
        }

        var khungCu  = hopCu.querySelector('.amodal__panel');
        var khungMoi = hopMoi.querySelector('.amodal__panel');

        if (khungCu === null || khungMoi === null) {
            return false;
        }

        /* Lớp nền mờ nằm NGOÀI panel và mang địa chỉ đóng — cảnh mới có thể
           đóng về chỗ khác, nên chép lại href của nó. */
        var dimCu  = hopCu.querySelector('.amodal__dim');
        var dimMoi = hopMoi.querySelector('.amodal__dim');

        if (dimCu !== null && dimMoi !== null) {
            dimCu.setAttribute('href', dimMoi.getAttribute('href') || '');
        }

        khungCu.innerHTML = document.importNode(khungMoi, true).innerHTML;

        return true;
    }

    /** Cuộn tới neo và đặt tiêu điểm — chung cho cả dựng mới lẫn thay ruột. */
    function sauKhiVe(el, href, dungMoi, nut) {
        var tren = el.lastElementChild;

        /* Địa chỉ có neo (#form-don-thuoc) thì cuộn tới đó: thân hộp vừa được
           dựng lại nên nó đang ở đầu, mà thứ người ta vừa bấm để xem có thể nằm
           tận cuối. */
        var neo  = href.indexOf('#') >= 0 ? href.slice(href.indexOf('#') + 1) : '';
        var dich = neo !== '' ? document.getElementById(neo) : null;

        if (dich !== null) {
            dich.scrollIntoView();
        }

        /* TIÊU ĐIỂM CHỈ ĐỘNG VÀO KHI VỪA DỰNG HỘP MỚI.

           Đổi tab thì không: người dùng vừa bấm một tab và rất có thể bấm tiếp
           tab kế bên, giật tiêu điểm xuống ô nhập đầu tiên trong ruột là bắt họ
           Shift+Tab ngược lên. Thanh tab vẫn còn nguyên trên màn hình nên tiêu
           điểm cứ để yên tại chỗ vừa bấm là đúng nhất. */
        if (!dungMoi) {
            /* THAY RUỘT: TRẢ TIÊU ĐIỂM VỀ CHÍNH CÁI LINK VỪA BẤM.

               Cái tab người dùng vừa bấm nằm trong phần ruột vừa bị thay, nên
               thẻ ấy đã biến mất và tiêu điểm rơi về <body> — bấm Tab tiếp theo
               là đi lại từ đầu trang. Bản mới có đúng một thẻ cùng địa chỉ ấy
               (chính nó, vừa được dựng lại), tìm theo href rồi đặt tiêu điểm
               vào đó là người dùng bàn phím đi tiếp được ngay sang tab kế bên. */
            if (nut && !document.contains(nut)) {
                var ds = tren.querySelectorAll('a[data-modal]');

                for (var i = 0; i < ds.length; i++) {
                    if (ds[i].getAttribute('href') === href) {
                        ds[i].focus();
                        break;
                    }
                }
            }
        } else {
            /* Tiêu điểm vào ô nhập đầu tiên — người bấm "Thêm mới" định gõ
               ngay, và bắt họ bấm thêm một cái nữa vào ô đầu là thừa.

               CHỈ với hộp CÓ nút lưu ở chân. Hộp chỉ để xem (hồ sơ khách, ngăn
               kéo đơn hàng) vẫn có ô nhập nằm đâu đó giữa trang — hồ sơ khách
               có ô "lý do khoá" — và nhảy tiêu điểm vào đó là cuộn thân hộp
               xuống giữa chừng ngay khi vừa mở. Không có ô nào thì lấy chính
               khung hộp, để phím Esc và trình đọc màn hình bắt đúng chỗ. */
            var coLuu = tren.querySelector(
                '.amodal__foot button[type="submit"], .aodraw button[type="submit"]'
            ) !== null;
            var oDau = coLuu
                ? tren.querySelector('input:not([type="hidden"]), select, textarea')
                : null;
            var khung = tren.querySelector('.amodal__panel, .aodraw');

            if (oDau !== null) {
                oDau.focus();
            } else if (dich === null && khung !== null) {
                khung.setAttribute('tabindex', '-1');
                khung.focus();
            }
        }

        // Nút mở hộp NẰM TRONG hộp vừa mở cũng nạp trước, cùng luật với ngoài trang.
        lucRanh(function () { xepHangCaTrang(tren); });
    }

    function mo(href, nut, theoLichSu) {
        if (Object.prototype.hasOwnProperty.call(kho, href)) {
            // Có sẵn: dựng NGAY trong cú bấm, không qua Promise, không nháy.
            ve(kho[href], href, nut, theoLichSu);

            return;
        }

        nap(href)
            .then(function (html) {
                ve(html, href, nut, theoLichSu);
            })
            .catch(function () {
                // Mạng hỏng giữa chừng: đi bằng đường thường, đừng nuốt cú bấm.
                window.location.assign(href);
            });
    }

    // ── Bắt sự kiện ─────────────────────────────────────────────────────────

    function timNut(e, chon) {
        var t = e.target;

        return (t && typeof t.closest === 'function') ? t.closest(chon) : null;
    }

    document.addEventListener('click', function (e) {
        /* Chuột giữa, Ctrl/Cmd+bấm, Shift+bấm — người dùng đang cố mở tab mới
           hoặc cửa sổ mới. Đừng chặn: hộp thoại này CÓ địa chỉ thật, mở ở tab
           khác là chuyện hợp lệ. */
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey
            || e.shiftKey || e.altKey) {
            return;
        }

        /* dangMo() chỉ đúng khi hộp do file này dựng. Hộp máy chủ dựng sẵn (tải
           thẳng ?them=1, hoặc tắt JS) nằm trong trang chứ không trong host, nên
           nút đóng của nó là một liên kết thường và cứ để trình duyệt đi tiếp —
           đúng đường lùi mà cả module này dựa vào. */
        if (timNut(e, '[data-modal-close]') !== null && dangMo()) {
            e.preventDefault();

            hienTai = dongLopTren();
            history.back();

            return;
        }

        var nut = timNut(e, 'a[data-modal]');

        if (nut === null) {
            return;
        }

        var href = nut.getAttribute('href');

        if (href === null || href === '') {
            return;
        }

        e.preventDefault();
        mo(href, nut, true);
    });

    /* Rê chuột / chạm / tab tới là nạp trước. Ba sự kiện vì ba lối vào khác
       nhau: chuột, cảm ứng, bàn phím. Trùng nhau cũng không sao — nap() gộp
       chung một lượt fetch cho mỗi địa chỉ. */
    document.addEventListener('mouseover', function (e) {
        var a = timNut(e, 'a[data-modal]');

        if (a !== null) {
            napTruoc(a);
        }
    });

    document.addEventListener('focusin', function (e) {
        var a = timNut(e, 'a[data-modal]');

        if (a !== null) {
            napTruoc(a);
        }
    });

    document.addEventListener('touchstart', function (e) {
        var a = timNut(e, 'a[data-modal]');

        if (a !== null) {
            napTruoc(a);
        }
    }, { passive: true });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && dangMo()) {
            hienTai = dongLopTren();
            history.back();
        }
    });

    /* Bấm "quay lại" khi hộp đang mở thì đóng hộp — không rời trang. Cũng chạy
       ngay sau history.back() của chính hàm đóng ở trên: lúc ấy DOM đã đúng và
       hienTai đã trỏ về địa chỉ của lớp còn lại, nên nhánh đầu tiên thoát ra
       mà không dựng lại gì. Nút TIẾN thì rơi vào nhánh cuối và mở lại hộp. */
    window.addEventListener('popstate', function () {
        var st   = history.state;
        var href = (st && st.vinModal) ? st.vinModal : null;

        if (href === null) {
            hienTai = null;
            dongHet();

            return;
        }

        if (href === hienTai) {
            return;
        }

        mo(href, null, false);
    });

    lucRanh(function () { xepHangCaTrang(document); });
}());
