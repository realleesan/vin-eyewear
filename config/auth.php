<?php

/**
 * config/auth.php — đăng nhập bằng tài khoản bên ngoài.
 *
 * Giá trị thật nằm trong .env, file này chỉ mô tả cấu trúc nên commit được.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * LẤY HAI GIÁ TRỊ NÀY Ở ĐÂU
 *
 *   1. console.cloud.google.com -> tạo project (hoặc chọn project có sẵn)
 *   2. "APIs & Services" -> "OAuth consent screen": chọn External, điền tên
 *      ứng dụng và email liên hệ. Đang ở chế độ Testing thì CHỈ những tài
 *      khoản khai trong mục "Test users" đăng nhập được — nhớ bấm Publish khi
 *      muốn mở cho khách thật.
 *   3. "Credentials" -> Create credentials -> OAuth client ID -> Web application
 *   4. "Authorized redirect URIs" phải khớp TỪNG KÝ TỰ với giá trị 'redirect'
 *      bên dưới, kể cả http/https và có hay không dấu / ở cuối:
 *
 *          https://vreyewear.gt.tc/auth/google/callback
 *
 *      Chạy ở máy thì thêm một URI nữa cho địa chỉ local. Google cho khai
 *      nhiều URI trong cùng một client, không cần tạo client thứ hai.
 *   5. Chép Client ID và Client secret vào .env.
 *
 * Chưa điền thì nút "Tiếp tục với Google" KHÔNG hiện ra — xem
 * GoogleAuth::isConfigured(). Thà không có nút còn hơn có nút bấm vào ra trang
 * lỗi của Google.
 */

return [
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),

        /*
         * Dựng từ APP_URL chứ không khai riêng một biến nữa: hai giá trị cùng
         * nói về một địa chỉ mà tách làm hai chỗ thì sớm muộn cũng lệch, và
         * lệch ở đây thì Google từ chối với đúng một dòng "redirect_uri_mismatch"
         * không nói rõ bên nào sai.
         */
        'redirect'      => rtrim((string) env('APP_URL', ''), '/') . '/auth/google/callback',
    ],

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * ĐỒNG Ý ĐIỀU KHOẢN KHI ĐĂNG KÝ
     *
     * 'version' được ghi thẳng vào users.terms_version của từng tài khoản mới.
     * ĐỔI GIÁ TRỊ NÀY MỖI KHI SỬA NỘI DUNG văn bản — nếu không, tài khoản đăng
     * ký sau bản sửa sẽ mang cùng một nhãn với tài khoản đăng ký trước đó, và
     * cột kia mất hết ý nghĩa.
     *
     * Dùng ngày ISO làm phiên bản chứ không phải số đếm: đọc phát biết ngay là
     * văn bản của thời điểm nào, không phải đi tra bảng đối chiếu.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * CÒN THIẾU: VĂN BẢN ĐIỀU KHOẢN DỊCH VỤ
     *
     * 'terms_url' để RỖNG vì trang Điều khoản dịch vụ CHƯA TỒN TẠI.
     * config/policy.php hiện chỉ có năm mục: bao-hanh, doi-tra, do-mat,
     * giao-hang, bao-mat — không có 'dieu-khoan'. Ba nơi đang trỏ tới
     * /chinh-sach#dieu-khoan (auth/index.php, _layout/footer.php,
     * _layout/auth-footer.php) nên hiện chỉ nhảy lên đầu trang.
     *
     * Ô tick vì thế CHỈ nói về Chính sách bảo mật — thứ có thật và đọc được.
     * Xin đồng ý cho một văn bản không tồn tại thì tệ hơn là không xin.
     *
     * Khi có nội dung: thêm mục 'dieu-khoan' vào config/policy.php, điền
     * 'terms_url' bên dưới, và ĐỔI 'version'. auth/_signup.php tự thêm vế
     * "Điều khoản dịch vụ" vào câu khi thấy 'terms_url' khác rỗng.
     * ─────────────────────────────────────────────────────────────────────────
     */
    'consent' => [
        'version'     => '2026-08-25',
        'privacy_url' => '/chinh-sach#bao-mat',
        'terms_url'   => '',
    ],
];
