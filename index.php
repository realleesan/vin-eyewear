<?php
/**
 * Vin Eyewear - Entry Point
 * Application bootstrap file
 */

// Toàn bộ view/dữ liệu là UTF-8 -> khai báo tường minh, không phụ thuộc vào
// default_charset trong php.ini của máy chủ (nếu bị đặt khác, tiếng Việt sẽ vỡ).
header('Content-Type: text/html; charset=UTF-8');

// Start session
session_start();

// Define absolute path constants
define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');
define('CORE_PATH', ROOT_PATH . '/core');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('VIEWS_PATH', APP_PATH . '/views');

// Load core files
require_once CORE_PATH . '/Router.php';
require_once CORE_PATH . '/BaseController.php';

// Load routes configuration
$routes = require_once CONFIG_PATH . '/routes.php';

// Initialize router and dispatch
$router = new Router($routes);
$router->dispatch();