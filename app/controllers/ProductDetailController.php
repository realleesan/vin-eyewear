<?php

require_once APP_PATH . '/models/ProductModel.php';

/**
 * ProductDetailController.php
 * Route: GET /product/detail
 * View:  product/detail
 *
 * MOCKUP: mọi thẻ sản phẩm ở Home/Product đều trỏ về trang này (1 trang detail
 * dùng chung, chưa nhận id). Khi có DB thì đọc id từ query string và gọi
 * ProductModel::find($id).
 */
class ProductDetailController extends BaseController
{
    public function index(): void
    {
        $product = ProductModel::featured();

        $this->renderView('product/detail', [
            'pageTitle'   => $product['name'] . ' - Vin Eyewear',
            'currentPage' => 'product',
            'product'     => $product,
            'related'     => ProductModel::related($product['id'], 4),
        ]);
    }
}
