/**
 * cau-hinh.js — đọc toàn bộ cấu hình từ biến môi trường, MỘT LẦN, lúc khởi động.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO ĐỌC MỘT LẦN CHỨ KHÔNG process.env RẢI KHẮP NƠI
 *
 * Cầu nối này giữ hai khoá bí mật khác nhau và ba địa chỉ. Đọc rải rác thì gõ
 * sai một tên biến (SITE_WEBHOOK_KEY vs SITE_WEBHOOK_KEY_) sẽ thành chuỗi rỗng
 * lặng lẽ, và triệu chứng là "webhook trả 401 mà khoá hai bên giống hệt nhau" —
 * đúng cái lỗi đã mất nửa ngày ở phía website (xem .htaccess mục 2).
 *
 * Gom về đây thì lúc khởi động in được một bảng tổng kết, và thiếu thứ bắt buộc
 * là chết ngay chứ không chết vào lúc có tiền thật đi qua.
 * ─────────────────────────────────────────────────────────────────────────────
 */

'use strict';

function chuoi(ten, macDinh = '') {
    const v = process.env[ten];

    return typeof v === 'string' ? v.trim() : macDinh;
}

function co(ten, macDinh = false) {
    const v = chuoi(ten).toLowerCase();

    if (v === '') return macDinh;

    return v === '1' || v === 'true' || v === 'yes' || v === 'on';
}

function so(ten, macDinh) {
    const v = Number.parseInt(chuoi(ten), 10);

    return Number.isFinite(v) && v > 0 ? v : macDinh;
}

const cauHinh = {
    cong: so('PORT', 10000),

    /* Khoá SePay gửi kèm: `Authorization: Apikey <khoá>`. Chính là chuỗi tự đặt
       trên my.sepay.vn. Để trống = cầu nối từ chối mọi request (403), giống hệt
       cách website xử sự khi chưa khai khoá. Một địa chỉ webhook nhận bừa là một
       nút "đánh dấu đã trả tiền" mở cho cả internet. */
    khoaSepay: chuoi('SEPAY_WEBHOOK_KEY'),

    /* Địa chỉ webhook THẬT trên InfinityFree, ví dụ:
       https://vreyewear.gt.tc/webhook/sepay */
    urlSite: chuoi('SITE_WEBHOOK_URL'),

    /* Khoá website đợi (SEPAY_WEBHOOK_KEY trong .env trên hosting).
       NÊN KHÁC khoaSepay: hai chặng, hai khoá, lộ chặng này không mở được chặng
       kia. Để trống thì dùng lại khoaSepay — tiện lúc chạy thử, không nên để lâu. */
    khoaSite: chuoi('SITE_WEBHOOK_KEY') || chuoi('SEPAY_WEBHOOK_KEY'),

    /* Khoá website dùng để KÉO hàng đợi về (chiều ngược lại). Để trống = tắt
       hẳn đường kéo, chỉ còn đường đẩy. */
    khoaKeo: chuoi('PULL_KEY'),

    /* Nơi ghi hàng đợi xuống đĩa. Trên gói miễn phí của Render đĩa là tạm:
       khởi động lại là mất. Xem chú thích đầu hang-doi.js. */
    thuMucDuLieu: chuoi('DATA_DIR', './data'),

    /*
     * ĐẨY HỎNG THÌ TRẢ MÃ NÀO CHO SEPAY?
     *
     *   false (mặc định) -> trả 500. SePay coi là thất bại và GỬI LẠI tối đa 7
     *                       lần trong 5 giờ. Đó là một tầng bền vững miễn phí,
     *                       không cần đĩa, không cần CSDL. Đổi lại: nhật ký
     *                       webhook bên my.sepay.vn đỏ, dù tiền vẫn về đúng đơn
     *                       qua đường kéo.
     *   true             -> trả 200 ngay khi đã xếp được vào hàng đợi. Nhật ký
     *                       bên SePay xanh, nhưng nếu tiến trình chết trước khi
     *                       website kéo kịp thì giao dịch đó mất hẳn — SePay
     *                       không gửi lại thứ nó tưởng đã giao xong.
     *
     * Chỉ bật true khi hàng đợi đã nằm trên đĩa THẬT (Render Disk trả phí) hoặc
     * khi chấp nhận rủi ro mất một giao dịch để đổi lấy nhật ký sạch.
     */
    ackKhiXepHang: co('QUEUE_ACK', false),

    /*
     * Danh sách IP của SePay, ngăn cách bằng dấu phẩy. Để trống = không lọc.
     *
     * Đây là lớp phụ, KHÔNG phải lớp chính — khoá mới là thứ giữ cửa. Lọc IP
     * dễ thành thứ tự bắn vào chân: SePay đổi hạ tầng là webhook chết câm mà
     * không ai biết vì sao. Nên mặc định tắt; bật thì nhớ mục này khi debug.
     *
     * Sáu IP tính tới 2026-08 (chép từ kiem-tra-sepay.php):
     *   172.236.138.20, 172.233.83.68, 171.244.35.2,
     *   151.158.108.68, 151.158.109.79, 103.255.238.139
     */
    ipSepay: chuoi('SEPAY_IPS').split(',').map((s) => s.trim()).filter(Boolean),

    /* Số giao dịch tối đa giữ trong hàng đợi. Chạm trần thì bỏ cái CŨ NHẤT —
       xem hang-doi.js. */
    tranHangDoi: so('QUEUE_MAX', 500),

    /* Giây chờ tối đa cho mỗi lần gọi sang InfinityFree. Hosting miễn phí lúc
       tải nặng trả lời rất chậm, nhưng SePay cũng có hạn giờ của nó — chờ quá
       lâu thì cả hai đầu cùng bỏ cuộc. */
    hanGioSite: so('SITE_TIMEOUT', 20) * 1000,
};

/** Thiếu thứ nào thì không chạy được? Trả về danh sách để in ra rồi thoát. */
cauHinh.thieu = function thieu() {
    const ds = [];

    if (cauHinh.khoaSepay === '') ds.push('SEPAY_WEBHOOK_KEY');
    if (cauHinh.urlSite === '')   ds.push('SITE_WEBHOOK_URL');

    return ds;
};

module.exports = cauHinh;
