<?php

/**
 * BookingModel — lịch hẹn đo mắt.
 *
 * Port từ createAppointment / getBookedSlots trong src/lib/shop.functions.ts.
 *
 * Bảng `appointments` có UNIQUE (store_id, appointment_date, time_slot). Đó
 * mới là thứ THỰC SỰ chặn đặt trùng: kiểm tra bằng SELECT rồi mới INSERT sẽ
 * hở đúng khoảnh khắc giữa hai câu lệnh, hai người bấm cùng lúc đều thấy
 * "còn trống". Ở đây vẫn kiểm trước để báo lỗi cho đẹp, nhưng chốt chặn cuối
 * cùng là ràng buộc của DB — xem cách bắt lỗi 1062 trong create().
 */

class BookingModel extends BaseModel
{
    protected static string $table = 'appointments';

    public const STATUSES = [
        'pending'   => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'done'      => 'Đã hoàn tất',
        'cancelled' => 'Đã huỷ',
    ];

    /**
     * Các khung giờ ĐÃ có người đặt của một cơ sở trong một ngày.
     *
     * Bỏ qua lịch đã huỷ — khung giờ đó phải mở lại cho người khác.
     */
    public static function bookedSlots(string $storeId, string $date): array
    {
        $rows = Database::fetchAll(
            "SELECT time_slot
               FROM appointments
              WHERE store_id = :store
                AND appointment_date = :date
                AND status <> 'cancelled'",
            ['store' => $storeId, 'date' => $date]
        );

        return array_column($rows, 'time_slot');
    }

    /**
     * Khung giờ ĐÃ ĐẶT của NHIỀU cơ sở trong NHIỀU ngày, gộp trong MỘT câu lệnh.
     *
     * Trang đặt lịch dựng sẵn lưới giờ cho cả 7 ngày × mọi cơ sở để khách đổi
     * ngày/cơ sở mà không phải tải lại trang (xem app/views/booking/index.php).
     * Gọi bookedSlots() trong hai vòng lặp lồng nhau sẽ thành 14 câu lệnh cho
     * đúng một trang — trong khi WHERE IN … lấy tất cả bằng một lượt.
     *
     * @return array<string, array<string, array<string, true>>>
     *         [store_id][YYYY-MM-DD][HH:MM] => true
     */
    public static function bookedMatrix(array $storeIds, array $dates): array
    {
        if ($storeIds === [] || $dates === []) {
            return [];
        }

        // Chỗ giữ tham số phải sinh theo số phần tử: nhét thẳng mảng vào câu
        // lệnh là mở đúng cửa SQL injection mà mọi chỗ khác trong dự án đã bịt.
        $params = [];
        $sBind  = [];
        $dBind  = [];

        foreach (array_values($storeIds) as $i => $id) {
            $sBind[] = ":s{$i}";
            $params["s{$i}"] = $id;
        }

        foreach (array_values($dates) as $i => $date) {
            $dBind[] = ":d{$i}";
            $params["d{$i}"] = $date;
        }

        $rows = Database::fetchAll(
            'SELECT store_id, appointment_date, time_slot
               FROM appointments
              WHERE store_id IN (' . implode(', ', $sBind) . ')
                AND appointment_date IN (' . implode(', ', $dBind) . ")
                AND status <> 'cancelled'",
            $params
        );

        $matrix = [];

        foreach ($rows as $row) {
            // appointment_date là cột DATE nên PDO trả 'YYYY-MM-DD' — cùng dạng
            // với khoá $dates, dùng thẳng làm khoá được.
            $matrix[$row['store_id']][$row['appointment_date']][$row['time_slot']] = true;
        }

        return $matrix;
    }

    /**
     * Khung giờ còn trống, tính từ danh sách khung giờ trong config.
     */
    public static function availableSlots(string $storeId, string $date): array
    {
        $all    = (array) config('app.time_slots');
        $booked = self::bookedSlots($storeId, $date);
        $free   = array_diff($all, $booked);

        // Khung giờ ĐÃ TRÔI QUA của hôm nay không còn là chỗ trống.
        // Trước đây danh sách chỉ trừ đi những khung đã có người đặt, nên lúc
        // 15h vẫn mời khách đặt khung 08:00 sáng cùng ngày.
        if ($date === date('Y-m-d')) {
            $now  = date('H:i');
            $free = array_filter($free, static fn (string $slot): bool => $slot > $now);
        }

        return array_values($free);
    }

    /**
     * Khung giờ này đã trôi qua chưa (chỉ xét khi đặt cho chính hôm nay).
     */
    private static function isPastSlot(string $date, string $slot): bool
    {
        return $date === date('Y-m-d') && $slot <= date('H:i');
    }

    /**
     * Tạo lịch hẹn.
     *
     * @return array ['ok'=>true,'code'=>...] | ['ok'=>false,'error'=>...]
     */
    public static function create(array $data): array
    {
        // Không cho đặt lịch trong quá khứ — xét cả NGÀY lẫn GIỜ.
        // Đặt cho 15:00 hôm nay lúc 09:00 sáng thì hợp lệ; đặt cho 08:00 hôm
        // nay lúc 15:00 chiều thì không. Kiểm ở đây chứ không chỉ ở khâu dựng
        // danh sách khung giờ: form không phải đường vào duy nhất, ai gửi
        // thẳng POST cũng phải bị chặn.
        $today = date('Y-m-d');
        if ($data['date'] < $today) {
            return ['ok' => false, 'error' => 'Không thể đặt lịch trong quá khứ.'];
        }

        if (!StoreModel::isBookable($data['storeId'])) {
            return ['ok' => false, 'error' => 'Cơ sở không hợp lệ.'];
        }

        if (!in_array($data['timeSlot'], (array) config('app.time_slots'), true)) {
            return ['ok' => false, 'error' => 'Khung giờ không hợp lệ.'];
        }

        if (self::isPastSlot($data['date'], $data['timeSlot'])) {
            return ['ok' => false, 'error' => 'Khung giờ này đã qua, vui lòng chọn giờ khác.'];
        }

        $code = generateCode('LH');

        try {
            Database::execute(
                'INSERT INTO appointments
                    (id, code, user_id, store_id, appointment_date, time_slot,
                     service_type, full_name, phone, note)
                 VALUES
                    (:id, :code, :user_id, :store_id, :appointment_date, :time_slot,
                     :service_type, :full_name, :phone, :note)',
                [
                    'id'               => uuid(),
                    'code'             => $code,
                    'user_id'          => $data['userId'] ?? null,
                    'store_id'         => $data['storeId'],
                    'appointment_date' => $data['date'],
                    'time_slot'        => $data['timeSlot'],
                    'service_type'     => $data['serviceType'],
                    'full_name'        => $data['fullName'],
                    'phone'            => $data['phone'],
                    'note'             => $data['note'] ?: null,
                ]
            );
        } catch (PDOException $e) {
            // 1062 = trùng khoá duy nhất. Ở bảng này chỉ có hai khoá như vậy:
            // `code` (sinh ngẫu nhiên, gần như không đụng) và bộ ba
            // (cơ sở, ngày, khung giờ) — nên trên thực tế đây luôn là
            // trường hợp hai người đặt cùng khung giờ.
            if (((int) ($e->errorInfo[1] ?? 0)) === 1062) {
                return ['ok' => false, 'error' => 'Khung giờ này vừa được đặt, vui lòng chọn giờ khác.'];
            }

            error_log('[BookingModel] Không tạo được lịch hẹn: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không đặt được lịch, vui lòng thử lại.'];
        }

        return ['ok' => true, 'code' => $code];
    }

    /**
     * Lịch hẹn của một khách. Thay cho policy "own appointments read".
     */
    public static function forUser(string $userId): array
    {
        // Kèm tên cơ sở: thẻ lịch hẹn trong trang tài khoản phải nói khách hẹn
        // ở đâu, và store_id trần thì không dùng được vào việc gì cả.
        return Database::fetchAll(
            'SELECT a.*, s.name AS store_name, s.address AS store_address
               FROM appointments a
               LEFT JOIN stores s ON s.id = a.store_id
              WHERE a.user_id = :uid
              ORDER BY a.appointment_date DESC, a.time_slot DESC',
            ['uid' => $userId]
        );
    }

    /**
     * Danh sách cho khu quản trị, kèm tên cơ sở.
     */
    public static function withStore(string $status = '', int $limit = 100): array
    {
        $where  = '';
        $params = [];

        if ($status !== '') {
            $where = ' WHERE a.status = :status';
            $params['status'] = $status;
        }

        return Database::fetchAll(
            "SELECT a.*, s.name AS store_name, s.code AS store_code
               FROM appointments a
               JOIN stores s ON s.id = a.store_id
               {$where}
              ORDER BY a.appointment_date DESC, a.time_slot DESC
              LIMIT " . max(1, $limit),
            $params
        );
    }
}
