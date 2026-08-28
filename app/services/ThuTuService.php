<?php

/**
 * ThuTuService — đổi chỗ hai dòng liền nhau trong một bảng có cột `sort_order`.
 *
 * Dùng cho ba màn hình quản trị có nút ↑↓: Danh mục · Bộ sưu tập · Gói chiết
 * suất. Cả ba làm cùng một việc, nên viết một lần ở đây thay vì ba bản chép.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO GHI LẠI SỐ THỨ TỰ CHO CẢ BẢNG, KHÔNG CHỈ HOÁN ĐỔI HAI DÒNG
 *
 * Cách hiển nhiên là đọc `sort_order` của hai dòng rồi tráo chúng cho nhau. Nó
 * hỏng ngay ở ca thường gặp nhất: bảng nào cũng khai `sort_order NOT NULL
 * DEFAULT 0`, nên trước lần bấm đầu tiên MỌI DÒNG đều mang số 0. Tráo 0 với 0
 * thì không có gì xảy ra, mà người bấm thì thấy nút có phản hồi (trang tải
 * lại) và kết luận là tính năng hỏng.
 *
 * Nên: nhận vào DÃY ID THEO ĐÚNG THỨ TỰ ĐANG HIỆN, đổi chỗ hai phần tử trong
 * dãy ấy, rồi ghi lại `sort_order` = vị trí cho toàn bộ. Sau lần bấm đầu tiên
 * cả bảng có số thứ tự thật, và mọi lần sau chỉ là ghi lại đúng những con số
 * gần như không đổi. Bảng nào ở đây cũng dưới hai chục dòng nên chi phí không
 * đáng kể — đổi lại là không còn trạng thái "toàn số 0" nào để phải xử lý
 * riêng.
 *
 * DÃY ID PHẢI DO NƠI GỌI TRUYỀN VÀO, không tự truy vấn ở đây: mỗi bảng có một
 * thứ tự hiển thị riêng (danh mục sắp theo sort_order rồi tên, bộ sưu tập còn
 * xét ngày ra mắt), và service này không nên biết luật của từng bảng.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ThuTuService
{
    /**
     * Đổi chỗ một dòng với dòng liền trên ('len') hoặc liền dưới ('xuong').
     *
     * @param string   $bang         Tên bảng — CHỈ nhận hằng viết thẳng trong
     *                               controller, không bao giờ nhận từ request.
     * @param string[] $idsTheoThuTu Id của mọi dòng, theo đúng thứ tự đang hiện.
     * @param string   $id           Dòng người dùng bấm.
     * @param string   $huong        'len' hoặc 'xuong'.
     *
     * @return bool false khi không có gì để đổi (dòng đầu bấm lên, dòng cuối
     *              bấm xuống, hoặc id không có trong dãy) — nơi gọi tự quyết
     *              có báo gì không.
     */
    public static function doiCho(string $bang, array $idsTheoThuTu, string $id, string $huong): bool
    {
        // Chốt chặn cuối: tên bảng luôn là hằng ở nơi gọi, nhưng nó đi thẳng
        // vào chuỗi SQL (tên bảng không tham số hoá được), nên vẫn phải kiểm.
        if (preg_match('/^[a-z_][a-z0-9_]*$/', $bang) !== 1) {
            throw new InvalidArgumentException("Tên bảng không hợp lệ: {$bang}");
        }

        $ids = array_values(array_map('strval', $idsTheoThuTu));
        $vt  = array_search((string) $id, $ids, true);

        if ($vt === false) {
            return false;
        }

        $dich = $huong === 'len' ? $vt - 1 : $vt + 1;

        if ($dich < 0 || $dich >= count($ids)) {
            return false;
        }

        [$ids[$vt], $ids[$dich]] = [$ids[$dich], $ids[$vt]];

        /* Một giao dịch cho cả loạt UPDATE: dừng giữa chừng thì bảng còn lại
           một thứ tự lẫn lộn không ai dựng lại được — hai dòng cùng số, một số
           bị khuyết. Thà không đổi gì. */
        Database::transaction(static function () use ($bang, $ids): void {
            foreach ($ids as $i => $rowId) {
                Database::execute(
                    "UPDATE `{$bang}` SET sort_order = :thu_tu WHERE id = :id",
                    ['thu_tu' => $i, 'id' => $rowId]
                );
            }
        });

        return true;
    }

    /**
     * Đọc hướng từ request, trả '' nếu không hợp lệ.
     *
     * Gom vào đây để ba controller không mỗi nơi kiểm một kiểu — và để chỗ
     * duy nhất biết hai chuỗi 'len'/'xuong' là service này.
     */
    public static function huongTuRequest(mixed $raw): string
    {
        $huong = is_string($raw) ? $raw : '';

        return in_array($huong, ['len', 'xuong'], true) ? $huong : '';
    }
}
