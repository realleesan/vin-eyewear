<?php

/**
 * config/app.php
 *
 * Cấu hình chung của ứng dụng. Giá trị thật lấy từ .env.
 */

return [
    'name'     => 'Vin Eyewear',
    'env'      => env('APP_ENV', 'production'),

    // Mặc định TẮT gỡ lỗi. Đặt mặc định là true sẽ khiến một server thật quên
    // tạo .env vô tình phơi cả stack trace lẫn thông số kết nối DB ra ngoài.
    'debug'    => env('APP_DEBUG', false),

    'url'      => env('APP_URL', 'http://localhost:8000'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),

    // Thời gian sống cookie phiên, tính bằng giây. Mặc định 14 ngày.
    'session_lifetime' => (int) env('SESSION_LIFETIME', 1209600),

    // Số sản phẩm mỗi trang ở danh sách sản phẩm.
    'per_page' => 12,

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * SONG NGỮ — MÃ WIDGET ELFSIGHT WEBSITE TRANSLATOR
     *
     * ĐỂ TRỐNG = KHÔNG NHÚNG GÌ CẢ. Không thẻ <script>, không thẻ <div>, không
     * một request nào ra static.elfsight.com. Site chạy y như trước, chỉ là
     * thuần tiếng Việt. Đây là mặc định có chủ ý: một máy dev quên khai .env
     * thì không được phép tự đi gọi dịch vụ ngoài.
     *
     * LẤY MÃ Ở ĐÂU: elfsight.com -> tạo widget "Website Translator" -> mã nhúng
     * họ sinh ra có dạng
     *     <div class="elfsight-app-1ac7608f-e549-4325-bed8-08f5b5..."></div>
     * Chép ĐÚNG phần sau "elfsight-app-" (chuỗi UUID), không chép cả class.
     *
     * Trong trình soạn của họ chọn kiểu hiển thị INLINE, không phải nổi: thẻ
     * div nằm trong cụm nút của thanh nav (xem _layout/translator.php).
     *
     * VÌ SAO NẰM Ở .env CHỨ KHÔNG GÕ THẲNG VÀO VIEW: mã này gắn với MỘT tài
     * khoản Elfsight và MỘT tên miền. Máy dev, máy thử và hosting thật không
     * nên cùng đốt vào một hạn mức lượt xem — gói miễn phí chỉ có 200 lượt/tháng
     * và vượt là widget bị tắt tạm. Để trống trên máy dev là cách rẻ nhất.
     *
     * ĐÂY KHÔNG PHẢI BÍ MẬT. Mã widget hiện nguyên trong HTML mà ai xem nguồn
     * cũng đọc được; nó ở .env vì khác nhau theo môi trường, không phải vì cần
     * giấu. Đừng đối xử với nó như SEPAY_WEBHOOK_KEY.
     * ─────────────────────────────────────────────────────────────────────────
     */
    'elfsight_translator' => env('ELFSIGHT_WIDGET_ID', ''),

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * MỐC TÍNH DOANH THU — bảng Tổng quan chỉ cộng tiền của đơn đặt TỪ mốc này.
     *
     * VÌ SAO CẦN: cửa hàng chạy thử nhiều tháng trước khi mở thật, và đơn thử
     * nằm lẫn trong cùng một bảng với đơn thật. Riêng đơn COD thì chỉ cần bấm
     * sang "Hoàn tất" là OrderModel::changeStatus() tự đánh dấu đã thu tiền —
     * nên vài lần bấm thử đủ dựng ra một con số doanh thu không có thật.
     *
     * CHỌN MỐC THAY VÌ XOÁ ĐƠN, và đây là điểm chính:
     *
     *   · Không mất gì. Đơn cũ vẫn nằm nguyên ở /quan-tri/don-hang, vẫn tra
     *     cứu được, vẫn gắn với lịch sử mua của khách trong module Khách hàng.
     *   · Đảo ngược được. Đặt sai mốc thì sửa một dòng .env, không phải khôi
     *     phục từ bản sao lưu.
     *   · Không có lệnh DELETE nào chạy trên máy chủ thật — thao tác mà một
     *     ký tự gõ thiếu là mất vĩnh viễn lịch sử mua của khách.
     *
     * ĐỂ TRỐNG = tính toàn bộ dữ liệu, đúng như trước khi có tuỳ chọn này.
     *
     * Định dạng: 'YYYY-MM-DD' hoặc 'YYYY-MM-DD HH:MM:SS'. Chỉ ngày thì tính từ
     * 00:00:00 hôm đó.
     *
     * LỌC THEO `created_at` CHỨ KHÔNG PHẢI `paid_at` — một luật cho cả doanh
     * thu lẫn tạm thu, và nó đọc ra được thành một câu: "đơn đặt từ ngày X trở
     * đi". Đánh đổi có thật và phải biết: một đơn đặt TRƯỚC mốc mà khách trả
     * tiền SAU mốc thì không vào doanh thu. Đó là cái giá của việc bắt đầu lại
     * từ 0 — tiền ấy vẫn thấy đủ ở module Đơn hàng.
     *
     * CHỈ ÁP CHO HAI Ô TIỀN. Các ô hàng chờ (đơn mới, lịch hẹn chờ, liên hệ
     * chưa đẩy) KHÔNG lọc theo mốc: chúng đếm VIỆC CÒN PHẢI LÀM, và một đơn từ
     * tháng trước chưa ai xác nhận thì vẫn là việc chưa xong. Giấu nó đi vì
     * ngày tháng là giấu mất một khách đang chờ.
     * ─────────────────────────────────────────────────────────────────────────
     */
    'thong_ke_tu' => env('STATS_SINCE', ''),

    // Phí giao hàng cố định (VND) khi khách chọn giao tận nơi thay vì nhận
    // tại cửa hàng. Đặt ở đây để đổi một chỗ, không rải rác trong checkout.
    'shipping_fee' => 30000,

    // Đơn từ mức này trở lên được miễn phí giao hàng.
    // Khớp quy tắc trong createOrder của bản Lovable (shop.functions.ts):
    //   subtotal < 1.000.000đ  ->  thu 30.000đ
    'free_shipping_threshold' => 1000000,

    /*
     * TỶ LỆ ĐẶT CỌC, tính theo phần trăm tổng đơn.
     *
     * CHỈ áp cho đơn có CẮT TRÒNG THEO ĐỘ. Tròng mài riêng theo số đo của một
     * người thì không bán lại cho ai khác được, nên khách phải cọc trước —
     * cho cả COD lẫn chuyển khoản. Đơn chỉ mua gọng (gọng kèm tròng demo chưa
     * cắt độ) không cọc đồng nào.
     *
     * Tính trên TỔNG ĐƠN CUỐI CÙNG (tạm tính − giảm giá + phí ship), không
     * phải trên tiền hàng: nhờ vậy "cọc + còn lại = tổng cộng" đúng bằng phép
     * cộng, và ba con số trên trang thanh toán khớp nhau mà không cần chú
     * thích nào.
     *
     * Đổi số ở đây KHÔNG đổi tiền cọc của đơn đã đặt: số tiền được chốt vào
     * `orders.deposit_amount` lúc ghi đơn — xem OrderModel::place().
     */
    'deposit_rate' => 30,

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * ĐÃ BỎ: 'time_slots' và 'booking_change_cutoff_hours' — 2026-08-25
     *
     * Ghi lại ở đây vì cả hai đều phục vụ GIẢ ĐỊNH A5 trong CLAUDE.md, mà A5
     * chưa được BA nghiệm thu. Đảo lại quyết định thì đây là chỗ đầu tiên phải
     * dựng lại, nên để trống mà không nói gì là bắt người sau tự đi tìm.
     *
     *   'time_slots' — mười một khung giờ nhận lịch: 08:00–11:00 và 14:00–20:00.
     *      Khách không chọn giờ nữa nên không còn ai đọc mảng này.
     *
     *   'booking_change_cutoff_hours' => 2 — khách tự đổi/huỷ được tới trước
     *      GIỜ hẹn 2 tiếng. Con số ấy cần một mốc chính xác tới phút để trừ đi,
     *      mà mốc đó dựng từ ngày ghép với khung giờ. Không còn khung giờ thì
     *      không còn gì để trừ: luật nay là "sang tới ngày hẹn thì gọi tổng
     *      đài", một câu không có tham số nào. Xem BookingModel::changeBlocker.
     * ─────────────────────────────────────────────────────────────────────────
     */
];
