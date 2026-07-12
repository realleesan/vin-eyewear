<?php

/**
 * ProductController.php
 * Route: GET /product
 * View:  product/index
 */
class ProductController extends BaseController
{
    public function index(): void
    {
        // --- Danh sách 12 sản phẩm tĩnh (placeholder — thay bằng Model sau) ---
        $products = [
            ['id' => 1,  'name' => 'Classic Round Gold',    'category' => 'tron',    'price' => 1_250_000, 'image' => '/assets/images/products/product-1.jpg',  'badge' => 'Bán chạy'],
            ['id' => 2,  'name' => 'Aviator Silver',        'category' => 'aviator', 'price' => 990_000,   'image' => '/assets/images/products/product-2.jpg',  'badge' => 'Mới'],
            ['id' => 3,  'name' => 'Square Tortoise',       'category' => 'vuong',   'price' => 1_450_000, 'image' => '/assets/images/products/product-3.jpg',  'badge' => ''],
            ['id' => 4,  'name' => 'Cat Eye Black',         'category' => 'cat-eye', 'price' => 850_000,   'image' => '/assets/images/products/product-4.jpg',  'badge' => 'Sale'],
            ['id' => 5,  'name' => 'Wayfarer Matte Black',  'category' => 'vuong',   'price' => 1_100_000, 'image' => '/assets/images/products/product-5.jpg',  'badge' => 'Bán chạy'],
            ['id' => 6,  'name' => 'Oval Rose Gold',        'category' => 'tron',    'price' => 1_350_000, 'image' => '/assets/images/products/product-6.jpg',  'badge' => 'Mới'],
            ['id' => 7,  'name' => 'Rimless Titanium',      'category' => 'rimless', 'price' => 2_100_000, 'image' => '/assets/images/products/product-7.jpg',  'badge' => ''],
            ['id' => 8,  'name' => 'Sport Wrap Gunmetal',   'category' => 'sport',   'price' => 780_000,   'image' => '/assets/images/products/product-8.jpg',  'badge' => 'Sale'],
            ['id' => 9,  'name' => 'Browline Havana',       'category' => 'vuong',   'price' => 1_180_000, 'image' => '/assets/images/products/product-9.jpg',  'badge' => ''],
            ['id' => 10, 'name' => 'Aviator Gold Mirror',   'category' => 'aviator', 'price' => 1_050_000, 'image' => '/assets/images/products/product-10.jpg', 'badge' => 'Bán chạy'],
            ['id' => 11, 'name' => 'Cat Eye Marble',        'category' => 'cat-eye', 'price' => 920_000,   'image' => '/assets/images/products/product-11.jpg', 'badge' => 'Mới'],
            ['id' => 12, 'name' => 'Round Clear Lens',      'category' => 'tron',    'price' => 650_000,   'image' => '/assets/images/products/product-12.jpg', 'badge' => ''],
        ];

        $this->renderView('product/index', [
            'pageTitle'   => 'Sản Phẩm - Vin Eyewear',
            'currentPage' => 'product',
            'products'    => $products,
        ]);
    }
}