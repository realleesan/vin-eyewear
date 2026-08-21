<?php

/**
 * core/Zalo.php — đẩy thông báo lịch hẹn qua Zalo.
 *
 * Cửa hàng yêu cầu: khách đặt lịch xong là thông tin phải tự chạy vào Zalo của
 * cửa hàng (và của khách nếu được), để nhân viên không phải ngồi canh trang
 * quản trị mới biết có lịch mới.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ⚠️  CHƯA CẮM NHÀ CUNG CẤP — TIN CHƯA ĐI TỚI ZALO ĐƯỢC
 *
 * Zalo KHÔNG có API kiểu "gửi tin tới một số điện thoại" dùng ngay được. Muốn
 * gửi thật thì phải có đủ ba thứ, và cả ba đều là việc đăng ký với Zalo chứ
 * không phải việc viết mã:
 *
 *   1. Một Official Account (OA) của cửa hàng, đã xác thực doanh nghiệp.
 *   2. Một mẫu tin ZNS được Zalo DUYỆT (nội dung cố định, chỉ điền tham số).
 *      Tin tự do không gửi được cho người chưa quan tâm OA.
 *   3. access_token của OA — token này hết hạn theo giờ và phải làm mới bằng
 *      refresh_token, nên phải có chỗ cất token mới, không chỉ cất trong .env.
 *
 * Chưa cấu hình thì send() GHI RA ERROR LOG rồi trả false — giống hệt cách
 * core/Otp.php đang làm với mã OTP. Nghĩa là ngay lúc này lịch hẹn vẫn đặt
 * được bình thường và nhân viên vẫn phải xem /quan-tri/lich-hen; chỉ có phần
 * "tự chạy vào Zalo" là chưa chạy.
 *
 * CHỖ CẮM LÀ ĐÚNG MỘT HÀM: send(). Nối ZNS vào đó, trả true khi Zalo nhận —
 * không phần nào khác phải sửa. Việc soạn nội dung, chọn người nhận, chuẩn hoá
 * số điện thoại và nuốt lỗi đều đã nằm ở đây và chạy thật.
 * ─────────────────────────────────────────────────────────────────────────────
 * GỬI THÔNG BÁO KHÔNG BAO GIỜ ĐƯỢC LÀM HỎNG VIỆC ĐẶT LỊCH
 *
 * Đây là việc phụ chạy kèm một việc chính đã xong: lịch đã nằm trong CSDL rồi.
 * Zalo sập, token hết hạn, mạng ra ngoài bị chặn — không lý do nào trong số đó
 * được phép biến thành một trang lỗi cho người vừa đặt lịch. Nên mọi lối ra của
 * notify() đều bị bọc try/catch và mọi hỏng hóc chỉ đi vào error log.
 *
 * Cũng vì thế mọi lượt gọi ra ngoài phải có HẠN GIỜ NGẮN. Không có hạn thì một
 * đầu bên kia treo sẽ giữ luôn request của khách, và họ ngồi nhìn trang trắng
 * sau khi đã bấm "Đặt lịch".
 */
class Zalo
{
    /**
     * ĐÃ CẮM ZNS CHƯA? Đổi thành true trong chính lần sửa mà bạn nối API vào
     * send() bên dưới.
     *
     * Có hằng này vì nơi gọi cần biết SỰ THẬT để còn nói đúng với khách và ghi
     * đúng vào log. Trả bừa true thì mọi thứ trông như đã gửi, trong khi không
     * ai nhận được gì và cũng không còn ai biết mà gọi lại.
     */
    public const PROVIDER_READY = false;

    /** Hạn giờ cho một lượt gọi ra ngoài (giây) — xem khối chú thích đầu file. */
    private const TIMEOUT         = 5;
    private const CONNECT_TIMEOUT = 3;

    // ========================================================================
    // ĐIỂM VÀO
    // ========================================================================

    /**
     * Đẩy một lịch hẹn vừa phát sinh sang Zalo.
     *
     * $event là việc vừa xảy ra, dùng để đổi câu mở đầu:
     *   'created'     khách vừa đặt
     *   'rescheduled' khách vừa đổi ngày/giờ
     *   'cancelled'   khách vừa huỷ
     *
     * ĐỔI VÀ HUỶ CŨNG BÁO, không chỉ lúc đặt. Cửa hàng yêu cầu tính năng này
     * để "nhân viên không phải liên tục túc trực kiểm tra trên web" — mà nếu
     * chỉ báo lúc đặt thì một lịch đã đổi giờ hoặc đã huỷ vẫn nằm im trong Zalo
     * với thông tin cũ, và nhân viên tin vào nó. Báo thiếu còn tệ hơn không báo.
     *
     * @param array $appointment dòng `appointments` (kèm store_name nếu có)
     */
    public static function appointment(array $appointment, string $event = 'created'): void
    {
        try {
            $message = self::compose($appointment, $event);

            // 1. CỬA HÀNG — luôn gửi. Đây mới là mục đích chính của tính năng.
            $shop = self::shopPhone();

            if ($shop !== null) {
                self::send($shop, $message, 'shop');
            }

            /* 2. KHÁCH — "nếu được", đúng chữ trong yêu cầu. Thực tế ZNS gửi
                  cho khách tốn phí theo tin và cần mẫu riêng đã duyệt, nên nó
                  là một công tắc bật/tắt được chứ không mặc định bật. */
            $customer = self::normalize((string) ($appointment['phone'] ?? ''));

            if ($customer !== null && config('zalo.notify_customer', false)) {
                self::send($customer, $message, 'customer');
            }
        } catch (Throwable $e) {
            // Nuốt MỌI thứ. Lịch đã nằm trong CSDL — xem khối chú thích đầu file.
            error_log('[Zalo] Không đẩy được thông báo lịch hẹn: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // NỘI DUNG
    // ========================================================================

    /**
     * Soạn nội dung tin.
     *
     * Một chuỗi nhiều dòng, không phải mảng tham số của mẫu ZNS. Lý do: mẫu ZNS
     * chưa tồn tại (chưa có OA để mà đăng ký), nên chưa biết nó có bao nhiêu ô
     * và tên ô là gì. Chuỗi này là thứ ghi ra log ngay lúc này để nhân viên vẫn
     * đọc được, và khi có mẫu thật thì send() là nơi tách nó ra thành tham số —
     * hoặc dựng thẳng mảng tham số từ $appointment, tuỳ mẫu được duyệt ra sao.
     */
    private static function compose(array $appointment, string $event): string
    {
        $head = [
            'created'     => 'LỊCH HẸN MỚI',
            'rescheduled' => 'KHÁCH ĐỔI GIỜ HẸN',
            'cancelled'   => 'KHÁCH HUỶ LỊCH',
        ][$event] ?? 'LỊCH HẸN';

        $date = (string) ($appointment['appointment_date'] ?? '');
        $when = $date === '' ? '—' : formatDate($date);

        $lines = [
            $head . ' · ' . (string) ($appointment['code'] ?? ''),
            'Khách:    ' . (string) ($appointment['full_name'] ?? ''),
            'Điện thoại: ' . (string) ($appointment['phone'] ?? ''),
            'Cơ sở:    ' . (string) ($appointment['store_name'] ?? '—'),
            'Dịch vụ:  ' . (string) ($appointment['service_type'] ?? '—'),
            'Thời gian: ' . (string) ($appointment['time_slot'] ?? '—') . ' ngày ' . $when,
        ];

        $note = trim((string) ($appointment['note'] ?? ''));

        if ($note !== '') {
            $lines[] = 'Ghi chú:  ' . $note;
        }

        /* Câu cuối nhắc việc phải làm. Cửa hàng bỏ giới hạn khung giờ chính vì
           họ sẽ gọi xác nhận và tự xếp người — nên tin báo phải nói ra việc đó,
           không thì nó chỉ là một mẩu thông tin không ai biết để làm gì. */
        if ($event !== 'cancelled') {
            $lines[] = 'Vui lòng gọi khách để xác nhận lịch.';
        }

        return implode("\n", $lines);
    }

    // ========================================================================
    // GỬI
    // ========================================================================

    /**
     * CHỖ CẮM NHÀ CUNG CẤP.
     *
     * Nối Zalo ZNS vào đây và trả true khi Zalo nhận. Khung gọi HTTP đã có sẵn
     * ở post() bên dưới — đã bọc hạn giờ, đã kiểm chứng chỉ TLS, đã ghi log khi
     * hỏng. Việc còn lại là ba dòng: dựng phần thân theo mẫu đã duyệt, gọi
     * post(), đọc `error` trong kết quả (ZNS trả 0 khi thành công).
     *
     * $to đã chuẩn hoá về dạng 0xxxxxxxxx; ZNS đòi dạng 84xxxxxxxxx nên đổi ở
     * chính chỗ cắm — xem toZns().
     *
     * @param string $who 'shop' | 'customer' — chỉ để ghi log cho dễ đọc
     */
    private static function send(string $to, string $message, string $who): bool
    {
        $token = (string) config('zalo.access_token', '');

        if (!self::PROVIDER_READY || $token === '') {
            error_log(sprintf(
                "[Zalo] Chưa cắm nhà cung cấp — tin gửi %s (%s) chỉ nằm ở log:\n%s",
                $who,
                $to,
                $message
            ));

            return false;
        }

        /* Tới đây nghĩa là đã có OA và token. Phần dựng thân tin theo mẫu ZNS
           phải viết cùng lúc với việc đăng ký mẫu, vì tên tham số do mẫu quy
           định. Để trống có chủ ý — đừng đoán tên tham số, một mẫu sai thì Zalo
           từ chối cả tin. */
        error_log('[Zalo] PROVIDER_READY đang bật nhưng send() chưa dựng thân tin ZNS.');

        return false;
    }

    /**
     * Số điện thoại dạng ZNS: 84xxxxxxxxx, không dấu cộng.
     *
     * Chưa dùng tới cho tới khi cắm nhà cung cấp, nhưng để sẵn ở đây vì đó là
     * thứ dễ làm sai nhất khi nối API: gửi "0366599711" thì Zalo nhận request
     * rồi lặng lẽ không giao tin.
     */
    private static function toZns(string $phone): string
    {
        return '84' . ltrim($phone, '0');
    }

    // ========================================================================
    // NGƯỜI NHẬN
    // ========================================================================

    /**
     * Số Zalo của cửa hàng, hoặc null nếu chưa khai / khai sai.
     *
     * Đọc từ config chứ không gõ cứng: đổi số là việc của cửa hàng, và số này
     * còn hiện ở thanh liên hệ nổi nên phải có đúng một nguồn.
     */
    private static function shopPhone(): ?string
    {
        $phone = self::normalize((string) config('zalo.shop_phone', ''));

        if ($phone === null) {
            error_log('[Zalo] Chưa khai số Zalo của cửa hàng — xem config/company.php.');
        }

        return $phone;
    }

    /**
     * Chuẩn hoá số về dạng 0xxxxxxxxx, hoặc null nếu không đọc được.
     *
     * Đi qua normalizePhone() của core/helpers.php — cùng hàm mà lúc đăng ký
     * tài khoản dùng, nên "0366 599 711", "+84366599711" và "84366599711" đều
     * ra một kết quả. Số trong `appointments.phone` là chữ khách tự gõ ở form
     * đặt lịch, mà form đó chỉ đòi đủ 8 chữ số — chưa chuẩn hoá bao giờ.
     */
    private static function normalize(string $raw): ?string
    {
        $raw = trim($raw);

        return $raw === '' ? null : normalizePhone($raw);
    }

    // ========================================================================
    // GỌI HTTP
    // ========================================================================

    /**
     * POST JSON, trả mảng đã giải mã hoặc null.
     *
     * Cùng khuôn với core/GoogleAuth.php: đi qua cURL nếu có, không thì dùng
     * luồng https:// của PHP. Bản chỉ-cURL sẽ chết bằng fatal error trên bản
     * PHP không bật extension đó, mà một thông báo phụ thì càng không được phép
     * làm sập trang.
     *
     * Cả hai đường đều KIỂM chứng chỉ TLS và đều có hạn giờ ngắn.
     *
     * Chưa có ai gọi tới cho tới khi cắm nhà cung cấp — nó nằm sẵn đây để lần
     * sửa ấy chỉ phải viết phần thân tin, không phải viết lại phần mạng.
     */
    private static function post(string $url, array $headers, string $body): ?array
    {
        $raw = extension_loaded('curl')
            ? self::viaCurl($url, $headers, $body)
            : self::viaStream($url, $headers, $body);

        if ($raw === null) {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    private static function viaCurl(string $url, array $headers, string $body): ?string
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            // Mặc định của cURL, viết rõ ra để người sửa sau thấy chúng tồn tại.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            error_log('[Zalo] cURL gọi ' . $url . ' lỗi: ' . $err);

            return null;
        }

        return (string) $raw;
    }

    private static function viaStream(string $url, array $headers, string $body): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => self::TIMEOUT,
                // Đừng ném warning ra màn hình khi Zalo trả 4xx/5xx — đọc thân
                // phản hồi để còn ghi log cho có ích.
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $raw = @file_get_contents($url, false, $context);

        if ($raw === false) {
            error_log('[Zalo] Không gọi được ' . $url . ' (file_get_contents).');

            return null;
        }

        return $raw;
    }
}
