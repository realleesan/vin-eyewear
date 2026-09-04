<?php

/**
 * DashboardController — tổng quan khu quản trị (/quan-tri).
 *
 * Port từ src/routes/_authenticated/quan-tri/index.tsx.
 */

class DashboardController extends AdminController
{
    public function index(): void
    {
        /*
         * HAI CON SỐ CHỈ DÙNG CHO CHÂN THẺ — `orders_total` và
         * `upcoming_appointments`.
         *
         * Chúng không lên thẻ số liệu nào cả; chúng là vế "xem tất cả 33 đơn"
         * ở đáy hai danh sách bị cắt ngắn. Nếu không đếm thì chân thẻ chỉ ghi
         * được "Xem tất cả →", và câu hỏi mà cái chân ấy sinh ra để trả lời —
         * "sáu dòng này là sáu dòng đầu, hay là tất cả?" — lại bỏ ngỏ.
         *
         * `upcoming_appointments` đếm ĐÚNG tập mà thẻ đang cắt từ đó
         * (>= CURDATE()), không phải tổng số lịch hẹn trong bảng: chân thẻ ghi
         * "xem tất cả N lịch" mà N gồm cả buổi hẹn năm ngoái thì nó nói dối.
         * Nó cũng KHÔNG trùng `pending_appointments` — lịch đã xác nhận vẫn là
         * lịch sắp tới, chỉ là không còn phải xác nhận nữa.
         */
        // Đếm gộp trong MỘT câu lệnh thay vì 8 câu riêng.
        // Mỗi truy vấn là một vòng đi-về tới DB; trang tổng quan mở rất
        // thường xuyên nên gộp lại là đáng.
        /*
         * Ô "Liên hệ chưa đẩy" đọc `contact_requests`.`zalo_sent_at`, cột chỉ
         * có từ migration 2026-08-26-lien-he-qua-zalo.
         *
         * PHẢI HỎI TRƯỚC KHI NHẮC TỚI NÓ. Cả tám con số dưới đây đi trong MỘT
         * câu lệnh, nên một cột thiếu không làm hụt một ô mà đổ nguyên câu với
         * lỗi 1054 — và đây là trang đầu tiên mọi nhân viên nhìn thấy sau khi
         * đăng nhập. Khu quản trị coi như đóng cửa vì một file nâng cấp chưa
         * chạy.
         *
         * Chưa có cột thì ô đó đọc 0, đúng nghĩa "không biết có gì tồn đọng" —
         * và trang /quan-tri/lien-he có sẵn một dải cảnh báo nói rõ phải chạy
         * file nào.
         */
        $demChuaDay = Database::columnExists('contact_requests', 'zalo_sent_at')
            ? '(SELECT COUNT(*) FROM contact_requests WHERE zalo_sent_at IS NULL)'
            : '0';

        /* Cùng lối với $demChuaDay ngay trên: tính biểu thức RA BIẾN rồi nội
           suy, vì câu này là chuỗi nháy KÉP — viết ' . ... . ' trong đó thì nó
           thành chữ thường nằm giữa câu SQL chứ không phải phép nối, và php -l
           vẫn báo sạch. */
        $nguongSapHet = ProductModel::nguongSapHetSql();

        $stats = Database::fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM products WHERE is_visible = 1)              AS products,
                -- Cùng ngưỡng với trang Tồn kho, và cùng lý do: mỗi mặt hàng
                -- có mốc riêng ở `low_stock_at`, để trống mới rơi về 5. Thẻ này
                -- dẫn thẳng sang trang ấy nên hai con số phải khớp; lệch nhau
                -- thì bấm vào thẻ ghi 3 sắp hết lại ra một bảng 7 dòng.
                (SELECT COUNT(*) FROM products
                  WHERE stock_quantity <= {$nguongSapHet})                          AS low_stock,
                (SELECT COUNT(*) FROM categories WHERE is_visible = 1)            AS categories,
                (SELECT COUNT(*) FROM orders WHERE status = 'new')                 AS new_orders,
                (SELECT COUNT(*) FROM orders)                                       AS orders_total,
                (SELECT COUNT(*) FROM appointments WHERE status = 'pending')       AS pending_appointments,
                (SELECT COUNT(*) FROM appointments
                  WHERE appointment_date >= CURDATE())                              AS upcoming_appointments,
                {$demChuaDay}                                                      AS contacts_chua_day"
        );

        /*
         * ─────────────────────────────────────────────────────────────────────
         * TIỀN — TÁCH RA MỘT CÂU LỆNH RIÊNG, VÀ TÁCH LÀM HAI CON SỐ
         *
         * Bản trước tính doanh thu là `SUM(total) WHERE status <> 'cancelled'`.
         * Con số đó SAI, và sai theo hướng nguy hiểm nhất là luôn đẹp hơn sự
         * thật: nó cộng cả đơn khách vừa bấm đặt cách đây ba phút và chưa trả
         * một đồng nào. Chủ cửa hàng mở bảng ra thấy một con số không có trong
         * tài khoản ngân hàng nào cả.
         *
         *   DOANH THU  chỉ đơn `payment_status = 'paid'` — tiền đã về ĐỦ. Ba
         *              đường đưa một đơn tới đó, cả ba đều là tiền thật:
         *              webhook SePay khớp giao dịch ngân hàng, nhân viên đối
         *              chiếu sao kê rồi bấm, và đơn COD được giao xong
         *              (OrderModel::changeStatus tự đánh dấu). Xem markPaid().
         *
         *   TẠM THU    tiền CỌC đang giữ của đơn mới trả 30% —
         *              `payment_status = 'deposit_paid'`. Nó nằm trong tài
         *              khoản cửa hàng thật, nhưng KHÔNG PHẢI doanh thu: hàng
         *              chưa giao, tròng có khi chưa mài, và đơn còn huỷ được.
         *              Cộng nó vào doanh thu là ghi nhận một khoản bán hàng
         *              chưa xảy ra.
         *
         * HAI NHÓM RỜI NHAU, không có đơn nào nằm ở cả hai: `payment_status`
         * là một cột, một đơn chỉ mang đúng một giá trị. Nên cộng hai ô lại
         * không bao giờ đếm trùng.
         *
         * Và TẠM THU cộng `deposit_amount` chứ không cộng `total`: khách mới
         * đưa 30%, phần còn lại vẫn nằm trong túi họ.
         *
         * ─────────────────────────────────────────────────────────────────────
         * MỘT LƯỢT QUÉT BẢNG, KHÔNG PHẢI BỐN
         *
         * Ba con số dưới đây đều đọc cùng một tập dòng (mọi đơn chưa huỷ), nên
         * chúng đi bằng CASE WHEN trong một câu lệnh thay vì ba truy vấn con
         * quét `orders` ba lượt. Trang này là trang mở nhiều nhất khu quản trị.
         *
         * TỪNG CÓ CON SỐ THỨ TƯ — `so_don_coc`, đếm số đơn đang giữ cọc. Nó in
         * ra dòng "2 đơn mới trả cọc" dưới ô Tạm thu, và bản thiết kế mới bỏ
         * dòng đó (xem app/views/admin/dashboard/index.php). Bỏ luôn ở đây chứ
         * không để lại một cột không ai đọc: cần lại thì thêm một CASE WHEN
         * nữa, rẻ hơn nhiều so với việc lần sau phải đoán xem nó còn dùng
         * ở đâu không.
         *
         * Đơn ĐÃ HUỶ loại ở mệnh đề WHERE chung — kể cả đơn đã trả tiền rồi mới
         * huỷ. Tiền đó phải hoàn lại, nên nó không phải doanh thu; giữ nó trong
         * bảng là để chủ cửa hàng thấy một khoản thu không bao giờ đối chiếu
         * được với sổ ngân hàng.
         */
        /*
         * MỐC TÍNH DOANH THU — xem config/app.php ['thong_ke_tu'].
         *
         * Để trống thì cộng toàn bộ, đúng như trước. Có giá trị thì chỉ cộng
         * đơn đặt TỪ mốc đó — cách bắt đầu lại từ 0 mà không xoá một dòng nào.
         *
         * Đi qua THAM SỐ RÀNG BUỘC chứ không nối vào chuỗi SQL. Giá trị này
         * đến từ .env, tức là từ một file người ta sửa tay lúc nửa đêm trên
         * File Manager của hosting — chính xác loại nguồn không được tin.
         */
        $mocThongKe = self::mocThongKe();
        $locMoc     = $mocThongKe !== null ? ' AND created_at >= :moc' : '';
        $thamSo     = $mocThongKe !== null ? ['moc' => $mocThongKe] : [];

        $tien = Database::fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total END), 0)
                    AS doanh_thu,
                COUNT(CASE WHEN payment_status = 'paid' THEN 1 END)
                    AS so_don_da_thu,
                COALESCE(SUM(CASE WHEN payment_status = 'deposit_paid' THEN deposit_amount END), 0)
                    AS tam_thu
               FROM orders
              WHERE status <> 'cancelled'" . $locMoc,
            $thamSo
        ) ?? [];

        $this->renderAdmin('admin/dashboard/index', [
            'pageTitle'    => 'Tổng quan — Quản trị Vin Eyewear',
            'stats'        => $stats,
            'tien'         => $tien,
            // null = không đặt mốc; view dùng để nói rõ số liệu tính từ đâu.
            'mocThongKe'   => $mocThongKe,
            'recentOrders' => Database::fetchAll(
                'SELECT * FROM orders ORDER BY created_at DESC LIMIT 8'
            ),
            /*
             * LỊCH HẸN SẮP TỚI — truy vấn riêng, không dùng
             * BookingModel::withStore().
             *
             * Hàm đó sắp xếp ngày GIẢM DẦN vì nó viết cho trang danh sách, nơi
             * người ta muốn thấy cái vừa đặt trước tiên. Trên bảng tổng quan
             * thì câu hỏi ngược lại: hôm nay và mấy hôm tới ai sẽ đến. Dùng
             * chung một hàm ở hai chỗ nghĩa là thẻ "Lịch hẹn sắp tới" bày ra
             * buổi hẹn hôm qua.
             *
             * >= CURDATE() chứ không phải > : buổi hẹn chiều nay vẫn là việc
             * phải chuẩn bị, và nó là dòng quan trọng nhất trong cả thẻ.
             *
             * Đơn đã huỷ vẫn hiện, có huy hiệu "Đã huỷ" — người trực quầy cần
             * biết một chỗ vừa trống ra, không chỉ biết ai sẽ đến.
             */
            'recentBookings' => Database::fetchAll(
                "SELECT a.*, s.name AS store_name, s.code AS store_code
                   FROM appointments a
                   JOIN stores s ON s.id = a.store_id
                  WHERE a.appointment_date >= CURDATE()
                  ORDER BY a.appointment_date ASC, a.created_at ASC
                  LIMIT 8"
            ),
            'lowStock'     => Database::fetchAll(
                'SELECT id, slug, name, sku, stock_quantity, status
                   FROM products
                  WHERE stock_quantity <= ' . ProductModel::nguongSapHetSql() . '
                  ORDER BY stock_quantity ASC
                  LIMIT 8'
            ),
            'orderStatuses'   => OrderModel::STATUSES,
            'bookingStatuses' => BookingModel::STATUSES,
        ]);
    }
    /**
     * Mốc tính doanh thu, dạng 'YYYY-MM-DD HH:MM:SS', hoặc null nếu không đặt.
     *
     * KIỂM ĐỊNH DẠNG RỒI MỚI DÙNG, và trả null khi sai thay vì ném lỗi.
     *
     * Giá trị này đến từ .env — file mà trên hosting người ta sửa tay bằng
     * File Manager, không qua bước kiểm nào. Gõ nhầm "26/08/2026" (kiểu Việt
     * Nam) thay vì "2026-08-26" là chuyện sẽ xảy ra. Ném lỗi ở đó nghĩa là
     * TRANG ĐẦU TIÊN sau khi đăng nhập trả 500 vì một dấu gạch chéo — mà lỗi
     * ấy lại không nói ra nguyên nhân.
     *
     * Trả null thì bảng hiện số liệu toàn bộ, dòng dẫn ghi rõ "trên toàn bộ dữ
     * liệu", và người đặt mốc nhìn ra ngay là nó chưa ăn.
     */
    private static function mocThongKe(): ?string
    {
        $raw = trim((string) config('app.thong_ke_tu', ''));

        if ($raw === '') {
            return null;
        }

        // Chỉ có ngày thì lấy từ đầu ngày hôm đó.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            $raw .= ' 00:00:00';
        }

        $d = DateTime::createFromFormat('Y-m-d H:i:s', $raw);
        $loi = DateTime::getLastErrors();

        if ($d === false || ($loi !== false && ($loi['warning_count'] ?? 0) > 0)) {
            error_log('[Tổng quan] STATS_SINCE sai định dạng, bỏ qua mốc: ' . $raw);

            return null;
        }

        return $d->format('Y-m-d H:i:s');
    }
}
