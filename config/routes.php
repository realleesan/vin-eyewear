<?php

/**
 * config/routes.php
 *
 * Bảng ánh xạ URL -> Controller@Action.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐƯỜNG DẪN TIẾNG VIỆT
 *
 * Bản Lovable dùng URL tiếng Việt (/san-pham, /su-kien, /gioi-thieu…), bản PHP
 * cũ dùng tiếng Anh (/product, /event, /about…). Port frontend thì lấy theo
 * Lovable, vì hai lý do:
 *
 *   1. Toàn bộ liên kết trong 22 trang nguồn đều trỏ URL tiếng Việt. Giữ URL
 *      tiếng Anh nghĩa là phải dịch tay từng liên kết ở mỗi trang port sang —
 *      hàng trăm chỗ, sai một chỗ là link chết mà không ai biết.
 *   2. Site phục vụ người Việt; URL tiếng Việt có dấu gạch nối đọc được và
 *      tốt hơn cho tìm kiếm.
 *
 * URL tiếng Anh cũ KHÔNG bị bỏ: xem mảng 'redirects' bên dưới, chúng trả 301
 * về URL mới nên link đã chia sẻ hay đã được lập chỉ mục vẫn dùng được.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Cú pháp:
 *   'duong-dan'          => 'Controller@action'    — khớp chính xác
 *   'san-pham/{slug}'    => 'Controller@action'    — {slug} truyền vào action
 *   'duong-dan-cu'       => 'redirect:/duong-dan'  — chuyển hướng 301
 */

return [

    // -----------------------------------------------------------------------
    // TRANG CÔNG KHAI — đã có controller, chạy được
    // -----------------------------------------------------------------------
    ''                 => 'HomeController@index',
    '/'                => 'HomeController@index',

    // Đổi ngôn ngữ giao diện rồi trả về đúng trang đang đọc. GET chứ không
    // phải POST — lý do ghi ở đầu app/controllers/LangController.php.
    'ngon-ngu'         => 'LangController@switch',

    // Tìm kiếm toàn site: sản phẩm + bài viết + cơ sở + chính sách.
    // Ô tìm kiếm trên đầu trang trỏ vào đây, KHÔNG còn trỏ /san-pham?q=.
    'tim-kiem'         => 'SearchController@index',

    'san-pham'         => 'ProductController@index',
    // Đặt TRƯỚC 'san-pham/{slug}': router khớp theo thứ tự khai, để sau thì
    // 'danh-gia' bị hiểu thành slug của một sản phẩm.
    'san-pham/danh-gia' => 'ProductDetailController@review',   // POST
    'san-pham/{slug}'  => 'ProductDetailController@show',

    'su-kien'          => 'EventController@index',
    'su-kien/{slug}'   => 'EventDetailController@show',

    'gioi-thieu'       => 'AboutController@index',
    'lien-he'          => 'ContactController@index',
    'lien-he/gui'      => 'ContactController@submit',   // form liên hệ (POST)
    'thu-ar'           => 'ArController@tryon',

    // -----------------------------------------------------------------------
    // TRANG CÓ TRẠNG THÁI — controller còn RỖNG, sẽ port ở bước sau.
    //
    // Khai sẵn ở đây để liên kết trong header/footer trỏ đúng chỗ ngay từ
    // giờ. Router trả 404 khi file controller rỗng (class chưa tồn tại), nên
    // các đường này tạm thời 404 chứ không gây lỗi 500.
    // -----------------------------------------------------------------------
    'chinh-sach'       => 'PolicyController@index',
    'dat-lich'         => 'BookingController@index',
    'dat-lich/gui'     => 'BookingController@submit',    // form đặt lịch (POST)

    'nhan-tin/dang-ky' => 'NewsletterController@submit', // đăng ký nhận tin (POST)

    // Giỏ hàng — lưu trong session, chưa chạm DB cho tới lúc đặt hàng.
    // Bốn đường dưới chỉ nhận POST (CartController tự chặn GET).
    // 'gio-hang/sua' nhận CẢ BỐN nút của một dòng (tick · − · + · thùng rác),
    // phân biệt bằng trường `act` — HTML không cho lồng <form>, mà bản thiết
    // kế đặt cả bốn nút trong cùng một hàng. Xem CartController::update().
    'gio-hang'            => 'CartController@index',
    'gio-hang/them'       => 'CartController@add',
    'gio-hang/sua'        => 'CartController@update',
    'gio-hang/chon-tat-ca'=> 'CartController@toggleAll',
    'gio-hang/xoa-chon'   => 'CartController@removeSelected',
    // Một bước của hộp thoại "Chọn hình thức mua" (POST). Năm bước dùng chung
    // một đường, phân biệt bằng trường `buoc` — xem CartController::buyStep().
    'gio-hang/chon'       => 'CartController@buyStep',
    'gio-hang/ma'         => 'CartController@voucher',
    'gio-hang/xoa-het'    => 'CartController@clear',

    'thanh-toan'          => 'OrderController@checkout',
    'thanh-toan/dat'      => 'OrderController@place',    // đặt hàng (POST)
    // Chọn/gỡ mã giảm giá NGAY TRONG form thanh toán (POST). Không dùng lại
    // 'gio-hang/ma' vì đường đó luôn quay về giỏ hàng và chỉ nhận mỗi ô `code`
    // — xem OrderController::voucher().
    'thanh-toan/ma'       => 'OrderController@voucher',
    // Màn "Thanh toán QR" của bản thiết kế — chỉ đơn chuyển khoản đi qua.
    'thanh-toan/chuyen-khoan' => 'OrderController@transfer',
    'thanh-toan/hoan-tat' => 'OrderController@success',
    // Đăng nhập / đăng ký / tài khoản
    'auth'              => 'AuthController@index',
    'auth/dang-nhap'    => 'AuthController@login',      // POST
    'auth/dang-ky'      => 'AuthController@register',   // POST
    'auth/dang-xuat'    => 'AuthController@logout',     // POST
    // Đăng nhập/đăng ký bằng Google. Cả hai là GET: chúng phải chạy khi không
    // có JavaScript, và địa chỉ callback do Google gọi tới nên không thể là POST.
    'auth/google'          => 'AuthController@googleStart',
    'auth/google/callback' => 'AuthController@googleCallback',
    // Trang tài khoản dựng theo "Vin Eyewear Account.dc.html": SÁU mục nằm
    // trên CÙNG một đường dẫn, chọn bằng ?muc=... (ho-so · dia-chi · mat-khau
    // · don-hang · do-mat · lich-hen). Không tách thành năm route vì
    // cột điều hướng bên trái phải hiện y hệt nhau ở cả sáu — tách ra là sáu
    // action chỉ khác nhau đúng một biến.
    'tai-khoan'         => 'AuthController@profile',
    'tai-khoan/ho-so'   => 'AuthController@updateProfile',      // POST
    'tai-khoan/khuc-xa' => 'AuthController@updatePrescription', // POST
    'tai-khoan/mat-khau'=> 'AuthController@changePassword',     // POST
    'tai-khoan/anh'     => 'AuthController@updateAvatar',       // POST (multipart)
    'tai-khoan/mua-lai' => 'AuthController@reorder',            // POST

    // Sổ địa chỉ. Cả ba đều POST: xoá và đổi mặc định qua GET nghĩa là một
    // thẻ <img src="/tai-khoan/dia-chi/xoa?id=..."> trên trang khác cũng xoá
    // được địa chỉ của khách đang đăng nhập.
    'tai-khoan/dia-chi/luu'      => 'AuthController@saveAddress',       // POST
    'tai-khoan/dia-chi/xoa'      => 'AuthController@deleteAddress',     // POST
    'tai-khoan/dia-chi/mac-dinh' => 'AuthController@setDefaultAddress', // POST

    // Khách tự đổi / huỷ lịch hẹn. Cả hai POST, cùng lý do như sổ địa chỉ: huỷ
    // lịch qua GET nghĩa là một thẻ <img src="/tai-khoan/lich-hen/huy?ma=...">
    // trên trang khác cũng huỷ được lịch của khách đang đăng nhập.
    // Form CHỌN giờ mới thì mở bằng ?doi=<mã> trên chính /tai-khoan — xem
    // app/views/auth/account/lich-hen.php.
    'tai-khoan/lich-hen/doi' => 'AuthController@rescheduleBooking', // POST
    'tai-khoan/lich-hen/huy' => 'AuthController@cancelBooking',     // POST

    // Quên mật khẩu. Hai bước tách riêng vì bước hai tới từ một liên kết
    // trong email — phải mở được bằng GET, không có phiên nào cả.
    'quen-mat-khau'         => 'AuthController@forgot',
    'quen-mat-khau/gui'     => 'AuthController@forgotSubmit',   // POST
    'dat-lai-mat-khau'      => 'AuthController@reset',
    'dat-lai-mat-khau/luu'  => 'AuthController@resetSubmit',    // POST

    // -----------------------------------------------------------------------
    // KHU QUẢN TRỊ — controller còn RỖNG, sẽ port ở bước sau
    // -----------------------------------------------------------------------
    'quan-tri'                    => 'DashboardController@index',

    'quan-tri/don-hang'           => 'OrderAdminController@index',
    'quan-tri/don-hang/trang-thai'=> 'OrderAdminController@updateStatus',       // POST
    // Ghi nhận đã nhận tiền (đối chiếu sao kê cho đơn chuyển khoản). Tách khỏi
    // trang-thai vì trạng thái GIAO VẬN và trạng thái TIỀN là hai trục khác
    // nhau — xem OrderModel::PAYMENT_STATUSES.
    'quan-tri/don-hang/thanh-toan'=> 'OrderAdminController@updatePayment',      // POST

    'quan-tri/lich-hen'           => 'AppointmentAdminController@index',
    'quan-tri/lich-hen/trang-thai'=> 'AppointmentAdminController@updateStatus', // POST

    'quan-tri/lien-he'            => 'ContactAdminController@index',
    'quan-tri/lien-he/trang-thai' => 'ContactAdminController@updateStatus',     // POST

    'quan-tri/ton-kho'            => 'InventoryAdminController@index',
    'quan-tri/ton-kho/cap-nhat'   => 'InventoryAdminController@updateStock',    // POST

    'quan-tri/san-pham'     => 'ProductAdminController@index',
    'quan-tri/san-pham/luu' => 'ProductAdminController@save',      // POST
    'quan-tri/san-pham/xoa' => 'ProductAdminController@delete',    // POST

    'quan-tri/danh-muc'     => 'CategoryAdminController@index',
    'quan-tri/danh-muc/luu' => 'CategoryAdminController@save',     // POST
    'quan-tri/danh-muc/xoa' => 'CategoryAdminController@delete',   // POST

    'quan-tri/su-kien'      => 'EventAdminController@index',
    'quan-tri/su-kien/luu'  => 'EventAdminController@save',        // POST
    'quan-tri/su-kien/xoa'  => 'EventAdminController@delete',      // POST

    'quan-tri/bien-the'     => 'VariantAdminController@index',
    'quan-tri/bien-the/luu' => 'VariantAdminController@save',    // POST
    'quan-tri/bien-the/xoa' => 'VariantAdminController@delete',  // POST

    'quan-tri/danh-gia'     => 'ReviewAdminController@index',
    'quan-tri/danh-gia/sua' => 'ReviewAdminController@update',   // POST

    'quan-tri/ma-giam-gia'      => 'VoucherAdminController@index',
    'quan-tri/ma-giam-gia/luu'  => 'VoucherAdminController@save',   // POST
    'quan-tri/ma-giam-gia/xoa'  => 'VoucherAdminController@delete', // POST
    'quan-tri/ma-giam-gia/phat' => 'VoucherAdminController@grant',  // POST

    'quan-tri/co-so'        => 'StoreAdminController@index',
    'quan-tri/co-so/luu'    => 'StoreAdminController@save',        // POST
    'quan-tri/co-so/xoa'    => 'StoreAdminController@delete',      // POST

    // Yêu cầu đặt lại mật khẩu — đường dự phòng khi hosting không gửi được
    // mail. Nhân viên gọi xác minh rồi mới bấm tạo liên kết.
    'quan-tri/quen-mat-khau'      => 'PasswordResetAdminController@index',
    'quan-tri/quen-mat-khau/tao'  => 'PasswordResetAdminController@issue',  // POST

    // -----------------------------------------------------------------------
    // CHUYỂN HƯỚNG TỪ URL TIẾNG ANH CŨ
    //
    // Giữ để link đã chia sẻ hoặc đã lập chỉ mục không chết. 301 (vĩnh viễn)
    // chứ không 302: báo cho công cụ tìm kiếm chuyển hẳn sang địa chỉ mới,
    // thay vì giữ mãi cả hai bản và chia nhỏ thứ hạng.
    // -----------------------------------------------------------------------
    'home'             => 'redirect:/',
    'product'          => 'redirect:/san-pham',
    'product/detail'   => 'redirect:/san-pham',
    'category'         => 'redirect:/san-pham',
    'danh-muc'         => 'redirect:/san-pham',   // Lovable không có trang danh mục riêng
    'about'            => 'redirect:/gioi-thieu',
    'event'            => 'redirect:/su-kien',
    'event/detail'     => 'redirect:/su-kien',
    'contact'          => 'redirect:/lien-he',
    'ar'               => 'redirect:/thu-ar',
    'cart'             => 'redirect:/gio-hang',
    'account'          => 'redirect:/tai-khoan',
];
