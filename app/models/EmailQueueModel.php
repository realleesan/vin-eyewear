<?php

/**
 * app/models/EmailQueueModel.php — hàng chờ và sổ kết quả gửi email.
 *
 * SRS v2.1.0 — FR-EM-01..08, FR-SP-18, FR-LH-08, và vế còn thiếu của FR-TT-11.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * XẾP HÀNG, KHÔNG GỬI THẲNG — FR-EM-06
 *
 * *"Gửi email không bao giờ chặn nghiệp vụ chính. Đặt hàng, đổi trạng thái hay
 * huỷ đơn vẫn thành công kể cả khi máy chủ thư không trả lời."*
 *
 * Nên xepHang() chỉ ghi một dòng vào bảng rồi trả về ngay. Việc gửi do
 * quetGui() làm, ở một lượt truy cập khác. Một máy chủ SMTP treo 12 giây không
 * còn là 12 giây cộng vào thời gian đặt hàng của khách.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG THỬ GỬI KHI CHƯA CÓ ĐƯỜNG RA
 *
 * Mailer::canDeliver() trả false với driver 'log' và với 'smtp' chưa cấu hình.
 * Hosting hiện tại (InfinityFree miễn phí) thì KHÔNG driver nào gửi được — vô
 * hiệu hoá mail() và chặn cổng SMTP ra ngoài.
 *
 * Trong tình huống đó quetGui() KHÔNG đụng vào hàng chờ. Không phải để tiết
 * kiệm: mỗi lần thử là một lần `so_lan_thu` tăng, và sau vài lượt truy cập
 * toàn bộ thư sẽ nằm ở 'hong' — một cái sổ đầy thất bại do một nguyên nhân
 * DUY NHẤT là chưa ai cấu hình đường gửi.
 *
 * Để nguyên ở 'cho' thì ngày cửa hàng nối được đường gửi, cả hàng chờ tự chảy
 * đi, không mất lá nào và không phải bấm gửi lại từng cái.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NỘI DUNG DỰNG NGAY LÚC SỰ KIỆN, KHÔNG DỰNG LÚC GỬI
 *
 * Xem khối chú thích trong migration. Tóm tắt: một lá thư "đơn VE-1234 đã xác
 * nhận" nằm chờ ba ngày rồi đơn ấy bị huỷ — dựng lại lúc gửi là gửi đi một câu
 * nói về hiện tại dưới tên một sự kiện quá khứ.
 */

class EmailQueueModel extends BaseModel
{
    protected static string $table = 'email_queue';

    /** Trạng thái một lá thư. */
    public const TRANG_THAI = [
        'cho'  => 'Đang chờ gửi',
        'xong' => 'Đã gửi',
        'hong' => 'Gửi hỏng',
        'bo'   => 'Đã bỏ',
    ];

    /** Thử tối đa bao nhiêu lần trước khi chuyển sang 'hong'. */
    public const THU_TOI_DA = 4;

    /**
     * Một lượt quét gửi tối đa bao nhiêu thư.
     *
     * BA, KHÔNG PHẢI MƯỜI. Con số này nhân với thời gian chờ của một lá thư
     * hỏng, và cả tích ấy nằm trong lượt tải trang của một người thật.
     *
     * Mailer::sendSmtp() mở một kết nối TCP+TLS mới cho mỗi lá với hạn chờ 12
     * giây ở cả hai đầu. Mười lá vào một máy chủ SMTP không trả lời là 120
     * giây — quá max_execution_time (thường 30s), nên PHP bị giết giữa vòng
     * lặp và khách thấy TRANG TRẮNG: hàm này chạy trước router nên chưa có
     * dòng HTML nào kịp ra.
     *
     * Ba lá là tối đa 36 giây ở ca tệ nhất, và NGAN_SACH_GIAY dưới đây cắt
     * trước cả ngưỡng đó.
     */
    private const QUET_TOI_DA = 3;

    /**
     * Một lượt quét được tiêu tối đa bao nhiêu giây.
     *
     * Trần cứng đứng cạnh trần số lá: một lá thư chậm 12 giây thì lá thứ hai
     * không được phép bắt đầu. Đo bằng đồng hồ chứ không bằng số lượt vì cái
     * đắt là THỜI GIAN, và nó không tỉ lệ với số thư — chín lá gửi tức thì rẻ
     * hơn một lá gặp máy chủ treo.
     */
    private const NGAN_SACH_GIAY = 10;

    /** Hai lượt quét cách nhau tối thiểu bao nhiêu giây. */
    private const QUET_CACH_NHAU = 120;

    /** Bảng đã có chưa. */
    public static function available(): bool
    {
        return Database::tableExists('email_queue');
    }

    // ========================================================================
    // XẾP HÀNG
    // ========================================================================

    /**
     * Dựng nội dung từ mẫu rồi xếp một lá thư vào hàng chờ.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * KHÔNG NÉM, KHÔNG CHẶN — FR-EM-06
     *
     * Hàm này chạy ngay cạnh việc chính (vừa đặt xong đơn, vừa đổi xong trạng
     * thái). Mọi đường thoát đều trả null lặng lẽ:
     *
     *   · bảng chưa có          máy chưa chạy migration đợt 6
     *   · khách không có email  FR-EM-08 nói thẳng: bỏ qua, KHÔNG báo lỗi
     *   · mẫu thư tắt/không có  cửa hàng chủ động tắt mốc này
     *   · trùng khoá chống lặp  sự kiện đã sinh thư rồi
     *
     * Ba cái đầu ghi error_log; cái thứ tư thì không — nó là hoạt động bình
     * thường, ghi log chỉ làm đầy file.
     *
     * @param  string      $loai     mã sự kiện, khớp email_templates.key
     * @param  string|null $email    địa chỉ nhận; rỗng/null = bỏ qua êm
     * @param  array       $bien     dữ liệu thay vào {{...}} của mẫu
     * @param  string|null $khoa     khoá chống trùng; null = cho gửi nhiều lần
     * @param  string|null $guiSau   'Y-m-d H:i:s' — hẹn giờ gửi, null = ngay
     * @return string|null id lá thư vừa xếp, hoặc null
     */
    public static function xepHang(
        string $loai,
        ?string $email,
        array $bien = [],
        ?string $khoa = null,
        ?string $guiSau = null,
        ?string $userId = null,
        ?string $lienQuanLoai = null,
        ?string $lienQuanId = null,
        string $ngonNgu = 'vi'
    ): ?string {
        try {
            if (!self::available()) {
                return null;
            }

            $email = trim((string) $email);

            /* FR-EM-08 — KHÁCH KHÔNG CÓ EMAIL THÌ BỎ QUA, KHÔNG BÁO LỖI.

               Phần lớn khách của cửa hàng đăng ký bằng số điện thoại và không
               bao giờ điền email (xem `users`.`email` cho phép rỗng). Với họ,
               "không gửi được thư" là trạng thái bình thường của mọi đơn hàng,
               không phải một sự cố. Ghi log ở đây là ghi vài nghìn dòng mỗi
               tháng cho một việc không ai cần làm gì. */
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }

            $mau = EmailTemplateModel::noiDung($loai, $ngonNgu);

            if ($mau === null) {
                // Mẫu bị tắt là lựa chọn của cửa hàng, không log. Mẫu KHÔNG CÓ
                // thì là lỗi lập trình — một mã sự kiện chưa khai trong bảng.
                if (EmailTemplateModel::tim($loai) === null) {
                    error_log('EmailQueueModel: không có mẫu thư "' . $loai . '"');
                }

                return null;
            }

            $id = uuid();

            Database::execute(
                'INSERT INTO email_queue
                    (id, loai, nguoi_nhan, user_id, ngon_ngu, lien_quan_loai,
                     lien_quan_id, subject, body, gui_sau, khoa_chong_trung)
                 VALUES
                    (:id, :loai, :email, :uid, :nn, :lql, :lqi, :sub, :body,
                     COALESCE(:gs, NOW()), :khoa)',
                [
                    'id'    => $id,
                    'loai'  => $loai,
                    'email' => utf8Substr($email, 0, 190),
                    'uid'   => $userId,
                    'nn'    => $ngonNgu,
                    'lql'   => $lienQuanLoai,
                    'lqi'   => $lienQuanId,
                    'sub'   => utf8Substr(self::thay($mau['subject'], $bien, false), 0, 255),
                    'body'  => self::dungKhung(self::thay($mau['body'], $bien)),
                    'gs'    => $guiSau,
                    'khoa'  => $khoa,
                ]
            );

            return $id;
        } catch (PDOException $e) {
            /* TRÙNG KHOÁ CHỐNG LẶP LÀ CHUYỆN BÌNH THƯỜNG, không phải lỗi.

               Nhân viên bấm nhầm "Đã xác nhận" rồi bấm lại; bộ quét nhắc lịch
               chạy hai lượt trong cùng một ngày. Khoá duy nhất chặn đúng những
               ca đó — im lặng là hành vi đúng. 23000 là lớp lỗi ràng buộc. */
            if (($e->getCode() ?? '') !== '23000') {
                error_log('EmailQueueModel::xepHang: ' . $e->getMessage());
            }

            return null;
        } catch (Throwable $e) {
            error_log('EmailQueueModel::xepHang: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Thay {{ten_bien}} bằng giá trị.
     *
     * BIẾN THIẾU THÀNH CHUỖI RỖNG, không để nguyên "{{ten}}". Người sửa mẫu gõ
     * nhầm một tên biến thì thư đến tay khách thiếu một mẩu chữ — xấu; còn để
     * nguyên dấu ngoặc thì khách đọc được phần ruột của hệ thống, và điều đó
     * trông như trang web hỏng.
     *
     * ESCAPE THEO CHỖ ĐẶT, KHÔNG PHẢI LÚC NÀO CŨNG ESCAPE — $html quyết định.
     *
     * RUỘT THƯ ($html = true) là HTML, và giá trị đi vào nó gồm những thứ KHÁCH
     * TỰ NHẬP: họ tên, lý do huỷ đơn, tên sản phẩm. Không escape thì một cái
     * tên chứa thẻ script là một lá thư mang mã của người khác — gửi từ địa chỉ
     * của cửa hàng. Mẫu thư (do nhân viên viết) vẫn được dùng HTML tự do; chỉ
     * giá trị thay vào mới bị escape.
     *
     * TIÊU ĐỀ ($html = false) thì KHÔNG, và bản đầu đã escape nhầm cả nó. Tiêu
     * đề là một dòng tiêu đề MIME chữ thuần, không phải HTML — Mailer::encodeHeader()
     * mã hoá nó theo RFC 2047. Escape ở đó thì một sản phẩm tên `Gọng "Sky" & Co`
     * hiện trong hộp thư của khách thành `Gọng &quot;Sky&quot; &amp; Co`.
     *
     * Đổi lại, chỗ ấy phải chặn thứ khác: XUỐNG DÒNG. Một ký tự \r hoặc \n lọt
     * vào tiêu đề là chèn được header mới — một địa chỉ Bcc chẳng hạn. Thay
     * chúng bằng dấu cách ngay khi thay biến, đừng tin nơi khác làm hộ.
     */
    private static function thay(string $mau, array $bien, bool $html = true): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            static function (array $m) use ($bien, $html): string {
                $v = (string) ($bien[$m[1]] ?? '');

                return $html
                    ? e($v)
                    : trim((string) preg_replace('/[\r\n]+/', ' ', $v));
            },
            $mau
        );
    }

    /**
     * Bọc ruột thư vào khung chung: tiêu đề cửa hàng và chân thư.
     *
     * Ở ĐÂY chứ không trong từng mẫu. Mười lăm mẫu mà mỗi mẫu mang một bản sao
     * của cùng cái khung thì đổi số điện thoại cửa hàng là sửa mười lăm chỗ —
     * và người sửa mẫu thứ chín sẽ quên mất mình đang sửa cái gì.
     *
     * CSS NỘI TUYẾN, không phải <style>: Gmail và Outlook đều cắt bỏ khối
     * <style> ở nhiều ngữ cảnh, còn thuộc tính style trên từng thẻ thì luôn
     * sống. Đây là lý do thư điện tử trông như HTML của năm 2003.
     */
    private static function dungKhung(string $ruot): string
    {
        /* 'short_name' chứ không 'name': 'name' là tên pháp nhân đầy đủ
           ("CÔNG TY TNHH VIN EYEWEAR VIỆT NAM"), đúng cho hoá đơn và chân
           trang pháp lý, nhưng đọc như một công văn khi đứng đầu một lá thư
           báo đơn hàng. 'hotline' chứ không 'phone' — config/company.php đặt
           tên khoá là hotline. */
        $ten = (string) config('company.short_name', 'Vin Eyewear');
        $sdt = (string) config('company.hotline', '');
        $web = rtrim((string) config('app.url', ''), '/');

        $chan = 'Thư này gửi tự động từ website ' . $ten . '.';

        if ($sdt !== '') {
            $chan .= ' Cần hỗ trợ, bạn gọi ' . $sdt . '.';
        }

        return '<div style="margin:0;padding:24px 12px;background:#f6f2ec;'
             . 'font-family:Helvetica,Arial,sans-serif;color:#2f2a24;">'
             . '<div style="max-width:560px;margin:0 auto;background:#fff;'
             . 'border-radius:12px;padding:28px 30px;">'
             . '<p style="margin:0 0 20px;font-size:18px;font-weight:600;'
             . 'letter-spacing:.02em;color:#8f2b3c;">' . e($ten) . '</p>'
             . '<div style="font-size:15px;line-height:1.7;">' . $ruot . '</div>'
             . '<p style="margin:26px 0 0;padding-top:16px;border-top:1px solid #ece4d9;'
             . 'font-size:12px;line-height:1.6;color:#8a7d6d;">' . e($chan)
             . ($web !== '' ? '<br><a href="' . e($web) . '" style="color:#8f2b3c;">'
                 . e($web) . '</a>' : '')
             . '</p></div></div>';
    }

    // ========================================================================
    // GỬI
    // ========================================================================

    /**
     * Đẩy hàng chờ đi — bám theo lượt truy cập, cùng lối với FR-TT-11.
     *
     * Hosting không có cron, nên "định kỳ" nghĩa là mượn một lượt duyệt trang.
     * Ba cái phanh giống hệt OrderModel::quetDonQuaHan(), và một cái thứ tư
     * riêng của việc gửi thư:
     *
     *   1. Giãn cách 2 phút, chốt bằng mtime của một TỆP trong storage/ —
     *      không phải CSDL. Hàm này chạy trước router ở mọi lượt GET, kể cả
     *      trang tĩnh; hỏi CSDL để biết "đã tới giờ chưa" là mở một kết nối ở
     *      gần như mọi lượt.
     *   2. Trần 10 thư mỗi lượt. Gửi thư là việc chậm (SMTP có thể mất vài
     *      giây mỗi lá); một lượt truy cập không được gánh hơn thế.
     *   3. Nuốt mọi ngoại lệ.
     *   4. KHÔNG chạm hàng chờ khi Mailer chưa gửi được — xem khối đầu file.
     *
     * Giãn cách ngắn hơn quét đơn quá hạn (2 phút so với 10): một lá thư xác
     * nhận đơn tới sau mười phút thì khách đã kịp lo lắng, còn một đơn quá hạn
     * huỷ chậm mười phút thì không ai để ý.
     *
     * @return int số thư đã gửi được
     */
    public static function quetGui(): int
    {
        try {
            if (!Mailer::canDeliver()) {
                return 0;
            }

            $tep = ROOT_PATH . '/storage/quet';

            if (!is_dir($tep) && !@mkdir($tep, 0770, true) && !is_dir($tep)) {
                error_log('EmailQueueModel::quetGui: không tạo được ' . $tep);

                return 0;
            }

            $tep .= '/email.txt';

            if (is_file($tep) && time() - (int) @filemtime($tep) < self::QUET_CACH_NHAU) {
                return 0;
            }

            if (@file_put_contents($tep, (string) time(), LOCK_EX) === false) {
                error_log('EmailQueueModel::quetGui: không ghi được ' . $tep);

                return 0;
            }

            if (!self::available()) {
                return 0;
            }

            /* CŨ NHẤT TRƯỚC. Thư xếp hàng trước là thư khách chờ lâu nhất, và
               với những mốc như "đã nhận đơn" thì thứ tự chính là nội dung —
               nhận thư "đã giao hàng" trước thư "đã xác nhận" thì khách đọc
               ngược cả câu chuyện. */
            /* `so_lan_thu` TRONG ĐIỀU KIỆN LỌC, không chỉ trong nhánh kết quả.

               Nhánh kết quả là nơi DUY NHẤT chuyển một lá sang 'hong', và nó
               chỉ chạy khi Mailer::send() trả về. Một lá làm chết tiến trình
               giữa chừng — SMTP treo quá max_execution_time, hoặc hết bộ nhớ
               vì ruột thư quá lớn — không bao giờ tới đó. Không có dòng này
               thì nó ở lại 'cho' mãi mãi, và vì sắp theo `gui_sau` tăng dần
               nên nó đứng ĐẦU mọi lượt quét về sau: một trong ba suất mỗi hai
               phút bị nó chiếm vĩnh viễn, chặn cả hàng chờ phía sau. */
            $thu = Database::fetchAll(
                "SELECT * FROM email_queue
                  WHERE trang_thai = 'cho'
                    AND gui_sau <= NOW()
                    AND so_lan_thu < " . self::THU_TOI_DA . "
                  ORDER BY gui_sau ASC, created_at ASC
                  LIMIT " . self::QUET_TOI_DA
            );

            $xong    = 0;
            $batDau  = time();

            foreach ($thu as $t) {
                if (self::guiMot($t)) {
                    $xong++;
                }

                /* HẾT NGÂN SÁCH THÌ DỪNG, những lá còn lại đợi lượt sau. Không
                   mất lá nào: chúng vẫn ở 'cho' và vẫn tới lượt ở lần quét kế
                   tiếp, muộn nhất hai phút sau. */
                if (time() - $batDau >= self::NGAN_SACH_GIAY) {
                    break;
                }
            }

            return $xong;
        } catch (Throwable $e) {
            error_log('EmailQueueModel::quetGui: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Gửi đúng một lá và ghi kết quả.
     *
     * GIÃN CÁCH TĂNG DẦN sau mỗi lần hỏng: 5 phút, 20, 45, 80 (lần thử thứ n
     * đợi 5×n² phút). Máy chủ thư từ chối vì quá tải thì thử lại ngay là bị từ
     * chối tiếp; đợi lâu dần cho nó thời gian hồi.
     *
     * Hết THU_TOI_DA lần thì chuyển 'hong' và đứng lại. Không thử mãi: một địa
     * chỉ gõ sai không bao giờ nhận được thư, và mỗi lần thử là một lần chậm
     * lượt truy cập của một người thật.
     */
    private static function guiMot(array $thu): bool
    {
        /* ─────────────────────────────────────────────────────────────────────
           GIÀNH LÁ THƯ TRƯỚC KHI GỬI — nếu không, hai lượt truy cập cùng lúc
           gửi hai bản cho cùng một khách.

           Phanh mtime là "đọc rồi mới ghi", không phải một cái khoá. Ngay sau
           deploy, tệp storage/quet/email.txt CHƯA TỒN TẠI, nên ba lượt GET
           đồng thời đều thấy is_file() false, đều lọt qua, đều SELECT đúng
           mấy dòng 'cho' ấy và đều gọi Mailer::send(). Khoá chống trùng ở
           `email_queue` chặn việc XẾP HÀNG trùng, không chặn việc GỬI trùng.

           Câu UPDATE dưới đây là chỗ giành: nó tăng `so_lan_thu` với điều
           kiện giá trị cũ đúng bằng giá trị vừa đọc. Chỉ MỘT tiến trình thấy
           rowCount 1; những tiến trình khác thấy 0 và bỏ qua lá này.

           Dùng chính cột `so_lan_thu` làm thẻ giành thay vì thêm một trạng
           thái 'đang gửi' thứ năm: không phải sửa lược đồ, và nó vốn đã phải
           tăng đúng một lần cho mỗi lần thử — nay việc tăng ấy diễn ra TRƯỚC
           khi thử, nên một tiến trình bị giết giữa chừng cũng đã ghi dấu lần
           thử đó thay vì để lá thư quay lại y nguyên. Câu SELECT của lượt
           quét lọc luôn `so_lan_thu < THU_TOI_DA`, nên một lá làm chết tiến
           trình bốn lần thì tự rơi ra khỏi hàng thay vì chiếm suất mãi mãi.

           ĐẨY `gui_sau` RA XA CÙNG LÚC, và đây là nửa thứ hai của cái khoá.

           Chỉ so `so_lan_thu` thì hai lượt quét ĐỒNG THỜI bị chặn, nhưng hai
           lượt LỆCH NHAU thì không: lượt A giành lá M rồi treo trong SMTP —
           một máy chủ trả lời nhỏ giọt giữ được một lá lâu hơn hẳn 12 giây,
           vì hạn 12 giây là cho mỗi lượt ĐỌC chứ không cho cả phiên. Hàng M
           vẫn mang trạng thái 'cho' suốt lúc ấy, nên lượt B tới hai phút sau
           đọc được nó, giành 1→2 và gửi bản thứ hai cho cùng một khách.

           `gui_sau` đẩy thêm 5 phút làm câu SELECT của lượt sau không nhìn
           thấy hàng ấy nữa. Cả hai nhánh kết quả bên dưới đều ghi đè lại
           `gui_sau`, nên với một lá gửi xong hay hỏng thật thì con số này
           không sống quá vài giây — nó chỉ có tác dụng đúng trong quãng đang
           gửi dở.
           ───────────────────────────────────────────────────────────────────── */
        $lan = (int) $thu['so_lan_thu'] + 1;

        $gianh = Database::execute(
            "UPDATE email_queue
                SET so_lan_thu = :moi,
                    gui_sau    = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
              WHERE id = :id AND trang_thai = 'cho' AND so_lan_thu = :cu",
            ['moi' => $lan, 'id' => $thu['id'], 'cu' => (int) $thu['so_lan_thu']]
        );

        if ($gianh === 0) {
            return false;
        }

        $ok = Mailer::send(
            (string) $thu['nguoi_nhan'],
            (string) $thu['subject'],
            (string) $thu['body']
        );

        if ($ok) {
            // `so_lan_thu` đã tăng ở bước giành, không tăng lại lần nữa.
            Database::execute(
                "UPDATE email_queue
                    SET trang_thai = 'xong', gui_luc = NOW(), loi_gan_nhat = NULL
                  WHERE id = :id",
                ['id' => $thu['id']]
            );

            return true;
        }

        $het = $lan >= self::THU_TOI_DA;

        Database::execute(
            'UPDATE email_queue
                SET trang_thai = :tt, loi_gan_nhat = :loi,
                    gui_sau = DATE_ADD(NOW(), INTERVAL :phut MINUTE)
              WHERE id = :id',
            [
                'tt'   => $het ? 'hong' : 'cho',
                'loi'  => utf8Substr((string) (Mailer::lastError() ?? 'Không rõ lý do'), 0, 500),
                'phut' => 5 * $lan * $lan,
                'id'   => $thu['id'],
            ]
        );

        return false;
    }

    // ========================================================================
    // MÀN QUẢN TRỊ — FR-EM-05
    // ========================================================================

    /** Bao nhiêu lá thư trên một trang của màn quản trị. */
    public const MOI_TRANG = 50;

    /**
     * Một trang của sổ thư, lọc theo trạng thái.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * PHẢI PHÂN TRANG, KHÔNG ĐƯỢC CẮT CỨNG
     *
     * Bản đầu trả về 100 dòng gần nhất và thôi. Trên hosting hiện tại thì đó
     * là một cái bẫy chắc chắn sập: không lá nào rời được trạng thái 'cho'
     * (xem khối đầu file), nên khi hàng chờ vượt 100 dòng thì toàn bộ lịch sử
     * 'xong' và 'bo' biến mất khỏi màn hình — vĩnh viễn, vì thứ tự sắp xếp đẩy
     * 'cho' lên trước. FR-EM-05 đòi một cuốn sổ ĐỌC ĐƯỢC; một cuốn sổ chỉ cho
     * xem trang đầu thì không phải sổ.
     *
     * Dải viên lọc lại in ĐÚNG tổng số thật, nên bản cắt cứng còn nói dối:
     * "Tất cả 4.812" ngồi trên một bảng 100 dòng, không một chữ nào nói rằng
     * nó đã bị cắt.
     *
     * @return array{rows: list<array>, total: int, totalPages: int, page: int}
     */
    public static function danhSach(string $trangThai = '', int $trang = 1): array
    {
        $trong = ['rows' => [], 'total' => 0, 'totalPages' => 1, 'page' => 1];

        if (!self::available()) {
            return $trong;
        }

        $where  = '';
        $params = [];

        if ($trangThai !== '' && isset(self::TRANG_THAI[$trangThai])) {
            $where        = " WHERE trang_thai = :tt";
            $params['tt'] = $trangThai;
        }

        $total = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM email_queue' . $where,
            $params
        );

        $soTrang = max(1, (int) ceil($total / self::MOI_TRANG));
        $trang   = min(max(1, $trang), $soTrang);

        /* CHỜ VÀ HỎNG LÊN ĐẦU, rồi mới tới thời gian. Đây là hàng chờ có người
           đang đợi ở đầu bên kia — thứ tự thời gian thuần sẽ đẩy một lá thư
           hỏng từ tuần trước xuống dưới đống thư đã gửi hôm nay.

           OFFSET/LIMIT ghép thẳng vào chuỗi vì cả hai đã qua (int) và các phép
           kẹp ở trên — MySQL không nhận tham số buộc ở hai vị trí này khi
           ATTR_EMULATE_PREPARES tắt. */
        $rows = Database::fetchAll(
            "SELECT * FROM email_queue" . $where
            . " ORDER BY FIELD(trang_thai, 'hong', 'cho', 'xong', 'bo'),
                        created_at DESC
               LIMIT " . self::MOI_TRANG . ' OFFSET ' . (($trang - 1) * self::MOI_TRANG),
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'totalPages' => $soTrang, 'page' => $trang];
    }

    /** Số thư theo từng trạng thái — cho dải lọc và huy hiệu thanh bên. */
    public static function demTheoTrangThai(): array
    {
        $dem = ['' => 0];

        foreach (array_keys(self::TRANG_THAI) as $k) {
            $dem[$k] = 0;
        }

        if (!self::available()) {
            return $dem;
        }

        foreach (Database::fetchAll(
            'SELECT trang_thai, COUNT(*) AS n FROM email_queue GROUP BY trang_thai'
        ) as $d) {
            if (isset($dem[$d['trang_thai']])) {
                $dem[$d['trang_thai']] = (int) $d['n'];
            }

            $dem[''] += (int) $d['n'];
        }

        return $dem;
    }

    /** Một lá thư theo id. */
    public static function chiTiet(string $id): ?array
    {
        if (!self::available()) {
            return null;
        }

        return Database::fetchOne('SELECT * FROM email_queue WHERE id = :id', ['id' => $id]);
    }

    /**
     * Đưa một lá thư trở lại hàng chờ — nút "Gửi lại" của FR-EM-05.
     *
     * ĐẶT LẠI `so_lan_thu` VỀ 0. Người bấm nút này vừa sửa một thứ ở bên ngoài
     * — cấu hình SMTP, địa chỉ nhận, hạn mức nhà cung cấp — nên bộ đếm cũ nói
     * về một thế giới không còn nữa. Giữ nguyên nó thì một lá đã hỏng bốn lần
     * chỉ được thử thêm 0 lần rồi lại về 'hong' ngay.
     *
     * `gui_sau` về NOW() để nó đi ở lượt quét kế tiếp chứ không phải sau 80 phút.
     */
    public static function guiLai(string $id): array
    {
        $thu = self::chiTiet($id);

        if ($thu === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy lá thư này.'];
        }

        if ($thu['trang_thai'] === 'xong') {
            return ['ok' => false, 'error' =>
                'Thư này đã gửi thành công rồi. Muốn gửi lại thì sự kiện phải xảy ra lần nữa.'];
        }

        /* ĐẾM DÒNG ĐÃ SỬA, và điều kiện phải nhắc lại trạng thái.

           Giữa lúc chiTiet() đọc và lúc câu này chạy, một lượt quét ở tiến
           trình khác có thể vừa gửi xong lá thư. Không kiểm thì người bấm thấy
           "đã đưa về hàng chờ" và một dòng nhật ký được ghi — cho một lá đã
           tới tay khách. Tệ hơn: nó bị kéo ngược về 'cho' và gửi lần thứ hai. */
        $n = Database::execute(
            "UPDATE email_queue
                SET trang_thai = 'cho', so_lan_thu = 0, loi_gan_nhat = NULL,
                    gui_sau = NOW()
              WHERE id = :id AND trang_thai <> 'xong'",
            ['id' => $id]
        );

        if ($n === 0) {
            return ['ok' => false, 'error' =>
                'Lá thư này vừa được gửi đi. Tải lại trang để xem trạng thái mới.'];
        }

        return ['ok' => true];
    }

    /** Bỏ hẳn một lá thư — không gửi nữa, nhưng vẫn nằm trong sổ. */
    public static function bo(string $id): array
    {
        $thu = self::chiTiet($id);

        if ($thu === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy lá thư này.'];
        }

        if ($thu['trang_thai'] === 'xong') {
            return ['ok' => false, 'error' => 'Thư đã gửi rồi thì không bỏ được.'];
        }

        // Cùng lý do như guiLai(): lá thư có thể vừa được gửi xong.
        $n = Database::execute(
            "UPDATE email_queue SET trang_thai = 'bo' WHERE id = :id AND trang_thai <> 'xong'",
            ['id' => $id]
        );

        if ($n === 0) {
            return ['ok' => false, 'error' =>
                'Lá thư này vừa được gửi đi, không bỏ được nữa. Tải lại trang.'];
        }

        return ['ok' => true];
    }
}
