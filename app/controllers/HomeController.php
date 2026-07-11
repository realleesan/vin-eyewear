<?php

/**
 * HomeController.php
 * Route: GET /
 * View:  home/index
 */
class HomeController extends BaseController
{
    public function index(): void
    {
        // --- Banner carousel (placeholder — thay bằng ảnh thật sau) ---
        $slides = [
            [
                'image'    => '/assets/images/banner/banner-1.jpg',
                'title'    => 'Phong Cách Của Bạn, Tầm Nhìn Của Chúng Tôi',
                'subtitle' => 'Bộ sưu tập kính mắt cao cấp — thiết kế tinh tế, chất lượng vượt trội.',
                'cta_text' => 'Khám phá ngay',
                'cta_href' => '/product',
            ],
            [
                'image'    => '/assets/images/banner/banner-2.jpg',
                'title'    => 'Thử Kính Ngay Tại Nhà Với Công Nghệ AR',
                'subtitle' => 'Không cần đến cửa hàng — đeo thử hàng trăm gọng kính qua camera của bạn.',
                'cta_text' => 'Thử kính AR',
                'cta_href' => '/ar',
            ],
            [
                'image'    => '/assets/images/banner/banner-3.jpg',
                'title'    => 'AI Phân Tích Khuôn Mặt — Chọn Kính Chuẩn Hơn',
                'subtitle' => 'Hệ thống AI nhận diện dáng mặt và đề xuất gọng kính phù hợp nhất cho bạn.',
                'cta_text' => 'Tìm kính phù hợp',
                'cta_href' => '/product',
            ],
        ];

        // --- Best seller (placeholder — thay bằng query Model sau) ---
        $bestSellers = [
            ['id' => 1, 'name' => 'Classic Round Gold',    'price' => 1_250_000, 'image' => '/assets/images/products/product-1.jpg', 'badge' => 'Bán chạy'],
            ['id' => 2, 'name' => 'Aviator Silver',        'price' => 990_000,  'image' => '/assets/images/products/product-2.jpg', 'badge' => 'Mới'],
            ['id' => 3, 'name' => 'Square Tortoise',       'price' => 1_450_000, 'image' => '/assets/images/products/product-3.jpg', 'badge' => ''],
            ['id' => 4, 'name' => 'Cat Eye Black',         'price' => 850_000,  'image' => '/assets/images/products/product-4.jpg', 'badge' => 'Sale'],
            ['id' => 5, 'name' => 'Wayfarer Matte Black',  'price' => 1_100_000, 'image' => '/assets/images/products/product-5.jpg', 'badge' => 'Bán chạy'],
            ['id' => 6, 'name' => 'Oval Rose Gold',        'price' => 1_350_000, 'image' => '/assets/images/products/product-6.jpg', 'badge' => 'Mới'],
            ['id' => 7, 'name' => 'Rimless Titanium',      'price' => 2_100_000, 'image' => '/assets/images/products/product-7.jpg', 'badge' => ''],
            ['id' => 8, 'name' => 'Sport Wrap Gunmetal',   'price' => 780_000,  'image' => '/assets/images/products/product-8.jpg', 'badge' => 'Sale'],
        ];

        $this->renderView('home/index', [
            'pageTitle'   => 'Vin Eyewear - Kính Mắt Cao Cấp',
            'currentPage' => 'home',
            'slides'      => $slides,
            'bestSellers' => $bestSellers,
        ]);
    }
}