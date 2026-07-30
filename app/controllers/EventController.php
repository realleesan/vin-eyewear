<?php
/**
 * Vin Eyewear - Event Controller
 * Handles event listing page requests
 */

class EventController extends BaseController
{
    private function getEventsData(): array 
    {
        return [
            'uu-dai-bo-suu-tap-di-san-mua-he' => [
                'id' => 'uu-dai-bo-suu-tap-di-san-mua-he',
                'title' => 'Chương Trình Ưu Đãi Mùa Hè: Giảm 20% Bộ Sưu Tập Di Sản',
                'category' => 'TIN ƯU ĐÃI',
                'date' => '15/07/2026 - 15/08/2026',
                'image' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?q=80&w=1200',
                'excerpt' => 'Giảm giá 20% cho toàn bộ dòng gọng kính Di Sản - cơ hội tuyệt vời để sở hữu những thiết kế kinh điển chế tác thủ công từ chất liệu Ý.',
                'intro' => 'Vin Eyewear hân hoan mang đến chương trình khuyến mãi đặc biệt mùa hè 2026 - giảm giá 20% cho toàn bộ bộ sưu tập Di Sản (Heritage Collection). Đây là cơ hội tuyệt vời để bạn sở hữu những thiết kế kinh điển, lấy cảm hứng từ nét đẹp cổ điển với mức giá tốt nhất trong năm.',
                'section1_title' => 'Về Bộ Sưu Tập Di Sản',
                'section1_content' => 'Bộ sưu tập Di Sản là dòng sản phẩm đặc biệt, kết hợp giữa nghệ thuật chế tác kính truyền thống và công nghệ hiện đại. Mỗi chiếc kính trong bộ sưu tập này đều được chế tác thủ công từ nhựa acetate Ý nguyên khối, với công nghệ nhuộm màu tròng kính độc quyền cho phép cá nhân hóa sắc độ theo phong cách riêng của người đeo.',
                'details' => [
                    ['label' => 'Thời gian', 'val' => '15/07/2026 - 15/08/2026'],
                    ['label' => 'Mức giảm giá', 'val' => '20% cho toàn bộ dòng sản phẩm Di Sản'],
                    ['label' => 'Địa điểm áp dụng', 'val' => 'Tất cả cửa hàng Vin Eyewear & Mua hàng trực tuyến'],
                    ['label' => 'Quà tặng đính kèm', 'val' => 'Túi đựng kính phiên bản giới hạn cho đơn hàng từ 2 triệu đồng'],
                    ['label' => 'Chế độ bảo hành', 'val' => '12 tháng chính hãng']
                ],
                'steps' => [
                    'Ghé thăm cửa hàng Vin Eyewear tại Long Biên hoặc Tây Hồ để trải nghiệm trực tiếp',
                    'Truy cập website vineyewear.com và đặt hàng trực tuyến với mã giảm giá DISAN20',
                    'Sử dụng tính năng thử kính ảo AR trên điện thoại để chọn mẫu kính phù hợp trước khi quyết định'
                ],
                'note' => 'Chương trình không áp dụng đồng thời với các chương trình khuyến mãi khác. Số lượng quà tặng có hạn và sẽ ưu tiên cho những khách hàng đặt hàng sớm nhất.'
            ],
            'buoi-chia-se-bao-quan-kinh-nhua-cao-cap' => [
                'id' => 'buoi-chia-se-bao-quan-kinh-nhua-cao-cap',
                'title' => 'Buổi Chia Sẻ: Nghệ Thuật Chăm Sóc Và Bảo Quản Kính Nhựa Nhập Khẩu',
                'category' => 'SỰ KIỆN',
                'date' => '05/08/2026',
                'image' => 'https://images.unsplash.com/photo-1572635196237-14b3f281503f?q=80&w=1200',
                'excerpt' => 'Trải nghiệm buổi hướng dẫn chuyên sâu từ các nghệ nhân chế tác kính về cách đánh bóng và bảo quản gọng kính cao cấp đúng chuẩn.',
                'intro' => 'Gọng kính nhựa Acetate nguyên khối là một tác phẩm nghệ thuật tỉ mỉ. Để giữ cho chiếc kính luôn sáng bóng và bền đẹp như mới, Vin Eyewear tổ chức buổi chia sẻ đặc quyền dành riêng cho cộng đồng những người yêu thích kính mắt thủ công.',
                'section1_title' => 'Nội Dung Buổi Giao Lưu',
                'section1_content' => 'Tại buổi chia sẻ, các nghệ nhân của Vin Eyewear sẽ hướng dẫn bạn kỹ thuật đánh bóng thủ công bằng dung dịch chuyên dụng, cách xử lý các vết xước nhẹ tại nhà và quy trình cân chỉnh gọng kính để luôn ôm sát khuôn mặt mà không gây biến dạng chất liệu.',
                'details' => [
                    ['label' => 'Thời gian', 'val' => '09:30 - 11:30, Ngày 05/08/2026'],
                    ['label' => 'Địa điểm', 'val' => 'Cửa hàng trung tâm Tây Hồ, Hà Nội'],
                    ['label' => 'Chi phí tham dự', 'val' => 'Miễn phí (Giới hạn 20 khách đăng ký sớm nhất)'],
                    ['label' => 'Quà tặng đặc quyền', 'val' => 'Bộ dụng cụ vệ sinh kính cao cấp trị giá 500.000 VNĐ']
                ],
                'steps' => [
                    'Điền thông tin đăng ký tại mẫu phiếu xác nhận trên trang web Vin Eyewear',
                    'Nhận thư điện tử xác nhận mã vé mời từ hệ thống',
                    'Mang theo chiếc kính Vin Eyewear yêu thích của bạn đến tham dự buổi giao lưu'
                ],
                'note' => 'Để bảo đảm không gian trải nghiệm cá nhân hóa tốt nhất, chúng tôi chỉ tiếp nhận tối đa 20 khách hàng. Vui lòng xác nhận tham dự trước ngày 03/08/2026.'
            ],
            'ra-mat-dong-kinh-titan-sieu-nhe' => [
                'id' => 'ra-mat-dong-kinh-titan-sieu-nhe',
                'title' => 'Ra Mắt Dòng Kính Titan Siêu Nhẹ Mới Mùa Thu 2026',
                'category' => 'SẢN PHẨM MỚI',
                'date' => '20/08/2026',
                'image' => 'https://images.unsplash.com/photo-1508296695146-257a814070b4?q=80&w=1200',
                'excerpt' => 'Đột phá công nghệ chế tác với chất liệu Titan hàng không siêu nhẹ, siêu bền và an toàn tuyệt đối cho làn da.',
                'intro' => 'Vin Eyewear tự hào giới thiệu dòng gọng kính Titan Siêu Nhẹ thế hệ mới - định nghĩa lại sự thoải mái trong sinh hoạt hàng ngày. Khung kính nhẹ chỉ 8 gram nhưng sở hữu độ bền cùng khả năng chịu lực vượt trội.',
                'section1_title' => 'Công Nghệ Titan Hàng Không',
                'section1_content' => 'Sử dụng hợp kim Titan cao cấp nhập khẩu từ Nhật Bản, dòng sản phẩm này mang lại độ đàn hồi tối đa, hoàn toàn không bị ăn mòn bởi mồ hôi và an toàn tuyệt đối ngay cả với những làn da nhạy cảm nhất.',
                'details' => [
                    ['label' => 'Ngày mở bán', 'val' => '20/08/2026 trên toàn hệ thống'],
                    ['label' => 'Trọng lượng sản phẩm', 'val' => 'Chỉ từ 8.2 gram'],
                    ['label' => 'Tông màu chủ đạo', 'val' => 'Đen Nhám, Vàng Đồng Ánh Kim, Xám Titan'],
                    ['label' => 'Ưu đãi trải nghiệm', 'val' => 'Tặng kèm tròng kính chống ánh sáng xanh cho 50 đơn hàng đặt trước đầu tiên']
                ],
                'steps' => [
                    'Đặt hàng trước trên website để nhận vị trí ưu tiên quà tặng',
                    'Đến hệ thống cửa hàng thử trực tiếp các kiểu dáng kính phù hợp nhất',
                    'Đo khám thị lực miễn phí với thiết bị đo khúc xạ tự động thế hệ mới'
                ],
                'note' => 'Quà tặng tròng kính cao cấp chỉ áp dụng cho khách hàng hoàn tất đặt cọc trước ngày 19/08/2026.'
            ],
            'trien-lam-nghe-thuat-khung-kinh-qua-cac-thap-ky' => [
                'id' => 'trien-lam-nghe-thuat-khung-kinh-qua-cac-thap-ky',
                'title' => 'Triển Lãm: Khung Kính Qua Các Thập Kỷ Ký Ức',
                'category' => 'TRIỂN LÃM',
                'date' => '01/09/2026 - 10/09/2026',
                'image' => 'https://images.unsplash.com/photo-1509695507497-903c140c43b0?q=80&w=1200',
                'excerpt' => 'Hành trình ngược thời gian khám phá lịch sử phát triển của thiết kế kính mắt từ thế kỷ trước đến các biểu tượng thời trang đương đại.',
                'intro' => 'Kính mắt không chỉ là công cụ hỗ trợ tầm nhìn, đó còn là tuyên ngôn về văn hóa và thời trang. Triển lãm không gian mở tái hiện lại các mốc lịch sử phát triển của thiết kế kính qua hơn 100 năm.',
                'section1_title' => 'Không Gian Nghệ Thuật Độc Đáo',
                'section1_content' => 'Triển lãm trưng bày hơn 100 mẫu kính cổ hiếm có cùng các bản vẽ phác thảo tay nguyên bản từ các xưởng chế tác lâu đời tại Ý và Nhật Bản.',
                'details' => [
                    ['label' => 'Thời gian mở cửa', 'val' => '09:00 - 21:00 hàng ngày (01/09 - 10/09/2026)'],
                    ['label' => 'Địa điểm diễn ra', 'val' => 'Trung tâm Không gian Nghệ thuật Long Biên, Hà Nội'],
                    ['label' => 'Vé vào cửa', 'val' => 'Tự do và hoàn toàn miễn phí']
                ],
                'steps' => [
                    'Đăng ký xác nhận trực tuyến để nhận bưu thiếp lưu niệm tại cổng triển lãm',
                    'Tham gia tour trải nghiệm có người thuyết minh vào lúc 10 giờ và 15 giờ mỗi ngày',
                    'Trải nghiệm khu vực chụp ảnh phong cách hoài cổ thập niên 70'
                ],
                'note' => 'Vui lòng giữ gìn trật tự và không chạm tay trực tiếp vào các hiện vật trưng bày trong tủ kính.'
            ],
            'chuong-trinh-kham-thi-luc-va-tu-van-dang-kinh' => [
                'id' => 'chuong-trinh-kham-thi-luc-va-tu-van-dang-kinh',
                'title' => 'Chương Trình Khám Thị Lực Và Tư Vấn Dáng Kính Miễn Phí',
                'category' => 'TIN ƯU ĐÃI',
                'date' => '25/08/2026 - 15/09/2026',
                'image' => 'https://images.unsplash.com/photo-1574258495973-f010dfbb5371?q=80&w=1200',
                'excerpt' => 'Khám thị lực chuẩn y khoa 12 bước và tư vấn chọn kiểu dáng kính phù hợp với cấu trúc khuôn mặt hoàn toàn miễn phí.',
                'intro' => 'Đôi mắt sáng khỏe là nền tảng cho mọi hoạt động hàng ngày. Mùa tựu trường và làm việc mới này, Vin Eyewear đồng hành cùng bạn với dịch vụ chăm sóc và kiểm tra thị lực toàn diện.',
                'section1_title' => 'Đo Thị Lực Chuẩn Y Khoa 12 Bước',
                'section1_content' => 'Đội ngũ chuyên viên kiểm tra khúc xạ giàu kinh nghiệm kết hợp cùng trang thiết bị hiện đại nhập khẩu từ Đức đảm bảo kết quả đo độ cận, loạn, viễn chính xác nhất.',
                'details' => [
                    ['label' => 'Thời gian áp dụng', 'val' => '25/08/2026 - 15/09/2026'],
                    ['label' => 'Dịch vụ bao gồm', 'val' => 'Đo khám mắt 0 đồng & Trợ giá 15% khi thu cũ đổi gọng mới'],
                    ['label' => 'Đối tượng tham gia', 'val' => 'Học sinh, sinh viên và người đi làm']
                ],
                'steps' => [
                    'Đặt lịch hẹn đo mắt trước qua tổng đài hoặc website để không phải chờ đợi',
                    'Đến cửa hàng gần nhất thực hiện quy trình kiểm tra thị lực 12 bước',
                    'Nhận tư vấn lựa chọn dáng gọng kính tôn lên đường nét khuôn mặt'
                ],
                'note' => 'Chương trình thu gọng cũ đổi gọng mới áp dụng cho tất cả các thương hiệu gọng kính hiện có của khách hàng.'
            ],
            'dem-tiec-trai-nghiem-nhuom-mau-trong-kinh-thu-cong' => [
                'id' => 'dem-tiec-trai-nghiem-nhuom-mau-trong-kinh-thu-cong',
                'title' => 'Đêm Tiệc Trải Nghiệm: Nhuộm Màu Tròng Kính Thủ Công',
                'category' => 'SỰ KIỆN',
                'date' => '30/09/2026',
                'image' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?q=80&w=1200',
                'excerpt' => 'Đêm tiệc kín dành riêng cho khách hàng thân thiết trải nghiệm phối màu tròng kính theo yêu cầu trực tiếp cùng chuyên gia.',
                'intro' => 'Dành riêng cho những khách hàng yêu thích sự độc bản, đêm tiệc mang tới đặc quyền pha phối màu tròng kính thủ công vô cùng đặc biệt.',
                'section1_title' => 'Trải Nghiệm Nhuộm Màu Theo Yêu Cầu',
                'section1_content' => 'Khách mời sẽ được trực tiếp lựa chọn độ chuyển màu, phối các dải màu độc đáo cho tròng kính dưới sự hỗ trợ chuyên sâu từ chuyên gia màu sắc.',
                'details' => [
                    ['label' => 'Thời gian', 'val' => '18:00 - 21:00, Ngày 30/09/2026'],
                    ['label' => 'Hình thức', 'val' => 'Tiệc nhẹ & Trạm trải nghiệm làm kính thủ công'],
                    ['label' => 'Yêu cầu tham dự', 'val' => 'Dành riêng cho khách mời có thư xác nhận']
                ],
                'steps' => [
                    'Xác nhận tham dự qua liên kết phản hồi đính kèm trong thư mời',
                    'Lựa chọn khung giờ trải nghiệm dịch vụ riêng biệt',
                    'Nhận sản phẩm kính hoàn thiện độc bản ngay tại đêm tiệc'
                ],
                'note' => 'Trang phục tham dự khuyến nghị: Lịch sự / Có điểm nhấn màu vàng đồng.'
            ]
        ];
    }
    public function index(): void
    {
        $eventsData = $this->getEventsData();
        
        $data = [
            'title' => 'Sự kiện - Vin Eyewear',
            'pageTitle' => 'Sự kiện',
            'eventsData' => $eventsData
        ];
        
        $this->renderView('event/index', $data);
    }

}