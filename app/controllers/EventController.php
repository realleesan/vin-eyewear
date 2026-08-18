<?php

/**
 * EventController — sự kiện, tin tức & khuyến mãi (/su-kien).
 *
 * Đây cũng là trang mà mục "Khuyến mãi" trên thanh điều hướng trỏ tới — xem
 * bảng $navItems trong app/views/_layout/header.php.
 *
 * Bản cũ gõ cứng toàn bộ nội dung sự kiện trong controller (162 dòng mảng).
 * Nay đọc từ bảng `events` qua EventModel, nên người quản trị thêm sự kiện
 * là trang tự cập nhật.
 */

class EventController extends BaseController
{
    /** Số bài mỗi trang, đúng PAGE_SIZE của "Vin Eyewear News.dc.html". */
    private const PER_PAGE = 9;

    public function index(): void
    {
        // Lọc theo nhóm; rỗng = xem tất cả.
        // Đối chiếu với danh sách nhóm CÓ THẬT trong DB thay vì tin thẳng
        // tham số URL — gõ ?category=<script> cũng chỉ rơi về "tất cả".
        $categories = EventModel::categories();
        $active     = (string) ($_GET['category'] ?? '');

        if ($active !== '' && !in_array($active, $categories, true)) {
            $active = '';
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));

        /*
         * Chỉ tab "Tất cả" mới có thẻ lớn nổi bật, nên chỉ nó cần lấy dư một
         * bài ở trang 1 — xem tham số $leadExtra của paginateVisible(). Lọc
         * theo nhóm thì không có thẻ lớn, lưới lấy đúng PER_PAGE.
         */
        $leadExtra = $active === '';

        $result = EventModel::paginateVisible($active, $page, self::PER_PAGE, $leadExtra);
        $items  = $result['items'];

        /*
         * BÀI NỔI BẬT = BÀI MỚI NHẤT, không phải một cờ trong CSDL.
         *
         * Bản thiết kế lấy đúng phần tử đầu danh sách làm thẻ lớn màu tối, và
         * chỉ khi đang xem "Tất cả" ở trang 1. Làm theo đúng vậy nên KHÔNG cần
         * thêm cột `is_featured`: bài mới nhất tự lên đầu, và người quản trị
         * không phải nhớ bỏ cờ của bài cũ mỗi lần đăng bài mới.
         */
        $featured = null;

        if ($leadExtra && $result['page'] === 1 && $items !== []) {
            $featured = array_shift($items);
        }

        /*
         * MỘT TRANG CHIA LÀM HAI KHỐI, đúng như bản thiết kế dựng:
         *
         *   4 bài đầu  -> THẺ NGANG trong lưới 2 cột (ảnh trái, chữ phải)
         *   còn lại    -> DANH SÁCH GỌN "Bài viết khác" (ảnh 64px một hàng)
         *
         * Vì sao không cho cả 9 bài vào lưới thẻ: 9 thẻ ngang xếp thành 5 hàng
         * cao bằng nhau, và bài thứ 9 trông quan trọng y như bài thứ 1. Bản thiết
         * kế cố tình hạ bậc phần đuôi — trang vẫn liệt kê đủ, nhưng mắt biết đâu
         * là phần chính.
         *
         * TRANG CHỈ CÒN 1–2 BÀI thì lưới 2 cột sẽ hở một ô trống to bằng nửa
         * trang. Bản thiết kế xử lý bằng cách cho thẻ chiếm CẢ HAI cột và đổi tỉ
         * lệ ảnh sang 45% — xem $bigCards.
         */
        $bigCards = count($items) <= 2;

        $this->renderView('event/index', [
            'pageTitle'  => 'Sự kiện & Tin tức — Vin Eyewear',
            'metaDesc'   => 'Sự kiện, ưu đãi và tin tức mới nhất từ Vin Eyewear — '
                          . 'workshop chọn gọng, khám mắt miễn phí và các chương trình đặc biệt.',
            'featured'   => $featured,
            // 'events' giữ nguyên để trang biết "có bài nào không"; hai khối bên
            // dưới mới là thứ view lặp qua.
            'events'     => $items,
            'cards'      => $bigCards ? $items : array_slice($items, 0, 4),
            'compact'    => $bigCards ? [] : array_slice($items, 4),
            'bigCards'   => $bigCards,
            'categories' => $categories,
            'counts'     => $this->counts($categories),
            'active'     => $active,
            'total'      => $result['total'],
            'page'       => $result['page'],
            'totalPages' => $result['totalPages'],
        ]);
    }

    /**
     * Số bài mỗi nhóm, hiện cạnh tên nhóm ở dải lọc.
     *
     * Đếm một lượt trên toàn bộ sự kiện thay vì truy vấn lại cho từng nhóm —
     * danh sách này nhỏ, không đáng thêm N câu lệnh.
     */
    private function counts(array $categories): array
    {
        $all    = EventModel::visible();
        $counts = ['' => count($all)];

        foreach ($categories as $category) {
            $counts[$category] = 0;
        }

        foreach ($all as $event) {
            $key = $event['category'] ?? '';

            if (isset($counts[$key])) {
                $counts[$key]++;
            }
        }

        return $counts;
    }
}
