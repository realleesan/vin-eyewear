<?php
/**
 * Vin Eyewear - Contact Controller
 * Handles contact page requests
 */

class ContactController extends BaseController
{
    public function index()
    {
        $data = [
            'title' => 'Liên hệ - Vin Eyewear',
            'pageTitle' => 'Liên hệ'
        ];
        
        $this->renderView('contact/index', $data);
    }
}