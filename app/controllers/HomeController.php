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
            'bestSellers' => $bestSellers,
        ]);
    }
}