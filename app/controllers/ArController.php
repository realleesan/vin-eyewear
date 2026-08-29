<?php

/**
 * ArController — thử kính AR (/thu-ar).
 *
 * Port từ src/routes/thu-ar.tsx + src/components/ar/ar-tryon.tsx.
 *
 * Mỗi mẫu gọng AR được nối với sản phẩm thật trong DB qua slug, để giá và
 * tình trạng còn hàng luôn khớp trang bán hàng — không gõ cứng như bản
 * Lovable (ar-frames.ts có price/compareAt riêng, dễ lệch với giá thật).
 */

class ArController extends BaseController
{
    public function tryon(): void
    {
        $ar     = config('ar');
        $frames = [];

        foreach ($ar['frames'] as $frame) {
            $product = ProductModel::findVisibleBySlug($frame['slug']);

            // Sản phẩm bị ẩn hoặc xoá thì bỏ mẫu AR đó khỏi danh sách, thay
            // vì hiện một mẫu bấm "Mua ngay" ra trang 404.
            if ($product === null) {
                continue;
            }

            $frames[] = $frame + [
                'productId' => $product['id'],
                /* Qua ProductPricing như mọi chỗ khác: màn thử AR có nút
                   "Mua" dẫn thẳng sang giỏ, nên con số ở đây mà khác con số
                   giỏ hàng tính là khách thấy giá nhảy giữa hai bước. */
                'price'     => ProductPricing::giaBan($product),
                'compareAt' => ProductPricing::giaGach($product),
                'inStock'   => ProductModel::inStock($product),
                'url'       => '/san-pham/' . rawurlencode($product['slug']),
            ];
        }

        /*
         * ?gong=<slug> — nút "Thử AR" trên thẻ sản phẩm dẫn tới đây.
         *
         * Đưa mẫu được chọn lên ĐẦU danh sách thay vì thêm một biến "đang
         * chọn": view và JS đều lấy phần tử đầu tiên làm mặc định (radio thứ
         * nhất checked, ảnh trên khung hình, hộp mua hàng), nên chỉ cần đổi
         * thứ tự là cả ba chỗ khớp nhau, không phải sửa gì thêm.
         *
         * Slug lạ thì bỏ qua, giữ nguyên thứ tự cũ — không báo lỗi vì đây chỉ
         * là gợi ý chọn sẵn, không phải điều kiện để trang chạy.
         */
        $wanted = (string) ($_GET['gong'] ?? '');

        if ($wanted !== '') {
            foreach ($frames as $i => $frame) {
                if ($frame['slug'] === $wanted) {
                    array_unshift($frames, ...array_splice($frames, $i, 1));
                    break;
                }
            }
        }

        $this->renderView('ar/tryon', [
            'pageTitle'    => 'Thử kính AR — Vin Eyewear',
            'metaDesc'     => 'Thử kính trực tuyến bằng camera. Tự động căn gọng theo '
                            . 'khuôn mặt, đổi màu và chọn hiệu ứng tròng ngay trên trình duyệt.',
            'frames'       => $frames,
            'colors'       => $ar['colors'],
            'lensEffects'  => $ar['lens_effects'],
            'sizes'        => $ar['sizes'],
            'faceAdvice'   => $ar['face_advice'],
            'vision'       => $ar['vision'],
        ]);
    }
}
