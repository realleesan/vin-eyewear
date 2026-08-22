<?php

/**
 * core/Zalo.php — đường ra Zalo của dự án.
 *
 * Ba việc đi chung một đường vì cùng dùng ZNS, cùng một access_token và cùng
 * một chỗ gọi HTTP:
 *
 *   1. THÔNG BÁO LỊCH HẸN cho cửa hàng — appointment().
 *   2. THÔNG BÁO ĐƠN HÀNG MỚI cho cửa hàng — order().
 *   3. MÃ OTP khi khách đăng ký / quên mật khẩu — sendOtp(), do core/Otp.php gọi.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA THỨ PHẢI CÓ TRƯỚC KHI TIN ĐI ĐƯỢC — VÀ CẢ BA LÀ VIỆC ĐĂNG KÝ, KHÔNG PHẢI
 * VIỆC VIẾT MÃ
 *
 *   1. Một Official Account (OA) của cửa hàng, đã xác thực doanh nghiệp.
 *   2. Mẫu tin ZNS được Zalo DUYỆT. Mỗi loại tin một mẫu riêng: mẫu OTP khác
 *      mẫu báo lịch hẹn. Zalo có sẵn mẫu OTP mì ăn liền, duyệt nhanh nhất
 *      trong các loại — khai mã mẫu vào ZALO_ZNS_TEMPLATE_OTP.
 *   3. app_id + secret_key của ứng dụng, và refresh_token của OA lấy một lần
 *      qua màn hình cấp quyền. access_token chỉ sống ~25 giờ nên KHÔNG khai
 *      thẳng vào .env được — xem khối "TOKEN" bên dưới.
 *
 * Thiếu bất kỳ thứ nào thì mọi lối gửi ở đây GHI RA ERROR LOG rồi trả false.
 * Nơi gọi dựa vào giá trị trả về đó: lịch hẹn vẫn đặt bình thường (nhân viên
 * xem /quan-tri/lich-hen), còn yêu cầu quên mật khẩu rơi về hàng chờ ở
 * /quan-tri/quen-mat-khau để nhân viên gọi điện.
 *
 * ⚠️ RIÊNG LUỒNG ĐĂNG KÝ thì không có đường vòng nào: chưa cấu hình xong thì
 * khách thật KHÔNG tự đăng ký được, vì họ không có cách nào biết mã. Ở máy
 * phát triển (app.debug) mã hiện thẳng trên màn hình.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * GỬI THÔNG BÁO KHÔNG BAO GIỜ ĐƯỢC LÀM HỎNG VIỆC ĐẶT LỊCH HAY ĐẶT HÀNG
 *
 * Đây là việc phụ chạy kèm một việc chính đã xong: lịch (hoặc đơn) đã nằm
 * trong CSDL rồi. Zalo sập, token hết hạn, mạng ra ngoài bị chặn — không lý do
 * nào trong số đó được phép biến thành một trang lỗi cho người vừa bấm đặt.
 * Nên mọi lối ra của appointment() và order() đều bị bọc try/catch và mọi hỏng
 * hóc chỉ đi vào error log.
 *
 * Cũng vì thế mọi lượt gọi ra ngoài phải có HẠN GIỜ NGẮN. Không có hạn thì một
 * đầu bên kia treo sẽ giữ luôn request của khách, và họ ngồi nhìn trang trắng
 * sau khi đã bấm "Đặt hàng".
 */
class Zalo
{
    /** Hạn giờ cho một lượt gọi ra ngoài (giây) — xem khối chú thích đầu file. */
    private const TIMEOUT         = 5;
    private const CONNECT_TIMEOUT = 3;

    /** Gửi một tin theo mẫu đã duyệt. */
    private const ZNS_URL = 'https://business.openapi.zalo.me/message/template';

    /** Đổi refresh_token lấy access_token mới. */
    private const TOKEN_URL = 'https://oauth.zaloapp.com/v4/oa/access_token';

    /**
     * Làm mới token sớm hơn hạn ngần này giây.
     *
     * Không có khoảng đệm thì một token còn đúng hai giây vẫn được coi là dùng
     * được, và nó hết hạn giữa đường đi của request — tin rơi mất mà log chỉ
     * nói "token không hợp lệ", rất khó lần.
     */
    private const TOKEN_SKEW = 300;

    /**
     * Mã lỗi Zalo trả khi access_token hỏng/hết hạn. Gặp thì làm mới rồi gửi
     * lại ĐÚNG MỘT LẦN — token vừa hết hạn là chuyện thường ngày, không phải
     * lỗi cấu hình, mà bắt khách bấm lại vì nó thì vô lý.
     */
    private const TOKEN_ERRORS = [-124, -216];

    // ========================================================================
    // ĐIỂM VÀO — LỊCH HẸN
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
                self::notify($shop, $appointment, $event, $message,
                             (string) config('zalo.template_shop', ''), 'shop');
            }

            /* 2. KHÁCH — "nếu được", đúng chữ trong yêu cầu. Thực tế ZNS gửi
                  cho khách tốn phí theo tin và cần mẫu riêng đã duyệt, nên nó
                  là một công tắc bật/tắt được chứ không mặc định bật. */
            $customer = self::normalize((string) ($appointment['phone'] ?? ''));

            if ($customer !== null && config('zalo.notify_customer', false)) {
                self::notify($customer, $appointment, $event, $message,
                             (string) config('zalo.template_customer', ''), 'customer');
            }
        } catch (Throwable $e) {
            // Nuốt MỌI thứ. Lịch đã nằm trong CSDL — xem khối chú thích đầu file.
            error_log('[Zalo] Không đẩy được thông báo lịch hẹn: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // ĐIỂM VÀO — ĐƠN HÀNG
    // ========================================================================

    /**
     * Đẩy một đơn hàng vừa đặt sang Zalo của cửa hàng.
     *
     * ─────────────────────────────────────────────────────────────────────
     * ĐÂY LÀ MỘT NỬA CỦA LUỒNG HUỶ ĐƠN, KHÔNG PHẢI MỘT TIỆN ÍCH BÁO TIN
     *
     * Website CỐ Ý không có nút "huỷ đơn": cửa hàng tự đi giao và không đồng
     * bộ trạng thái vận chuyển thời gian thực với đơn vị vận chuyển nào, nên
     * một nút huỷ trên web sẽ đổi trạng thái trong CSDL trong khi hàng có thể
     * đã nằm trên xe — hai bên hiểu khác nhau về cùng một đơn.
     *
     * Thay vào đó, đơn chạy về Zalo của cửa hàng, nhân viên gọi khách xác
     * nhận, và khách muốn huỷ thì nhắn lại chính cuộc trò chuyện đó. Cái đẩy
     * tin này CHÍNH LÀ thứ làm cho việc bỏ nút huỷ trở nên chấp nhận được:
     * không có nó thì đơn nằm im trong /quan-tri/don-hang chờ ai đó nhớ mở ra,
     * và khách nhắn Zalo huỷ một đơn mà bên kia còn chưa biết là có.
     * ─────────────────────────────────────────────────────────────────────
     *
     * CHỈ GỬI CHO CỬA HÀNG, không gửi cho khách. Khác lịch hẹn ở chỗ đó:
     * khách vừa rời trang xác nhận đơn có đủ mọi thứ họ cần (mã đơn, số tiền,
     * chỗ nhận hàng) và sắp nhận một cuộc gọi từ nhân viên — thêm một tin ZNS
     * tốn phí chỉ để nhắc lại là thừa.
     *
     * @param array $order dòng `orders` (kèm store_name nếu có)
     * @param array $items dòng hàng, để tin báo nói được khách mua gì
     */
    public static function order(array $order, array $items = []): void
    {
        try {
            $shop = self::shopPhone();

            if ($shop === null) {
                return;
            }

            $template = (string) config('zalo.template_order', '');

            if ($template === '' || !self::hasCredentials()) {
                /* Chưa khai mẫu thì tin vẫn phải ĐI ĐÂU ĐÓ. Cùng lý lẽ với
                   notify() của lịch hẹn: nội dung dạng chữ nằm trong error log
                   vẫn cứu được một đơn bị bỏ quên, còn im lặng thì không. */
                error_log(sprintf(
                    "[Zalo] Chưa khai mẫu tin đơn hàng — tin chỉ nằm ở log:\n%s",
                    self::composeOrder($order, $items)
                ));

                return;
            }

            self::sendZns($shop, $template, self::orderParams($order, $items), 'shop');
        } catch (Throwable $e) {
            // Nuốt MỌI thứ. Đơn đã nằm trong CSDL — xem khối chú thích đầu file.
            error_log('[Zalo] Không đẩy được thông báo đơn hàng: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // ĐIỂM VÀO — OTP
    // ========================================================================

    /**
     * Gửi mã xác minh 6 số qua Zalo.
     *
     * Gọi từ Otp::send(). Trả về "Zalo đã nhận tin chưa" — nơi gọi cần SỰ THẬT
     * để còn nói đúng với khách; xem khối chú thích đầu core/Otp.php.
     *
     * @param string $phone số đã chuẩn hoá dạng 0xxxxxxxxx
     */
    public static function sendOtp(string $phone, string $code): bool
    {
        try {
            $to = self::normalize($phone);

            if ($to === null) {
                error_log('[Zalo] Số nhận OTP không đọc được: ' . $phone);

                return false;
            }

            /* Tên tham số do MẪU ĐÃ DUYỆT quy định, không phải do mã nguồn.
               Mẫu OTP dựng sẵn của Zalo dùng tên `otp`; mẫu tự soạn có thể đặt
               tên khác, nên nó nằm trong config chứ không gõ cứng ở đây. Sai
               tên thì Zalo từ chối cả tin chứ không bỏ qua một ô. */
            $param = (string) config('zalo.otp_param', 'otp');

            return self::sendZns(
                $to,
                (string) config('zalo.template_otp', ''),
                [$param => $code],
                'otp'
            );
        } catch (Throwable $e) {
            // Cùng lý do với appointment(): một thông báo hỏng không được phép
            // biến màn hình đăng ký thành trang lỗi.
            error_log('[Zalo] Không gửi được OTP: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * ĐÃ ĐỦ ĐIỀU KIỆN GỬI OTP CHƯA?
     *
     * Trang /quan-tri/quen-mat-khau đọc giá trị này để nói với nhân viên rằng
     * yêu cầu bằng số điện thoại đang tự chạy hay đang chờ họ gọi điện.
     *
     * Đây là câu trả lời theo CẤU HÌNH, không phải theo một hằng bật tay: khai
     * đủ mẫu tin và token là bật, xoá đi là tắt. Một công tắc riêng thì sớm
     * muộn cũng lệch với cấu hình thật.
     */
    public static function otpReady(): bool
    {
        return (string) config('zalo.template_otp', '') !== '' && self::hasCredentials();
    }

    // ========================================================================
    // NỘI DUNG LỊCH HẸN
    // ========================================================================

    /**
     * Soạn nội dung tin lịch hẹn dạng chữ.
     *
     * Vẫn giữ dù đã cắm ZNS: khi chưa khai mẫu tin lịch hẹn (mẫu này phải tự
     * soạn và chờ Zalo duyệt, lâu hơn mẫu OTP dựng sẵn) thì đây là thứ ghi ra
     * error log để nhân viên vẫn đọc được, và nó cũng là bản mô tả những ô mà
     * mẫu cần có khi đi đăng ký.
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

    /**
     * Gửi một tin lịch hẹn, hoặc ghi ra log nếu chưa khai mẫu.
     *
     * Tách khỏi sendZns() vì tin lịch hẹn có đường lui mà OTP không có: nội
     * dung dạng chữ vẫn hữu ích khi nằm trong log, còn một mã OTP nằm trong
     * log thì chẳng tới tay ai.
     */
    private static function notify(
        string $to,
        array $appointment,
        string $event,
        string $message,
        string $template,
        string $who
    ): bool {
        if ($template === '' || !self::hasCredentials()) {
            error_log(sprintf(
                "[Zalo] Chưa khai mẫu tin lịch hẹn (%s) — tin chỉ nằm ở log:\n%s",
                $who,
                $message
            ));

            return false;
        }

        return self::sendZns($to, $template, self::appointmentParams($appointment, $event), $who);
    }

    /**
     * Các ô của mẫu tin lịch hẹn.
     *
     * TÊN Ô PHẢI KHỚP MẪU ĐÃ DUYỆT. Mẫu lịch hẹn là mẫu tự soạn nên tên ô do
     * chính người đi đăng ký đặt — đặt đúng bảy tên dưới đây khi soạn mẫu thì
     * không phải sửa dòng nào ở đây. Đặt khác thì sửa ở đúng chỗ này.
     *
     * Giá trị đều là chuỗi: ZNS từ chối tin nếu một ô là số hay null.
     */
    private static function appointmentParams(array $appointment, string $event): array
    {
        $date = (string) ($appointment['appointment_date'] ?? '');

        return [
            'su_kien'    => [
                'created'     => 'Lịch hẹn mới',
                'rescheduled' => 'Khách đổi giờ hẹn',
                'cancelled'   => 'Khách huỷ lịch',
            ][$event] ?? 'Lịch hẹn',
            'ma_lich'    => (string) ($appointment['code'] ?? '—'),
            'khach_hang' => (string) ($appointment['full_name'] ?? '—'),
            'dien_thoai' => (string) ($appointment['phone'] ?? '—'),
            'co_so'      => (string) ($appointment['store_name'] ?? '—'),
            'dich_vu'    => (string) ($appointment['service_type'] ?? '—'),
            'thoi_gian'  => (string) ($appointment['time_slot'] ?? '—')
                            . ($date === '' ? '' : ' ngày ' . formatDate($date)),
        ];
    }

    /**
     * Tin báo đơn hàng dạng CHỮ — dùng khi chưa khai mẫu ZNS, và là bản mẫu
     * để soạn mẫu tin trên zns.zalo.me.
     */
    private static function composeOrder(array $order, array $items): string
    {
        $lines = [
            'ĐƠN HÀNG MỚI · ' . (string) ($order['code'] ?? ''),
            'Khách:     ' . (string) ($order['customer_name'] ?? '—'),
            'Điện thoại: ' . (string) ($order['customer_phone'] ?? '—'),
            'Nhận hàng: ' . self::orderDelivery($order),
            'Thanh toán: ' . self::orderPayment($order),
            'Tổng tiền: ' . money((int) ($order['total'] ?? 0)),
            'Sản phẩm:  ' . self::orderItems($items),
        ];

        $note = trim((string) ($order['note'] ?? ''));

        if ($note !== '') {
            $lines[] = 'Ghi chú:   ' . $note;
        }

        /* Câu cuối nhắc việc phải làm — và đây không phải câu xã giao. Khách
           KHÔNG có nút huỷ trên web (xem order()), nên cuộc gọi xác nhận này
           là lần đầu tiên và có thể là lần duy nhất họ nói được là muốn đổi
           hay bỏ đơn. */
        $lines[] = 'Vui lòng gọi khách để xác nhận đơn.';

        return implode("\n", $lines);
    }

    /**
     * Các ô của mẫu tin đơn hàng.
     *
     * TÊN Ô PHẢI KHỚP MẪU ĐÃ DUYỆT — cùng ràng buộc như appointmentParams().
     * Giá trị đều là chuỗi và đều CẮT NGẮN: ZNS giới hạn độ dài từng ô, và một
     * đơn năm món hay một địa chỉ dài sẽ vượt quá, khiến Zalo từ chối cả tin.
     * Thà mất mấy chữ cuối còn hơn mất cả tin báo.
     */
    private static function orderParams(array $order, array $items): array
    {
        return [
            'ma_don'     => (string) ($order['code'] ?? '—'),
            'khach_hang' => self::clip((string) ($order['customer_name'] ?? '—')),
            'dien_thoai' => (string) ($order['customer_phone'] ?? '—'),
            'nhan_hang'  => self::clip(self::orderDelivery($order)),
            'thanh_toan' => self::orderPayment($order),
            'tong_tien'  => money((int) ($order['total'] ?? 0)),
            'san_pham'   => self::clip(self::orderItems($items)),
        ];
    }

    /**
     * "Giao tận nơi · <địa chỉ>" hoặc "Nhận tại <tên cơ sở>".
     *
     * Nhân viên gọi khách cần biết NGAY là đơn này phải mang đi đâu — đó là
     * thứ quyết định họ xếp đơn vào chuyến giao nào, hay chỉ để hàng ở quầy.
     */
    private static function orderDelivery(array $order): string
    {
        if ((string) ($order['delivery_method'] ?? '') === 'pickup') {
            return 'Nhận tại ' . (string) ($order['store_name'] ?? 'cửa hàng');
        }

        $address = trim((string) ($order['shipping_address'] ?? ''));

        return 'Giao tận nơi' . ($address === '' ? '' : ' · ' . $address);
    }

    /** "COD" hoặc "Chuyển khoản (đã nhận tiền / chưa nhận tiền)". */
    private static function orderPayment(array $order): string
    {
        if ((string) ($order['payment_method'] ?? '') !== 'bank_transfer') {
            return 'COD — thu khi giao';
        }

        return 'Chuyển khoản — '
             . ((string) ($order['payment_status'] ?? '') === 'paid'
                ? 'đã nhận tiền' : 'CHƯA nhận tiền');
    }

    /** "Gọng Aviator ×1, Tròng 1.61 ×1" — rỗng thì trả một gạch. */
    private static function orderItems(array $items): string
    {
        $parts = [];

        foreach ($items as $item) {
            $name = trim((string) ($item['product_name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $parts[] = $name . ' ×' . (int) ($item['quantity'] ?? 1);
        }

        return $parts === [] ? '—' : implode(', ', $parts);
    }

    /**
     * Cắt một ô của mẫu ZNS về độ dài an toàn.
     *
     * 100 ký tự là giới hạn Zalo áp cho phần lớn kiểu ô. Cắt theo KÝ TỰ chứ
     * không phải byte (utf8Substr, không phải substr): cắt giữa chừng một chữ
     * tiếng Việt nhiều byte sẽ sinh ký tự hỏng, và Zalo từ chối tin có ký tự
     * không hợp lệ — đúng cái ta đang cố tránh.
     */
    private static function clip(string $value, int $limit = 100): string
    {
        return utf8Length($value) <= $limit
            ? $value
            : rtrim(utf8Substr($value, 0, $limit - 1)) . '…';
    }

    // ========================================================================
    // GỬI
    // ========================================================================

    /**
     * Gửi một tin ZNS theo mẫu đã duyệt.
     *
     * @param string $to       số dạng 0xxxxxxxxx (đổi sang 84… ở toZns())
     * @param array  $data     các ô của mẫu, tên do mẫu quy định
     * @param string $who      'shop' | 'customer' | 'otp' — chỉ để đọc log
     * @param bool   $retrying chốt chặn đệ quy, xem TOKEN_ERRORS
     */
    private static function sendZns(
        string $to,
        string $templateId,
        array $data,
        string $who,
        bool $retrying = false
    ): bool {
        if ($templateId === '') {
            error_log('[Zalo] Chưa khai mã mẫu tin cho "' . $who . '" — xem config/zalo.php.');

            return false;
        }

        $token = self::accessToken($retrying);

        if ($token === null) {
            error_log('[Zalo] Không có access_token — tin "' . $who . '" gửi ' . $to . ' không đi.');

            return false;
        }

        $body = json_encode([
            'phone'         => self::toZns($to),
            'template_id'   => $templateId,
            'template_data' => $data,
            /* Chuỗi riêng cho từng tin. Zalo in nó trong báo cáo ZNS, nên khi
               khách bảo "không nhận được" thì có thứ để tra đúng tin đó. */
            'tracking_id'   => $who . '-' . bin2hex(random_bytes(6)),
        ], JSON_UNESCAPED_UNICODE);

        $res = self::post(self::ZNS_URL, [
            'Content-Type: application/json',
            'access_token: ' . $token,
        ], (string) $body);

        if ($res === null) {
            return false;
        }

        $error = (int) ($res['error'] ?? -1);

        if ($error === 0) {
            return true;
        }

        /* Token vừa hết hạn: làm mới rồi gửi lại đúng một lần. $retrying chặn
           vòng lặp vô tận khi refresh_token cũng đã hỏng. */
        if (!$retrying && in_array($error, self::TOKEN_ERRORS, true)) {
            error_log('[Zalo] access_token hỏng (' . $error . ') — làm mới rồi gửi lại.');

            return self::sendZns($to, $templateId, $data, $who, true);
        }

        error_log(sprintf(
            '[Zalo] Gửi tin "%s" tới %s hỏng — error %d: %s',
            $who,
            $to,
            $error,
            (string) ($res['message'] ?? '')
        ));

        return false;
    }

    /**
     * Số điện thoại dạng ZNS: 84xxxxxxxxx, không dấu cộng.
     *
     * Thứ dễ làm sai nhất khi nối API: gửi "0366599711" thì Zalo nhận request
     * rồi lặng lẽ không giao tin.
     */
    private static function toZns(string $phone): string
    {
        return '84' . ltrim($phone, '0');
    }

    // ========================================================================
    // TOKEN
    // ========================================================================

    /**
     * ACCESS_TOKEN KHÔNG NẰM TRONG .env ĐƯỢC.
     *
     * Token của OA sống khoảng 25 giờ. Sau đó phải đổi refresh_token lấy cặp
     * mới — VÀ ZALO TRẢ VỀ MỘT REFRESH_TOKEN MỚI, cái cũ chết ngay. Nghĩa là
     * mỗi lần làm mới đều phải GHI LẠI được, mà .env thì không phải chỗ ứng
     * dụng tự ghi: nó do người triển khai giữ, và sửa file cấu hình lúc đang
     * chạy là cách nhanh nhất để mất luôn cả refresh_token trong một lần ghi
     * hỏng giữa chừng.
     *
     * Nên cặp token sống trong một file JSON dưới storage/ (đã nằm trong
     * .gitignore). .env chỉ giữ refresh_token LẦN ĐẦU, lấy tay một lần qua màn
     * hình cấp quyền của Zalo. Xoá file token là quay về dùng lại giá trị đó.
     *
     * @param bool $force bỏ qua token đang cache, làm mới ngay
     */
    private static function accessToken(bool $force = false): ?string
    {
        $cached = self::readToken();

        if (!$force
            && $cached !== null
            && ($cached['access_token'] ?? '') !== ''
            && (int) ($cached['expires_at'] ?? 0) > time() + self::TOKEN_SKEW) {
            return (string) $cached['access_token'];
        }

        $refresh = (string) ($cached['refresh_token'] ?? '');

        if ($refresh === '') {
            $refresh = (string) config('zalo.refresh_token', '');
        }

        $appId  = (string) config('zalo.app_id', '');
        $secret = (string) config('zalo.secret_key', '');

        if ($refresh === '' || $appId === '' || $secret === '') {
            /* Đường lui cho lúc thử nghiệm: khai tay một access_token còn hạn
               vào .env, gửi thử vài tin rồi bỏ. Không dùng được lâu vì nó chết
               sau ~25 giờ và không có gì làm mới. */
            $manual = (string) config('zalo.access_token', '');

            if ($manual === '') {
                error_log('[Zalo] Chưa khai app_id/secret_key/refresh_token — xem config/zalo.php.');

                return null;
            }

            return $manual;
        }

        $res = self::post(self::TOKEN_URL, [
            'Content-Type: application/x-www-form-urlencoded',
            'secret_key: ' . $secret,
        ], http_build_query([
            'refresh_token' => $refresh,
            'app_id'        => $appId,
            'grant_type'    => 'refresh_token',
        ]));

        $access = $res === null ? '' : (string) ($res['access_token'] ?? '');

        if ($access === '') {
            error_log('[Zalo] Không làm mới được access_token: '
                      . json_encode($res, JSON_UNESCAPED_UNICODE));

            return null;
        }

        self::writeToken([
            'access_token'  => $access,
            // Zalo trả refresh_token MỚI mỗi lần; giữ cái cũ là lần sau chết.
            'refresh_token' => (string) ($res['refresh_token'] ?? $refresh),
            'expires_at'    => time() + (int) ($res['expires_in'] ?? 86400),
        ]);

        return $access;
    }

    /** Đã khai đủ thứ để LẤY được token chưa? Không gọi mạng. */
    private static function hasCredentials(): bool
    {
        if ((string) config('zalo.access_token', '') !== '') {
            return true;
        }

        $cached = self::readToken();

        $hasRefresh = (string) config('zalo.refresh_token', '') !== ''
                      || (string) ($cached['refresh_token'] ?? '') !== '';

        return $hasRefresh
               && (string) config('zalo.app_id', '') !== ''
               && (string) config('zalo.secret_key', '') !== '';
    }

    private static function tokenFile(): string
    {
        return (string) config('zalo.token_file', ROOT_PATH . '/storage/zalo/token.json');
    }

    private static function readToken(): ?array
    {
        $file = self::tokenFile();

        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Cất cặp token mới.
     *
     * Ghi ra file tạm rồi rename: rename là thao tác nguyên tử trên cùng một
     * phân vùng, nên một request khác đọc giữa chừng vẫn thấy file cũ nguyên
     * vẹn thay vì một file JSON cụt. Mất refresh_token là phải vào tay lấy lại
     * qua màn hình cấp quyền của Zalo, không tự phục hồi được.
     *
     * 0600: file này gửi được tin dưới danh nghĩa cửa hàng, không phải thứ để
     * mọi tài khoản trên máy chủ đọc.
     */
    private static function writeToken(array $token): void
    {
        $file = self::tokenFile();
        $dir  = dirname($file);

        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            error_log('[Zalo] Không tạo được thư mục cất token: ' . $dir);

            return;
        }

        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (@file_put_contents($tmp, json_encode($token, JSON_PRETTY_PRINT)) === false
            || !@rename($tmp, $file)) {
            @unlink($tmp);
            error_log('[Zalo] Không ghi được token vào ' . $file);

            return;
        }

        @chmod($file, 0600);
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
            error_log('[Zalo] Chưa khai số Zalo của cửa hàng — xem config/zalo.php.');
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
     * POST, trả mảng đã giải mã hoặc null.
     *
     * Cùng khuôn với core/GoogleAuth.php: đi qua cURL nếu có, không thì dùng
     * luồng https:// của PHP. Bản chỉ-cURL sẽ chết bằng fatal error trên bản
     * PHP không bật extension đó, mà một thông báo phụ thì càng không được phép
     * làm sập trang.
     *
     * Cả hai đường đều KIỂM chứng chỉ TLS và đều có hạn giờ ngắn.
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

        if (!is_array($data)) {
            error_log('[Zalo] ' . $url . ' trả về thứ không phải JSON: ' . substr($raw, 0, 300));

            return null;
        }

        return $data;
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
