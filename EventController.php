<?php
/**
 * Vin Eyewear - Event Controller
 * Handles event page requests
 */

class EventController extends BaseController
{
    public function index()
    {
        $data = [
            'title' => 'Sự kiện - Vin Eyewear',
            'pageTitle' => 'Sự kiện'
        ];
        
        $this->renderView('event/index', $data);
    }
}