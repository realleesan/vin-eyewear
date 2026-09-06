<?php

/**
 * admin/sepay/index.php — Sổ giao dịch ngân hàng. FR-SG-01..07.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ MỘT SỔ TIỀN, KHÔNG PHẢI MỘT DANH SÁCH ĐỂ QUẢN LÝ
 *
 * Không có nút Sửa, không có nút Xoá, không có nút Thêm — FR-SG-07 nói thẳng
 * "chỉ đọc và gắn đơn". Mỗi dòng ở đây là một khoản tiền có thật đã vào tài
 * khoản ngân hàng của cửa hàng; sửa nó là làm sổ nói khác sao kê, và lúc ấy cả
 * hai đều mất giá trị.
 *
 * Thao tác DUY NHẤT là gắn một khoản chưa biết của ai vào một đơn.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HAI VIÊN LỌC ĐƯỢC IN ĐẬM — FR-SG-03 nói rõ, và lý do nằm ở nghiệp vụ
 *
 * "Thu một phần" và "Không tìm thấy đơn" là hai chỗ tiền đã về mà đơn của
 * khách vẫn hiện chưa thanh toán. Bốn viên còn lại đã xong việc. Một dải sáu
 * viên bằng nhau bắt người trực quầy tự nhớ hai viên nào cần mở mỗi sáng.
 * ─────────────────────────────────────────────────────────────────────────────
 */
?>
<div class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Sổ giao dịch ngân hàng</h1>
        <p class="ahead__lead">
            Mọi khoản tiền ngân hàng báo về, kể cả những khoản hệ thống chưa khớp
            được vào đơn nào. Chỉ đọc và gắn đơn — sổ này phải khớp với sao kê.
        </p>
    </div>

    <?php if ($coBang): ?>
        <div class="ahead__tools">
            <?php /* Ô TÌM GIỮ NGUYÊN BỘ LỌC ĐANG CHỌN — FR-SG-04 đứng cạnh
                     FR-SG-03, và người đang xem "Không tìm thấy đơn" mà gõ một
                     số tiền thì phải được lọc CHỒNG lên, không bị ném về danh
                     sách đầy đủ. Cùng cách làm với màn Lịch sử thao tác.

                     KHÔNG giữ ?page: kết quả tìm là một tập khác hẳn, và trang
                     7 của tập cũ gần như chắc chắn không tồn tại trong tập mới
                     — người gõ tìm sẽ thấy một bảng trống và tưởng không có gì. */ ?>
            <form class="asearch" method="get" action="/quan-tri/doi-soat" role="search">
                <?php if ($loc !== ''): ?>
                    <input type="hidden" name="loc" value="<?= e($loc) ?>">
                <?php endif; ?>

                <label class="sr-only" for="q">Tìm trong sổ giao dịch</label>
                <input type="search" id="q" name="q" value="<?= e($q) ?>"
                       placeholder="Nội dung chuyển khoản, mã đơn, số tiền…">
                <button type="submit" class="astatus__save astatus__save--ghost">Tìm</button>
                <?php if ($q !== '' || $loc !== ''): ?>
                    <a href="/quan-tri/doi-soat" class="apanel__more">Xoá bộ lọc</a>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if (!$coBang): ?>
    <p class="apanel__empty">
        Chưa có bảng sổ giao dịch trong cơ sở dữ liệu. Chạy
        <code>sudo bash database/migrate.sh</code> rồi mở lại trang này.
        Tới lúc đó webhook của SePay cũng đang tạm ngưng — mọi khoản chuyển khoản
        phải đối chiếu tay với sao kê ngân hàng.
    </p>
<?php else: ?>

<?php /* CỘT `gan_boi` CHƯA CÓ — chỉ nhắc một dòng, đừng để trang đổ. Bảng vẫn
         đọc được đầy đủ; thứ thiếu là dấu vết ai đã gắn dòng nào, mà vết đầy
         đủ thì vẫn nằm trong màn Lịch sử thao tác. */ ?>
<?php if (!$coGanTay): ?>
    <div class="anote anote--alert">
        <p>
            <strong>Chưa nâng cấp cơ sở dữ liệu.</strong>
            Chạy <code>database/migrations/2026-09-06-dot-7-doi-soat.sql</code> để sổ
            ghi lại được ai đã gắn giao dịch nào. Gắn đơn vẫn dùng được ngay bây giờ,
            và vết đầy đủ vẫn có trong Lịch sử thao tác.
        </p>
    </div>
<?php endif; ?>

<?php
/* VIÊN LỌC PHẢI MANG THEO TỪ KHOÁ ĐANG TÌM.

   Ô tìm ở trên đã giữ `loc` khi gõ, và chú thích ở đó nói hai bộ lọc phải
   chồng lên nhau. Chiều ngược lại cũng vậy: gõ "1320000" rồi bấm "Không tìm
   thấy đơn" mà mất số tiền là quẳng đi đúng thứ vừa gõ. Bộ lọc một chiều thì
   không phải bộ lọc chồng. */
$locUrl = static function (string $ketQua) use ($q): string {
    $t = array_filter(['loc' => $ketQua, 'q' => $q], static fn (string $v): bool => $v !== '');

    return '/quan-tri/doi-soat' . ($t !== [] ? '?' . http_build_query($t) : '');
};
?>
<nav class="atabs" aria-label="Lọc theo kết quả đối soát">
    <a class="atabs__item<?= $loc === '' ? ' is-active' : '' ?>"
       <?= $loc === '' ? 'aria-current="true"' : '' ?>
       href="<?= e($locUrl('')) ?>">
        Tất cả <span class="atabs__num"><?= (int) ($dem[''] ?? 0) ?></span>
    </a>
    <?php foreach (SepayModel::KET_QUA as $ma => $nhan): ?>
        <?php
        /* Hai viên "hàng chờ" đeo thêm .atabs__item--cho — xem khối đầu file
           và luật FR-SG-03. Danh sách nằm trong hằng số của model chứ không
           viết tay ở đây: nếu mai có thêm một kết quả cần xử lý thì sửa một
           chỗ, không phải đi tìm cả view. */
        $canXuLy = in_array($ma, SepayModel::CAN_XU_LY, true);
        ?>
        <a class="atabs__item<?= $canXuLy ? ' atabs__item--cho' : '' ?><?= $loc === $ma ? ' is-active' : '' ?>"
           <?= $loc === $ma ? 'aria-current="true"' : '' ?>
           href="<?= e($locUrl($ma)) ?>">
            <?= e($nhan) ?> <span class="atabs__num"><?= (int) ($dem[$ma] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($rows === []): ?>
    <p class="ahead__note">
        <?= $q !== '' || $loc !== ''
            ? 'Không có giao dịch nào khớp bộ lọc này.'
            : 'Chưa có giao dịch nào. Ngân hàng chưa báo về khoản chuyển khoản nào.' ?>
    </p>
<?php else: ?>
<div class="atable-wrap">
    <table class="atable">
        <thead>
            <tr>
                <th scope="col">Thời điểm</th>
                <th scope="col">Số tiền</th>
                <th scope="col">Nội dung chuyển khoản</th>
                <th scope="col">Đơn đã khớp</th>
                <th scope="col">Kết quả đối soát</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                /* Năm kết quả mượn viên thuốc CÓ SẴN của khu quản trị, ánh xạ
                   theo NGHĨA chứ không theo tên trạng thái đơn hàng:

                     paid          xong hẳn        xanh
                     deposit_paid  đang đi tiếp    xanh dương
                     partial       cần người nhìn  vàng
                     no_order      cần người nhìn  đỏ  (nặng hơn partial: khoản
                                   này còn chưa biết của ai)
                     ignored       không phải việc xám */
                $vien = [
                    'paid'         => 'badge--completed',
                    'deposit_paid' => 'badge--shipping',
                    'partial'      => 'badge--pending',
                    'no_order'     => 'badge--cancelled',
                    'ignored'      => 'badge--neutral',
                ][$r['applied']] ?? 'badge--neutral';

                /* Mốc hiện là mốc NGÂN HÀNG ghi nhận; thiếu thì lùi về lúc
                   webhook tới. Lý do đầy đủ ở SepayModel::danhSach(). */
                $khi = $r['transaction_date'] ?? $r['created_at'];

                $moUrl = '/quan-tri/doi-soat?' . http_build_query(array_filter([
                    'loc'  => $loc,
                    'q'    => $q,
                    'page' => $page > 1 ? (string) $page : '',
                    'xem'  => $r['id'],
                ], static fn (string $v): bool => $v !== ''));
                ?>
                <tr>
                    <td><?= e(formatDate((string) $khi, 'd/m/Y H:i')) ?></td>
                    <td class="num">
                        <?php /* TIỀN RA ĐEO DẤU TRỪ. Cột số tiền không có dấu
                                 thì một khoản hoàn 500.000₫ đọc y hệt một khoản
                                 khách trả 500.000₫ — trên một sổ đối soát thì
                                 đó là hai dòng ngược chiều nhau. */ ?>
                        <?= $r['transfer_type'] === 'out' ? '−' : '' ?><?= e(money((int) $r['amount'])) ?>
                    </td>
                    <td>
                        <?php /* Nội dung chuyển khoản là thứ người ta thật sự
                                 bấm vào — nó dài, nó là manh mối để đoán khoản
                                 này của ai, và nó dễ trúng hơn một nút bé. */ ?>
                        <a href="<?= e($moUrl) ?>" data-modal><?= e((string) ($r['content'] ?? '(trống)')) ?></a>
                        <?php if ($r['gan_boi'] !== null): ?>
                            <span class="atable__sub">
                                Gắn tay<?= $r['gan_ten'] !== null ? ' bởi ' . e((string) $r['gan_ten']) : '' ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        /* CỘT NÀY NÓI VỀ ĐƠN ĐÃ KHỚP, nên nó phải hỏi
                           `order_id`, không hỏi `order_code`.

                           handle() ghi `order_code` với MỌI mã đọc được ra khỏi
                           nội dung chuyển khoản, kể cả khi tra bảng `orders`
                           không thấy đơn nào. Hỏi nhầm cột thì một dòng "Không
                           tìm thấy đơn" lại hiện một mã đơn ngay bên cạnh —
                           đọc ra như hệ thống tìm thấy rồi mà vẫn báo động, và
                           nhân viên sẽ gõ đúng cái mã ấy vào ô gắn rồi nhận
                           câu "không tìm thấy đơn hàng".

                           Mã ĐỌC ĐƯỢC mà không khớp thì vẫn đáng hiện, chỉ là
                           dưới một cái tên khác: nó cho biết khách đã gõ gần
                           đúng và sai ở đâu. */
                        ?>
                        <?php if ($r['order_id'] !== null): ?>
                            <?= e((string) $r['order_code']) ?>
                            <span class="atable__sub">
                                <?php if ($r['customer_name'] !== null): ?>
                                    <?= e((string) $r['customer_name']) ?> ·
                                <?php endif; ?>
                                <?php /* TRẠNG THÁI TIỀN CỦA ĐƠN, cạnh kết quả
                                         đối soát của dòng. Hai thứ khác nhau và
                                         CÓ THỂ nói ngược nhau — một dòng "Thu
                                         một phần" thuộc đơn "đã thanh toán" là
                                         chuyện bình thường sau khi cộng dồn.
                                         Không hiện cả hai thì người đọc không có
                                         cách nào phân biệt dòng đã xong với dòng
                                         còn treo. */ ?>
                                <?= e(OrderModel::PAYMENT_STATUSES[$r['payment_status']]
                                    ?? (string) $r['payment_status']) ?>
                            </span>
                        <?php elseif ($r['order_code'] !== null): ?>
                            <span class="atable__sub">
                                Đọc được “<?= e((string) $r['order_code']) ?>” — không có đơn nào
                            </span>
                        <?php else: ?>
                            <span class="atable__sub">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $vien ?>"><?= e(SepayModel::KET_QUA[$r['applied']] ?? $r['applied']) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="apanel__foot">
        <p class="apanel__more">
            <?= number_format((int) $total, 0, ',', '.') ?> giao dịch
            <?php if ($totalPages > 1): ?>· trang <?= (int) $page ?>/<?= (int) $totalPages ?><?php endif; ?>
        </p>

        <?php if ($totalPages > 1): ?>
            <nav class="pager" aria-label="Phân trang">
                <?php
                /* Chỉ hiện cửa sổ quanh trang hiện tại kèm hai đầu — sổ này dài
                   ra mỗi lần có khách chuyển khoản, in hết số trang thì thanh
                   phân trang dài hơn cả bảng. Cùng cách làm với Lịch sử thao
                   tác và Hàng chờ thư. */
                $tu    = max(1, $page - 2);
                $den   = min($totalPages, $page + 2);
                $moc   = array_unique(array_merge([1], range($tu, $den), [$totalPages]));
                sort($moc);
                $truoc = 0;

                $duongDan = static function (int $i) use ($loc, $q): string {
                    $t = array_filter(
                        ['loc' => $loc, 'q' => $q, 'page' => $i > 1 ? (string) $i : ''],
                        static fn (string $v): bool => $v !== ''
                    );

                    return '/quan-tri/doi-soat' . ($t !== [] ? '?' . http_build_query($t) : '');
                };
                ?>
                <?php foreach ($moc as $i): ?>
                    <?php if ($truoc > 0 && $i > $truoc + 1): ?>
                        <span class="pager__link" aria-hidden="true">…</span>
                    <?php endif; ?>
                    <?php if ($i === $page): ?>
                        <span class="pager__link is-current" aria-current="page"><?= (int) $i ?></span>
                    <?php else: ?>
                        <a class="pager__link" href="<?= e($duongDan((int) $i)) ?>"><?= (int) $i ?></a>
                    <?php endif; ?>
                    <?php $truoc = $i; ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
/* ─────────────────────────────────────────────────────────────────────────────
   NGĂN KÉO MỘT GIAO DỊCH — mở bằng ?xem=<id>.

   HAI HÌNH DẠNG, và ranh giới là `order_id`:

     chưa có đơn   hiện form gõ mã đơn để gắn — việc DUY NHẤT màn này làm.
     đã có đơn     chỉ đọc: mọi khoản đã về cho đơn ấy, cộng lại, so với tổng.
                   Không vẽ form gắn lại: FR-SG-07 cấm sửa một dòng sổ tiền, và
                   SepayModel::ganDon() cũng từ chối.
   ───────────────────────────────────────────────────────────────────────────── */
?>
<?php if ($xem !== null): ?>
    <?php
    $dongUrl = '/quan-tri/doi-soat';
    $thamVe  = array_filter(
        ['loc' => $loc, 'q' => $q, 'page' => $page > 1 ? (string) $page : ''],
        static fn (string $v): bool => $v !== ''
    );

    if ($thamVe !== []) {
        $dongUrl .= '?' . http_build_query($thamVe);
    }

    $chuaCoDon = $xem['order_id'] === null;
    $laTienRa  = $xem['transfer_type'] !== 'in';
    // Gắn được: chưa có đơn VÀ là tiền vào. Cùng bộ điều kiện với ganDon().
    $ganDuoc   = $chuaCoDon && !$laTienRa;

    /* SỐ DƯ, không phải tổng thu: tiền ra bị TRỪ. Đúng phép cộng mà
       SepayModel::ganDon() dùng để quyết định đơn đã đủ tiền chưa — hai chỗ
       lệch nhau thì màn hình nói một đằng, hệ thống làm một nẻo. */
    $daNhan = 0;

    foreach ($khoanKhac as $k) {
        $daNhan += $k['transfer_type'] === 'out' ? -(int) $k['amount'] : (int) $k['amount'];
    }
    ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => 'Giao dịch ' . money((int) $xem['amount']),
        'phu'     => formatDate((string) ($xem['transaction_date'] ?? $xem['created_at']), 'd/m/Y H:i')
            . ' · ' . (SepayModel::KET_QUA[$xem['applied']] ?? $xem['applied']),
        'dongUrl' => $dongUrl,
        'rong'    => 'lg',
    ]); ?>

        <dl class="arefund__facts">
            <div><dt>Số tiền</dt><dd><?= e(money((int) $xem['amount'])) ?></dd></div>
            <div>
                <dt>Chiều tiền</dt>
                <dd><?= $laTienRa ? 'Ra khỏi tài khoản' : 'Vào tài khoản' ?></dd>
            </div>
            <div><dt>Mã SePay</dt><dd>#<?= (int) $xem['sepay_id'] ?></dd></div>
            <div>
                <dt>Đơn đã khớp</dt>
                <dd><?= $xem['order_code'] !== null ? e((string) $xem['order_code']) : '—' ?></dd>
            </div>
        </dl>

        <p class="arefund__done">
            <strong>Nội dung chuyển khoản</strong><br>
            <?= e((string) ($xem['content'] ?? '(ngân hàng không gửi nội dung)')) ?>
        </p>

        <?php if (!$chuaCoDon): ?>
            <?php /* MỌI KHOẢN ĐÃ VỀ CHO ĐƠN NÀY — câu trả lời cho "đơn còn
                     thiếu bao nhiêu". Trước màn này, cách duy nhất biết được
                     là mở phpMyAdmin. */ ?>
            <div class="atable-wrap">
                <table class="atable">
                    <thead>
                        <tr>
                            <th scope="col">Thời điểm</th>
                            <th scope="col">Số tiền</th>
                            <th scope="col">Nội dung</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($khoanKhac as $k): ?>
                            <tr>
                                <td><?= e(formatDate((string) $k['khi'], 'd/m/Y H:i')) ?></td>
                                <td class="num">
                                    <?php /* Dấu trừ cho tiền RA — bảng này là
                                             một số dư, và phép cộng dồn ở
                                             ganDon() cũng trừ chúng đi. Không
                                             hiện dấu thì một khoản hoàn
                                             500.000₫ đọc y hệt một khoản khách
                                             trả 500.000₫. */ ?>
                                    <?= $k['transfer_type'] === 'out' ? '−' : '' ?><?= e(money((int) $k['amount'])) ?>
                                </td>
                                <td><?= e((string) ($k['content'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="arefund__done">
                Đơn <?= e((string) ($xem['don_ma'] ?? $xem['order_code'])) ?> đã nhận
                <strong><?= e(money($daNhan)) ?></strong> trên tổng
                <strong><?= e(money((int) ($xem['order_total'] ?? 0))) ?></strong>.
                <?php if ($daNhan < (int) ($xem['order_total'] ?? 0)): ?>
                    Còn thiếu <strong><?= e(money((int) $xem['order_total'] - $daNhan)) ?></strong>.
                <?php endif; ?>
                <?php if ($xem['gan_boi'] !== null && $xem['gan_luc'] !== null): ?>
                    <br>Giao dịch này do người gắn tay lúc
                    <?= e(formatDate((string) $xem['gan_luc'], 'd/m/Y H:i')) ?>.
                <?php endif; ?>
            </p>

            <?php partial('admin/_layout/modal-foot', ['dongUrl' => $dongUrl]); ?>

        <?php elseif ($laTienRa): ?>
            <p class="arefund__done">
                Đây là khoản tiền RA khỏi tài khoản — hoàn tiền, phí ngân hàng, hoặc
                chuyển đi nơi khác. Nó nằm trong sổ để khớp với sao kê, nhưng không
                gắn vào đơn nào được.
            </p>

            <?php partial('admin/_layout/modal-foot', ['dongUrl' => $dongUrl]); ?>

        <?php elseif ($donXacNhan !== null): ?>
            <?php
            /* ── BƯỚC HAI: XÁC NHẬN ĐÚNG ĐƠN ─────────────────────────────
               Người bấm vừa gõ một mã đơn. Trước khi gắn — thao tác không gỡ
               ra được — hiện TÊN KHÁCH, TỔNG TIỀN và SỐ ĐÃ NHẬN của đơn ấy.
               Ba con số đó là thứ để họ nhận ra mình gõ nhầm; nhắc lại mã đơn
               thì gõ nhầm đọc ra giống hệt gõ đúng. Lý do đầy đủ ở
               SepayAdminController::gan(). */
            $daCo = SepayModel::khoanCuaDon((string) $donXacNhan['id']);
            $duNo = 0;

            foreach ($daCo as $k) {
                $duNo += $k['transfer_type'] === 'out' ? -(int) $k['amount'] : (int) $k['amount'];
            }

            $sauKhiGan = $duNo + (int) $xem['amount'];
            $tongDon   = (int) $donXacNhan['total'];
            ?>
            <dl class="arefund__facts">
                <div><dt>Đơn</dt><dd><?= e((string) $donXacNhan['code']) ?></dd></div>
                <div><dt>Khách</dt><dd><?= e((string) $donXacNhan['customer_name']) ?></dd></div>
                <div><dt>Tổng đơn</dt><dd><?= e(money($tongDon)) ?></dd></div>
                <div><dt>Đã nhận</dt><dd><?= e(money($duNo)) ?></dd></div>
            </dl>

            <p class="arefund__done">
                Gắn khoản <strong><?= e(money((int) $xem['amount'])) ?></strong> này vào đơn
                <strong><?= e((string) $donXacNhan['code']) ?></strong> của
                <strong><?= e((string) $donXacNhan['customer_name']) ?></strong>.
                <br>Sau khi gắn, đơn sẽ nhận tổng <strong><?= e(money($sauKhiGan)) ?></strong>
                trên <strong><?= e(money($tongDon)) ?></strong> —
                <?php if ($sauKhiGan >= $tongDon): ?>
                    đủ tiền, đơn chuyển sang “đã thanh toán”.
                <?php elseif ((int) ($donXacNhan['deposit_amount'] ?? 0) > 0
                    && $sauKhiGan >= (int) $donXacNhan['deposit_amount']): ?>
                    đủ cọc, đơn chuyển sang “đã đặt cọc”.
                <?php else: ?>
                    vẫn chưa đủ cọc, đơn giữ nguyên trạng thái.
                <?php endif; ?>
            </p>

            <form method="post" action="/quan-tri/doi-soat/gan" id="gan-don">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= e($xem['id']) ?>">
                <input type="hidden" name="ma_don" value="<?= e($maXacNhan) ?>">
                <input type="hidden" name="xac_nhan" value="1">
                <input type="hidden" name="loc" value="<?= e($loc) ?>">
                <input type="hidden" name="q" value="<?= e($q) ?>">
                <input type="hidden" name="page" value="<?= (int) $page ?>">
            </form>

            <?php partial('admin/_layout/modal-foot', [
                'dongUrl' => $dongUrl,
                'luuForm' => 'gan-don',
                'luuNhan' => 'Đúng đơn này — gắn',
                'ghiChu'  => 'Gắn xong không gỡ ra được.',
            ]); ?>

        <?php else: ?>
            <form method="post" action="/quan-tri/doi-soat/gan" class="aform__grid" id="gan-don">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= e($xem['id']) ?>">
                <input type="hidden" name="loc" value="<?= e($loc) ?>">
                <input type="hidden" name="q" value="<?= e($q) ?>">
                <input type="hidden" name="page" value="<?= (int) $page ?>">

                <div class="field field--wide">
                    <label for="ma_don">Mã đơn hàng *</label>
                    <input type="text" id="ma_don" name="ma_don" required
                           placeholder="DH-260906-1A2B" autocomplete="off">
                    <p class="field__hint">
                        Gõ hoa hay thường đều được, có gạch nối hay không cũng được.
                        Bước sau sẽ hiện tên khách và số tiền của đơn để bạn soát lại
                        trước khi chốt.
                    </p>
                </div>
            </form>

            <?php
            /* ── KHÔNG PHẢI TIỀN KHÁCH TRẢ ───────────────────────────────
               Tài khoản của cửa hàng nhận đủ thứ không phải tiền hàng: lãi
               nhập vốn, chủ nạp tiền vào, nhà cung cấp hoàn, một lượt chuyển
               thử. Không có đường này thì mỗi khoản như vậy ở lại "Không tìm
               thấy đơn" vĩnh viễn và huy hiệu có một cái sàn không bao giờ về
               0 — đúng thứ mà cả cách đếm ở demChuaXuLy() sinh ra để tránh.

               LÝ DO BẮT BUỘC: đây là tuyên bố rằng một khoản tiền CÓ THẬT không
               liên quan tới việc bán hàng. Nó vào nhật ký thao tác. */
            ?>
            <details class="brxnote" style="margin-top:14px;">
                <summary class="brxnote__toggle">Khoản này không phải tiền khách trả</summary>

                <form method="post" action="/quan-tri/doi-soat/bo-qua" class="aform__grid"
                      id="bo-qua-gd">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= e($xem['id']) ?>">
                    <input type="hidden" name="loc" value="<?= e($loc) ?>">
                    <input type="hidden" name="q" value="<?= e($q) ?>">
                    <input type="hidden" name="page" value="<?= (int) $page ?>">

                    <div class="field field--wide">
                        <label for="ly_do">Lý do *</label>
                        <input type="text" id="ly_do" name="ly_do" required maxlength="120"
                               placeholder="Ví dụ: lãi nhập vốn, chủ nạp tiền vào tài khoản">
                        <p class="field__hint">
                            Khoản này sẽ chuyển sang “Bỏ qua” và thôi nằm trong hàng chờ.
                            Số tiền, nội dung và thời điểm giữ nguyên — sổ vẫn khớp sao kê.
                        </p>
                    </div>

                    <button type="submit" class="astatus__save astatus__save--ghost">
                        Đánh dấu bỏ qua
                    </button>
                </form>
            </details>

            <?php partial('admin/_layout/modal-foot', [
                'dongUrl' => $dongUrl,
                'luuForm' => 'gan-don',
                'luuNhan' => 'Tìm đơn này',
                'ghiChu'  => 'Bước sau hiện tên khách và số tiền để soát lại.',
            ]); ?>
        <?php endif; ?>
<?php endif; ?>
