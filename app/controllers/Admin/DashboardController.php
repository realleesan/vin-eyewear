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
        $stats = Database::fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM products WHERE is_visible = 1)              AS products,
                (SELECT COUNT(*) FROM products WHERE stock_quantity <= 5)         AS low_stock,
                (SELECT COUNT(*) FROM categories WHERE is_visible = 1)            AS categories,
                (SELECT COUNT(*) FROM events WHERE is_visible = 1)                AS events,
                (SELECT COUNT(*) FROM orders)                                      AS orders,
                (SELECT COUNT(*) FROM orders WHERE status = 'new')                 AS new_orders,
                (SELECT COUNT(*) FROM appointments WHERE status = 'pending')       AS pending_appointments,
                (SELECT COUNT(*) FROM contact_requests WHERE status = 'new')       AS new_contacts,
                (SELECT COALESCE(SUM(total), 0) FROM orders
                  WHERE status <> 'cancelled')                                     AS revenue"
        );

        $this->renderAdmin('admin/dashboard/index', [
            'pageTitle'    => 'Tổng quan — Quản trị Vin Eyewear',
            'stats'        => $stats,
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
                  ORDER BY a.appointment_date ASC, a.time_slot ASC
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
