<?php
/**
 * Vin Eyewear - Category Controller
 * Handles category page requests
 */

class CategoryController extends BaseController
{
    public function index(): void
    {
        $data = [
            'title' => 'Danh mục - Vin Eyewear',
            'pageTitle' => 'Danh mục'
        ];
        
        $this->renderView('category/index', $data);
    }
}
