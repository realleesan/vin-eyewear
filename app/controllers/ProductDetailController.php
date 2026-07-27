<?php

require_once APP_PATH . '/models/ProductModel.php';

/**
 * ProductDetailController.php
 * Route: GET /product/detail?id={id}
 * View:  product/detail
 *
 * Thẻ sản phẩm ở Home/Product/Related đều trỏ về đây kèm ?id=.
 *   - không có ?id=      → sản phẩm mặc định (link cũ, bookmark)
 *   - id sai/không tồn tại → 404, KHÔNG im lặng đổ về sản phẩm mặc định
 */
class ProductDetailController extends BaseController
{
    public function index(): void
    {
        $raw = $_GET['id'] ?? null;

        if ($raw === null || $raw === '') {
            $id = ProductModel::FEATURED_ID;
        } else {
            $id = filter_var($raw, FILTER_VALIDATE_INT);
        }

        $product = $id === false ? null : ProductModel::detail($id);

        if ($product === null) {
            require_once APP_PATH . '/controllers/ErrorController.php';
            (new ErrorController())->notFound();

            return;
        }

        $this->renderView('product/detail', [
            'pageTitle'   => $product['name'] . ' - Vin Eyewear',
            'currentPage' => 'product',
            'product'     => $product,
            'related'     => ProductModel::related($product['id'], 4),
        ]);
    }
}
