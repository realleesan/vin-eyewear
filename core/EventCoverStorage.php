<?php

/**
 * core/EventCoverStorage.php
 *
 * Nhận, kiểm tra và cất ảnh bìa của sự kiện (khu vực "Kéo thả / chọn ảnh"
 * trong form sự kiện).
 *
 * Bốn lớp bảo vệ, y hệt ProductImageStorage:
 *   1. Kích thước tối đa trước khi đọc file.
 *   2. exif_imagetype() — xác nhận đây là ảnh thật, không tin đuôi hay MIME do
 *      trình duyệt gửi lên.
 *   3. Tên file do chính mã PHP sinh (time() + random), không lấy từ tên gốc,
 *      nên không có đường nào để chèn "../" hay ".php".
 *   4. .htaccess trong thư mục lưu tắt trình thông dịch PHP và chặn mọi định dạng
 *      trừ jpg/png/webp.
 */

class EventCoverStorage
{
    /** Thư mục lưu, tính từ gốc dự án. Cũng chính là tiền tố lưu trong CSDL. */
    private const DIR = 'assets/uploads/events';

    /** Trần dung lượng — 5 MB. */
    public const MAX_BYTES = 5242880;

    /** Định dạng nhận, ánh xạ hằng của exif_imagetype() sang đuôi file MÌNH đặt. */
    private const ALLOWED = [
        IMAGETYPE_JPEG  => 'jpg',
        IMAGETYPE_PNG   => 'png',
        IMAGETYPE_WEBP  => 'webp',
    ];

    /**
     * Cất một file vừa tải lên.
     *
     * @param  array $file một phần tử của $_FILES['cover_image']
     * @return array{ok:bool, path?:string, error?:string}
     */
    public static function store(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Bạn chưa chọn ảnh bìa nào.'];
        }

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => 'Ảnh bìa vượt quá dung lượng cho phép (tối đa 5 MB).'];
        }

        if ($error !== UPLOAD_ERR_OK) {
            error_log('[EventCoverStorage] Lỗi tải lên, mã ' . $error);

            return ['ok' => false, 'error' => 'Không nhận được ảnh, vui lòng thử lại.'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Không nhận được ảnh, vui lòng thử lại.'];
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Ảnh bìa vượt quá dung lượng cho phép (tối đa 5 MB).'];
        }

        $type = @exif_imagetype($tmp);

        if ($type === false || !isset(self::ALLOWED[$type])) {
            return ['ok' => false, 'error' => 'Chỉ nhận ảnh định dạng JPEG, PNG hoặc WEBP.'];
        }

        $dir = ROOT_PATH . '/' . self::DIR;

        if (!self::prepareDir($dir)) {
            return ['ok' => false, 'error' => 'Không lưu được ảnh, vui lòng thử lại.'];
        }

        $name = time() . '_' . bin2hex(random_bytes(4)) . '.' . self::ALLOWED[$type];

        if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
            error_log('[EventCoverStorage] Không ghi được vào ' . $dir . '/' . $name);

            return ['ok' => false, 'error' => 'Không lưu được ảnh, vui lòng thử lại.'];
        }

        return ['ok' => true, 'path' => self::DIR . '/' . $name];
    }

    /**
     * Xoá một ảnh bìa cũ.
     */
    public static function remove(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $full = realpath(ROOT_PATH . '/' . ltrim($path, '/'));
        $base = realpath(ROOT_PATH . '/' . self::DIR);

        if ($full === false || $base === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
            return;
        }

        @unlink($full);
    }

    /**
     * Tạo thư mục lưu nếu chưa có, kèm .htaccess tắt trình thông dịch.
     */
    private static function prepareDir(string $dir): bool
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        $guard = $dir . '/.htaccess';

        if (!is_file($guard)) {
            @file_put_contents($guard, <<<'HTACCESS'
            # Thư mục chứa file do NGƯỜI DÙNG tải lên. Không có gì ở đây được chạy.
            #
            # Sinh tự động bởi core/EventCoverStorage.php — sửa tay ở đây thì lần sau
            # thư mục bị xoá đi tạo lại là mất. Sửa trong file PHP đó.

            <IfModule mod_php.c>
                php_flag engine off
            </IfModule>
            <IfModule mod_php7.c>
                php_flag engine off
            </IfModule>
            <IfModule mod_php8.c>
                php_flag engine off
            </IfModule>

            <IfModule mod_rewrite.c>
                RewriteEngine Off
            </IfModule>

            <FilesMatch "(?i)\.(?!jpe?g$|png$|webp$)[^.]+$">
                <IfModule mod_authz_core.c>
                    Require all denied
                </IfModule>
                <IfModule !mod_authz_core.c>
                    Order allow,deny
                    Deny from all
                </IfModule>
            </FilesMatch>
            HTACCESS);
        }

        return true;
    }
}
