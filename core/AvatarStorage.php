<?php

/**
 * core/AvatarStorage.php
 *
 * Ảnh đại diện của khách (nút "Chọn ảnh" trong "Vin Eyewear Account.dc.html",
 * mục Hồ sơ).
 *
 * Toàn bộ phần kiểm tra và cất file nằm ở core/ImageUploader.php — đọc phần
 * đầu file đó để biết VÌ SAO một cái ảnh lại cần bốn lớp chặn. Ở đây chỉ còn
 * ba con số của riêng ảnh đại diện: cất ở đâu, nặng tối đa bao nhiêu, nhận
 * định dạng nào.
 *
 * Trước đây file này chứa cả phần canh chừng; nó được tách ra khi khu quản trị
 * cần tải ảnh sản phẩm, để hai chỗ dùng CHUNG một bản luật thay vì hai bản
 * chép tay lệch nhau dần.
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
        return self::uploader()->store($file);
    }

    /** Xoá một ảnh cũ sau khi khách thay ảnh khác. */
    public static function remove(?string $path): void
    {
        self::uploader()->remove($path);
    }

    private static function uploader(): ImageUploader
    {
        return new ImageUploader(self::DIR, self::MAX_BYTES, self::ALLOWED);
    }
}
