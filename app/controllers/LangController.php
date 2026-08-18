<?php

/**
 * LangController — đổi ngôn ngữ giao diện (/ngon-ngu?lang=en).
 *
 * Ghi lựa chọn vào cookie rồi trả khách về đúng trang họ đang đọc.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO LÀ GET CHỨ KHÔNG PHẢI POST + CSRF
 *
 * Dự án dùng POST kèm token cho mọi thao tác đổi trạng thái (thêm giỏ hàng,
 * lưu địa chỉ…). Ở đây cố ý khác, vì ba lẽ:
 *
 *   1. Thao tác này KHÔNG phá gì cả. Kẻ xấu dụ được người khác đổi ngôn ngữ
 *      giao diện của chính họ thì cũng chỉ được đúng thế, và họ bấm một lần
 *      là về như cũ.
 *   2. Nó phải chạy KHÔNG CẦN JAVASCRIPT. Bọc trong <form> POST thì mỗi mục
 *      trong danh sách xổ là một form riêng — HTML không cho lồng form, mà
 *      cụm này nằm giữa đầu trang vốn đã có sẵn form tìm kiếm.
 *   3. Đây là lựa chọn hiển thị, không phải dữ liệu của tài khoản: nó nằm ở
 *      cookie chứ không nằm trong CSDL, nên không có gì để giả mạo.
 *
 * Hai liên kết gọi tới đây mang rel="nofollow" (xem _layout/header.php) để
 * máy tìm kiếm không đi theo và lập chỉ mục cùng một trang hai lần.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class LangController extends BaseController
{
    /** Cookie sống một năm — lựa chọn ngôn ngữ không phải thứ hỏi lại mỗi tuần. */
    private const LIFETIME = 31536000;

    public function switch(): void
    {
        $lang = (string) ($_GET['lang'] ?? '');

        /*
         * ĐỐI CHIẾU với danh sách cho phép trước khi ghi. Giá trị này đến từ
         * URL, và nó sẽ đi thẳng vào thuộc tính lang của thẻ <html> ở lần tải
         * sau — ghi thẳng chuỗi từ URL vào cookie là để dành sẵn một chỗ chèn.
         *
         * Mã lạ thì lặng lẽ bỏ qua chứ không báo lỗi: người dùng không gõ tay
         * đường dẫn này, có sai thì cũng là do liên kết hỏng.
         */
        if (in_array($lang, LANG_CODES, true)) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

            setcookie(LANG_COOKIE, $lang, [
                'expires'  => time() + self::LIFETIME,
                'path'     => '/',
                'secure'   => $isHttps,
                // Chỉ PHP đọc cookie này, không dòng JavaScript nào cần tới.
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        /*
         * safeRedirectPath() chặn chuyển hướng ra ngoài site: tham số này đến
         * từ URL nên nếu tin thẳng thì /ngon-ngu?redirect=https://... thành
         * một cái bẫy chuyển hướng mang tên miền thật của cửa hàng.
         */
        redirect(safeRedirectPath($_GET['redirect'] ?? null, '/'));
    }
}
