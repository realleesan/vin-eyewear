<?php

/**
 * BookingController — đặt lịch đo mắt (/dat-lich).
 *
 * Dựng theo "Vin Eyewear Booking.dc.html" (Claude Design): bốn thẻ đánh số
 * 1-2-3-4 bên trái, cột tóm tắt dính theo cuộn bên phải.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHUNG GIỜ LÀ NGUYỆN VỌNG, KHÔNG PHẢI CHỖ ĐÃ GIỮ
 *
 * Cửa hàng yêu cầu bỏ giới hạn số người trên một khung giờ: đo mắt và cắt kính
 * hết khoảng 30 phút, phần lâu nhất là 10–15 phút thử tròng còn lắp kính thì
 * máy làm rất nhanh, nên không cần chia ca như tiệm cắt tóc. Khách chọn ngày
 * và giờ mong muốn, cửa hàng ghi nhận rồi GỌI ĐIỆN xác nhận và tự xếp người.
 *
 * Kéo theo một lần dọn đáng kể ở đây: lưới giờ không còn phụ thuộc CƠ SỞ.
 *
 * Bản trước dựng sẵn lưới cho MỌI tổ hợp (cơ sở × ngày) — 2 cơ sở × 7 ngày ×
 * 11 khung = 154 ô — vì mỗi cơ sở có tập khung đã kín khác nhau, và CSS chọn
 * ra đúng một lưới để hiện. Nay không cơ sở nào kín khung nào, nên chỉ còn 7
 * ngày × 11 khung = 77 ô, và mấy luật CSS `.bkspane--0…7` biến mất theo. Luật
 * ấy vốn gõ cứng tối đa tám cơ sở; mở cơ sở thứ chín là lưới giờ của nó không
 * hiện ra nữa mà không có gì báo.
 *
 * Vẫn KHÔNG một dòng JavaScript và vẫn không tải lại trang khi khách đổi ngày:
 * dải ngày là một nhóm radio, CSS hiện lưới của ngày đang chọn.
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
        $grid   = $this->grid($days, $slots);

        $old = $_SESSION['_old_booking'] ?? [];
        unset($_SESSION['_old_booking']);

        // Đến từ nút "Đặt lịch tham dự" của một bài sự kiện: cơ sở, ngày, giờ
        // và ghi chú suy ra từ chính bài đó.
        $event   = $this->event();
        $prefill = $this->eventPrefill($event, $stores, $days, $slots, $grid);

        // Ghi chú CHỈ điền khi khách chưa gõ gì. Quay lại sau khi form báo lỗi
        // thì $old['note'] là chữ của khách — điền đè lên là xoá mất.
        if (!isset($old['note']) && $prefill['note'] !== '') {
            $old['note'] = $prefill['note'];
        }

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
                         . 'Chọn cơ sở, ngày và khung giờ phù hợp.',
            'stores'    => $stores,
            'services'  => self::SERVICES,
            'days'      => $days,
            'slots'     => $slots,
            'grid'      => $grid,
            'pick'      => $this->pick($stores, $days, $slots, $old, $prefill),
            'old'       => $old,
            'event'     => $event,
            // 'fit' | 'later' | 'over' — view đổi câu nhắc theo trạng thái này.
            'eventWhen' => $prefill['when'],
            'success'   => flash('booking_success'),
            'error'     => flash('booking_error'),
        ]);
    }

    /* ────────────────────────────────────────────────────────────────────────
       ĐẶT LỊCH THAM DỰ MỘT SỰ KIỆN

       Nút "Đặt lịch tham dự" ở bài sự kiện trỏ tới /dat-lich?su-kien=SLUG và
       CHỈ mang đúng cái slug đó. Cơ sở, ngày, giờ đều tra lại từ CSDL ở đây
       chứ không nhận qua URL: sửa tay địa chỉ cũng không đặt được ra ngoài
       những cơ sở đang mở và 7 ngày đang nhận lịch.

       Mọi thứ điền sẵn chỉ là GỢI Ý — vẫn là các nút radio bình thường, khách
       đổi lại được hết. Không suy được thì để nguyên mặc định chứ không đoán.
       ──────────────────────────────────────────────────────────────────────── */

    /** Bài sự kiện đang đặt chỗ, nếu có. */
    private function event(): ?array
    {
        $slug = trim((string) ($_GET['su-kien'] ?? ''));

        return $slug === '' ? null : EventModel::findVisibleBySlug($slug);
    }

    /**
     * Suy lựa chọn ban đầu từ bài sự kiện.
     *
     * `when` cho view biết phải nói gì: 'fit' (đã chọn hộ đủ), 'later' (còn xa,
     * ngoài 7 ngày đang mở) hay 'over' (đã diễn ra xong).
     *
     * @return array{store: ?int, service: ?int, day: ?int, time: ?int, note: string, when: string}
     */
    private function eventPrefill(
        ?array $event,
        array $stores,
        array $days,
        array $slots,
        array $grid
    ): array {
        if ($event === null) {
            return [
                'store' => null, 'service' => null, 'day' => null,
                'time'  => null, 'note'    => '',   'when' => '',
            ];
        }

        $store = $this->storeAt((string) ($event['location'] ?? ''), $stores);
        $day   = $this->dayOf($event, $days);
        $when  = $this->eventWindow($event, $day);

        return [
            'store'   => $store,
            'service' => $this->serviceFor($event),
            'day'     => $day,
            // Ngày không chọn được thì cũng không có khung giờ nào để chỉ tới.
            'time'    => $day === null ? null : $this->slotAt($event, $grid, $day, $slots),
            // Chương trình đã xong thì KHÔNG điền "Đăng ký tham dự…": khách vẫn
            // đặt được lịch đo mắt bình thường, nhưng để nhân viên đọc thấy câu
            // đăng ký một sự kiện không còn nữa là gây nhầm chứ không giúp gì.
            'note'    => $when === 'over' ? '' : $this->eventNote($event),
            'when'    => $when,
        ];
    }

    /** 'fit' | 'later' | 'over' — xem eventPrefill(). */
    private function eventWindow(array $event, ?int $day): string
    {
        if ($day !== null) {
            return 'fit';
        }

        // Không có ends_at thì lấy chính ngày khai mạc làm mốc kết thúc, giống
        // cách EventModel::currentPromo() hiểu "còn hạn".
        $end = strtotime((string) ($event['ends_at'] ?: $event['starts_at'] ?? ''));

        return ($end !== false && $end < time()) ? 'over' : 'later';
    }

    /**
     * TỪ KHOÁ NHẬN DẠNG DỊCH VỤ, đọc theo THỨ TỰ TỪ TRÊN XUỐNG.
     *
     * Khoá là chỉ số trong self::SERVICES. Thứ tự chính là luật ưu tiên: một
     * bài hay chạm tới nhiều thứ cùng lúc ("Ưu đãi gọng 0 đồng khi mua tròng
     * cao cấp" có cả gọng lẫn tròng), nên cái nào đặc trưng hơn thì xét trước.
     * Đo mắt lên đầu vì đó cũng là mặc định an toàn nhất của cửa hàng.
     *
     * Từ khoá viết ở dạng slug (bỏ dấu, gạch nối) để so cho khớp với slugify().
     */
    private const SERVICE_HINTS = [
        // 0 — Đo mắt cận/loạn
        0 => ['do-mat', 'kham-mat', 'kham-thi-luc', 'thi-luc', 'khuc-xa', 'can-thi', 'loan-thi'],
        // 3 — Bảo hành / Vệ sinh kính
        3 => ['ve-sinh', 'bao-quan', 'bao-hanh', 'cham-soc', 'sua-kinh'],
        // 2 — Cắt tròng lấy liền
        2 => ['trong-kinh', 'cat-trong', 'nhuom', 'lay-lien'],
        // 1 — Tư vấn & Thử gọng
        1 => ['gong', 'thu-kinh', 'dang-kinh', 'khung-kinh', 'bo-suu-tap',
              'ra-mat', 'trien-lam', 'workshop', 'tu-van'],
    ];

    /**
     * Dịch vụ nào hợp với bài sự kiện này? — đoán từ TIÊU ĐỀ và phân loại.
     *
     * CỐ Ý không đọc excerpt/content: gần như bài nào cũng nhắc "đo khúc xạ
     * miễn phí" ở đâu đó trong thân bài, đọc cả thân thì bài nào cũng ra "Đo
     * mắt cận/loạn" — đoán mà lúc nào cũng ra một đáp án thì không phải đoán.
     *
     * Không nhận ra thì trả null và form giữ dịch vụ mặc định.
     */
    private function serviceFor(array $event): ?int
    {
        // Bọc hai đầu bằng gạch nối để "gong" chỉ khớp trọn một từ: thiếu nó
        // thì mọi chữ có âm "gong" nằm giữa từ khác cũng tính là khớp.
        $text = '-' . slugify(
            $event['title'] . ' ' . ($event['category'] ?? '')
        ) . '-';

        foreach (self::SERVICE_HINTS as $service => $hints) {
            foreach ($hints as $hint) {
                if (str_contains($text, '-' . $hint . '-')) {
                    return isset(self::SERVICES[$service]) ? $service : null;
                }
            }
        }

        return null;
    }

    /**
     * Cơ sở nào tổ chức? — đoán từ `events.location`.
     *
     * Cột đó là văn bản tự do ("Cơ sở Long Biên", "Cơ sở 46 Hoàng Hoa Thám,
     * Tây Hồ", "Cả 2 cơ sở Vin Eyewear") chứ không phải khoá ngoại, nên phải
     * đối chiếu bằng chữ. So trên dạng slug để bỏ qua dấu và hoa/thường — cùng
     * cách SearchController::matches() đang làm.
     *
     * Nhắc tới TỪ HAI cơ sở trở lên ("cả 2 cơ sở") thì trả null: chọn hộ một
     * cái là chọn sai một nửa số lần, thà để khách tự chọn.
     */
    private function storeAt(string $location, array $stores): ?int
    {
        $where = slugify($location);

        if ($where === '') {
            return null;
        }

        $brand = slugify((string) config('company.short_name'));
        $hit   = null;

        foreach ($stores as $i => $store) {
            // Hai dấu hiệu nhận ra một cơ sở: phần tên riêng (bỏ tên thương
            // hiệu, còn "tay-ho") và số nhà + tên đường ("46-hoang-hoa-tham").
            $name    = slugify((string) $store['name']);
            $needles = [
                $brand === '' ? $name : trim(str_replace($brand, '', $name), '-'),
                slugify(explode(',', (string) $store['address'])[0]),
            ];

            foreach ($needles as $needle) {
                if ($needle === '' || !str_contains($where, $needle)) {
                    continue;
                }

                if ($hit !== null && $hit !== $i) {
                    return null; // câu chữ chỉ tới nhiều cơ sở
                }

                $hit = $i;
                break;
            }
        }

        return $hit;
    }

    /**
     * Chỉ số ngày khai mạc trong dải 7 ngày, hoặc null nếu không rơi vào đó.
     */
    private function dayOf(array $event, array $days): ?int
    {
        $start = strtotime((string) ($event['starts_at'] ?? ''));

        if ($start === false) {
            return null;
        }

        $at = array_search(date('Y-m-d', $start), array_column($days, 'date'), true);

        if ($at !== false) {
            return (int) $at;
        }

        // Chương trình đã khai mạc nhưng CÒN đang chạy (triển lãm dài ngày, đợt
        // ưu đãi cả tháng): ngày khai mạc thì qua rồi, nhưng hôm nay tới vẫn
        // đúng chương trình — đó mới là ngày cần điền sẵn.
        $end = strtotime((string) ($event['ends_at'] ?? ''));

        return ($start < time() && $end !== false && $end >= time()) ? 0 : null;
    }

    /**
     * Khung giờ mở sớm nhất kể từ giờ khai mạc.
     *
     * Không đòi trùng khít giờ khai mạc: sự kiện mở 14:00 mà khung ấy đã trôi
     * qua thì 15:00 vẫn là trong chương trình. Không còn khung nào từ giờ đó
     * trở đi thì trả null và cột tóm tắt hiện "Chưa chọn giờ" như thường.
     */
    private function slotAt(array $event, array $grid, int $day, array $slots): ?int
    {
        $start = strtotime((string) ($event['starts_at'] ?? ''));
        $from  = $start === false ? '' : date('H:i', $start);

        foreach ($slots as $ti => $slot) {
            // Khung giờ là "HH:MM" nên so chuỗi cũng ra đúng thứ tự thời gian.
            if ($from !== '' && $slot < $from) {
                continue;
            }

            if (!empty($grid[$day][$ti]['free'])) {
                return $ti;
            }
        }

        return null;
    }

    /**
     * Ghi chú điền sẵn — để nhân viên gọi xác nhận biết khách đăng ký theo
     * chương trình nào, vì bảng `appointments` không có cột nào trỏ tới sự kiện.
     */
    private function eventNote(array $event): string
    {
        $note = 'Đăng ký tham dự: ' . trim((string) $event['title']);
        $when = dateRange($event['starts_at'] ?? null, $event['ends_at'] ?? null);

        if ($when !== '') {
            $note .= ' (' . $when . ')';
        }

        if (!empty($event['location'])) {
            $note .= ' — ' . $event['location'];
        }

        // Ô ghi chú giới hạn 500 ký tự; tiêu đề dài cộng địa điểm dài thì vượt.
        return excerpt($note, 480);
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
    private function grid(array $days, array $slots): array
    {
        $today = date('Y-m-d');
        $now   = date('H:i');
        $grid  = [];

        foreach ($days as $di => $day) {
            foreach ($slots as $ti => $slot) {
                /* Chỉ còn MỘT lý do một ô không bấm được: giờ đã trôi qua.
                   "Đã có người đặt" không còn là lý do — xem đầu file. */
                $grid[$di][$ti] = [
                    'label' => $slot,
                    'free'  => !($day['date'] === $today && $slot <= $now),
                ];
            }
        }

        return $grid;
    }

    /**
     * Lựa chọn ban đầu, dạng CHỈ SỐ để khớp với các nút radio trong view.
     *
     * Thứ tự ưu tiên: dữ liệu vừa gõ hụt (quay lại sau lỗi) → bài sự kiện
     * (?su-kien=SLUG: cơ sở, dịch vụ, ngày, giờ) → ?store=MÃ (liên kết từ
     * trang Liên hệ) → mặc định của bản thiết kế (cơ sở đầu, dịch vụ đầu, hôm
     * nay, chưa chọn giờ).
     *
     * @param array{store: ?int, service: ?int, day: ?int, time: ?int, note: string} $prefill
     *
     * @return array{store: int, service: int, day: int, time: ?int}
     */
    private function pick(array $stores, array $days, array $slots, array $old, array $prefill): array
    {
        $storeIds = array_column($stores, 'id');

        // ?store=TAYHO — mã cơ sở, dùng ở nút "Đặt lịch tại cơ sở này".
        $store = 0;

        if (!empty($_GET['store'])) {
            $found = StoreModel::findActiveByCode((string) $_GET['store']);
            $at    = $found !== null ? array_search($found['id'], $storeIds, true) : false;
            $store = $at === false ? 0 : (int) $at;
        }

        // Nơi tổ chức sự kiện, nếu đoán ra được từ bài viết.
        if ($prefill['store'] !== null) {
            $store = $prefill['store'];
        }

        // Quay lại sau khi form báo lỗi: giữ đúng thứ khách đã chọn.
        if (isset($old['storeId'])) {
            $at    = array_search($old['storeId'], $storeIds, true);
            $store = $at === false ? $store : (int) $at;
        }

        // Dịch vụ: đoán từ bài sự kiện, không đoán được thì về mặc định.
        $service = $prefill['service'] ?? 0;
        $chosen  = array_search($old['serviceType'] ?? null, self::SERVICES, true);

        if ($chosen !== false) {
            $service = (int) $chosen;
        }

        // Ngày khai mạc và khung giờ của sự kiện, cũng nhường chỗ cho $old:
        // đã bấm gửi một lần thì lựa chọn của khách mới là thứ đúng nhất.
        $day  = $prefill['day'] ?? 0;
        $time = $prefill['time'];

        if (isset($old['dayIndex'])) {
            $day = (int) $old['dayIndex'];
        }

        if (isset($old['timeIndex'])) {
            $time = (int) $old['timeIndex'];
        }

        return [
            'store'   => $store,
            'service' => isset(self::SERVICES[$service]) ? $service : 0,
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
            'userId'      => AuthMiddleware::customerId(),
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

        /*
         * Quay về ĐÚNG bài sự kiện đang đặt chỗ. Không mang slug theo thì lần
         * quay lại này mất dòng nhắc "đang đặt lịch cho…", và bấm gửi lần nữa
         * cũng không còn gì để mang tiếp — trong khi lỗi hay gặp nhất ở đây
         * (quên chọn giờ) thì gần như chắc chắn có lần gửi thứ hai.
         *
         * Cơ sở / ngày / giờ / ghi chú khách đã chọn nằm trong $data nên vẫn
         * được giữ nguyên như mọi lần lỗi khác.
         */
        $slug = trim((string) ($_POST['event_slug'] ?? ''));

        redirect('/dat-lich' . ($slug === '' ? '' : '?su-kien=' . rawurlencode($slug)));
    }
}
