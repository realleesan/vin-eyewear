<?php

/**
 * config/routes.php
 *
 * Bảng ánh xạ URL -> Controller@Action.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐƯỜNG DẪN TIẾNG VIỆT
 *
 * Bản Lovable dùng URL tiếng Việt (/san-pham, /bo-suu-tap, /gioi-thieu…), bản
 * PHP cũ dùng tiếng Anh (/product, /collection, /about…). Port frontend thì lấy theo
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

    // Tìm kiếm toàn site: sản phẩm + bài viết + cơ sở + chính sách.
    // Ô tìm kiếm trên đầu trang trỏ vào đây, KHÔNG còn trỏ /san-pham?q=.
    'tim-kiem'         => 'SearchController@index',

    'san-pham'         => 'ProductController@index',
    // Đặt TRƯỚC 'san-pham/{slug}': router khớp theo thứ tự khai, để sau thì
    // 'danh-gia' bị hiểu thành slug của một sản phẩm.
    'san-pham/danh-gia' => 'ProductDetailController@review',   // POST
    // Cùng lý do đặt trước 'san-pham/{slug}' như dòng trên.
    'san-pham/cho-hang' => 'ProductDetailController@waitlist', // POST

    /*
     * HAI TRANG CON CỦA DANH MỤC. Cũng phải đứng TRƯỚC 'san-pham/{slug}', vì
     * nếu để sau thì router coi 'gong-kinh' là slug sản phẩm và trả 404.
     *
     * Khai đích danh chứ không dùng 'san-pham/{slug}' => ProductController:
     * đường có tham số ấy đã thuộc về trang CHI TIẾT sản phẩm, không chia được.
     * Danh sách slug hợp lệ nằm ở ProductController::SUB_PAGES — thêm dòng ở
     * đây thì phải thêm cả bên đó, lý do đầy đủ ghi tại chỗ ấy.
     *
     * Danh mục thứ ba ('kinh-mat') cố ý chưa có trang con, vẫn duyệt được qua
     * /san-pham?category=kinh-mat.
     */
    'san-pham/gong-kinh'  => 'ProductController@category',
    'san-pham/trong-kinh' => 'ProductController@category',

    'san-pham/{slug}'  => 'ProductDetailController@show',

    /*
     * BỘ SƯU TẬP — danh sách, rồi trang chi tiết của từng bộ.
     *
     * Trang chi tiết KHÔNG dựng lại lưới hàng: nó kể câu chuyện của bộ rồi đẩy
     * người xem sang /san-pham?collection=<slug>. Lý do đầy đủ (kể cả vì sao
     * route này từng bị bỏ hôm 2026-08-25 rồi dựng lại) ghi ở đầu
     * app/controllers/CollectionController.php.
     *
     * {slug} là slug trong bảng `collections`, cũng chính là chuỗi nằm ở cột
     * products.collection — một giá trị, đừng sinh thêm giá trị thứ hai.
     */
    'bo-suu-tap'         => 'CollectionController@index',
    'bo-suu-tap/{slug}'  => 'CollectionController@show',

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

    /* Màn QR hỏi vài giây một lần: đơn này đã nhận được tiền chưa. Trả JSON,
       không trả trang. Đây là thứ thay cho nút "Tôi đã chuyển khoản" đã bỏ —
       xem OrderController::payStatus(). */
    'thanh-toan/trang-thai' => 'OrderController@payStatus',
    'thanh-toan/hoan-tat' => 'OrderController@success',

    /*
     * BIÊN NHẬN — chỉ mở được khi TIỀN ĐÃ VỀ THẬT.
     *
     * Khác hẳn /thanh-toan/hoan-tat: trang đó nói "đơn đã được ghi nhận" ngay
     * sau khi đặt, chưa liên quan gì tới tiền. Trang này nói "đã thanh toán",
     * và câu đó chỉ được phép hiện khi orders.payment_status đã sang 'paid'
     * hoặc 'deposit_paid' — xem OrderController::paid.
     */
    'thanh-toan/thanh-cong' => 'OrderController@paid',
    // Đăng nhập / đăng ký / tài khoản
    'auth'              => 'AuthController@index',
    'auth/dang-nhap'    => 'AuthController@login',      // POST
    /*
     * ĐĂNG KÝ LÀ BỐN CHẶNG, không còn một cú POST như trước — số điện thoại
     * phải xác minh bằng mã trước khi tài khoản ra đời. Xem khối chú thích
     * "ĐĂNG KÝ — BỐN CHẶNG" trong AuthController.
     */
    'auth/dang-ky'          => 'AuthController@signupPhone',   // POST — nhận số
    'auth/dang-ky/gui-ma'   => 'AuthController@signupSend',    // POST — sinh & gửi mã
    'auth/dang-ky/xac-minh' => 'AuthController@signupVerify',  // POST — kiểm mã
    'auth/dang-ky/mat-khau' => 'AuthController@signupFinish',  // POST — tạo tài khoản
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

    // Quên mật khẩu — khách tự làm bằng mã OTP. Bốn chặng nằm chung một địa
    // chỉ, chọn bằng ?buoc= (xem khối chú thích trong AuthController).
    /*
     * WEBHOOK SEPAY — máy chủ SePay gọi vào đây khi có tiền về tài khoản ngân
     * hàng của cửa hàng, để đơn chuyển khoản tự sang "đã thanh toán" / "đã đặt
     * cọc" mà không cần ai đối chiếu sao kê.
     *
     * Đây là đường DUY NHẤT trong site không kiểm CSRF: người gọi là máy chủ
     * bên thứ ba, không có phiên và không có token nào để gửi. Thứ thay thế là
     * khoá bí mật trong header Authorization — xem SepayController::webhook.
     *
     * Chưa khai SEPAY_WEBHOOK_KEY thì địa chỉ này trả 403 cho mọi request.
     */
    'webhook/sepay'          => 'SepayController@webhook',   // POST, không CSRF

    'quen-mat-khau'          => 'AuthController@forgot',
    'quen-mat-khau/gui'      => 'AuthController@forgotSubmit',  // POST — gửi mã
    'quen-mat-khau/gui-lai'  => 'AuthController@forgotResend',  // POST — gửi lại mã
    'quen-mat-khau/xac-minh' => 'AuthController@forgotVerify',  // POST — kiểm mã
    'quen-mat-khau/dat-lai'  => 'AuthController@forgotFinish',  // POST — lưu mật khẩu mới

    // Đường của NHÂN VIÊN: liên kết có token, cho ca khách không nhận được mã.
    // Tách riêng vì nó tới từ một liên kết dán qua Zalo hay đọc qua điện thoại
    // — phải mở được bằng GET, không có phiên nào cả.
    'dat-lai-mat-khau'      => 'AuthController@reset',
    'dat-lai-mat-khau/luu'  => 'AuthController@resetSubmit',    // POST

    // -----------------------------------------------------------------------
    // KHU QUẢN TRỊ — controller còn RỖNG, sẽ port ở bước sau
    // -----------------------------------------------------------------------
    /*
     * CỔNG ĐĂNG NHẬP — hai địa chỉ DUY NHẤT trong khu này mở cho người chưa
     * đăng nhập. Mọi dòng còn lại đi qua AdminController, và lớp đó chặn ngay
     * ở constructor.
     *
     * Tách GET và POST thành hai đường vì router khớp theo ĐƯỜNG DẪN, không
     * theo phương thức — cùng lối với cặp 'auth' / 'auth/dang-nhap' ở trên.
     */
    'quan-tri/dang-nhap'          => 'AdminAuthController@index',
    'quan-tri/dang-nhap/xac-thuc' => 'AdminAuthController@login',              // POST

    /*
     * ĐĂNG XUẤT CỦA KHU QUẢN TRỊ — đường riêng, không dùng chung
     * 'auth/dang-xuat' của khách ở phía trên file này.
     *
     * Việc bên trong y hệt nhau, nhưng để hai khu vực POST chung một địa chỉ
     * là giữ lại đúng sợi dây cuối cùng nối chúng với nhau — lý do đầy đủ ghi
     * ở AdminAuthController::logout().
     */
    'quan-tri/dang-xuat'          => 'AdminAuthController@logout',             // POST

    'quan-tri'                    => 'DashboardController@index',

    'quan-tri/don-hang'           => 'OrderAdminController@index',
    'quan-tri/don-hang/trang-thai'=> 'OrderAdminController@updateStatus',       // POST
    // Ghi nhận đã nhận tiền (đối chiếu sao kê cho đơn chuyển khoản). Tách khỏi
    // trang-thai vì trạng thái GIAO VẬN và trạng thái TIỀN là hai trục khác
    // nhau — xem OrderModel::PAYMENT_STATUSES.
    'quan-tri/don-hang/thanh-toan'=> 'OrderAdminController@updatePayment',      // POST
    /* Thao tác trên NHIỀU đơn đã tick cùng lúc. Một đường cho cả đổi trạng
       thái lẫn ghi nhận tiền, phân việc bằng trường `act` — vì cả hai nút nằm
       trong cùng một <form> mang danh sách id, mà một form chỉ có một action.
       Lý do đầy đủ ở OrderAdminController::bulk(). */
    'quan-tri/don-hang/hang-loat' => 'OrderAdminController@bulk',               // POST
    // Trả loạt đơn vừa đổi về trạng thái cũ. Trạng thái cũ đi trong chính form
    // chứ không nằm trong session — xem OrderAdminController::undoStatus().
    'quan-tri/don-hang/hoan-tac'  => 'OrderAdminController@undoStatus',         // POST

    'quan-tri/lich-hen'           => 'AppointmentAdminController@index',
    'quan-tri/lich-hen/trang-thai'=> 'AppointmentAdminController@updateStatus', // POST
    // Khách gọi điện đặt, hoặc đang đứng ở quầy hẹn hôm sau quay lại — hai
    // đường vào không đi qua trang đặt lịch của khách.
    'quan-tri/lich-hen/tao'       => 'AppointmentAdminController@store',        // POST
    /* Huỷ lịch đi đường RIÊNG, không phải một giá trị của ô chọn trạng thái:
       huỷ là ngã rẽ ra khỏi vòng đời chứ không phải một bước tiến tới, và ô
       chọn thì tự gửi form nên trượt tay một nấc là mất buổi hẹn của khách.
       Lý do đầy đủ ở AppointmentAdminController::cancel(). */
    'quan-tri/lich-hen/huy'       => 'AppointmentAdminController@cancel',       // POST

    'quan-tri/lien-he'            => 'ContactAdminController@index',
    /* Đường ĐẨY SANG ZALO, thay cho 'lien-he/trang-thai' bỏ ngày 2026-08-26.
       Trang liên hệ không còn trạng thái để đổi — nó thành sổ lưu trữ, và thao
       tác duy nhất còn lại là đẩy lại một tin Zalo đã nuốt mất. */
    'quan-tri/lien-he/zalo'       => 'ContactAdminController@sendZalo',         // POST
    // Đẩy CẢ hàng chờ một lượt — yêu cầu kẹt lại gần như luôn kẹt theo lô.
    'quan-tri/lien-he/zalo-tat-ca' => 'ContactAdminController@sendZaloAll',    // POST

    'quan-tri/cho-hang'           => 'WaitlistAdminController@index',
    'quan-tri/cho-hang/da-bao'    => 'WaitlistAdminController@markNotified',   // POST
    'quan-tri/ton-kho'            => 'InventoryAdminController@index',
    'quan-tri/ton-kho/cap-nhat'   => 'InventoryAdminController@updateStock',    // POST

    'quan-tri/san-pham'     => 'ProductAdminController@index',
    'quan-tri/san-pham/luu' => 'ProductAdminController@save',      // POST
    'quan-tri/san-pham/xoa' => 'ProductAdminController@delete',    // POST

    'quan-tri/danh-muc'     => 'CategoryAdminController@index',
    'quan-tri/danh-muc/luu' => 'CategoryAdminController@save',     // POST
    'quan-tri/danh-muc/xoa' => 'CategoryAdminController@delete',   // POST
    // Thứ tự danh mục = thứ tự trên menu trang bán hàng, nên nó là dữ liệu
    // khách nhìn thấy chứ không phải chuyện sắp cho gọn mắt.
    'quan-tri/danh-muc/thu-tu' => 'CategoryAdminController@move',   // POST
    'quan-tri/danh-muc/hien'   => 'CategoryAdminController@toggle', // POST

    'quan-tri/bo-suu-tap'     => 'CollectionAdminController@index',
    'quan-tri/bo-suu-tap/luu' => 'CollectionAdminController@save',      // POST
    'quan-tri/bo-suu-tap/xoa' => 'CollectionAdminController@delete',    // POST
    'quan-tri/bo-suu-tap/thu-tu' => 'CollectionAdminController@move',   // POST
    'quan-tri/bo-suu-tap/hien'   => 'CollectionAdminController@toggle', // POST
    /* CÂU HỎI THƯỜNG GẶP của một bộ — hai đường riêng, không gộp vào /luu.
       Form bộ sưu tập là MỘT <form>, mà mỗi câu hỏi cần nút xoá của riêng nó,
       và HTML không cho lồng <form> vào nhau. */
    /* Chữ đầu trang /bo-suu-tap (tiêu đề + đoạn dẫn). Đường riêng vì nó là nội
       dung của TRANG DANH SÁCH, không thuộc bộ sưu tập nào — gộp vào /luu thì
       sửa bộ nào cũng ghi đè được nó. */
    'quan-tri/bo-suu-tap/tong-quan' => 'CollectionAdminController@saveTexts', // POST
    'quan-tri/bo-suu-tap/faq/luu' => 'CollectionAdminController@saveFaq',   // POST
    'quan-tri/bo-suu-tap/faq/xoa' => 'CollectionAdminController@deleteFaq', // POST

    'quan-tri/bien-the'     => 'VariantAdminController@index',
    'quan-tri/bien-the/luu' => 'VariantAdminController@save',    // POST
    'quan-tri/bien-the/xoa' => 'VariantAdminController@delete',  // POST

    'quan-tri/danh-gia'     => 'ReviewAdminController@index',
    'quan-tri/danh-gia/sua' => 'ReviewAdminController@update',   // POST
    // Phản hồi CÔNG KHAI của cửa hàng, hiện dưới đánh giá ở trang sản phẩm.
    'quan-tri/danh-gia/phan-hoi' => 'ReviewAdminController@reply', // POST

    'quan-tri/ma-giam-gia'      => 'VoucherAdminController@index',
    'quan-tri/ma-giam-gia/luu'  => 'VoucherAdminController@save',   // POST
    'quan-tri/ma-giam-gia/xoa'  => 'VoucherAdminController@delete', // POST
    'quan-tri/ma-giam-gia/phat' => 'VoucherAdminController@grant',  // POST
    // Tắt KHÁC xoá: mã tắt vẫn tra ngược được từ đơn cũ, bật lại một cú bấm.
    'quan-tri/ma-giam-gia/bat-tat' => 'VoucherAdminController@toggle', // POST

    // Bảng giá tròng — một LƯỚI kiểu tròng × gói chiết suất, không phải CRUD:
    // không có route xoá, và route lưu ghi cả bảng một lượt. Xem
    // LensPriceAdminController.
    /* Bốn danh sách thuộc tính tròng (loại · chiết suất · lớp phủ · màu) —
       chúng dựng nên bộ lọc của /san-pham/trong-kinh. Đặt ngay trên "Giá tròng"
       vì hai màn cùng nói về tròng và người mở cái này thường mở luôn cái kia. */
    'quan-tri/thuoc-tinh-trong'         => 'LensOptionAdminController@index',
    'quan-tri/thuoc-tinh-trong/luu'     => 'LensOptionAdminController@save',   // POST
    'quan-tri/thuoc-tinh-trong/hien'    => 'LensOptionAdminController@toggle', // POST
    'quan-tri/thuoc-tinh-trong/thu-tu'  => 'LensOptionAdminController@move',   // POST

    'quan-tri/gia-trong'     => 'LensPriceAdminController@index',
    'quan-tri/gia-trong/luu' => 'LensPriceAdminController@save',   // POST

    /* DANH MỤC gói chiết suất — trang riêng, vào từ nút trên bảng giá. Tách
       khỏi lưới giá vì hai việc khác nhịp: giá đổi hằng tháng và sửa hàng loạt
       trong một lưới, còn thêm một gói là việc vài tháng một lần và đi từng
       bản ghi. Xem LensPriceAdminController::packages(). */
    'quan-tri/gia-trong/goi'     => 'LensPriceAdminController@packages',
    'quan-tri/gia-trong/goi/luu' => 'LensPriceAdminController@savePackage',   // POST
    'quan-tri/gia-trong/goi/xoa' => 'LensPriceAdminController@deletePackage', // POST
    // Thứ tự gói = thứ tự khách thấy ở bước chọn loại tròng; gói đầu được
    // chọn sẵn, nên nó quyết định gói nào bán chạy.
    'quan-tri/gia-trong/goi/thu-tu' => 'LensPriceAdminController@movePackage', // POST

    /*
     * KHÁCH HÀNG
     *
     * Trang chi tiết là MỘT route có tham số, và bốn tab đi qua ?tab= chứ
     * không phải bốn route con. Tab dựng bằng địa chỉ (không phải JavaScript)
     * nên sau mỗi POST còn quay về đúng chỗ vừa đứng — xem CustomerAdminController::TABS.
     *
     * SÁU ĐƯỜNG POST ĐỀU ĐẶT TRƯỚC 'khach-hang/{id}'. Router khớp chính xác
     * trước rồi mới tới route có tham số nên thứ tự khai không đổi kết quả,
     * nhưng đọc theo thứ tự này thì thấy ngay cái nào là trang, cái nào là
     * thao tác — và người thêm route thứ bảy sẽ đặt nó đúng chỗ.
     *
     * BẢY ĐƯỜNG GHI ĐÃ BỎ ngày 2026-08-28, đừng thêm lại mà chưa đọc đầu
     * CustomerAdminController:
     *   · 'khach-hang/ho-so'            hồ sơ khách nay chỉ xem
     *   · 'khach-hang/dia-chi/luu'      sổ địa chỉ nay chỉ xem
     *   · 'khach-hang/dia-chi/xoa'
     *   · 'khach-hang/dia-chi/mac-dinh'
     *   · 'khach-hang/ghi-chu/luu'      bỏ hẳn phần ghi chú nội bộ
     *   · 'khach-hang/ghi-chu/xoa'
     *   · 'khach-hang/dat-lai'          gửi email đặt lại mật khẩu; đường duy
     *                                   nhất còn lại là /quan-tri/quen-mat-khau,
     *                                   đường có bước gọi điện xác minh
     */
    'quan-tri/khach-hang'                  => 'CustomerAdminController@index',
    'quan-tri/khach-hang/xuat'             => 'CustomerAdminController@export',

    'quan-tri/khach-hang/khoa'             => 'CustomerAdminController@lock',              // POST
    'quan-tri/khach-hang/mo-khoa'          => 'CustomerAdminController@unlock',            // POST
    'quan-tri/khach-hang/xoa'              => 'CustomerAdminController@softDelete',        // POST
    'quan-tri/khach-hang/khoi-phuc'        => 'CustomerAdminController@restore',           // POST

    'quan-tri/khach-hang/don-thuoc/luu'    => 'CustomerAdminController@savePrescription',  // POST
    'quan-tri/khach-hang/don-thuoc/xoa'    => 'CustomerAdminController@deletePrescription',// POST

    // Đặt SAU mọi đường trên. Id là UUID nên trên thực tế không đụng nhau,
    // nhưng đừng dựa vào đó: thêm 'khach-hang/thong-ke' mà quên đặt lên trên
    // thì nó vẫn chạy nhờ luật khớp chính xác — chỉ là người đọc file không
    // còn thấy được luật đó nữa.
    'quan-tri/khach-hang/{id}'             => 'CustomerAdminController@show',

    'quan-tri/co-so'        => 'StoreAdminController@index',
    'quan-tri/co-so/luu'    => 'StoreAdminController@save',        // POST
    'quan-tri/co-so/xoa'    => 'StoreAdminController@delete',      // POST
    // Tạm đóng KHÁC xoá: lịch hẹn đã đặt ở cơ sở đó vẫn tra được, chỉ là khách
    // không đặt thêm được nữa.
    'quan-tri/co-so/hoat-dong' => 'StoreAdminController@toggle',   // POST

    // Yêu cầu đặt lại mật khẩu — đường dự phòng khi hosting không gửi được
    // mail. Nhân viên gọi xác minh rồi mới bấm tạo liên kết.
    /*
     * TÀI KHOẢN NỘI BỘ — khác hẳn 'quan-tri/quen-mat-khau' ngay dưới.
     *
     *   nhan-vien      mật khẩu của NHÂN VIÊN, cấp lại ngay tại chỗ vì người
     *                  đó ngồi cùng phòng. Chỉ vai trò 'admin'.
     *   quen-mat-khau  mật khẩu của KHÁCH, phát ra một liên kết đặt lại, và
     *                  bắt gọi điện xác minh trước. Vai trò 'manager' trở lên.
     *
     * Lý do đầy đủ ghi ở đầu Admin/StaffAdminController.
     */
    'quan-tri/nhan-vien'          => 'StaffAdminController@index',
    'quan-tri/nhan-vien/luu'      => 'StaffAdminController@save',              // POST
    'quan-tri/nhan-vien/khoa'     => 'StaffAdminController@toggleLock',        // POST
    'quan-tri/nhan-vien/dat-lai'  => 'StaffAdminController@resetPassword',     // POST
    /* MỞ KHOÁ ĐĂNG NHẬP sau 5 lần sai (SNFR-06) — Quyết định Q13, 04/09/2026.
       Đường RIÊNG, không gộp vào 'nhan-vien/khoa': đó là khoá hành chính do
       người đặt và không có hạn, còn đây là khoá kỹ thuật do hệ thống đặt và
       tự tan sau 15 phút. Lý do đầy đủ ở StaffAdminController::moKhoaDangNhap. */
    'quan-tri/nhan-vien/mo-khoa-dang-nhap' => 'StaffAdminController@moKhoaDangNhap', // POST
    /* GÁN CƠ SỞ cho tài khoản nội bộ — SNFR-07b, Q12.1 đến Q12.3.
       Đường riêng chứ không gộp vào 'nhan-vien/luu': lưu hồ sơ là sửa thông tin
       một con người, còn đây là sửa PHẠM VI QUYỀN của họ. Gộp hai việc vào một
       form nghĩa là mỗi lần sửa số điện thoại cũng ghi đè luôn phân công cơ sở,
       và vết trong nhật ký không phân biệt được hai thao tác đó. */
    'quan-tri/nhan-vien/co-so'    => 'StaffAdminController@saveStores',        // POST

    // Đổi mật khẩu của CHÍNH MÌNH — mọi nhân viên đều vào được.
    'quan-tri/doi-mat-khau'       => 'AccountAdminController@index',
    'quan-tri/doi-mat-khau/luu'   => 'AccountAdminController@save',            // POST

    'quan-tri/quen-mat-khau'      => 'PasswordResetAdminController@index',
    'quan-tri/quen-mat-khau/tao'  => 'PasswordResetAdminController@issue',  // POST

    /*
     * LỊCH SỬ THAO TÁC — UC-3.2.10.2 và vế "đọc được" của SNFR-11.
     *
     * Hệ thống đã ghi vết vào bảng `customer_audit_logs` từ lâu, nhưng cho tới
     * 03/09/2026 không có màn nào ĐỌC nó — trừ tab Hoạt động của một khách cụ
     * thể. Vết của thao tác tiền và thao tác kho thì không có đường nào xem.
     *
     * '/nhat-ky/xuat' là GET chứ không POST dù nó sinh ra một file: nó chỉ
     * đọc, không đổi gì, nên phải chia sẻ được và bấm F5 được. Đặt sau đường
     * cha để router khớp đúng thứ tự khai.
     *
     * Chỉ vai trò 'admin' — chốt trong controller, không chỉ giấu khỏi thanh
     * bên. Lý do tạm siết ở 'admin' ghi ở đầu Admin/AuditLogAdminController.
     */
    'quan-tri/nhat-ky'            => 'AuditLogAdminController@index',
    'quan-tri/nhat-ky/xuat'       => 'AuditLogAdminController@xuat',

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
    /* 'event' và 'event/detail' ĐÃ BỎ cùng với tính năng sự kiện (2026-08-26).
       Không trỏ chúng sang /bo-suu-tap hay trang chủ: nội dung đã biến mất
       thật, mà chuyển hướng sang một trang không liên quan là "soft 404" —
       máy tìm kiếm coi đó là lừa và giữ URL cũ trong chỉ mục lâu hơn. Để 404
       là nói đúng sự thật, và /su-kien/{slug} cũng vậy. */
    'contact'          => 'redirect:/lien-he',
    'ar'               => 'redirect:/thu-ar',
    'cart'             => 'redirect:/gio-hang',
    'account'          => 'redirect:/tai-khoan',
];
