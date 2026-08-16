<?php

/**
 * core/AvatarStorage.php
 *
 * Nhận, kiểm tra và cất ảnh đại diện của khách (nút "Chọn ảnh" trong
 * "Vin Eyewear Account.dc.html", mục Hồ sơ).
 *
 * Đây là chỗ DUY NHẤT trong dự án nhận file từ người dùng, nên toàn bộ phần
 * canh chừng gom về một file thay vì rải trong controller.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO PHẢI CẨN THẬN ĐẾN THẾ VỚI MỘT CÁI ẢNH
 *
 * Mã nguồn nằm CÙNG CẤP với thư mục gốc web (không có public/ riêng — xem
 * .htaccess), nên bất cứ file nào ghi được vào assets/ cũng gọi thẳng qua URL
 * được. Một file tên `x.php` chứa mã PHP mà lọt vào đó là chiếm được máy chủ.
 *
 * Bốn lớp chặn, mỗi lớp tự nó đã đủ, và cố ý chồng lên nhau:
 *
 *   1. Kích thước — chặn trước mọi thứ khác, để không phải đọc file 2 GB.
 *   2. exif_imagetype() — đọc mấy byte đầu để biết ĐÂY LÀ ẢNH GÌ THẬT.
 *      KHÔNG tin $_FILES['type']: giá trị đó do trình duyệt gửi lên, sửa được
 *      bằng bất kỳ công cụ nào, nên "image/png" ở đó không chứng minh gì cả.
 *      Cũng không tin đuôi file người dùng đặt, vì lý do y hệt.
 *   3. Đuôi file do MÌNH đặt — suy ra từ kết quả bước 2, không lấy từ tên gốc.
 *      Tên file cũng do mình sinh (uuid), nên không có đường nào để chèn
 *      "../" hay ".php" vào đường dẫn.
 *   4. .htaccess trong chính thư mục lưu, tắt hẳn mọi trình thông dịch.
 *      Lớp này đứng đây phòng khi ba lớp trên bị một lỗi nào đó lách qua —
 *      ảnh PNG hợp lệ vẫn có thể mang mã PHP ở phần metadata, và điều đó chỉ
 *      nguy hiểm nếu máy chủ chịu CHẠY file đó.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class AvatarStorage
{
    /** Thư mục lưu, tính từ gốc dự án. Cũng chính là tiền tố lưu trong CSDL. */
    private const DIR = 'assets/uploads/avatars';

    /**
     * Trần dung lượng — 1 MB, đúng con số bản thiết kế in dưới nút chọn ảnh
     * ("Dung lượng tối đa 1 MB").
     */
    public const MAX_BYTES = 1048576;

    /**
     * Định dạng nhận, ánh xạ hằng của exif_imagetype() sang đuôi file MÌNH đặt.
     * Đúng hai định dạng bản thiết kế ghi ("Định dạng JPEG, PNG").
     */
    private const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
    ];

    /**
     * Cất một file vừa tải lên, trả về đường dẫn để lưu vào CSDL.
     *
     * @param  array $file một phần tử của $_FILES
     * @return array{ok:bool, path?:string, error?:string}
     */
    public static function store(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Bạn chưa chọn ảnh nào.'];
        }

        // INI_SIZE/FORM_SIZE nghĩa là PHP đã tự cắt file giữa chừng. Báo đúng
        // nguyên nhân, vì nó khác hẳn "ảnh hỏng" dưới mắt người dùng.
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => 'Ảnh vượt quá dung lượng cho phép (tối đa 1 MB).'];
        }

        if ($error !== UPLOAD_ERR_OK) {
            error_log('[AvatarStorage] Lỗi tải lên, mã ' . $error);

            return ['ok' => false, 'error' => 'Không nhận được ảnh, vui lòng thử lại.'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');

        // Không có dòng này thì một tham số khéo léo có thể trỏ $tmp vào file
        // bất kỳ trên đĩa máy chủ (/etc/passwd…) và ta ngoan ngoãn chép nó ra
        // thư mục web.
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Không nhận được ảnh, vui lòng thử lại.'];
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Ảnh vượt quá dung lượng cho phép (tối đa 1 MB).'];
        }

        $type = @exif_imagetype($tmp);

        if ($type === false || !isset(self::ALLOWED[$type])) {
            return ['ok' => false, 'error' => 'Chỉ nhận ảnh định dạng JPEG hoặc PNG.'];
        }

        $dir = ROOT_PATH . '/' . self::DIR;

        if (!self::prepareDir($dir)) {
            return ['ok' => false, 'error' => 'Không lưu được ảnh, vui lòng thử lại.'];
        }

        $name = uuid() . '.' . self::ALLOWED[$type];

        if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
            error_log('[AvatarStorage] Không ghi được vào ' . $dir);

            return ['ok' => false, 'error' => 'Không lưu được ảnh, vui lòng thử lại.'];
        }

        return ['ok' => true, 'path' => self::DIR . '/' . $name];
    }

    /**
     * Xoá một ảnh cũ sau khi khách thay ảnh khác.
     *
     * Đường dẫn đến từ CSDL chứ không từ request, nhưng vẫn kiểm lại tiền tố:
     * hàm này xoá file, và một hàm xoá file thì không nên tin bất cứ đường dẫn
     * nào chỉ vì nó "đáng lẽ" an toàn. realpath() lo phần "../".
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
     *
     * Viết .htaccess bằng code chứ không commit sẵn vào repo: thư mục upload
     * trống rỗng thì git không theo dõi được, và một file .gitkeep cạnh một
     * file .htaccess quan trọng rất dễ bị dọn nhầm. Sinh lại mỗi lần cần dùng
     * thì nó không thể vắng mặt.
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
            # Sinh tự động bởi core/AvatarStorage.php — sửa tay ở đây thì lần sau
            # thư mục bị xoá đi tạo lại là mất. Sửa trong file PHP đó.

            # php_flag là chỉ thị của mod_php. Máy chủ chạy PHP-FPM (phần lớn
            # hosting hiện nay) KHÔNG hiểu nó và trả 500 cho cả thư mục — nên
            # phải bọc trong IfModule, mỗi bản mod_php một tên khác nhau.
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

            # Hàng rào thật, không phụ thuộc mod_php: chặn mọi thứ TRỪ hai đuôi
            # ảnh. File .php lọt vào đây cũng không tải về hay chạy được.
            <FilesMatch "(?i)\.(?!jpe?g$|png$)[^.]+$">
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
