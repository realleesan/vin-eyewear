<?php
/**
 * Vin Eyewear - Error Controller
 * Handles 404 and 500 error pages
 */

class ErrorController extends BaseController
{
    public function notFound(): void
    {
        http_response_code(404);
        $this->renderView('errors/404', [
            'pageTitle' => '404 - Trang không tìm thấy | Vin Eyewear',
        ]);
    }

    public function serverError(): void
    {
        http_response_code(500);
        $this->renderView('errors/500', [
            'pageTitle' => '500 - Lỗi hệ thống | Vin Eyewear',
        ]);
    }
}