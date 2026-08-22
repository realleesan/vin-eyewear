<?php

/**
 * AboutController — trang Giới thiệu (/gioi-thieu).
 *
 * Nội dung tĩnh, không đụng cơ sở dữ liệu. Đặt thẳng trong controller thay vì
 * tách ra config như trang chính sách: chỉ có hai mảng ngắn và chúng gắn chặt
 * với bố cục của đúng trang này, không dùng lại ở đâu khác.
 *
 * Bố cục theo bản thiết kế "Gioi thieu v2.dc.html" — xem app/views/about/index.php.
 */

class AboutController extends BaseController
{
    public function index(): void
    {
        // Khối 02 — bốn ô nền đỏ trong lưới giá trị cốt lõi. Chỉ có tên: bản
        // thiết kế để mỗi ô một số thứ tự và một dòng tiêu đề, phần diễn giải
        // nằm gọn ở ô mở đầu chiếm hai cột.
        $values = [
            'Lấy khách hàng làm trọng tâm',
            'Chính trực',
            'Nhiệt huyết',
            'Tận tâm',
        ];

        // Khối 03 — ba mục trong danh sách của phần dịch vụ đo mắt
        $exam = [
            [
                'title' => 'Thiết bị đo khúc xạ',
                'desc'  => 'Máy đo được bảo dưỡng định kỳ, kết hợp thử kính trực tiếp để ra kết '
                         . 'quả phù hợp với từng người.',
            ],
            [
                'title' => 'Quy trình chuẩn hóa',
                'desc'  => 'Mỗi lần tư vấn đi qua đủ bước: đo mắt, thử tròng, tư vấn gọng, lắp '
                         . 'kính và hướng dẫn bảo quản.',
            ],
            [
                'title' => 'Cải tiến liên tục',
                'desc'  => 'Chúng tôi thường xuyên cập nhật kiến thức kỹ thuật và theo dõi nhãn '
                         . 'khoa mới để phục vụ tốt hơn.',
            ],
        ];

        $this->renderView('about/index', [
            'pageTitle' => 'Giới thiệu Vin Eyewear — Chuyên gia kính cận',
            'metaDesc'  => 'Câu chuyện thương hiệu, giá trị cốt lõi và dịch vụ đo khúc xạ '
                         . 'của Vin Eyewear.',
            'values'    => $values,
            'exam'      => $exam,
        ]);
    }
}
