/**
 * server.js — CẦU NỐI SEPAY ⇄ VIN EYEWEAR
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * VÌ SAO CÓ MÁY CHỦ NÀY
 *
 * Website chạy trên InfinityFree bản miễn phí. Hosting đó đặt một lớp chống bot
 * TRƯỚC Apache: mọi khách không phải trình duyệt đều nhận về một trang HTML đố
 * JavaScript thay vì tới được index.php. Máy chủ của SePay không chạy JS, nên
 * webhook KHÔNG BAO GIỜ tới /webhook/sepay — và không để lại dấu vết nào trong
 * error log của site để mà lần (xem cuối kiem-tra-sepay.php).
 *
 * Cầu nối này đứng ở giữa, trên Render (có địa chỉ công khai thật, nhận được
 * webhook), và đưa giao dịch về website bằng HAI đường độc lập:
 *
 *   ĐẨY   cầu nối -> website.  Tự giải lời đố của lớp chống bot rồi POST vào
 *         /webhook/sepay. Nhanh nhất, nhưng phụ thuộc vào việc lời đố không
 *         đổi kiểu — xem lib/infinityfree.js.
 *
 *   KÉO   website -> cầu nối.  Website POST /api/keo để lấy những giao dịch
 *         chưa nhận được. Chiều này website gọi RA NGOÀI nên lớp chống bot
 *         không đứng chắn — đây là đường KHÔNG BAO GIỜ HỎNG, và là lý do cả hệ
 *         thống vẫn chạy được kể cả ngày InfinityFree đổi cách chặn.
 *         Website gọi lúc nào: mỗi lần màn QR của khách hỏi thăm trạng thái
 *         đơn (pay-watch.js hỏi mỗi 4 giây trong hai phút đầu), và mỗi lần
 *         nhân viên mở /quan-tri/don-hang. Tức là đúng lúc cần.
 *
 * Cả hai đường đổ vào cùng một hộp thư (lib/hang-doi.js) và cùng chống trùng
 * bằng `id` của SePay, nên giao hai lần cũng không cộng tiền hai lần.
 * ═════════════════════════════════════════════════════════════════════════════
 * BA KHOÁ, KHÔNG PHẢI MỘT
 *
 *   SEPAY_WEBHOOK_KEY   SePay -> cầu nối.   Dán bên my.sepay.vn.
 *   SITE_WEBHOOK_KEY    cầu nối -> website. Là SEPAY_WEBHOOK_KEY trong .env
 *                       TRÊN HOSTING (tên biến bên đó không đổi được vì
 *                       config/sepay.php đọc đúng tên ấy).
 *   PULL_KEY            website -> cầu nối. Đường kéo.
 *
 * Ba chặng, ba khoá. Lộ một chặng không mở được chặng nào khác. Dùng chung một
 * chuỗi cho cả ba thì tiện hơn đúng một lần — lúc cấu hình — và trả giá vào
 * ngày phải đổi khoá.
 * ═════════════════════════════════════════════════════════════════════════════
 * KHÔNG CÓ PHỤ THUỘC NGOÀI NÀO
 *
 * Cùng ràng buộc với phần website (xem CLAUDE.md): không package cần cài, không
 * bước build. `node server.js` là chạy. Toàn bộ dùng module có sẵn của Node 20:
 * node:http, node:crypto, node:fs. Kéo mã về là chạy được, ở đây cũng như ở kia.
 * ═════════════════════════════════════════════════════════════════════════════
 */

'use strict';

const http   = require('node:http');
const crypto = require('node:crypto');

const cauHinh      = require('./lib/cau-hinh');
const nhatKy       = require('./lib/nhat-ky');
const HangDoi      = require('./lib/hang-doi');
const infinityfree = require('./lib/infinityfree');

/* Thân request tối đa. Một giao dịch SePay chưa tới 1KB; 64KB là rộng rãi gấp
   sáu chục lần. Đặt trần vì địa chỉ này công khai và không có gì ngăn ai đó
   bơm 500MB vào bộ nhớ của một tiến trình 512MB. */
const THAN_TOI_DA = 64 * 1024;

const hangDoi = new HangDoi(cauHinh, nhatKy);

// ─────────────────────────────────────────────────────────────────────────────
// TIỆN ÍCH
// ─────────────────────────────────────────────────────────────────────────────

/** Trả JSON rồi đóng. */
function traLoi(res, ma, duLieu) {
    const than = JSON.stringify(duLieu);

    res.writeHead(ma, {
        'Content-Type': 'application/json; charset=utf-8',
        'Content-Length': Buffer.byteLength(than),
        'Cache-Control': 'no-store',
    });
    res.end(than);
}

/**
 * Lấy khoá ra khỏi header `Authorization`.
 *
 * Chấp nhận cả "Apikey abc", "ApiKey abc", "Bearer abc" và chuỗi trần: trang
 * cấu hình của SePay từng đổi cách viết, và một khoảng trắng thừa không đáng
 * làm hỏng cả tích hợp. Cùng cách xử sự với SepayController::apiKey() bên PHP —
 * hai đầu phải dễ tính giống nhau, không thì lỗi chỉ hiện ở một đầu.
 */
function docKhoa(req) {
    const tho = String(req.headers.authorization || '').trim();

    return tho.replace(/^\s*(api\s*key|bearer)\s+/i, '').trim();
}

/**
 * So khoá không để lộ thời gian.
 *
 * So bằng `===` cho phép dò từng ký tự qua thời gian phản hồi. Băm cả hai về 32
 * byte trước rồi timingSafeEqual: vừa chống dò, vừa khỏi lo hai chuỗi khác độ
 * dài (timingSafeEqual ném lỗi khi độ dài lệch — và chính việc nó ném lỗi cũng
 * đã là một kênh rò rỉ độ dài khoá).
 */
function khoaTrung(a, b) {
    if (!a || !b) return false;

    const ba = crypto.createHash('sha256').update(String(a)).digest();
    const bb = crypto.createHash('sha256').update(String(b)).digest();

    return crypto.timingSafeEqual(ba, bb);
}

/** IP thật của người gọi. Render đứng sau proxy nên phải đọc X-Forwarded-For. */
function ipGoi(req) {
    const xff = String(req.headers['x-forwarded-for'] || '').split(',')[0].trim();

    return xff || req.socket.remoteAddress || '?';
}

/** Đọc thân request, chặn ở THAN_TOI_DA. */
function docThan(req) {
    return new Promise((ok, hong) => {
        let tong = 0;
        const manh = [];

        req.on('data', (m) => {
            tong += m.length;

            if (tong > THAN_TOI_DA) {
                hong(new Error('Thân request quá lớn'));
                req.destroy();

                return;
            }

            manh.push(m);
        });
        req.on('end', () => ok(Buffer.concat(manh).toString('utf8')));
        req.on('error', hong);
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// ĐẨY MỘT GIAO DỊCH SANG WEBSITE
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Gửi một giao dịch vào /webhook/sepay của website.
 *
 * ĐỌC KẾT QUẢ THEO MÃ TRẠNG THÁI CỦA WEBSITE, đúng như SepayController đã hứa:
 *
 *   200  đã nhận và đã hiểu (kể cả kết luận là "không làm gì cả") -> XONG
 *   400  payload hỏng      -> gửi lại bao nhiêu lần cũng thế, coi như xong
 *   401  sai khoá          -> lỗi CẤU HÌNH, gửi lại vô ích, phải có người sửa
 *   403  website chưa khai khoá                     — cũng là lỗi cấu hình
 *   503  website chưa chạy migration                — lỗi TẠM, giữ lại chờ
 *   5xx  website lỗi thật                           — lỗi TẠM, giữ lại chờ
 *
 * Phân biệt "hỏng vĩnh viễn" với "hỏng tạm" là điểm mấu chốt: giữ lại một giao
 * dịch mà website sẽ không bao giờ nhận nổi thì hộp thư đầy dần bằng rác, và
 * cái rác đó sẽ đẩy văng giao dịch thật khi chạm trần (xem HangDoi.donDep).
 *
 * @return {Promise<{xong: boolean, tam: boolean, ma: number, ghiChu: string}>}
 */
async function dayVeSite(txn) {
    const kq = await infinityfree.goi(cauHinh.urlSite, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json; charset=utf-8',
            Authorization: 'Apikey ' + cauHinh.khoaSite,
        },
        body: JSON.stringify(txn),
        hanGio: cauHinh.hanGioSite,
    });

    if (kq.loi !== null) {
        return { xong: false, tam: true, ma: 0, ghiChu: kq.loi };
    }

    const tomTat = (kq.quaTuong ? 'qua lớp chống bot, ' : '')
                 + 'HTTP ' + kq.ma + ' — ' + kq.than.slice(0, 200).replace(/\s+/g, ' ');

    if (kq.ma === 200 || kq.ma === 201) {
        return { xong: true, tam: false, ma: kq.ma, ghiChu: tomTat };
    }

    if (kq.ma === 400) {
        return { xong: true, tam: false, ma: kq.ma, ghiChu: 'website từ chối payload: ' + tomTat };
    }

    if (kq.ma === 401 || kq.ma === 403) {
        /* Kêu to: đây là loại lỗi im lặng nhất trong cả tích hợp. Tiền vẫn về
           tài khoản, đơn vẫn treo, và không ai thấy gì ngoài dòng này. */
        nhatKy.loi('WEBSITE TỪ CHỐI KHOÁ (HTTP ' + kq.ma + '). '
            + 'SITE_WEBHOOK_KEY ở Render có vân tay ' + nhatKy.vanTay(cauHinh.khoaSite)
            + ' — so với vân tay in ở mục 2 của kiem-tra-sepay.php trên hosting. '
            + 'Trong lúc chưa sửa, giao dịch vẫn về được bằng đường kéo.');

        /* Vẫn coi là TẠM chứ không vứt: khoá lệch là thứ người sửa được trong
           mười phút, và giao dịch phải còn nguyên khi khoá đã đúng. */
        return { xong: false, tam: true, ma: kq.ma, ghiChu: tomTat };
    }

    return { xong: false, tam: true, ma: kq.ma, ghiChu: tomTat };
}

// ─────────────────────────────────────────────────────────────────────────────
// ĐƯỜNG 1 — POST /webhook/sepay   (SePay gọi vào)
// ─────────────────────────────────────────────────────────────────────────────

async function nhanTuSepay(req, res) {
    const ip = ipGoi(req);

    /* CHƯA KHAI KHOÁ = ĐÓNG HẲN, y hệt SepayController. Mở tự do trong lúc chờ
       cấu hình xong nghĩa là để sẵn một nút "đánh dấu đã trả tiền" cho cả
       internet bấm — mà cầu nối này còn công khai hơn cả website. */
    if (cauHinh.khoaSepay === '') {
        nhatKy.loi('Có request tới /webhook/sepay nhưng SEPAY_WEBHOOK_KEY chưa khai — từ chối. IP ' + ip);

        return traLoi(res, 403, { success: false, message: 'Cầu nối chưa được cấu hình' });
    }

    if (!khoaTrung(cauHinh.khoaSepay, docKhoa(req))) {
        nhatKy.loi('Sai khoá ở /webhook/sepay, IP ' + ip
            + ' (gửi kèm vân tay ' + nhatKy.vanTay(docKhoa(req)) + ')');

        return traLoi(res, 401, { success: false, message: 'Unauthorized' });
    }

    if (cauHinh.ipSepay.length > 0 && !cauHinh.ipSepay.includes(ip)) {
        nhatKy.loi('Khoá đúng nhưng IP ' + ip + ' không nằm trong SEPAY_IPS — từ chối. '
            + 'Nếu SePay vừa đổi hạ tầng thì XOÁ biến SEPAY_IPS đi, đừng đoán.');

        return traLoi(res, 403, { success: false, message: 'IP không được phép' });
    }

    let txn;

    try {
        txn = JSON.parse(await docThan(req));
    } catch (e) {
        nhatKy.loi('Payload không đọc được từ IP ' + ip + ': ' + e.message);

        return traLoi(res, 400, { success: false, message: 'Payload không hợp lệ' });
    }

    if (!txn || typeof txn !== 'object' || !(Number(txn.id) > 0)) {
        return traLoi(res, 400, { success: false, message: 'Payload không hợp lệ' });
    }

    const { moi, ban } = hangDoi.them(txn);

    nhatKy.tin('Nhận #' + ban.id + ' ' + (txn.transferType || '?') + ' '
        + (txn.transferAmount || 0) + 'đ' + (moi ? '' : ' (SePay gửi lại lần ' + ban.lanNhan + ')'));

    /* Đã giao rồi mà SePay vẫn gửi lại: nó chưa nhận được câu trả lời của lần
       trước (mạng đứt đúng lúc trả lời). Trả 200 ngay, đừng đẩy lại — website
       chống trùng được nhưng mỗi lần đẩy là một lần đi qua lớp chống bot. */
    if (ban.daGiao) {
        return traLoi(res, 200, { success: true, message: 'Đã xử lý trước đó' });
    }

    const kq = await dayVeSite(txn);

    if (kq.xong) {
        hangDoi.danhDauGiao(ban.id, 'day');
        nhatKy.tin('Đẩy #' + ban.id + ' về website: ' + kq.ghiChu);

        return traLoi(res, 200, { success: true, forwarded: true });
    }

    hangDoi.ghiLoi(ban.id, kq.ghiChu);
    nhatKy.loi('Đẩy #' + ban.id + ' HỎNG: ' + kq.ghiChu
        + ' — giao dịch nằm lại hàng đợi, chờ website kéo.');

    /*
     * MÃ TRẢ VỀ Ở ĐÂY LÀ MỘT QUYẾT ĐỊNH VỀ ĐỘ BỀN, KHÔNG PHẢI VỀ SỰ LỊCH SỰ.
     * Xem chú thích `ackKhiXepHang` trong cau-hinh.js — mặc định trả 500 để
     * mượn cơ chế gửi lại của chính SePay làm tầng bền vững.
     */
    if (cauHinh.ackKhiXepHang) {
        return traLoi(res, 200, { success: true, queued: true });
    }

    return traLoi(res, 500, { success: false, message: 'Chưa giao được về website, đã xếp hàng đợi' });
}

// ─────────────────────────────────────────────────────────────────────────────
// ĐƯỜNG 2 — POST /api/keo   (website gọi ra lấy)
//
// Thân request:  {"ack": [123, 456], "gioi_han": 20}
//   ack       những sepay_id website đã GHI XONG VÀO SỔ ở vòng trước.
//   gioi_han  lấy nhiều nhất bao nhiêu giao dịch một lượt.
//
// Trả về:        {"ok": true, "giao_dich": [ ...payload gốc của SePay... ],
//                 "con_lai": 3}
//
// WEBSITE CHỈ ĐƯỢC ACK SAU KHI ĐÃ GHI VÀO CSDL, không phải sau khi nhận được.
// Ack sớm rồi chết giữa chừng là mất giao dịch — mà "mất" ở đây nghĩa là tiền
// đã về tài khoản còn đơn thì treo mãi. Xem SepayRelay::keo() bên PHP.
// ─────────────────────────────────────────────────────────────────────────────

async function choWebsiteKeo(req, res) {
    if (cauHinh.khoaKeo === '') {
        return traLoi(res, 403, { ok: false, message: 'PULL_KEY chưa khai — đường kéo đang tắt' });
    }

    if (!khoaTrung(cauHinh.khoaKeo, docKhoa(req))) {
        nhatKy.loi('Sai khoá ở /api/keo, IP ' + ipGoi(req));

        return traLoi(res, 401, { ok: false, message: 'Unauthorized' });
    }

    let yeuCau = {};

    try {
        const tho = await docThan(req);

        if (tho.trim() !== '') yeuCau = JSON.parse(tho);
    } catch (e) {
        return traLoi(res, 400, { ok: false, message: 'Payload không hợp lệ' });
    }

    const ack = Array.isArray(yeuCau.ack) ? yeuCau.ack : [];
    let daAck = 0;

    for (const id of ack) {
        if (hangDoi.danhDauGiao(id, 'keo')) daAck += 1;
    }

    if (daAck > 0) {
        nhatKy.tin('Website báo đã ghi sổ ' + daAck + ' giao dịch: ' + ack.join(', '));
    }

    const gioiHan = Math.min(Math.max(Number(yeuCau.gioi_han) || 20, 1), 100);
    const ds      = hangDoi.choXuLy(gioiHan);

    if (ds.length > 0) {
        nhatKy.tin('Trao cho website ' + ds.length + ' giao dịch chưa xử lý.');
    }

    return traLoi(res, 200, {
        ok: true,
        giao_dich: ds.map((b) => b.txn),
        con_lai: Math.max(0, hangDoi.demChoXuLy() - ds.length),
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// CHẨN ĐOÁN — GET /kiem-tra   (và ?thu=1 để bắn thử một phát qua lớp chống bot)
// ─────────────────────────────────────────────────────────────────────────────

async function chanDoan(req, res, url) {
    /* Khoá kéo mở được mục này; chưa khai thì dùng khoá SePay. Không mở tự do:
       màn này in ra cấu hình bên trong và số giao dịch đang chờ. */
    const khoaCan = cauHinh.khoaKeo || cauHinh.khoaSepay;

    if (!khoaTrung(khoaCan, docKhoa(req))) {
        return traLoi(res, 401, {
            ok: false,
            message: 'Gọi kèm header: Authorization: Apikey <PULL_KEY>',
        });
    }

    const bao = {
        ok: true,
        gio: new Date().toISOString(),
        cau_hinh: {
            url_site: cauHinh.urlSite || '(CHƯA KHAI)',
            khoa_sepay: nhatKy.vanTay(cauHinh.khoaSepay),
            khoa_site: nhatKy.vanTay(cauHinh.khoaSite),
            khoa_keo: nhatKy.vanTay(cauHinh.khoaKeo),
            ack_khi_xep_hang: cauHinh.ackKhiXepHang,
            loc_ip: cauHinh.ipSepay.length > 0 ? cauHinh.ipSepay : '(không lọc)',
        },
        hang_doi: hangDoi.tomTat(),
        /* Ba vân tay ở trên dùng để SO, không phải để dán:
             khoa_sepay phải trùng chuỗi đã dán bên my.sepay.vn
             khoa_site  phải trùng vân tay mục 2 của kiem-tra-sepay.php trên hosting
             khoa_keo   phải trùng SEPAY_RELAY_KEY trong .env trên hosting */
        ghi_chu: 'Vân tay để SO, không phải để dán. Xem relay/README.md.',
    };

    if (url.searchParams.get('thu') === '1') {
        /*
         * BẮN THỬ CẢ CHẶNG ĐẨY, KHÔNG ĐỤNG VÀO DỮ LIỆU THẬT.
         *
         * Gửi một payload có id = 0. SepayController kiểm theo đúng thứ tự:
         * khoá -> bảng -> payload. Nên mã trả về nói được đang kẹt ở đâu, mà
         * không giao dịch nào được ghi vào sổ:
         *
         *   400  ✓ TOÀN BỘ CHẶNG ĐẨY THÔNG — qua lớp chống bot, header
         *        Authorization tới được PHP, khoá khớp, chỉ payload là bịa.
         *   401  khoá lệch          403  website chưa khai khoá
         *   503  chưa chạy migration
         *   0    không qua nổi lớp chống bot (đọc `loi` để biết vì sao)
         */
        const kq = await infinityfree.goi(cauHinh.urlSite, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
                Authorization: 'Apikey ' + cauHinh.khoaSite,
            },
            body: JSON.stringify({ id: 0, thu: true }),
            hanGio: cauHinh.hanGioSite,
        });

        bao.ban_thu = {
            ma: kq.ma,
            qua_lop_chong_bot: kq.quaTuong || (kq.ma > 0),
            loi: kq.loi,
            than: kq.than.slice(0, 300),
            ket_luan: kq.ma === 400
                ? '✓ Chặng đẩy THÔNG (400 = website nhận được, hiểu được, chỉ chê payload thử).'
                : kq.ma === 401 ? '✗ Khoá lệch — so khoa_site ở trên với mục 2 của kiem-tra-sepay.php.'
                : kq.ma === 403 ? '✗ Website chưa khai SEPAY_WEBHOOK_KEY trong .env trên hosting.'
                : kq.ma === 503 ? '✗ Website chưa chạy migration 2026-08-22-sepay-doi-soat.sql.'
                : kq.ma === 0   ? '✗ Không qua được lớp chống bot. Đường đẩy hỏng — đường kéo vẫn chạy.'
                : '? Website trả HTTP ' + kq.ma + ', xem `than`.',
        };
    }

    return traLoi(res, 200, bao);
}

// ─────────────────────────────────────────────────────────────────────────────
// ĐIỀU HƯỚNG
// ─────────────────────────────────────────────────────────────────────────────

const may = http.createServer(async (req, res) => {
    let url;

    try {
        url = new URL(req.url, 'http://x');
    } catch (e) {
        return traLoi(res, 400, { ok: false });
    }

    const duong = url.pathname.replace(/\/+$/, '') || '/';

    try {
        if (duong === '/' && req.method === 'GET') {
            /* Trang sống-chết cho dịch vụ ping bên ngoài (cron-job.org…) đánh
               thức Render trước giờ mở cửa. KHÔNG in gì về cấu hình: địa chỉ
               này ai cũng gọi được. */
            res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });

            return res.end('Vin Eyewear — cầu nối SePay. Đang chạy.\n');
        }

        if (duong === '/webhook/sepay') {
            if (req.method !== 'POST') {
                return traLoi(res, 405, { success: false, message: 'Method not allowed' });
            }

            return await nhanTuSepay(req, res);
        }

        if (duong === '/api/keo') {
            if (req.method !== 'POST') {
                return traLoi(res, 405, { ok: false, message: 'Method not allowed' });
            }

            return await choWebsiteKeo(req, res);
        }

        if (duong === '/kiem-tra' && req.method === 'GET') {
            return await chanDoan(req, res, url);
        }

        return traLoi(res, 404, { ok: false, message: 'Không có đường này' });
    } catch (e) {
        /* Lưới cuối. Một exception lọt ra khỏi handler trong Node là tiến trình
           chết, mà tiến trình chết đúng lúc SePay đang gửi thì SePay nhận được
           lỗi kết nối chứ không phải mã HTTP — và bên đó ghi nhật ký khác hẳn. */
        nhatKy.loi('Lỗi không lường trước ở ' + duong + ': ' + (e && e.stack || e));

        if (!res.headersSent) {
            return traLoi(res, 500, { ok: false, message: 'Lỗi máy chủ' });
        }

        return res.end();
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// KHỞI ĐỘNG
// ─────────────────────────────────────────────────────────────────────────────

const thieu = cauHinh.thieu();

if (thieu.length > 0) {
    /* CHẾT NGAY chứ không chạy nửa vời. Một cầu nối thiếu SITE_WEBHOOK_URL vẫn
       nhận webhook ngon lành rồi ném tất cả vào hư không — và trên bảng điều
       khiển của Render thì dịch vụ vẫn xanh. Thà không lên. */
    nhatKy.loi('THIẾU BIẾN MÔI TRƯỜNG BẮT BUỘC: ' + thieu.join(', '));
    nhatKy.loi('Khai ở Render -> dịch vụ -> Environment. Xem relay/README.md.');
    process.exit(1);
}

may.listen(cauHinh.cong, () => {
    nhatKy.tin('Cầu nối SePay đang nghe cổng ' + cauHinh.cong);
    nhatKy.tin('  website     ' + cauHinh.urlSite);
    nhatKy.tin('  khoá SePay  vân tay ' + nhatKy.vanTay(cauHinh.khoaSepay));
    nhatKy.tin('  khoá site   vân tay ' + nhatKy.vanTay(cauHinh.khoaSite));
    nhatKy.tin('  khoá kéo    vân tay ' + nhatKy.vanTay(cauHinh.khoaKeo)
        + (cauHinh.khoaKeo === '' ? '  ⚠ CHƯA KHAI — chỉ còn đường đẩy' : ''));

    if (cauHinh.khoaSite === cauHinh.khoaSepay && cauHinh.khoaSepay !== '') {
        nhatKy.loi('SITE_WEBHOOK_KEY đang trùng SEPAY_WEBHOOK_KEY. Chạy được, '
            + 'nhưng lộ một chặng là lộ cả hai — nên tách ra khi xong việc thử.');
    }
});

/* Render gửi SIGTERM rồi đợi trước khi giết hẳn. Đóng tử tế để request đang dở
   được trả lời xong — request đang dở ở đây có thể là một giao dịch tiền thật. */
for (const tinHieu of ['SIGTERM', 'SIGINT']) {
    process.on(tinHieu, () => {
        nhatKy.tin('Nhận ' + tinHieu + ', đóng máy chủ...');
        may.close(() => process.exit(0));
        setTimeout(() => process.exit(0), 10000).unref();
    });
}
