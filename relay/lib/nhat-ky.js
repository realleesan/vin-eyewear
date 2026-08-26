/**
 * nhat-ky.js — ghi log ra stdout/stderr, có mốc giờ và có che bí mật.
 *
 * Render giữ log của tiến trình và cho xem trên bảng điều khiển; đó là thứ duy
 * nhất còn lại để lần khi webhook im lặng. Nên mỗi dòng phải tự nói đủ: giờ
 * nào, việc gì, giao dịch số mấy.
 *
 * CHE BÍ MẬT là bắt buộc chứ không phải cẩn thận thừa: header Authorization đi
 * qua đây trong mọi request, và một dòng log lỡ in nguyên khoá ra thì khoá đó
 * coi như đã lộ — bảng điều khiển Render chia sẻ được, ảnh chụp màn hình gửi
 * cho nhau được. Cùng bài học đã ghi ở đầu kiem-tra-sepay.php.
 */

'use strict';

const crypto = require('node:crypto');

function gio() {
    /* Giờ Việt Nam, vì người đọc log là người đang ngồi ở cửa hàng đối chiếu
       với app ngân hàng — chứ không phải máy. */
    return new Date().toLocaleString('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh' });
}

/** Vân tay 8 ký tự để SO hai khoá mà không phải in ra khoá nào. */
function vanTay(chuoi) {
    if (!chuoi) return '(trống)';

    return crypto.createHash('sha256').update(String(chuoi)).digest('hex').slice(0, 8);
}

module.exports = {
    tin(...phan)  { console.log('[' + gio() + ']', ...phan); },
    loi(...phan)  { console.error('[' + gio() + '] ⚠', ...phan); },
    vanTay,
};
