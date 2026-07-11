<?php
/**
 * Vin Eyewear - Home Controller
 * Handles home page requests
 */

class HomeController extends BaseController
{
    public function index()
    {
        $data = [
            'title' => 'Trang chủ - Vin Eyewear',
            'pageTitle' => 'Trang chủ'
        ];
        
        $this->renderView('home/index', $data);
    }
}