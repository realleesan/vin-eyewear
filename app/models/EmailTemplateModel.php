<?php

/**
 * app/models/EmailTemplateModel.php — mẫu thư gửi cho khách.
 *
 * SRS v2.1.0 — FR-EM-09: *"Mẫu email do cửa hàng sửa được trong khu quản trị,
 * mỗi mẫu có hai bản Việt và Anh."*
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MẪU NẰM TRONG CSDL, KHÔNG NẰM TRONG MÃ NGUỒN
 *
 * Câu chữ trong thư là thứ chủ cửa hàng đổi, không phải lập trình viên: một
 * dòng "cảm ơn bạn đã chọn Vin Eyewear" sửa thành gì là chuyện của người bán
 * hàng. Để trong file PHP nghĩa là mỗi lần đổi một dấu phẩy phải deploy.
 *
 * Ranh giới giống hệt SettingModel: config/*.php cho giá trị LẬP TRÌNH VIÊN
 * quyết định, bảng CSDL cho giá trị NGƯỜI VẬN HÀNH quyết định.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BỐN CỘT NỘI DUNG, HAI CỘT TIẾNG ANH ĐỂ TRỐNG
 *
 * Song ngữ là đợt 9. Cột dựng sẵn ngay bây giờ vì thêm cột vào một bảng đã có
 * dữ liệu thật là thêm một migration, một lần deploy, và một lần nữa phải nhớ
 * ra rằng nó tồn tại.
 *
 * Ô tiếng Anh trống thì noiDung() lùi về bản tiếng Việt — đúng luật dự phòng
 * FR-SN-06, và nhờ vậy bật song ngữ lên ở đợt 9 không làm thư nào thành trắng.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BẢNG CHƯA CÓ THÌ KHÔNG NÉM LỖI
 *
 * Cùng lối phòng thủ với AuditLogModel và RefundRequestModel. Lớp này bị hỏi
 * ngay trong luồng đặt hàng và đổi trạng thái đơn; một máy chưa chạy migration
 * mà ném lỗi ở đây là hỏng đúng nghiệp vụ chính — thứ FR-EM-06 cấm.
 */

class EmailTemplateModel extends BaseModel
{
    protected static string $table = 'email_templates';

    /** Bảng đã có chưa. */
    public static function available(): bool
    {
        return Database::tableExists('email_templates');
    }

    /**
     * Toàn bộ mẫu, sắp theo mã — cho màn quản trị.
     *
     * Sắp theo `key` chứ không theo `nhan`: mã có tiền tố nhóm (don. · tien. ·
     * lich. · kho.), nên sắp theo nó là tự gom các mẫu cùng phân hệ lại với
     * nhau. Sắp theo nhãn tiếng Việt thì "Đã hoàn tiền cọc" nằm cạnh "Đã nhận
     * lịch hẹn" — hai thứ không liên quan gì.
     */
    public static function all(?string $orderBy = null, ?int $limit = null): array
    {
        /* HAI THAM SỐ CỐ Ý BỎ QUA, và chữ ký phải giữ nguyên.

           PHP từ chối nạp lớp nếu chữ ký hẹp hơn BaseModel::all() — thử bỏ hai
           tham số này đi là cả khu quản trị chết ngay ở dòng require. Nên
           chúng ở lại, nhưng không được dùng:

             $orderBy  thứ tự ở đây là một quyết định nghiệp vụ, không phải
                       lựa chọn của nơi gọi — xem khối chú thích trên. Nhận
                       chuỗi SQL thô từ bên ngoài cho một bảng 15 dòng cũng
                       chẳng để làm gì.
             $limit    bảng này có ĐÚNG 15 dòng, do migration nạp và không có
                       màn nào thêm được dòng mới. Cắt bớt là giấu mất một mốc
                       thư khỏi màn duy nhất sửa được nó. */
        if (!self::available()) {
            return [];
        }

        return Database::fetchAll('SELECT * FROM email_templates ORDER BY `key` ASC');
    }

    /**
     * @var array<string, array|null> Nhớ trong MỘT lượt truy cập — xem tim().
     */
    private static array $nho = [];

    /**
     * Một mẫu theo mã, hoặc null.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * NHỚ LẠI TRONG MỘT LƯỢT TRUY CẬP
     *
     * Hàm này bị hỏi MỘT LẦN CHO MỖI LÁ THƯ, qua noiDung() trong xepHang(). Ở
     * đường một lá một lượt thì không sao, nhưng có ba chỗ xếp hàng theo lô:
     *
     *   quetNhacLichHen()   tới 50 lịch hẹn, và cả 50 lá cùng mẫu 'lich.nhac'
     *   hangCoLai*()        tới 200 lượt chờ, cùng mẫu 'kho.co_hang'
     *   canhBaoSapQuaHan()  tới 20 đơn, cùng mẫu 'don.sap_qua_han'
     *
     * Hai chỗ đầu chạy trên lượt duyệt trang của một người thật (bộ quét định
     * kỳ) hoặc trong lượt bấm Lưu của nhân viên. 200 câu SELECT giống hệt nhau
     * ở đó là 200 vòng đi-về không ai cần.
     *
     * NHỚ CẢ KẾT QUẢ NULL: một mã sự kiện chưa khai trong bảng bị hỏi đúng
     * chừng ấy lần, và nó là ca xấu nhất — mỗi lần trượt còn kèm một dòng
     * error_log ở xepHang().
     *
     * Bộ nhớ này CHỈ SỐNG TRONG MỘT LƯỢT TRUY CẬP, nên không có chuyện đọc
     * phải câu chữ cũ sau khi cửa hàng vừa sửa mẫu. luu() vẫn xoá nó đi cho
     * chắc: cùng một lượt POST vừa ghi xong rồi đọc lại để dựng màn hình.
     */
    public static function tim(string $key): ?array
    {
        if (array_key_exists($key, self::$nho)) {
            return self::$nho[$key];
        }

        if (!self::available()) {
            // KHÔNG nhớ ca này: available() đã có bộ nhớ riêng trong
            // Database::tableExists(), và bảng có thể xuất hiện giữa chừng khi
            // ai đó vừa chạy migration.
            return null;
        }

        return self::$nho[$key] = Database::fetchOne(
            'SELECT * FROM email_templates WHERE `key` = :k',
            ['k' => $key]
        );
    }

    /**
     * Tiêu đề và ruột thư theo ngôn ngữ, đã áp luật dự phòng.
     *
     * @return array{subject:string, body:string}|null null khi không có mẫu
     *         hoặc mẫu đang tắt
     */
    public static function noiDung(string $key, string $ngonNgu = 'vi'): ?array
    {
        $mau = self::tim($key);

        if ($mau === null || (int) $mau['bat'] !== 1) {
            return null;
        }

        /* DỰ PHÒNG VỀ TIẾNG VIỆT — FR-SN-06.

           Ô tiếng Anh trống là trạng thái BÌNH THƯỜNG ở đợt 6, không phải lỗi
           cấu hình. Thiếu bản dịch thì gửi bản tiếng Việt; thư đến tay khách
           bằng thứ tiếng họ không chọn vẫn hơn hẳn một lá thư trống. */
        $subject = $ngonNgu === 'en' ? trim((string) ($mau['subject_en'] ?? '')) : '';
        $body    = $ngonNgu === 'en' ? trim((string) ($mau['body_en'] ?? '')) : '';

        if ($subject === '') {
            $subject = (string) $mau['subject_vi'];
        }

        if ($body === '') {
            $body = (string) $mau['body_vi'];
        }

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Lưu một mẫu. Chỉ ghi bốn cột nội dung và cờ bật — KHÔNG cho sửa `key`,
     * `nhan`, `mo_ta` hay `bien`.
     *
     * Bốn cột đó mô tả CHÍNH SỰ KIỆN, không phải câu chữ: đổi `key` là làm mọi
     * lời gọi trong mã trỏ vào hư không, và đổi `bien` không làm biến mới xuất
     * hiện. Người sửa mẫu cần đọc chúng, không cần sửa chúng.
     */
    public static function luu(string $key, array $v, ?string $boi = null): array
    {
        $mau = self::tim($key);

        if ($mau === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy mẫu thư này.'];
        }

        $subject = trim((string) ($v['subject_vi'] ?? ''));
        $body    = trim((string) ($v['body_vi'] ?? ''));

        /* BẢN TIẾNG VIỆT LÀ BẮT BUỘC — FR-SN-14, và ở đây nó nặng hơn một luật
           song ngữ: đây là bản dự phòng của chính nó. Để trống là mọi thư loại
           này gửi đi với tiêu đề rỗng. */
        if ($subject === '' || $body === '') {
            return ['ok' => false, 'error' => 'Tiêu đề và nội dung tiếng Việt là bắt buộc.'];
        }

        /* ─────────────────────────────────────────────────────────────────────
           BIẾN LẠ LÀ LỖI, KHÔNG PHẢI CHUYỆN NHỎ — và không có cách nào khác
           để phát hiện.

           EmailQueueModel::thay() thay mọi {{ten}} không nhận ra bằng CHUỖI
           RỖNG, cố ý: để nguyên dấu ngoặc thì khách đọc được phần ruột của hệ
           thống. Cái giá là một biến gõ sai KHÔNG để lại dấu vết nào — không
           lỗi, không dòng nhật ký, hàng chờ trông vẫn bình thường. Thư gửi đi
           chỉ thiếu một mẩu chữ, và người duy nhất phát hiện là khách.

           Nên chặn ngay lúc lưu, chỗ duy nhất còn có một con người để nói.

           BÁO LỖI CHỨ KHÔNG TỰ SỬA: 'ma_dom' có thể là gõ nhầm 'ma_don', mà
           cũng có thể là người ta định dùng một biến chưa có. Đoán hộ là chọn
           thay họ. */
        $chapNhan = array_map(
            'trim',
            explode(',', str_replace(['{{', '}}'], '', (string) ($mau['bien'] ?? '')))
        );

        foreach ([$subject, $body, (string) ($v['subject_en'] ?? ''), (string) ($v['body_en'] ?? '')] as $doan) {
            preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $doan, $m);

            foreach ($m[1] as $ten) {
                if (!in_array($ten, $chapNhan, true)) {
                    return ['ok' => false, 'error' => sprintf(
                        'Mẫu này không có biến {{%s}}. Chỉ dùng được: %s',
                        $ten,
                        (string) ($mau['bien'] ?? '(không có biến nào)')
                    )];
                }
            }
        }

        /* RUỘT THƯ CÓ TRẦN, và trần phải nằm ở đây chứ không ở cột CSDL.

           `body_vi` là TEXT — 65 535 BYTE, mà một ký tự tiếng Việt tốn ba.
           Vượt trần thì MySQL ở chế độ nghiêm ném "Data too long", luuMau()
           không bắt, và người dùng nhận một trang 500 kèm mất trắng thứ vừa
           gõ. Ở chế độ lỏng thì tệ hơn: MySQL cắt giữa một thẻ HTML và mọi lá
           thư loại ấy về sau mang thẻ hở.

           20 000 ký tự là dư sức cho một lá thư giao dịch và còn cách xa trần
           byte kể cả khi mọi ký tự đều có dấu. */
        foreach ([['Nội dung tiếng Việt', $body], ['Nội dung tiếng Anh', (string) ($v['body_en'] ?? '')]] as [$ten, $doan]) {
            if (utf8Length($doan) > 20000) {
                return ['ok' => false, 'error' => $ten . ' quá dài (tối đa 20.000 ký tự).'];
            }
        }

        // Cờ bật CŨ, đọc trước khi ghi đè — nơi gọi cần nó cho dòng nhật ký:
        // bật/tắt một mẫu là quyết định có hậu quả lớn nhất ở màn này.
        $batCu = (int) $mau['bat'];

        Database::execute(
            'UPDATE email_templates
                SET subject_vi = :sv, body_vi = :bv,
                    subject_en = :se, body_en = :be,
                    bat = :bat, updated_by = :boi
              WHERE `key` = :k',
            [
                'sv'  => utf8Substr($subject, 0, 200),
                'bv'  => $body,
                'se'  => ($x = trim((string) ($v['subject_en'] ?? ''))) !== ''
                    ? utf8Substr($x, 0, 200) : null,
                'be'  => ($y = trim((string) ($v['body_en'] ?? ''))) !== '' ? $y : null,
                'bat' => ($v['bat'] ?? '') === '1' ? 1 : 0,
                'boi' => $boi,
                'k'   => $key,
            ]
        );

        // Câu chữ vừa đổi — bỏ bản nhớ để lượt đọc sau trong cùng request
        // (dựng lại màn hình) thấy đúng thứ vừa ghi.
        unset(self::$nho[$key]);

        $batMoi = ($v['bat'] ?? '') === '1' ? 1 : 0;

        return ['ok' => true, 'bat_cu' => $batCu, 'bat_moi' => $batMoi];
    }
}
