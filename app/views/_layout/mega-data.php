<?php

/**
 * _layout/mega-data.php — DỮ LIỆU của một mục sản phẩm trên thanh nav
 * ("Gọng kính" hoặc "Tròng kính"), dùng chung cho HAI bản vẽ:
 *   _layout/mega-menu.php         bảng xổ desktop
 *   _layout/mega-menu-mobile.php  khối <details> trong ngăn kéo
 *
 * Tách riêng để hai bản vẽ không bao giờ lệch nhau về liên kết — cùng lý do
 * header.php dùng chung một $navItems cho thanh nav và ngăn kéo.
 *
 * Cách dùng (file này TRẢ VỀ một mảng, không in gì):
 *   $megaSlug = 'gong-kinh';
 *   $mega     = require VIEWS_PATH . '/_layout/mega-data.php';
 *
 * Nhận từ nơi require: $megaSlug ('gong-kinh' | 'trong-kinh'), $productSub
 * (đoạn thứ hai của URL khi đang ở /san-pham/…, header.php đặt sẵn).
 *
 * Bị require nhiều lần trong cùng một phạm vi, nên KHÔNG khai function/class
 * nào ở đây — khai lần hai là lỗi fatal. Chỉ dùng closure gán biến.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * RUỘT BẢNG = CÁC LÁT CẮT LỌC CỦA CHÍNH TRANG CON ĐÓ
 *
 *   Gọng kính   Kiểu dáng (config taxonomy.frame_styles  -> ?shape=)
 *               Chất liệu (config taxonomy.materials     -> ?material=)
 *   Tròng kính  Loại tròng / Chiết suất / Tính năng — đọc từ bảng quản trị
 *               /quan-tri/thuoc-tinh-trong qua LensOptionModel::visible(),
 *               đúng nguồn mà cột lọc của /san-pham/trong-kinh đang dùng
 *               (-> ?lens_type= / ?lens_index= / ?lens_coat=)
 *
 * Tham số lọc để dạng VÔ HƯỚNG (?shape=oval) chứ không phải mảng: controller
 * nhận cả hai (ProductController::multi), và dạng vô hướng ra URL gọn hơn.
 *
 * Không đếm số hàng cho từng liên kết: làm thế phải chạy ProductModel::catalog()
 * hai lần trên MỌI lượt xem trang chỉ để vẽ header. Lát cắt nào đang không có
 * hàng thì trang đích vẫn hiện mờ lựa chọn đó và báo "chưa có sản phẩm phù
 * hợp" — không phải trang hỏng. LensOptionModel tự nhớ trong request nên gọi ở
 * đây rồi gọi lại ở trang tròng kính không tốn thêm truy vấn.
 */

$megaSlug   = $megaSlug ?? 'gong-kinh';
$productSub = $productSub ?? '';
$taxonomy   = config('taxonomy');
$megaBase   = danhMucUrl($megaSlug);

/** URL trang con kèm một tham số lọc. danhMucUrl() lo phần đường dẫn. */
$megaUrl = static function (array $search) use ($megaBase): string {
    $noi = str_contains($megaBase, '?') ? '&' : '?';

    return $search === [] ? $megaBase : $megaBase . $noi . http_build_query($search);
};

/*
 * Các cột của bảng: [tiêu đề, [[nhãn, url], …]]. Cột rỗng bị bỏ ngay ở đây để
 * phần vẽ bên dưới không phải hỏi lại.
 */
$megaCols = [];

if ($megaSlug === 'trong-kinh') {
    $megaLabel = t('nav.lenses');
    $megaAll   = t('mega.all_lenses');

    /* Nhóm quản trị => tên tham số trên URL của trang tròng kính. Cùng bảng
       nối với $nhomTrong trong ProductModel::catalog(). Màu tròng cố ý không
       đưa lên đây: bảng xổ giữ ba cột cho gọn, màu vẫn lọc được ở trang đích. */
    $megaNhom = [
        'loai-trong' => ['head' => t('mega.lens_type'),  'param' => 'lens_type'],
        'chiet-suat' => ['head' => t('mega.lens_index'), 'param' => 'lens_index'],
        'lop-phu'    => ['head' => t('mega.lens_coat'),  'param' => 'lens_coat'],
    ];

    foreach ($megaNhom as $nhom => $cot) {
        $links = [];

        foreach (LensOptionModel::visible($nhom) as $o) {
            $links[] = [(string) $o['label'], $megaUrl([$cot['param'] => (string) $o['option_key']])];
        }

        $megaCols[] = [$cot['head'], $links];
    }
} else {
    $megaLabel = t('nav.frames');
    $megaAll   = t('mega.all_frames');

    foreach ([
        [t('mega.shape'),    $taxonomy['frame_styles'] ?? []],
        [t('mega.material'), $taxonomy['materials'] ?? []],
    ] as [$head, $slice]) {
        $links = [];

        /* $lat chứ không phải $item: header.php đang ở giữa vòng
           `foreach ($navItems as $item)` khi require file này. */
        foreach ($slice as $lat) {
            $links[] = [(string) $lat['label'], $megaUrl($lat['search'])];
        }

        $megaCols[] = [$head, $links];
    }
}

return [
    'slug'  => $megaSlug,
    'base'  => $megaBase,
    'label' => $megaLabel,
    'all'   => $megaAll,
    'cols'  => array_values(array_filter($megaCols, static fn (array $c): bool => $c[1] !== [])),
    'on'    => $productSub === $megaSlug,
];
