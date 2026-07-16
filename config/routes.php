<?php
/**
 * Vin Eyewear - Routes Configuration
 * Maps URL paths to Controller@Action
 */

return [
    '' => 'HomeController@index',
    '/' => 'HomeController@index',
    'home' => 'HomeController@index',
    'product' => 'ProductController@index',
    'product/detail' => 'ProductDetailController@index',
    'about' => 'AboutController@index',
    'event' => 'EventController@index',
    'ar' => 'ArController@tryon',
    'contact' => 'ContactController@index',
];