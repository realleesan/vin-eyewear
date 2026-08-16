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

    /**
     * 403 — đã đăng nhập nhưng không đủ quyền.
     *
     * Tách khỏi 404 có chủ ý: người dùng ĐÃ đăng nhập, nói rõ "không đủ
     * quyền" hữu ích hơn là giả vờ trang không tồn tại. Với người CHƯA đăng
     * nhập thì AuthMiddleware đã đẩy về /auth trước khi tới đây.
     */
    public function forbidden(): void
    {
        http_response_code(403);
        $this->renderView('errors/403', [
            'pageTitle' => '403 - Không đủ quyền truy cập | Vin Eyewear',
        ]);
    }
}
