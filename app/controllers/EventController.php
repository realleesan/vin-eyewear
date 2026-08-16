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

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $result = EventModel::paginateVisible($active, $page, self::PER_PAGE);
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

        if ($active === '' && $result['page'] === 1 && $items !== []) {
            $featured = array_shift($items);
        }

        $this->renderView('event/index', [
            'pageTitle'  => 'Sự kiện & Tin tức — Vin Eyewear',
            'metaDesc'   => 'Sự kiện, ưu đãi và tin tức mới nhất từ Vin Eyewear — '
                          . 'workshop chọn gọng, khám mắt miễn phí và các chương trình đặc biệt.',
            'featured'   => $featured,
            'events'     => $items,
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
