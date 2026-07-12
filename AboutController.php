<?php
/**
 * Vin Eyewear - About Controller
 * Handles about page requests
 */

class AboutController extends BaseController
{
    public function index()
    {
        $data = [
            'title' => 'Giới thiệu - Vin Eyewear',
            'pageTitle' => 'Giới thiệu'
        ];
        
        $this->renderView('about/index', $data);
    }
}