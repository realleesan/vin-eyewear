<?php
/**
 * Vin Eyewear - Product Controller
 * Handles product page requests
 */

class ProductController extends BaseController
{
    public function index()
    {
        $data = [
            'title' => 'Sản phẩm - Vin Eyewear',
            'pageTitle' => 'Sản phẩm'
        ];
        
        $this->renderView('product/index', $data);
    }
}