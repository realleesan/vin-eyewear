/**
 * infinityfree.js — gọi được vào một site InfinityFree từ máy chủ, không qua
 * trình duyệt.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VẤN ĐỀ: HOSTING MIỄN PHÍ KHÔNG TIN AI KHÔNG PHẢI TRÌNH DUYỆT
 *
 * InfinityFree đặt một lớp chống bot TRƯỚC cả Apache. Request đầu tiên từ một
 * khách lạ KHÔNG bao giờ tới được index.php: nó nhận về một trang HTML nhỏ,
 * mã 200, nội dung đại khái:
 *
 *     <script src="/aes.js"></script>
 *     <script>
 *       var a = toNumbers("f655ba9d09a112d4968c63579db590b4"),   // khoá AES
 *           b = toNumbers("98344c2eee86c3994890592585b49f80"),   // IV
 *           c = toNumbers("2a3f...");                            // bản mã
 *       document.cookie = "__test=" + toHex(slowAES.decrypt(c, 2, a, b))
 *                       + "; expires=...; path=/";
 *       location.href = "...";
 *     </script>
 *
 * Trình duyệt chạy đoạn đó, có cookie `__test`, tải lại, và từ đó đi thẳng.
 * Máy chủ của SePay thì không chạy JavaScript — nó thấy 200 kèm HTML, coi như
 * đã giao xong, và webhook KHÔNG BAO GIỜ tới PHP. Không có dòng nào trong error
 * log của site để mà lần: đó chính là triệu chứng "nhật ký WebHooks bên SePay
 * trống trơn" ghi ở cuối kiem-tra-sepay.php.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CÁCH GIẢI: LÀM ĐÚNG VIỆC TRÌNH DUYỆT LÀM, BẰNG 20 DÒNG
 *
 * `slowAES.decrypt(c, 2, a, b)` là AES-CBC (chế độ 2) giải bản mã c bằng khoá a
 * và IV b. Node có sẵn trong `node:crypto`, không cần cài gì. Giải xong đem hex
 * hoá là ra đúng giá trị cookie.
 *
 * KHÔNG BỎ ĐỆM (setAutoPadding(false)): slowAES trả về nguyên khối 16 byte, còn
 * OpenSSL mặc định coi byte cuối là số byte đệm PKCS#7 rồi cắt đi — cắt nhầm là
 * cookie sai một khúc và tường chống bot đá về, lặp vô hạn.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ CHỖ DỄ HỎNG NHẤT CỦA CẢ HỆ THỐNG, VÀ NÓ KHÔNG PHẢI LỖI CỦA TA
 *
 * InfinityFree đổi lớp chống bot lúc nào là việc của họ. Nên hàm này KHÔNG được
 * phép là đường duy nhất: giao hỏng thì giao dịch vẫn nằm trong hàng đợi và
 * website tự KÉO về (POST /api/keo) — chiều đó là website gọi ra ngoài, tường
 * chống bot không đứng chắn. Xem hang-doi.js và core/SepayRelay.php.
 *
 * Vì thế mọi thất bại ở đây đều ghi log kèm 300 ký tự đầu của thứ nhận được:
 * ngày lớp chống bot đổi, dòng log đó là manh mối duy nhất.
 * ─────────────────────────────────────────────────────────────────────────────
 */

'use strict';

const crypto = require('node:crypto');

/*
 * User-Agent của một trình duyệt thật.
 *
 * Không phải để giả dạng cho vui: lớp chống bot của InfinityFree chặn thẳng
 * những UA có chữ "curl", "python", "node", "bot". Gửi UA mặc định của Node là
 * ăn 403 trước cả khi có cơ hội giải đố.
 */
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
         + '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

/** "f6 55 ba…" dạng hex -> Buffer. Đúng việc toNumbers() bên kia làm. */
function tuHex(hex) {
    return Buffer.from(hex, 'hex');
}

/**
 * Đọc lời đố ra khỏi trang HTML.
 *
 * @return {{khoa: Buffer, iv: Buffer, banMa: Buffer}|null}
 */
function docLoiDo(html) {
    /* Ba lần gọi toNumbers("...") theo đúng thứ tự khoá, IV, bản mã. Bắt cả ba
       bằng một biểu thức toàn cục rồi lấy ba cái đầu, thay vì cố khớp nguyên
       câu lệnh `var a=...,b=...,c=...`: khoảng trắng và tên biến bên đó đã đổi
       ít nhất một lần, còn ba lời gọi này thì chưa. */
    const m = String(html).match(/toNumbers\("([0-9a-fA-F]+)"\)/g);

    if (!m || m.length < 3) {
        return null;
    }

    const hex = m.slice(0, 3).map((s) => s.replace(/^toNumbers\("|"\)$/g, ''));
    const khoa = tuHex(hex[0]);
    const iv   = tuHex(hex[1]);

    // AES-128 hoặc AES-256; IV luôn 16 byte. Cỡ khác là lời đố đã đổi kiểu.
    if ((khoa.length !== 16 && khoa.length !== 32) || iv.length !== 16) {
        return null;
    }

    return { khoa, iv, banMa: tuHex(hex[2]) };
}

/** Giải lời đố -> giá trị cookie `__test`. */
function giaiLoiDo({ khoa, iv, banMa }) {
    const thuatToan = khoa.length === 32 ? 'aes-256-cbc' : 'aes-128-cbc';
    const d = crypto.createDecipheriv(thuatToan, khoa, iv);

    d.setAutoPadding(false);   // xem khối chú thích đầu file

    return Buffer.concat([d.update(banMa), d.final()]).toString('hex');
}

/** Trang nhận được có phải lời đố không? */
function laLoiDo(res, than) {
    /* Nhận diện bằng NỘI DUNG chứ không bằng mã trạng thái: lớp chống bot trả
       200 kèm HTML, y như một trang bình thường. */
    if (typeof than !== 'string') return false;

    return than.includes('toNumbers(') && than.includes('__test=');
}

/**
 * Gọi một địa chỉ trên site InfinityFree, tự vượt lớp chống bot nếu gặp.
 *
 * @param {string} url
 * @param {{method?: string, headers?: object, body?: string, hanGio?: number}} tuyChon
 * @return {Promise<{ma: number, than: string, quaTuong: boolean, loi: string|null}>}
 */
async function goi(url, tuyChon = {}) {
    const hanGio = tuyChon.hanGio || 20000;
    const method = tuyChon.method || 'GET';

    const headerGoc = {
        'User-Agent': UA,
        Accept: 'application/json, text/plain, */*',
        ...(tuyChon.headers || {}),
    };

    async function motLan(cookie) {
        const headers = { ...headerGoc };

        if (cookie) headers.Cookie = cookie;

        const res = await fetch(url, {
            method,
            headers,
            body: tuyChon.body,
            redirect: 'follow',
            signal: AbortSignal.timeout(hanGio),
        });

        return { res, than: await res.text() };
    }

    try {
        let { res, than } = await motLan(null);

        if (!laLoiDo(res, than)) {
            return { ma: res.status, than, quaTuong: false, loi: null };
        }

        const loiDo = docLoiDo(than);

        if (loiDo === null) {
            return {
                ma: 0,
                than: '',
                quaTuong: false,
                loi: 'Gặp lớp chống bot nhưng KHÔNG đọc được lời đố. '
                   + 'InfinityFree có thể đã đổi cách chặn. Trang nhận được: '
                   + than.slice(0, 300).replace(/\s+/g, ' '),
            };
        }

        const cookie = '__test=' + giaiLoiDo(loiDo);

        ({ res, than } = await motLan(cookie));

        if (laLoiDo(res, than)) {
            /* Giải đúng mà vẫn bị chặn: cookie không được chấp nhận. Thường là
               lời đố đã đổi thuật toán, hoặc IP của Render nằm trong danh sách
               đen. Không thử lần ba — lặp chỉ tốn thêm hạn giờ của SePay. */
            return {
                ma: 0,
                than: '',
                quaTuong: true,
                loi: 'Đã giải lời đố và gửi lại kèm cookie __test, vẫn bị chặn.',
            };
        }

        return { ma: res.status, than, quaTuong: true, loi: null };
    } catch (e) {
        return {
            ma: 0,
            than: '',
            quaTuong: false,
            loi: (e && e.name === 'TimeoutError')
                ? 'Quá hạn giờ ' + hanGio + 'ms khi gọi ' + url
                : 'Lỗi mạng khi gọi ' + url + ': ' + (e && e.message),
        };
    }
}

module.exports = { goi, docLoiDo, giaiLoiDo, UA };
