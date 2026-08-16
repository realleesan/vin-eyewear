<?php

/**
 * core/Router.php
 *
 * Đối chiếu URL với bảng route rồi gọi controller tương ứng.
 *
 * Ba loại route, xét theo đúng thứ tự này:
 *   1. Khớp chính xác      'san-pham'        => 'ProductController@index'
 *   2. Chuyển hướng        'product'         => 'redirect:/san-pham'
 *   3. Có tham số          'san-pham/{slug}' => 'ProductDetailController@show'
 *
 * Khớp chính xác luôn được xét TRƯỚC route có tham số. Nhờ vậy 'san-pham/moi'
 * (nếu sau này thêm) không bị 'san-pham/{slug}' nuốt mất.
 */

class Router
{
    private array $routes;

    /** Các route có tham số, tách sẵn lúc khởi tạo để không phải lọc lại mỗi lần. */
    private array $patternRoutes = [];

    public function __construct(array $routes)
    {
        $this->routes = $routes;

        foreach ($routes as $path => $target) {
            if (str_contains($path, '{')) {
                $this->patternRoutes[$path] = $target;
            }
        }
    }

    public function dispatch(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = trim($uri, '/');

        // File tĩnh có thật thì trả thẳng — chỉ dùng khi chạy bằng server
        // built-in của PHP (php -S). Trên Apache/nginx, .htaccess đã chặn
        // trước nên nhánh này không bao giờ chạy tới.
        if ($uri !== '' && $this->serveStaticFile($uri)) {
            return;
        }

        // 1. Khớp chính xác
        if (array_key_exists($uri, $this->routes)) {
            $target = $this->routes[$uri];

            if (str_starts_with($target, 'redirect:')) {
                $this->redirectPermanent(substr($target, strlen('redirect:')));
                return;
            }

            $this->callController($target);
            return;
        }

        // 2. Route có tham số
        if ($this->dispatchPattern($uri)) {
            return;
        }

        $this->handle404();
    }

    /**
     * Đối chiếu URL với các route dạng 'su-kien/{slug}'.
     *
     * Giá trị bắt được truyền vào action theo đúng thứ tự xuất hiện.
     * Trả về true nếu đã xử lý xong.
     */
    private function dispatchPattern(string $uri): bool
    {
        $uriSegments = $uri === '' ? [] : explode('/', $uri);

        foreach ($this->patternRoutes as $pattern => $target) {
            $patternSegments = explode('/', $pattern);

            // Số đoạn phải bằng nhau — loại nhanh phần lớn trường hợp
            if (count($patternSegments) !== count($uriSegments)) {
                continue;
            }

            $params  = [];
            $matched = true;

            foreach ($patternSegments as $i => $segment) {
                if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                    // Đoạn rỗng không tính là khớp: '/su-kien/' không phải
                    // là một slug hợp lệ, phải để nó rơi xuống 404.
                    if ($uriSegments[$i] === '') {
                        $matched = false;
                        break;
                    }
                    $params[] = $uriSegments[$i];
                    continue;
                }

                if ($segment !== $uriSegments[$i]) {
                    $matched = false;
                    break;
                }
            }

            if ($matched) {
                $this->callController($target, $params);
                return true;
            }
        }

        return false;
    }

    /**
     * Nạp controller rồi gọi action.
     *
     * @param string $target 'TenController@action'
     * @param array  $params tham số bắt được từ URL
     */
    private function callController(string $target, array $params = []): void
    {
        [$controllerClass, $action] = explode('@', $target);

        // Tìm cả trong controllers/ lẫn controllers/Admin/
        $candidates = [
            APP_PATH . '/controllers/' . $controllerClass . '.php',
            APP_PATH . '/controllers/Admin/' . $controllerClass . '.php',
        ];

        $file = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && filesize($candidate) > 0) {
                $file = $candidate;
                break;
            }
        }

        // File không tồn tại HOẶC rỗng (controller chưa được viết) -> 404.
        // Kiểm filesize vì dự án có sẵn nhiều file controller 0 byte; require
        // một file rỗng sẽ không định nghĩa class nào và gây lỗi khó hiểu.
        if ($file === null) {
            $this->handle404();
            return;
        }

        require_once $file;

        if (!class_exists($controllerClass)) {
            $this->handle404();
            return;
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $action)) {
            $this->handle404();
            return;
        }

        try {
            $controller->$action(...$params);
        } catch (Throwable $e) {
            // Ghi log đầy đủ, nhưng chỉ hiện chi tiết khi đang gỡ lỗi.
            // Không có dòng này thì lỗi biến mất không dấu vết.
            error_log(sprintf(
                '[Router] %s@%s lỗi: %s tại %s:%d',
                $controllerClass,
                $action,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            if (config('app.debug')) {
                throw $e;
            }

            $this->handle500();
        }
    }

    /**
     * Trả file tĩnh khi chạy bằng `php -S`. Trả về true nếu đã trả file.
     */
    private function serveStaticFile(string $uri): bool
    {
        $file = realpath(ROOT_PATH . '/' . $uri);

        // realpath() + kiểm tiền tố chặn path traversal: không có bước này,
        // /assets/../../../etc/passwd sẽ đọc được file ngoài thư mục dự án.
        if ($file === false
            || !is_file($file)
            || !str_starts_with($file, ROOT_PATH . DIRECTORY_SEPARATOR)) {
            return false;
        }

        // Chỉ phục vụ các đuôi tài nguyên tĩnh đã biết. Danh sách cho phép
        // (không phải danh sách cấm) để .php, .env, .sql không bao giờ bị
        // đọc thô ra ngoài dù có lọt vào thư mục public.
        $mimes = [
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'glb'   => 'model/gltf-binary',
            'gltf'  => 'model/gltf+json',
        ];

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (!isset($mimes[$ext])) {
            return false;
        }

        header('Content-Type: ' . $mimes[$ext]);
        readfile($file);

        return true;
    }

    /**
     * Chuyển hướng vĩnh viễn (301) — dùng cho các URL tiếng Anh cũ.
     */
    private function redirectPermanent(string $to): void
    {
        header('Location: ' . $to, true, 301);
        exit;
    }

    private function handle404(): void
    {
        http_response_code(404);
        $this->renderError('notFound', '/errors/404.php');
        exit;
    }

    private function handle500(): void
    {
        http_response_code(500);
        $this->renderError('serverError', '/errors/500.php');
        exit;
    }

    /**
     * Hiện trang lỗi qua ErrorController, có đường lui khi chính controller
     * đó cũng hỏng — trang lỗi mà lỗi thì người dùng chỉ thấy màn hình trắng.
     */
    private function renderError(string $action, string $fallbackFile): void
    {
        $file = APP_PATH . '/controllers/ErrorController.php';

        if (is_file($file) && filesize($file) > 0) {
            require_once $file;

            if (class_exists('ErrorController')) {
                $controller = new ErrorController();

                if (method_exists($controller, $action)) {
                    try {
                        $controller->$action();
                        return;
                    } catch (Throwable $e) {
                        error_log('[Router] Trang lỗi cũng lỗi: ' . $e->getMessage());
                    }
                }
            }
        }

        if (is_file(ROOT_PATH . $fallbackFile)) {
            require ROOT_PATH . $fallbackFile;
            return;
        }

        echo 'Đã có lỗi xảy ra.';
    }
}
