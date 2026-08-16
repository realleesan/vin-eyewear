<?php

/**
 * NewsletterController — nhận form đăng ký nhận tin (S20).
 *
 * Chỉ có một action POST: khối đăng ký là một phần của trang chủ, không phải
 * trang riêng, nên không cần index().
 */

class NewsletterController extends BaseController
{
    public function submit(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            redirect('/');
        }

        // Nơi quay về sau khi xử lý. Nhận từ form nhưng lọc qua
        // safeRedirectPath(): trường ẩn nằm trong HTML, ai cũng sửa được.
        $back = safeRedirectPath($_POST['redirect'] ?? null, '/') . '#dang-ky-nhan-tin';

        if (!csrfCheck($_POST['_token'] ?? null)) {
            flash('newsletter_error', 'Phiên làm việc đã hết hạn, vui lòng gửi lại.');
            redirect($back);
        }

        $email  = (string) ($_POST['email'] ?? '');
        $source = (string) ($_POST['source'] ?? 'home');

        $result = NewsletterModel::subscribe($email, $source);

        if (!$result['ok']) {
            // Giữ lại địa chỉ vừa nhập để khách sửa, không phải gõ lại từ đầu
            $_SESSION['_old_newsletter'] = $email;
            flash('newsletter_error', $result['error']);
            redirect($back);
        }

        flash(
            'newsletter_success',
            $result['already']
                ? 'Địa chỉ này đã có trong danh sách nhận tin.'
                : 'Đã đăng ký. Hẹn gặp bạn trong bản tin gần nhất.'
        );

        // POST/Redirect/GET: không có bước chuyển hướng này, khách bấm F5 là
        // gửi lại form thêm lần nữa.
        redirect($back);
    }
}
