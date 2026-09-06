<?php

/**
 * app/services/EmailEvents.php — mọi chỗ sinh ra một lá thư cho khách.
 *
 * SRS v2.1.0 — FR-EM-01 (mốc đơn hàng), FR-EM-02 (mốc tiền), FR-EM-03 và
 * FR-LH-08 (lịch hẹn), FR-EM-04 và FR-SP-18 (hàng có lại), FR-TT-11 (cảnh báo
 * trước khi tự huỷ).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỘT LỚP RIÊNG, KHÔNG RẢI VÀO CÁC MODEL
 *
 * Mỗi lá thư cần một mã sự kiện, một địa chỉ nhận, một khoá chống trùng và một
 * mảng biến. Nhét bốn thứ đó vào OrderModel::changeStatus() là biến một hàm về
 * trạng thái đơn thành một hàm về soạn thư, và lần thứ hai sẽ có người chép nó
 * sang BookingModel.
 *
 * Ở đây thì mỗi nơi gọi chỉ còn MỘT dòng, và luật "thư nào gửi cho ai, kèm
 * biến gì" nằm đúng một chỗ để đọc.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG HÀM NÀO Ở ĐÂY ĐƯỢC PHÉP NÉM LỖI — FR-EM-06
 *
 * Và điều đó KHÔNG tự nhiên có chỉ vì EmailQueueModel::xepHang() tự bọc
 * try/catch. Bản đầu của file này tin như vậy và đã sai: các hàm dưới đây còn
 * chạy truy vấn của RIÊNG chúng TRƯỚC khi gọi xepHang() — emailTaiKhoan() hỏi
 * `users`, lichHen() có thể hỏi `stores`. Một "MySQL server has gone away" ở
 * đó ném xuyên qua cả lớp.
 *
 * Hậu quả không nhỏ: OrderModel::place() gọi donHang() SAU khi transaction đã
 * commit, và bắt Throwable quanh cả khối — nên một lá thư hỏng khiến khách
 * thấy "Không tạo được đơn hàng, vui lòng thử lại" cho một đơn ĐÃ TẠO XONG và
 * đã trừ kho. Họ đặt lại, và có hai đơn.
 *
 * Nên mọi hàm công khai ở đây đi qua chay(), và chay() nuốt Throwable.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÀ KHÔNG HÀM NÀO ĐƯỢC GHI CSDL TRONG TRANSACTION CỦA NGƯỜI KHÁC
 *
 * chay() hoãn việc ra sau commit khi đang ở trong một transaction. Lý do đầy
 * đủ ở Database::sauKhiCommit(); tóm tắt: markPaid() bị gọi từ trong
 * transaction tiền của SepayModel, và một deadlock lúc chèn thư sẽ cuộn lại cả
 * giao dịch ấy trong im lặng.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐỊA CHỈ NHẬN LẤY Ở ĐÂU
 *
 *   Đơn hàng   `orders`.`customer_email` — chép lúc đặt, có cả với khách vãng
 *              lai. Rỗng thì lùi về email tài khoản (đơn đặt trước khi form
 *              thanh toán hỏi email).
 *   Lịch hẹn   CHỈ email tài khoản. Bảng `appointments` không có cột email —
 *              form đặt lịch chỉ hỏi tên và số điện thoại. Nghĩa là khách vãng
 *              lai đặt lịch KHÔNG nhận được thư nào, và đó là giới hạn thật
 *              của dữ liệu chứ không phải chỗ quên: muốn gửi thì phải thêm ô
 *              email vào form đặt lịch trước.
 *   Chờ hàng   `stock_waitlist`.`email` — bản chụp từ hồ sơ lúc đăng ký.
 */

class EmailEvents
{
    /**
     * Chạy một việc soạn thư — nuốt mọi lỗi, và hoãn ra sau commit nếu đang
     * ở trong một transaction. Xem hai khối chú thích đầu file.
     */
    private static function chay(callable $viec): void
    {
        try {
            // Đã hoãn được thì thôi — hàng đợi sau-commit tự bọc try/catch.
            if (Database::sauKhiCommit($viec)) {
                return;
            }

            $viec();
        } catch (Throwable $e) {
            error_log('EmailEvents: ' . $e->getMessage());
        }
    }

    /** Địa chỉ web của cửa hàng, không có dấu / ở cuối. */
    private static function goc(): string
    {
        return rtrim((string) config('app.url', ''), '/');
    }

    /** Email tài khoản của một khách, hoặc null. */
    private static function emailTaiKhoan(?string $userId): ?string
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        $u = Database::fetchOne('SELECT email FROM users WHERE id = :id', ['id' => $userId]);
        $e = trim((string) ($u['email'] ?? ''));

        return $e === '' ? null : $e;
    }

    // ========================================================================
    // ĐƠN HÀNG — FR-EM-01
    // ========================================================================

    /**
     * Năm mốc đơn hàng. Nơi gọi truyền sẵn bản ghi đơn nó vừa đọc.
     *
     * @param string $moc 'tao' | 'xac_nhan' | 'giao' | 'hoan_tat' | 'huy'
     */
    public static function donHang(array $don, string $moc, ?string $lyDo = null): void
    {
        $ma = (string) ($don['code'] ?? '');

        if ($ma === '') {
            return;
        }

        self::chay(static function () use ($don, $moc, $lyDo, $ma): void {
        $email = trim((string) ($don['customer_email'] ?? ''))
            ?: self::emailTaiKhoan($don['user_id'] ?? null);

        $bien = [
            'ten_khach' => (string) ($don['customer_name'] ?? 'bạn'),
            'ma_don'    => $ma,
            'tong_tien' => money((int) ($don['total'] ?? 0)),
            'link_don'  => self::goc() . '/tai-khoan?muc=don-hang&don=' . rawurlencode($ma),
            /* Nhãn theo hình thức nhận hàng — đơn nhận tại quầy đọc "Sẵn sàng
               tại cửa hàng" chứ không "Đang giao" (FR-QT-05). Cùng một hàm mà
               hai màn hình dùng, nên thư không bao giờ nói khác màn hình. */
            'nhan_trang_thai' => OrderModel::nhanTrangThai(
                (string) ($don['status'] ?? ''),
                $don['delivery_method'] ?? null
            ),
            'ly_do' => $lyDo !== null && $lyDo !== '' ? 'Lý do: ' . $lyDo : '',
        ];

        /* KHOÁ CHỐNG TRÙNG THEO ĐƠN + MỐC, không theo đơn.

           Một đơn đi qua cả năm mốc nên khoá phải phân biệt được chúng. Nhưng
           TRONG một mốc thì chỉ được một thư: nhân viên bấm nhầm "Đã xác nhận"
           rồi bấm lại, hay đơn đi lui rồi tiến lại qua cùng một trạng thái,
           đều không được sinh thư thứ hai. */
        EmailQueueModel::xepHang(
            'don.' . $moc,
            $email,
            $bien,
            'don.' . $moc . ':' . ($don['id'] ?? $ma),
            null,
            $don['user_id'] ?? null,
            'order',
            $don['id'] ?? null
        );
        });
    }

    // ========================================================================
    // TIỀN — FR-EM-02
    // ========================================================================

    /**
     * @param string $moc 'coc' | 'du' | 'hoan'
     */
    public static function tien(array $don, string $moc, int $soTien, ?string $ngay = null): void
    {
        $ma = (string) ($don['code'] ?? '');

        if ($ma === '') {
            return;
        }

        self::chay(static function () use ($don, $moc, $soTien, $ngay, $ma): void {
        $email = trim((string) ($don['customer_email'] ?? ''))
            ?: self::emailTaiKhoan($don['user_id'] ?? null);

        EmailQueueModel::xepHang(
            'tien.' . $moc,
            $email,
            [
                'ten_khach' => (string) ($don['customer_name'] ?? 'bạn'),
                'ma_don'    => $ma,
                'so_tien'   => money($soTien),
                'ngay_hoan' => $ngay !== null ? formatDate($ngay) : '',
                'link_don'  => self::goc() . '/tai-khoan?muc=don-hang&don=' . rawurlencode($ma),
            ],
            'tien.' . $moc . ':' . ($don['id'] ?? $ma),
            null,
            $don['user_id'] ?? null,
            'order',
            $don['id'] ?? null
        );
        });
    }

    // ========================================================================
    // LỊCH HẸN — FR-EM-03, FR-LH-08
    // ========================================================================

    /**
     * @param string $moc 'dat' | 'xac_nhan' | 'doi_ngay' | 'huy' | 'nhac'
     */
    public static function lichHen(array $lich, string $moc, ?string $ngayCu = null): void
    {
        $ma = (string) ($lich['code'] ?? '');

        if ($ma === '') {
            return;
        }

        self::chay(static function () use ($lich, $moc, $ngayCu, $ma): void {
            /* CHỈ EMAIL TÀI KHOẢN — bảng `appointments` không có cột email.
               Xem khối chú thích đầu lớp.

               `email_nhan` là lối tắt cho bộ quét nhắc lịch: câu quét ấy đã
               JOIN sang `users` để lọc người có email, nên hỏi lại từng người
               một là năm mươi truy vấn thừa trên đúng lượt truy cập của một
               người thật. Không có thì hỏi như thường. */
            $email = trim((string) ($lich['email_nhan'] ?? ''))
                ?: self::emailTaiKhoan($lich['user_id'] ?? null);

            if ($email === null || $email === '') {
                return;
            }

            $coSo = trim((string) ($lich['store_name'] ?? ''));

            if ($coSo === '' && ($lich['store_id'] ?? null) !== null) {
                $s    = StoreModel::find((string) $lich['store_id']);
                $coSo = (string) ($s['name'] ?? '');
            }

            $ngay = (string) ($lich['appointment_date'] ?? '');

            /* ─────────────────────────────────────────────────────────────────
               BA MỐC MANG NGÀY VÀO KHOÁ, HAI MỐC KIA MANG ID.

               Khoá chống trùng phải phân biệt được đúng những lần ĐÁNG gửi lại.
               Chia theo việc mốc ấy có xảy ra hai lần cho cùng một lịch hẹn
               không:

                 nhac       gửi trước một ngày. Lịch dời sang tuần sau thì đáng
                            được nhắc lần nữa — nhắc cho ngày MỚI, không phải
                            im lặng vì đã nhắc cho ngày cũ.
                 doi_ngay   dời hai lần là hai lá thư. Bản đầu khoá theo id
                            nên lần dời thứ hai bị nuốt: khách được báo lịch
                            chuyển sang 22/09, rồi nhân viên dời tiếp sang
                            25/09 mà không lá thư nào đi — khách vẫn tới vào
                            22/09. Đúng cái hỏng mà lá thư này sinh ra để chặn.
                 xac_nhan   khách tự đổi ngày thì rescheduleOwned() đặt lịch
                            về 'pending', nên cửa hàng phải xác nhận LẠI. Khoá
                            theo id thôi là lần xác nhận thứ hai không có thư.

               Hai mốc còn lại xảy ra đúng một lần cho mỗi lịch hẹn, và ngày
               của chúng không đổi:

                 dat        chỉ đặt được một lần.
                 huy        huỷ một lịch đã huỷ vẫn ra "đã huỷ"; bấm hai lần
                            không được sinh thư thứ hai.
               ───────────────────────────────────────────────────────────────── */
            /* HAI KIỂU HẬU TỐ, VÀ CHÚNG TRẢ LỜI HAI CÂU KHÁC NHAU.

                 nhac                 hậu tố là NGÀY HẸN. Câu hỏi là "đã nhắc
                                      cho ngày này chưa" — đúng một lá cho mỗi
                                      ngày hẹn, dù lịch có bị đụng vào mấy lần.
                 doi_ngay, xac_nhan   hậu tố là MỐC SỬA GẦN NHẤT. Câu hỏi là
                                      "đã báo cho LẦN SỬA này chưa".

               Vì sao hai mốc sau không dùng ngày: ngày là một TẬP HỢP, không
               phải một dãy. Dời 20/09 → 22/09 → 25/09 → rồi quay lại 22/09 thì
               khoá '…:2026-09-22' đã tồn tại, lá thư thứ ba bị nuốt, và khách
               vẫn đinh ninh buổi hẹn của mình là 25/09. `updated_at` thì tăng
               ở mọi lần UPDATE nên không bao giờ lặp lại.

               Lùi về $ngay khi bản ghi không mang `updated_at` — nơi gọi nào
               dựng mảng bằng tay thì vẫn có một khoá dùng được, chỉ là quay
               lại đúng tính chất tập hợp ở trên. */
            $khoa = 'lich.' . $moc . ':' . ($lich['id'] ?? $ma);

            if ($moc === 'nhac') {
                $khoa .= ':' . $ngay;
            } elseif ($moc === 'doi_ngay' || $moc === 'xac_nhan') {
                $khoa .= ':' . (string) ($lich['updated_at'] ?? $ngay);
            }

            EmailQueueModel::xepHang(
                'lich.' . $moc,
                $email,
                [
                    'ten_khach' => (string) ($lich['full_name'] ?? 'bạn'),
                    'ma_lich'   => $ma,
                    'ngay_hen'  => $ngay !== '' ? formatDate($ngay) : '',
                    'ngay_cu'   => $ngayCu !== null && $ngayCu !== '' ? formatDate($ngayCu) : '',
                    'co_so'     => $coSo !== '' ? $coSo : 'cửa hàng',
                    'link_lich' => self::goc() . '/tai-khoan?muc=lich-hen',
                ],
                $khoa,
                null,
                $lich['user_id'] ?? null,
                'appointment',
                $lich['id'] ?? null
            );
        });
    }

    // ========================================================================
    // HÀNG CÓ LẠI — FR-EM-04, FR-SP-18
    // ========================================================================

    /**
     * Báo cho những người đang chờ một mặt hàng vừa có hàng trở lại.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * ĐÁNH DẤU `notified_at` NGAY, KHÔNG ĐỢI THƯ GỬI XONG — NHƯNG CHỈ NHỮNG
     * LƯỢT CHỜ THẬT SỰ ĐÃ SINH RA MỘT LÁ THƯ
     *
     * Vế đầu nghe ngược mà đúng: `notified_at` trả lời câu "đã xử lý lượt chờ
     * này chưa", không phải "thư đã tới tay chưa". Câu sau do `email_queue`
     * trả lời, và nó trả lời chi tiết hơn nhiều. Không đánh dấu ngay thì lần
     * nhập hàng kế tiếp — hoặc chỉ một lần sửa số kho — lại xếp thêm một lá
     * nữa cho cùng những người ấy.
     *
     * Vế sau là chỗ bản đầu SAI, và sai theo hướng xoá mất việc của nhân viên:
     * nó đánh dấu CẢ LÔ, kể cả những lượt chờ xepHang() vừa từ chối. Mà lý do
     * từ chối phổ biến nhất lại đúng là lý do màn "Chờ hàng" tồn tại:
     *
     *   · Khách không có email. `stock_waitlist`.`email` cho phép NULL vì
     *     đăng ký chờ chỉ cần một trong hai — số điện thoại là đủ. Với họ,
     *     cách báo tin DUY NHẤT là nhân viên gọi.
     *   · Cửa hàng tắt mẫu `kho.co_hang`.
     *   · Chưa chạy migration đợt 6.
     *
     * Trong cả ba ca, đánh dấu `notified_at` là làm những người ấy biến mất
     * khỏi danh sách "Đang chờ" mà không ai gọi cho họ, và không có đường nào
     * tìm lại. Nhập một lô hàng có mười người chờ, sáu người chỉ để lại số
     * điện thoại, thì sáu người đó mất hẳn.
     *
     * Nên chỉ đánh dấu những id mà xepHang() trả về một mã thư.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * `$variantId` NULL NGHĨA LÀ "LƯỢT CHỜ KHÔNG CHỌN PHƯƠNG ÁN"
     *
     * Không phải "mọi phương án" — bản đầu hiểu như thế và nó dẫn tới một lá
     * thư sai hẳn. Lập luận cũ là "`products`.`status` chặn trước mọi biến thể
     * nên mở nó ra là mở cho tất cả", nhưng VariantModel::inStock() là phép VÀ:
     * mặt hàng phải đang bán VÀ tồn của ĐÚNG biến thể ấy phải còn.
     *
     * Nên nhập 5 cái màu Nâu rồi sửa tồn mặt hàng 0 → 5 mà báo cho người chờ
     * màu Đen là mời họ bấm vào một nút vẫn đang khoá. Tệ hơn: lượt chờ của
     * họ bị đánh dấu đã báo, nên hôm màu Đen về thật thì không còn ai để gửi.
     *
     * @return int số thư đã xếp hàng
     */
    public static function hangCoLai(array $sanPham, ?string $variantId = null): int
    {
        return self::guiChoLuotCho(
            $sanPham,
            static fn (): array => WaitlistModel::dangChoCho((string) $sanPham['id'], $variantId)
        );
    }

    /**
     * Báo cho mọi người đang chờ mặt hàng này mà thứ họ chờ GIỜ MUA ĐƯỢC.
     *
     * Dùng ở màn Kho online, nơi tồn của CẢ MẶT HÀNG vừa từ 0 lên. Việc ấy lật
     * `products`.`status` sang 'in_stock', và cột đó là cổng chặn đứng trước
     * mọi biến thể — nên thứ vừa mở ra không chỉ là mặt hàng gốc mà cả những
     * phương án vốn đã có tồn riêng. Lý do đầy đủ ở WaitlistModel::dangChoMuaDuoc().
     */
    public static function hangCoLaiCaMatHang(array $sanPham): int
    {
        return self::guiChoLuotCho(
            $sanPham,
            static fn (): array => WaitlistModel::dangChoMuaDuoc((string) $sanPham['id'])
        );
    }

    /**
     * Ruột chung của hai hàm trên: xếp thư cho một danh sách lượt chờ rồi đánh
     * dấu đúng những lượt đã sinh ra thư.
     *
     * @param callable(): array $doc hàm đọc danh sách — gọi trong try nên một
     *        CSDL trục trặc cũng không ném ra khỏi lớp này (FR-EM-06)
     */
    private static function guiChoLuotCho(array $sanPham, callable $doc): int
    {
        /* HOÃN RA SAU COMMIT NẾU ĐANG Ở TRONG MỘT TRANSACTION — cùng luật với
           chay(), và ở đây nó còn nặng hơn: một lô có thể tới 200 lá thư, tức
           200 câu INSERT chạy trong khi transaction của người khác đang giữ
           khoá. Xem Database::sauKhiCommit().

           Hôm nay chưa nơi gọi nào nằm trong transaction, nhưng nơi gọi HIỂN
           NHIÊN tiếp theo thì có: hoàn kho khi huỷ đơn, nằm giữa
           OrderModel::changeStatus(). Trả 0 khi hoãn — số thư thật chỉ đếm
           được lúc chạy, và không ai đọc con số này ngoài nhật ký lỗi. */
        if (Database::sauKhiCommit(fn (): int => self::guiChoLuotCho($sanPham, $doc))) {
            return 0;
        }

        try {
            if (!WaitlistModel::available() || !EmailQueueModel::available()) {
                return 0;
            }

            $cho = $doc();

            if ($cho === []) {
                return 0;
            }

            $link = self::goc() . '/san-pham/' . rawurlencode((string) ($sanPham['slug'] ?? ''));
            $daBao = [];

            foreach ($cho as $d) {
                $pa = trim((string) ($d['variant_label'] ?? ''));

                $id = EmailQueueModel::xepHang(
                    'kho.co_hang',
                    $d['email'] ?? null,
                    [
                        'ten_khach'     => (string) ($d['full_name'] ?? 'bạn'),
                        'ten_san_pham'  => (string) ($sanPham['name'] ?? ''),
                        'phuong_an'     => $pa !== '' ? '(' . $pa . ')' : '',
                        'link_san_pham' => $link,
                    ],
                    // Một lượt chờ chỉ sinh đúng một thư, mãi mãi.
                    'kho.co_hang:' . $d['id'],
                    null,
                    $d['user_id'] ?? null,
                    'product',
                    $sanPham['id'] ?? null
                );

                if ($id !== null) {
                    $daBao[] = $d['id'];
                }
            }

            WaitlistModel::danhDauDaBaoNhieu($daBao);

            return count($daBao);
        } catch (Throwable $e) {
            error_log('EmailEvents::hangCoLai: ' . $e->getMessage());

            return 0;
        }
    }
}
