<?php

/**
 * SepayRelay — website tự sang cầu nối lấy giao dịch về.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO WEBSITE PHẢI ĐI LẤY, THAY VÌ NGỒI ĐỢI NGƯỜI TA MANG TỚI
 *
 * InfinityFree bản miễn phí đặt một lớp chống bot TRƯỚC Apache. Ai gọi vào mà
 * không phải trình duyệt thì nhận một trang HTML đố JavaScript, không tới được
 * index.php. Máy chủ SePay không chạy JS, nên webhook gửi thẳng vào đây KHÔNG
 * BAO GIỜ tới SepayController — và không để lại dấu vết nào trong error log để
 * mà lần. Đó là toàn bộ lý do có thư mục relay/ trong repo này.
 *
 * Cầu nối trên Render nhận webhook thay, rồi ĐẨY sang đây (nó tự giải được lời
 * đố — relay/lib/infinityfree.js). Nhưng đường đẩy phụ thuộc vào việc lời đố
 * không đổi kiểu, mà đó là thứ InfinityFree đổi lúc nào cũng được.
 *
 * File này là đường CÒN LẠI: website gọi RA NGOÀI. Chiều đó không có tường nào
 * chắn (chính chiều mà GoogleAuth và Zalo vẫn đi hằng ngày), nên nó là đường
 * KHÔNG HỎNG. Đường đẩy nhanh hơn; đường này chắc hơn. Giữ cả hai.
 * ─────────────────────────────────────────────────────────────────────────────
 * GỌI Ở ĐÂU, VÀ VÌ SAO KHÔNG PHẢI Ở MỌI TRANG
 *
 * Hosting này không có cron. Nên "định kỳ" ở đây nghĩa là ăn theo lượt truy cập
 * có sẵn, và chỉ ở hai chỗ mà việc chậm một nhịp gây hại thật:
 *
 *   OrderController::payStatus     khách đang đứng trước mã QR, pay-watch.js
 *                                  hỏi mỗi 4 giây trong hai phút đầu. Đây đúng
 *                                  là lúc tiền đang về.
 *   OrderAdminController::index    nhân viên mở danh sách đơn để đối chiếu.
 *
 * KHÔNG gắn vào mọi trang: mỗi lần kéo là một lần gọi HTTPS ra ngoài, và trang
 * chủ của một site bán hàng không có lý do gì phải chờ Render trả lời.
 * ─────────────────────────────────────────────────────────────────────────────
 * CHỈ ACK SAU KHI ĐÃ GHI VÀO SỔ
 *
 * Ack (báo cầu nối "cái này tôi nhận rồi, bỏ khỏi hàng đợi") chỉ được gửi sau
 * khi SepayModel::handle() chạy xong không ném lỗi. Ack sớm rồi CSDL hỏng giữa
 * chừng là mất hẳn giao dịch: cầu nối xoá nó đi, SePay thì tưởng đã giao xong
 * từ lâu, và tiền về tài khoản trong khi đơn treo mãi ở 'unpaid'.
 *
 * Chậm một nhịp thì chỉ là chậm. Ack sai thì mất luôn.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class SepayRelay
{
    /** Hạn giờ mỗi lần gọi cầu nối, tính bằng giây. */
    private const HAN_GIO     = 6;
    private const HAN_GIO_NOI = 4;

    /** Nhiều nhất bao nhiêu vòng kéo trong một lượt. */
    private const TOI_DA_VONG = 3;

    /** Lấy nhiều nhất bao nhiêu giao dịch một vòng. */
    private const MOI_VONG = 20;

    /** Nhịp nghỉ tối đa sau khi gọi hỏng, tính bằng giây. */
    private const NHIP_TOI_DA = 120;

    /** Đã khai đủ để đi lấy chưa? */
    public static function batDuoc(): bool
    {
        return self::url() !== '' && self::khoa() !== '';
    }

    /**
     * Kéo hàng đợi về và ghi vào sổ.
     *
     * @param bool $bqNhip bỏ qua nhịp nghỉ, kéo ngay (dùng cho trang chẩn đoán)
     * @return array{keo:int, loi:?string, bo_qua:?string}
     *         `keo` là số giao dịch đã ghi vào sổ lần này.
     */
    public static function keo(bool $bqNhip = false): array
    {
        if (!self::batDuoc()) {
            return ['keo' => 0, 'loi' => null, 'bo_qua' => 'chua-cau-hinh'];
        }

        /* Chưa chạy migration thì bảng sổ chưa có. Kéo về mà không ghi được là
           tự tay vứt giao dịch: cầu nối trao ra, ta không ghi nổi, và cũng
           không ack — nhưng đã tốn một lượt gọi vô ích. Đứng yên tốt hơn. */
        if (!SepayModel::available()) {
            return ['keo' => 0, 'loi' => null, 'bo_qua' => 'chua-co-bang'];
        }

        /*
         * MỘT NGƯỜI KÉO MỘT LÚC, VÀ KHÔNG KÉO DÀY HƠN NHỊP.
         *
         * pay-watch.js hỏi mỗi 4 giây, mà một màn QR có thể đang mở trên nhiều
         * máy cùng lúc. Không có khoá thì mười lượt truy cập song song thành
         * mười lần gọi Render, và mỗi lần đều lôi về cùng một giao dịch để rồi
         * cùng ném lỗi trùng khoá UNIQUE.
         *
         * flock không chặn (LOCK_NB): ai đang kéo thì kệ họ, lượt này đi tiếp
         * ngay. Chờ tới lượt khoá là bắt khách chờ theo — mà thứ họ đang chờ
         * (tiền về) thì người kia đang lấy giúp rồi.
         */
        $tep = self::tepNhip();
        $fh  = @fopen($tep, 'c+');

        if ($fh === false) {
            /* Không ghi được vào storage/ (hosting đặt thư mục chỉ đọc). Vẫn
               kéo, chỉ là mất phần điều nhịp — thà gọi hơi dày còn hơn không
               bao giờ nhận được tiền về. */
            error_log('[SePay] Không mở được tệp nhịp ' . $tep . ' — kéo không điều nhịp.');

            return self::keoThat();
        }

        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);

            return ['keo' => 0, 'loi' => null, 'bo_qua' => 'nguoi-khac-dang-keo'];
        }

        $nhip = self::docNhip($fh);

        if (!$bqNhip && (time() - $nhip['lan']) < $nhip['cho']) {
            flock($fh, LOCK_UN);
            fclose($fh);

            return ['keo' => 0, 'loi' => null, 'bo_qua' => 'chua-toi-nhip'];
        }

        $kq = self::keoThat();

        /*
         * LÙI DẦN KHI GỌI HỎNG.
         *
         * Render gói miễn phí NGỦ sau 15 phút không ai gọi, và tỉnh dậy mất
         * khoảng 50 giây — lâu hơn hạn giờ 6 giây ở đây. Nếu cứ 3 giây lại gọi
         * một lần thì suốt lúc nó đang tỉnh, mỗi khách đứng trước màn QR phải
         * chờ thêm 6 giây cho một câu trả lời sẽ không tới.
         *
         * Nên hỏng thì gấp đôi nhịp nghỉ, tối đa 2 phút; được thì về nhịp gốc.
         * Lượt gọi đầu vẫn đủ để ĐÁNH THỨC Render, nên chờ lâu hơn không làm
         * mất gì — nó dậy xong thì lượt kéo sau lấy hết một thể.
         */
        $nhipMoi = $kq['loi'] === null
            ? self::nhipGoc()
            : min(self::NHIP_TOI_DA, max(self::nhipGoc(), $nhip['cho']) * 2);

        self::ghiNhip($fh, ['lan' => time(), 'cho' => $nhipMoi]);

        flock($fh, LOCK_UN);
        fclose($fh);

        return $kq;
    }

    /** Vòng kéo thật, không lo khoá và nhịp. */
    private static function keoThat(): array
    {
        $ack     = [];
        $daGhi   = 0;
        $loi     = null;

        for ($vong = 0; $vong < self::TOI_DA_VONG; $vong++) {
            $traLoi = self::goi(['ack' => $ack, 'gioi_han' => self::MOI_VONG]);

            if ($traLoi === null) {
                /* Gọi hỏng SAU khi đã ghi sổ mà chưa kịp ack: không mất gì.
                   Cầu nối vẫn giữ những giao dịch đó, lượt kéo sau lấy lại,
                   và SepayModel::handle() chống trùng bằng UNIQUE sepay_id. */
                $loi = 'Không gọi được cầu nối.';
                break;
            }

            $ack = [];
            $ds  = is_array($traLoi['giao_dich'] ?? null) ? $traLoi['giao_dich'] : [];

            foreach ($ds as $txn) {
                if (!is_array($txn) || (int) ($txn['id'] ?? 0) <= 0) {
                    continue;
                }

                try {
                    $kq = SepayModel::handle($txn);

                    // Ghi được (kể cả kết luận "không làm gì cả") -> ack.
                    $ack[] = (int) $txn['id'];
                    $daGhi++;

                    error_log(sprintf(
                        '[SePay] Kéo về #%d %s -> %s%s',
                        (int) $txn['id'],
                        money((int) round((float) ($txn['transferAmount'] ?? 0))),
                        $kq['status'],
                        isset($kq['order_code']) && $kq['order_code'] !== null
                            ? ' (' . $kq['order_code'] . ')' : ''
                    ));
                } catch (Throwable $e) {
                    /* KHÔNG ack. Giao dịch nằm lại bên cầu nối, lượt sau kéo
                       lại. Đây đúng là ca mà việc ack sớm sẽ làm mất tiền. */
                    error_log('[SePay] Kéo về #' . (int) $txn['id']
                        . ' nhưng ghi sổ hỏng: ' . $e->getMessage());
                }
            }

            // Hết đồ trong hộp -> vòng cuối chỉ còn việc gửi nốt ack.
            if ($ds === []) {
                $ack = [];
                break;
            }
        }

        // Ack đợt cuối. Bỏ qua kết quả: ack rơi thì lượt kéo sau ack lại.
        if ($ack !== []) {
            self::goi(['ack' => $ack, 'gioi_han' => 1]);
        }

        return ['keo' => $daGhi, 'loi' => $loi, 'bo_qua' => null];
    }

    /**
     * Gọi POST <cầu nối>/api/keo.
     *
     * @return array|null mảng đã json_decode, hoặc null khi hỏng
     */
    private static function goi(array $than): ?array
    {
        $url  = rtrim(self::url(), '/') . '/api/keo';
        $body = json_encode($than, JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Apikey ' . self::khoa(),
            'Accept: application/json',
        ];

        /* Đi cURL nếu có, không thì file_get_contents — cùng cách xoay xở với
           GoogleAuth::http(). Có hosting không bật extension cURL, và một tích
           hợp chết bằng "Call to undefined function curl_init()" thì không ai
           đoán ra nguyên nhân. */
        $tho = extension_loaded('curl')
            ? self::quaCurl($url, $headers, $body)
            : self::quaStream($url, $headers, $body);

        if ($tho === null) {
            return null;
        }

        $data = json_decode($tho, true);

        if (!is_array($data) || ($data['ok'] ?? false) !== true) {
            /* Cầu nối trả 401 khi khoá lệch, 403 khi PULL_KEY chưa khai. Cả
               hai đều là lỗi cấu hình im lặng — ghi log kèm nguyên văn, vì
               chẩn đoán ở mục 7 của kiem-tra-sepay.php đọc lại từ đây. */
            error_log('[SePay] Cầu nối trả về thứ không dùng được: ' . substr($tho, 0, 300));

            return null;
        }

        return $data;
    }

    private static function quaCurl(string $url, array $headers, string $body): ?string
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => self::HAN_GIO,
            CURLOPT_CONNECTTIMEOUT => self::HAN_GIO_NOI,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $tho = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($tho === false) {
            error_log('[SePay] cURL gọi cầu nối lỗi: ' . $err);

            return null;
        }

        return (string) $tho;
    }

    private static function quaStream(string $url, array $headers, string $body): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => self::HAN_GIO,
                'ignore_errors' => true,   // đọc được thân phản hồi của 4xx/5xx
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $tho = @file_get_contents($url, false, $ctx);

        if ($tho === false) {
            error_log('[SePay] Không gọi được cầu nối ' . $url . ' (file_get_contents).');

            return null;
        }

        return $tho;
    }

    // ── Nhịp: một tệp nhỏ trong storage/ ────────────────────────────────

    private static function tepNhip(): string
    {
        $dir = ROOT_PATH . '/storage/sepay';

        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            // Trả về đường dẫn kệ nó — fopen bên trên sẽ hỏng và tự xử lý.
            error_log('[SePay] Không tạo được thư mục ' . $dir);
        }

        return $dir . '/nhip-keo.json';
    }

    /** @return array{lan:int, cho:int} */
    private static function docNhip($fh): array
    {
        rewind($fh);
        $tho  = (string) stream_get_contents($fh);
        $data = json_decode($tho, true);

        return [
            'lan' => (int) ($data['lan'] ?? 0),
            'cho' => max(1, (int) ($data['cho'] ?? self::nhipGoc())),
        ];
    }

    private static function ghiNhip($fh, array $nhip): void
    {
        rewind($fh);
        ftruncate($fh, 0);
        fwrite($fh, json_encode($nhip));
        fflush($fh);
    }

    private static function nhipGoc(): int
    {
        return max(1, (int) config('sepay.relay_interval', 3));
    }

    private static function url(): string
    {
        return trim((string) config('sepay.relay_url', ''));
    }

    private static function khoa(): string
    {
        return trim((string) config('sepay.relay_key', ''));
    }
}
