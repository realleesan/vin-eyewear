<?php

/**
 * app/models/SettingModel.php — cấu hình cửa hàng tự sửa được.
 *
 * SRS v2.1.0 — FR-NK-04 (mốc tính doanh thu).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO KHÔNG ĐỂ TRONG .env NHƯ TRƯỚC
 *
 * Mốc tính doanh thu trước đây nằm ở `STATS_SINCE` trong tệp .env trên máy chủ.
 * Đổi nó nghĩa là: mở FTP, sửa một tệp ẩn, không sai một ký tự, rồi hy vọng lần
 * deploy sau không ghi đè. Trên thực tế điều đó nghĩa là chủ cửa hàng phải nhờ
 * người kỹ thuật, và mốc ấy gần như không bao giờ được đổi.
 *
 * Bảng này là chỗ cho những giá trị NGƯỜI VẬN HÀNH quyết định, khác hẳn
 * config/*.php vốn là chỗ cho những giá trị LẬP TRÌNH VIÊN quyết định. Ranh
 * giới ấy đáng giữ: một khoá vào nhầm nơi thì hoặc chủ cửa hàng không sửa được
 * thứ họ nên sửa, hoặc họ sửa được thứ đủ sức làm hỏng trang.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỌI GIÁ TRỊ LÀ CHUỖI
 *
 * Không cột kiểu, không JSON. Nơi gọi tự ép kiểu, vì chỉ nơi gọi mới biết một
 * chuỗi rỗng nghĩa là 0, là null, hay là "chưa đặt". Thêm một cột `type` ở đây
 * là mời người sau dựng một hệ thống kiểu nhỏ trong một bảng bốn cột.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BẢNG CHƯA CÓ THÌ IM LẶNG TRẢ MẶC ĐỊNH
 *
 * Cùng lối phòng thủ với AuditLogModel và RefundRequestModel: một máy chưa chạy
 * migration thì mất mốc doanh thu, chứ không mất cả màn hình tổng quan.
 */

class SettingModel extends BaseModel
{
    protected static string $table = 'app_settings';

    /** Mốc tính doanh thu — FR-NK-04. Rỗng = tính từ đầu. */
    public const MOC_DOANH_THU = 'moc_doanh_thu';

    /* KHÔNG CÓ KHOÁ CHO MỐC QUÉT ĐƠN QUÁ HẠN Ở ĐÂY — cố ý.

       Bản đầu của FR-TT-11 giữ mốc ấy trong bảng này. Nhưng phép quét chạy
       TRƯỚC router ở mọi lượt GET, kể cả những trang xưa nay không đụng tới
       CSDL, nên chỉ riêng việc HỎI mốc đã mở một kết nối ở 999/1000 lượt.
       Mốc nay là mtime của storage/quet/don-qua-han.txt — xem
       OrderModel::quetDonQuaHan(). */

    /**
     * Bộ nhớ tạm trong MỘT lượt truy cập.
     *
     * Mốc doanh thu bị hỏi ba lần ở bảng tổng quan (doanh thu hôm nay, tháng
     * này, tổng), và mốc quét bị hỏi mỗi lượt. Ba vòng đi-về tới CSDL cho một
     * giá trị không đổi trong cùng một lượt là ba lần thừa.
     *
     * KHÔNG cache giữa các lượt: giá trị này người ta vừa sửa xong ở màn quản
     * trị và mong thấy đổi ngay.
     *
     * @var array<string, ?string>|null
     */
    private static ?array $nho = null;

    /** Bảng đã có chưa. */
    public static function available(): bool
    {
        return Database::tableExists('app_settings');
    }

    /** Đọc một khoá; trả $macDinh khi chưa đặt hoặc bảng chưa có. */
    public static function get(string $ten, ?string $macDinh = null): ?string
    {
        if (self::$nho === null) {
            self::$nho = [];

            if (self::available()) {
                foreach (Database::fetchAll('SELECT name, value FROM app_settings') as $d) {
                    self::$nho[(string) $d['name']] = $d['value'] === null ? null : (string) $d['value'];
                }
            }
        }

        /* array_key_exists chứ không ?? — một dòng lưu giá trị NULL là MỘT
           QUYẾT ĐỊNH ĐÃ GHI ("bỏ mốc"), không phải "chưa có dòng nào". Với ??
           thì hai thứ ấy trả về cùng một kết quả, và mocDoanhThu() không phân
           biệt nổi để lùi về .env cho đúng ca. */
        return array_key_exists($ten, self::$nho) ? self::$nho[$ten] : $macDinh;
    }

    /**
     * Ghi một khoá.
     *
     * INSERT … ON DUPLICATE KEY UPDATE chứ không "hỏi rồi ghi": hai lượt truy
     * cập cùng ghi một khoá — chuyện xảy ra thật với mốc quét ở FR-TT-11 khi
     * hai người mở trang cùng lúc — thì lối hỏi-trước sinh ra lỗi trùng khoá
     * chính, và nó nổ ở giữa một lượt duyệt trang bình thường.
     *
     * @return bool false khi bảng chưa có (nơi gọi tự quyết định nói gì)
     */
    public static function set(string $ten, ?string $giaTri, ?string $boi = null): bool
    {
        if (!self::available()) {
            return false;
        }

        Database::execute(
            'INSERT INTO app_settings (name, value, updated_by)
                  VALUES (:n, :v, :b)
             ON DUPLICATE KEY UPDATE value = VALUES(value),
                                     updated_by = VALUES(updated_by),
                                     updated_at = NOW()',
            ['n' => $ten, 'v' => $giaTri, 'b' => $boi]
        );

        // Bộ nhớ tạm của chính lượt này phải thấy giá trị vừa ghi: màn quản trị
        // ghi xong là vẽ lại ngay, và vẽ ra số cũ thì người bấm tưởng không lưu.
        if (self::$nho !== null) {
            self::$nho[$ten] = $giaTri;
        }

        return true;
    }

    /**
     * Mốc tính doanh thu, dạng 'Y-m-d', hoặc null nếu tính từ đầu.
     *
     * ĐỌC BẢNG TRƯỚC, .env SAU. Giá trị cũ trong `STATS_SINCE` vẫn được tôn
     * trọng cho tới khi có người đặt mốc trên màn hình lần đầu — nếu không, lần
     * deploy đưa FR-NK-04 lên sẽ làm mọi con số doanh thu nhảy vọt trong im
     * lặng, và không ai biết vì sao.
     */
    public static function mocDoanhThu(): ?string
    {
        /* PHÂN BIỆT "CHƯA AI ĐẶT" VỚI "ĐÃ ĐẶT LÀ RỖNG" — và đó là cả vấn đề.

           Bản đầu hỏi get(…, '') rồi coi chuỗi rỗng là "chưa đặt" và lùi về
           .env. Hệ quả: quản trị viên xoá trắng ô ngày rồi Lưu — một lựa chọn
           thật, nghĩa là "tính trên toàn bộ dữ liệu" — nhận được câu "Đã bỏ
           mốc", một dòng nhật ký nói đã bỏ mốc, và một bảng tổng quan vẫn lọc
           theo STATS_SINCE y như cũ. Cả lời xác nhận lẫn vết kiểm toán đều nói
           sai.

           Dùng một giá trị mặc định KHÔNG THỂ TRÙNG với dữ liệu thật để nhận ra
           "không có dòng nào". Có dòng — kể cả dòng rỗng — nghĩa là đã có người
           quyết định, và quyết định của họ thắng .env. */
        $chuaDat = "\0chua-dat";
        $moc     = self::get(self::MOC_DOANH_THU, $chuaDat);

        if ($moc === $chuaDat) {
            // Chưa ai đặt trên màn hình: tôn trọng mốc cũ trong .env, nếu không
            // thì lần deploy đưa FR-NK-04 lên sẽ làm mọi con số nhảy vọt trong
            // im lặng.
            $moc = (string) config('app.thong_ke_tu', '');
        }

        $moc = trim((string) $moc);

        return $moc === '' ? null : $moc;
    }
}
