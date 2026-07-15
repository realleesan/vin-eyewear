<?php
/**
 * Vin Eyewear - AR Controller
 * Handles AR try-on page requests
 */

class ArController extends BaseController
{
    public function tryon(): void
    {
        $glasses = [
            ['id' => 'classic-round', 'name' => 'Classic Round Gold', 'overlay' => '/assets/images/ar/classic-round.svg'],
            ['id' => 'aviator',       'name' => 'Aviator Silver',     'overlay' => '/assets/images/ar/aviator.svg'],
            ['id' => 'square-tortoise','name' => 'Square Tortoise',   'overlay' => '/assets/images/ar/square-tortoise.svg'],
            ['id' => 'cat-eye',       'name' => 'Cat Eye Black',      'overlay' => '/assets/images/ar/cat-eye.svg'],
        ];

        $this->renderView('ar/tryon', [
            'title'    => 'Thử kính AR - Vin Eyewear',
            'pageTitle'=> 'Thử kính AR',
            'glasses'  => $glasses,
        ]);
    }
}