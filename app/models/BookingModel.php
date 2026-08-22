<?php

/**
 * BookingModel — lịch hẹn đo mắt.
 *
 * Port từ createAppointment / getBookedSlots trong src/lib/shop.functions.ts.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỘT KHUNG GIỜ NHẬN BAO NHIÊU LỊCH CŨNG ĐƯỢC — CHỦ Ý, KHÔNG PHẢI SÓT
 *
 * Bảng `appointments` từng có UNIQUE (cơ sở, ngày, giờ, `active_slot`) cho mỗi
 * khung giờ đúng một lịch còn hiệu lực, kèm cả một cột sinh chỉ để phục vụ
 * khoá ấy. Cửa hàng yêu cầu bỏ hẳn: đo mắt và cắt kính hết khoảng 30 phút,
 * phần lâu nhất là 10–15 phút thử tròng còn lắp kính thì máy làm rất nhanh,
 * nên không cần chia ca như tiệm cắt tóc.
 *
 * Nên khung giờ khách chọn là NGUYỆN VỌNG, không phải một chỗ đã giữ. Cửa hàng
 * ghi nhận rồi GỌI ĐIỆN xác nhận và tự xếp người — cái chốt thật nằm ở cuộc
 * gọi đó. Kéo theo ba thứ biến mất khỏi file này: bookedSlots(),
 * bookedMatrix(), và nhánh bắt lỗi 1062 "khung giờ vừa có người đặt".
 *
 * CÒN LẠI MỘT LUẬT VỀ GIỜ, và nó không liên quan tới chỗ ngồi: không đặt được
 * vào giờ ĐÃ TRÔI QUA. 15h chiều mà vẫn mời khách chọn khung 08:00 sáng cùng
 * ngày thì đó là một cái hẹn không ai giữ được. Xem openSlots().
 *
 * Lỗi 1062 trên bảng này nay chỉ còn một nghĩa duy nhất: trùng MÃ lịch hẹn.
 * ─────────────────────────────────────────────────────────────────────────────
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
     * Khung giờ MỞ của một ngày — cả danh sách trong config, trừ đi giờ đã qua.
     *
     * Thay cho bộ ba bookedSlots() / bookedMatrix() / availableSlots() cũ. Cả
     * ba đều tồn tại để trả lời "khung này còn chỗ không", mà câu hỏi đó không
     * còn nữa — xem khối chú thích đầu file.
     *
     * KHÔNG nhận $storeId nữa, và đó là điểm chính: danh sách giờ mở giống hệt
     * nhau ở mọi cơ sở, nên tham số ấy chỉ còn là một lời hứa sai rằng kết quả
     * phụ thuộc vào nó.
     *
     * @return list<string>
     */
    public static function openSlots(string $date): array
    {
        $today = date('Y-m-d');

        // NGÀY ĐÃ QUA THÌ KHÔNG CÓ KHUNG NÀO, chứ không phải có đủ cả mười một.
        // Nơi gọi đều đã chặn ngày quá khứ trước khi tới đây, nhưng một hàm
        // công khai phải tự đúng: trả đủ danh sách cho ngày hôm qua là mời
        // người gọi tiếp theo dựng ra một cái hẹn không thể xảy ra.
        if ($date < $today) {
            return [];
        }

        $all = array_values((array) config('app.time_slots'));

        if ($date > $today) {
            return $all;
        }

        // Hôm nay thì cắt phần đã trôi qua. So sánh chuỗi "HH:MM" chạy đúng vì
        // cả hai vế đều hai chữ số có đệm 0 — "09:00" < "14:00".
        $now = date('H:i');

        return array_values(array_filter($all, static fn (string $slot): bool => $slot > $now));
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
            /* 1062 = trùng khoá duy nhất. Từ khi bỏ khoá khung giờ, bảng này
               chỉ còn MỘT khoá như vậy: `code`. Mã sinh ngẫu nhiên nên đụng là
               chuyện hiếm tới mức gần như không xảy ra, và khi xảy ra thì thử
               lại là xong — không phải lỗi của khách và cũng không có gì để họ
               sửa, nên câu báo giống hệt mọi lỗi ghi khác. */
            error_log('[BookingModel] Không tạo được lịch hẹn: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không đặt được lịch, vui lòng thử lại.'];
        }

        return ['ok' => true, 'code' => $code];
    }

    /**
     * Lịch hẹn của một khách. Thay cho policy "own appointments read".
     */
    /**
     * Số lịch hẹn SẮP TỚI của một khách — con số trên huy hiệu ở cột trái.
     *
     * Cùng cách nghĩ với OrderModel::countActive(): huy hiệu đếm việc còn phải
     * theo dõi, không đếm lịch sử. Ba thứ bị loại:
     *
     *   status = 'cancelled'   khách hoặc cửa hàng đã huỷ
     *   status = 'done'        đã đo xong
     *   ngày hẹn < hôm nay     quá ngày, dù chưa ai bấm gì
     *
     * Vế thứ ba là vế quan trọng nhất và cũng dễ quên nhất: một lịch 'pending'
     * của tháng trước không bao giờ tự đổi trạng thái — không có tiến trình
     * nền nào dọn nó, và nhân viên cũng không phải lúc nào cũng vào đánh dấu.
     * Chỉ dựa vào status thì huy hiệu treo con số đó mãi mãi.
     *
     * So theo NGÀY chứ không theo giờ hẹn: lịch 09:00 sáng nay lúc 8 giờ tối
     * vẫn còn đếm cho tới nửa đêm. Cố tình như vậy — khách tới muộn, hoặc
     * nhân viên chưa kịp đánh dấu 'done', thì cái lịch ấy vẫn là việc của hôm
     * nay. CURDATE() để MySQL tự chốt ngày, khỏi lệch múi giờ với PHP.
     */
    public static function countUpcoming(string $userId): int
    {
        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM appointments
              WHERE user_id = :uid
                AND status NOT IN (\'done\', \'cancelled\')
                AND appointment_date >= CURDATE()',
            ['uid' => $userId]
        );
    }

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
     * Một lịch hẹn theo MÃ, và chỉ khi đúng chủ.
     *
     * Điều kiện user_id nằm trong câu lệnh, không kiểm sau khi đọc: mã lịch in
     * trên trang tài khoản và cả trong email, nên nó không phải bí mật.
     */
    public static function findOwned(string $code, string $userId): ?array
    {
        if ($code === '') {
            return null;
        }

        return Database::fetchOne(
            'SELECT a.*, s.name AS store_name, s.address AS store_address
               FROM appointments a
               LEFT JOIN stores s ON s.id = a.store_id
              WHERE a.code = :code AND a.user_id = :uid
              LIMIT 1',
            ['code' => $code, 'uid' => $userId]
        );
    }

    /**
     * Một lịch hẹn theo MÃ, không hỏi ai là chủ.
     *
     * Chỉ dùng cho việc đẩy thông báo sang Zalo sau khi đã ghi xong — nơi gọi
     * vừa tự tay tạo hoặc sửa đúng hàng này, nên không có gì để kiểm quyền
     * thêm. KHÔNG dùng hàm này ở bất kỳ chỗ nào khách nhìn thấy dữ liệu: mã
     * lịch hẹn ngắn và đoán được, findOwned() mới là hàm cho việc đó.
     *
     * Trả về kèm store_name vì tin báo cho cửa hàng phải nói rõ cơ sở nào.
     */
    public static function findByCode(string $code): ?array
    {
        if ($code === '') {
            return null;
        }

        return Database::fetchOne(
            'SELECT a.*, s.name AS store_name, s.address AS store_address
               FROM appointments a
               LEFT JOIN stores s ON s.id = a.store_id
              WHERE a.code = :code
              LIMIT 1',
            ['code' => $code]
        );
    }

    // ========================================================================
    // KHÁCH TỰ ĐỔI / HUỶ LỊCH
    //
    // MỘT CHỖ TRẢ LỜI "ĐƯỢC SỬA HAY KHÔNG": changeBlocker(). View gọi nó để biết
    // có in nút ra hay không, controller gọi lại đúng nó trước khi ghi. Nếu mỗi
    // bên tự viết điều kiện riêng thì sớm muộn nút hiện ra mà bấm vào bị chặn,
    // hoặc tệ hơn — nút không hiện nhưng POST tay vẫn ghi được.
    // ========================================================================

    /**
     * Vì sao lịch này KHÔNG được đổi/huỷ nữa — hoặc null nếu được.
     *
     * Chuỗi trả về viết cho khách đọc, nên view in thẳng được.
     */
    public static function changeBlocker(array $appointment): ?string
    {
        $status = (string) ($appointment['status'] ?? '');

        if ($status === 'cancelled') {
            return 'Lịch này đã huỷ.';
        }

        if ($status === 'done') {
            return 'Lịch này đã hoàn tất.';
        }

        $at = self::startsAt($appointment);

        if ($at === null) {
            // Ngày/giờ hỏng thì không đoán — để khách gọi tổng đài, đừng cho sửa
            // một hàng mà chính hệ thống không đọc nổi.
            return 'Không đọc được giờ hẹn, vui lòng gọi tổng đài.';
        }

        if ($at <= time()) {
            return 'Giờ hẹn đã qua.';
        }

        $cutoff = self::cutoffSeconds();

        if ($at - time() < $cutoff) {
            return sprintf(
                'Chỉ đổi hoặc huỷ được trước giờ hẹn ít nhất %d giờ. Vui lòng gọi tổng đài.',
                (int) round($cutoff / 3600)
            );
        }

        return null;
    }

    /** Khách hàng đổi/huỷ được tới trước giờ hẹn bao nhiêu giây. */
    private static function cutoffSeconds(): int
    {
        return max(0, (int) config('app.booking_change_cutoff_hours', 2)) * 3600;
    }

    /**
     * Mốc thời gian bắt đầu của lịch hẹn, hoặc null nếu dữ liệu hỏng.
     *
     * time_slot lưu dạng "09:00" nên ghép thẳng với ngày là ra mốc đầy đủ.
     */
    private static function startsAt(array $appointment): ?int
    {
        $date = (string) ($appointment['appointment_date'] ?? '');
        $slot = (string) ($appointment['time_slot'] ?? '');

        if ($date === '' || !preg_match('/^\d{1,2}:\d{2}$/', $slot)) {
            return null;
        }

        return strtotime($date . ' ' . $slot) ?: null;
    }

    /**
     * Khách tự huỷ lịch.
     *
     * KHÔNG xoá hàng: cửa hàng cần biết khung giờ đó từng có người hẹn rồi huỷ —
     * đó là dữ liệu vận hành (khách hay huỷ giờ nào, cơ sở nào trống thật), và
     * nó cũng là thứ nhân viên tra lại khi khách gọi hỏi về một mã lịch cũ.
     *
     * @return array ['ok'=>true] | ['ok'=>false,'error'=>...]
     */
    public static function cancelOwned(string $code, string $userId): array
    {
        $appointment = self::findOwned($code, $userId);

        if ($appointment === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy lịch hẹn.'];
        }

        $blocker = self::changeBlocker($appointment);

        if ($blocker !== null) {
            return ['ok' => false, 'error' => $blocker];
        }

        /*
         * Điều kiện status nằm TRONG câu UPDATE, không chỉ ở phép kiểm phía trên:
         * giữa lúc đọc và lúc ghi, nhân viên có thể vừa đổi lịch sang 'done'.
         * Trả về 0 dòng thì coi như có người khác vừa đổi trước.
         */
        $changed = Database::execute(
            "UPDATE appointments
                SET status = 'cancelled'
              WHERE code = :code AND user_id = :uid
                AND status IN ('pending', 'confirmed')",
            ['code' => $code, 'uid' => $userId]
        );

        if ($changed === 0) {
            return ['ok' => false, 'error' => 'Lịch hẹn vừa được cập nhật, vui lòng tải lại trang.'];
        }

        return ['ok' => true];
    }

    /**
     * Khách tự đổi sang ngày/giờ khác.
     *
     * BA quyết định đáng ghi lại:
     *
     * 1. SỬA TẠI CHỖ, không huỷ-rồi-đặt-mới. Khách giữ nguyên mã lịch đã được
     *    nhắc qua điện thoại, và cửa hàng không có hai hàng cho cùng một lần hẹn.
     *
     * 2. ĐỔI XONG VỀ 'pending'. Nhân viên xác nhận cho một giờ CỤ THỂ; giờ khác
     *    thì lời xác nhận cũ không còn nghĩa gì. Để nguyên 'confirmed' sẽ thành
     *    một lịch "đã xác nhận" mà chưa ai ở cửa hàng nhìn thấy.
     *
     * 3. GIỮ NGUYÊN CƠ SỞ. Đổi cơ sở là đổi gần hết thông tin của lần hẹn (đường
     *    đi, nhân viên, thiết bị) — việc đó nên là đặt lịch mới, không phải sửa.
     *
     * @return array ['ok'=>true] | ['ok'=>false,'error'=>...]
     */
    public static function rescheduleOwned(
        string $code,
        string $userId,
        string $date,
        string $slot
    ): array {
        $appointment = self::findOwned($code, $userId);

        if ($appointment === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy lịch hẹn.'];
        }

        $blocker = self::changeBlocker($appointment);

        if ($blocker !== null) {
            return ['ok' => false, 'error' => $blocker];
        }

        // Cùng bộ kiểm với create(): form không phải đường vào duy nhất.
        if ($date < date('Y-m-d')) {
            return ['ok' => false, 'error' => 'Không thể hẹn vào một ngày đã qua.'];
        }

        if (!in_array($slot, (array) config('app.time_slots'), true)) {
            return ['ok' => false, 'error' => 'Khung giờ không hợp lệ.'];
        }

        if (self::isPastSlot($date, $slot)) {
            return ['ok' => false, 'error' => 'Khung giờ này đã qua, vui lòng chọn giờ khác.'];
        }

        if ($date === $appointment['appointment_date'] && $slot === $appointment['time_slot']) {
            return ['ok' => false, 'error' => 'Bạn đang chọn đúng giờ hẹn hiện tại.'];
        }

        // Giờ MỚI cũng phải cách hiện tại đủ xa như luật đổi/huỷ, không thì khách
        // dùng chức năng đổi lịch để lách hạn: đổi sang 30 phút sau rồi huỷ.
        $newAt = self::startsAt(['appointment_date' => $date, 'time_slot' => $slot]);

        if ($newAt === null || $newAt - time() < self::cutoffSeconds()) {
            return ['ok' => false, 'error' => 'Vui lòng chọn giờ hẹn xa hơn so với hiện tại.'];
        }

        try {
            $changed = Database::execute(
                "UPDATE appointments
                    SET appointment_date = :date,
                        time_slot        = :slot,
                        status           = 'pending'
                  WHERE code = :code AND user_id = :uid
                    AND status IN ('pending', 'confirmed')",
                ['date' => $date, 'slot' => $slot, 'code' => $code, 'uid' => $userId]
            );
        } catch (PDOException $e) {
            // Không còn khoá khung giờ nào để đụng: đổi sang giờ đã có người
            // khác đặt là hợp lệ. Xem khối chú thích đầu file.
            error_log('[BookingModel] Không đổi được lịch hẹn: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không đổi được lịch, vui lòng thử lại.'];
        }

        if ($changed === 0) {
            return ['ok' => false, 'error' => 'Lịch hẹn vừa được cập nhật, vui lòng tải lại trang.'];
        }

        return ['ok' => true];
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
