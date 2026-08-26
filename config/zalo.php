<?php

/**
 * config/zalo.php
 *
 * Đẩy thông báo lịch hẹn qua Zalo — xem core/Zalo.php để biết luồng và giới hạn.
 *
 * File này khai BỐN việc đi chung một đường ZNS:
 *   · thông báo lịch hẹn cho cửa hàng
 *   · thông báo đơn hàng mới cho cửa hàng
 *   · yêu cầu liên hệ cho CSKH
 *   · mã OTP lúc khách đăng ký / quên mật khẩu
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * LẤY BỐN GIÁ TRỊ BẮT BUỘC Ở ĐÂU
 *
 *   1. developers.zalo.me -> tạo ứng dụng. Mục "Thông tin ứng dụng" cho
 *      App ID và Secret Key  ->  ZALO_APP_ID, ZALO_APP_SECRET
 *   2. Gắn Official Account đã xác thực vào ứng dụng, bật quyền "Gửi ZNS".
 *   3. Vào "Official Account" -> "Cấp quyền cho ứng dụng", đăng nhập bằng tài
 *      khoản quản trị OA. Zalo trả về một `oauth_code` trên URL; đổi mã đó lấy
 *      cặp token đầu tiên (một lần duy nhất, bằng tay hoặc bằng công cụ của
 *      Zalo). Chép refresh_token vào  ->  ZALO_OA_REFRESH_TOKEN
 *   4. zns.zalo.me -> "Quản lý mẫu tin". Mẫu OTP có sẵn mẫu dựng trước, duyệt
 *      nhanh nhất. Chép ID mẫu vào  ->  ZALO_ZNS_TEMPLATE_OTP
 *
 * Thiếu bất kỳ thứ nào thì tin chỉ ghi ra error log. Lịch hẹn vẫn đặt được và
 * quên mật khẩu vẫn rơi về hàng chờ của nhân viên — nhưng ĐĂNG KÝ THÌ TẮC,
 * xem khối chú thích đầu core/Zalo.php.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * VÌ SAO SỐ CỬA HÀNG Ở ĐÂY MÀ KHÔNG Ở config/company.php: company.php là thông
 * tin CÔNG KHAI, in ra chân trang và trang liên hệ. Số này là ĐỊA CHỈ NHẬN
 * THÔNG BÁO NỘI BỘ — nó có thể là số của máy trực, không nhất thiết là số khách
 * nhìn thấy. Trộn hai thứ thì đổi số trực một cái là đổi luôn số in trên web.
 */

return [
    /*
     * Số Zalo của cửa hàng — nơi nhận mọi thông báo lịch hẹn.
     *
     * Nhận đủ dạng "0366 599 711", "+84366599711", "84366599711":
     * Zalo::normalize() đưa hết về 0xxxxxxxxx qua normalizePhone().
     *
     * Muốn TẮT hẳn việc báo cho cửa hàng thì xoá con số mặc định ngay dưới đây,
     * không phải để trống biến trong .env: env() quy ước một biến rỗng nghĩa là
     * "không khai" và trả về đúng giá trị mặc định này. Khi không còn số nào,
     * Zalo::shopPhone() ghi một dòng vào error log rồi thôi — thà vậy còn hơn
     * lặng lẽ gửi vào hư không.
     */
    'shop_phone' => env('ZALO_SHOP_PHONE', '0366599711'),

    /*
     * Có gửi cho CHÍNH KHÁCH nữa không?
     *
     * Yêu cầu của cửa hàng ghi "và gửi cả cho Zalo của khách hàng nếu được" —
     * chữ "nếu được" là có lý do: ZNS gửi cho khách tính phí theo từng tin và
     * cần một mẫu riêng đã duyệt, khác mẫu gửi nội bộ. Nên mặc định TẮT, bật
     * khi đã đăng ký xong mẫu đó và chấp nhận chi phí.
     *
     * Bật khi chưa cắm nhà cung cấp cũng không hại gì: tin chỉ đi vào log.
     */
    'notify_customer' => (bool) env('ZALO_NOTIFY_CUSTOMER', false),

    /*
     * ỨNG DỤNG trên developers.zalo.me — dùng để làm mới token.
     *
     * ⚠️ ĐỂ TRONG .env, KHÔNG BAO GIỜ COMMIT. Secret key cộng refresh_token là
     * đủ để gửi tin dưới danh nghĩa cửa hàng.
     */
    'app_id'     => env('ZALO_APP_ID', ''),
    'secret_key' => env('ZALO_APP_SECRET', ''),

    /*
     * refresh_token LẦN ĐẦU, lấy tay một lần qua màn hình cấp quyền OA.
     *
     * Chỉ dùng khi chưa có file token. Từ lần làm mới đầu tiên trở đi, Zalo
     * cấp refresh_token MỚI và cái cũ chết ngay — cặp mới nằm trong 'token_file'
     * bên dưới, còn giá trị ở đây đứng im và dần vô dụng. Đó là chủ ý: .env do
     * người triển khai giữ, ứng dụng không tự ghi vào.
     */
    'refresh_token' => env('ZALO_OA_REFRESH_TOKEN', ''),

    /*
     * Chỗ cất cặp token đang dùng.
     *
     * Dưới storage/ vì thư mục đó đã nằm trong .gitignore và là nơi duy nhất
     * ứng dụng được phép ghi. File tự sinh ở lần làm mới đầu tiên, quyền 0600.
     * Xoá nó đi là quay về dùng lại 'refresh_token' ở trên.
     */
    'token_file' => ROOT_PATH . '/storage/zalo/token.json',

    /*
     * ĐƯỜNG LUI CHO LÚC THỬ NGHIỆM: một access_token còn hạn, khai tay.
     *
     * Gửi thử được vài tin rồi thôi — token sống khoảng 25 giờ và không có gì
     * làm mới nó. Đừng dùng trên production; khai đủ bốn giá trị ở trên thì
     * ứng dụng tự lo phần này.
     */
    'access_token' => env('ZALO_OA_ACCESS_TOKEN', ''),

    /*
     * Mã mẫu tin ZNS đã được Zalo duyệt.
     *
     * Một mẫu cho tin báo nội bộ (gửi cửa hàng), một cho tin gửi khách nếu bật
     * 'notify_customer'. Cả hai là mẫu TỰ SOẠN, nên tên các ô do chính người đi
     * đăng ký đặt: đặt đúng bảy tên mà Zalo::appointmentParams() đang gửi
     * (su_kien, ma_lich, khach_hang, dien_thoai, co_so, dich_vu, thoi_gian) thì
     * không phải sửa mã; đặt khác thì sửa ở đúng hàm đó.
     */
    'template_shop'     => env('ZALO_ZNS_TEMPLATE_SHOP', ''),
    'template_customer' => env('ZALO_ZNS_TEMPLATE_CUSTOMER', ''),

    /*
     * Mẫu tin ĐƠN HÀNG MỚI — gửi cửa hàng, không gửi khách.
     *
     * Đây không phải tiện ích báo tin cho vui: website CỐ Ý không có nút huỷ
     * đơn (cửa hàng tự giao, không đồng bộ trạng thái vận chuyển thời gian
     * thực), nên khách muốn huỷ phải nhắn Zalo cho nhân viên. Tin này là thứ
     * làm cho cuộc trò chuyện đó có chỗ bắt đầu — xem Zalo::order().
     *
     * Bảy ô: ma_don, khach_hang, dien_thoai, nhan_hang, thanh_toan, tong_tien,
     * san_pham. Đặt đúng bảy tên đó lúc soạn mẫu thì không phải sửa mã.
     *
     * ĐỂ TRỐNG CŨNG KHÔNG SAO: đơn vẫn đặt được bình thường và tin báo rơi
     * xuống error log nguyên văn, nhân viên vẫn thấy đơn ở /quan-tri/don-hang.
     */
    'template_order' => env('ZALO_ZNS_TEMPLATE_ORDER', ''),

    /*
     * Mẫu tin YÊU CẦU LIÊN HỆ — gửi CSKH.
     *
     * Bốn ô: khach_hang, dien_thoai, email, noi_dung. Đặt đúng bốn tên đó lúc
     * soạn mẫu thì không phải sửa mã; đặt khác thì sửa ở Zalo::contactParams().
     *
     * ⚠️ ĐỂ TRỐNG CÓ HẬU QUẢ KHÁC HẲN ba mẫu trên. Lịch hẹn, đơn hàng và OTP
     * đều còn đường lui: lịch và đơn nằm trong khu quản trị với hàng chờ riêng
     * để nhân viên mở ra xem, còn quên mật khẩu rơi về /quan-tri/quen-mat-khau.
     *
     * Yêu cầu liên hệ thì KHÔNG. Từ 2026-08-26 trang /quan-tri/lien-he bỏ cột
     * trạng thái và thành sổ lưu trữ thuần — không ai ngồi canh nó nữa. Chưa
     * khai mẫu này nghĩa là mọi yêu cầu chỉ nằm trong error log.
     *
     * Cái đỡ cho khoảng đó là cột `zalo_sent_at`: chưa gửi được thì huy hiệu
     * "Liên hệ" trên thanh bên sáng lên với đúng số yêu cầu chưa tới tay ai,
     * và trang quản trị có nút "Gửi sang Zalo" để đẩy lại từng cái. Nhưng đó
     * là lưới an toàn, không phải cách vận hành — khai mẫu này sớm.
     */
    'template_contact' => env('ZALO_ZNS_TEMPLATE_CONTACT', ''),

    /*
     * Số Zalo của CSKH — nơi nhận yêu cầu liên hệ.
     *
     * ĐỂ TRỐNG THÌ RƠI VỀ 'shop_phone' ở đầu file, không phải tắt tính năng.
     * Cửa hàng một cơ sở thì hai số là một, và bắt khai hai lần cùng một con
     * số là cách chắc chắn để một trong hai bị khai sai rồi không ai nhận ra.
     *
     * Tách ra khi bộ phận CSKH có máy trực riêng: lúc đó thông báo lịch hẹn và
     * đơn hàng vẫn về máy quầy, còn câu hỏi của khách về máy CSKH.
     */
    'cskh_phone' => env('ZALO_CSKH_PHONE', ''),

    /*
     * Mẫu tin OTP — thứ chặn giữa "khách đăng ký được" và "không".
     *
     * Ưu tiên khai cái này trước hai mẫu trên: Zalo có mẫu OTP dựng sẵn, duyệt
     * nhanh; mẫu lịch hẹn phải tự soạn nên chờ lâu hơn.
     */
    'template_otp' => env('ZALO_ZNS_TEMPLATE_OTP', ''),

    /*
     * Tên ô chứa mã trong mẫu OTP.
     *
     * Mẫu dựng sẵn của Zalo đặt tên `otp`. Nếu mẫu được duyệt của bạn đặt tên
     * khác thì sửa ở đây — sai tên thì Zalo từ chối cả tin chứ không bỏ qua
     * một ô, và thông báo lỗi không nói ra ô nào sai.
     */
    'otp_param' => env('ZALO_ZNS_OTP_PARAM', 'otp'),
];
