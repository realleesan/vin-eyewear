<?php

require_once APP_PATH . '/models/ProductModel.php';

/**
 * ProductController.php
 * Route: GET /product
 * View:  product/index
 */
class ProductController extends BaseController
{
    public function index(): void
    {
        $this->renderView('product/index', [
            'pageTitle'   => 'Sản Phẩm - Vin Eyewear',
            'currentPage' => 'product',
            'products'    => ProductModel::all(),
        ]);
    }
}
