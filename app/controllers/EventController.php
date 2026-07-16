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
                'image' => 'https://moscot.com/cdn/shop/files/1920x860_-HP_BANNER-DESKTOP-CUSTOM_MADE_TINT_2_1.jpg?v=1783603402&width=1920'
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
                'image' => 'https://moscot.com/cdn/shop/files/1400x900_-FLOW-CMT_2_1.jpg?v=1783603988&width=1200'
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
                'image' => 'https://moscot.com/cdn/shop/files/1080x1080_CRAFTSMANSHIP_-_HOMEPAGE_BANNER_MOBILE_1.jpg?v=1742324666&width=1920'
            ],
            4 => [
                'date' => '10/10/2026',
                'title' => 'Kính Mát Eyewear',
                'description' => '
                    <p>Bộ sưu tập kính mát mới nhất từ Vin Eyewear với thiết kế hiện đại và chất lượng cao cấp. Bảo vệ mắt khỏi tia UV đồng thời nâng tầm phong cách của bạn.</p>
                    <p>Đa dạng mẫu mã từ cổ điển đến hiện đại, phù hợp với mọi khuôn mặt và phong cách sử dụng.</p>
                    <p><strong>Thời gian:</strong> 10/10/2026 - 31/10/2026</p>
                    <p><strong>Ưu đãi:</strong> Mua 1 tặng 1 bộ vệ sinh kính</p>
                ',
                'image' => 'https://moscot.com/cdn/shop/files/920x920_-PROMO_GRID-EYEGLASSES-02_2_1.jpg?v=1782759499&width=920'
            ],
            5 => [
                'date' => '15/11/2026',
                'title' => 'Kính Mát Sunglasses',
                'description' => '
                    <p>Kính mát thời thượng với thiết kế sang trọng và chất liệu cao cấp. Lựa chọn hoàn hảo cho những ngày nắng đẹp.</p>
                    <p>Công nghệ chống tia UV400, chống chói, bảo vệ mắt tối ưu trong mọi điều kiện ánh sáng.</p>
                    <p><strong>Thời gian:</strong> 15/11/2026 - 30/11/2026</p>
                    <p><strong>Ưu đãi:</strong> Giảm 25% cho tất cả kính mát</p>
                ',
                'image' => 'https://moscot.com/cdn/shop/files/920x920_-PROMO_GRID-SUNGLASSES-02_2_1.jpg?v=1782759503&width=920'
            ],
            6 => [
                'date' => '01/12/2026',
                'title' => 'Bộ Sưu Tập Đặc Biệt',
                'description' => '
                    <p>Bộ sưu tập đặc biệt nhân dịp kỷ niệm thành lập Vin Eyewear. Những thiết kế độc bản, giới hạn số lượng.</p>
                    <p>Mỗi chiếc kính là tác phẩm nghệ thuật, kết hợp giữa thủ công truyền thống và công nghệ hiện đại.</p>
                    <p><strong>Thời gian:</strong> 01/12/2026 - 31/12/2026</p>
                    <p><strong>Ưu đãi:</strong> Giảm 30% cho BST đặc biệt</p>
                ',
                'image' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&h=600&fit=crop'
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