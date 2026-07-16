<?php
/**
 * Vin Eyewear - Event Controller
 * Handles event page requests
 */

class EventController extends BaseController
{
    public function index()
    {
        $eventData = [
            1 => [
                'date' => '15/07/2026',
                'title' => 'Khuyến Mãi Mùa Hè',
                'description' => '
                    <p>Chào đón mùa hè sôi động, Vin Eyewear mang đến chương trình khuyến mãi đặc biệt với giảm giá 20% cho tất cả sản phẩm kính mắt. Đây là cơ hội tuyệt vời để bạn sở hữu những chiếc kính chất lượng cao với mức giá ưu đãi.</p>
                    <p>Chương trình áp dụng cho cả gọng kính thời trang và kính thuốc từ các thương hiệu uy tín. Đặc biệt, khi mua trọn bộ (gọng + tròng), bạn sẽ được tặng thêm bộ vệ sinh kính cao cấp.</p>
                    <p><strong>Thời gian áp dụng:</strong> 15/07/2026 - 15/08/2026</p>
                    <p><strong>Địa điểm áp dụng:</strong> Cả hai cơ sở Long Biên và Tây Hồ</p>
                ',
                'image' => 'https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=800&h=500&fit=crop'
            ],
            2 => [
                'date' => '20/08/2026',
                'title' => 'Ra Mắt BST Thu Đông 2026',
                'description' => '
                    <p>Vin Eyewear hân hoan giới thiệu bộ sưu tập kính mắt Thu Đông 2026 với những thiết kế đột phá, kết hợp giữa nét cổ điển và hiện đại. BST mới mang đến những lựa chọn phong cách đa dạng cho mọi dịp sử dụng.</p>
                    <p>Nổi bật trong bộ sưu tập là dòng gọng kính acetate với màu sắc trầm ấm, phù hợp với trang phục thu đông, cùng các tròng kính chống ánh sáng xanh thế hệ mới bảo vệ mắt tối ưu.</p>
                    <p><strong>Sự kiện ra mắt:</strong> 20/08/2026 tại cơ sở Long Biên</p>
                    <p><strong>Ưu đãi đặc biệt:</strong> Giảm 15% cho BST mới trong tuần đầu ra mắt</p>
                ',
                'image' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&h=500&fit=crop'
            ],
            3 => [
                'date' => '01/09/2026',
                'title' => 'Tư Vấn Miễn Phí',
                'description' => '
                    <p>Dịch vụ tư vấn chuyên nghiệp tại Vin Eyewear giúp bạn tìm được chiếc kính hoàn hảo nhất. Đội ngũ kỹ thuật viên có kinh nghiệm sẽ đo mắt và tư vấn miễn phí, đảm bảo bạn chọn được sản phẩm phù hợp nhất.</p>
                    <p>Dịch vụ bao gồm: đo khúc xạ, đo khoảng cách đồng tử (PD), tư vấn chọn gọng phù hợp khuôn mặt, và hướng dẫn sử dụng kính đúng cách.</p>
                    <p><strong>Thời gian:</strong> 01/09/2026 - 30/09/2026</p>
                    <p><strong>Địa điểm:</strong> Cả hai cơ sở Long Biên và Tây Hồ</p>
                    <p><strong>Đăng ký trước:</strong> Ưu tiên phục vụ khách hàng đã đặt lịch</p>
                ',
                'image' => 'https://images.unsplash.com/photo-1556306535-0f09a537f0a3?w=800&h=500&fit=crop'
            ]
        ];

        $data = [
            'title' => 'Sự kiện - Vin Eyewear',
            'pageTitle' => 'Sự kiện',
            'eventData' => $eventData
        ];
        
        $this->renderView('event/index', $data);
    }
}