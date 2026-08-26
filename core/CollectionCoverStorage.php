<?php

/**
 * core/CollectionCoverStorage.php
 *
 * Ảnh bìa của bộ sưu tập, tải lên từ form quản trị (/quan-tri/bo-suu-tap).
 *
 * Phần kiểm tra và cất file nằm ở core/ImageUploader.php — xem đầu file đó để
 * biết vì sao một cái ảnh cần bốn lớp chặn. Ở đây chỉ có tham số riêng.
 *
 * Lớp này từng là bản sao của EventCoverStorage, khác đúng một thứ: THƯ MỤC
 * LƯU. Giữ riêng chứ không gộp, vì gộp thì ảnh bộ sưu tập nằm chung trong
 * assets/uploads/su-kien và một lần dọn ảnh sự kiện cũ sẽ quét nhầm cả ảnh
 * đang dùng. Sự kiện đã bỏ hẳn (2026-08-26) nên chỉ còn lớp này — ghi lại để
 * ai thấy nó "thừa giống ProductImageStorage" biết rằng nó không phải bản sao
 * bỏ quên.
 *
 * Mỗi bộ đúng MỘT ảnh bìa (cột collections.cover_image là VARCHAR), nên lớp
 * này không có storeMany().
 *
 * Trần dung lượng và định dạng để BẰNG ảnh sản phẩm: cùng là ảnh do cửa hàng
 * chụp và tải lên, đặt hai con số khác nhau chỉ tạo ra câu hỏi "vì sao chỗ này
 * khác chỗ kia" mà không ai trả lời được.
 */

class CollectionCoverStorage
{
    /** Thư mục lưu, tính từ gốc dự án. Cũng chính là tiền tố lưu trong CSDL. */
    private const DIR = 'assets/uploads/bo-suu-tap';

    public const MAX_BYTES = ProductImageStorage::MAX_BYTES;

    private const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    /**
     * Cất ảnh bìa vừa chọn.
     *
     * Ô trống là chuyện bình thường (sửa bộ sưu tập mà không đổi ảnh), nên trả
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
        // có gạch đầu là đường dẫn TƯƠNG ĐỐI — trên một trang có đường dẫn
        // nhiều tầng nó sẽ trỏ sai và ảnh vỡ.
        return ['ok' => true, 'path' => '/' . ltrim($stored['path'], '/')];
    }

    /**
     * Xoá ảnh bìa cũ. Chỉ động tới file NẰM TRONG thư mục này — ảnh đi kèm mã
     * nguồn (/assets/images/…) thì gỡ khỏi CSDL chứ không xoá khỏi đĩa.
     *
     * Điều đó quan trọng ngay từ hôm nay: ba bộ gieo sẵn trong migration đều
     * trỏ tới ảnh mẫu trong assets/images/, không phải ảnh tải lên.
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
