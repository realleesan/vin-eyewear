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
            'recentBookings' => BookingModel::withStore('', 8),
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
