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
 * TỪ 2026-08-28 LỚP NÀY CẤT CẢ MỘT BỘ ẢNH, KHÔNG CHỈ MỘT ẢNH BÌA.
 *
 * Trước đó mỗi bộ đúng một ảnh (cột `cover_image` là VARCHAR) nên lớp này chỉ
 * có store(). Nay cột chứa ảnh là `collections`.`images` — mảng JSON, phần tử
 * đầu là ảnh đại diện, đúng quy ước của `products`.`images`.
 *
 * TÊN LỚP GIỮ NGUYÊN dù nó không còn chỉ lo "cover". Cái tên nói về THƯ MỤC
 * LƯU (assets/uploads/bo-suu-tap) chứ không phải về số lượng ảnh, và đổi tên
 * lớp thì file cũ vẫn nằm lại trên hosting: deploy khai
 * dangerous-clean-slate: false, tức là không xoá file chỉ vì nó biến mất khỏi
 * Git. Một file mồ côi mang tên lớp cũ thì vô hại nhưng gây bối rối lâu hơn
 * là cái tên hơi hẹp này.
 *
 * store() giữ lại cho ảnh lẻ; storeMany() cho cả loạt — cùng chữ ký với
 * ProductImageStorage::storeMany() để hai chỗ gọi đọc giống nhau.
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

    /**
     * Trần số ảnh cho MỘT bộ sưu tập.
     *
     * Cao hơn sản phẩm (8) vì đây là ảnh lookbook: một buổi chụp cho ra mươi
     * lăm kiểu là bình thường, trong khi một cái gọng thì chụp tám góc là hết.
     *
     * Có trần chứ không để tự do: cột `images` đọc ra ở mọi lượt tải trang bộ
     * sưu tập, và một bộ ba trăm ảnh sẽ làm trang đó nặng dần mà không ai để ý
     * cho tới lúc nó chậm hẳn.
     */
    public const MAX_FILES = 16;

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
     * Cất cả một loạt ảnh vừa chọn.
     *
     * Cùng chữ ký và cùng cách cư xử với ProductImageStorage::storeMany():
     * $room là số chỗ CÒN LẠI sau khi đã đếm ảnh cũ được giữ, nên nơi gọi
     * quyết định trần chứ lớp này không tự đoán.
     *
     * Ảnh hỏng KHÔNG làm hỏng cả lượt: những ảnh tốt vẫn được cất, ảnh hỏng đi
     * vào 'errors' kèm tên file để nhân viên biết ảnh NÀO — chọn một lượt mười
     * sáu cái mà chỉ báo "có ảnh hỏng" thì không ai tìm ra là ảnh nào.
     *
     * @param  array $field $_FILES['image_files']
     * @param  int   $room  còn nhận thêm được mấy ảnh nữa
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
                $errors[] = sprintf(
                    'Mỗi bộ sưu tập chỉ nhận tối đa %d ảnh, những ảnh chọn thêm đã bị bỏ qua.',
                    self::MAX_FILES
                );
                break;
            }

            $one = $uploader->store($file);

            if ($one['ok']) {
                // Gạch chéo đầu — cùng lý do với store(): cột `images` in
                // thẳng ra <img src>, mà "assets/..." không có gạch đầu là
                // đường dẫn TƯƠNG ĐỐI và sẽ trỏ sai trên /bo-suu-tap/<slug>.
                $paths[] = '/' . ltrim($one['path'], '/');
                continue;
            }

            // utf8Substr() chứ KHÔNG phải mb_substr(): máy chủ của cửa hàng
            // không nạp extension mbstring — xem ghi chú ở core/helpers.php.
            $ten = trim((string) ($file['name'] ?? ''));
            $errors[] = ($ten !== '' ? '“' . utf8Substr($ten, 0, 60) . '”: ' : '') . $one['error'];
        }

        return ['paths' => $paths, 'errors' => $errors];
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
