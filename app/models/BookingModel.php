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
 * Lỗi 1062 trên bảng này nay chỉ còn một nghĩa duy nhất: trùng MÃ lịch hẹn.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÁCH KHÔNG CHỌN GIỜ NỮA — 2026-08-25
 *
 * Bước tiếp theo của chính quyết định trên. Khung giờ đã là nguyện vọng chứ
 * không phải chỗ đã giữ, mà cửa hàng thì vẫn gọi điện xếp lại giờ trong gần
 * như mọi trường hợp — nên hỏi khách một câu mà câu trả lời hầu như không được
 * dùng chỉ làm form dài thêm và tạo kỳ vọng sai ("tôi đã đặt 15:00 rồi").
 *
 * Khách nay chỉ chọn NGÀY, và cột `time_slot` đã bị BỎ HẲN khỏi bảng
 * (2026-08-25-bo-han-cot-khung-gio.sql). Giờ được thống nhất trong cuộc gọi
 * xác nhận và không lưu lại ở đâu cả — cái hẹn nằm trong cuộc gọi đó.
 *
 * Kéo theo: openSlots() và isPastSlot() biến mất. Cả hai chỉ tồn tại để trả
 * lời "khung giờ nào còn đặt được", câu hỏi không còn ai hỏi.
 *
 * CÒN LẠI MỘT LUẬT VỀ THỜI GIAN: không đặt được vào NGÀY đã qua. Trước đây
 * luật này xét tới từng khung giờ — 15h chiều thì khung 08:00 cùng ngày là
 * một cái hẹn không ai giữ được. Không còn giờ để xét thì mốc thô nhất còn
 * đúng là ngày: hẹn cho hôm nay vẫn nhận, vì cửa hàng mở tới 21:00 và người
 * gọi xác nhận sẽ chốt giờ cụ thể.
 *
 * ĐÂY LÀ GIẢ ĐỊNH A5 trong CLAUDE.md, chưa được BA nghiệm thu. Mọi chỗ phụ
 * thuộc vào nó đều gom ở file này và ở BookingController.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class BookingModel extends BaseModel
{
    protected static string $table = 'appointments';

    /**
     * Dịch vụ nhận đặt lịch.
     *
     * Ở ĐÂY chứ không ở BookingController: từ 2026-08-28 khu quản trị cũng tạo
     * được lịch hẹn (bản thiết kế "Lịch hẹn.dc.html"), nên danh sách này có hai
     * nơi đọc. Để nguyên trong controller thì nơi thứ hai phải chép lại, và hai
     * bản chép thì sớm muộn lệch nhau — mà chuỗi này được ghi THẲNG vào
     * `appointments.service_type`, nên lệch nghĩa là dữ liệu cũ và mới không
     * lọc chung được nữa.
     *
     * GIỮ NGUYÊN danh sách của cửa hàng, không lấy 4 dịch vụ trong bản thiết
     * kế: tên dịch vụ là chuyện của cửa hàng, và đổi chữ ở đây là làm lệch mọi
     * lịch hẹn đã có.
     */
    public const SERVICES = [
        'Đo mắt cận/loạn',
        'Tư vấn & Thử gọng',
        'Cắt tròng lấy liền',
        'Bảo hành / Vệ sinh kính',
    ];

    public const STATUSES = [
        'pending'   => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'done'      => 'Đã hoàn tất',
        'cancelled' => 'Đã huỷ',
    ];

    /**
     * Hai trạng thái nhân viên ĐẶT ĐƯỢC bằng ô chọn ở /quan-tri/lich-hen.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VÌ SAO KHÔNG PHẢI CẢ BỐN
     *
     * STATUSES ở trên là VÒNG ĐỜI — bốn chỗ một cái hẹn có thể đứng. Hằng này
     * là VIỆC NHÂN VIÊN LÀM, và hai thứ đó không trùng nhau:
     *
     *   pending    không ai "đặt" nó cả. Nó là chỗ mọi cái hẹn bắt đầu, do
     *              khách bấm đặt lịch mà thành (DEFAULT của cột trong CSDL).
     *              Bày nó trong ô chọn là mời nhân viên đẩy một buổi đã gọi xác
     *              nhận rồi quay ngược về hàng chờ — thao tác không có nghĩa
     *              nghiệp vụ nào, mà lại làm huy hiệu thanh bên đếm sai.
     *   cancelled  là NGÃ RẼ RA KHỎI vòng đời, không phải một bước tiến tới.
     *              Nó đi bằng nút "Huỷ lịch" riêng có hỏi lại — cùng lẽ với nút
     *              "Đã nhận tiền" ở bảng đơn hàng: việc khác loại thì phải khác
     *              dáng, đừng để lẫn vào một danh sách xổ xuống nơi trượt tay
     *              một nấc là huỷ mất buổi hẹn của khách.
     *
     * Cả bốn trạng thái VẪN CÒN NGUYÊN trong dữ liệu và trong dải viên lọc —
     * lọc là để TÌM, không phải để đổi. Bỏ viên "Chờ xác nhận" đi thì không còn
     * cách nào lọc ra đúng những buổi cần gọi, việc chính của trang này mỗi sáng.
     *
     * ĐÂY LÀ LUẬT DO KHÁCH HÀNG CHỐT ngày 2026-08-27, chưa nằm trong SRS.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public const STAFF_STATUSES = ['confirmed', 'done'];

    /**
     * Tạo lịch hẹn.
     *
     * @return array ['ok'=>true,'code'=>...] | ['ok'=>false,'error'=>...]
     */
    public static function create(array $data): array
    {
        // Không cho đặt lịch trong quá khứ. Kiểm ở đây chứ không chỉ ở khâu dựng
        // dải ngày: form không phải đường vào duy nhất, ai gửi thẳng POST cũng
        // phải bị chặn.
        //
        // Chỉ còn xét NGÀY. Trước đây có thêm hai phép kiểm về khung giờ (giờ
        // có trong config không, giờ đã trôi qua chưa) — khách không chọn giờ
        // nữa nên không còn gì để kiểm. Hẹn cho hôm nay vẫn hợp lệ: cửa hàng mở
        // tới 21:00 và người gọi xác nhận sẽ chốt giờ cụ thể.
        $today = date('Y-m-d');
        if ($data['date'] < $today) {
            return ['ok' => false, 'error' => 'Không thể đặt lịch trong quá khứ.'];
        }

        if (!StoreModel::isBookable($data['storeId'])) {
            return ['ok' => false, 'error' => 'Cơ sở không hợp lệ.'];
        }

        $code = generateCode('LH');

        try {
            Database::execute(
                'INSERT INTO appointments
                    (id, code, user_id, store_id, appointment_date,
                     service_type, full_name, phone, note)
                 VALUES
                    (:id, :code, :user_id, :store_id, :appointment_date,
                     :service_type, :full_name, :phone, :note)',
                [
                    'id'               => uuid(),
                    'code'             => $code,
                    'user_id'          => $data['userId'] ?? null,
                    'store_id'         => $data['storeId'],
                    'appointment_date' => $data['date'],
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
     * Lịch này đã QUÁ NGÀY mà chưa ai chốt gì chưa?
     *
     * ─────────────────────────────────────────────────────────────────────
     * MỘT TRẠNG THÁI SUY RA, KHÔNG PHẢI MỘT TRẠNG THÁI LƯU TRONG CSDL
     *
     * Bốn trạng thái trong STATUSES đều do NGƯỜI đặt: khách đặt lịch ('pending'),
     * nhân viên xác nhận ('confirmed'), đo xong ('done'), một trong hai bên huỷ
     * ('cancelled'). Không có ai — và không có tiến trình nền nào — đặt trạng
     * thái "đã quá ngày". Một lịch 'pending' của tháng trước sẽ nằm đó mãi.
     *
     * Nên "quá hạn" tính TẠI CHỖ mỗi lần hiện ra, từ ngày hẹn so với hôm nay.
     * Không ghi vào CSDL vì hai lý do:
     *
     *   · Không có tiến trình nền nào để quét. Ghi lúc khách mở trang thì
     *     lịch của người không bao giờ đăng nhập sẽ không bao giờ được dọn —
     *     dữ liệu đúng hay sai tuỳ vào ai vừa ghé thăm.
     *   · Cửa hàng vẫn phải đánh dấu 'done' được cho một lịch cũ khi đối
     *     chiếu sổ sách. Đè sẵn một trạng thái máy tự đặt là cướp mất quyền đó.
     *
     * 'done' và 'cancelled' KHÔNG tính là quá hạn dù ngày đã qua: chúng đã có
     * kết cục rồi, và "Đã hoàn tất" nói đúng hơn "Đã qua" nhiều.
     * ─────────────────────────────────────────────────────────────────────
     */
    public static function isExpired(array $appointment): bool
    {
        $status = (string) ($appointment['status'] ?? '');

        if ($status !== 'pending' && $status !== 'confirmed') {
            return false;
        }

        /* So theo NGÀY, không theo giờ hẹn — cùng ngưỡng mà countUpcoming()
           dùng, nên con số trên huy hiệu và chữ trên thẻ không bao giờ nói hai
           điều khác nhau. Lịch 09:00 sáng nay lúc 8 giờ tối vẫn là việc của
           hôm nay. */
        return (string) ($appointment['appointment_date'] ?? '') < date('Y-m-d');
    }

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
              ORDER BY a.appointment_date DESC, a.created_at DESC',
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

        $date = (string) ($appointment['appointment_date'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            // Ngày hỏng thì không đoán — để khách gọi tổng đài, đừng cho sửa
            // một hàng mà chính hệ thống không đọc nổi.
            return 'Không đọc được ngày hẹn, vui lòng gọi tổng đài.';
        }

        /*
         * HẠN LÀ HẾT NGÀY HÔM TRƯỚC NGÀY HẸN — không còn là "trước giờ hẹn N giờ".
         *
         * Luật cũ ghép ngày với khung giờ thành một mốc chính xác tới phút rồi
         * trừ đi booking_change_cutoff_hours. Bảng không còn cột giờ nào nên
         * vế đầu không dựng được nữa.
         *
         * Đã cân nhắc lấy 00:00 của ngày hẹn làm mốc rồi giữ nguyên con số 2
         * giờ — bỏ, vì "hạn chót 22:00 đêm hôm trước" là một con số không giải
         * thích được cho khách, và tệ hơn là nó thay đổi ý nghĩa của một tham
         * số cấu hình mà tên vẫn như cũ.
         *
         * Mốc theo ngày thì nói thành một câu: sang ngày hẹn là thôi tự đổi,
         * gọi tổng đài. Đúng với cách cửa hàng làm việc — sáng ra nhân viên
         * chốt danh sách hẹn của ngày rồi mới gọi từng người.
         */
        if ($date <= date('Y-m-d')) {
            return 'Đã tới ngày hẹn, vui lòng gọi tổng đài để đổi hoặc huỷ.';
        }

        return null;
    }

    /**
     * Khách tự huỷ lịch.
     *
     * KHÔNG xoá hàng: cửa hàng cần biết ngày đó từng có người hẹn rồi huỷ — đó
     * là dữ liệu vận hành (khách hay huỷ vào ngày nào, cơ sở nào trống thật),
     * và nó cũng là thứ nhân viên tra lại khi khách gọi hỏi về một mã lịch cũ.
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
     * 2. ĐỔI XONG VỀ 'pending'. Nhân viên xác nhận cho một NGÀY cụ thể; ngày
     *    khác thì lời xác nhận cũ không còn nghĩa gì. Để nguyên 'confirmed' sẽ
     *    thành một lịch "đã xác nhận" mà chưa ai ở cửa hàng nhìn thấy.
     *
     *    Giờ hẹn thống nhất qua điện thoại cho đúng cái ngày CŨ, nên đổi ngày
     *    là lời hẹn giờ đó cũng hết hiệu lực — cửa hàng gọi lại để chốt giờ
     *    mới. Hệ thống không lưu giờ nên không có gì phải dọn ở đây.
     *
     * 3. GIỮ NGUYÊN CƠ SỞ. Đổi cơ sở là đổi gần hết thông tin của lần hẹn (đường
     *    đi, nhân viên, thiết bị) — việc đó nên là đặt lịch mới, không phải sửa.
     *
     * @return array ['ok'=>true] | ['ok'=>false,'error'=>...]
     */
    public static function rescheduleOwned(
        string $code,
        string $userId,
        string $date
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

        if ($date === $appointment['appointment_date']) {
            return ['ok' => false, 'error' => 'Bạn đang chọn đúng ngày hẹn hiện tại.'];
        }

        /* NGÀY MỚI cũng phải qua được chính luật đổi/huỷ, không thì khách dùng
           chức năng đổi lịch để lách hạn: đổi sang hôm nay rồi huỷ. changeBlocker
           chặn 'pending'/'confirmed' theo ngày, nên ở đây chỉ cần hỏi lại đúng
           câu ấy với ngày mới. */
        if ($date <= date('Y-m-d')) {
            return ['ok' => false, 'error' => 'Vui lòng chọn một ngày hẹn từ ngày mai trở đi.'];
        }

        try {
            $changed = Database::execute(
                "UPDATE appointments
                    SET appointment_date = :date,
                        status           = 'pending'
                  WHERE code = :code AND user_id = :uid
                    AND status IN ('pending', 'confirmed')",
                ['date' => $date, 'code' => $code, 'uid' => $userId]
            );
        } catch (PDOException $e) {
            // Không còn khoá nào để đụng: đổi sang ngày đã có người khác hẹn là
            // hợp lệ. Xem khối chú thích đầu file.
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
    public static function withStore(
        string $status = '',
        int $limit = 100,
        string $q = '',
        string $storeId = '',
        int $offset = 0,
        ?array $phamViCoSo = null
    ): array {
        /* Ba bộ lọc CỘNG ĐƯỢC với nhau, nên gom điều kiện vào mảng rồi mới
           ghép — thêm bộ lọc thứ tư sau này chỉ là đẩy thêm một phần tử.

           $q và $storeId thêm 2026-08-28 cho bản thiết kế "Lịch hẹn.dc.html";
           để mặc định rỗng nên mọi nơi gọi cũ vẫn chạy y như trước.

           $offset thêm 2026-08-29 cùng lúc với phân trang. Cũng để mặc định 0
           vì trang Tổng quan gọi hàm này chỉ để lấy vài buổi hẹn gần nhất —
           nó không phân trang và không nên phải biết tới chuyện đó.

           LIMIT/OFFSET ghép thẳng vào câu chứ KHÔNG qua tham số ràng buộc: dự
           án tắt EMULATE_PREPARES, mà MySQL không nhận tham số ở vị trí LIMIT
           khi dùng prepared statement thật. An toàn vì cả hai đã qua max() và
           là số nguyên do controller tính ra — cùng cách làm với
           InventoryAdminController::index. */
        [$where, $params] = self::locWithStore($status, $q, $storeId, $phamViCoSo);

        return Database::fetchAll(
            "SELECT a.*, s.name AS store_name, s.code AS store_code
               FROM appointments a
               JOIN stores s ON s.id = a.store_id
               {$where}
              ORDER BY a.appointment_date DESC, a.created_at DESC
              LIMIT " . max(1, $limit) . " OFFSET " . max(0, $offset),
            $params
        );
    }

    /**
     * Đếm lịch hẹn theo từng trạng thái, TRONG PHẠM VI hai bộ lọc kia.
     *
     * Vì sao không đếm toàn bảng cho gọn: dải viên lọc đứng ngay trên danh
     * sách, nên "Chờ xác nhận 10" mà bên dưới chỉ có 2 dòng thì con số ấy nói
     * về một danh sách người dùng không nhìn thấy. Đếm cùng phạm vi thì hai
     * thứ luôn khớp nhau.
     *
     * Trả về mảng có ĐỦ mọi khoá trạng thái (giá trị 0 nếu không có dòng nào),
     * cộng khoá '' là tổng — để view khỏi phải tự phòng khoá thiếu.
     */
    public static function statusCounts(
        string $q = '',
        string $storeId = '',
        ?array $phamViCoSo = null
    ): array {
        /* Đếm cũng phải theo phạm vi, không chỉ danh sách. Bỏ sót chỗ này thì
           các tab trạng thái hiện "Chờ xác nhận (12)" trong khi bấm vào chỉ
           thấy 4 — và con số 12 kia chính là thứ vừa rò rỉ ra ngoài. */
        [$where, $params] = self::locWithStore('', $q, $storeId, $phamViCoSo);

        $rows   = Database::fetchAll(
            "SELECT a.status, COUNT(*) AS n
               FROM appointments a
               JOIN stores s ON s.id = a.store_id
               {$where}
              GROUP BY a.status",
            $params
        );

        $counts = ['' => 0];

        foreach (array_keys(self::STATUSES) as $key) {
            $counts[$key] = 0;
        }

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
            $counts['']            += (int) $row['n'];
        }

        return $counts;
    }

    /**
     * Mệnh đề WHERE + tham số dùng chung cho withStore() và statusCounts().
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private static function locWithStore(
        string $status,
        string $q,
        string $storeId,
        ?array $phamViCoSo = null
    ): array {
        $dieuKien = [];
        $params   = [];

        /*
         * HAI THỨ CÙNG NÓI VỀ CƠ SỞ, ĐỪNG NHẦM CHÚNG VỚI NHAU.
         *
         *   $phamViCoSo   RÀNG BUỘC QUYỀN. Máy chủ áp, người dùng không gỡ
         *                 được, đọc từ bảng phân công của chính họ (SNFR-07b).
         *   $storeId      BỘ LỌC. Người dùng chọn trong ô "Cơ sở" trên màn
         *                 hình, bỏ chọn là thôi lọc.
         *
         * Cả hai cùng áp: nhân viên chỉ thuộc Long Biên mà chọn lọc Tây Hồ thì
         * ra danh sách rỗng, đúng như phải thế. Ô lọc KHÔNG nới được phạm vi.
         *
         * Trước lần sửa này chỉ có $storeId, và đó là toàn bộ lỗ hổng: một ô
         * lọc bỏ trống nghĩa là thấy lịch hẹn của cả hai cơ sở.
         */
        [$loc, $locParams] = StaffStoreModel::menhDe($phamViCoSo, 'a.store_id', 'pv');

        if ($loc !== null) {
            $dieuKien[] = $loc;
            $params    += $locParams;
        }

        if ($status !== '') {
            $dieuKien[]       = 'a.status = :status';
            $params['status'] = $status;
        }

        if ($storeId !== '') {
            $dieuKien[]      = 'a.store_id = :co_so';
            $params['co_so'] = $storeId;
        }

        if ($q !== '') {
            /* Ba cột người ta thật sự gõ vào ô tìm: mã lịch đọc qua điện
               thoại, tên khách, số điện thoại. addcslashes để dấu % hay _
               trong từ khoá không thành ký tự đại diện của LIKE. */
            $dieuKien[] = '(a.code LIKE :tim_code OR a.full_name LIKE :tim_ten OR a.phone LIKE :tim_sdt)';
            $needle     = '%' . addcslashes($q, '%_\\') . '%';
            $params += ['tim_code' => $needle, 'tim_ten' => $needle, 'tim_sdt' => $needle];
        }

        return [$dieuKien !== [] ? 'WHERE ' . implode(' AND ', $dieuKien) : '', $params];
    }
}
