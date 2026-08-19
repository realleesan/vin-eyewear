<?php

/**
 * core/ProductImageStorage.php
 *
 * Nhận, kiểm tra và cất ảnh sản phẩm do admin tải lên (khu vực "Kéo thả / chọn ảnh"
 * trong form sản phẩm).
 *
 * Bốn lớp bảo vệ, y hệt AvatarStorage:
 *   1. Kích thước tối đa trước khi đọc file.
 *   2. exif_imagetype() — xác nhận đây là ảnh thật, không tin đuôi hay MIME do
 *      trình duyệt gửi lên.
 *   3. Tên file do chính mã PHP sinh (time() + random), không lấy từ tên gốc,
 *      nên không có đường nào để chèn "../" hay ".php".
 *   4. .htaccess trong thư mục lưu tắt trình thông dịch PHP và chặn mọi định dạng
 *      trừ jpg/png/webp.
 */

class ProductImageStorage
{
    /** Thư mục lưu, tính từ gốc dự án. Cũng chính là tiền tố lưu trong CSDL. */
    private const DIR = 'assets/uploads/products';

    /** Trần dung lượng — 5 MB. */
    public const MAX_BYTES = 5242880;

    /** Định dạng nhận, ánh xạ hằng của exif_imagetype() sang đuôi file MÌNH đặt. */
    private const ALLOWED = [
        IMAGETYPE_JPEG  => 'jpg',
        IMAGETYPE_PNG   => 'png',
        IMAGETYPE_WEBP  => 'webp',
    ];

    /**
     * Cất một hoặc nhiều file vừa tải lên.
     *
     * @param  array $files phần tử của $_FILES['images'] (mảng các mảng)
     * @return array{ok:bool, urls?:string[], error?:string}
     */
    public static function storeMultiple(array $files): array
    {
        $error = (int) ($files['error'][0] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Bạn chưa chọn ảnh nào.'];
        }

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => 'Ảnh vượt quá dung lượng cho phép (tối đa 5 MB).'];
        }

        if ($error !== UPLOAD_ERR_OK) {
            error_log('[ProductImageStorage] Lỗi tải lên, mã ' . $error);

            return ['ok' => false, 'error' => 'Không nhận được ảnh, vui lòng thử lại.'];
        }

        $dir = ROOT_PATH . '/' . self::DIR;

        if (!self::prepareDir($dir)) {
            return ['ok' => false, 'error' => 'Không lưu được ảnh, vui lòng thử lại.'];
        }

        $count = count($files['name']);
        $urls  = [];

        for ($i = 0; $i < $count; $i++) {
            $tmp = (string) ($files['tmp_name'][$i] ?? '');

            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }

            $size = (int) ($files['size'][$i] ?? 0);

            if ($size > self::MAX_BYTES) {
                continue;
            }

            $type = @exif_imagetype($tmp);

            if ($type === false || !isset(self::ALLOWED[$type])) {
                continue;
            }

            $name = time() . '_' . bin2hex(random_bytes(4)) . '.' . self::ALLOWED[$type];

            if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
                error_log('[ProductImageStorage] Không ghi được vào ' . $dir . '/' . $name);
                continue;
            }

            $urls[] = '/' . self::DIR . '/' . $name;

            usleep(500000);
            time();
        }

        if ($urls === []) {
            return ['ok' => false, 'error' => 'Không lưu được ảnh, vui lòng thử lại.'];
        }

        return ['ok' => true, 'urls' => $urls];
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
            # Sinh tự động bởi core/ProductImageStorage.php — sửa tay ở đây thì lần sau
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
