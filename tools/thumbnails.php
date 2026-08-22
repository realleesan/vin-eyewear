<?php

/**
 * tools/thumbnails.php — sinh ảnh nhỏ (thumbnail) cho mọi ảnh trong assets/images.
 *
 *     php tools/thumbnails.php            # sinh những cái còn thiếu
 *     php tools/thumbnails.php --force    # dựng lại tất cả
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO SINH SẴN CHỨ KHÔNG RESIZE LÚC CHẠY
 *
 * Bảng xổ giỏ hàng ở thanh nav hiện năm ảnh 40×40. Nếu để trình duyệt tải ảnh
 * gốc rồi thu nhỏ bằng CSS thì mỗi lần rê chuột là ~140KB đường truyền cho
 * một khối ảnh tổng cộng 8KB thật sự cần.
 *
 * Cách còn lại — một địa chỉ /anh?w=80 tự resize khi có request — thì KHÔNG
 * dùng được ở đây: hosting hiện tại là gói miễn phí có hạn mức CPU, và resize
 * ảnh là việc tốn CPU nhất mà một trang bán kính có thể làm. Ảnh sinh sẵn thì
 * máy chủ chỉ việc trả file tĩnh.
 *
 * Hệ quả: ảnh nhỏ ĐI THEO GIT như mã nguồn, vì workflow deploy đẩy FTP thẳng
 * từ git. Chúng là thứ "dựng ra được" nên về lý không nên nằm trong repo,
 * nhưng ở đây không có bước build nào trên máy chủ để dựng chúng.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CHẠY BẰNG PHP CỦA XAMPP
 *
 *     /opt/lampp/bin/php tools/thumbnails.php
 *
 * PHP hệ thống trên máy này KHÔNG có GD, còn bản trong XAMPP thì có. Script tự
 * kiểm và báo rõ nếu thiếu, thay vì đổ một đống lỗi "undefined function".
 *
 * Máy chủ KHÔNG cần GD: nó chỉ đọc file đã sinh sẵn.
 * ─────────────────────────────────────────────────────────────────────────────
 */

const CANH  = 96;   // cạnh dài nhất của ảnh nhỏ, tính bằng pixel
const CHAT   = 82;  // chất lượng JPEG
const NGUON  = __DIR__ . '/../assets/images';
const DICH   = __DIR__ . '/../assets/images/thumbs';

/*
 * 96px cho một ô hiện ở 40px: màn hình 2× cần 80px, và 96 cho dư một chút để
 * còn dùng lại được ở những ô 48px sau này. Ảnh ở cỡ đó chỉ vài KB nên không
 * đáng để tiết kiệm thêm.
 */

if (!extension_loaded('gd')) {
    fwrite(STDERR, "✗ Bản PHP này không có GD.\n"
        . "  Thử:  /opt/lampp/bin/php tools/thumbnails.php\n");
    exit(1);
}

$force = in_array('--force', $argv, true);

if (!is_dir(DICH) && !mkdir(DICH, 0755, true) && !is_dir(DICH)) {
    fwrite(STDERR, "✗ Không tạo được thư mục " . DICH . "\n");
    exit(1);
}

/**
 * Đọc một file ảnh thành tài nguyên GD.
 *
 * Trả null cho định dạng không đọc được thay vì ném lỗi: thư mục ảnh có thể
 * chứa .svg, .webp hay file rác, và một cái lạ không nên chặn cả lượt chạy.
 */
function doc(string $path, string $mime): ?GdImage
{
    $anh = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png'  => @imagecreatefrompng($path),
        'image/gif'  => @imagecreatefromgif($path),
        default      => false,
    };

    return $anh instanceof GdImage ? $anh : null;
}

$xong = 0;
$bo   = 0;
$loi  = 0;

$duyet = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(NGUON, FilesystemIterator::SKIP_DOTS)
);

foreach ($duyet as $file) {
    /** @var SplFileInfo $file */
    $path = $file->getPathname();

    // Không tự soi chính mình: thư mục đích nằm BÊN TRONG thư mục nguồn.
    if (str_starts_with($path, DICH)) {
        continue;
    }

    if (!$file->isFile()) {
        continue;
    }

    $info = @getimagesize($path);

    if ($info === false) {
        continue;   // không phải ảnh
    }

    [$w, $h, , ] = $info;
    $mime = $info['mime'] ?? '';

    // Đường dẫn tương đối giữ nguyên cấu trúc thư mục con (home/, ar/…), nhờ
    // vậy ProductModel::thumb() ghép được đường dẫn ảnh nhỏ chỉ bằng một lần
    // thay chuỗi, không cần bảng tra.
    $rel  = substr($path, strlen(NGUON) + 1);
    $dich = DICH . '/' . $rel;

    if (!$force && is_file($dich) && filemtime($dich) >= filemtime($path)) {
        $bo++;
        continue;
    }

    /* Ảnh vốn đã nhỏ hơn cạnh đích thì CHÉP NGUYÊN, không phóng to. Phóng to
       vừa mờ vừa nặng hơn bản gốc — vô nghĩa ở cả hai đầu. */
    $canhDai = max($w, $h);

    $thuMuc = dirname($dich);

    if (!is_dir($thuMuc) && !mkdir($thuMuc, 0755, true) && !is_dir($thuMuc)) {
        fwrite(STDERR, "✗ Không tạo được $thuMuc\n");
        $loi++;
        continue;
    }

    if ($canhDai <= CANH) {
        copy($path, $dich);
        $xong++;
        continue;
    }

    $anh = doc($path, $mime);

    if ($anh === null) {
        continue;
    }

    $tyLe = CANH / $canhDai;
    $nw   = max(1, (int) round($w * $tyLe));
    $nh   = max(1, (int) round($h * $tyLe));

    $nho = imagecreatetruecolor($nw, $nh);

    /* PNG và GIF có thể trong suốt. Không giữ kênh alpha thì nền trong suốt
       thành đen đặc — dễ thấy nhất ở logo và icon. */
    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagealphablending($nho, false);
        imagesavealpha($nho, true);
        imagefill($nho, 0, 0, imagecolorallocatealpha($nho, 0, 0, 0, 127));
    }

    imagecopyresampled($nho, $anh, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $ok = match ($mime) {
        'image/png' => imagepng($nho, $dich, 8),
        'image/gif' => imagegif($nho, $dich),
        default     => imagejpeg($nho, $dich, CHAT),
    };

    imagedestroy($nho);
    imagedestroy($anh);

    if ($ok) {
        $xong++;
        printf(
            "  %-40s %5d×%-5d -> %3d×%-3d  %5.1fKB -> %4.1fKB\n",
            $rel,
            $w,
            $h,
            $nw,
            $nh,
            filesize($path) / 1024,
            filesize($dich) / 1024
        );
    } else {
        fwrite(STDERR, "✗ Không ghi được $dich\n");
        $loi++;
    }
}

printf("\n✓ %d ảnh nhỏ đã sinh, %d bỏ qua (đã có sẵn), %d lỗi\n", $xong, $bo, $loi);

exit($loi > 0 ? 1 : 0);
