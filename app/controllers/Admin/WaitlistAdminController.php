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
            /* Bảng thiếu thì chỉ hiện một dải nhắc, không để trang đổ — cùng
               cách làm với màn Nhật ký thao tác. Xem WaitlistModel::available().
               Không có cờ này thì danh sách rỗng vì THIẾU BẢNG trông y hệt danh
               sách rỗng vì KHÔNG AI ĐANG CHỜ, mà hai thứ đó cần hai hành động
               khác hẳn nhau. */
            'coBang'    => WaitlistModel::available(),
        ]);
    }

    /** Nhân viên đã gọi / nhắn xong. */
    public function markNotified(): void
    {
        $this->requirePost('/quan-tri/cho-hang');

        /* MỌI NHÂN VIÊN — SRS v2.1.0, ma trận 5.2.2.

           Trước đây thao tác này đòi Quản lý cơ sở, xếp cùng mức với sửa tồn
           kho. Nhưng hai việc khác hẳn nhau: sửa tồn kho là đổi một con số
           kinh doanh, còn đánh dấu "đã báo" chỉ là ghi nhận rằng chính người
           đang bấm vừa gọi điện cho khách xong. Bắt họ đi tìm Quản trị viên
           để ghi lại việc mình vừa làm là đặt một cánh cửa giữa người làm và
           việc họ vừa làm.

           Không còn phép kiểm nào ở đây — constructor của lớp cha đã chặn
           người ngoài khu quản trị. */

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
