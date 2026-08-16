<?php

/**
 * AboutController — trang Giới thiệu (/gioi-thieu).
 *
 * Port từ src/routes/gioi-thieu.tsx.
 *
 * Nội dung tĩnh, không đụng cơ sở dữ liệu. Đặt thẳng trong controller thay vì
 * tách ra config như trang chính sách: chỉ có 3 mảng ngắn và chúng gắn chặt
 * với bố cục của đúng trang này, không dùng lại ở đâu khác.
 */

class AboutController extends BaseController
{
    public function index(): void
    {
        // Quy trình đo mắt — số thứ tự do view tự sinh theo vị trí
        $steps = [
            ['title' => 'Tiếp nhận',         'desc' => 'Ghi nhận nhu cầu và tiền sử thị lực của khách.'],
            ['title' => 'Đo khúc xạ',        'desc' => 'Đo tự động kết hợp thử kính chủ quan để xác định độ chính xác.'],
            ['title' => 'Thử tròng',         'desc' => 'Khách trải nghiệm tròng kính phù hợp trong 10-15 phút.'],
            ['title' => 'Tư vấn gọng',       'desc' => 'Chọn dáng gọng theo khuôn mặt, chất liệu và ngân sách.'],
            ['title' => 'Lắp & hiệu chỉnh',  'desc' => 'Lắp tròng, cân chỉnh gọng và hướng dẫn bảo quản.'],
        ];

        $stats = [
            ['value' => '2014',   'label' => 'Năm thành lập'],
            ['value' => '02',     'label' => 'Cơ sở tại Hà Nội'],
            ['value' => '50k+',   'label' => 'Lượt đo khúc xạ'],
            ['value' => '4.9/5',  'label' => 'Đánh giá khách hàng'],
        ];

        $values = [
            ['icon' => 'eye',       'title' => 'Đo mắt chuẩn xác',    'desc' => 'Thiết bị hiện đại và kỹ thuật viên nhiều năm kinh nghiệm.'],
            ['icon' => 'award',     'title' => 'Sản phẩm chính hãng', 'desc' => 'Gọng và tròng kính nhập khẩu, có tem bảo hành đầy đủ.'],
            ['icon' => 'handshake', 'title' => 'Hậu mãi trọn đời',    'desc' => 'Cân chỉnh, vệ sinh và siết ốc miễn phí không giới hạn.'],
        ];

        $this->renderView('about/index', [
            'pageTitle' => 'Giới thiệu Vin Eyewear — Chuyên gia kính cận',
            'metaDesc'  => 'Câu chuyện thương hiệu, quy trình đo khúc xạ 5 bước '
                         . 'và cam kết chất lượng của Vin Eyewear.',
            'steps'     => $steps,
            'stats'     => $stats,
            'values'    => $values,
        ]);
    }
}
