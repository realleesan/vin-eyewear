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

        $stats = Database::fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM products WHERE is_visible = 1)              AS products,
                (SELECT COUNT(*) FROM products WHERE stock_quantity <= 5)         AS low_stock,
                (SELECT COUNT(*) FROM categories WHERE is_visible = 1)            AS categories,
                (SELECT COUNT(*) FROM orders WHERE status = 'new')                 AS new_orders,
                (SELECT COUNT(*) FROM appointments WHERE status = 'pending')       AS pending_appointments,
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
         * Bốn con số dưới đây đều đọc cùng một tập dòng (mọi đơn chưa huỷ), nên
         * chúng đi bằng CASE WHEN trong một câu lệnh thay vì bốn truy vấn con
         * quét `orders` bốn lượt. Trang này là trang mở nhiều nhất khu quản trị.
         *
         * Đơn ĐÃ HUỶ loại ở mệnh đề WHERE chung — kể cả đơn đã trả tiền rồi mới
         * huỷ. Tiền đó phải hoàn lại, nên nó không phải doanh thu; giữ nó trong
         * bảng là để chủ cửa hàng thấy một khoản thu không bao giờ đối chiếu
         * được với sổ ngân hàng.
         */
        $tien = Database::fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total END), 0)
                    AS doanh_thu,
                COUNT(CASE WHEN payment_status = 'paid' THEN 1 END)
                    AS so_don_da_thu,
                COALESCE(SUM(CASE WHEN payment_status = 'deposit_paid' THEN deposit_amount END), 0)
                    AS tam_thu,
                COUNT(CASE WHEN payment_status = 'deposit_paid' THEN 1 END)
                    AS so_don_coc
               FROM orders
              WHERE status <> 'cancelled'"
        ) ?? [];

        $this->renderAdmin('admin/dashboard/index', [
            'pageTitle'    => 'Tổng quan — Quản trị Vin Eyewear',
            'stats'        => $stats,
            'tien'         => $tien,
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
                  WHERE stock_quantity <= 5
                  ORDER BY stock_quantity ASC
                  LIMIT 8'
            ),
            'orderStatuses'   => OrderModel::STATUSES,
            'bookingStatuses' => BookingModel::STATUSES,
        ]);
    }
}
