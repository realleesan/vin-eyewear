<?php

/**
 * core/ImageUploader.php
 *
 * Nhận, kiểm tra và cất MỘT file ảnh do người dùng tải lên. Không biết gì về
 * nghiệp vụ: gọi nó thì đưa thư mục lưu, trần dung lượng và danh sách định
 * dạng nhận; nó trả về đường dẫn để lưu vào CSDL.
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
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO LỚP NÀY TỒN TẠI (trước đây toàn bộ nằm trong AvatarStorage)
 *
 * Khi khu quản trị cần tải ảnh sản phẩm, có hai đường: chép bốn lớp chặn ở
 * trên sang một file thứ hai, hoặc tách chúng ra đây và để cả hai chỗ gọi
 * chung. Chép là hỏng: lần sau vá một lỗ hổng ở một file, file kia vẫn thủng
 * mà không ai nhớ ra. Nên phần canh chừng VẪN chỉ có một bản duy nhất —
 * là file này — còn AvatarStorage và ProductImageStorage chỉ còn là hai bộ
 * tham số (thư mục nào, nặng bao nhiêu, nhận định dạng gì).
 */

class ImageUploader
{
    /**
     * @param string $dir      thư mục lưu, tính từ gốc dự án — cũng chính là
     *                         tiền tố đường dẫn cất vào CSDL
     * @param int    $maxBytes trần dung lượng mỗi file
     * @param array  $allowed  hằng exif_imagetype() => đuôi file MÌNH đặt,
     *                         ví dụ [IMAGETYPE_JPEG => 'jpg']
     */
    public function __construct(
        private string $dir,
        private int $maxBytes,
        private array $allowed
    ) {
    }

    /**
     * Cất một file vừa tải lên, trả về đường dẫn để lưu vào CSDL.
     *
     * @param  array $file một phần tử của $_FILES
     * @return array{ok:bool, path?:string, error?:string}
     */
    public function store(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Bạn chưa chọn ảnh nào.'];
        }

        // INI_SIZE/FORM_SIZE nghĩa là PHP đã tự cắt file giữa chừng. Báo đúng
        // nguyên nhân, vì nó khác hẳn "ảnh hỏng" dưới mắt người dùng.
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => $this->tooBigMessage()];
        }

        if ($error !== UPLOAD_ERR_OK) {
            error_log('[ImageUploader] Lỗi tải lên, mã ' . $error);

            return ['ok' => false, 'error' => 'Không nhận được ảnh, vui lòng thử lại.'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');

        // Không có dòng này thì một tham số khéo léo có thể trỏ $tmp vào file
        // bất kỳ trên đĩa máy chủ (/etc/passwd…) và ta ngoan ngoãn chép nó ra
        // thư mục web.
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Không nhận được ảnh, vui lòng thử lại.'];
        }

        if ((int) ($file['size'] ?? 0) > $this->maxBytes) {
            return ['ok' => false, 'error' => $this->tooBigMessage()];
        }

        $type = @exif_imagetype($tmp);

        if ($type === false || !isset($this->allowed[$type])) {
            return ['ok' => false, 'error' => 'Chỉ nhận ảnh định dạng ' . $this->formatLabel() . '.'];
        }

        $dir = ROOT_PATH . '/' . $this->dir;

        if (!$this->prepareDir($dir)) {
            return ['ok' => false, 'error' => 'Không lưu được ảnh, vui lòng thử lại.'];
        }

        $name = uuid() . '.' . $this->allowed[$type];

        if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
            error_log('[ImageUploader] Không ghi được vào ' . $dir);

            return ['ok' => false, 'error' => 'Không lưu được ảnh, vui lòng thử lại.'];
        }

        return ['ok' => true, 'path' => $this->dir . '/' . $name];
    }

    /**
     * Xoá một ảnh cũ sau khi nó bị thay hoặc bị gỡ khỏi danh sách.
     *
     * Đường dẫn đến từ CSDL chứ không từ request, nhưng vẫn kiểm lại tiền tố:
     * hàm này xoá file, và một hàm xoá file thì không nên tin bất cứ đường dẫn
     * nào chỉ vì nó "đáng lẽ" an toàn. realpath() lo phần "../".
     *
     * Hệ quả cố ý: ảnh nằm NGOÀI thư mục lưu (vd /assets/images/product-1.jpg
     * đi kèm mã nguồn) thì gỡ khỏi danh sách chứ không bao giờ bị xoá khỏi đĩa.
     */
    public function remove(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $full = realpath(ROOT_PATH . '/' . ltrim($path, '/'));
        $base = realpath(ROOT_PATH . '/' . $this->dir);

        if ($full === false || $base === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
            return;
        }

        @unlink($full);
    }

    /** Đường dẫn thư mục lưu, tính từ gốc dự án. */
    public function dir(): string
    {
        return $this->dir;
    }

    /**
     * Giá trị cho thuộc tính accept="" của ô chọn file.
     *
     * Chỉ là gợi ý cho hộp thoại chọn file của hệ điều hành — người dùng vẫn
     * chọn được file khác, nên máy chủ vẫn phải kiểm lại (store() ở trên).
     */
    public function accept(): string
    {
        $mimes = [];

        foreach ($this->allowed as $type => $ext) {
            $mimes[] = image_type_to_mime_type($type);
        }

        return implode(',', array_unique($mimes));
    }

    /** "1 MB", "2,5 MB" — để in ra màn hình. Dấu phẩy thập phân theo tiếng Việt. */
    public function limitLabel(): string
    {
        $mb = $this->maxBytes / 1048576;

        return (fmod($mb, 1.0) === 0.0
            ? (string) (int) $mb
            : str_replace('.', ',', (string) round($mb, 1))) . ' MB';
    }

    /** "JPEG hoặc PNG", "JPEG, PNG hoặc WEBP". */
    public function formatLabel(): string
    {
        $names = array_values(array_unique(array_map(
            static fn (string $ext): string => strtoupper($ext === 'jpg' ? 'jpeg' : $ext),
            $this->allowed
        )));

        if (count($names) < 2) {
            return (string) ($names[0] ?? '');
        }

        $last = array_pop($names);

        return implode(', ', $names) . ' hoặc ' . $last;
    }

    /**
     * Tách $_FILES của một ô chọn nhiều file thành danh sách từng file.
     *
     * PHP dựng $_FILES['x'] cho name="x[]" theo kiểu "mảng của từng THUỘC TÍNH"
     * (['name' => [...], 'error' => [...]]) chứ không phải "danh sách file",
     * nên không lặp thẳng được. Hàm này lật lại cho đúng chiều.
     *
     * @return array<int, array> mỗi phần tử là một $_FILES một file
     */
    public static function normalize(array $field): array
    {
        if ($field === []) {
            return [];
        }

        // Ô chọn MỘT file: các khoá đã là giá trị vô hướng, trả về nguyên vẹn.
        if (!is_array($field['name'] ?? null)) {
            return [$field];
        }

        $out = [];

        foreach (array_keys($field['name']) as $i) {
            $one = [];
            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
                $one[$key] = $field[$key][$i] ?? null;
            }
            $out[] = $one;
        }

        return $out;
    }

    /**
     * Trần dung lượng bị vượt — gộp một chỗ vì có hai đường dẫn tới nó
     * (PHP tự cắt, và ta tự đo).
     */
    private function tooBigMessage(): string
    {
        return 'Ảnh vượt quá dung lượng cho phép (tối đa ' . $this->limitLabel() . ').';
    }

    /**
     * Tạo thư mục lưu nếu chưa có, kèm .htaccess tắt trình thông dịch.
     *
     * Viết .htaccess bằng code chứ không commit sẵn vào repo: thư mục upload
     * trống rỗng thì git không theo dõi được, và một file .gitkeep cạnh một
     * file .htaccess quan trọng rất dễ bị dọn nhầm. Sinh lại mỗi lần cần dùng
     * thì nó không thể vắng mặt.
     */
    private function prepareDir(string $dir): bool
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        $guard = $dir . '/.htaccess';

        if (!is_file($guard)) {
            @file_put_contents($guard, $this->htaccess());
        }

        return true;
    }

    /**
     * Nội dung .htaccess canh thư mục lưu.
     *
     * Danh sách đuôi cho phép sinh TỪ $allowed chứ không gõ cứng: thêm một
     * định dạng ở chỗ khai báo mà quên sửa hàng rào này thì ảnh tải lên được
     * nhưng trình duyệt tải về nhận 403 — lỗi rất khó đoán.
     */
    private function htaccess(): string
    {
        $exts = implode('|', array_map(
            // jpg và jpeg là cùng một thứ; đuôi ta đặt luôn là .jpg nhưng vẫn
            // để lọt jpeg phòng file cũ chép tay vào thư mục.
            static fn (string $ext): string => ($ext === 'jpg' ? 'jpe?g' : preg_quote($ext, '"')) . '$',
            array_values(array_unique($this->allowed))
        ));

        return <<<HTACCESS
        # Thư mục chứa file do NGƯỜI DÙNG tải lên. Không có gì ở đây được chạy.
        #
        # Sinh tự động bởi core/ImageUploader.php — sửa tay ở đây thì lần sau
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

        # Hàng rào thật, không phụ thuộc mod_php: chặn mọi thứ TRỪ các đuôi
        # ảnh cho phép. File .php lọt vào đây cũng không tải về hay chạy được.
        <FilesMatch "(?i)\.(?!{$exts})[^.]+\$">
            <IfModule mod_authz_core.c>
                Require all denied
            </IfModule>
            <IfModule !mod_authz_core.c>
                Order allow,deny
                Deny from all
            </IfModule>
        </FilesMatch>
        HTACCESS;
    }
}
