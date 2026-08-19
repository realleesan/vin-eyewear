<?php

/**
 * NewsletterModel — danh sách email nhận tin (S20).
 *
 * Ở bước này chỉ LƯU LẠI địa chỉ, chưa nối với dịch vụ gửi thư hàng loạt
 * (Mailchimp/Brevo…). Bảng `newsletter_subscribers` chính là danh sách để
 * xuất ra khi nối dịch vụ — làm ngược lại, tức gửi thẳng sang bên thứ ba mà
 * không giữ bản của mình, thì đổi nhà cung cấp là mất trắng danh sách.
 */

class NewsletterModel extends BaseModel
{
    protected static string $table = 'newsletter_subscribers';

    /** Nơi khách bấm đăng ký — cột `source`, dùng để biết vị trí nào hiệu quả. */
    /**
     * Nơi khách bấm đăng ký. Giá trị lạ bị ép về 'home' (xem subscribe), nên
     * THÊM Ô ĐĂNG KÝ Ở TRANG MỚI THÌ PHẢI THÊM MỘT DÒNG VÀO ĐÂY — không thì
     * cột `source` ghi sai chỗ và mất hẳn ý nghĩa thống kê.
     */
    public const SOURCES = ['home', 'footer', 'su-kien'];

    /**
     * Ghi một địa chỉ vào danh sách.
     *
     * Đăng ký lại bằng đúng email cũ KHÔNG phải là lỗi của khách: trả về
     * ['ok' => true, 'already' => true] để view báo "địa chỉ này đã trong danh
     * sách" thay vì ném lỗi hệ thống ra màn hình.
     *
     * @return array ['ok'=>true,'already'=>bool] | ['ok'=>false,'error'=>string]
     */
    public static function subscribe(string $email, string $source = 'home'): array
    {
        $email = trim($email);

        if ($email === '') {
            return ['ok' => false, 'error' => 'Vui lòng nhập email.'];
        }

        // Kiểm ở SERVER chứ không chỉ dựa vào type="email" của form: thuộc tính
        // đó chỉ là gợi ý cho trình duyệt, ai gửi thẳng POST cũng bỏ qua được.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email không hợp lệ.'];
        }

        // 255 là độ rộng cột; dài hơn thì MySQL cắt bớt trong chế độ lỏng, và
        // địa chỉ lưu được sẽ không còn là địa chỉ khách nhập.
        if (strlen($email) > 255) {
            return ['ok' => false, 'error' => 'Email quá dài.'];
        }

        if (!in_array($source, self::SOURCES, true)) {
            $source = 'home';
        }

        /*
         * Hạ về chữ thường để khoá UNIQUE không nhận "An@Gmail.com" và
         * "an@gmail.com" là hai người khác nhau — chữ hoa/thường trong phần
         * domain không có ý nghĩa, và phần lớn nhà cung cấp cũng bỏ qua ở
         * phần tên.
         *
         * strtolower() chứ không phải mb_strtolower(): dự án này không phụ
         * thuộc extension mbstring (xem utf8Length() trong core/helpers.php,
         * máy dev đang thiếu mbstring). Với email thì không mất gì —
         * FILTER_VALIDATE_EMAIL ở trên đã loại mọi địa chỉ ngoài ASCII.
         */
        $email = strtolower($email);

        /*
         * CẢ phần đọc lẫn phần ghi nằm trong try.
         *
         * Chưa chạy database/migrations/2026-08-15-dang-ky-nhan-tin.sql thì
         * ngay firstWhere() đã ném lỗi "table doesn't exist". Để nó ngoài try
         * thì lỗi bay thẳng ra thành trang 500 — khách gửi form và mất luôn
         * trang chủ, thay vì thấy một dòng báo lỗi.
         */
        try {
            $existing = static::firstWhere(['email' => $email]);

            if ($existing !== null) {
                // Từng huỷ nhận tin rồi đăng ký lại -> bật lại, không tạo dòng mới
                if ($existing['unsubscribed_at'] !== null) {
                    static::update($existing['id'], ['unsubscribed_at' => null]);

                    return ['ok' => true, 'already' => false];
                }

                return ['ok' => true, 'already' => true];
            }

            static::insert(['email' => $email, 'source' => $source]);
        } catch (Throwable $e) {
            /*
             * Hai người bấm cùng lúc thì cả hai đều thấy firstWhere() trả về
             * null, người sau đâm vào khoá UNIQUE. Đó vẫn là đăng ký thành
             * công dưới góc nhìn của khách, không phải sự cố.
             *
             * Phép kiểm này cũng phải bọc try: nếu lỗi ban đầu là do thiếu
             * bảng thì exists() sẽ ném đúng lỗi đó lần nữa.
             */
            try {
                if (static::exists(['email' => $email])) {
                    return ['ok' => true, 'already' => true];
                }
            } catch (Throwable $ignored) {
                // Bỏ qua: lỗi thật đã nằm ở $e, ghi log ngay dưới
            }

            error_log('[NewsletterModel] Không lưu được email nhận tin: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không đăng ký được, vui lòng thử lại.'];
        }

        return ['ok' => true, 'already' => false];
    }
}
