<?php

/**
 * BookingController — đặt lịch đo mắt (/dat-lich).
 *
 * Dựng theo "Vin Eyewear Booking.dc.html" (Claude Design): bốn thẻ đánh số
 * 1-2-3-4 bên trái, cột tóm tắt dính theo cuộn bên phải.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO DỰNG SẴN LƯỚI GIỜ CỦA CẢ 7 NGÀY × MỌI CƠ SỞ
 *
 * Bản thiết kế đổi cơ sở/ngày là lưới giờ đổi NGAY, không tải lại trang. Bản
 * cũ ở đây làm ngược lại: mỗi lần đổi là một lượt GET để server tính lại
 * khung giờ trống — mà tải lại thì mất luôn tên, số điện thoại khách vừa gõ.
 *
 * Nay server gửi sẵn lưới giờ cho MỌI tổ hợp (cơ sở × ngày), và CSS chọn ra
 * đúng một lưới để hiện. Không một dòng JavaScript, không mất dữ liệu đang gõ.
 * Giá phải trả là trang nặng thêm: 2 cơ sở × 7 ngày × 11 khung = 154 ô. Vẫn
 * nhẹ, nhưng nếu cửa hàng mở tới hàng chục cơ sở thì phải tính lại cách này.
 *
 * Toàn bộ dữ liệu đó lấy bằng MỘT câu lệnh — xem BookingModel::bookedMatrix().
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
    private const SERVICES = [
        'Đo mắt cận/loạn',
        'Tư vấn & Thử gọng',
        'Cắt tròng lấy liền',
        'Bảo hành / Vệ sinh kính',
    ];

    /** Số ngày hiện trên dải chọn ngày, đúng bản thiết kế. */
    private const DAYS = 7;

    public function index(): void
    {
        $stores = StoreModel::active();
        $slots  = array_values((array) config('app.time_slots'));
        $days   = $this->days();

        $old = $_SESSION['_old_booking'] ?? [];
        unset($_SESSION['_old_booking']);

        $this->renderView('booking/index', [
            'pageTitle' => 'Đặt lịch đo mắt — Vin Eyewear',
            'metaDesc'  => 'Đặt lịch đo khúc xạ miễn phí tại Vin Eyewear. '
                         . 'Chọn cơ sở, ngày và khung giờ phù hợp.',
            'stores'    => $stores,
            'services'  => self::SERVICES,
            'days'      => $days,
            'slots'     => $slots,
            'grid'      => $this->grid($stores, $days, $slots),
            'pick'      => $this->pick($stores, $days, $slots, $old),
            'old'       => $old,
            'success'   => flash('booking_success'),
            'error'     => flash('booking_error'),
        ]);
    }

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
     * Lưới giờ [chỉ số cơ sở][chỉ số ngày][chỉ số khung] => ['label', 'free'].
     *
     * Một khung giờ KHÔNG trống vì hai lý do khác nhau: đã có người đặt, hoặc
     * đã trôi qua (chỉ xảy ra với hôm nay). Cả hai đều hiện gạch ngang như
     * nhau trong bản thiết kế, nên ở đây gộp thành một cờ `free`.
     */
    private function grid(array $stores, array $days, array $slots): array
    {
        $booked = BookingModel::bookedMatrix(
            array_column($stores, 'id'),
            array_column($days, 'date')
        );

        $today = date('Y-m-d');
        $now   = date('H:i');
        $grid  = [];

        foreach ($stores as $si => $store) {
            foreach ($days as $di => $day) {
                foreach ($slots as $ti => $slot) {
                    $taken = isset($booked[$store['id']][$day['date']][$slot]);
                    $past  = $day['date'] === $today && $slot <= $now;

                    $grid[$si][$di][$ti] = [
                        'label' => $slot,
                        'free'  => !$taken && !$past,
                    ];
                }
            }
        }

        return $grid;
    }

    /**
     * Lựa chọn ban đầu, dạng CHỈ SỐ để khớp với các nút radio trong view.
     *
     * Thứ tự ưu tiên: dữ liệu vừa gõ hụt (quay lại sau lỗi) → ?store=MÃ (liên
     * kết từ trang Liên hệ) → mặc định của bản thiết kế (cơ sở đầu, dịch vụ
     * đầu, hôm nay, chưa chọn giờ).
     *
     * @return array{store: int, service: int, day: int, time: ?int}
     */
    private function pick(array $stores, array $days, array $slots, array $old): array
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

        $service = array_search($old['serviceType'] ?? null, self::SERVICES, true);
        $day     = isset($old['dayIndex']) ? (int) $old['dayIndex'] : 0;
        $time    = isset($old['timeIndex']) ? (int) $old['timeIndex'] : null;

        return [
            'store'   => $store,
            'service' => $service === false ? 0 : (int) $service,
            'day'     => ($day >= 0 && $day < count($days)) ? $day : 0,
            'time'    => ($time !== null && $time >= 0 && $time < count($slots)) ? $time : null,
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

        $slots = array_values((array) config('app.time_slots'));
        $days  = $this->days();

        /*
         * Ô giờ gửi lên dạng "chỉ số ngày|chỉ số khung", ví dụ "3|5".
         *
         * KHÔNG nhận thẳng ngày và giờ từ form. Ngày được dựng LẠI ở server từ
         * chỉ số, nên dù có sửa tay giá trị gửi lên cũng chỉ chọn được trong
         * đúng 7 ngày đang mở — không đặt lùi về quá khứ hay nhảy sang năm sau
         * được. (BookingModel::create() vẫn kiểm lần nữa, đây chỉ là lớp đầu.)
         */
        [$dayIndex, $timeIndex] = $this->parseSlot((string) ($_POST['time_slot'] ?? ''));

        $valid = $dayIndex !== null && $timeIndex !== null
              && isset($days[$dayIndex], $slots[$timeIndex]);

        $data = [
            'storeId'     => (string) ($_POST['store_id'] ?? ''),
            'dayIndex'    => $dayIndex,
            'timeIndex'   => $timeIndex,
            'date'        => $valid ? $days[$dayIndex]['date'] : '',
            'timeSlot'    => $valid ? $slots[$timeIndex] : '',
            'serviceType' => (string) ($_POST['service_type'] ?? ''),
            'fullName'    => trim((string) ($_POST['full_name'] ?? '')),
            'phone'       => trim((string) ($_POST['phone'] ?? '')),
            'note'        => trim((string) ($_POST['note'] ?? '')),
            'userId'      => $_SESSION['user_id'] ?? null,
        ];

        if (!$valid) {
            $this->fail('Vui lòng chọn khung giờ.', $data);
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
         * Đặt xong thì đi đâu?
         *
         * ĐANG ĐĂNG NHẬP -> sang thẳng "Lịch hẹn của tôi". Lịch vừa đặt đã gắn
         * user_id nên hiện ngay ở đầu danh sách, kèm nút đổi giờ / huỷ — tức là
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
     * Tách "3|5" thành [3, 5]. Trả [null, null] nếu không đúng dạng.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function parseSlot(string $raw): array
    {
        if (!preg_match('/^(\d{1,2})\|(\d{1,2})$/', $raw, $m)) {
            return [null, null];
        }

        return [(int) $m[1], (int) $m[2]];
    }

    /**
     * Báo lỗi, nhớ lại nội dung đã nhập rồi quay về form.
     */
    private function fail(string $message, array $data): never
    {
        $_SESSION['_old_booking'] = $data;
        flash('booking_error', $message);

        redirect('/dat-lich');
    }
}
