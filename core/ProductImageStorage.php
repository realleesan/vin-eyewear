<?php

/**
 * core/ProductImageStorage.php
 *
 * Ảnh sản phẩm tải lên từ form quản trị (/quan-tri/san-pham).
 *
 * Phần kiểm tra và cất file nằm ở core/ImageUploader.php — xem đầu file đó để
 * biết vì sao phải chặn bốn lớp. Ở đây chỉ có tham số riêng của ảnh sản phẩm,
 * cộng phần gom nhiều file thành một danh sách (form sản phẩm cho chọn nhiều
 * ảnh một lượt, khác ảnh đại diện chỉ có một).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA CON SỐ DƯỚI ĐÂY LÀ GIẢ ĐỊNH CỦA TEAM, CHƯA HỎI BA
 *
 * SRS chỉ viết "nếu ảnh sai định dạng hoặc vượt dung lượng cho phép thì báo
 * lỗi" mà không nói cho phép bao nhiêu. Ba con số này gom về một chỗ đúng cho
 * lý do đó: BA chốt khác đi thì sửa ở đây, không phải đi lùng khắp nơi.
 *
 *   MAX_BYTES 3 MB — ảnh chụp điện thoại nén JPEG thường 1–3 MB, để 1 MB như
 *                    ảnh đại diện thì nhân viên phải tự nén trước, không thực tế.
 *   MAX_FILES 8    — trang chi tiết dùng ảnh đầu làm ảnh đại diện, ảnh thứ hai
 *                    hiện khi rê chuột; số còn lại là ảnh phụ. 8 là rộng rãi
 *                    mà vẫn chặn được cú tải nhầm cả thư mục ảnh.
 *   WEBP           — nhận thêm so với ảnh đại diện vì máy ảnh điện thoại và
 *                    công cụ nén hiện nay xuất WEBP là mặc định.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class ProductImageStorage
{
    /** Thư mục lưu, tính từ gốc dự án. Cũng chính là tiền tố lưu trong CSDL. */
    private const DIR = 'assets/uploads/san-pham';

    /** Trần dung lượng MỖI ảnh. Xem ghi chú đầu file. */
    public const MAX_BYTES = 3145728;

    /** Số ảnh tối đa của một sản phẩm. Xem ghi chú đầu file. */
    public const MAX_FILES = 8;

    private const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    /**
     * Cất tất cả ảnh vừa chọn trong một ô <input type="file" multiple>.
     *
     * Ô trống là chuyện BÌNH THƯỜNG ở đây (sửa sản phẩm mà không thêm ảnh mới),
     * khác hẳn ảnh đại diện — nên file mang mã UPLOAD_ERR_NO_FILE bị bỏ qua
     * lặng lẽ chứ không thành lỗi "Bạn chưa chọn ảnh nào.".
     *
     * @param  array $field một ô của $_FILES, ví dụ $_FILES['image_files']
     * @param  int   $room  còn chỗ cho bao nhiêu ảnh nữa (đã trừ ảnh đang giữ)
     * @return array{paths: string[], errors: string[]}
     */
    public static function storeMany(array $field, int $room): array
    {
        $uploader = self::uploader();
        $paths    = [];
        $errors   = [];

        foreach (ImageUploader::normalize($field) as $file) {
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if (count($paths) >= $room) {
                $errors[] = sprintf('Mỗi sản phẩm chỉ nhận tối đa %d ảnh, những ảnh chọn thêm đã bị bỏ qua.', self::MAX_FILES);
                break;
            }

            $one = $uploader->store($file);

            if ($one['ok']) {
                // Thêm gạch chéo đầu: cột `images` in THẲNG ra <img src>, mà
                // "assets/..." không có gạch đầu là đường dẫn TƯƠNG ĐỐI —
                // trên /san-pham/abc nó thành /san-pham/assets/... và ảnh vỡ.
                // Ảnh của bản seed cũng lưu dạng "/assets/images/...".
                $paths[] = '/' . ltrim($one['path'], '/');
                continue;
            }

            // Kèm tên file người dùng đặt để họ biết ảnh NÀO hỏng khi chọn một
            // lượt tám cái. Tên này do trình duyệt gửi lên nên phải cắt ngắn và
            // để view tự escape — không dùng nó vào bất cứ đường dẫn nào.
            //
            // utf8Substr() chứ KHÔNG phải mb_substr(): máy chủ của cửa hàng
            // không nạp extension mbstring, gọi hàm mb_* là lỗi 500 — xem ghi
            // chú ở core/helpers.php. Tên file tiếng Việt có dấu thì substr()
            // trần lại cắt giữa một ký tự nhiều byte.
            $label = trim((string) ($file['name'] ?? ''));
            $errors[] = ($label !== '' ? '“' . utf8Substr($label, 0, 60) . '”: ' : '') . $one['error'];
        }

        return ['paths' => $paths, 'errors' => $errors];
    }

    /**
     * Xoá ảnh khỏi đĩa khi nó bị gỡ khỏi sản phẩm.
     *
     * Chỉ xoá được file NẰM TRONG thư mục này. Ảnh đi kèm mã nguồn
     * (/assets/images/product-1.jpg của bản seed) gỡ khỏi danh sách thì thôi,
     * không bao giờ bị xoá — chúng còn được dùng ở chỗ khác và tải lại được
     * bằng git, còn xoá nhầm thì không.
     */
    public static function remove(?string $path): void
    {
        self::uploader()->remove($path);
    }

    /** Cho thuộc tính accept="" của ô chọn file. */
    public static function accept(): string
    {
        return self::uploader()->accept();
    }

    /** "3 MB" — in dưới ô chọn file. */
    public static function limitLabel(): string
    {
        return self::uploader()->limitLabel();
    }

    /** "JPEG, PNG hoặc WEBP" — in dưới ô chọn file. */
    public static function formatLabel(): string
    {
        return self::uploader()->formatLabel();
    }

    private static function uploader(): ImageUploader
    {
        return new ImageUploader(self::DIR, self::MAX_BYTES, self::ALLOWED);
    }
}
