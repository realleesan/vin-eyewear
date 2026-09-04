<?php

/**
 * app/models/LoginAttemptModel.php
 *
 * Đếm số lần đăng nhập hỏng và khoá tạm thời — SNFR-06.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐẶC TẢ
 *
 * SNFR-06: "tài khoản tạm thời bị khóa 15 phút nếu nhập sai mật khẩu quá 5 lần
 * liên tiếp". Trước bản này hệ thống không đếm lần sai ở bất cứ đâu, nên ô đăng
 * nhập — cả của khách lẫn của cổng /quan-tri — là một mục tiêu dò mật khẩu
 * không có trần.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO ĐẾM THEO CHUỖI ĐỊNH DANH CHỨ KHÔNG THEO CỘT TRONG BẢNG `users`
 *
 * Đây là điểm dễ làm sai nhất, và cũng là lý do file này tồn tại thay vì hai
 * cột thêm vào `users`.
 *
 * Đếm trên `users` thì chỉ đếm được cho tài khoản CÓ THẬT. Kẻ dò gõ sai 5 lần
 * vào một email bất kỳ, thử thêm lần nữa rồi đọc câu trả lời: "tạm khoá" nghĩa
 * là email đó có tài khoản ở đây, "sai thông tin" nghĩa là không. Cái khoá
 * dựng lên để chặn dò
 * mật khẩu lại tặng không một máy tra cứu danh sách khách hàng — đúng thứ mà
 * UserModel::attempt() và AdminAuthController::login() đã cẩn thận tránh bằng
 * cách dùng chung một câu báo lỗi cho mọi ca hỏng.
 *
 * Đếm theo chuỗi định danh thì mọi định danh đều đếm được, có tài khoản hay
 * không. Gõ sai 5 lần vào một email không tồn tại cũng bị khoá y hệt, nên câu
 * trả lời của hệ thống không nói lên điều gì về việc tài khoản có tồn tại.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO BĂM CHUỖI ĐỊNH DANH
 *
 * Bảng này sẽ chứa email và số điện thoại của những người GÕ NHẦM, tức phần
 * lớn là khách hàng thật. Cất nguyên văn là dựng thêm một bản danh sách liên
 * hệ nữa nằm ngoài `profiles`, với đúng một công dụng là đếm — không đáng.
 * Băm SHA-256 thì đếm vẫn chạy (so khớp bằng nhau là đủ) mà bảng đọc trộm
 * được cũng không ra ai.
 *
 * Không cần muối: mục đích không phải chống dò ngược mà là không lưu thừa dữ
 * liệu cá nhân, và một cột khoá chính có muối thì không tra cứu được.
 */
class LoginAttemptModel extends BaseModel
{
    protected static string $table = 'login_attempts';

    /** Sai bao nhiêu lần liên tiếp thì khoá — SNFR-06. */
    public const TOI_DA = 5;

    /** Khoá bao lâu (giây) — SNFR-06 chốt 15 phút. */
    public const KHOA_TRONG = 900;

    /**
     * Tính năng này chỉ chạy khi bảng đã có.
     *
     * Cùng lý do đã ghi ở RememberModel::available(): cho phép đẩy mã nguồn
     * lên trước, chạy migration sau, mà site không lỗi ở khoảng giữa. Ở đây
     * còn quan trọng hơn — bảng chưa có mà ném lỗi thì KHÔNG AI đăng nhập
     * được, kể cả người vào chạy migration.
     */
    public static function available(): bool
    {
        return Database::tableExists(static::$table);
    }

    /**
     * Chuẩn hoá rồi băm chuỗi định danh.
     *
     * Hạ chữ thường và cắt khoảng trắng để "An@Vin.vn" và " an@vin.vn " đếm
     * chung một ô — nếu không thì đổi hoa thường là xoá sạch bộ đếm.
     *
     * strtolower() TRẦN, KHÔNG PHẢI mb_strtolower(): máy chủ của cửa hàng
     * không nạp extension mbstring, gọi hàm mb_* là lỗi 500 (xem ghi chú ở
     * core/helpers.php). Ở đây còn nguy hiểm hơn chỗ khác vì mọi lời gọi tới
     * hàm này đều nằm trong `catch (Throwable)`, mà Error cũng là Throwable —
     * lỗi sẽ bị nuốt sạch và cái khoá 15 phút lặng lẽ không bao giờ chạy.
     * strtolower() trần là đủ: định danh chỉ có thể là email hoặc số điện
     * thoại, cả hai đều ASCII.
     *
     * ─────────────────────────────────────────────────────────────────────
     * CHUẨN HOÁ SỐ ĐIỆN THOẠI GIỐNG HỆT findByLogin()
     *
     * Bản đầu cố ý không gọi normalizePhone(), vì sợ hai chuỗi khác nhau dồn
     * về một ô làm bộ đếm nhảy nhanh hơn thực tế. Lập luận đó SAI, và sai theo
     * hướng nguy hiểm.
     *
     * UserModel::findByLogin() chuẩn hoá số trước khi tra, nên 0912345678,
     * +84912345678, "091 234 5678" và 0912.345.678 đều mở CÙNG một tài khoản.
     * Đếm theo chuỗi thô thì mỗi cách gõ là một ô riêng với đủ 5 lượt mới — mà
     * số cách gõ là vô hạn (thêm dấu cách hay dấu chấm ở bất kỳ đâu). Trần 5
     * lần trở thành không có trần, đúng với cách đăng nhập phổ biến nhất của
     * khách.
     *
     * "Bộ đếm nhảy nhanh hơn" chính là điều PHẢI xảy ra: 5 lần sai vào cùng
     * một tài khoản thì phải đếm là 5, gõ số kiểu nào cũng vậy.
     *
     * Chuỗi không phải số điện thoại thì giữ nguyên văn — email, và cả chuỗi
     * rác mà người dò gõ vào, vẫn phải đếm được.
     * ─────────────────────────────────────────────────────────────────────
     */
    private static function khoa(string $login): string
    {
        $login = trim($login);

        if (looksLikePhone($login) && ($sdt = normalizePhone($login)) !== null) {
            $login = $sdt;
        }

        return hash('sha256', strtolower($login));
    }

    /**
     * Còn bị khoá bao nhiêu giây? Trả 0 nếu đang được phép thử.
     *
     * Trả về SỐ GIÂY chứ không phải true/false để nơi gọi dựng được câu "vui
     * lòng thử lại sau N phút" — báo "đã bị khoá" mà không nói bao lâu thì
     * người gõ nhầm mật khẩu thật không biết nên chờ hay nên gọi cửa hàng.
     */
    public static function conKhoa(string $login): int
    {
        if (!self::available()) {
            return 0;
        }

        try {
            $den = Database::fetchValue(
                'SELECT locked_until FROM login_attempts WHERE login_key = :k',
                ['k' => self::khoa($login)]
            );
        } catch (Throwable) {
            // Bảng hỏng thì cho qua, KHÔNG chặn đăng nhập. Một cái khoá gãy
            // không được phép biến thành cửa đóng với cả cửa hàng.
            return 0;
        }

        if ($den === null) {
            return 0;
        }

        $conLai = strtotime((string) $den) - time();

        return $conLai > 0 ? $conLai : 0;
    }

    /**
     * Ghi nhận một lần đăng nhập hỏng. Đủ TOI_DA lần thì đặt khoá.
     *
     * Một câu UPSERT chứ không phải SELECT rồi INSERT/UPDATE: hai lượt gõ gần
     * nhau (người dò chạy song song) sẽ chèn trùng khoá chính và một trong hai
     * lần đếm rơi mất.
     *
     * `fails` được đặt lại về 0 ngay khi khoá: hết 15 phút là người ta có lại
     * đủ 5 lần thử, không phải bị khoá lại ngay ở lần sai đầu tiên.
     *
     * THỨ TỰ HAI DÒNG GÁN LÀ CÓ CHỦ Ý, ĐỪNG ĐỔI. MySQL chạy các phép gán
     * trong ON DUPLICATE KEY UPDATE lần lượt từ trên xuống, và dòng dưới đọc
     * được giá trị MỚI mà dòng trên vừa ghi. Đặt `fails` lên trước thì khi tới
     * lượt `locked_until`, `fails` đã bị gán về 0 — điều kiện `fails + 1 >= 5`
     * thành sai, và cái khoá không bao giờ được đặt. Bộ đếm chạy, khoá không.
     */
    public static function ghiNhanHong(string $login): void
    {
        if (!self::available()) {
            return;
        }

        try {
            Database::execute(
                'INSERT INTO login_attempts (login_key, fails, locked_until, updated_at)
                 VALUES (:k, 1, NULL, NOW())
                 ON DUPLICATE KEY UPDATE
                     locked_until = IF(fails + 1 >= :max, DATE_ADD(NOW(), INTERVAL :giay SECOND), locked_until),
                     fails        = IF(fails + 1 >= :max2, 0, fails + 1),
                     updated_at   = NOW()',
                [
                    'k'    => self::khoa($login),
                    'max'  => self::TOI_DA,
                    'max2' => self::TOI_DA,
                    'giay' => self::KHOA_TRONG,
                ]
            );
        } catch (Throwable) {
            // Cùng lý do như conKhoa(): bộ đếm hỏng thì thôi, không chặn.
        }
    }

    /**
     * Xoá bộ đếm sau một lần đăng nhập ĐÚNG.
     *
     * "5 lần sai LIÊN TIẾP" của SNFR-06 nằm ở chữ liên tiếp: người dùng thật
     * gõ nhầm 4 lần rồi vào được thì lần nhầm thứ 5 vào tuần sau không được
     * cộng dồn vào bốn lần cũ.
     */
    public static function xoa(string $login): void
    {
        if (!self::available()) {
            return;
        }

        try {
            Database::execute(
                'DELETE FROM login_attempts WHERE login_key = :k',
                ['k' => self::khoa($login)]
            );
        } catch (Throwable) {
        }
    }

    /**
     * Trong danh sách định danh này, cái nào đang bị khoá thì còn bao nhiêu giây?
     *
     * VÌ SAO NHẬN MỘT DANH SÁCH: bộ đếm khoá theo CHUỖI ĐỊNH DANH đã băm, chứ
     * không theo id tài khoản — đó là chủ ý, để kẻ dò gõ vào một số không tồn
     * tại cũng bị khoá y hệt (xem khối chú thích đầu file). Hệ quả là một
     * người có hai đường vào — số điện thoại và email — thì có HAI bộ đếm
     * riêng, và màn quản trị muốn trả lời "tài khoản này có đang bị khoá
     * không" thì phải hỏi cả hai.
     *
     * Trả về số giây LỚN NHẤT trong nhóm: người ta cần biết còn phải chờ bao
     * lâu nữa mới vào được, mà vào được nghĩa là mọi đường đều đã mở.
     */
    public static function conKhoaBatKy(array $logins): int
    {
        $lau = 0;

        foreach ($logins as $login) {
            $login = trim((string) $login);

            if ($login === '') {
                continue;
            }

            $lau = max($lau, self::conKhoa($login));
        }

        return $lau;
    }

    /**
     * Mở khoá ngay lập tức cho một nhóm định danh — Quyết định Q13, 04/09/2026.
     *
     * SNFR-06 khoá 15 phút sau 5 lần sai. Điều khoản ấy có một mặt trái mà BA
     * đã chấp nhận có điều kiện: bộ đếm theo tài khoản nghĩa là NGƯỜI NGOÀI
     * cũng khoá được tài khoản của nhân viên, chỉ bằng cách gõ sai năm lần.
     * BA chọn giữ nguyên cách đếm (đơn giản, không lộ việc tài khoản có tồn
     * tại hay không) và bù lại bằng đường mở khoá tay ngay trong khu quản trị.
     *
     * Dùng lại xoa() thay vì viết câu DELETE mới: xoa() đã lo phần chuẩn hoá
     * số điện thoại trước khi băm, và đó chính là chỗ dễ sai nhất — mở khoá
     * cho "0912 345 678" mà bộ đếm nằm ở "0912345678" thì nút bấm không làm gì
     * cả, và không ai biết vì nó cũng không báo lỗi.
     */
    public static function moKhoa(array $logins): void
    {
        foreach ($logins as $login) {
            $login = trim((string) $login);

            if ($login !== '') {
                self::xoa($login);
            }
        }
    }

    /**
     * Dọn các dòng đã hết hạn khoá và lâu không đụng tới.
     *
     * Gọi thưa thớt từ nơi ghi nhận hỏng (xem UserModel::attempt), theo đúng
     * nếp của RememberModel::purgeExpired: hosting không có cron, nên việc dọn
     * phải ăn theo lượt truy cập.
     */
    public static function donCu(): void
    {
        if (!self::available()) {
            return;
        }

        try {
            Database::execute(
                'DELETE FROM login_attempts
                  WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
                    AND (locked_until IS NULL OR locked_until < NOW())'
            );
        } catch (Throwable) {
        }
    }
}
