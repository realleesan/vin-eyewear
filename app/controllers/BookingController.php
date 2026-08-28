<?php

/**
 * BookingController — đặt lịch đo mắt (/dat-lich).
 *
 * Dựng theo "Vin Eyewear Booking.dc.html" (Claude Design): bốn thẻ đánh số
 * 1-2-3-4 bên trái, cột tóm tắt dính theo cuộn bên phải.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÁCH CHỌN NGÀY, KHÔNG CHỌN GIỜ — 2026-08-25
 *
 * Cửa hàng đã bỏ giới hạn số người trên một khung giờ từ trước: đo mắt và cắt
 * kính hết khoảng 30 phút, phần lâu nhất là 10–15 phút thử tròng còn lắp kính
 * thì máy làm rất nhanh, nên không cần chia ca như tiệm cắt tóc. Khung giờ vì
 * thế đã chỉ còn là NGUYỆN VỌNG — cửa hàng ghi nhận rồi GỌI ĐIỆN xác nhận và
 * tự xếp người.
 *
 * Nay bỏ nốt ô chọn giờ. Một câu hỏi mà câu trả lời gần như không bao giờ được
 * dùng thì chỉ làm form dài thêm và tạo kỳ vọng sai: khách tick 15:00 rồi tin
 * rằng mình đã có chỗ lúc 15:00.
 *
 * Cái mất đi lớn nhất ở file này là LƯỚI GIỜ. Bản trước dựng sẵn 7 ngày × 11
 * khung = 77 ô kèm cờ "còn bấm được không", cùng grid() và slotAt(); trước nữa
 * còn nhân thêm chiều cơ sở thành 154 ô. Không còn ô giờ thì cả tầng ấy biến
 * mất, và dải ngày — vốn là một nhóm radio CHỈ để chọn lưới nào hiện ra, server
 * bỏ qua — nay là trường được gửi lên thật.
 *
 * Vẫn KHÔNG một dòng JavaScript: dải ngày là nhóm radio, CSS đọc ngày đang tick
 * để cập nhật cột tóm tắt bên phải.
 *
 * ĐÂY LÀ GIẢ ĐỊNH A5 trong CLAUDE.md, chưa được BA nghiệm thu.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class BookingController extends BaseController
{
    /**
     * Dịch vụ nhận đặt lịch.
     *
     * GIỮ NGUYÊN danh sách của cửa hàng, không lấy 4 dịch vụ trong bản thiết
     * kế: chuỗi này được ghi thẳng vào `appointments.service_type` của những
     * lịch hẹn đã có và hiện trong khu quản trị. Đổi chữ ở đây là làm lệch
     * dữ liệu cũ, mà tên dịch vụ là chuyện của cửa hàng chứ không phải của
     * bản thiết kế.
     */
    /* Danh sách thật nằm ở BookingModel::SERVICES — khu quản trị cũng đọc nó
       để dựng ô chọn dịch vụ khi tạo lịch hộ khách. Bí danh này giữ nguyên mọi
       chỗ gọi self::SERVICES bên dưới. */
    private const SERVICES = BookingModel::SERVICES;

    /** Số ngày hiện trên dải chọn ngày, đúng bản thiết kế. */
    private const DAYS = 7;

    public function index(): void
    {
        $stores = StoreModel::active();
        $days   = $this->days();

        $old = $_SESSION['_old_booking'] ?? [];
        unset($_SESSION['_old_booking']);

        /*
         * Khách đang đăng nhập thì tên và số điện thoại lấy sẵn từ hồ sơ.
         *
         * Cùng lẽ với trang thanh toán (OrderController::checkout): thứ khách
         * đã khai một lần thì đừng bắt gõ lại. Vẫn là ô nhập bình thường, sửa
         * được — đặt hộ người nhà bằng số của người ta là chuyện thường.
         *
         * isset() chứ không empty(): quay lại sau lỗi thì ô rỗng cũng là lựa
         * chọn của khách, điền đè lên là giật mất chữ đang gõ dở.
         */
        $khachId = AuthMiddleware::customerId();
        $me = $khachId !== null ? UserModel::profile($khachId) : null;

        if ($me !== null) {
            $old['fullName'] = $old['fullName'] ?? (string) ($me['full_name'] ?? '');
            $old['phone']    = $old['phone']    ?? (string) ($me['phone'] ?? '');
        }

        $this->renderView('booking/index', [
            'pageTitle' => 'Đặt lịch đo mắt — Vin Eyewear',
            'metaDesc'  => 'Đặt lịch đo khúc xạ miễn phí tại Vin Eyewear. '
                         . 'Chọn cơ sở và ngày phù hợp.',
            'stores'    => $stores,
            'services'  => self::SERVICES,
            'days'      => $days,
            'pick'      => $this->pick($stores, $days, $old),
            'old'       => $old,
            'success'   => flash('booking_success'),
            'error'     => flash('booking_error'),
        ]);
    }

    /* ────────────────────────────────────────────────────────────────────────
       "ĐẶT LỊCH THAM DỰ" ĐÃ BỎ — 2026-08-26.

       Chỗ này từng nhận /dat-lich?su-kien=SLUG từ nút "Đặt lịch tham dự" ở
       cuối một bài sự kiện, rồi SUY cơ sở, ngày, dịch vụ và ghi chú ra từ
       chính bài đó (events.location, starts_at/ends_at, tiêu đề + phân loại).
       Tính năng sự kiện đã bỏ hẳn nên không còn bài nào để suy, và cả cụm
       event() · eventPrefill() · eventWindow() · serviceFor() · storeAt() ·
       dayOf() · eventNote() cùng bảng SERVICE_HINTS đi theo.

       Trang đặt lịch KHÔNG mất gì khác: ?store=MÃ (nút "Đặt lịch tại cơ sở
       này" ở trang Liên hệ) vẫn chạy, và mọi lựa chọn vẫn là radio bình
       thường với mặc định cơ sở đầu · dịch vụ đầu · hôm nay.
       ──────────────────────────────────────────────────────────────────────── */

    /**
     * Bảy ngày kể từ hôm nay.
     *
     * @return list<array{date: string, weekday: string, dm: string}>
     */
    private function days(): array
    {
        $wd   = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
        $days = [];

        for ($i = 0; $i < self::DAYS; $i++) {
            $ts = strtotime("+{$i} day");

            $days[] = [
                'date'    => date('Y-m-d', $ts),
                'weekday' => $i === 0 ? 'Hôm nay' : $wd[(int) date('w', $ts)],
                'dm'      => date('d/m', $ts),
            ];
        }

        return $days;
    }

    /**
     * Lựa chọn ban đầu, dạng CHỈ SỐ để khớp với các nút radio trong view.
     *
     * Thứ tự ưu tiên: dữ liệu vừa gõ hụt (quay lại sau lỗi) → ?store=MÃ (liên
     * kết từ trang Liên hệ) → mặc định của bản thiết kế (cơ sở đầu, dịch vụ
     * đầu, hôm nay).
     *
     * KHÔNG CÒN KHOÁ 'time'. Ngày luôn có một giá trị được chọn sẵn — mặc định
     * là hôm nay — nên khác hẳn khung giờ ngày trước: khung giờ chấp nhận
     * "chưa chọn" và cột tóm tắt phải có sẵn câu "Chưa chọn giờ" cho trạng
     * thái đó. Nay không có trạng thái rỗng nào để hiện.
     *
     * @return array{store: int, service: int, day: int}
     */
    private function pick(array $stores, array $days, array $old): array
    {
        $storeIds = array_column($stores, 'id');

        // ?store=TAYHO — mã cơ sở, dùng ở nút "Đặt lịch tại cơ sở này".
        $store = 0;

        if (!empty($_GET['store'])) {
            $found = StoreModel::findActiveByCode((string) $_GET['store']);
            $at    = $found !== null ? array_search($found['id'], $storeIds, true) : false;
            $store = $at === false ? 0 : (int) $at;
        }

        // Quay lại sau khi form báo lỗi: giữ đúng thứ khách đã chọn.
        if (isset($old['storeId'])) {
            $at    = array_search($old['storeId'], $storeIds, true);
            $store = $at === false ? $store : (int) $at;
        }

        $service = 0;
        $chosen  = array_search($old['serviceType'] ?? null, self::SERVICES, true);

        if ($chosen !== false) {
            $service = (int) $chosen;
        }

        // Quay lại sau lỗi thì lựa chọn của khách mới là thứ đúng nhất.
        $day = 0;

        if (isset($old['dayIndex'])) {
            $day = (int) $old['dayIndex'];
        }

        return [
            'store'   => $store,
            'service' => isset(self::SERVICES[$service]) ? $service : 0,
            'day'     => ($day >= 0 && $day < count($days)) ? $day : 0,
        ];
    }

    /**
     * Nhận form đặt lịch (POST /dat-lich/gui).
     */
    public function submit(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            redirect('/dat-lich');
        }

        if (!csrfCheck($_POST['_token'] ?? null)) {
            flash('booking_error', 'Phiên làm việc đã hết hạn, vui lòng đặt lại.');
            redirect('/dat-lich');
        }

        $days = $this->days();

        /*
         * Form gửi lên CHỈ SỐ ngày trong dải 7 ngày, không phải ngày tháng.
         *
         * Giữ nguyên tính chất quan trọng nhất của cách làm cũ: ngày được dựng
         * LẠI ở server từ chỉ số, nên sửa tay giá trị gửi lên cũng chỉ chọn
         * được trong đúng 7 ngày đang mở — không đặt lùi về quá khứ hay nhảy
         * sang năm sau được. (BookingModel::create() vẫn kiểm lần nữa, đây chỉ
         * là lớp đầu.)
         *
         * Tên trường vẫn là `bk_day` như hồi nó chỉ dùng để CSS chọn lưới giờ
         * nào hiện ra và server bỏ qua. Đổi tên thì đẹp hơn nhưng phải sửa cả
         * các luật CSS gắn với #bk-d-N, mà cái tên này không sai — nó vẫn là
         * "ngày đang chọn".
         */
        $rawDay   = (string) ($_POST['bk_day'] ?? '');
        $dayIndex = preg_match('/^\d{1,2}$/', $rawDay) ? (int) $rawDay : null;
        $valid    = $dayIndex !== null && isset($days[$dayIndex]);

        $data = [
            'storeId'     => (string) ($_POST['store_id'] ?? ''),
            'dayIndex'    => $dayIndex,
            'date'        => $valid ? $days[$dayIndex]['date'] : '',
            'serviceType' => (string) ($_POST['service_type'] ?? ''),
            'fullName'    => trim((string) ($_POST['full_name'] ?? '')),
            'phone'       => trim((string) ($_POST['phone'] ?? '')),
            'note'        => trim((string) ($_POST['note'] ?? '')),
            'userId'      => AuthMiddleware::customerId(),
        ];

        if (!$valid) {
            $this->fail('Vui lòng chọn ngày hẹn.', $data);
        }

        if (utf8Length($data['fullName']) < 2) {
            $this->fail('Vui lòng nhập họ tên.', $data);
        }

        if (strlen(preg_replace('/\D/', '', $data['phone'])) < 8) {
            $this->fail('Số điện thoại không hợp lệ.', $data);
        }

        if (!in_array($data['serviceType'], self::SERVICES, true)) {
            $this->fail('Vui lòng chọn dịch vụ.', $data);
        }

        $result = BookingModel::create($data);

        if (!$result['ok']) {
            $this->fail($result['error'], $data);
        }

        /*
         * ĐẨY SANG ZALO CỦA CỬA HÀNG — ngay khi lịch đã nằm trong CSDL.
         *
         * Cửa hàng đặt tính năng này để nhân viên không phải liên tục mở
         * /quan-tri/lich-hen xem có lịch mới chưa. Đọc lại hàng vừa ghi thay vì
         * gửi $data: tin báo cần TÊN cơ sở, mà $data chỉ có id.
         *
         * Zalo::appointment() tự nuốt mọi lỗi và có hạn giờ ngắn — Zalo sập hay
         * mạng ra ngoài bị chặn cũng không được phép biến thành trang lỗi cho
         * người vừa đặt lịch xong. Xem khối chú thích đầu core/Zalo.php.
         */
        $saved = BookingModel::findByCode($result['code']);

        if ($saved !== null) {
            Zalo::appointment($saved, 'created');
        }

        /*
         * Đặt xong thì đi đâu?
         *
         * ĐANG ĐĂNG NHẬP -> sang thẳng "Lịch hẹn của tôi". Lịch vừa đặt đã gắn
         * user_id nên hiện ngay ở đầu danh sách, kèm nút đổi ngày / huỷ — tức là
         * khách thấy luôn lịch của mình nằm ở đâu và sửa được bằng cách nào,
         * thay vì một mã LH… trên trang đặt lịch rồi phải tự mò ra trang tài
         * khoản. Dùng flash 'account_success' vì đó là ô thông báo mà
         * auth/profile.php đọc.
         *
         * KHÁCH VÃNG LAI -> ở lại /dat-lich như cũ. Không có trang tài khoản
         * nào để sang, mà /tai-khoan thì AuthMiddleware đá thẳng về trang đăng
         * nhập — đặt lịch xong bị hỏi mật khẩu là mất hẳn mã lịch hẹn.
         */
        if (!empty($data['userId'])) {
            flash(
                'account_success',
                'Đã đặt lịch! Mã lịch hẹn ' . $result['code']
                . '. Chúng tôi sẽ gọi xác nhận trong 15 phút.'
            );

            redirect('/tai-khoan?muc=lich-hen');
        }

        flash('booking_success', $result['code']);
        redirect('/dat-lich');
    }

    /**
     * Báo lỗi, nhớ lại nội dung đã nhập rồi quay về form.
     */
    private function fail(string $message, array $data): never
    {
        $_SESSION['_old_booking'] = $data;
        flash('booking_error', $message);

        // Cơ sở / ngày / dịch vụ / ghi chú khách đã chọn nằm trong $data nên
        // vẫn được giữ nguyên khi form dựng lại.
        redirect('/dat-lich');
    }
}
