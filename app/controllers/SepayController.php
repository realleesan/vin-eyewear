<?php

/**
 * SepayController — nhận webhook báo biến động số dư từ SePay.
 *
 * Địa chỉ:  POST /webhook/sepay
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ ĐƯỜNG DUY NHẤT TRONG SITE KHÔNG CÓ CSRF TOKEN — VÀ PHẢI THẾ
 *
 * Người gọi là máy chủ của SePay, không phải trình duyệt của ai cả: không có
 * phiên, không có cookie, nên không có token nào để mà gửi. Thứ thay thế là
 * KHOÁ BÍ MẬT trong header `Authorization`, và nó phải được kiểm trước mọi
 * thứ khác.
 *
 * Hệ quả: khoá rò rỉ = ai cũng gửi được một tin "đã nhận 5 triệu" giả và đơn
 * tự sang đã thanh toán. Vì thế khoá nằm trong .env, so bằng hash_equals, và
 * KHÔNG khai khoá thì địa chỉ này đóng hẳn chứ không mở tự do.
 * ─────────────────────────────────────────────────────────────────────────────
 * LUÔN TRẢ 200 CHO NHỮNG THỨ ĐÃ NHẬN ĐƯỢC
 *
 * SePay coi mọi mã khác 200/201 là thất bại và gửi lại tối đa 7 lần trong 5
 * giờ. Đúng cho lỗi tạm (CSDL sập); nhưng SAI cho những thứ gửi lại bao nhiêu
 * lần cũng vậy — giao dịch không khớp đơn nào, tiền chuyển ra, giao dịch đã xử
 * lý rồi. Trả lỗi cho mấy trường hợp đó chỉ tạo ra bảy lần gõ cửa vô ích.
 *
 * Nên: nhận được và hiểu được -> 200, kể cả khi kết luận là "không làm gì cả".
 * Chỉ 4xx/5xx khi thật sự không xử lý được: sai khoá, JSON hỏng, CSDL lỗi.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class SepayController extends BaseController
{
    public function webhook(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->reply(405, ['success' => false, 'message' => 'Method not allowed']);
        }

        $key = trim((string) config('sepay.webhook_key', ''));

        /*
         * CHƯA KHAI KHOÁ = ĐÓNG HẲN.
         *
         * Đây là trạng thái mặc định cho tới khi cửa hàng có tài khoản SePay
         * thật. Mở tự do trong lúc chờ nghĩa là để sẵn một nút "đánh dấu đã
         * trả tiền" cho cả internet bấm.
         */
        if ($key === '') {
            error_log('[SePay] Có request tới webhook nhưng SEPAY_WEBHOOK_KEY chưa khai — từ chối.');
            $this->reply(403, ['success' => false, 'message' => 'Webhook chưa được cấu hình']);
        }

        if (!hash_equals($key, $this->apiKey())) {
            error_log('[SePay] Webhook sai khoá xác thực, IP ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
            $this->reply(401, ['success' => false, 'message' => 'Unauthorized']);
        }

        // Chưa chạy migration thì bảng sổ chưa có. Trả 503 chứ không 200: đây
        // ĐÚNG là lỗi tạm mà việc gửi lại sẽ chữa được, sau khi chạy migration.
        if (!SepayModel::available()) {
            error_log('[SePay] Chưa có bảng sepay_transactions — chạy migration 2026-08-22-sepay-doi-soat.');
            $this->reply(503, ['success' => false, 'message' => 'Chưa sẵn sàng']);
        }

        $body = file_get_contents('php://input') ?: '';
        $txn  = json_decode($body, true);

        if (!is_array($txn) || (int) ($txn['id'] ?? 0) <= 0) {
            error_log('[SePay] Payload không đọc được: ' . utf8Substr($body, 0, 500));
            $this->reply(400, ['success' => false, 'message' => 'Payload không hợp lệ']);
        }

        try {
            $result = SepayModel::handle($txn);
        } catch (Throwable $e) {
            /* Lỗi THẬT — để SePay gửi lại. Đây là chỗ duy nhất đáng trả 5xx,
               vì lần gửi lại có cơ hội thành công. */
            error_log('[SePay] Xử lý giao dịch #' . (int) $txn['id'] . ' hỏng: ' . $e->getMessage());
            $this->reply(500, ['success' => false, 'message' => 'Lỗi xử lý']);
        }

        /* Ghi lại MỌI giao dịch vào error log, kể cả cái khớp đẹp. Đây là thứ
           duy nhất còn lại để lần khi khách nói "tôi chuyển rồi mà đơn chưa
           đổi" — sổ trong CSDL nói cái gì đã vào, log nói cái gì đã tới. */
        error_log(sprintf(
            '[SePay] #%d %s %s -> %s%s',
            (int) $txn['id'],
            (string) ($txn['transferType'] ?? '?'),
            money((int) round((float) ($txn['transferAmount'] ?? 0))),
            $result['status'],
            isset($result['order_code']) && $result['order_code'] !== null
                ? ' (' . $result['order_code'] . ')' : ''
        ));

        // 200 + {"success": true} là đúng thứ SePay đợi để thôi gửi lại.
        $this->reply(200, ['success' => true]);
    }

    /**
     * Khoá trong header `Authorization: Apikey <khoá>`.
     *
     * BA CHỖ ĐỌC, vì không máy chủ nào giống máy chủ nào:
     *   HTTP_AUTHORIZATION           Apache + mod_php, hoặc đã có luật rewrite
     *   REDIRECT_HTTP_AUTHORIZATION  sau khi mod_rewrite chuyển hướng nội bộ
     *   getallheaders()              đường lui cuối
     *
     * Xem thêm luật `E=HTTP_AUTHORIZATION` trong .htaccess: Apache chạy PHP
     * dưới CGI/FastCGI nuốt mất header này, và triệu chứng là webhook im lặng
     * trả 401 mà không có gì để lần.
     */
    private function apiKey(): string
    {
        $raw = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($raw === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $raw = (string) $value;
                    break;
                }
            }
        }

        // "Apikey abc123" -> "abc123". Chấp nhận cả "ApiKey"/"APIKEY" và cả
        // chuỗi trần không có tiền tố, vì trang cấu hình của SePay từng đổi
        // cách viết và một khoảng trắng thừa không đáng làm hỏng cả tích hợp.
        return trim(preg_replace('/^\s*api\s*key\s+/i', '', trim($raw)) ?? '');
    }

    /** Trả JSON rồi dừng hẳn. */
    private function reply(int $status, array $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
