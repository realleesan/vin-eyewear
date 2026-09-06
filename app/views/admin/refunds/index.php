<?php

/**
 * admin/refunds/index.php — hàng chờ duyệt hoàn tiền cọc.
 *
 * SRS v2.1.0, UC-04 · FR-DH-14.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ HÀNG CHỜ CÓ NGƯỜI ĐANG ĐỢI TIỀN Ở ĐẦU BÊN KIA
 *
 * Vì thế bảng sắp CHỜ DUYỆT lên đầu, rồi mới tới thời gian — không phải thứ tự
 * thời gian thuần, vốn đẩy những yêu cầu cũ chưa duyệt xuống dưới đống yêu cầu
 * mới đã hoàn.
 *
 * BỐN CON SỐ ĐI CÙNG NHAU trên mỗi dòng, không tách ra ngăn kéo: số tiền cửa
 * hàng ĐÃ NHẬN, tiền tròng, số đề nghị, và căn cứ (đã mài hay chưa). Người duyệt
 * cần cả bốn để quyết trong một cái nhìn — bắt họ mở từng dòng ra xem là biến
 * một việc hai phút thành hai mươi.
 *
 * "ĐÃ NHẬN" KHÔNG ĐỒNG NGHĨA "TIỀN CỌC". Với đơn khách chuyển đủ một lần thì
 * cửa hàng đang giữ cả tổng đơn chứ không phải 30% — xem
 * RefundRequestModel::daNhan(). Cột trong bảng vì thế tên là "Đã nhận".
 *
 * HỆ THỐNG KHÔNG CHUYỂN TIỀN. Mọi nút ở đây chỉ ghi lại quyết định; việc chuyển
 * khoản làm ở ngân hàng rồi quay lại bấm "Đã hoàn tiền". Chữ trên nút nói đúng
 * điều đó — "Duyệt" chứ không phải "Hoàn tiền", "Đã hoàn tiền" chứ không phải
 * "Chuyển tiền".
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * NGĂN KÉO MỞ CHO MỌI NHÂN VIÊN, form thì chỉ Quản trị viên ($canDuyet). Cả màn
 * này sinh ra để người trực quầy trả lời được câu "tiền của tôi đến đâu rồi" mà
 * không phải đi tìm Quản trị viên chỉ để ĐỌC một con số.
 *
 * Biến: $refunds, $counts, $status, $statuses, $canDuyet, $soSot, $sot,
 *       $sanSang, $xem
 */
?>
<div class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Hoàn tiền cọc</h1>
        <p class="ahead__lead">
            <?= (int) ($counts['pending'] ?? 0) ?> yêu cầu chờ duyệt ·
            <?= (int) ($counts['approved'] ?? 0) ?> đã duyệt, chờ chuyển tiền.
            <?php if (!$canDuyet): ?>
                Chỉ Quản trị viên duyệt được; bạn xem để trả lời khách.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if (!$sanSang): ?>
    <p class="apanel__empty">
        Chưa nâng cấp cơ sở dữ liệu nên chưa có sổ hoàn tiền.
        Chạy <code>database/migrations/2026-09-06-dot-4-khach-chu-dong.sql</code> rồi tải lại trang.
    </p>
<?php else: ?>

<?php if ($soSot > 0): ?>
    <?php
    /* ĐƠN RƠI QUA KHE — nói ra, đừng để im.

       RefundRequestModel::taoChoDonHuy() cố ý không chặn luồng huỷ đơn khi việc
       ghi sổ hoàn tiền hỏng: việc huỷ đã xong và khách đã thấy nó xong. Cái giá
       là một đơn có cọc bị huỷ mà không có yêu cầu hoàn tiền nào — tức một
       khách đang chờ tiền mà hàng chờ này không biết.

       LẦN ĐẦU BẬT MÀN NÀY THÌ CON SỐ SẼ LỚN, và đó là đúng: mọi đơn đã huỷ mà
       cửa hàng còn giữ tiền, từ trước khi có sổ hoàn tiền, đều rơi vào đây.
       Chúng là tồn đọng thật cần dọn một lượt, không phải lỗi hiển thị.

       Dòng cảnh báo đứng TRÊN bảng vì nó nói về những thứ KHÔNG có trong bảng.
       Đặt xuống dưới thì người đọc hết bảng rồi tưởng đã xong việc.

       In luôn vài mã đơn: một con số trần chỉ nói "có việc", còn mã đơn thì bắt
       đầu làm được ngay. */
    ?>
    <div class="anote anote--alert">
    <p>
        Có <strong><?= (int) $soSot ?></strong> đơn đã huỷ mà cửa hàng vẫn đang giữ
        tiền, nhưng <strong>không có yêu cầu hoàn tiền nào</strong>. Đây là những
        khách đang chờ tiền mà bảng dưới không thấy — kiểm tra thủ công và liên hệ họ.
        <?php if ($sot !== []): ?>
            <br>Ví dụ:
            <?php foreach (array_slice($sot, 0, 8) as $i => $d): ?>
                <?= $i > 0 ? ' · ' : '' ?><strong><?= e($d['code']) ?></strong>
                (<?= e(money(RefundRequestModel::daNhan($d))) ?>)
            <?php endforeach; ?>
            <?php if ($soSot > 8): ?> … <?php endif; ?>
        <?php endif; ?>
    </p>
    </div>
<?php endif; ?>

<?php partial('admin/_layout/filter-tabs', [
    'base' => '/quan-tri/hoan-tien', 'statuses' => $statuses,
    'counts' => $counts, 'current' => $status,
]); ?>

<?php if ($refunds === []): ?>
    <p class="apanel__empty">Chưa có yêu cầu hoàn tiền nào.</p>
<?php else: ?>
    <div class="atable-wrap">
        <table class="atable atable--full">
            <thead>
                <tr>
                    <th scope="col">Đơn</th>
                    <th scope="col">Khách</th>
                    <th scope="col" class="num">Đã nhận</th>
                    <th scope="col" class="num">Tiền tròng</th>
                    <th scope="col" class="num">Đề nghị hoàn</th>
                    <th scope="col">Căn cứ</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($refunds as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['order_code']) ?></strong>
                            <span class="atable__sub"><?= e(formatDate($r['created_at'], 'd/m/Y')) ?></span>
                        </td>
                        <td>
                            <?= e($r['customer_name'] ?? '—') ?>
                            <span class="atable__sub"><?= e($r['customer_phone'] ?? '') ?></span>
                        </td>
                        <td class="num"><?= e(money((int) $r['received_amount'])) ?></td>
                        <td class="num">
                            <?= (int) $r['lens_amount'] > 0
                                ? e(money((int) $r['lens_amount']))
                                : '<span class="atable__sub">—</span>' ?>
                        </td>
                        <td class="num">
                            <strong><?= e(money((int) $r['suggested_amount'])) ?></strong>
                            <?php if ($r['approved_amount'] !== null
                                      && (int) $r['approved_amount'] !== (int) $r['suggested_amount']): ?>
                                <?php /* Số ĐÃ DUYỆT khác số đề nghị thì phải thấy cả hai:
                                         con số cuối cùng là thứ đem đi chuyển khoản, nhưng
                                         con số đề nghị là thứ giải thích vì sao có lý do
                                         kèm theo. */ ?>
                                <span class="atable__sub">
                                    duyệt <?= e(money((int) $r['approved_amount'])) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $r['shop_fault'] === 1): ?>
                                <span class="badge badge--neutral">Lỗi cửa hàng</span>
                            <?php elseif ((int) $r['lens_started'] === 1): ?>
                                <span class="atable__sub">Huỷ sau khi đã mài</span>
                            <?php else: ?>
                                <span class="atable__sub">Huỷ trước khi mài</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge--<?= $r['status'] === 'refunded' ? 'in_stock' : 'neutral' ?>">
                                <?= e($statuses[$r['status']] ?? $r['status']) ?>
                            </span>
                            <?php if ($r['refunded_on'] !== null): ?>
                                <span class="atable__sub"><?= e(formatDate($r['refunded_on'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="arow-actions">
                            <?php
                            /* NHÂN VIÊN CŨNG MỞ ĐƯỢC NGĂN KÉO, chỉ không thấy form.

                               Cả màn này sinh ra để người trực quầy trả lời được
                               câu "tiền của tôi đến đâu rồi" mà không phải đi tìm
                               Quản trị viên. Bảng cho con số, nhưng lý do duyệt
                               lệch và ngày chuyển khoản thì chỉ ngăn kéo có —
                               và đó đúng là hai thứ khách hỏi tiếp. */
                            $nhan = $canDuyet
                                ? ($r['status'] === 'pending' ? 'Duyệt'
                                   : ($r['status'] === 'approved' ? 'Đã hoàn tiền' : 'Xem'))
                                : 'Xem';
                            ?>
                            <a href="/quan-tri/hoan-tien?xem=<?= e(rawurlencode($r['id'])) ?>" data-modal>
                                <?= e($nhan) ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
/* ─────────────────────────────────────────────────────────────────────────────
   NGĂN KÉO XỬ LÝ — mở bằng ?xem=<id>, cùng lối với mọi màn khác.

   Hai hình dạng tuỳ trạng thái: 'pending' thì hỏi DUYỆT hay TỪ CHỐI; 'approved'
   thì hỏi NGÀY đã chuyển tiền. Không gộp làm một form nhiều nhánh — hai việc
   xảy ra cách nhau vài ngày và người bấm đang nghĩ về hai chuyện khác nhau.
   ───────────────────────────────────────────────────────────────────────────── */
?>
<?php if ($xem !== null): ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => 'Hoàn tiền cọc — đơn ' . $xem['order_code'],
        'phu'     => 'Hệ thống không chuyển tiền. Ở đây chỉ ghi lại quyết định.',
        'dongUrl' => '/quan-tri/hoan-tien',
        'rong'    => '',
    ]); ?>

        <dl class="arefund__facts">
            <?php /* "ĐÃ NHẬN", không phải "tiền cọc": với đơn khách đã chuyển đủ một
             lần thì cửa hàng đang giữ cả tổng đơn chứ không phải 30%. Xem
             RefundRequestModel::daNhan(). */ ?>
            <div><dt>Cửa hàng đã nhận</dt><dd><?= e(money((int) $xem['received_amount'])) ?></dd></div>
            <div><dt>Tiền tròng của đơn</dt><dd><?= e(money((int) $xem['lens_amount'])) ?></dd></div>
            <div>
                <dt>Căn cứ</dt>
                <dd><?= (int) $xem['lens_started'] === 1
                        ? 'Huỷ SAU khi đã bấm mốc bắt đầu mài'
                        : 'Huỷ TRƯỚC khi bấm mốc bắt đầu mài' ?></dd>
            </div>
            <div>
                <dt>Hệ thống đề nghị hoàn</dt>
                <dd><strong><?= e(money((int) $xem['suggested_amount'])) ?></strong></dd>
            </div>
        </dl>

        <?php
        /* ─────────────────────────────────────────────────────────────────────
           BA HÌNH DẠNG, KHÔNG PHẢI HAI

           Bản đầu chỉ có if 'pending' / else, và cái else ấy nuốt luôn hai
           trạng thái KẾT THÚC: một yêu cầu đã hoàn tiền vẫn được vẽ lại form
           "Ngày đã chuyển tiền", còn một yêu cầu bị TỪ CHỐI thì hiện dòng "Đã
           duyệt hoàn 0đ" — nói ngược hẳn điều đã xảy ra.

           Chỉ 'pending' và 'approved' có việc để làm. Hai trạng thái còn lại
           chỉ để đọc, và đọc là đúng thứ nhân viên trực quầy cần.

           $canDuyet gác form chứ không gác cả ngăn kéo: xem thì mọi nhân viên,
           duyệt thì chỉ Quản trị viên — BR-DH-14.4, và phép chặn thật nằm ở ba
           action ghi của controller, không ở đây.
           ───────────────────────────────────────────────────────────────────── */
        ?>
        <?php if (!$canDuyet): ?>
            <?php if ($xem['decided_at'] !== null): ?>
                <p class="arefund__done">
                    <?= $xem['status'] === 'rejected' ? 'Đã từ chối hoàn tiền' : 'Đã duyệt hoàn' ?>
                    <?php if ($xem['status'] !== 'rejected' && $xem['approved_amount'] !== null): ?>
                        <strong><?= e(money((int) $xem['approved_amount'])) ?></strong>
                    <?php endif; ?>
                    <?php if ($xem['decided_by_name'] !== null): ?>
                        bởi <?= e($xem['decided_by_name']) ?>
                    <?php endif; ?>
                    <?= e(formatDate((string) $xem['decided_at'], 'd/m/Y H:i')) ?>.
                    <?php if ($xem['decision_note'] !== null): ?>
                        <br>Lý do: <?= e($xem['decision_note']) ?>
                    <?php endif; ?>
                    <?php if ($xem['refunded_on'] !== null): ?>
                        <br>Đã chuyển tiền ngày <?= e(formatDate((string) $xem['refunded_on'])) ?>.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <p class="arefund__done">
                    Đang chờ Quản trị viên duyệt. Nếu khách hỏi, hãy nói cửa hàng đã
                    ghi nhận và sẽ liên hệ lại.
                </p>
            <?php endif; ?>

            <?php partial('admin/_layout/modal-foot', ['dongUrl' => '/quan-tri/hoan-tien']); ?>

        <?php elseif ($xem['status'] === 'pending'): ?>
            <form method="post" action="/quan-tri/hoan-tien/duyet" class="aform__grid" id="hoan-duyet">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= e($xem['id']) ?>">

                <div class="field field--check field--wide">
                    <label>
                        <input type="checkbox" name="loi_cua_hang" value="1">
                        <span>
                            <strong>Lỗi từ phía cửa hàng</strong> — mài sai độ, giao sai hàng,
                            hết hàng sau khi nhận cọc. Tích ô này thì hoàn
                            <strong>toàn bộ số tiền đã nhận</strong> bất kể đã mài hay chưa.
                        </span>
                    </label>
                </div>

                <div class="field">
                    <label for="hoan-so">Số tiền hoàn</label>
                    <?php /* Để trống = duyệt theo số đề nghị. Không điền sẵn con số:
                             một ô đã có sẵn giá trị là một ô người ta bấm qua mà
                             không đọc, và đây là ô quyết định chi bao nhiêu tiền. */ ?>
                    <input type="text" id="hoan-so" name="so_tien" inputmode="numeric"
                           placeholder="Để trống = <?= e(money((int) $xem['suggested_amount'])) ?>">
                    <p class="field__hint">
                        Khác số đề nghị thì bắt buộc ghi lý do bên dưới.
                    </p>
                </div>

                <div class="field field--wide">
                    <label for="hoan-ly-do">Lý do duyệt lệch</label>
                    <input type="text" id="hoan-ly-do" name="ly_do" maxlength="500"
                           placeholder="Bắt buộc khi duyệt số khác đề nghị">
                </div>
            </form>

            <?php
            /* TỪ CHỐI LÀ MỘT FORM RIÊNG, CÓ Ô LÝ DO RIÊNG VÀ NÚT RIÊNG.

               Hai lý do không gộp được vào form duyệt:

                 · Gộp thì một cú bấm nhầm nút biến "duyệt 520.000đ" thành "từ
                   chối hoàn" — hai kết quả trái ngược nhau cho cùng một khoản
                   tiền của cùng một khách.
                 · tuChoi() BẮT BUỘC lý do tối thiểu 10 ký tự, còn duyet() chỉ
                   đòi khi số lệch. Dùng chung một ô thì ô ấy vừa bắt buộc vừa
                   không, tuỳ nút nào được bấm — không nhãn nào viết đúng được.

               Bản đầu có form vệ tinh này nhưng KHÔNG có ô lý do và KHÔNG có
               nút nào gửi nó, nên cả đường từ chối chưa bao giờ chạy được: mọi
               yêu cầu sai đều kẹt vĩnh viễn ở "chờ duyệt". Nút nằm TRONG form
               vì modal-foot chỉ đẻ ra đúng một nút Lưu, và nút ấy thuộc về
               form duyệt. */
            ?>
            <form method="post" action="/quan-tri/hoan-tien/tu-choi" class="aform__grid arefund__reject">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= e($xem['id']) ?>">

                <div class="field field--wide">
                    <label for="tuchoi-ly-do">Từ chối hoàn tiền — lý do</label>
                    <input type="text" id="tuchoi-ly-do" name="ly_do" maxlength="500"
                           placeholder="Bắt buộc, tối thiểu 10 ký tự. Khách sẽ hỏi câu này.">
                </div>

                <div class="field field--wide">
                    <button type="submit" class="astatus__save astatus__save--ghost">
                        Từ chối hoàn tiền
                    </button>
                </div>
            </form>

            <?php partial('admin/_layout/modal-foot', [
                'dongUrl' => '/quan-tri/hoan-tien',
                'luuNhan' => 'Duyệt hoàn tiền',
                'luuForm' => 'hoan-duyet',
            ]); ?>

        <?php elseif ($xem['status'] === 'approved'): ?>
            <p class="arefund__done">
                Đã duyệt hoàn <strong><?= e(money((int) ($xem['approved_amount'] ?? 0))) ?></strong>
                <?php if ($xem['decided_by_name'] !== null): ?>
                    bởi <?= e($xem['decided_by_name']) ?>
                <?php endif; ?>
                <?= e(formatDate((string) $xem['decided_at'], 'd/m/Y H:i')) ?>.
                <?php if ($xem['decision_note'] !== null): ?>
                    <br>Lý do: <?= e($xem['decision_note']) ?>
                <?php endif; ?>
            </p>

            <form method="post" action="/quan-tri/hoan-tien/da-hoan" class="aform__grid" id="hoan-xong">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= e($xem['id']) ?>">

                <div class="field">
                    <label for="hoan-ngay">Ngày đã chuyển tiền <span aria-hidden="true">*</span></label>
                    <?php /* KHÔNG lấy NOW(): chuyển khoản làm ở ngân hàng có thể
                             trước lúc bấm vài giờ hoặc vài ngày, và đối soát sau
                             này đọc đúng cột này. Điền sẵn hôm nay cho tiện, vẫn
                             sửa được. */ ?>
                    <input type="date" id="hoan-ngay" name="ngay" required
                           max="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>">
                </div>

                <div class="field field--wide">
                    <label for="hoan-ghi">Ghi chú</label>
                    <input type="text" id="hoan-ghi" name="ghi_chu" maxlength="500"
                           placeholder="Ví dụ: chuyển khoản TPBank, mã giao dịch …">
                </div>
            </form>

            <?php partial('admin/_layout/modal-foot', [
                'dongUrl' => '/quan-tri/hoan-tien',
                'luuNhan' => 'Đã hoàn tiền',
                'luuForm' => 'hoan-xong',
            ]); ?>

        <?php else: ?>
            <?php /* 'refunded' và 'rejected' — đã xong, chỉ để đọc. KHÔNG vẽ form
                     nào: một yêu cầu đã hoàn mà vẫn hiện nút "Đã hoàn tiền" là mời
                     người ta bấm lần hai, và câu trả lời duy nhất họ nhận được sẽ
                     là một dòng lỗi. */ ?>
            <p class="arefund__done">
                <?php if ($xem['status'] === 'rejected'): ?>
                    <strong>Đã từ chối hoàn tiền</strong>
                <?php else: ?>
                    <strong>Đã hoàn <?= e(money((int) ($xem['approved_amount'] ?? 0))) ?></strong>
                <?php endif; ?>
                <?php if ($xem['decided_by_name'] !== null): ?>
                    — <?= e($xem['decided_by_name']) ?>
                <?php endif; ?>
                <?php if ($xem['decided_at'] !== null): ?>
                    , <?= e(formatDate((string) $xem['decided_at'], 'd/m/Y H:i')) ?>
                <?php endif; ?>.
                <?php if ($xem['decision_note'] !== null): ?>
                    <br>Lý do: <?= e($xem['decision_note']) ?>
                <?php endif; ?>
                <?php if ($xem['refunded_on'] !== null): ?>
                    <br>Chuyển tiền ngày <?= e(formatDate((string) $xem['refunded_on'])) ?>
                    <?= $xem['refund_note'] !== null ? ' — ' . e($xem['refund_note']) : '' ?>.
                <?php endif; ?>
            </p>

            <?php partial('admin/_layout/modal-foot', ['dongUrl' => '/quan-tri/hoan-tien']); ?>
        <?php endif; ?>
<?php endif; ?>

<?php endif; ?>
