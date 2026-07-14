<?php
/**
 * Vin Eyewear - Contact Controller
 * Handles contact page requests
 */

class ContactController extends BaseController
{
    public function index()
    {
        $stores = [
            [
                'id' => 'long-bien',
                'name' => 'Vin Eyewear - Long Biên',
                'tagline' => 'Cơ sở chính',
                'address' => '261 Ngọc Lâm, P. Bồ Đề, Q. Long Biên, TP. Hà Nội (ngã tư Hồng Tiến)',
                'phone' => '0912 345 678',
                'hours' => 'Thứ 2 - Chủ nhật: 08:30 - 21:30',
                'map_url' => 'https://maps.google.com/maps?q=261%20Ng%E1%BB%8Dc%20L%C3%A2m,%20B%E1%BB%93%20%C4%90%E1%BB%81,%20Long%20Bi%C3%AAn,%20H%C3%A0%20N%E1%BB%99i&t=&z=16&ie=UTF8&iwloc=&output=embed',
                'image' => '/assets/images/store-longbien.png'
            ],
            [
                'id' => 'tay-ho',
                'name' => 'Vin Eyewear - Tây Hồ',
                'tagline' => 'Mới khai trương',
                'address' => '46 Hoàng Hoa Thám, P. Thụy Khuê, Q. Tây Hồ, TP. Hà Nội',
                'phone' => '0987 654 321',
                'hours' => 'Thứ 2 - Chủ nhật: 08:30 - 22:00',
                'map_url' => 'https://maps.google.com/maps?q=46%20Ho%C3%A0ng%20Hoa%20Th%C3%A1m,%20Th%E1%BB%A5y%20Khu%C3%AA,%20T%C3%A2y%20H%E1%BB%93,%20H%C3%A0%20N%E1%BB%99i&t=&z=16&ie=UTF8&iwloc=&output=embed',
                'image' => '/assets/images/store-tayho.png'
            ]
        ];

        $data = [
            'title' => 'Liên hệ - Vin Eyewear',
            'pageTitle' => 'Liên hệ',
            'stores' => $stores
        ];
        
        $this->renderView('contact/index', $data);
    }
}