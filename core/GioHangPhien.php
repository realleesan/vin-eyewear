<?php

/**
 * GioHangPhien — mỗi tài khoản một giỏ, trong cùng một phiên trình duyệt.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VẤN ĐỀ NÓ SINH RA ĐỂ GIẢI
 *
 * Giỏ hàng nằm ở $_SESSION['cart'] (xem đầu CartController để biết vì sao
 * không nằm trong DB). Phiên thì thuộc về TRÌNH DUYỆT, không thuộc về tài
 * khoản — nên trước file này, một ô $_SESSION['cart'] duy nhất được mọi người
 * ngồi trước máy đó dùng chung:
 *
 *   A đăng nhập, bỏ 3 món vào giỏ, đăng xuất  →  logout() cố tình giữ giỏ lại
 *   B đăng nhập trên cùng trình duyệt         →  B thấy nguyên 3 món của A
 *
 * Đợt 7 đã bịt được một nhánh của chuyện này (xoá `rx_ho_so` khỏi các dòng khi
 * đăng xuất, để đơn của B không trỏ vào hồ sơ Y TẾ của A). Nhưng đó là bịt
 * triệu chứng: bản thân cái giỏ vẫn đổi chủ. File này cắt ở gốc.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CÁCH LÀM: MỘT NGĂN CHO MỖI CHỦ, GIỎ ĐANG HOẠT ĐỘNG VẪN Ở CHỖ CŨ
 *
 *   $_SESSION['cart']         giỏ ĐANG HOẠT ĐỘNG — không đổi chỗ, không đổi
 *                             hình dạng, nên 40-odd chỗ đọc/ghi nó trong
 *                             CartController, OrderController và các view
 *                             không phải sửa một dòng nào.
 *   $_SESSION['gio_chu']      giỏ đang hoạt động là của ai: 'khach' | 'user:<id>'
 *   $_SESSION['gio_kho']      ngăn của những chủ KHÁC, dạng
 *                             [ 'user:42' => ['cart' => [...], 'voucher' => 'X'] ]
 *
 * Chủ đổi thì tráo: cất giỏ đang hoạt động vào ngăn của chủ cũ, lấy ngăn của
 * chủ mới ra. Chủ của giỏ đang hoạt động KHÔNG BAO GIỜ có ngăn riêng cùng lúc
 * — nhấc ra là xoá ngăn — vì hai bản sao của cùng một giỏ thì sớm muộn cũng
 * lệch nhau, và lúc đó không ai biết bản nào thật.
 *
 * Mã giảm giá (`cart_voucher`) đi theo giỏ: mã A áp cho giỏ A không có việc gì
 * phải giảm giá cho giỏ B. `cart_seq` thì KHÔNG — nó là bộ đếm chung, giữ ở
 * ngoài để số thứ tự thêm-vào không bao giờ trùng giữa các ngăn.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÁCH VÃNG LAI → ĐĂNG NHẬP LÀ GỘP, KHÔNG PHẢI TRÁO
 *
 * Chiều này là ngoại lệ có chủ ý: người ta chọn hàng rồi mới đăng nhập để
 * thanh toán là luồng mua hàng bình thường nhất trên đời. Tráo ở đây nghĩa là
 * bấm "Thanh toán" xong thì giỏ trống.
 *
 * Gộp xong thì NGĂN VÃNG LAI BỊ XOÁ. Đây là dòng giữ cho lỗ hổng cũ không mở
 * lại bằng cửa sau: nếu ngăn vãng lai còn đó, nó sẽ được gộp tiếp vào tài
 * khoản kế tiếp đăng nhập trên máy này — đúng cảnh "giỏ dùng chung".
 * ─────────────────────────────────────────────────────────────────────────────
 */
class GioHangPhien
{
    /** Chủ của giỏ khi không có ai đăng nhập. */
    public const KHACH_VANG_LAI = 'khach';

    private const O_CHU = 'gio_chu';
    private const O_KHO = 'gio_kho';

    /**
     * Đặt giỏ đang hoạt động về đúng chủ $userId (null = khách vãng lai).
     *
     * Gọi từ AuthMiddleware::customerId(), tức là ở MỌI đường mà danh tính có
     * thể đổi: đăng nhập, đăng xuất, phiên hết hạn sau 24 giờ, dựng lại phiên
     * từ cookie ghi nhớ, tài khoản bị khoá giữa chừng. Đặt một chỗ ở đó thay
     * vì rắc lời gọi khắp nơi — mọi thay đổi danh tính đều đi qua đúng ba hàm
     * (customerId, login, logout) và cả ba đều chạm tới file này.
     *
     * RẺ khi không có gì để làm, và đó là điều kiện bắt buộc: một trang gọi
     * customerId() hàng chục lần. Chủ không đổi thì hàm này không ghi gì cả.
     */
    public static function theoChu(?string $userId): void
    {
        $moi = self::khoa($userId);
        $cu  = self::chuHienTai();

        if ($moi === $cu) {
            return;
        }

        self::catVao($cu);

        /* HỘP THOẠI MUA HÀNG ĐANG DỞ THÌ BỎ, không tráo theo giỏ.

           $_SESSION['_buy_intent'] là một lượt "Mua ngay" đang đi giữa chừng
           và nó mang theo `rx` / `rx_ho_so` — số đo và hồ sơ đo mắt của người
           đang thao tác. Nó chỉ có nghĩa trong đúng lượt bấm ấy, nên đổi chủ
           là bỏ: giữ lại thì người kế tiếp bấm "Mua ngay" có thể rơi vào giữa
           luồng của người trước, với số đo của người trước điền sẵn. */
        unset($_SESSION['_buy_intent']);

        if ($moi === self::KHACH_VANG_LAI) {
            // Tài khoản → vãng lai. Giỏ ĐI THEO TÀI KHOẢN vào ngăn của nó;
            // người đang ngồi trước máy nhận lại đúng ngăn vãng lai của mình,
            // thường là rỗng. Đây chính là chỗ bịt lỗ hổng.
            self::nhacRa($moi);

            return;
        }

        // Vãng lai (hoặc một tài khoản khác) → tài khoản: gộp, xem ghi chú đầu file.
        self::gopVaoNgan($moi);
    }

    /**
     * Trước khi huỷ phiên lúc đăng xuất: cất giỏ đang hoạt động vào ngăn của
     * chủ nó, rồi trả về những thứ cần sống sót qua session_destroy().
     *
     * Tách làm hai nửa vì huyPhien() xoá sạch $_SESSION — thứ gì muốn còn thì
     * phải nằm trong một biến PHP thường trong lúc đó.
     *
     * NGĂN CỦA TÀI KHOẢN VỪA ĐĂNG XUẤT ĐƯỢC GIỮ LẠI, và điều đó an toàn: ngăn
     * chỉ được nhấc ra khi có người đăng nhập lại đúng tài khoản ấy. Người kế
     * tiếp ngồi vào máy là khách vãng lai, và khách vãng lai chỉ thấy ngăn
     * vãng lai. Nhờ vậy A đăng xuất rồi đăng nhập lại vẫn còn nguyên giỏ —
     * đúng thứ câu chú thích cũ ở logout() muốn, nhưng không kèm cái giá là
     * đưa giỏ ấy cho người lạ.
     */
    public static function truocKhiHuyPhien(): array
    {
        self::catVao(self::chuHienTai());

        return [
            'kho' => $_SESSION[self::O_KHO] ?? [],
            'seq' => (int) ($_SESSION['cart_seq'] ?? 0),
        ];
    }

    /** Nửa sau của cặp trên: dựng lại kho trong phiên mới, mở ngăn vãng lai. */
    public static function sauKhiHuyPhien(array $luu): void
    {
        $_SESSION[self::O_KHO] = $luu['kho'] ?? [];
        $_SESSION['cart_seq']  = (int) ($luu['seq'] ?? 0);

        self::nhacRa(self::KHACH_VANG_LAI);
    }

    /** Chủ của giỏ đang hoạt động. Phiên chưa từng có giỏ thì là khách vãng lai. */
    public static function chuHienTai(): string
    {
        $chu = $_SESSION[self::O_CHU] ?? null;

        return is_string($chu) && $chu !== '' ? $chu : self::KHACH_VANG_LAI;
    }

    // ========================================================================
    // BÊN TRONG
    // ========================================================================

    private static function khoa(?string $userId): string
    {
        return ($userId === null || $userId === '')
            ? self::KHACH_VANG_LAI
            : 'user:' . $userId;
    }

    /**
     * Cất giỏ đang hoạt động vào ngăn của $chu.
     *
     * Ngăn RỖNG thì xoá hẳn thay vì lưu một mảng trống: một máy tính chung có
     * thể thấy hàng chục tài khoản trong đời một phiên, và không có lý do gì
     * để phiên phình ra vì những người chỉ đăng nhập xem đơn rồi đi.
     */
    private static function catVao(string $chu): void
    {
        $gio     = $_SESSION['cart'] ?? [];
        $voucher = (string) ($_SESSION['cart_voucher'] ?? '');

        if (!is_array($gio) || $gio === []) {
            unset($_SESSION[self::O_KHO][$chu]);

            return;
        }

        /* NGĂN VÃNG LAI KHÔNG BAO GIỜ GIỮ MÃ HỒ SƠ ĐO MẮT — giữ nguyên bảo đảm
           của đợt 7, nhưng đặt ở chỗ đúng hơn.

           Sau file này thì giỏ của một tài khoản không còn chảy sang người
           khác nữa, nên về lý thì không cần dòng này. Vẫn giữ vì nó rẻ và vì
           `rx_ho_so` trỏ vào một bản ghi Y TẾ: chỉ cần một đường nào đó sau
           này lỡ đổ giỏ của người đăng nhập vào ngăn vãng lai là hồ sơ của họ
           đi theo. Số đo (`rx`) thì ở lại — nó là thứ hiện trên màn hình và
           sửa được; thứ phải cắt là LIÊN KẾT tới sổ y tế. */
        if ($chu === self::KHACH_VANG_LAI) {
            foreach ($gio as $khoaDong => $dong) {
                if (is_array($dong) && array_key_exists('rx_ho_so', $dong)) {
                    $gio[$khoaDong]['rx_ho_so'] = null;
                }
            }
        }

        $_SESSION[self::O_KHO][$chu] = ['cart' => $gio, 'voucher' => $voucher];
    }

    /**
     * Nhấc ngăn của $chu ra làm giỏ đang hoạt động (ngăn không có = giỏ rỗng).
     *
     * Xoá ngăn ngay sau khi nhấc: xem "một bản sao duy nhất" ở đầu file.
     */
    private static function nhacRa(string $chu): void
    {
        $ngan    = $_SESSION[self::O_KHO][$chu] ?? null;
        $gio     = is_array($ngan['cart'] ?? null) ? $ngan['cart'] : [];
        $voucher = (string) ($ngan['voucher'] ?? '');

        $_SESSION['cart'] = $gio;

        if ($voucher !== '') {
            $_SESSION['cart_voucher'] = $voucher;
        } else {
            unset($_SESSION['cart_voucher']);
        }

        unset($_SESSION[self::O_KHO][$chu]);
        $_SESSION[self::O_CHU] = $chu;
    }

    /**
     * Gộp ngăn vãng lai vào ngăn của $chu, rồi mở ngăn đó ra.
     *
     * DÒNG TRÙNG KHOÁ THÌ LẤY SỐ LƯỢNG LỚN HƠN, không cộng dồn. Hai lý do:
     *
     *   · Cộng dồn làm khách giật mình. Họ để 2 chiếc trong giỏ từ hôm qua,
     *     hôm nay chưa đăng nhập lại chọn thêm 2 chiếc y hệt, đăng nhập xong
     *     thấy 4 — không ai định mua 4.
     *   · Mỗi con số ở đây ĐÃ từng qua kiểm tồn kho và trần số lượng lúc được
     *     thêm vào. Lấy cái lớn hơn thì kết quả vẫn là một giá trị từng hợp
     *     lệ; cộng dồn thì có thể vọt qua cả kho lẫn trần ngay tại đây.
     *
     * Tồn kho vẫn được soát lại ở lines() và một lần nữa lúc đặt hàng, nên đây
     * không phải chốt chặn cuối — nhưng không có nghĩa là được phép tạo ra một
     * con số sai ngay từ đầu.
     */
    private static function gopVaoNgan(string $chu): void
    {
        $nganKhach = $_SESSION[self::O_KHO][self::KHACH_VANG_LAI] ?? null;
        $gioKhach  = is_array($nganKhach['cart'] ?? null) ? $nganKhach['cart'] : [];
        $maKhach   = (string) ($nganKhach['voucher'] ?? '');

        // Đã nhận rồi thì không để lại cho người đăng nhập sau — xem đầu file.
        unset($_SESSION[self::O_KHO][self::KHACH_VANG_LAI]);

        self::nhacRa($chu);

        foreach ($gioKhach as $khoaDong => $dong) {
            if (!is_array($dong)) {
                continue;
            }

            $daCo = $_SESSION['cart'][$khoaDong] ?? null;

            if (!is_array($daCo)) {
                $_SESSION['cart'][$khoaDong] = $dong;

                continue;
            }

            $_SESSION['cart'][$khoaDong]['quantity'] = max(
                (int) ($daCo['quantity'] ?? 0),
                (int) ($dong['quantity'] ?? 0)
            );

            /* Vừa chạm tới thì nhảy lên đầu danh sách "mới nhất" — cùng quy
               ước với CartController::add(). Bộ đếm là chung cho cả phiên nên
               hai số này so được với nhau. */
            $_SESSION['cart'][$khoaDong]['added_seq'] = max(
                (int) ($daCo['added_seq'] ?? 0),
                (int) ($dong['added_seq'] ?? 0)
            );

            // Món khách vừa chủ động chọn thì để tick sẵn, như lúc thêm vào giỏ.
            if (!empty($dong['selected'])) {
                $_SESSION['cart'][$khoaDong]['selected'] = true;
            }
        }

        /* Mã của tài khoản thắng nếu có: nó được áp cho chính giỏ này. Mã
           khách vãng lai vừa gõ chỉ dùng khi giỏ tài khoản chưa có mã nào. */
        if ((string) ($_SESSION['cart_voucher'] ?? '') === '' && $maKhach !== '') {
            $_SESSION['cart_voucher'] = $maKhach;
        }
    }
}
