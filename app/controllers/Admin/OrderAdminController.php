<?php

/**
 * OrderAdminController — đơn hàng (/quan-tri/don-hang).
 *
 * Port từ src/routes/_authenticated/quan-tri/don-hang.tsx.
 *
 * Nhân viên (staff) xem và đổi trạng thái đơn được — khớp policy gốc
 * "staff orders" vốn cho cả admin, manager và staff toàn quyền trên bảng này.
 */

class OrderAdminController extends AdminController
{
    public function index(): void
    {
        $status = (string) ($_GET['status'] ?? '');

        // Chỉ nhận trạng thái có thật; giá trị lạ coi như không lọc
        if ($status !== '' && !isset(OrderModel::STATUSES[$status])) {
            $status = '';
        }

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $result = OrderModel::paginateAdmin($status, $page, 20);

        // Lấy dòng hàng của các đơn đang hiện, gộp MỘT câu lệnh thay vì
        // truy vấn trong vòng lặp (N+1) — 20 đơn sẽ thành 21 câu lệnh.
        $itemsByOrder = [];
        $ids = array_column($result['items'], 'id');

        if ($ids !== []) {
            $ph = [];
            $params = [];
            foreach (array_values($ids) as $i => $id) {
                $ph[] = ":id{$i}";
                $params["id{$i}"] = $id;
            }

            foreach (Database::fetchAll(
                'SELECT * FROM order_items WHERE order_id IN (' . implode(', ', $ph) . ')',
                $params
            ) as $item) {
                $itemsByOrder[$item['order_id']][] = $item;
            }
        }

        $this->renderAdmin('admin/orders/index', [
            'pageTitle' => 'Đơn hàng — Quản trị',
            'orders'    => $result['items'],
            'items'     => $itemsByOrder,
            'total'     => $result['total'],
            'page'      => $result['page'],
            'totalPages'=> $result['totalPages'],
            'status'    => $status,
            'statuses'  => OrderModel::STATUSES,
            'counts'    => $this->statusCounts(),
        ]);
    }

    /**
     * Đổi trạng thái đơn.
     */
    public function updateStatus(): void
    {
        $this->requirePost('/quan-tri/don-hang');

        $id     = (string) ($_POST['id'] ?? '');
        $status = (string) ($_POST['status'] ?? '');

        if (!isset(OrderModel::STATUSES[$status])) {
            flash('admin_error', 'Trạng thái không hợp lệ.');
            redirect('/quan-tri/don-hang');
        }

        if (!OrderModel::exists(['id' => $id])) {
            flash('admin_error', 'Không tìm thấy đơn hàng.');
            redirect('/quan-tri/don-hang');
        }

        // Cột `status` và bảng lịch sử phải đổi CÙNG NHAU: thanh tiến trình
        // trong trang tài khoản của khách đọc lịch sử để lấy giờ của từng
        // bước, nên một bản ghi thiếu là một mốc trống vĩnh viễn.
        Database::transaction(static function () use ($id, $status): void {
            OrderModel::update($id, ['status' => $status]);
            OrderModel::logStatus($id, $status, AuthMiddleware::userId());
        });

        flash('admin_success', 'Đã cập nhật trạng thái đơn hàng.');
        redirect('/quan-tri/don-hang');
    }

    /**
     * Số đơn theo từng trạng thái, hiện cạnh tên bộ lọc.
     */
    private function statusCounts(): array
    {
        $rows   = Database::fetchAll('SELECT status, COUNT(*) AS n FROM orders GROUP BY status');
        $counts = ['' => 0];

        foreach (array_keys(OrderModel::STATUSES) as $key) {
            $counts[$key] = 0;
        }

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
            $counts['']            += (int) $row['n'];
        }

        return $counts;
    }
}
