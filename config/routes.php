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
    'category' => 'CategoryController@index',
    'about' => 'AboutController@index',
    'event' => 'EventController@index',
    'event/detail' => 'EventDetailController@index',
    'ar' => 'ArController@tryon',
    'contact' => 'ContactController@index',
];