<?php

/**
 * config/sepay.php — cổng đối soát chuyển khoản SePay (https://sepay.vn).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SEPAY LÀM GÌ, VÀ VÌ SAO NÓ KHÔNG PHẢI "CỔNG THANH TOÁN" THEO NGHĨA THƯỜNG
 *
 * Nó KHÔNG giữ tiền và KHÔNG đứng giữa giao dịch. Khách vẫn chuyển khoản thẳng
 * vào tài khoản ngân hàng của cửa hàng như trước; SePay chỉ ngồi đọc biến động
 * số dư của tài khoản đó rồi BÁO VỀ website qua webhook.
 *
 * Đổi lại đúng một việc, nhưng là việc tốn người nhất: KHÔNG CÒN AI PHẢI NGỒI
 * ĐỐI CHIẾU SAO KÊ. Trước bản này, khách chuyển tiền xong bấm "Tôi đã chuyển
 * khoản" là hết — đơn nằm im ở 'unpaid' cho tới khi một nhân viên mở app ngân
 * hàng, dò xem tiền đã về chưa, rồi vào /quan-tri/don-hang bấm tay. Với đơn cắt
 * tròng thì đó là khoảng chờ nằm ngay giữa "khách trả cọc" và "cửa hàng bắt đầu
 * mài".
 *
 * VÌ THẾ NỘI DUNG CHUYỂN KHOẢN PHẢI LÀ MÃ ĐƠN. Đó là sợi dây duy nhất buộc một
 * dòng tiền vào một đơn hàng — xem chú thích ở config/company.php -> bank.
 * ─────────────────────────────────────────────────────────────────────────────
 * TÀI KHOẢN NHẬN TIỀN KHÔNG KHAI Ở ĐÂY
 *
 * Số tài khoản, tên chủ tài khoản và mã ngân hàng vẫn nằm nguyên ở
 * config/company.php -> 'bank'. Khai lại ở đây là có HAI nguồn cho cùng một sự
 * thật, và ngày đổi tài khoản sẽ có người sửa một chỗ quên chỗ kia — mã QR trỏ
 * một nơi còn webhook nghe một nơi khác.
 *
 * File này chỉ giữ những thứ CỦA RIÊNG SEPAY: bật/tắt và khoá xác thực webhook.
 * ─────────────────────────────────────────────────────────────────────────────
 * CÁCH BẬT — BA BƯỚC, ĐỀU LÀ VIỆC ĐĂNG KÝ CHỨ KHÔNG PHẢI VIỆC VIẾT MÃ
 *
 *   1. Điền tài khoản thật vào config/company.php -> 'bank' (number, holder,
 *      bin, name). Chưa có thì màn QR tự ẩn mã đi — xem ghi chú ở đó.
 *   2. sepay.vn -> đăng ký, liên kết tài khoản ngân hàng của hộ kinh doanh.
 *   3. sepay.vn -> Tích hợp webhook. Điền:
 *        URL       https://<tên-miền>/webhook/sepay
 *        Xác thực  API Key  ->  sinh một chuỗi ngẫu nhiên dài, chép vào
 *                              SEPAY_WEBHOOK_KEY trong .env
 *      Rồi đặt SEPAY_ENABLED=true.
 *
 * Chưa làm xong bước 3 thì webhook TRẢ 403 cho mọi request (xem
 * SepayController::webhook) và mọi thứ chạy y như trước: khách vẫn chuyển khoản
 * được, nhân viên vẫn đối chiếu tay. Không có gì hỏng, chỉ là chưa tự động.
 * ─────────────────────────────────────────────────────────────────────────────
 */

return [
    /*
     * Công tắc chính.
     *
     * TẮT thì webhook từ chối mọi request và giao diện KHÔNG hứa hẹn gì về việc
     * xác nhận tự động — màn QR quay về câu "nhân viên đối chiếu trong giờ làm
     * việc", đúng như trước. Lời hứa "xác nhận sau 1–2 phút" mà không có ai giữ
     * còn tệ hơn là không hứa; xem app/views/order/transfer.php.
     *
     * Cờ này TÁCH khỏi việc có khoá hay không: có thể đã khai khoá để chạy thử
     * mà chưa muốn nói với khách là đã tự động.
     */
    'enabled' => (bool) env('SEPAY_ENABLED', false),

    /*
     * Khoá xác thực webhook — chuỗi ngẫu nhiên do CHÍNH BẠN đặt trên trang cấu
     * hình của SePay, không phải thứ SePay cấp.
     *
     * SePay gửi kèm mỗi request dưới dạng:  Authorization: Apikey <khoá>
     *
     * ⚠️ ĐỂ TRONG .env, KHÔNG COMMIT. Ai biết khoá này là gửi được một tin
     * "đã nhận 5 triệu" giả và đơn tự chuyển sang đã thanh toán.
     *
     * Để TRỐNG = webhook đóng hoàn toàn (403). Đó là mặc định, và là trạng thái
     * đúng cho tới khi có tài khoản thật: một địa chỉ webhook nhận bừa mọi
     * request là một nút "đánh dấu đã trả tiền" mở cho cả internet.
     */
    'webhook_key' => env('SEPAY_WEBHOOK_KEY', ''),

    /*
     * Kiểu ảnh QR của qr.sepay.vn: '' | 'compact' | 'qronly' | 'standee'.
     *
     * 'qronly' = chỉ ô mã, không kèm khung logo ngân hàng. Khung 210px của bản
     * thiết kế đã có viền riêng, chồng thêm khung nữa là mã bị bóp nhỏ và điện
     * thoại khó bắt — cùng lý do đã ghi ở order/transfer.php.
     */
    'qr_template' => env('SEPAY_QR_TEMPLATE', 'qronly'),
];
