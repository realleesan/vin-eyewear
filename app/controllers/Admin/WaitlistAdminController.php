<?php

/**
 * WaitlistAdminController — màn "Chờ hàng" trong khu quản trị.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MÀN NÀY LÀ PHẦN CÒN LẠI CỦA MỘT LỜI HỨA
 *
 * Trang bán hàng có nút "Thông báo khi có hàng". Khách bấm, để lại số điện
 * thoại, và tin rằng sẽ có người gọi. Nếu chỉ ghi vào bảng mà không ai nhìn
 * thấy thì lời hứa đó rỗng — nên màn này ra đời cùng lúc với cái nút, không
 * phải sau.
 *
 * Việc báo tin là việc TAY: hosting hiện tại không gửi được email (xem chú
 * thích đầu WaitlistModel). Nhân viên nhìn danh sách, gọi hoặc nhắn Zalo, rồi
 * bấm "Đã báo".
 * ─────────────────────────────────────────────────────────────────────────────
 */

class WaitlistAdminController extends AdminController
{
    public function index(): void
    {
        /* Mặc định chỉ hiện người ĐANG CHỜ — đó là việc phải làm hôm nay.
           ?loc=tat-ca để xem cả những lượt đã báo, khi cần đối chiếu. */
        $tatCa = ($_GET['loc'] ?? '') === 'tat-ca';

        $this->renderAdmin('admin/waitlist/index', [
            'pageTitle' => 'Chờ hàng',
            'rows'      => WaitlistModel::danhSach(!$tatCa),
            'tatCa'     => $tatCa,
            'dangCho'   => WaitlistModel::demDangCho(),
        ]);
    }

    /** Nhân viên đã gọi / nhắn xong. */
    public function markNotified(): void
    {
        $this->requirePost('/quan-tri/cho-hang');

        /* requireManager chứ không chỉ requireStaff: đánh dấu "đã báo" là xoá
           một việc khỏi danh sách việc phải làm. Cùng mức quyền với sửa tồn
           kho — xem InventoryAdminController::updateStock. */
        $this->requireManager('/quan-tri/cho-hang');

        $id = (string) ($_POST['id'] ?? '');

        if ($id !== '' && WaitlistModel::exists(['id' => $id])) {
            WaitlistModel::danhDauDaBao($id);
            flash('admin_success', 'Đã đánh dấu là đã báo khách.');
        } else {
            flash('admin_error', 'Không tìm thấy lượt chờ này.');
        }

        /* Giữ nguyên bộ lọc đang xem: dấu ngoặc là BẮT BUỘC ở đây. Toán tử `.`
           bám chặt hơn `===`, nên viết không ngoặc thì PHP hiểu thành
           ('/quan-tri/cho-hang' . $loc) === 'tat-ca' — luôn sai, và chuyển
           hướng về chuỗi rỗng. */
        $loc = ($_POST['loc'] ?? '') === 'tat-ca' ? '?loc=tat-ca' : '';

        redirect('/quan-tri/cho-hang' . $loc);
    }
}
