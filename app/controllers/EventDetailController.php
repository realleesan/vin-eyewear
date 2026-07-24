<?php
/**
 * Vin Eyewear - Event Detail Controller
 * Handles event detail page requests
 */

class EventDetailController extends BaseController
{
    public function index(): void
    {
        $data = [
            'title' => 'Chi tiết sự kiện - Vin Eyewear',
            'pageTitle' => 'Chi tiết sự kiện'
        ];
        
        $this->renderView('event/detail', $data);
    }
}
