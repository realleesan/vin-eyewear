<?php

/**
 * DashboardController — tổng quan khu quản trị (/quan-tri).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TRANG NÀY TRẢ LỜI CÂU HỎI GÌ
 *
 * Bản trước đếm bảy con số trên toàn bộ dữ liệu và dừng ở đó. Nó nói được "có
 * 33 đơn" nhưng không nói được "tháng này khá hơn hay kém hơn tháng trước", và
 * không nói được việc nào đang để khách chờ. Người mở bảng buổi sáng đọc xong
 * vẫn phải sang bốn trang khác mới biết phải làm gì trước.
 *
 * Nay trang có hai nửa, và ranh giới giữa chúng là thứ phải giữ khi sửa về sau:
 *
 *   NỬA TRÊN — theo KỲ đang chọn. Doanh thu, lợi nhuận, tỉ lệ, biểu đồ, top sản
 *   phẩm. Đây là phần để ĐỌC và so sánh; đổi kỳ thì mọi con số ở đây đổi theo.
 *
 *   NỬA DƯỚI — "CẦN XỬ LÝ GẤP" và ba danh sách. Đây là phần để LÀM, và nó cố ý
 *   KHÔNG ăn theo kỳ: chọn "Hôm nay" mà 32 đơn tồn từ tuần trước biến mất khỏi
 *   màn hình thì cái tên "cần xử lý gấp" thành nói dối. Xem DashboardStats
 *   ::canXuLy().
 *
 * Bộ lọc CƠ SỞ thì áp cho cả hai nửa, với những bảng có cột cơ sở.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỌI PHÉP TÍNH NẰM Ở DashboardStats
 *
 * Controller này chỉ làm ba việc: đọc request, kiểm giá trị, đưa xuống view.
 * Lý do tách — xem khối chú thích đầu app/services/DashboardStats.php.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG GHI VẾT THAO TÁC, VÀ ĐÓ LÀ ĐÚNG
 *
 * CLAUDE.md mục 5 buộc ghi nhật ký cho mọi thao tác LÀM THAY ĐỔI DỮ LIỆU. Trang
 * này không ghi một dòng nào vào CSDL, và cũng không hiện số đo khúc xạ của
 * khách ở đâu cả (thứ mà mục 6 buộc ghi vết cả khi chỉ ĐỌC). Thêm một vết cho
 * mỗi lượt mở trang mở nhất khu quản trị chỉ làm loãng bảng vết tới mức không
 * còn tra được gì.
 */

class DashboardController extends AdminController
{
    public function index(): void
    {
        $phamVi = $this->phamViCoSo();

        /* MỐC LÀM SÀN CHO KỲ, không thay thế kỳ. Xem DashboardStats::ky(). */
        $moc = self::mocThongKe();
        $ky  = DashboardStats::ky($_GET, $moc);

        $coSoChon    = DashboardStats::coSoChonDuoc($phamVi);
        $coSo        = $this->coSoHopLe($coSoChon);

        $tien   = DashboardStats::tien($ky, $coSo, $phamVi);
        $giaVon = DashboardStats::giaVon($ky, $coSo, $phamVi);
        $lich   = DashboardStats::lichHen($ky, $coSo, $phamVi);

        /*
         * ─────────────────────────────────────────────────────────────────────
         * BẢY CON SỐ CỦA HAI DẢI Ô ĐẾM — KHÔNG THEO KỲ, CÓ THEO CƠ SỞ
         *
         * Chúng là trạng thái HIỆN TẠI của các hàng chờ và của kho, đúng như
         * nửa dưới của trang. "Đơn hàng mới" theo kỳ thì bấm vào ô ghi 4 lại ra
         * một danh sách 33 dòng, vì trang Đơn hàng không có bộ lọc kỳ tương ứng
         * — và yêu cầu nghiệm thu số 4 đòi hai con số ấy khớp nhau.
         *
         * Đếm gộp MỘT câu lệnh thay vì sáu câu riêng: mỗi truy vấn là một vòng
         * đi-về tới CSDL và trang này mở rất thường xuyên.
         *
         * Ô "Liên hệ chưa đẩy" đọc `contact_requests`.`zalo_sent_at`, cột chỉ có
         * từ migration 2026-08-26-lien-he-qua-zalo. PHẢI HỎI TRƯỚC KHI NHẮC TỚI
         * NÓ: cả câu lệnh đi một lượt, nên một cột thiếu không làm hụt một ô mà
         * đổ nguyên câu với lỗi 1054 — trên đúng trang đầu tiên mọi nhân viên
         * nhìn thấy sau khi đăng nhập.
         * ─────────────────────────────────────────────────────────────────────
         */
        $demChuaDay = Database::columnExists('contact_requests', 'zalo_sent_at')
            ? '(SELECT COUNT(*) FROM contact_requests WHERE zalo_sent_at IS NULL)'
            : '0';

        /* Tính biểu thức RA BIẾN rồi nội suy: câu dưới là chuỗi nháy KÉP, viết
           ' . ... . ' trong đó thì nó thành chữ thường nằm giữa câu SQL chứ
           không phải phép nối — và php -l vẫn báo sạch. */
        $nguongSapHet = ProductModel::nguongSapHetSql();

        [$locDon, $paramsDon]   = $this->locDemDon($coSo, $phamVi);
        [$locLich, $paramsLich] = $this->locDemLich($coSo, $phamVi);

        $stats = Database::fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM products WHERE is_visible = 1)              AS products,
                -- Cùng ngưỡng với trang Tồn kho, và cùng lý do: mỗi mặt hàng có
                -- mốc riêng ở `low_stock_at`, để trống mới rơi về 5. Ô này dẫn
                -- thẳng sang trang ấy nên hai con số phải khớp.
                (SELECT COUNT(*) FROM products
                  WHERE stock_quantity > 0
                    AND stock_quantity <= {$nguongSapHet})                        AS low_stock,
                (SELECT COUNT(*) FROM categories WHERE is_visible = 1)            AS categories,
                (SELECT COUNT(*) FROM orders o WHERE o.status = 'new'{$locDon})   AS new_orders,
                (SELECT COUNT(*) FROM appointments a
                  WHERE a.status = 'pending'{$locLich})                           AS pending_appointments,
                {$demChuaDay}                                                     AS contacts_chua_day",
            $paramsDon + $paramsLich
        ) ?? [];

        $this->renderAdmin('admin/dashboard/index', [
            'pageTitle'  => 'Tổng quan — Quản trị Vin Eyewear',

            // ── Bộ lọc ──────────────────────────────────────────────────────
            'ky'         => $ky,
            'kyChon'     => DashboardStats::KY,
            'coSo'       => $coSo,
            'coSoChon'   => $coSoChon,
            'coSoGiao'   => DashboardStats::CO_SO_GIAO,
            /* View dùng cờ này để nói rõ vì sao con số thấp hơn mong đợi — cùng
               cách làm với trang Đơn hàng và Lịch hẹn. */
            'gioiHanCoSo' => $this->biGioiHanCoSo(),

            // ── Nửa trên: theo kỳ ───────────────────────────────────────────
            'tien'       => $tien,
            'giaVon'     => $giaVon,
            'lich'       => $lich,
            'top'        => DashboardStats::topSanPham($ky, $coSo, $phamVi),
            'bieuDo'     => DashboardStats::theoNgay($ky, $coSo, $phamVi),

            // ── Nửa dưới: hiện tại ──────────────────────────────────────────
            'stats'      => $stats,
            'canXuLy'    => DashboardStats::canXuLy($coSo, $phamVi),
            'nguongGio'  => DashboardStats::NGUONG_TON_DONG_GIO,

            /*
             * BA DANH SÁCH CUỐI TRANG — lọc theo CƠ SỞ, không theo kỳ.
             *
             * Chúng trả lời "đang có gì" chứ không "kỳ vừa rồi ra sao", đúng
             * như khối Cần xử lý gấp ngay trên. Chân mỗi thẻ nói rõ điều đó để
             * không ai đọc chúng như một phần của kỳ đang chọn.
             */
            'recentOrders' => Database::fetchAll(
                "SELECT o.* FROM orders o"
                . ($locDon === '' ? '' : ' WHERE 1 = 1' . $locDon)
                . ' ORDER BY o.created_at DESC LIMIT 8',
                $paramsDon
            ),
            /*
             * LỊCH HẸN SẮP TỚI — truy vấn riêng, không dùng BookingModel
             * ::withStore(). Hàm đó sắp ngày GIẢM DẦN vì nó viết cho trang danh
             * sách, nơi người ta muốn thấy cái vừa đặt trước tiên. Ở đây câu hỏi
             * ngược lại: hôm nay và mấy hôm tới ai sẽ đến.
             *
             * >= hôm nay chứ không phải > : buổi hẹn chiều nay vẫn là việc phải
             * chuẩn bị, và nó là dòng quan trọng nhất trong cả thẻ.
             */
            'recentBookings' => Database::fetchAll(
                "SELECT a.*, s.name AS store_name, s.code AS store_code
                   FROM appointments a
                   JOIN stores s ON s.id = a.store_id
                  WHERE a.appointment_date >= :hom_nay{$locLich}
                  ORDER BY a.appointment_date ASC, a.created_at ASC
                  LIMIT 8",
                ['hom_nay' => date('Y-m-d')] + $paramsLich
            ),
            /* Kho là MỘT, không chia theo cơ sở — bảng `products` không có cột
               cơ sở nào. Thẻ này vì thế luôn là toàn hệ thống, và chân thẻ nói
               ra điều đó khi người dùng đang lọc một cơ sở. */
            'lowStock'     => Database::fetchAll(
                'SELECT id, slug, name, sku, stock_quantity, status
                   FROM products
                  WHERE stock_quantity <= ' . ProductModel::nguongSapHetSql() . '
                  ORDER BY stock_quantity ASC
                  LIMIT 8'
            ),

            'orderStatuses'   => OrderModel::STATUSES,
            'bookingStatuses' => BookingModel::STATUSES,

            'adminStyles'  => ['assets/css/admin-dashboard.css'],
            'adminScripts' => ['assets/js/admin-dashboard.js'],
        ]);
    }

    /**
     * Giá trị ?co-so= sau khi kiểm — '' nếu không hợp lệ.
     *
     * KIỂM THEO DANH SÁCH CHỌN ĐƯỢC, không chỉ theo bảng `stores`. Một id cơ sở
     * có thật nhưng nằm ngoài phạm vi của người này thì vẫn phải rơi về '' —
     * nếu không, ô chọn hiện "Tất cả cơ sở" trong khi truy vấn đang lọc theo một
     * cơ sở họ không được xem, và họ thấy một trang trống không giải thích được.
     *
     * (Dữ liệu vẫn an toàn kể cả khi bỏ phép kiểm này: phạm vi được áp thêm ở
     * mọi truy vấn, nên chọn cơ sở ngoài phạm vi chỉ cho ra 0 dòng. Phép kiểm ở
     * đây là để MÀN HÌNH không nói dối, không phải để chặn rò rỉ.)
     *
     * @param list<array{id:string, name:string}> $coSoChon
     */
    private function coSoHopLe(array $coSoChon): string
    {
        $coSo = trim((string) ($_GET['co-so'] ?? ''));

        if ($coSo === '' || $coSo === DashboardStats::CO_SO_GIAO) {
            return $coSo;
        }

        return in_array($coSo, array_column($coSoChon, 'id'), true) ? $coSo : '';
    }

    /**
     * Mệnh đề cơ sở nối thêm vào các câu đếm — dạng ' AND (…)' hoặc ''.
     *
     * @return array{0:string, 1:array<string, string>}
     */
    private function locDemDon(string $coSo, ?array $phamVi): array
    {
        return $this->locDem($coSo, $phamVi, 'o.store_id', 'qd');
    }

    /** @return array{0:string, 1:array<string, string>} */
    private function locDemLich(string $coSo, ?array $phamVi): array
    {
        /* Lịch hẹn KHÔNG có nhóm "giao tận nơi": `appointments.store_id` là NOT
           NULL. Chọn nhóm ấy thì mọi con số lịch hẹn phải là 0, chứ không phải
           bỏ qua bộ lọc và hiện số của toàn hệ thống. */
        if ($coSo === DashboardStats::CO_SO_GIAO) {
            return [' AND 1 = 0', []];
        }

        return $this->locDem($coSo, $phamVi, 'a.store_id', 'ql');
    }

    /** @return array{0:string, 1:array<string, string>} */
    private function locDem(string $coSo, ?array $phamVi, string $cot, string $tien): array
    {
        $dieuKien = [];
        $params   = [];

        [$loc, $locParams] = StaffStoreModel::menhDe($phamVi, $cot, $tien . 'pv');

        if ($loc !== null) {
            $dieuKien[] = $loc;
            $params    += $locParams;
        }

        if ($coSo === DashboardStats::CO_SO_GIAO) {
            $dieuKien[] = $cot . ' IS NULL';
        } elseif ($coSo !== '') {
            $dieuKien[]              = $cot . ' = :' . $tien . 'cs';
            $params[$tien . 'cs'] = $coSo;
        }

        return [
            $dieuKien === [] ? '' : ' AND (' . implode(' AND ', $dieuKien) . ')',
            $params,
        ];
    }

    /**
     * Mốc tính doanh thu, dạng 'YYYY-MM-DD HH:MM:SS', hoặc null nếu không đặt.
     *
     * KIỂM ĐỊNH DẠNG RỒI MỚI DÙNG, và trả null khi sai thay vì ném lỗi.
     *
     * Giá trị này đến từ .env — file mà trên hosting người ta sửa tay bằng File
     * Manager, không qua bước kiểm nào. Gõ nhầm "26/08/2026" (kiểu Việt Nam)
     * thay vì "2026-08-26" là chuyện sẽ xảy ra. Ném lỗi ở đó nghĩa là TRANG ĐẦU
     * TIÊN sau khi đăng nhập trả 500 vì một dấu gạch chéo — mà lỗi ấy lại không
     * nói ra nguyên nhân.
     *
     * Trả null thì kỳ chạy tự do và dòng dẫn không nhắc tới mốc nào, nên người
     * đặt mốc nhìn ra ngay là nó chưa ăn.
     */
    private static function mocThongKe(): ?string
    {
        $raw = trim((string) config('app.thong_ke_tu', ''));

        if ($raw === '') {
            return null;
        }

        // Chỉ có ngày thì lấy từ đầu ngày hôm đó.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            $raw .= ' 00:00:00';
        }

        $d   = DateTime::createFromFormat('Y-m-d H:i:s', $raw);
        $loi = DateTime::getLastErrors();

        if ($d === false || ($loi !== false && ($loi['warning_count'] ?? 0) > 0)) {
            error_log('[Tổng quan] STATS_SINCE sai định dạng, bỏ qua mốc: ' . $raw);

            return null;
        }

        return $d->format('Y-m-d H:i:s');
    }
}
