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
        // Khối 02 — bốn thẻ giá trị cốt lõi, mỗi thẻ có tiêu đề, mô tả ngắn
        // và nhãn phụ phía dưới (theo bản thiết kế design-reference/introduction).
        $values = [
            [
                'title' => 'Lấy khách hàng làm trọng tâm',
                'desc'  => 'Lắng nghe thói quen sinh hoạt và đặc thù công việc để gợi ý tròng '
                         . 'kính và kiểu dáng gọng tối ưu nhất.',
                'tag'   => 'Tận tâm',
            ],
            [
                'title' => 'Chính trực & Minh bạch',
                'desc'  => '100% tròng kính và phụ kiện chính hãng, thông tin xuất xứ rõ ràng, '
                         . 'giá niêm yết công khai không phụ phí ẩn.',
                'tag'   => 'Đáng tin cậy',
            ],
            [
                'title' => 'Nhiệt huyết & Chuyên môn',
                'desc'  => 'Kỹ thuật viên khúc xạ nhiều năm kinh nghiệm, liên tục cập nhật '
                         . 'công nghệ đo khám thị lực tiên tiến.',
                'tag'   => 'Chuẩn xác',
            ],
            [
                'title' => 'Tận tâm đồng hành',
                'desc'  => 'Bảo dưỡng, vệ sinh siêu âm và cân chỉnh gọng kính trọn đời, hỗ '
                         . 'trợ trước và sau bán hàng chu đáo.',
                'tag'   => 'Đồng hành',
            ],
        ];

        // Khối 03 — bốn mục trong danh sách của phần dịch vụ đo mắt
        $exam = [
            [
                'title' => 'Quy trình đo khúc xạ 5 bước chuẩn quốc tế',
                'desc'  => 'Không vội vã. Kết hợp máy đo điện tử tự động và thử thị lực thực '
                         . 'tế 10–15 phút để mắt hoàn toàn thích nghi.',
            ],
            [
                'title' => 'Đa dạng phong cách & Phù hợp gương mặt',
                'desc'  => 'Hàng trăm mẫu gọng kính Titanium, Acetate cao cấp từ phong cách '
                         . 'cổ điển thanh lịch đến tối giản hiện đại.',
            ],
            [
                'title' => 'Hỗ trợ công nghệ chọn kính thông minh (AI & Digital Fitting)',
                'desc'  => 'Tư vấn kiểu dáng gọng theo tỷ lệ khuôn mặt và đo tâm mắt, khoảng '
                         . 'cách đồng tử (PD) chuẩn xác tuyệt đối.',
            ],
            [
                'title' => 'Nguồn gốc tròng kính chính hãng 100%',
                'desc'  => 'Bao bì niêm phong được bóc trước mặt khách hàng cùng thẻ bảo hành '
                         . 'chính hãng từ nhà phân phối.',
            ],
        ];

        // Khối cam kết dịch vụ — bốn thẻ icon + tiêu đề + mô tả
        $commitments = [
            [
                'icon'  => 'check',
                'title' => 'Chất lượng kiểm định',
                'desc'  => 'Tròng kính chống trầy xước, chống tia UV, lọc ánh sáng xanh đạt '
                         . 'chuẩn quang học quốc tế.',
            ],
            [
                'icon'  => 'refresh',
                'title' => 'Bảo hành linh hoạt',
                'desc'  => 'Đổi mới trong 7 ngày nếu có lỗi kỹ thuật. Miễn phí thay ve ốc và '
                         . 'cân chỉnh gọng trọn đời.',
            ],
            [
                'icon'  => 'eye',
                'title' => 'Đo mắt miễn phí 100%',
                'desc'  => 'Quy trình đo khúc xạ chuyên sâu hoàn toàn miễn phí, chu đáo ngay '
                         . 'cả khi bạn chưa mua sản phẩm.',
            ],
            [
                'icon'  => 'clock',
                'title' => 'Cắt kính lấy ngay',
                'desc'  => 'Hệ thống máy mài tự động tại chỗ, hoàn thiện gọng tròng kính chuẩn '
                         . 'xác chỉ sau 20 phút.',
            ],
        ];

        $this->renderView('about/index', [
            'pageTitle'   => 'Giới thiệu Vin Eyewear — Chuyên gia kính cận',
            'metaDesc'    => 'Câu chuyện thương hiệu, giá trị cốt lõi và dịch vụ đo khúc xạ '
                           . 'của Vin Eyewear.',
            'values'      => $values,
            'exam'        => $exam,
            'commitments' => $commitments,
        ]);
    }
}
