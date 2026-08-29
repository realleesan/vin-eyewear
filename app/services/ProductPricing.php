<?php

/**
 * ProductPricing — giá bán THẬT của một mặt hàng, sau khuyến mãi có hạn.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO CÓ FILE NÀY
 *
 * Ba cột `sale_price` · `sale_from` · `sale_to` có trong lược đồ và có ô nhập
 * trong form quản trị từ lâu, nhưng tới 2026-08-29 KHÔNG MỘT DÒNG MÃ NÀO đọc
 * chúng: nhân viên điền giá khuyến mãi vào form, bấm lưu, và khách không bao
 * giờ thấy. Đây là chỗ nối dây cho chúng chạy thật.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỘT NƠI QUYẾT ĐỊNH GIÁ, KHÔNG PHẢI SÁU
 *
 * Giá xuất hiện ở sáu chỗ: thẻ sản phẩm, trang chi tiết, trang thử AR, giỏ
 * hàng, lúc tạo đơn, và trang bộ sưu tập. Nếu mỗi chỗ tự đọc `sale_price` rồi
 * tự so ngày thì sớm muộn có chỗ quên so, và cái giá KHÁCH NHÌN THẤY sẽ khác
 * cái giá HOÁ ĐƠN GHI. Sai lệch đó không báo lỗi, chỉ âm thầm thu sai tiền.
 *
 * Nên mọi chỗ ấy đi qua đây. Đường tiền (giỏ hàng, tạo đơn) đi qua
 * VariantModel::priceOf(), mà hàm đó nay cũng gọi giaBan() — tức là chỉ có
 * ĐÚNG MỘT công thức trong cả dự án.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CHỊU ĐƯỢC MẢNG THIẾU CỘT
 *
 * Vài truy vấn chỉ SELECT một phần cột. Thiếu `sale_price` thì coi như không
 * khuyến mãi và trả về giá thường — nhầm theo hướng THU ĐÚNG GIÁ NIÊM YẾT, chứ
 * không phải hướng giảm giá cho một mặt hàng không được giảm.
 */

class ProductPricing
{
    /**
     * Khuyến mãi của mặt hàng này có đang hiệu lực không.
     *
     * Bốn điều kiện, thiếu một là không:
     *
     *   · có `sale_price` và nó > 0;
     *   · nó THẤP HƠN giá thường — "khuyến mãi" cao hơn giá gốc là dữ liệu gõ
     *     nhầm, và áp nó vào là tự tăng giá bán;
     *   · hôm nay không sớm hơn `sale_from` (trống = không có mốc bắt đầu);
     *   · hôm nay không muộn hơn `sale_to` (trống = chạy tới khi tắt tay).
     *
     * So sánh ngày bằng CHUỖI 'Y-m-d'. Hai cột kia kiểu DATE nên MySQL trả về
     * đúng dạng ấy, mà chuỗi ngày ISO thì so sánh từ điển trùng với so sánh
     * thời gian — không cần dựng DateTime cho mỗi mặt hàng trong một trang có
     * hai mươi thẻ.
     */
    public static function dangGiam(array $p): bool
    {
        $sale = $p['sale_price'] ?? null;

        if ($sale === null || (int) $sale <= 0) {
            return false;
        }

        if ((int) $sale >= (int) ($p['price'] ?? 0)) {
            return false;
        }

        $homNay = date('Y-m-d');
        $tu     = (string) ($p['sale_from'] ?? '');
        $den    = (string) ($p['sale_to'] ?? '');

        if ($tu !== '' && $homNay < substr($tu, 0, 10)) {
            return false;
        }

        if ($den !== '' && $homNay > substr($den, 0, 10)) {
            return false;
        }

        return true;
    }

    /** Giá khách thật sự trả cho một chiếc, CHƯA cộng biến thể và tròng. */
    public static function giaBan(array $p): int
    {
        return self::dangGiam($p)
            ? (int) $p['sale_price']
            : (int) ($p['price'] ?? 0);
    }

    /**
     * Giá gạch ngang bên cạnh giá bán, hoặc null nếu không có gì để gạch.
     *
     * ĐANG GIẢM thì gạch GIÁ THƯỜNG, không phải `compare_at_price`.
     *
     * compare_at là "giá niêm yết của hãng" — thứ cửa hàng chưa bao giờ bán
     * tới. Lấy nó làm mốc lúc đang khuyến mãi thì con số phần trăm giảm phồng
     * lên bằng cả hai lần giảm cộng lại, và khách so hai cửa hàng sẽ thấy một
     * mức giảm không có thật. Gạch giá thường thì con số đọc lên đúng nghĩa:
     * "bình thường bán ngần này, hôm nay ngần này".
     */
    public static function giaGach(array $p): ?int
    {
        if (self::dangGiam($p)) {
            return (int) $p['price'];
        }

        $compare = $p['compare_at_price'] ?? null;

        return $compare !== null ? (int) $compare : null;
    }

    /**
     * Ngày kết thúc khuyến mãi để hiện cho khách, hoặc null.
     *
     * Chỉ trả về khi khuyến mãi ĐANG chạy VÀ có mốc kết thúc: "còn tới 30/09"
     * là thứ thúc người ta quyết, còn in một cái hạn cho chương trình không
     * chạy thì chỉ gây hiểu nhầm.
     */
    public static function hanKhuyenMai(array $p): ?string
    {
        if (!self::dangGiam($p)) {
            return null;
        }

        $den = (string) ($p['sale_to'] ?? '');

        return $den !== '' ? substr($den, 0, 10) : null;
    }
}
