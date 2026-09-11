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
/* ┌─ MỘT DANH SÁCH PHẲNG, MỘT TRỤC — KHÔNG CÒN NHÓM ──────────────────────
   │ Theo tham chiếu chủ dự án đưa: bảng xổ là một cột chữ, "Xem tất cả"
   │ đứng đầu, rồi tới danh sách. Không tiêu đề nhóm, không cột thứ hai.
   │
   │ Nên mỗi bảng giữ ĐÚNG MỘT TRỤC phân loại:
   │   Gọng kính  → các DÁNG kính  (oval, vuông, mắt mèo…)
   │   Tròng kính → các LOẠI tròng (đơn tròng, đa tròng…)
   │
   │ VÌ SAO KHÔNG GỘP HẾT VÀO MỘT CỘT: bỏ tiêu đề nhóm rồi mà vẫn đổ cả
   │ chất liệu vào sau dáng thì ra "Oval · Vuông · … · Acetate · Kim loại" —
   │ hai trục khác nhau nằm liền một mạch, không có gì báo cho người đọc biết
   │ chỗ nào là chỗ chuyển. Một trục thì tự nó rõ.
   │
   │ BA TRỤC BỊ GỠ KHỎI BẢNG XỔ VẪN LỌC ĐƯỢC ở trang danh mục — chất liệu
   │ gọng, chiết suất và lớp phủ tròng đều có mặt trong bảng bộ lọc của
   │ /san-pham (xem $megaNhom cũ trong lịch sử git nếu cần dựng lại).
   └──────────────────────────────────────────────────────────────────────── */
$megaLinks = [];

if ($megaSlug === 'trong-kinh') {
    $megaLabel = t('nav.lenses');
    $megaAll   = t('mega.all_lenses');

    foreach (LensOptionModel::visible('loai-trong') as $o) {
        $megaLinks[] = [(string) $o['label'], $megaUrl(['lens_type' => (string) $o['option_key']])];
    }
} else {
    $megaLabel = t('nav.frames');
    $megaAll   = t('mega.all_frames');

    /* `$lat` chứ không `$item`: file này được require BÊN TRONG
       `foreach ($navItems as $item)` của header.php — trùng tên là ghi đè
       biến vòng lặp bên ngoài và hàng nav mất mục. */
    foreach ($taxonomy['frame_styles'] ?? [] as $lat) {
        $megaLinks[] = [(string) $lat['label'], $megaUrl($lat['search'])];
    }
}

return [
    'slug'  => $megaSlug,
    'base'  => $megaBase,
    'label' => $megaLabel,
    'all'   => $megaAll,
    'links' => $megaLinks,
    'on'    => $productSub === $megaSlug,
];
