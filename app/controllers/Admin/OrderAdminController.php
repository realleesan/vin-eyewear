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
            // Nhãn trạng thái TIỀN. Truyền vào như 'statuses' thay vì để view gọi
            // thẳng hằng của model — cùng một lối cho cả hai trục trạng thái.
            'payStatuses' => OrderModel::PAYMENT_STATUSES,
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

        // Mọi luật đi kèm việc đổi trạng thái nằm trong model: ghi lịch sử
        // (thanh tiến trình của khách đọc bảng đó) và đánh dấu đã thu tiền khi
        // đơn COD hoàn tất. Xem OrderModel::changeStatus.
        OrderModel::changeStatus($id, $status, AuthMiddleware::staffId());

        flash('admin_success', 'Đã cập nhật trạng thái đơn hàng.');
        redirect('/quan-tri/don-hang');
    }

    /**
     * Ghi nhận đã nhận được tiền, hoặc gỡ đánh dấu nếu bấm nhầm
     * (POST /quan-tri/don-hang/thanh-toan).
     *
     * Đây là bước ĐỐI CHIẾU TAY cho đơn chuyển khoản: nhân viên xem sao kê, thấy
     * tiền vào với nội dung là mã đơn thì bấm. Đơn COD không cần bấm — thu tiền
     * và giao hàng là cùng một việc, nên changeStatus() tự đánh dấu khi đơn sang
     * "Hoàn tất".
     *
     * Khi nối cổng thanh toán, webhook sẽ gọi thẳng OrderModel::markPaid() và
     * nút này còn lại để xử lý những ca cổng không bắt được (khách chuyển từ
     * ngân hàng khác, sai nội dung…).
     */
    public function updatePayment(): void
    {
        $this->requirePost('/quan-tri/don-hang');

        $id   = (string) ($_POST['id'] ?? '');
        $paid = ($_POST['paid'] ?? '') === '1';

        if (!OrderModel::exists(['id' => $id])) {
            flash('admin_error', 'Không tìm thấy đơn hàng.');
            redirect('/quan-tri/don-hang');
        }

        $changed = $paid ? OrderModel::markPaid($id) : OrderModel::markUnpaid($id);

        // Nói rõ "không có gì đổi" thay vì báo thành công: hai nhân viên cùng
        // xem một sao kê và cùng bấm thì người thứ hai phải biết là mình không
        // vừa ghi thêm một lần thu tiền nào.
        if (!$changed) {
            flash('admin_error', 'Đơn hàng đã ở đúng trạng thái thanh toán đó.');
            redirect('/quan-tri/don-hang');
        }

        flash('admin_success', $paid
            ? 'Đã ghi nhận thanh toán cho đơn hàng.'
            : 'Đã gỡ đánh dấu thanh toán.');
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
