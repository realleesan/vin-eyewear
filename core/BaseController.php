<?php
/**
 * Vin Eyewear - Base Controller
 * Parent class for all controllers with view rendering functionality
 */

class BaseController
{
    /**
     * Render a view with data
     *
     * @param string $viewName View path relative to views directory
     * @param array $data Data to pass to the view
     * @return void
     */
    protected function renderView($viewName, $data = [])
    {
        // Hộp thoại "Chọn hình thức mua" hiện ĐÈ LÊN bất kỳ trang nào có nút
        // thêm giỏ — trang chủ, danh mục, tìm kiếm, chi tiết sản phẩm. Nên nó
        // được dựng ở đây, một lần, thay vì mỗi controller phải nhớ tự làm.
        //
        // Xem _layout/buy-modal.php về cách nó bật/tắt bằng ?mua= trên URL.
        if (!array_key_exists('buyModal', $data)) {
            $data['buyModal'] = self::buyModal();
        }

        /*
         * TRANG ĐANG MỞ HỘP THOẠI THÌ ĐỪNG CẤT VÀO ĐỆM LỊCH SỬ.
         *
         * ?mua= và ?buoc= là trạng thái GIAO DIỆN tạm thời, không phải nội
         * dung. Thiếu dòng này, khách tắt JavaScript mua xong rồi bấm Lùi sẽ
         * được trình duyệt dựng lại nguyên trang cũ từ bfcache — kèm hộp thoại
         * xác nhận của món vừa mua xong, và máy chủ không có cơ hội nói rằng
         * lượt mua đó đã kết thúc.
         *
         * Chỉ cho trang CÓ hộp thoại: cả site còn lại vẫn được đệm như thường.
         *
         * Có JavaScript thì đường đi khác (địa chỉ ?mua= do pushState tạo ra,
         * chưa từng là một lần tải trang thật nên không mang được header này)
         * — chốt bên đó nằm ở khối 'pageshow' trong assets/js/buy-flow.js.
         */
        if (!empty($data['buyModal'])) {
            header('Cache-Control: no-store, must-revalidate');
        }

        /*
         * Dải báo "Đã thêm … vào giỏ hàng".
         *
         * Thêm vào giỏ nay Ở LẠI đúng trang khách đang đứng (xem
         * CartController::add), nên lời báo phải đi theo họ tới bất kỳ trang
         * nào — trang chủ, danh mục, chi tiết sản phẩm.
         *
         * Đọc ở đây, SAU controller: trang nào tự đọc flash này rồi (giỏ hàng
         * in nó thành một dải trong trang) thì tới lượt này không còn gì, và
         * không có chuyện một lời báo hiện hai lần ở hai chỗ.
         */
        if (!array_key_exists('toast', $data)) {
            /*
             * Đọc CẢ HAI: lời xác nhận và lời báo lỗi. Chỉ đọc 'cart_success'
             * thì một lỗi giỏ hàng xảy ra ở trang không phải /gio-hang sẽ biến
             * mất không dấu vết — khách bấm mua, không có gì xảy ra, và không
             * ai nói cho họ biết vì sao.
             */
            [$data['toast'], $data['toastTone']] = self::toastFromFlash();
        }

        // Extract data array to individual variables
        extract($data);

        // Load the master layout
        require_once VIEWS_PATH . '/_layout/master.php';
    }

    /**
     * Dải báo lấy từ flash, dùng chung cho trang đầy đủ và cho mảnh.
     *
     * @return array{0: string|null, 1: string}
     */
    protected static function toastFromFlash(): array
    {
        $toast = flash('cart_success');

        if ($toast !== null) {
            return [$toast, 'ok'];
        }

        return [flash('cart_error'), 'err'];
    }

    /**
     * Trả THẲNG ba mảnh của luồng mua hàng, không qua bước chuyển hướng.
     *
     * ─────────────────────────────────────────────────────────────────────
     * MỘT CÚ BẤM = MỘT LƯỢT ĐI VỀ, KHÔNG PHẢI HAI
     *
     * Đường cũ: buy-flow.js POST sang /gio-hang/chon, máy chủ trả 302, fetch
     * tự đi tiếp lượt thứ hai để GET trang có ?buoc= mới, rồi mới nhặt được
     * ba mảnh. Trên máy chủ chạy nhanh thì hai lượt đó không ai để ý; trên
     * hosting chia sẻ, mỗi lượt tốn vài trăm mili giây và khách bấm một cái
     * phải chờ gần hai giây — mà luồng mua có tới năm bước.
     *
     * Nay khi request mang header X-Buy-Flow, chính cú POST đó trả luôn ba
     * mảnh của bước kế. Địa chỉ mới đi kèm ở header X-Buy-Url để buy-flow.js
     * còn pushState — thanh địa chỉ vẫn phải đổi, nếu không nút Lùi và F5 mất
     * đường về đúng bước.
     *
     * LÀM ĐƯỢC VÌ BA MẢNH KHÔNG CẦN CONTROLLER CỦA TRANG ĐÍCH. Hộp thoại dựng
     * từ ?mua= + ?buoc= + phiên (buyModal), dải báo từ flash, cụm giỏ từ
     * phiên. Không mảnh nào cần danh sách sản phẩm liên quan hay đánh giá của
     * trang chi tiết — thứ mà lượt GET thứ hai vẫn phải tính đủ rồi vứt đi.
     *
     * KHÔNG dùng cho đích SANG TRANG KHÁC (ví dụ /thanh-toan sau "Mua ngay"):
     * ở đó phải là chuyển hướng thật để trình duyệt đổi trang. Nơi gọi tự
     * quyết — xem CartController::buyStepDone().
     * ─────────────────────────────────────────────────────────────────────
     */
    protected function buyFragment(string $url): never
    {
        /* buyModal() đọc $_GET, mà đây là request POST tới một địa chỉ khác.
           Nạp tay hai tham số nó cần, đúng như lượt GET thứ hai sẽ mang. */
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        $_GET['mua']  = $q['mua']  ?? '';
        $_GET['buoc'] = $q['buoc'] ?? '';

        header('Vary: X-Buy-Flow');
        header('X-Buy-Url: ' . $url);

        [$toast, $toastTone] = self::toastFromFlash();

        partial('_layout/buy-fragment', [
            'buyModal'  => self::buyModal(),
            'toast'     => $toast,
            'toastTone' => $toastTone,
        ]);

        exit;
    }

    /**
     * Dữ liệu cho hộp thoại "Chọn hình thức mua", hoặc null khi không phải mở.
     *
     * Trả null — tức là không vẽ gì — trong mọi trường hợp bất thường thay vì
     * báo lỗi: ?mua= là một tham số trên URL, ai cũng gõ tay được. Một hộp
     * thoại không mở là kết cục đúng cho một địa chỉ vô nghĩa.
     */
    private static function buyModal(): ?array
    {
        $id = trim((string) ($_GET['mua'] ?? ''));

        if ($id === '') {
            return null;
        }

        $row = ProductModel::find($id);

        if ($row === null) {
            return null;
        }

        // Lấy LẠI qua findVisibleBySlug: chỉ hàm đó giải mã cột `images` từ
        // JSON. Dùng thẳng kết quả của find() thì $product['images'] còn là
        // chuỗi, và ProductModel::image() trả về ký tự "[" — ô ảnh trong hộp
        // thoại thành một hình vỡ. Cùng hai bước mà CartController::add() làm.
        $product = ProductModel::findVisibleBySlug($row['slug']);

        if ($product === null || (int) $product['is_visible'] !== 1) {
            return null;
        }

        // MỌI mặt hàng đều đi qua đủ luồng. Khác nhau đúng một chỗ: nhánh
        // "theo số đo" của gọng và kính mát có thêm bước chọn GÓI TRÒNG rời,
        // còn tròng rời và kính áp tròng thì không — chúng đã là tròng rồi.
        $takesPackage = LensModel::takesLensPackage($product);

        /*
         * KHÔNG CÓ Ý ĐỊNH ĐANG TREO THÌ KHÔNG MỞ HỘP THOẠI.
         *
         * Ý định được đặt lúc khách bấm "Mua ngay"/"Thêm vào giỏ" và bị xoá
         * ngay khi món hàng vào giỏ (CartController::add), nên chốt này nói
         * đúng một điều: hộp thoại chỉ sống trong một lượt mua đang dở.
         *
         * VÌ SAO CẦN: ?mua= và ?buoc= nằm trên URL, mà URL thì nằm trong lịch
         * sử duyệt. Mua xong rồi bấm nút Lùi của trình duyệt là quay lại đúng
         * địa chỉ ?mua=…&buoc=xac-nhan đó, và bản trước dựng lại hộp thoại như
         * chưa có gì xảy ra — khách đã mua xong lại thấy màn hình xác nhận
         * hiện lên lần nữa.
         *
         * Bản trước cố ý cho phép: vào thẳng địa chỉ có ?mua= thì lùi về mặc
         * định hợp lý (một chiếc, không phương án) chứ không đóng hộp thoại.
         * Đổi lại quy ước đó — một đường dẫn dán tay hay chép cho người khác
         * nay chỉ mở TRANG, không mở hộp thoại, và nút mua trên trang vẫn ở
         * nguyên đó cho ai muốn bắt đầu thật.
         *
         * Ý định của một SẢN PHẨM KHÁC cũng rơi vào đây: khách mở hộp thoại
         * cho món A, đóng lại, rồi gõ tay ?mua=<B> — số lượng của A không được
         * theo sang, và cũng không có gì để mở cho B.
         */
        $intent = $_SESSION['_buy_intent'] ?? [];

        if (($intent['product_id'] ?? null) !== $product['id']) {
            return null;
        }

        // Bước đang mở. Tên lạ thì về đầu luồng — ?buoc= gõ tay được.
        $step = (string) ($_GET['buoc'] ?? '');

        if (!in_array($step, ['so-do', 'kieu-trong', 'trong', 'xac-nhan'], true)) {
            $step = 'hinh-thuc';
        }

        /* Hai bước chọn tròng không tồn tại với mặt hàng ĐÃ LÀ tròng (tròng
           rời, kính áp tròng) — gõ tay ?buoc=trong cũng không mở ra được. */
        if (!$takesPackage && in_array($step, ['kieu-trong', 'trong'], true)) {
            $step = 'xac-nhan';
        }

        /* Kiểu "Mắt đặt" không có bảng giá nào để chọn tiếp; ?buoc=trong gõ
           tay khi đang ở kiểu đó chỉ mở ra một bước bên buyStep() sẽ không
           chấp nhận. */
        if ($step === 'trong'
            && !LensModel::typeTakesPackage(LensModel::findType($intent['lens_type'] ?? null))) {
            $step = 'kieu-trong';
        }

        return [
            'product'  => $product,
            'step'     => $step,
            'takesPackage' => $takesPackage,
            'intent'  => [
                'variant_id' => $intent['variant_id'] ?? null,
                'quantity'   => max(1, (int) ($intent['quantity'] ?? 1)),
                'action'     => ($intent['action'] ?? '') === 'buy' ? 'buy' : 'add',
                'mode'       => ($intent['mode'] ?? '') === 'combo' ? 'combo' : 'frame',
                /* mode ở trên LUÔN trả về một trong hai giá trị, kể cả khi
                   khách chưa chọn gì — nên nó không phân biệt được "đã chọn
                   Chỉ mua gọng" với "chưa trả lời câu nào". Bước 1 cần đúng
                   phân biệt đó để đánh dấu ô đã chọn khi khách bấm Lùi về. */
                'mode_set'   => in_array($intent['mode'] ?? null, ['frame', 'combo'], true),
                'rx'         => $intent['rx'] ?? null,
                /* Sáu ô số đo nguyên văn — để bảng hiện lại đúng như lúc khách
                   rời đi khi họ bấm Lùi. Bản chép này CÓ CHỌN LỌC, nên quên
                   thêm khoá mới vào đây là view không bao giờ thấy nó dù
                   session đã lưu đủ. Xem rx_raw trong CartController. */
                'rx_raw'     => is_array($intent['rx_raw'] ?? null)
                    ? $intent['rx_raw']
                    : null,
                'lens_id'    => $intent['lens_id'] ?? null,
                'lens_type'  => $intent['lens_type'] ?? null,
                'back'       => safeRedirectPath(
                    $intent['back'] ?? null,
                    '/san-pham/' . rawurlencode($product['slug'])
                ),
            ],
        ];
    }
}
