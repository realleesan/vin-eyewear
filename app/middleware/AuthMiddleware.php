<?php

/**
 * AuthMiddleware — chặn truy cập theo trạng thái đăng nhập và vai trò.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ NƠI THAY THẾ ROW LEVEL SECURITY CỦA POSTGRES
 *
 * Bản Supabase để DB tự chặn: policy "staff orders", "admin products"… kiểm
 * vai trò ngay trong từng câu lệnh. MySQL không có cơ chế đó, nên mọi luật ấy
 * dồn về đây và về điều kiện user_id trong các Model.
 *
 * Hệ quả: QUÊN gọi middleware ở một controller quản trị nghĩa là trang đó mở
 * cho tất cả mọi người. Xem bảng đối chiếu ở cuối database/schema.sql.
 * ─────────────────────────────────────────────────────────────────────────────
 * HAI KHU VỰC, HAI PHIÊN, HAI COOKIE — KHÔNG BẮC CẦU, KHÔNG BIẾT NHAU
 *
 * Một tài khoản chỉ thuộc về ĐÚNG MỘT khu vực, quyết định bởi vai trò trong
 * bảng user_roles:
 *
 *   vai trò nội bộ (staff · manager · admin)  ->  CHỈ khu quản trị  /quan-tri
 *   vai trò khách  (customer, hoặc không có)  ->  CHỈ khu khách     /tai-khoan
 *
 * VÀ MỖI KHU CÓ PHIÊN RIÊNG. Đây là điểm khác căn bản so với bản trước:
 *
 *   khu khách    cookie `vin_session`  phạm vi /           ->  $_SESSION['user_id']
 *   khu quản trị cookie `vin_admin`    phạm vi /quan-tri   ->  $_SESSION['admin_id']
 *
 * Việc chia cookie làm ở core/App.php::startSession(), có ghi rõ lý do ở đó.
 * Trình duyệt không gửi cookie quản trị tới trang cửa hàng, nên hai danh tính
 * KHÔNG NHÌN THẤY NHAU — không phải vì mã ở đây chịu khó kiểm, mà vì dữ liệu
 * không có mặt.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HAI PHIÊN SỐNG SONG SONG ĐƯỢC, VÀ ĐÓ LÀ CHỦ Ý
 *
 * Một người có thể vừa đang đăng nhập /quan-tri, vừa đăng nhập /tai-khoan
 * bằng tài khoản khách RIÊNG của họ. Hai việc đó không đụng nhau:
 *
 *   · Đang mở khu quản trị mà vào /tai-khoan thì thấy đúng trạng thái CHƯA
 *     ĐĂNG NHẬP — form đăng nhập của khách, y hệt khách vãng lai. Không có
 *     dòng nhắc nào, không bị đá về /quan-tri.
 *   · Đăng nhập tài khoản khách ở đó KHÔNG làm mất phiên quản trị.
 *   · Đăng xuất một bên không đụng bên kia. Hai nút, hai đường, hai cookie.
 *
 * BẢN TRƯỚC KHÔNG NHƯ VẬY: cả hai khu dùng chung một ô $_SESSION['user_id'],
 * nên đăng nhập bên này là ghi đè bên kia, và requireLogin() phải đá tài khoản
 * nội bộ về /quan-tri kèm một dòng giải thích. Nhánh đó nay đã gỡ — không còn
 * gì để giải thích, vì với khu khách thì phiên quản trị đơn giản là không tồn tại.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO PHẢI TÁCH, chứ không để "ai cũng vào được cả hai"
 *
 *   · Mật khẩu mở khu quản trị mà đặt lại được qua luồng "Quên mật khẩu" của
 *     khách (email/OTP) thì cửa sau bên khách chính là cửa trước bên quản trị.
 *     Xem PasswordResetModel::requestOtp.
 *   · Cookie "ghi nhớ đăng nhập" sống 30 ngày là thứ hợp lý cho khách mua
 *     hàng, và là thứ không nên tồn tại cho một tài khoản mở được kho hàng
 *     và dữ liệu đơn thuốc kính. Cổng quản trị cố tình không cấp nó.
 *   · Đăng nhập bằng Google: SRS mục 3.A ghi rõ "Không áp dụng cho tài khoản
 *     nội bộ". Không chặn thì ai chiếm được hộp thư nội bộ là vào thẳng.
 *   · Một đơn hàng gắn nhầm vào tài khoản admin là dữ liệu bẩn không ai gỡ
 *     ra được.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BỐN CỬA, DÙNG ĐÚNG CỬA
 *
 *   customerId()   id khách đang đăng nhập, hoặc null. Mọi mã phía cửa hàng.
 *   requireLogin() cửa của khu khách.
 *   staffId()      id nhân viên đang đăng nhập, hoặc null. Chỉ khu quản trị.
 *   requireStaff() cửa của khu quản trị.
 *
 * KHÔNG CÒN userId() VÀ isStaffSession(). Hai hàm đó trả lời câu "phiên này là
 * ai" mà không nói của khu nào — câu hỏi ấy nay vô nghĩa, vì đang có tới hai
 * phiên và mỗi request chỉ nhìn thấy một. Gỡ hẳn thay vì để lại: một hàm mơ hồ
 * còn sống là một chỗ để người sửa sau vô tình bắc cầu lại hai khu vực.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class AuthMiddleware
{
    /** Khoá phiên của khu khách — chỉ có trong cookie `vin_session`. */
    private const O_KHACH = 'user_id';

    /** Khoá phiên của khu quản trị — chỉ có trong cookie `vin_admin`. */
    private const O_NOI_BO = 'admin_id';

    /**
     * Phiên quản trị chết sau bao lâu KHÔNG THAO TÁC (giây) — 30 phút.
     *
     * SNFR-10 (Quyết định C7) tách chính sách phiên theo nhóm người dùng, và
     * đây là vế nghiêm hơn: khách ưu tiên trải nghiệm, nhân viên ưu tiên bảo
     * mật vì thao tác của họ đụng tiền cọc và hồ sơ khúc xạ.
     *
     * ĐO THEO LƯỢT THAO TÁC CUỐI, KHÔNG PHẢI LÚC ĐĂNG NHẬP. Đo từ lúc đăng
     * nhập thì người đang xử lý đơn giữa chừng bị đá ra — 30 phút là quá ngắn
     * cho một ca trực. Đo theo thao tác cuối thì chỉ máy bỏ quên mới hết hạn,
     * đúng thứ điều khoản này muốn chặn: máy quầy dùng chung, nhân viên đứng
     * dậy tiếp khách rồi quên khoá màn hình.
     *
     * $_SESSION['logged_at'] ĐÃ TỒN TẠI TỪ TRƯỚC nhưng chưa từng có ai đọc —
     * nó ghi mốc đăng nhập, không phải mốc thao tác. Giữ nguyên nó (còn dùng
     * để hiển thị) và ghi mốc thao tác vào một ô riêng.
     */
    private const NOI_BO_HET_HAN_SAU = 1800;

    /** Ô ghi mốc thao tác cuối của phiên quản trị. */
    private const O_NOI_BO_THAO_TAC = 'admin_active_at';

    /**
     * Phiên KHÁCH chết sau bao lâu KHÔNG THAO TÁC (giây) — 24 giờ.
     *
     * SRS SNFR-10 viết 2 giờ; BA nới lên 24 giờ ngày 04/09/2026 (câu Q14.1) vì
     * 2 giờ cắt ngang đúng thói quen mua sắm: chọn gọng buổi sáng, hỏi ý kiến
     * người nhà, chiều quay lại đặt.
     *
     * ĐO THEO LƯỢT XEM TRANG CUỐI, cùng lối với phiên quản trị ở trên — không
     * phải đo từ lúc đăng nhập. Đo từ lúc đăng nhập thì người mua hàng liên
     * tục vẫn bị đá ra đúng 24 giờ sau, mà đó không phải thứ điều khoản này
     * muốn chặn.
     *
     * HẾT HẠN KHÔNG PHẢI LÀ ĐĂNG XUẤT HẲN. Hết hạn xong hàm này vẫn thử tiếp
     * cookie "ghi nhớ đăng nhập" (7 ngày, Quyết định C7) — nên khách đã tích ô
     * đó chỉ thấy phiên được dựng lại lặng lẽ, đúng như hai con số 24 giờ và 7
     * ngày ngụ ý. Khách KHÔNG tích ô đó thì phải đăng nhập lại.
     */
    private const KHACH_HET_HAN_SAU = 86400;

    /** Ô ghi mốc thao tác cuối của phiên khách. */
    private const O_KHACH_THAO_TAC = 'user_active_at';

    /** Đã thử khôi phục từ cookie "ghi nhớ" trong request này chưa. */
    private static bool $rememberChecked = false;

    /**
     * Nhớ kết quả "tài khoản này còn đăng nhập được không" trong suốt request.
     *
     * Khoá là id, giá trị là bool. Một trang gọi customerId() hàng chục lần —
     * header, giỏ hàng, mỗi khối cần biết đã đăng nhập chưa — và không có ô
     * nhớ này thì mỗi lần gọi là một lượt đi về cơ sở dữ liệu.
     */
    private static array $conSong = [];

    // ========================================================================
    // KHU KHÁCH HÀNG
    // ========================================================================

    /**
     * Id KHÁCH HÀNG đang đăng nhập, hoặc null.
     *
     * Không có phiên thì thử cookie "ghi nhớ đăng nhập" MỘT lần cho mỗi
     * request. Đây là chỗ duy nhất đọc cookie đó, nên mọi đường vào —
     * check(), requireLogin() — đều tự hưởng.
     *
     * VẪN KIỂM isStaff() DÙ ĐÃ TÁCH COOKIE. Thừa trong đời sống bình thường:
     * ô này nằm trong phiên của khu khách, và cổng quản trị không bao giờ ghi
     * vào đó. Nhưng nó rẻ và nó đỡ đúng một ca có thật — phiên `vin_session`
     * CŨ, cấp hồi hai khu còn dùng chung một ô, đang mang id của một tài khoản
     * nội bộ. Sau lần deploy này những phiên đó vẫn còn sống trong trình duyệt
     * nhân viên; không có dòng kiểm này thì họ bỗng thành đang-đăng-nhập với
     * tư cách khách hàng ở trang bán hàng.
     */
    public static function customerId(): ?string
    {
        $userId = $_SESSION[self::O_KHACH] ?? null;

        /*
         * HẾT HẠN DO KHÔNG THAO TÁC — 24 giờ, xem KHACH_HET_HAN_SAU.
         *
         * Chỉ DỌN Ô PHIÊN, không gọi logout(): logout() setcookie() và huỷ cả
         * token ghi nhớ, mà hàm này chạy giữa lúc dựng trang (xem khối cảnh
         * báo "headers already sent" bên dưới) và token ghi nhớ thì phải được
         * giữ — nó chính là đường dựng lại phiên ngay dòng sau.
         *
         * Đặt TRƯỚC nhánh cookie chứ không sau: để sau thì phiên cũ đã quá hạn
         * vẫn được dùng, và cái trần 24 giờ không bao giờ có hiệu lực.
         */
        if ($userId !== null) {
            $thaoTacCuoi = $_SESSION[self::O_KHACH_THAO_TAC] ?? ($_SESSION['logged_at'] ?? 0);

            if (time() - (int) $thaoTacCuoi > self::KHACH_HET_HAN_SAU) {
                unset(
                    $_SESSION[self::O_KHACH],
                    $_SESSION[self::O_KHACH_THAO_TAC],
                    $_SESSION['via_cookie']
                );
                $userId = null;

                /* GIỎ HÀNG SỐNG QUA PHIÊN — Q14.2, chốt 04/09/2026.

                   Nó vốn đã sống: nhánh này chỉ dọn ba ô trên, không đụng tới
                   $_SESSION['cart']. Nhưng Q14.2 đòi thêm vế thứ hai — CẢNH
                   BÁO dòng nào vừa đổi giá hoặc hết hàng — và trang giỏ hàng
                   không có cách nào tự biết phiên vừa hết hạn.

                   Cắm một lá cờ ở đây là chỗ DUY NHẤT biết điều đó. Cờ được
                   trang giỏ hàng đọc rồi xoá ngay (dùng một lần), nên nó không
                   nằm lại làm dải cảnh báo hiện mãi. */
                if (!empty($_SESSION['cart'])) {
                    $_SESSION['gio_qua_phien'] = true;
                }
            }
        }

        if ($userId === null) {
            $userId = self::tuCookieGhiNho();
        }

        if ($userId === null || UserModel::isStaff($userId)) {
            return null;
        }

        /*
         * ─────────────────────────────────────────────────────────────────────
         * TÀI KHOẢN BỊ KHOÁ HOẶC ĐÃ XOÁ THÌ PHIÊN ĐANG MỞ CŨNG PHẢI CHẾT.
         *
         * Không có dòng này thì nút "Khoá tài khoản" trong khu quản trị chỉ
         * chặn được LẦN ĐĂNG NHẬP SAU. Người đang mở sẵn một tab vẫn mua hàng,
         * vẫn đổi hồ sơ, vẫn đọc đơn thuốc của mình — cho tới khi phiên PHP hết
         * hạn, mà phiên thì sống hàng tuần. Ba cửa kia (mật khẩu, Google,
         * cookie ghi nhớ) đã chặn rồi; đây là cửa thứ tư.
         *
         * ─────────────────────────────────────────────────────────────────────
         * ĐÂY LÀ ĐÚNG CHỖ ĐÃ LÀM TRẮNG CẢ SITE NGÀY 2026-08-22.
         *
         * Bản đó (commit 0628170, bị revert bằng 7e14d0d sau bốn phút) cũng đặt
         * một phép kiểm "tài khoản còn sống không" ngay trên đường mà MỌI
         * request đều đi qua. Nguyên nhân không được ghi lại, nhưng hình dạng
         * hỏng thì rõ: một câu SELECT nhắc tới cột `deleted_at` trên cơ sở dữ
         * liệu chưa chạy migration là lỗi 1054, và vì nó nằm ở đây nên nó không
         * làm hỏng một trang mà hỏng tất cả.
         *
         * Ba thứ khiến bản này không lặp lại chuyện đó:
         *
         *   1. UserModel::coTheDangNhap() HỎI CỘT CÓ TỒN TẠI KHÔNG trước khi
         *      nhắc tới nó (Database::columnExists, có nhớ đệm), và trả TRUE
         *      khi không có cột — chưa nâng cấp thì mọi người vào bình thường.
         *   2. try/catch bọc ngoài, hỏng thì CHO QUA. Một tài khoản bị khoá vẫn
         *      vào được trong vài phút là chuyện phải gọi điện xử lý; cả site
         *      trắng trang thì không.
         *   3. Nhớ kết quả trong suốt request: một trang gọi customerId() hàng
         *      chục lần, không hỏi cơ sở dữ liệu chừng ấy lần.
         *
         * VÀ NÓ KHÔNG GHI GÌ RA NGOÀI — không setcookie, không redirect, không
         * echo. Chỉ trả null, tức là "không có ai đăng nhập". Hàm này được gọi
         * ở giữa lúc dựng trang, nên bất cứ thứ gì chạm tới header ở đây đều là
         * một cảnh báo "headers already sent" chờ sẵn.
         * ─────────────────────────────────────────────────────────────────────
         */
        if (!isset(self::$conSong[$userId])) {
            try {
                self::$conSong[$userId] = UserModel::coTheDangNhap($userId);
            } catch (Throwable $e) {
                error_log('AuthMiddleware: không kiểm được trạng thái tài khoản — ' . $e->getMessage());
                self::$conSong[$userId] = true;
            }
        }

        if (!self::$conSong[$userId]) {
            return null;
        }

        /* Chạm lại mốc thao tác — thứ biến 24 giờ thành "không thao tác" chứ
           không phải "kể từ khi đăng nhập". Ghi vào $_SESSION là thao tác
           trong bộ nhớ, không đụng header, nên an toàn ở giữa lúc dựng trang.

           Không có nhánh "chỉ hỏi phụ" như staffId(): phía khách không có chỗ
           nào gọi customerId() ngoài luồng phục vụ một lượt xem trang thật. */
        $_SESSION[self::O_KHACH_THAO_TAC] = time();

        return $userId;
    }

    /**
     * Có KHÁCH HÀNG nào đang đăng nhập không.
     *
     * Phiên quản trị KHÔNG tính, và ở đây thì không tính theo nghĩa mạnh nhất:
     * request tới trang cửa hàng không hề mang theo cookie `vin_admin`.
     */
    public static function check(): bool
    {
        return self::customerId() !== null;
    }

    /**
     * Bắt buộc đăng nhập. Chưa đăng nhập thì đưa về /auth kèm địa chỉ đang
     * muốn tới, để đăng nhập xong quay lại đúng chỗ.
     *
     * $returnTo ĐỂ TRỐNG thì lấy đường dẫn hiện tại — mà currentPath() CẮT
     * QUERY STRING. Với hầu hết trang thì đúng như vậy là tốt: query của
     * /san-pham là bộ lọc, mang qua màn đăng nhập rồi trả về chỉ tổ dài dòng.
     * Nhưng có những trang mà query CHÍNH LÀ chỗ khách muốn tới — /tai-khoan
     * dùng ?muc= để chọn mục — và ở đó bỏ query đi nghĩa là đăng nhập xong họ
     * rơi vào mục mặc định chứ không phải mục vừa bấm.
     *
     * Nên nơi gọi tự khai đường quay lại khi nó biết rõ hơn. Giá trị vẫn đi
     * qua safeRedirectPath() ở đầu bên kia (xem AuthController::loginTarget),
     * nên một chuỗi dẫn ra ngoài site không bao giờ thành đích thật.
     *
     * KHÔNG CÒN NHÁNH "TÀI KHOẢN NỘI BỘ ĐI NHẦM SANG ĐÂY". Trước đây hàm này
     * nhận ra phiên nội bộ rồi đá về /quan-tri kèm lời nhắn. Nay không nhận ra
     * được nữa, và cũng không cần: cookie quản trị không tới đây, nên với khu
     * khách thì người đó là khách vãng lai. Họ thấy form đăng nhập bình thường
     * và đăng nhập bằng tài khoản khách của mình được ngay — đúng thứ luồng cũ
     * chặn lại.
     */
    public static function requireLogin(?string $returnTo = null): string
    {
        $userId = self::customerId();

        if ($userId === null) {
            redirect('/auth?redirect=' . rawurlencode($returnTo ?? currentPath()));
        }

        return $userId;
    }

    /**
     * Ghi nhận đăng nhập KHÁCH HÀNG thành công.
     *
     * session_regenerate_id() là bắt buộc, không phải phòng xa: kẻ tấn công
     * có thể ép nạn nhân dùng một mã phiên do hắn biết trước (session
     * fixation), rồi sau khi nạn nhân đăng nhập thì dùng chính mã đó để vào.
     * Cấp mã phiên mới ngay lúc đổi quyền sẽ vô hiệu hoá mã cũ.
     *
     * Chỉ đụng tới phiên `vin_session`. Phiên quản trị nếu có thì nằm trong
     * một cookie khác và không hề bị ảnh hưởng.
     */
    public static function login(string $userId, bool $remember = false): void
    {
        session_regenerate_id(true);

        $_SESSION[self::O_KHACH]         = $userId;
        $_SESSION['logged_at']           = time();
        $_SESSION[self::O_KHACH_THAO_TAC] = time();
        unset($_SESSION['via_cookie']);

        if ($remember) {
            RememberModel::issue($userId);
        }
    }

    /**
     * Đăng xuất KHÁCH HÀNG: xoá phiên `vin_session` và cookie ghi nhớ.
     *
     * KHÔNG ĐỤNG TỚI PHIÊN QUẢN TRỊ. Không phải nhờ một dòng kiểm nào ở đây —
     * hàm này chạy trên đường dẫn của khu khách, nên session_destroy() chỉ với
     * tới được cookie `vin_session`. Nhân viên bấm Đăng xuất ở trang bán hàng
     * vẫn còn nguyên phiên ở /quan-tri.
     */
    public static function logout(): void
    {
        // Huỷ luôn token "ghi nhớ" của máy này. Bỏ qua bước này thì bấm Đăng
        // xuất xong tải lại trang là đăng nhập lại ngay — đúng cái người dùng
        // vừa cố tránh, nhất là trên máy dùng chung.
        RememberModel::forget();

        // Giữ lại giỏ hàng: khách đăng xuất trên máy chung vẫn nên mất phiên,
        // nhưng giỏ đang chọn dở thì không có lý do gì phải xoá.
        $cart = $_SESSION['cart'] ?? null;

        self::huyPhien();

        if ($cart !== null) {
            $_SESSION['cart'] = $cart;
        }
    }

    // ========================================================================
    // KHU QUẢN TRỊ
    // ========================================================================

    /**
     * Id NHÂN VIÊN đang đăng nhập, hoặc null.
     *
     * Chỉ trả về giá trị khi chạy trên đường /quan-tri — ngoài đó thì phiên
     * đang mở là `vin_session` và ô này không tồn tại. Đó là điều đúng: mã
     * phía cửa hàng không có việc gì phải biết ai đang trực quầy.
     *
     * KHÔNG khôi phục từ cookie "ghi nhớ". Cổng quản trị không bao giờ cấp
     * token đó (xem AdminAuthController::login), nên ở đây không có gì để
     * khôi phục — và nếu mai này có ai thêm vào thì phải sửa ở đúng một chỗ.
     *
     * Vai trò đọc lại từ DB mỗi lượt chứ không cất vào phiên: cùng lý do đã
     * ghi ở requireStaff().
     */
    /**
     * @param bool $huyNeuHetHan Phiên quá hạn thì có được HUỶ luôn không.
     *
     * Đặt false ở những nơi CHỈ HỎI "ai đang đăng nhập" mà không quyết định
     * cho đi tiếp hay không — cụ thể là AuditLogModel::write(). Một hàm ghi
     * log không nên có quyền đăng xuất người dùng, và nó có thể chạy giữa lúc
     * dựng trang: huỷ phiên ở đó là phần view còn lại đọc phải một $_SESSION
     * rỗng, mất flash và mất token CSRF của những form nằm phía dưới.
     *
     * Chốt headers_sent() bên dưới KHÔNG đủ để chặn ca đó: hosting chia sẻ
     * thường bật output_buffering, nên headers_sent() vẫn trả false giữa lúc
     * dựng trang.
     *
     * Phiên vẫn vô hiệu ngay lập tức dù không huỷ: hàm này trả null, và MỌI
     * đường vào khu quản trị đều đọc qua đây. Lượt bấm kế tiếp dọn nốt cookie.
     */
    public static function staffId(bool $huyNeuHetHan = true): ?string
    {
        $userId = $_SESSION[self::O_NOI_BO] ?? null;

        if ($userId === null || !UserModel::isStaff($userId)) {
            return null;
        }

        /* HẾT HẠN DO KHÔNG THAO TÁC — SNFR-10, 30 phút.

           Kiểm ở ĐÂY chứ không ở requireStaff(): staffId() là cửa duy nhất mà
           mọi đường trong khu quản trị đi qua để biết "ai đang đăng nhập", kể
           cả những chỗ chỉ hỏi mà không bắt buộc. Đặt ở requireStaff() thì một
           trang nào đó gọi thẳng staffId() sẽ bỏ lọt.

           Phiên quá hạn bị HUỶ chứ không chỉ bị coi là chưa đăng nhập: để lại
           thì cookie phiên cũ vẫn nằm trên máy quầy, và lần bấm sau lại tính
           là một phiên "vừa hết hạn" nữa. Huỷ xong mới đặt flash, vì huyPhien()
           dọn sạch $_SESSION. */
        $thaoTacCuoi = $_SESSION[self::O_NOI_BO_THAO_TAC] ?? ($_SESSION['logged_at'] ?? 0);

        if (time() - (int) $thaoTacCuoi > self::NOI_BO_HET_HAN_SAU) {
            /* CHỈ HUỶ PHIÊN KHI CHƯA GỬI HEADER.

               huyPhien() gọi setcookie() rồi session_start() — cả hai đều cần
               header chưa đi. Đường bình thường thì an toàn: AdminController
               kiểm quyền ngay ở constructor, tức trước khi view in ra chữ nào.
               Nhưng staffId() còn được gọi từ AuditLogModel::write(), và chỗ
               đó có thể chạy giữa lúc dựng trang.

               Gặp ca ấy thì bỏ qua việc huỷ và chỉ trả null: phiên vẫn vô hiệu
               ngay lập tức vì MỌI đường vào khu quản trị đều đọc qua hàm này,
               và lượt bấm kế tiếp sẽ dọn nốt cookie. Thà mất một câu thông báo
               còn hơn đổ một dòng cảnh báo "headers already sent" ra giữa
               trang quản trị. */
            if ($huyNeuHetHan && !headers_sent()) {
                self::logoutStaff();
                flash('admin_auth_error', 'Phiên làm việc đã hết hạn do không thao tác. Vui lòng đăng nhập lại.');
            }

            return null;
        }

        // Chỉ chạm lại mốc khi đây là một lượt thao tác THẬT, không phải một
        // lời hỏi phụ từ AuditLogModel: nếu không thì mỗi dòng vết ghi ra lại
        // gia hạn phiên thêm 30 phút, và cái timeout không bao giờ tới.
        if (!$huyNeuHetHan) {
            return $userId;
        }

        // Chạm lại mốc mỗi lượt: đây là thứ biến 30 phút thành "không thao
        // tác" chứ không phải "kể từ khi đăng nhập".
        $_SESSION[self::O_NOI_BO_THAO_TAC] = time();

        return $userId;
    }

    /**
     * Bắt buộc có quyền vào khu quản trị.
     *
     * Vai trò đọc lại từ DB mỗi lần, KHÔNG lấy từ session: session sống hàng
     * tuần, nên người vừa bị gỡ quyền vẫn giữ quyền tới khi tự đăng xuất.
     *
     * CHƯA ĐĂNG NHẬP THÌ VỀ CỔNG QUẢN TRỊ, KHÔNG PHẢI /auth.
     *
     * Trước đây dòng này gọi requireLogin(), tức là ném nhân viên sang trang
     * đăng nhập của KHÁCH: nền be, nút "Tạo tài khoản", nút "Đăng nhập bằng
     * Google". Không có gì trên màn hình đó nói rằng họ đang bước vào khu quản
     * trị, và cái nút tạo tài khoản thì gợi ý sai hẳn — tài khoản quản trị
     * không tự đăng ký được.
     *
     * MANG THEO CẢ QUERY STRING, khác requireLogin() vốn cắt nó bằng
     * currentPath(). Trong khu quản trị thì query THƯỜNG LÀ chỗ người ta muốn
     * tới: /quan-tri/don-hang?trang-thai=cho-xac-nhan là một tab riêng,
     * /quan-tri/san-pham?sua=<id> là một biểu mẫu đang mở. Cắt đi là đăng nhập
     * xong rơi về danh sách trống, phải tìm lại từ đầu.
     *
     * Vẫn đi qua safeRedirectPath() ở đầu bên kia — xem
     * AdminAuthController::target(), nơi còn siết thêm là chỉ nhận đường dẫn
     * nằm trong /quan-tri.
     *
     * KHÔNG CÒN NHÁNH 403 "đã đăng nhập nhưng không đủ quyền". Nhánh đó chỉ có
     * nghĩa khi một phiên KHÁCH lọt được vào đây, mà nay thì không: ô
     * `admin_id` chỉ do AdminAuthController::login() ghi, và nó kiểm quyền
     * trước khi ghi. Một ô rỗng hoặc một tài khoản vừa bị gỡ quyền đều dẫn về
     * cùng một chỗ — cổng đăng nhập — vì cả hai đều cần đúng một việc: đăng
     * nhập lại bằng tài khoản có quyền.
     */
    public static function requireStaff(): string
    {
        $userId = self::staffId();

        if ($userId === null) {
            redirect('/quan-tri/dang-nhap?redirect=' . rawurlencode(
                currentUrlWithout([])
            ));
        }

        return $userId;
    }

    /**
     * Bắt buộc một vai trò cụ thể (admin, manager…).
     *
     * Đi qua requireStaff() trước: phải là nhân viên đã, rồi mới xét vai trò
     * cụ thể. Nhờ vậy người chưa đăng nhập nhận cổng đăng nhập chứ không nhận
     * 403 — họ chưa được hỏi mật khẩu lần nào thì nói "không đủ quyền" là sai.
     */
    public static function requireRole(string $role): string
    {
        $userId = self::requireStaff();

        if (!UserModel::hasRole($userId, $role)) {
            // 403 chứ không 404: người dùng ĐÃ đăng nhập bằng tài khoản nội
            // bộ hợp lệ, nói rõ là không đủ quyền thì hữu ích hơn là giả vờ
            // trang không tồn tại.
            http_response_code(403);
            (new ErrorController())->forbidden();
            exit;
        }

        return $userId;
    }

    /**
     * Ghi nhận đăng nhập NHÂN VIÊN thành công.
     *
     * Không có tham số $remember, và đó là cố ý chứ không phải thiếu sót:
     * cookie ghi nhớ sống 30 ngày trên đúng cái máy hay được dùng chung ở
     * quầy. Cổng quản trị không cấp nó — xem AdminAuthController::login().
     *
     * Chỉ đụng tới phiên `vin_admin`. Nhân viên đang đăng nhập tài khoản khách
     * riêng của mình ở trang bán hàng thì phiên đó còn nguyên.
     */
    public static function loginStaff(string $userId): void
    {
        session_regenerate_id(true);

        $_SESSION[self::O_NOI_BO]           = $userId;
        $_SESSION['logged_at']              = time();
        $_SESSION[self::O_NOI_BO_THAO_TAC]  = time();
    }

    /**
     * Đăng xuất NHÂN VIÊN: xoá phiên `vin_admin`.
     *
     * Không gọi RememberModel::forget() — cổng này không cấp token ghi nhớ nên
     * không có gì để thu hồi, và cookie đó thuộc về khu khách: xoá nó ở đây là
     * bấm Đăng xuất bên quản trị lại đá luôn phiên mua hàng của người ta.
     */
    public static function logoutStaff(): void
    {
        self::huyPhien();
    }

    // ========================================================================
    // BÊN TRONG
    // ========================================================================

    /**
     * Khôi phục danh tính KHÁCH từ cookie "ghi nhớ đăng nhập".
     *
     * Thử một lần cho mỗi request, dù có bao nhiêu nơi gọi customerId().
     */
    private static function tuCookieGhiNho(): ?string
    {
        if (self::$rememberChecked) {
            return null;
        }

        self::$rememberChecked = true;

        // Không có cookie thì thôi, khỏi đụng tới cơ sở dữ liệu. Đại đa số
        // lượt truy cập là khách vãng lai không có cookie này.
        if (!isset($_COOKIE[RememberModel::COOKIE])) {
            return null;
        }

        $userId = RememberModel::consume();

        if ($userId === null) {
            return null;
        }

        /*
         * COOKIE GHI NHỚ LÀ CƠ CHẾ CỦA RIÊNG KHU KHÁCH HÀNG.
         *
         * Cổng quản trị không bao giờ cấp token này — AuthMiddleware
         * ::loginStaff() không nhận tham số $remember, có ghi rõ lý do ở đó.
         * Nên một token trỏ tới tài khoản nội bộ chỉ có thể là di sản: cấp
         * hồi tài khoản đó còn đăng nhập được ở /auth, trước khi hai khu vực
         * bị tách.
         *
         * Huỷ SẠCH token của tài khoản ấy chứ không chỉ bỏ qua lượt này:
         * consume() vừa xoay token xong, bỏ qua thôi thì lần sau vào trang
         * lại đúng cảnh này. Người đó đăng nhập lại ở /quan-tri/dang-nhap.
         */
        if (UserModel::isStaff($userId)) {
            RememberModel::forgetAllFor($userId);
            RememberModel::forget();

            return null;
        }

        // Đăng nhập lại từ cookie KHÔNG cấp phiên "mới tinh": đánh dấu
        // via_cookie để những thao tác nhạy cảm (đổi mật khẩu, đổi email) có
        // thể yêu cầu nhập lại mật khẩu nếu sau này cần siết thêm.
        session_regenerate_id(true);
        $_SESSION[self::O_KHACH]          = $userId;
        $_SESSION['logged_at']            = time();
        $_SESSION[self::O_KHACH_THAO_TAC] = time();
        $_SESSION['via_cookie']           = true;

        // Dọn token chết, thưa thớt thôi — 1/50 lượt khôi phục là đủ để bảng
        // không phình, mà không biến mỗi lần vào trang thành một lệnh DELETE.
        if (random_int(1, 50) === 1) {
            RememberModel::purgeExpired();
        }

        return $userId;
    }

    /**
     * Xoá sạch phiên ĐANG MỞ và cookie của nó, rồi mở lại một phiên rỗng.
     *
     * Dùng chung cho cả hai khu vực, và an toàn để dùng chung chính vì cookie
     * đã tách: session_name() và session_get_cookie_params() lúc này mang giá
     * trị mà App::startSession() đặt cho khu vực của request hiện tại, nên hàm
     * này không có cách nào với sang phiên của khu bên kia.
     */
    private static function huyPhien(): void
    {
        $_SESSION = [];

        // Xoá cả cookie phiên, nếu không trình duyệt vẫn gửi mã cũ lên.
        // $p['path'] là phạm vi của ĐÚNG khu vực này ('/' hoặc '/quan-tri') —
        // sai path thì trình duyệt giữ nguyên cookie cũ và người dùng bấm
        // Đăng xuất xong vẫn còn đăng nhập.
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
}
