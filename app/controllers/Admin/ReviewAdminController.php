<?php

/**
 * ReviewAdminController — duyệt đánh giá (/quan-tri/danh-gia).
 *
 * Đánh giá khách gửi vào với trạng thái 'pending' và KHÔNG hiện trên trang sản
 * phẩm. Màn hình này là nơi duy nhất đưa chúng ra ngoài — thiếu nó thì mọi
 * đánh giá nằm im mãi mãi.
 *
 * Cho phép cả staff (không chỉ admin/manager): duyệt đánh giá là việc chăm sóc
 * khách hàng hằng ngày, không phải sửa dữ liệu catalog.
 */

class ReviewAdminController extends AdminController
{
    private const BASE = '/quan-tri/danh-gia';

    public function index(): void
    {
        // 'status' chứ không phải 'trang-thai': dải lọc dùng chung
        // (admin/_layout/filter-tabs.php) sinh liên kết với tên đó.
        $status = (string) ($_GET['status'] ?? '');

        if ($status !== '' && !isset(ReviewModel::STATUSES[$status])) {
            $status = '';
        }

        $result = ReviewModel::paginateAdmin($status, max(1, (int) ($_GET['page'] ?? 1)), 20);

        $this->renderAdmin('admin/reviews/index', [
            'pageTitle' => 'Đánh giá — Quản trị',
            'reviews'   => $result['items'],
            'page'      => $result,
            'status'    => $status,
            'statuses'  => ReviewModel::STATUSES,
            'counts'    => $this->counts(),
            /*
             * ĐIỂM TRUNG BÌNH — chỉ tính đánh giá ĐANG HIỆN.
             *
             * Gộp cả bài chờ duyệt vào thì con số nhảy mỗi lần có khách viết
             * bài mới, trong khi thứ nó phải mô tả là điểm mà người lạ vào
             * trang bán hàng NHÌN THẤY. Một bài 1 sao đang nằm chờ duyệt không
             * hạ điểm của cửa hàng cho tới lúc ai đó bấm Duyệt.
             *
             * Trả null khi chưa có bài nào hiện — view bỏ hẳn vế câu đó thay vì
             * in "0.0★", con số đọc lên là sai (không phải điểm 0, mà là chưa
             * có điểm).
             */
            'diemTrungBinh' => Database::fetchValue(
                "SELECT AVG(rating) FROM reviews WHERE status = 'published'"
            ),
        ]);
    }

    /**
     * Duyệt · từ chối · xoá. Một action cho cả ba vì chúng ở cùng một hàng
     * trong bảng, và HTML không cho lồng <form> — cùng cách làm với giỏ hàng.
     */
    public function update(): void
    {
        $this->requirePost(self::BASE);

        $id  = (string) ($_POST['id'] ?? '');
        $act = (string) ($_POST['act'] ?? '');

        if ($act === 'xoa') {
            // Xoá hẳn thay vì để 'rejected': đánh giá spam hay bôi nhọ không
            // có lý do gì phải giữ lại trong CSDL. remove() tự tính lại điểm.
            ReviewModel::remove($id);
            flash('admin_success', 'Đã xoá đánh giá.');
            redirect(self::BASE);
        }

        $map = ['duyet' => 'published', 'tu-choi' => 'rejected', 'cho' => 'pending'];

        if (!isset($map[$act]) || !ReviewModel::setStatus($id, $map[$act])) {
            flash('admin_error', 'Không thực hiện được thao tác này.');
            redirect(self::BASE);
        }

        // setStatus tự gọi recount(), nên điểm trung bình của sản phẩm luôn
        // khớp đúng những đánh giá đang hiện ra ngoài.
        flash('admin_success', 'Đã cập nhật đánh giá.');
        redirect(self::BASE);
    }

    /** Số đánh giá theo từng trạng thái, hiện cạnh tên bộ lọc. */
    private function counts(): array
    {
        $out = ['' => ReviewModel::count()];

        foreach (array_keys(ReviewModel::STATUSES) as $key) {
            $out[$key] = ReviewModel::count(['status' => $key]);
        }

        return $out;
    }
}
