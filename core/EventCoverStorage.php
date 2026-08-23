<?php

/**
 * core/EventCoverStorage.php
 *
 * Ảnh bìa của sự kiện, tải lên từ form quản trị (/quan-tri/su-kien).
 *
 * Phần kiểm tra và cất file nằm ở core/ImageUploader.php — xem đầu file đó để
 * biết vì sao một cái ảnh cần bốn lớp chặn. Ở đây chỉ có tham số riêng.
 *
 * Mỗi sự kiện đúng MỘT ảnh bìa (cột events.cover_image là VARCHAR chứ không
 * phải JSON như products.images), nên lớp này không có storeMany().
 *
 * Trần dung lượng và danh sách định dạng để BẰNG ảnh sản phẩm: cùng là ảnh do
 * cửa hàng chụp và tải lên, đặt hai con số khác nhau chỉ tạo ra một câu hỏi
 * "vì sao chỗ này 3 MB chỗ kia 2 MB" mà không ai trả lời được. Cả hai đều là
 * giả định của team, chưa hỏi BA — xem ghi chú ở ProductImageStorage.
 */

class EventCoverStorage
{
    /** Thư mục lưu, tính từ gốc dự án. Cũng chính là tiền tố lưu trong CSDL. */
    private const DIR = 'assets/uploads/su-kien';

    public const MAX_BYTES = ProductImageStorage::MAX_BYTES;

    private const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    /**
     * Cất ảnh bìa vừa chọn.
     *
     * Ô trống là chuyện bình thường (sửa sự kiện mà không đổi ảnh), nên trả về
     * ok = false kèm error = null để chỗ gọi phân biệt được "không chọn gì"
     * với "chọn nhưng hỏng".
     *
     * @param  array $file $_FILES['cover_file']
     * @return array{ok:bool, path?:string, error?:string|null}
     */
    public static function store(array $file): array
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => null];
        }

        $stored = self::uploader()->store($file);

        if (!$stored['ok']) {
            return $stored;
        }

        // Gạch chéo đầu: cột này in thẳng ra <img src>, mà "assets/..." không
        // có gạch đầu là đường dẫn TƯƠNG ĐỐI — trên /su-kien/abc nó thành
        // /su-kien/assets/... và ảnh vỡ.
        return ['ok' => true, 'path' => '/' . ltrim($stored['path'], '/')];
    }

    /**
     * Xoá ảnh bìa cũ. Chỉ động tới file NẰM TRONG thư mục này — ảnh đi kèm mã
     * nguồn (/assets/images/…) thì gỡ khỏi CSDL chứ không xoá khỏi đĩa.
     */
    public static function remove(?string $path): void
    {
        self::uploader()->remove($path);
    }

    public static function accept(): string
    {
        return self::uploader()->accept();
    }

    public static function limitLabel(): string
    {
        return self::uploader()->limitLabel();
    }

    public static function formatLabel(): string
    {
        return self::uploader()->formatLabel();
    }

    private static function uploader(): ImageUploader
    {
        return new ImageUploader(self::DIR, self::MAX_BYTES, self::ALLOWED);
    }
}
