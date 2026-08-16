<?php

/**
 * Vin Eyewear — điểm vào duy nhất của ứng dụng.
 *
 * Mọi request đều được .htaccess đẩy về đây, nên file này chỉ làm hai việc:
 * khai báo đường dẫn gốc rồi giao lại cho App.
 *
 * Phần khởi động (nạp helper, cấu hình, phiên, autoload) nằm trong
 * core/App.php để script CLI (import dữ liệu, cron) dùng lại được mà không
 * phải kéo theo tầng định tuyến HTTP.
 */

// Hằng đường dẫn tuyệt đối — dùng thay cho đường dẫn tương đối, vì đường dẫn
// tương đối được tính theo thư mục làm việc hiện tại và sẽ sai khi script
// được gọi từ nơi khác.
define('ROOT_PATH',   __DIR__);
define('APP_PATH',    ROOT_PATH . '/app');
define('CORE_PATH',   ROOT_PATH . '/core');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('VIEWS_PATH',  APP_PATH . '/views');

require_once CORE_PATH . '/App.php';

App::boot();
App::run();
