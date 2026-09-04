<?php

/**
 * admin/dashboard/index.php — tổng quan.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TRANG CHIA HAI NỬA, VÀ RANH GIỚI ẤY LÀ NỘI DUNG
 *
 *   NỬA TRÊN theo KỲ đang chọn — thanh lọc, hàng chỉ số, biểu đồ, top sản phẩm.
 *   Phần để ĐỌC và so sánh.
 *
 *   NỬA DƯỚI là HIỆN TẠI — "Cần xử lý gấp", hai dải ô đếm, ba danh sách. Phần
 *   để LÀM. Nó cố ý không ăn theo kỳ; vì sao — xem DashboardStats::canXuLy().
 *
 * Mỗi thẻ ở nửa dưới đều tự nói ra là mình không theo kỳ. Không có dòng ấy thì
 * người vừa chọn "Hôm nay" đọc "32 đơn quá 24 giờ" và tưởng 32 đơn đó phát sinh
 * hôm nay.
 * ─────────────────────────────────────────────────────────────────────────────
 */

/* ─────────────────────────────────────────────────────────────────────────────
   HAI HÀM ĐỊNH DẠNG CHỈ DÙNG Ở TRANG NÀY

   Không đẩy lên core/helpers.php: chúng gắn chặt với cách trang này trình bày
   (phần trăm một chữ số thập phân, tiền rút gọn cho trục biểu đồ). Một helper
   toàn cục thì trang khác sẽ dùng, rồi đổi nó là đổi cả hai chỗ.
   ───────────────────────────────────────────────────────────────────────────*/

/** Phần trăm kiểu Việt Nam: dấu phẩy thập phân, luôn có dấu + hoặc −. */
$phanTram = static function (?float $v): string {
    if ($v === null) {
        return '—';
    }

    // Dấu TRỪ THẬT (U+2212), không phải dấu gạch nối: ở cỡ chữ nhỏ dấu gạch
    // nối ngắn tới mức "-8,3%" đọc ra như "8,3%".
    $dau = $v > 0 ? '+' : ($v < 0 ? "\u{2212}" : '');

    return $dau . number_format(abs($v), 1, ',', '.') . '%';
};

/**
 * Tiền rút gọn cho nhãn trục tung — "2,4 tr", "850 ng", "0".
 *
 * Trục tung của biểu đồ rộng chừng 60px trong khung nhìn SVG. Một con số đầy đủ
 * "12.480.000₫" không lọt, và cắt cụt nó bằng dấu ba chấm thì nhãn trục thành
 * vô nghĩa. Con số đầy đủ vẫn đọc được ở chú giải từng cột.
 */
$tienNgan = static function (int $v): string {
    if ($v >= 1000000) {
        return number_format($v / 1000000, $v >= 10000000 ? 0 : 1, ',', '.') . ' tr';
    }

    if ($v >= 1000) {
        return number_format($v / 1000, 0, ',', '.') . ' ng';
    }

    return (string) $v;
};

/* ── Các con số dẫn xuất ────────────────────────────────────────────────── */

$doanhThu   = (int) ($tien['doanhThu'] ?? 0);
$soDonThu   = (int) ($tien['soDonThu'] ?? 0);
$tongDon    = (int) ($tien['tongDon'] ?? 0);
$donHuy     = (int) ($tien['donHuy'] ?? 0);

$loiNhuan   = $doanhThu - (int) $giaVon['giaVon'];

/* GIÁ TRỊ ĐƠN TRUNG BÌNH CHIA CHO SỐ ĐƠN ĐÃ THU ĐỦ, không chia cho tổng số đơn.
   Tử số là doanh thu — tiền đã về — nên mẫu số phải là chính những đơn sinh ra
   số tiền ấy. Chia cho tổng số đơn (gồm cả đơn chưa trả tiền) cho ra một con số
   không phải giá trị trung bình của cái gì cả, và nó tụt xuống mỗi khi có nhiều
   đơn mới chưa thanh toán — tức là tụt xuống đúng lúc bán được nhiều. */
$donTb      = $soDonThu > 0 ? (int) round($doanhThu / $soDonThu) : null;

$tiLeHuy    = $tongDon > 0 ? ($donHuy / $tongDon) * 100 : null;

$daChot     = (int) $lich['daChot'];
$tiLeKhongDen = $daChot > 0 ? ((int) $lich['khongDen'] / $daChot) * 100 : null;

$soDongVon   = (int) $giaVon['soDong'];
$soDongCoVon = (int) $giaVon['soDongCoVon'];
$duVon       = $soDongVon > 0 && $soDongCoVon === $soDongVon;

/* ── Dải ô đếm: MỌI Ô ĐỀU BẤM ĐƯỢC, và mỗi ô dẫn tới danh sách ĐÃ LỌC SẴN ──

   Không có ô nào chỉ để nhìn nữa. Một con số trên bảng điều khiển mà bấm vào
   không đi đâu thì người đọc phải tự tìm lại nó ở trang khác bằng tay, và lần
   nào cũng có nguy cơ lọc ra một tập khác với tập vừa đếm.

   Đường dẫn phải khớp ĐÚNG điều kiện đã đếm, nếu không thì ô ghi 4 bấm vào ra
   bảng 33 dòng — xem ghi chú ở DashboardController::index() về `low_stock`. */
$queues = [
    ['value' => (int) ($stats['new_orders'] ?? 0),
     'label' => 'đơn hàng mới chờ xác nhận',
     'url'   => '/quan-tri/don-hang?status=new',
     'warn'  => true],
    ['value' => (int) ($stats['pending_appointments'] ?? 0),
     'label' => 'lịch hẹn chờ xác nhận',
     'url'   => '/quan-tri/lich-hen?status=pending',
     'warn'  => true],
    /* Ô này phải luôn bằng 0. Khác 0 nghĩa là ZNS đang hỏng và có người thật
       đang chờ gọi lại mà CSKH chưa biết. */
    ['value' => (int) ($stats['contacts_chua_day'] ?? 0),
     'label' => 'liên hệ chưa tới Zalo CSKH',
     'url'   => '/quan-tri/lien-he?zalo=chua',
     'warn'  => true],
];

$facts = [
    ['value' => (int) ($stats['products'] ?? 0),
     'label' => 'sản phẩm đang hiển thị',
     'url'   => '/quan-tri/san-pham'],
    ['value' => (int) ($stats['low_stock'] ?? 0),
     'label' => 'sản phẩm sắp hết hàng',
     'url'   => '/quan-tri/ton-kho?loc=low', 'warn' => true],
    ['value' => (int) ($stats['categories'] ?? 0),
     'label' => 'danh mục',
     'url'   => '/quan-tri/danh-muc'],
];

/* Nhãn cơ sở đang lọc, để nhắc lại trong các dòng dẫn. */
$tenCoSo = '';

if ($coSo === $coSoGiao) {
    $tenCoSo = 'Giao tận nơi';
} elseif ($coSo !== '') {
    foreach ($coSoChon as $s) {
        if ((string) $s['id'] === $coSo) {
            $tenCoSo = (string) $s['name'];
            break;
        }
    }
}

$tongCanXuLy = (int) $canXuLy['donMoi']['tong']
    + (int) $canXuLy['lichQuaHan']['tong']
    + (int) $canXuLy['hetHang']['tong']
    + (int) $canXuLy['lienHe']['tong'];
?>

<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Tổng quan</h1>

        <?php /* DÒNG DẪN PHẢI TRẢ LỜI BA CÂU MỘT LÚC: đang xem khoảng nào, con
                 số gồm những gì, và loại trừ những gì. Bản trước ghi "Tiền tính
                 từ 05/08/2026" mà không nói vì sao lại là mốc đó — người đọc
                 không biết đấy là kỳ đang chọn hay một mốc cấu hình, nên cũng
                 không biết có đổi được không. */ ?>
        <p class="ahead__lead">
            Tính từ <strong><?= e(formatDate($ky['tu'], 'd/m/Y')) ?></strong>
            đến <strong><?= e(formatDate($ky['den'], 'd/m/Y')) ?></strong>
            (<?= (int) $ky['soNgay'] ?> ngày)<?php if ($tenCoSo !== ''): ?>,
                cơ sở <strong><?= e($tenCoSo) ?></strong><?php endif; ?>.
            Doanh thu là tiền đã thu đủ của đơn đặt trong kỳ, không tính đơn đã huỷ.
        </p>

        <?php /* Mốc STATS_SINCE đã kẹp kỳ lại — PHẢI NÓI RA. Không có dòng này
                 thì chọn "30 ngày qua" ra 12 ngày dữ liệu và không có gì trên
                 màn hình giải thích được vì sao. */ ?>
        <?php if (!empty($ky['biKepBoiMoc'])): ?>
            <p class="ahead__lead adash__canhbao">
                Kỳ đã được cắt lại từ <strong><?= e(formatDate($ky['moc'], 'd/m/Y')) ?></strong> —
                cấu hình STATS_SINCE trong .env không cho thống kê đọc ngược về trước ngày đó.
            </p>
        <?php endif; ?>

        <?php if (!empty($ky['biCatNgan'])): ?>
            <p class="ahead__lead adash__canhbao">
                Khoảng tự chọn dài quá <?= (int) DashboardStats::TOI_DA_NGAY ?> ngày nên đã
                lấy lùi lại từ ngày kết thúc.
            </p>
        <?php endif; ?>

        <?php /* Nhân viên bị giới hạn phạm vi phải biết vì sao con số của mình
                 thấp hơn con số đồng nghiệp đọc ra — cùng dòng nhắc đã có ở
                 trang Đơn hàng và Lịch hẹn. */ ?>
        <?php if ($gioiHanCoSo): ?>
            <p class="ahead__lead">
                Bạn chỉ thấy dữ liệu của cơ sở mình được phân công.
            </p>
        <?php endif; ?>
    </div>

    <p class="ahead__today">Hôm nay · <?= e(date('d/m/Y')) ?></p>
</header>

<!-- ═══════════ THANH LỌC — KỲ VÀ CƠ SỞ ═══════════ -->
<?php
/*
 * MỘT FORM GET, KHÔNG PHẢI MỘT DÃY LIÊN KẾT.
 *
 * Form GET đẩy lựa chọn thẳng lên thanh địa chỉ, nên chia sẻ đường dẫn hay tải
 * lại trang đều giữ nguyên — đúng yêu cầu. Nó cũng là cách duy nhất nhận được
 * hai ô ngày của kỳ tuỳ chọn mà không cần một dòng JavaScript nào.
 *
 * NÚT "ÁP DỤNG" LUÔN CÓ THẬT. admin-dashboard.js tự gửi form khi đổi ô chọn và
 * lúc đó ẩn nút đi; tắt JS thì nút hiện nguyên và mọi thứ vẫn chạy. Đây là nếp
 * chung của dự án: JS chỉ TĂNG CƯỜNG.
 *
 * HAI Ô NGÀY KHÔNG BỊ ẨN BẰNG CSS khi kỳ khác "Tuỳ chọn". Ẩn bằng CSS thì người
 * tắt JS không bao giờ chọn được khoảng riêng. Chúng chỉ mờ đi và có dòng nhắc;
 * JS thu chúng lại khi không cần.
 */
?>
<form class="adash__loc" method="get" action="/quan-tri" id="adash-loc">
    <div class="adash__loc-nhom">
        <label class="adash__loc-nhan" for="adash-ky">Kỳ</label>
        <select class="adash__loc-o" id="adash-ky" name="ky" data-adash-ky>
            <?php foreach ($kyChon as $ma => $nhan): ?>
                <option value="<?= e($ma) ?>"<?= $ky['ma'] === $ma ? ' selected' : '' ?>><?= e($nhan) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="adash__loc-nhom adash__loc-nhom--ngay" data-adash-ngay>
        <label class="adash__loc-nhan" for="adash-tu">Từ ngày</label>
        <input class="adash__loc-o" type="date" id="adash-tu" name="tu"
               value="<?= e($ky['tu']) ?>" max="<?= e(date('Y-m-d')) ?>">
    </div>

    <div class="adash__loc-nhom adash__loc-nhom--ngay" data-adash-ngay>
        <label class="adash__loc-nhan" for="adash-den">Đến ngày</label>
        <input class="adash__loc-o" type="date" id="adash-den" name="den"
               value="<?= e($ky['den']) ?>" max="<?= e(date('Y-m-d')) ?>">
    </div>

    <div class="adash__loc-nhom">
        <label class="adash__loc-nhan" for="adash-coso">Cơ sở</label>
        <select class="adash__loc-o" id="adash-coso" name="co-so">
            <option value=""<?= $coSo === '' ? ' selected' : '' ?>>Tất cả cơ sở</option>
            <?php foreach ($coSoChon as $s): ?>
                <option value="<?= e((string) $s['id']) ?>"<?= $coSo === (string) $s['id'] ? ' selected' : '' ?>><?= e((string) $s['name']) ?></option>
            <?php endforeach; ?>
            <?php /* MỤC THỨ TƯ, KHÔNG PHẢI MỘT Ô THỪA.

                     `orders.store_id` chỉ có nghĩa với đơn nhận tại cửa hàng;
                     đơn giao tận nơi để NULL. Thiếu mục này thì chọn từng cơ sở
                     rồi cộng lại LUÔN THIẾU đúng số đơn giao hàng, mà không có
                     gì trên màn hình nói ra chỗ thiếu ấy. Có nó thì bốn mục cộng
                     lại đúng bằng "Tất cả cơ sở". */ ?>
            <?php if ($coSoChon !== []): ?>
                <option value="<?= e($coSoGiao) ?>"<?= $coSo === $coSoGiao ? ' selected' : '' ?>>Giao tận nơi (không thuộc cơ sở)</option>
            <?php endif; ?>
        </select>
    </div>

    <button type="submit" class="adash__loc-nut" data-adash-nut>Áp dụng</button>

    <p class="adash__loc-nhac" data-adash-nhac>
        Hai ô ngày chỉ có tác dụng khi kỳ là <strong>Tuỳ chọn</strong>.
    </p>
</form>

<!-- ═══════════ HÀNG CHỈ SỐ — THEO KỲ ═══════════ -->
<ul class="adash__kpis" role="list">

    <?php /* Thẻ tiền đeo nền tối, đúng như trước: nó là KẾT QUẢ để đọc, khác
             loài với những ô dẫn tới một việc phải làm. Nó vẫn là thẻ duy nhất
             không bấm được. */ ?>
    <li>
        <div class="astat astat--money">
            <span class="astat__label">Doanh thu trong kỳ</span>
            <span class="astat__value"><?= e(money($doanhThu)) ?></span>
            <span class="astat__note">
                <?= $soDonThu > 0
                    ? 'từ ' . $soDonThu . ' đơn đã thu đủ tiền'
                    : 'chưa có đơn nào thu đủ tiền trong kỳ' ?>
            </span>

            <?php
            $ptDoanhThu = DashboardStats::phanTram($doanhThu, (int) $tien['doanhThuTruoc']);
            ?>
            <?php /* NHÃN NGẮN Ở ĐÂY, KHOẢNG NGÀY XUỐNG DÒNG NOTE NGAY DƯỚI.

                     Đã thử để cả "So với 22/08–28/08" làm nhãn. Nhãn của
                     .astat__extra là chữ HOA có giãn khoảng, nên trong một thẻ
                     rộng ~220px nó gãy làm hai dòng đúng giữa khoảng ngày
                     ("22/08–" / "28/08") — đọc ra như hai mốc rời nhau. */ ?>
            <span class="astat__extra">
                <span class="astat__extra-label">So với kỳ trước</span>
                <span class="astat__extra-value adash__delta<?= $ptDoanhThu === null ? '' : ($ptDoanhThu >= 0 ? ' adash__delta--len' : ' adash__delta--xuong') ?>">
                    <?= e($phanTram($ptDoanhThu)) ?>
                </span>
            </span>

            <?php /* NÓI RA ĐÚNG KHOẢNG NGÀY ĐANG ĐEM RA SO. "Kỳ trước" là CÙNG
                     SỐ NGÀY dán sát ngay trước, không phải "tháng trước" theo
                     lịch (xem DashboardStats::ky()) — mà đó không phải thứ đoán
                     được từ hai chữ "kỳ trước".

                     KHÔNG in "+100%" khi kỳ trước bằng 0: chia cho 0 không có
                     kết quả, và một con số bịa ra ở đây trông hệt như một con số
                     đo được. */ ?>
            <span class="astat__note">
                kỳ trước <?= e(formatDate($ky['tuTruoc'], 'd/m')) ?>–<?= e(formatDate($ky['denTruoc'], 'd/m')) ?><?=
                    $ptDoanhThu === null ? ', không có doanh thu nên không so được' : '' ?>
            </span>

            <span class="astat__extra">
                <span class="astat__extra-label">Tạm thu (tiền cọc)</span>
                <span class="astat__extra-value"><?= e(money((int) $tien['tamThu'])) ?></span>
            </span>
        </div>
    </li>

    <li>
        <div class="astat">
            <span class="astat__label">Lợi nhuận gộp</span>
            <span class="astat__value"><?= e(money($loiNhuan)) ?></span>
            <span class="astat__note">
                doanh thu <?= e(money($doanhThu)) ?> − giá vốn <?= e(money((int) $giaVon['giaVon'])) ?>
            </span>

            <?php
            /*
             * ĐỘ PHỦ GIÁ VỐN LÀ MỘT PHẦN CỦA CON SỐ, KHÔNG PHẢI MỘT GHI CHÚ.
             *
             * `products.cost_price` để trống được, và dòng hàng không có giá vốn
             * đóng góp 0 vào giá vốn — tức là lợi nhuận CAO HƠN sự thật. Sai
             * theo hướng nguy hiểm nhất là luôn đẹp hơn thực tế.
             *
             * Không có cách nào đoán ra con số thiếu, nên thứ duy nhất làm được
             * là nói ra. "12/40 dòng hàng có giá vốn" vừa cảnh báo vừa chỉ đúng
             * việc phải làm; một con số trần trụi không làm được cả hai.
             */
            ?>
            <?php if ($soDongVon === 0): ?>
                <span class="astat__note astat__note--canh">Kỳ này chưa có dòng hàng nào đã thu tiền.</span>
            <?php elseif (!$duVon): ?>
                <span class="astat__note astat__note--canh">
                    Chỉ <?= $soDongCoVon ?>/<?= $soDongVon ?> dòng hàng có giá vốn —
                    số còn lại tính giá vốn bằng 0 nên lợi nhuận đang cao hơn thực tế.
                    <a href="/quan-tri/san-pham">Điền giá vốn →</a>
                </span>
            <?php else: ?>
                <span class="astat__note">đủ giá vốn cho <?= $soDongVon ?>/<?= $soDongVon ?> dòng hàng</span>
            <?php endif; ?>

            <?php /* Tiền tròng nằm trong `unit_price` nhưng tròng không có giá
                     vốn ở bảng nào — nói ra một lần ở đây, đừng để người đọc tự
                     phát hiện lúc đối chiếu sổ. */ ?>
            <span class="astat__note">Chưa trừ giá vốn tròng và chi phí vận hành.</span>
        </div>
    </li>

    <li>
        <div class="astat">
            <span class="astat__label">Giá trị đơn trung bình</span>
            <span class="astat__value"><?= $donTb === null ? '—' : e(money($donTb)) ?></span>
            <span class="astat__note">
                <?= $donTb === null
                    ? 'chưa có đơn nào thu đủ tiền để tính'
                    : 'trên ' . $soDonThu . ' đơn đã thu đủ tiền' ?>
            </span>
        </div>
    </li>

    <li>
        <div class="astat">
            <span class="astat__label">Tỉ lệ huỷ đơn</span>
            <span class="astat__value"><?= $tiLeHuy === null ? '—' : e(number_format($tiLeHuy, 1, ',', '.')) . '%' ?></span>
            <span class="astat__note">
                <?= $tiLeHuy === null
                    ? 'chưa có đơn nào trong kỳ'
                    : $donHuy . '/' . $tongDon . ' đơn đặt trong kỳ đã bị huỷ' ?>
            </span>
        </div>
    </li>

    <li>
        <div class="astat">
            <span class="astat__label">Khách không đến</span>
            <span class="astat__value"><?= $tiLeKhongDen === null ? '—' : e(number_format($tiLeKhongDen, 1, ',', '.')) . '%' ?></span>

            <?php
            /*
             * MẪU SỐ LÀ BUỔI ĐÃ CHỐT KẾT QUẢ (đã hoàn tất + khách không đến),
             * không phải tổng số lịch — xem DashboardStats::lichHen().
             *
             * Con số này CHỈ ĐÁNG TIN KHI NHÂN VIÊN CÓ BẤM NÚT. Lịch quá ngày mà
             * chưa ai chốt thì nó không nằm ở tử số lẫn mẫu số, nên tỉ lệ vẫn
             * đẹp trong khi thực tế có thể ngược lại. Dòng nhắc dưới đây là chỗ
             * duy nhất người đọc nhìn ra điều đó.
             */
            ?>
            <?php if ((int) $lich['tong'] === 0): ?>
                <span class="astat__note">
                    <?= $coSo === $coSoGiao
                        ? 'không áp dụng — lịch hẹn luôn thuộc một cơ sở'
                        : 'không có lịch hẹn nào trong kỳ' ?>
                </span>
            <?php elseif ($tiLeKhongDen === null): ?>
                <span class="astat__note">chưa có buổi hẹn nào được chốt kết quả trong kỳ</span>
            <?php else: ?>
                <span class="astat__note"><?= (int) $lich['khongDen'] ?>/<?= $daChot ?> buổi đã chốt kết quả</span>
            <?php endif; ?>

            <?php if ((int) $lich['quaHanChuaChot'] > 0): ?>
                <span class="astat__note astat__note--canh">
                    <?= (int) $lich['quaHanChuaChot'] ?> buổi đã qua ngày mà chưa ai chốt —
                    tỉ lệ trên chưa tính chúng.
                </span>
            <?php endif; ?>
        </div>
    </li>
</ul>

<!-- ═══════════ CẦN XỬ LÝ GẤP ═══════════ -->
<?php
/*
 * ĐẶT NGAY DƯỚI HÀNG CHỈ SỐ, TRƯỚC BIỂU ĐỒ — cố ý.
 *
 * Hàng chỉ số nói cửa hàng đang đi thế nào; khối này nói phải làm gì ngay. Đẩy
 * nó xuống dưới biểu đồ thì trên màn hình 13 inch nó nằm ngoài màn đầu, và một
 * hàng đợi phải cuộn mới thấy là một hàng đợi không ai trực.
 *
 * BỐN HÀNG ĐỢI, KHÔNG THEO KỲ. Xem DashboardStats::canXuLy().
 */
?>
<section class="apanel adash__gap" aria-labelledby="can-xu-ly">
    <div class="apanel__head">
        <h2 id="can-xu-ly" class="apanel__title">
            Cần xử lý gấp
            <?php if ($tongCanXuLy > 0): ?>
                <span class="adash__dem"><?= $tongCanXuLy ?></span>
            <?php endif; ?>
        </h2>
        <span class="apanel__count">Việc tồn đọng tính tới lúc này — không theo kỳ đang xem</span>
    </div>

    <?php if ($tongCanXuLy === 0): ?>
        <?php /* KHÔNG ĐỂ TRỐNG. Một khối rỗng đọc ra là "chưa tải xong" hoặc
                 "hỏng", không đọc ra là "xong việc". Câu xác nhận phải nói rõ đã
                 kiểm những gì, nếu không nó chỉ là một lời trấn an. */ ?>
        <p class="apanel__empty adash__sach">
            Đã sạch việc. Không có đơn nào ở trạng thái Mới quá <?= (int) $nguongGio ?> giờ,
            không có lịch hẹn nào quá ngày mà chưa xác nhận, không có sản phẩm hết hàng nào
            còn bày bán, và mọi yêu cầu liên hệ đều đã tới Zalo CSKH.
        </p>
    <?php else: ?>
        <div class="adash__hangdoi">

            <?php
            /*
             * MỖI DÒNG DẪN TỚI ĐÚNG BẢN GHI, không dẫn tới danh sách rồi để
             * người dùng tự tìm lại. Ba đường khác nhau vì ba trang có ba cách
             * mở một bản ghi, và tất cả đều là đường CÓ SẴN — không thêm route
             * mới nào cho việc này:
             *
             *   đơn hàng   ?xem=<id>  mở ngăn kéo chi tiết (OrderAdminController
             *                         ::drawer()), chạy được cả khi tắt JS.
             *   lịch hẹn   ?q=<mã>    ô tìm của trang Lịch hẹn tìm theo mã lịch,
             *                         nên nó lọc xuống đúng một dòng.
             *   sản phẩm   ?q=<SKU>   ô tìm của trang Tồn kho tìm theo SKU.
             *   liên hệ    ?xem=<id>  mở hộp chi tiết yêu cầu.
             */
            $khoi = static function (
                string $tieuDe,
                string $moTa,
                int $tong,
                array $dong,
                string $xemTatCa,
                callable $veDong
            ): void {
                if ($tong === 0) {
                    return;
                }
                ?>
                <section class="adash__doi">
                    <h3 class="adash__doi-tieu">
                        <?= e($tieuDe) ?>
                        <span class="adash__doi-so"><?= $tong ?></span>
                    </h3>
                    <p class="adash__doi-mo"><?= e($moTa) ?></p>

                    <ul class="alist" role="list">
                        <?php foreach ($dong as $d) {
                            $veDong($d);
                        } ?>
                    </ul>

                    <?php if ($tong > count($dong)): ?>
                        <a class="apanel__more" href="<?= e($xemTatCa) ?>">Xem tất cả <?= $tong ?> →</a>
                    <?php endif; ?>
                </section>
                <?php
            };

            $khoi(
                'Đơn ở trạng thái Mới quá ' . (int) $nguongGio . ' giờ',
                'Khách đã đặt và đang chờ một cuộc gọi xác nhận.',
                (int) $canXuLy['donMoi']['tong'],
                $canXuLy['donMoi']['dong'],
                '/quan-tri/don-hang?status=new',
                static function (array $o): void { ?>
                    <li class="alist__row">
                        <div class="alist__main">
                            <a class="alist__name alist__name--lead"
                               href="/quan-tri/don-hang?xem=<?= e((string) $o['id']) ?>"><?= e((string) $o['code']) ?></a>
                            <span class="alist__code"><?= e((string) $o['customer_name']) ?></span>
                        </div>
                        <div class="alist__side">
                            <span class="alist__num"><?= money((int) $o['total']) ?></span>
                            <span class="badge badge--new">đặt <?= e(formatDate((string) $o['created_at'], 'd/m H:i')) ?></span>
                        </div>
                    </li>
                <?php }
            );

            $khoi(
                'Lịch hẹn đã qua ngày mà vẫn Chờ xác nhận',
                'Buổi hẹn đã trôi qua nhưng chưa ai chốt là khách có đến hay không.',
                (int) $canXuLy['lichQuaHan']['tong'],
                $canXuLy['lichQuaHan']['dong'],
                '/quan-tri/lich-hen?status=pending',
                static function (array $a): void { ?>
                    <li class="alist__row">
                        <div class="alist__main">
                            <a class="alist__name alist__name--lead"
                               href="/quan-tri/lich-hen?q=<?= e(urlencode((string) $a['code'])) ?>"><?= e((string) $a['full_name']) ?></a>
                            <span class="alist__code"><?= e((string) $a['code']) ?> · <?= e((string) $a['store_name']) ?></span>
                        </div>
                        <div class="alist__side">
                            <span class="badge badge--pending">hẹn <?= e(formatDate((string) $a['appointment_date'])) ?></span>
                        </div>
                    </li>
                <?php }
            );

            $khoi(
                'Hết hàng nhưng vẫn bày bán',
                /* `allow_backorder = 1` KHÔNG vào đây: đó là mặt hàng cố ý cho
                   đặt trước khi hết kho, không phải một lỗi cần sửa. Xếp nó vào
                   danh sách gấp là dạy người dùng bỏ qua cả khối. */
                'Trang bán hàng vẫn hiện nút mua, khách đặt được thứ không có trong kho.',
                (int) $canXuLy['hetHang']['tong'],
                $canXuLy['hetHang']['dong'],
                '/quan-tri/ton-kho?loc=out',
                static function (array $p): void { ?>
                    <li class="alist__row">
                        <div class="alist__main">
                            <a class="alist__name alist__name--lead"
                               href="/quan-tri/ton-kho?q=<?= e(urlencode((string) $p['sku'])) ?>"><?= e((string) $p['name']) ?></a>
                            <span class="alist__code"><?= e((string) $p['sku']) ?></span>
                        </div>
                        <div class="alist__side">
                            <span class="alist__num alist__num--tight alist__num--danger"><?= (int) $p['stock_quantity'] ?></span>
                            <span class="badge badge--out_of_stock">Hết hàng</span>
                        </div>
                    </li>
                <?php }
            );

            $khoi(
                'Yêu cầu liên hệ chưa tới Zalo CSKH quá ' . (int) $nguongGio . ' giờ',
                /* ⚠ ĐÂY KHÔNG PHẢI "chưa xử lý" theo nghĩa cũ — cột `status` của
                   `contact_requests` đã bỏ ngày 2026-08-26. Xem chú thích ở
                   DashboardStats::canXuLy(). */
                'ZNS không đẩy được. Có người thật đang chờ gọi lại mà CSKH chưa biết.',
                (int) $canXuLy['lienHe']['tong'],
                $canXuLy['lienHe']['dong'],
                '/quan-tri/lien-he?zalo=chua',
                static function (array $c): void { ?>
                    <li class="alist__row">
                        <div class="alist__main">
                            <a class="alist__name alist__name--lead"
                               href="/quan-tri/lien-he?xem=<?= e((string) $c['id']) ?>"><?= e((string) $c['full_name']) ?></a>
                            <span class="alist__code"><?= e((string) $c['phone']) ?></span>
                        </div>
                        <div class="alist__side">
                            <span class="badge badge--cancelled">gửi <?= e(formatDate((string) $c['created_at'], 'd/m H:i')) ?></span>
                        </div>
                    </li>
                <?php }
            );
            ?>
        </div>

        <?php if ($coSo !== ''): ?>
            <p class="apanel__empty adash__nhac">
                Hai hàng đợi dưới — sản phẩm và yêu cầu liên hệ — luôn tính trên toàn hệ thống:
                kho là một, và yêu cầu liên hệ không gắn với cơ sở nào.
            </p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<!-- ═══════════ HAI DẢI Ô ĐẾM — HIỆN TẠI, MỌI Ô BẤM ĐƯỢC ═══════════ -->
<?php
/*
 * HAI DẢI BA Ô, KHÔNG PHẢI MỘT DẢI SÁU Ô.
 *
 * Đã thử gộp làm một. Hỏng theo hai đường cùng lúc:
 *
 *   BỐ CỤC — .afacts là flex với min-width 200px mỗi ô, nên sáu ô ở bề ngang
 *   thường rơi thành 5 + 1, và ô mồ côi ở hàng dưới giãn ra chiếm trọn chiều
 *   ngang. Con số "0 danh mục" khi ấy to bằng cả năm ô trên cộng lại.
 *
 *   NGHĨA — sáu ô ấy là hai loài khác nhau. Ba ô đầu là HÀNG CHỜ CÓ NGƯỜI ĐANG
 *   ĐỢI ở đầu bên kia; ba ô sau là TRẠNG THÁI KHO VÀ NỘI DUNG, biết để đấy chứ
 *   không ai "xử lý" số danh mục cả. Xếp chung một dải là bảo người đọc rằng
 *   sáu con số này cùng loại việc.
 *
 * Ba ô một dải cũng chia đều tăm tắp ở mọi bề ngang — 3 cột, rồi 2, rồi 1.
 */
$dai = [
    ['nhan' => 'Hàng chờ đang có người đợi', 'o' => $queues],
    ['nhan' => 'Kho và nội dung',            'o' => $facts],
];
?>
<?php foreach ($dai as $d): ?>
    <h2 class="sr-only"><?= e($d['nhan']) ?></h2>
    <ul class="afacts" role="list">
        <?php foreach ($d['o'] as $o): ?>
            <li class="afacts__cell">
                <a class="afacts__link" href="<?= e($o['url']) ?>">
                    <?php /* Tô hổ phách CHỈ KHI khác 0. "0 đơn chờ xác nhận" là
                             tin tốt; sơn màu cảnh báo lên nó là dạy người đọc
                             rằng màu ấy không có nghĩa gì, và tới hôm số thật
                             lên 5 thì họ đã quen lướt qua. */ ?>
                    <span class="afacts__num<?= (!empty($o['warn']) && $o['value'] > 0) ? ' afacts__num--warn' : '' ?>"><?= (int) $o['value'] ?></span>
                    <span class="afacts__label"><?= e($o['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endforeach; ?>

<!-- ═══════════ BIỂU ĐỒ VÀ TOP SẢN PHẨM ═══════════ -->
<div class="agrid">

    <section class="apanel" aria-labelledby="bieu-do">
        <div class="apanel__head">
            <h2 id="bieu-do" class="apanel__title">
                Doanh thu theo <?= $bieuDo['theoTuan'] ? 'tuần' : 'ngày' ?>
            </h2>
            <span class="apanel__count">
                <?= $bieuDo['theoTuan']
                    ? 'kỳ dài hơn hai tháng nên mỗi cột gộp 7 ngày'
                    : 'mỗi cột là một ngày trong kỳ' ?>
            </span>
        </div>

        <?php
        /*
         * ─────────────────────────────────────────────────────────────────────
         * SVG VIẾT TAY, KHÔNG THƯ VIỆN
         *
         * CLAUDE.md: dự án không có bước build và không thêm phụ thuộc ngoài.
         * Một biểu đồ cột thì cũng chỉ là mấy thẻ <rect> — nạp 90KB Chart.js cho
         * việc này là đổi cả ràng buộc kiến trúc lấy vài đường bo góc.
         *
         * VẼ SẴN Ở MÁY CHỦ, KHÔNG DỰNG BẰNG JS. Tắt JS thì biểu đồ vẫn đủ hình,
         * đủ trục, đủ nhãn — đúng nếp "mọi file JS chỉ là TĂNG CƯỜNG".
         *
         * ─────────────────────────────────────────────────────────────────────
         * ĐỌC ĐƯỢC TRÊN CẢ NỀN SÁNG LẪN NỀN TỐI
         *
         * Không có mã màu nào viết thẳng vào SVG. Chữ và vạch trục dùng
         * `currentColor` nên chúng thừa hưởng màu chữ của thẻ chứa; cột dùng một
         * token CSS. Đặt cái biểu đồ này lên nền tối (thẻ .astat--money, hoặc
         * một chủ đề tối về sau) thì trục và nhãn tự đảo theo, không phải sửa
         * một dòng nào ở đây. Xem khối token trong admin-dashboard.css.
         *
         * ─────────────────────────────────────────────────────────────────────
         * KÍCH THƯỚC KHUNG NHÌN LÀ MỘT QUYẾT ĐỊNH VỀ CỠ CHỮ, KHÔNG PHẢI SỐ TUỲ Ý
         *
         * SVG co giãn thì CHỮ CO THEO. Với viewBox rộng 1000 đơn vị vẽ trong một
         * thẻ rộng 600px, tỉ lệ là 0,6 — nhãn khai 11 đơn vị hiện ra 6,6px, tức
         * là không đọc được. Đây là lỗi kinh điển của biểu đồ SVG tự vẽ, và nó
         * không lộ ra trên máy người viết vì ở đó cửa sổ rộng.
         *
         * Nên khung nhìn đặt 620×200, XẤP XỈ BỀ NGANG THẬT của thẻ trên màn hình
         * làm việc. Tỉ lệ khi ấy quanh 1,0 và nhãn 10 đơn vị hiện đúng ~10px.
         *
         * Trên điện thoại thẻ hẹp hơn nhiều, nên .adash__svg có min-width 520px
         * và khung ngoài cuộn ngang được. Cuộn NGANG TRONG MỘT KHUNG là cách
         * duy nhất giữ cả hai điều: nhãn đọc được, và TRANG không tràn ngang.
         * ─────────────────────────────────────────────────────────────────────
         */
        $cot = $bieuDo['cot'];
        $n   = count($cot);

        $W = 620; $H = 200;
        $le = ['trai' => 54, 'phai' => 6, 'tren' => 10, 'duoi' => 24];
        $rongVe = $W - $le['trai'] - $le['phai'];
        $caoVe  = $H - $le['tren'] - $le['duoi'];

        $dinh = 0;

        foreach ($cot as $c) {
            $dinh = max($dinh, (int) $c['tien']);
        }

        /* TRẦN LÀM TRÒN LÊN tới hai chữ số có nghĩa, không lấy thẳng giá trị lớn
           nhất. Lấy thẳng thì cột cao nhất luôn chạm mép trên — nên mọi kỳ đều
           trông như đã kịch trần — và nhãn trục là những con số lẻ kiểu
           "3.847.500" mà mắt không so được giữa hai lần xem. */
        if ($dinh >= 10) {
            $bac  = 10 ** (strlen((string) $dinh) - 2);
            $tran = (int) (ceil($dinh / $bac) * $bac);
        } else {
            $tran = max($dinh, 1);
        }

        /* Nhãn trục hoành THƯA RA khi nhiều cột: 30 nhãn "dd/mm" cạnh nhau là
           chữ chồng lên chữ. Giữ tối đa 8 nhãn, và luôn giữ nhãn cuối cùng. */
        $buoc = max(1, (int) ceil($n / 8));
        ?>

        <div class="adash__chart">
            <?php if ($n === 0 || $dinh === 0): ?>
                <p class="apanel__empty">Kỳ này chưa có đồng doanh thu nào để vẽ.</p>
            <?php else: ?>
                <svg class="adash__svg" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img"
                     aria-label="Biểu đồ cột doanh thu <?= $bieuDo['theoTuan'] ? 'theo tuần' : 'theo ngày' ?>, từ <?= e(formatDate($ky['tu'], 'd/m/Y')) ?> đến <?= e(formatDate($ky['den'], 'd/m/Y')) ?>, cao nhất <?= e(money($dinh)) ?>">

                    <?php /* BA VẠCH NGANG, không nhiều hơn: 0, nửa, trần. Lưới
                             dày hơn thế thì nó cạnh tranh với chính các cột. */ ?>
                    <?php foreach ([0, 0.5, 1] as $tiLe): ?>
                        <?php $y = $le['tren'] + $caoVe * (1 - $tiLe); ?>
                        <line class="adash__luoi"
                              x1="<?= $le['trai'] ?>" y1="<?= round($y, 1) ?>"
                              x2="<?= $W - $le['phai'] ?>" y2="<?= round($y, 1) ?>" />
                        <text class="adash__truc"
                              x="<?= $le['trai'] - 7 ?>" y="<?= round($y + 3.5, 1) ?>"
                              text-anchor="end"><?= e($tienNgan((int) round($tran * $tiLe))) ?></text>
                    <?php endforeach; ?>

                    <?php
                    $rongO = $rongVe / $n;
                    /* Khe hở tối đa 2 đơn vị mỗi bên, và không bao giờ nuốt quá
                       30% bề ngang ô: ở kỳ 60 ngày một khe cố định sẽ ăn gần hết
                       cột và biểu đồ thành một dãy sợi chỉ. */
                    $khe     = min(2, $rongO * 0.3);
                    $rongCot = max(1, $rongO - $khe * 2);
                    ?>

                    <?php foreach ($cot as $i => $c): ?>
                        <?php
                        $tienCot = (int) $c['tien'];
                        $cao     = ($tienCot / $tran) * $caoVe;
                        /* Cột 0₫ vẫn để lại một vệt mỏng: nó nói "ngày này có tồn
                           tại và bán được 0₫", khác hẳn một khoảng trống — mà một
                           tuần nghỉ Tết với một tuần bán đều thì phải nhìn ra
                           được là hai chuyện khác nhau. */
                        $cao = $tienCot > 0 ? max($cao, 2) : 1;
                        $x   = $le['trai'] + $rongO * $i + $khe;
                        $y   = $le['tren'] + $caoVe - $cao;
                        ?>
                        <rect class="adash__cot<?= $tienCot === 0 ? ' adash__cot--rong' : '' ?>"
                              x="<?= round($x, 2) ?>" y="<?= round($y, 2) ?>"
                              width="<?= round($rongCot, 2) ?>" height="<?= round($cao, 2) ?>"
                              rx="1">
                            <?php /* <title> là chú giải CÓ SẴN CỦA TRÌNH DUYỆT —
                                     hiện khi rê chuột và đọc được bằng trình đọc
                                     màn hình, không tốn một dòng JS nào. Đây là
                                     chỗ con số đầy đủ được đọc, bù cho nhãn trục
                                     đã phải rút gọn. */ ?>
                            <title><?= e((string) $c['day']) ?> · <?= e(money($tienCot)) ?></title>
                        </rect>
                    <?php endforeach; ?>

                    <?php foreach ($cot as $i => $c): ?>
                        <?php if ($i % $buoc !== 0 && $i !== $n - 1) { continue; } ?>
                        <text class="adash__truc"
                              x="<?= round($le['trai'] + $rongO * ($i + 0.5), 2) ?>"
                              y="<?= $H - 8 ?>"
                              text-anchor="middle"><?= e((string) $c['nhan']) ?></text>
                    <?php endforeach; ?>
                </svg>
            <?php endif; ?>
        </div>

        <div class="apanel__foot">
            <span class="apanel__count">Cột tính theo ngày đặt đơn, chỉ gồm tiền đã thu đủ</span>
            <a href="/quan-tri/don-hang" class="apanel__more">Mở danh sách đơn →</a>
        </div>
    </section>

    <div class="agrid__col">
        <section class="apanel" aria-labelledby="top-sp">
            <div class="apanel__head apanel__head--plain">
                <h2 id="top-sp" class="apanel__title">Bán chạy nhất trong kỳ</h2>
            </div>

            <?php if ($top === []): ?>
                <p class="apanel__empty">Kỳ này chưa có sản phẩm nào bán ra và thu đủ tiền.</p>
            <?php else: ?>
                <?php /* XẾP THEO TIỀN, không theo số lượng — hai bảng xếp hạng ấy
                         khác hẳn nhau ở tiệm kính: khăn lau và nước rửa kính luôn
                         đứng đầu về số lượng mà gần như không đóng góp doanh thu.
                         Cột số lượng vẫn hiện để thấy được cả hai mặt. */ ?>
                <table class="atable">
                    <thead>
                        <tr>
                            <th scope="col">Sản phẩm</th>
                            <th scope="col">SL</th>
                            <th scope="col">Doanh thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top as $sp): ?>
                            <tr>
                                <td>
                                    <?php if ($sp['id'] !== null): ?>
                                        <a href="/quan-tri/ton-kho?q=<?= e(urlencode($sp['ten'])) ?>"><?= e($sp['ten']) ?></a>
                                    <?php else: ?>
                                        <?php /* product_id là SET NULL: sản phẩm đã bị
                                                 xoá khỏi danh mục. Không dựng liên kết
                                                 dẫn tới một trang trống. */ ?>
                                        <?= e($sp['ten']) ?>
                                        <span class="atable__sub">đã xoá khỏi danh mục</span>
                                    <?php endif; ?>
                                </td>
                                <td class="adash__so"><?= (int) $sp['soLuong'] ?></td>
                                <td class="adash__so"><?= money((int) $sp['doanhThu']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="apanel" aria-labelledby="recent-bookings">
            <div class="apanel__head apanel__head--plain">
                <h2 id="recent-bookings" class="apanel__title">Lịch hẹn sắp tới</h2>
                <a href="/quan-tri/lich-hen" class="apanel__more">Xem tất cả →</a>
            </div>

            <?php if ($recentBookings === []): ?>
                <p class="apanel__empty">Không có lịch hẹn nào sắp tới.</p>
            <?php else: ?>
                <table class="atable">
                    <thead>
                        <tr>
                            <th scope="col">Ngày</th>
                            <th scope="col">Khách</th>
                            <th scope="col">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBookings as $a): ?>
                            <tr>
                                <td><?= e(formatDate($a['appointment_date'])) ?></td>
                                <td><a href="/quan-tri/lich-hen?q=<?= e(urlencode((string) $a['code'])) ?>"><?= e($a['full_name']) ?></a></td>
                                <td><span class="badge badge--<?= e($a['status']) ?>"><?= e($bookingStatuses[$a['status']] ?? $a['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="apanel__foot">
                    <span class="apanel__count">Từ hôm nay trở đi — không theo kỳ</span>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<!-- ═══════════ BA DANH SÁCH — HIỆN TẠI ═══════════ -->
<div class="agrid adash__gap">

    <section class="apanel" aria-labelledby="recent-orders">
        <div class="apanel__head">
            <h2 id="recent-orders" class="apanel__title">Đơn hàng gần đây</h2>
            <a href="/quan-tri/don-hang" class="apanel__more">Xem tất cả →</a>
        </div>

        <?php if ($recentOrders === []): ?>
            <p class="apanel__empty">Chưa có đơn hàng nào.</p>
        <?php else: ?>
            <?php /* DANH SÁCH, KHÔNG PHẢI BẢNG — xem khối ghi chú ở .alist trong
                     admin.css. Bốn cột trong một thẻ rộng 600px thì tên khách bị
                     cắt trong khi cột trạng thái thừa khoảng trắng. */ ?>
            <ul class="alist" role="list">
                <?php foreach ($recentOrders as $o): ?>
                    <li class="alist__row">
                        <div class="alist__main">
                            <a class="alist__code" href="/quan-tri/don-hang?xem=<?= e((string) $o['id']) ?>"><?= e($o['code']) ?></a>
                            <span class="alist__name"><?= e($o['customer_name']) ?></span>
                        </div>
                        <div class="alist__side">
                            <span class="badge badge--<?= e($o['status']) ?>"><?= e($orderStatuses[$o['status']] ?? $o['status']) ?></span>
                            <span class="alist__num"><?= money((int) $o['total']) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="apanel__foot">
                <?php /* Chân thẻ nói RÕ ĐÂY LÀ GÌ: 8 đơn mới nhất, không phải 8
                         đơn của kỳ đang chọn. Thiếu câu này thì người vừa chọn
                         "Hôm nay" đọc danh sách và tưởng hôm nay có 8 đơn. */ ?>
                <span class="apanel__count">8 đơn mới nhất — không theo kỳ</span>
                <a href="/quan-tri/don-hang" class="apanel__more">Mở danh sách đơn →</a>
            </div>
        <?php endif; ?>
    </section>

    <div class="agrid__col">
        <section class="apanel" aria-labelledby="low-stock">
            <div class="apanel__head">
                <h2 id="low-stock" class="apanel__title">Sắp hết hàng</h2>
                <a href="/quan-tri/ton-kho" class="apanel__more">Quản lý tồn kho →</a>
            </div>

            <?php if ($lowStock === []): ?>
                <?php /* KHÔNG in con số ngưỡng ra câu này. Từ 2026-08-29 ngưỡng
                         "sắp hết" là của TỪNG mặt hàng (cột low_stock_at, để
                         trống mới rơi về 5), nên "tồn ≤ 5" chỉ đúng với phần kho
                         không đặt ngưỡng riêng — mà đọc lên thì như thể đúng với
                         cả kho. */ ?>
                <p class="apanel__empty">Không có sản phẩm nào sắp hết hàng.</p>
            <?php else: ?>
                <ul class="alist" role="list">
                    <?php foreach ($lowStock as $p): ?>
                        <?php
                        /* NHÃN ĐỌC CỘT `status`, KHÔNG TỰ SUY TỪ SỐ TỒN. Một sản
                           phẩm còn 1 cái trong kho vẫn có thể đã bị tắt bán (hàng
                           lỗi, hàng giữ cho khách đặt riêng) — lúc đó `status` là
                           'out_of_stock' dù số tồn khác 0, và người trực quầy cần
                           thấy đúng điều đó chứ không thấy "Sắp hết" rồi hứa với
                           khách. */
                        $conBan = ($p['status'] ?? '') !== 'out_of_stock';
                        ?>
                        <li class="alist__row">
                            <div class="alist__main">
                                <a class="alist__name alist__name--lead"
                                   href="/quan-tri/ton-kho?q=<?= e(urlencode((string) $p['sku'])) ?>"><?= e($p['name']) ?></a>
                                <span class="alist__code"><?= e($p['sku']) ?></span>
                            </div>
                            <div class="alist__side">
                                <span class="alist__num alist__num--tight<?= (int) $p['stock_quantity'] === 0 ? ' alist__num--danger' : '' ?>"><?= (int) $p['stock_quantity'] ?></span>
                                <span class="badge badge--<?= $conBan ? 'low_stock' : 'out_of_stock' ?>"><?= $conBan ? 'Sắp hết' : 'Hết hàng' ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="apanel__foot">
                    <?php /* Kho không chia theo cơ sở — `products` không có cột
                             cơ sở nào. Nói ra khi người dùng đang lọc, để họ
                             không đọc thẻ này như số của riêng cơ sở ấy. */ ?>
                    <span class="apanel__count">
                        Ưu tiên tồn thấp nhất<?= $coSo !== '' ? ' — kho chung, không chia theo cơ sở' : '' ?>
                    </span>
                    <a href="/quan-tri/ton-kho?loc=low" class="apanel__more">Xem tất cả →</a>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
