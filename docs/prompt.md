Hãy đóng vai trò là một Chuyên gia Lập trình PHP Senior. Tôi đang tự dựng một bộ lõi MVC tinh gọn bằng PHP cho dự án Web bán kính VinEyewear. Giai đoạn này (Phase 1) chỉ tập trung làm Prototype giao diện tĩnh cho 6 trang thuộc Main Menu nên bỏ qua hoàn toàn tầng Model (Database).

Dựa trên cấu trúc thư mục thực tế của tôi (trong đó index.php, .htaccess, thư mục app/, core/, config/, assets/ đều nằm ngay ở thư mục gốc của dự án), hãy viết mã nguồn hoàn chỉnh, sạch sẽ, chuẩn hóa bảo mật cơ bản cho toàn bộ các tệp tin dưới đây:

1. CẤU HÌNH CỬA NGÕ VÀ ĐIỀU HƯỚNG GỐC (Nằm ngay thư mục gốc .)
   .htaccess: Cấu hình RewriteEngine điều hướng tất cả mọi Request từ trình duyệt về file index.php ở gốc để xử lý URL thân thiện (Friendly URL), giữ nguyên Query String. Bỏ qua không điều hướng nếu yêu cầu trùng với các tệp tin hoặc thư mục tĩnh thực tế đang tồn tại (đặc biệt là tài nguyên trong thư mục assets/ như css, js, hình ảnh).

index.php: Khởi tạo session_start(). Định nghĩa các hằng số đường dẫn tuyệt đối dựa trên vị trí thư mục gốc hiện tại: ROOT_PATH, APP_PATH, CORE_PATH. Thực hiện require_once các file bộ lõi (core/Router.php, core/BaseController.php). Nạp mảng định tuyến từ config/routes.php, khởi tạo đối tượng Router từ core và kích hoạt hàm điều hướng hệ thống.

2. BẢN ĐỒ VÀ CỖ MÁY ĐỊNH TUYẾN
   config/routes.php: Trả về một mảng array cấu hình ánh xạ URL tĩnh. Hãy khai báo sẵn đúng 6 tuyến đường trống sau:

'' (hoặc '/') và 'home' trỏ đến 'HomeController@index'

'product' trỏ đến 'ProductController@index'

'about' trỏ đến 'AboutController@index'

'event' trỏ đến 'EventController@index'

'ar' trỏ đến 'ArController@tryon'

'contact' trỏ đến 'ContactController@index'

core/Router.php: Định nghĩa Class Router. Tiếp nhận mảng định tuyến từ file config qua hàm khởi tạo __construct(). Viết hàm dispatch() để phân tích chuỗi URL lấy từ $_SERVER['REQUEST_URI'], làm sạch các ký tự query hoặc dấu gạch chéo thừa, đối chiếu với bản đồ routes.php. Nếu khớp, tự động khởi tạo Class Controller tương ứng trong thư mục app/controllers/ và gọi Action của nó. Nếu không khớp tuyến đường nào, trả về mã lỗi 404 Not Found bằng cách require file errors/404.php.

3. BỘ ĐIỀU KHIỂN GỐC VÀ LAYOUT CHỦ
   core/BaseController.php: Tạo lớp cha chứa hàm bảo vệ renderView($viewName, $data = []). Hàm này dùng extract($data) để giải nén các biến dữ liệu sang View con, sau đó nạp tệp giao diện chung tại app/views/_layout/master.php.

app/views/_layout/master.php: File cấu trúc HTML chung (, , ). Sử dụng lệnh require_once để nạp cố định file header.php và footer.php nằm cùng thư mục _layout. Phần nội dung ở giữa sẽ tự động nhúng file View con động dựa trên biến $viewName được truyền từ Controller sang.

4. ĐỒNG LOẠT 6 CONTROLLER KHUNG XƯƠNG TRỐNG (Thư mục app/controllers/)
   Hãy viết cấu trúc kế thừa extends BaseController và hàm Action tương ứng gọi $this->renderView() cho từng file sau:

app/controllers/HomeController.php (Hàm index(), render view 'home/index')

app/controllers/ProductController.php (Hàm index(), render view 'product/index')

app/controllers/AboutController.php (Hàm index(), render view 'about/index')

app/controllers/EventController.php (Hàm index(), render view 'event/index')

app/controllers/ArController.php (Hàm tryon(), render view 'ar/tryon')

app/controllers/ContactController.php (Hàm index(), render view 'contact/index')

Hãy viết code thuần túy bằng PHP, tối ưu hiệu năng và đảm bảo đường dẫn require chính xác theo cấu trúc không có thư mục public.
