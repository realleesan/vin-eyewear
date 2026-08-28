<?php

/**
 * _tab-don-thuoc.php — tab 3: lịch sử đơn thuốc kính.
 *
 * Biến: $khach, $rxRecords, $rxDeltas, $rxSources, $stores, $doneAppts,
 *       $rxEditing, $auditReady, $duongDan.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TAB NÀY CHỈ VÀO ĐƯỢC BẰNG VAI TRÒ 'admin'.
 *
 * Chặn thật nằm ở CustomerAdminController::canRx(); thanh tab ẩn mục này là
 * chuyện gọn mắt. Đừng dựa vào việc file này "chỉ được require khi có quyền" —
 * hai tầng là yêu cầu của CLAUDE.md quy tắc 4, và tầng ở controller mới là
 * tầng thật.
 *
 * KHÔNG GHI ĐÈ, CHỈ THÊM DÒNG MỚI. Mỗi lần đo là một bản ghi, và cả bảng này
 * tồn tại để đọc được ĐƯỜNG ĐI của độ cận theo năm tháng — sửa đè lên bản cũ
 * là xoá mất chính thứ đang cần xem. Nút "Sửa" chỉ dành cho việc gõ nhầm.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$sua   = $rxEditing;
$form  = $sua ?? [];
$veTab = $duongDan . '?tab=don-thuoc';

/* In một giá trị cầu/trụ với DẤU RÕ RÀNG.

   "+1.00" chứ không phải "1.00": trong nhãn khoa, dấu là thứ phân biệt viễn
   với cận, và một số dương không dấu đứng cạnh một số âm có dấu trong cùng cột
   thì mắt đọc lướt sẽ hiểu nhầm. 0.00 thì không mang dấu nào — nó không phải
   cận cũng không phải viễn. */
$so = static function (mixed $v): string {
    if ($v === null || $v === '') {
        return '—';
    }

    $f = (float) $v;

    return ($f > 0 ? '+' : '') . number_format($f, 2, '.', '');
};
?>

<?php if (!$auditReady): ?>
    <?php /* NÓI RA KHI KHÔNG GHI ĐƯỢC VẾT.

             Bảng vết không ghi được mà không ai biết thì tệ hơn không có bảng
             vết, vì nó tạo cảm giác an toàn giả — người dùng tin rằng mọi lần
             mở tab này đều đã để lại dấu. Xem AuditLogModel. */ ?>
    <div class="anote anote--alert">
        <p>
            <strong>Chưa ghi được vết truy cập.</strong> Bảng
            <code>customer_audit_logs</code> không tồn tại, nên những lần xem và
            sửa dữ liệu sức khoẻ ở đây <strong>không được lưu lại</strong>.
        </p>
        <p>Chạy <code>database/migrations/2026-08-26-module-khach-hang.sql</code> để bật lại.</p>
    </div>
<?php endif; ?>

<div class="anote">
    <p>
        <strong>Dữ liệu sức khoẻ.</strong> Chỉ tài khoản quản trị mở được tab này,
        và mọi lần xem đều được ghi vết. Đừng chụp màn hình hay chép ra ngoài.
    </p>
</div>

<div class="apanel">
    <div class="apanel__head">
        <h2 class="apanel__title">Lịch sử đo (<?= count($rxRecords) ?> lần)</h2>
        <?php if ($sua !== null): ?>
            <a class="apanel__more" href="<?= e($veTab) ?>" data-modal>Huỷ sửa</a>
        <?php endif; ?>
    </div>

    <?php if ($rxRecords === []): ?>
        <p class="apanel__empty">Chưa có bản ghi đo nào cho khách này.</p>
    <?php else: ?>
        <div class="atable-wrap">
            <table class="atable atable--full acus__rx">
                <thead>
                    <tr>
                        <th scope="col">Ngày đo</th>
                        <th scope="col">Nguồn</th>
                        <?php /* Gộp tiêu đề "Mắt phải (OD)" cho ba cột con: ba
                                 nhãn SPH/CYL/AXIS lặp lại hai lần trong cùng
                                 một hàng tiêu đề thì không nói được cột nào
                                 thuộc mắt nào. */ ?>
                        <th scope="col" colspan="3" class="acus__rx-od">Mắt phải (OD)</th>
                        <th scope="col" colspan="3" class="acus__rx-os">Mắt trái (OS)</th>
                        <th scope="col">PD</th>
                        <th scope="col">Thay đổi</th>
                        <th scope="col">Nơi đo</th>
                        <th scope="col"></th>
                    </tr>
                    <tr class="acus__rx-sub">
                        <th scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col" class="acus__rx-od">SPH</th>
                        <th scope="col" class="acus__rx-od">CYL</th>
                        <th scope="col" class="acus__rx-od">AXIS</th>
                        <th scope="col" class="acus__rx-os">SPH</th>
                        <th scope="col" class="acus__rx-os">CYL</th>
                        <th scope="col" class="acus__rx-os">AXIS</th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rxRecords as $i => $rx): ?>
                        <?php
                        $lech    = $rxDeltas[$rx['id']] ?? null;
                        $moiNhat = $i === 0;
                        ?>
                        <tr<?= $moiNhat ? ' class="is-latest"' : '' ?>>
                            <td>
                                <?= e(formatDate($rx['measured_at'])) ?>
                                <?php if ($moiNhat): ?>
                                    <span class="atable__sub">
                                        <?= PrescriptionRecordModel::conHieuLuc($rx)
                                            ? 'đang dùng · còn hiệu lực'
                                            : 'đang dùng · nên đo lại' ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php /* Nguồn 'store' dùng viên nền xanh, hai
                                         nguồn kia viên trung tính: người đọc
                                         phải phân biệt được ngay số nào do máy
                                         đo ra và số nào do khách nhớ lại —
                                         CLAUDE.md điểm A1. */ ?>
                                <span class="badge badge--<?= $rx['source'] === 'store' ? 'in_stock' : 'neutral' ?>">
                                    <?= e($rxSources[$rx['source']] ?? $rx['source']) ?>
                                </span>
                            </td>

                            <td class="acus__rx-od num"><?= e($so($rx['od_sph'])) ?></td>
                            <td class="acus__rx-od num"><?= e($so($rx['od_cyl'])) ?></td>
                            <td class="acus__rx-od num">
                                <?= $rx['od_axis'] !== null ? e($rx['od_axis']) . '°' : '—' ?>
                            </td>

                            <td class="acus__rx-os num"><?= e($so($rx['os_sph'])) ?></td>
                            <td class="acus__rx-os num"><?= e($so($rx['os_cyl'])) ?></td>
                            <td class="acus__rx-os num">
                                <?= $rx['os_axis'] !== null ? e($rx['os_axis']) . '°' : '—' ?>
                            </td>

                            <td class="num"><?= $rx['pd'] !== null ? e($rx['pd']) : '—' ?></td>

                            <td>
                                <?php if ($lech === null): ?>
                                    <?php /* Bản ghi cũ nhất không có gì trước nó
                                             để so. Đó là dữ liệu THIẾU, không
                                             phải chênh lệch bằng 0 — hai thứ đó
                                             phải trông khác nhau. */ ?>
                                    <span class="atable__sub">lần đo đầu</span>
                                <?php elseif ($lech['od'] === null && $lech['os'] === null): ?>
                                    <span class="atable__sub">—</span>
                                <?php else: ?>
                                    <span class="acus__delta">
                                        <?php foreach (['od' => 'P', 'os' => 'T'] as $mat => $nhan): ?>
                                            <?php if ($lech[$mat] !== null): ?>
                                                <?php /* Tăng độ (âm hơn) là tin
                                                         xấu -> tô đỏ. Giữ nguyên
                                                         hoặc giảm thì không tô. */ ?>
                                                <span class="acus__delta-eye<?= $lech[$mat] < 0 ? ' is-worse' : '' ?>">
                                                    <?= e($nhan) ?>
                                                    <?= $lech[$mat] > 0 ? '+' : '' ?><?= e(number_format($lech[$mat], 2, '.', '')) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </span>
                                    <?php if ($lech['thang'] !== null): ?>
                                        <span class="atable__sub">
                                            sau <?= (int) $lech['thang'] ?> tháng
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= $rx['store_name'] !== null
                                    ? e($rx['store_name'])
                                    : '<span class="atable__sub">—</span>' ?>
                                <?php if ($rx['appointment_code'] !== null): ?>
                                    <?php /* Link sang module Lịch hẹn — dữ liệu
                                             đó không thuộc module này. */ ?>
                                    <span class="atable__sub">
                                        <a href="/quan-tri/lich-hen"><?= e($rx['appointment_code']) ?></a>
                                    </span>
                                <?php endif; ?>
                                <?php if ($rx['note'] !== null && $rx['note'] !== ''): ?>
                                    <span class="atable__sub"><?= e($rx['note']) ?></span>
                                <?php endif; ?>
                                <?php if ($rx['author_name'] !== null): ?>
                                    <span class="atable__sub">nhập bởi <?= e($rx['author_name']) ?></span>
                                <?php endif; ?>
                            </td>

                            <td class="arow-actions">
                                <?php /* Neo #form-don-thuoc: form sửa nằm DƯỚI bảng, mà mỗi
                                         lần đổi địa chỉ là thân hộp thoại cuộn về
                                         đầu — không có neo thì bấm "Sửa" trông như
                                         không có gì xảy ra. */ ?>
                                <a href="<?= e($veTab . '&sua=' . rawurlencode($rx['id'])) ?>#form-don-thuoc"
                                   data-modal>Sửa</a>
                                <form method="post" action="/quan-tri/khach-hang/don-thuoc/xoa"
                                      data-confirm="Xoá bản ghi đo ngày <?= e(formatDate($rx['measured_at'])) ?>? Lịch sử độ kính sẽ mất một mốc và không khôi phục được."
                                      data-confirm-title="Xoá bản ghi đo?"
                                      data-confirm-ok="Xoá">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($khach['id']) ?>">
                                    <input type="hidden" name="rx_id" value="<?= e($rx['id']) ?>">
                                    <button type="submit" class="arow-del">Xoá</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<section class="apanel" id="form-don-thuoc">
    <div class="apanel__head">
        <h2 class="apanel__title"><?= $sua !== null ? 'Sửa bản ghi đo' : 'Thêm lần đo mới' ?></h2>
    </div>

    <?php if ($sua === null): ?>
        <p class="aform__note">
            Mỗi lần đo là một bản ghi mới — <strong>đừng sửa bản cũ</strong> khi khách
            đo lại. Bản cũ chính là thứ cho biết độ đã tăng bao nhiêu.
        </p>
    <?php endif; ?>

    <form class="aform" method="post" action="/quan-tri/khach-hang/don-thuoc/luu">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= e($khach['id']) ?>">
        <?php if ($sua !== null): ?>
            <input type="hidden" name="rx_id" value="<?= e($sua['id']) ?>">
        <?php endif; ?>

        <div class="aform__grid">
            <div class="field">
                <label for="ngay-do">Ngày đo</label>
                <input type="date" id="ngay-do" name="measured_at" required
                       max="<?= e(date('Y-m-d')) ?>"
                       value="<?= e((string) ($form['measured_at'] ?? date('Y-m-d'))) ?>">
            </div>

            <div class="field">
                <label for="nguon">Nguồn số đo</label>
                <select id="nguon" name="source" required>
                    <?php foreach ($rxSources as $ma => $nhan): ?>
                        <option value="<?= e($ma) ?>"
                            <?= ($form['source'] ?? 'store') === $ma ? 'selected' : '' ?>>
                            <?= e($nhan) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php /* Nhắc ngay tại ô: đây là cột hay bị điền cho xong nhất,
                         mà điền sai thì số khách nhớ mang máng được đóng dấu
                         "kỹ thuật viên đo". CLAUDE.md điểm A1. */ ?>
                <p class="field__hint">Chọn đúng — số khách tự khai không được ghi là cửa hàng đo.</p>
            </div>

            <?php foreach (['od' => 'Mắt phải (OD)', 'os' => 'Mắt trái (OS)'] as $mat => $nhan): ?>
                <fieldset class="acus__eye field--wide">
                    <legend><?= e($nhan) ?></legend>

                    <div class="acus__eye-row">
                        <div class="field">
                            <label for="<?= $mat ?>-sph">SPH (cầu)</label>
                            <?php /* step 0.25: tròng chỉ mài theo bước 1/4 diop,
                                     nên mọi giá trị lẻ hơn thế đều là gõ nhầm.
                                     Đây chỉ là gợi ý của trình duyệt — máy chủ
                                     vẫn kiểm lại khoảng -30..30. */ ?>
                            <input type="number" id="<?= $mat ?>-sph" name="<?= $mat ?>_sph"
                                   step="0.25" min="-30" max="30" placeholder="-2.25"
                                   value="<?= e((string) ($form[$mat . '_sph'] ?? '')) ?>">
                        </div>
                        <div class="field">
                            <label for="<?= $mat ?>-cyl">CYL (trụ)</label>
                            <input type="number" id="<?= $mat ?>-cyl" name="<?= $mat ?>_cyl"
                                   step="0.25" min="-30" max="30" placeholder="-0.75"
                                   value="<?= e((string) ($form[$mat . '_cyl'] ?? '')) ?>">
                        </div>
                        <div class="field">
                            <label for="<?= $mat ?>-axis">AXIS (trục)</label>
                            <input type="number" id="<?= $mat ?>-axis" name="<?= $mat ?>_axis"
                                   step="1" min="0" max="180" placeholder="180"
                                   value="<?= e((string) ($form[$mat . '_axis'] ?? '')) ?>">
                        </div>
                        <div class="field">
                            <label for="<?= $mat ?>-va">Thị lực</label>
                            <?php /* CHỮ chứ không số: thị lực ghi dạng phân số
                                     "10/10", type="number" sẽ chặn dấu gạch. */ ?>
                            <input type="text" id="<?= $mat ?>-va" name="<?= $mat ?>_va"
                                   maxlength="16" placeholder="10/10"
                                   value="<?= e((string) ($form[$mat . '_va'] ?? '')) ?>">
                        </div>
                    </div>
                </fieldset>
            <?php endforeach; ?>

            <div class="field">
                <label for="pd">PD — khoảng cách đồng tử (mm)</label>
                <input type="number" id="pd" name="pd" step="0.5" min="30" max="90"
                       placeholder="63"
                       value="<?= e((string) ($form['pd'] ?? '')) ?>">
            </div>

            <div class="field">
                <label for="co-so">Nơi đo</label>
                <select id="co-so" name="store_id">
                    <option value="">— không ghi —</option>
                    <?php foreach ($stores as $cs): ?>
                        <option value="<?= e($cs['id']) ?>"
                            <?= ($form['store_id'] ?? '') === $cs['id'] ? 'selected' : '' ?>>
                            <?= e($cs['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field field--wide">
                <label for="lich-hen">Gắn với lịch hẹn</label>
                <select id="lich-hen" name="appointment_id">
                    <option value="">— không gắn (toa mang từ ngoài vào) —</option>
                    <?php foreach ($doneAppts as $lh): ?>
                        <option value="<?= e($lh['id']) ?>"
                            <?= ($form['appointment_id'] ?? '') === $lh['id'] ? 'selected' : '' ?>>
                            <?= e($lh['code']) ?> — <?= e(formatDate($lh['appointment_date'])) ?>
                            <?= $lh['store_name'] !== null ? ' — ' . e($lh['store_name']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php /* Chỉ liệt kê lịch ĐÃ HOÀN TẤT: gắn số đo vào một buổi hẹn
                         chưa diễn ra là nói rằng nó lấy từ một lần đo chưa xảy
                         ra. Máy chủ kiểm lại điều này, xem
                         PrescriptionRecordModel::validate(). */ ?>
                <p class="field__hint">
                    <?= $doneAppts === []
                        ? 'Khách chưa có lịch hẹn nào đã hoàn tất.'
                        : 'Chỉ hiện lịch hẹn đã hoàn tất của khách này.' ?>
                </p>
            </div>

            <div class="field field--wide">
                <label for="ghi-chu-rx">Ghi chú</label>
                <input type="text" id="ghi-chu-rx" name="note" maxlength="255"
                       placeholder="Ví dụ: khuyến nghị tròng chống ánh sáng xanh"
                       value="<?= e((string) ($form['note'] ?? '')) ?>">
            </div>

            <button type="submit" class="astatus__save">
                <?= $sua !== null ? 'Lưu bản ghi' : 'Thêm bản ghi' ?>
            </button>
        </div>
    </form>
</section>
