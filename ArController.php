<?php

/**
 * Vin Eyewear - AR Controller
 * Handles AR try-on page requests
 */

class ArController extends BaseController
{
    public function tryon()
    {
        $data = [
            'title' => 'Thử kính AR - Vin Eyewear',
            'pageTitle' => 'Thử kính AR',
            'viewName' => 'ar/tryon'
        ];

        $this->renderView('ar/tryon', $data);
    }
}
