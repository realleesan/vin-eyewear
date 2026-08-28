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
            /*
             * Cột `reply` chỉ có từ migration 2026-08-28-phan-hoi-danh-gia.
             *
             * PHẢI HỎI TRƯỚC KHI DÙNG, cùng lý do với `zalo_sent_at` ở trang
             * Liên hệ: chưa chạy file nâng cấp mà cứ đọc cột đó thì cả trang
             * đổ lỗi 1054. Chưa có cột thì view ẩn hẳn phần phản hồi và nói rõ
             * phải chạy file nào — trang vẫn duyệt được đánh giá như thường.
             */
            'coCotReply' => Database::columnExists('reviews', 'reply'),
            /* ?tra-loi=<id> mở hộp soạn phản hồi. Truy vấn riêng: đánh giá đang
               mở có thể nằm ở trang khác của phân trang hoặc bị dải lọc loại
               ra, mà đường dẫn thì vẫn phải mở được. */
            'dangTraLoi' => isset($_GET['tra-loi'])
                ? Database::fetchOne(
                    'SELECT r.*, p.name AS product_name
                       FROM reviews r
                       JOIN products p ON p.id = r.product_id
                      WHERE r.id = :id',
                    ['id' => (string) $_GET['tra-loi']]
                )
                : null,
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

    /**
     * Ghi phản hồi công khai của cửa hàng cho một đánh giá
     * (POST /quan-tri/danh-gia/phan-hoi).
     *
     * ─────────────────────────────────────────────────────────────────────────
     * CÂU NÀY HIỆN CHO KHÁCH ĐỌC, KHÔNG PHẢI GHI CHÚ NỘI BỘ
     *
     * Nó nằm ngay dưới đánh giá ở trang sản phẩm, ký tên cửa hàng. Vì thế:
     *
     *   · lưu cả `replied_at` — khách cần biết cửa hàng trả lời lúc nào; trả
     *     lời sau ba ngày và sau ba tháng là hai chuyện khác nhau, mà
     *     `updated_at` thì đổi theo cả những lần bấm Duyệt nên không nói được
     *     điều đó;
     *   · XOÁ TRẮNG được: gửi chuỗi rỗng thì gỡ luôn cả `replied_at` về NULL,
     *     tức là "chưa từng trả lời". Để lại một mốc thời gian không có nội
     *     dung thì trang sản phẩm vẽ ra một khối phản hồi trống.
     *
     * KHÔNG đòi đánh giá phải ở trạng thái 'published': soạn sẵn câu trả lời
     * rồi mới bấm Duyệt là thứ tự làm việc hợp lý — cả hai cùng hiện ra một
     * lượt với khách.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function reply(): void
    {
        $this->requirePost(self::BASE);

        if (!Database::columnExists('reviews', 'reply')) {
            flash('admin_error', 'Chưa lưu được phản hồi — cơ sở dữ liệu còn thiếu cột. '
                . 'Chạy database/migrations/2026-08-28-phan-hoi-danh-gia.sql.');
            redirect(self::BASE);
        }

        $id = (string) ($_POST['id'] ?? '');

        if (ReviewModel::find($id) === null) {
            flash('admin_error', 'Không tìm thấy đánh giá.');
            redirect(self::BASE);
        }

        $noiDung = trim((string) ($_POST['reply'] ?? ''));

        Database::execute(
            'UPDATE reviews
                SET reply = :reply, replied_at = :luc
              WHERE id = :id',
            [
                'reply' => $noiDung !== '' ? $noiDung : null,
                'luc'   => $noiDung !== '' ? date('Y-m-d H:i:s') : null,
                'id'    => $id,
            ]
        );

        flash('admin_success', $noiDung !== ''
            ? 'Đã lưu phản hồi — khách đọc được ngay dưới đánh giá ở trang sản phẩm.'
            : 'Đã gỡ phản hồi khỏi trang sản phẩm.');

        redirect(self::BASE);
    }
}
