<?php
/**
 * Vin Eyewear - Router Class
 * Handles URL routing and dispatching to controllers
 */

class Router
{
    private $routes;

    public function __construct($routes)
    {
        $this->routes = $routes;
    }

    public function dispatch()
    {
        // Get the URI from server
        $uri = $_SERVER['REQUEST_URI'];
        
        // Remove query string if present
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        // Remove leading/trailing slashes
        $uri = trim($uri, '/');
        
        // Handle empty URI (root)
        if ($uri === '' || $uri === '/') {
            $uri = '';
        }
        
        // Check if route exists
        if (array_key_exists($uri, $this->routes)) {
            $route = $this->routes[$uri];
            $this->callController($route);
        } else {
            // 404 Not Found
            $this->handle404();
        }
    }

    private function callController($route)
    {
        // Parse controller@action format
        list($controllerName, $action) = explode('@', $route);
        
        // Build full controller class name
        $controllerClass = $controllerName;
        
        // Build controller file path
        $controllerFile = APP_PATH . '/controllers/' . $controllerClass . '.php';
        
        // Check if controller file exists
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            
            // Check if class exists
            if (class_exists($controllerClass)) {
                $controller = new $controllerClass();
                
                // Check if action exists
                if (method_exists($controller, $action)) {
                    $controller->$action();
                } else {
                    $this->handle404();
                }
            } else {
                $this->handle404();
            }
        } else {
            $this->handle404();
        }
    }

    private function handle404()
    {
        header('HTTP/1.0 404 Not Found');
        require_once ROOT_PATH . '/errors/404.php';
        exit;
    }
}