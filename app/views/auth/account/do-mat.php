<?php

/**
 * auth/account/do-mat.php — mục "Thông số đo mắt" (/tai-khoan?muc=do-mat).
 *
 * Bản thiết kế vẽ mục này ở dạng CHỈ ĐỌC: một thẻ gồm dòng "Đo ngày … · Cơ sở
 * …", bảng 5 cột hai mắt, và hai thẻ tròn (PD, khuyến nghị). Đó là trạng thái
 * mặc định ở đây.
 *
 * Huy hiệu hiệu lực và câu nhắc đo lại đã gỡ theo SRS v2.1.0 (H09): hệ thống
 * không kết luận một số đo còn dùng được hay không, ngày đo là đủ.
 *
 * Form tự nhập nằm ở trạng thái riêng (?sua=1). Nó có từ trước bản thiết kế
 * và là cách duy nhất để khách mang đơn thuốc đo ở nơi khác sang, nên không
 * bỏ — chỉ chuyển ra khỏi màn hình mặc định. Chưa có thông số nào thì
 * controller mở thẳng form, khỏi bắt khách nhìn một thẻ rỗng rồi tự tìm nút.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỤC "KÍNH ĐANG ĐEO" ĐÃ GỠ — SRS v2.1.0, A20
 *
 * Trang này từng có thẻ thứ hai cho khách tự khai cặp kính đang dùng. Chủ đầu
 * tư đã bỏ: dữ liệu tự khai, không ai đối chiếu, và trên thực tế gần như không
 * được điền.
 * ─────────────────────────────────────────────────────────────────────────────
 */

?>

<div class="acct-head acct-head--row">
    <div>
        <h1 class="acct-head__title">Thông số đo mắt</h1>
        <p class="acct-head__lead">Kết quả đo khúc xạ gần nhất tại Vin Eyewear.</p>
    </div>
    <div class="acct-head__actions">
        <?php if (!$editing): ?>
            <a class="acct-btn acct-btn--outline" href="/tai-khoan?muc=do-mat&amp;sua=1">Tự nhập</a>
        <?php endif; ?>
        <a class="acct-btn acct-btn--primary" href="/dat-lich">Đặt lịch đo lại</a>
    </div>
</div>

<?php if ($editing): ?>

    <form class="acct-card acct-form" method="post" action="/tai-khoan/khuc-xa">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

        <h2 class="acct-form__title">Nhập thông số</h2>

        <table class="acct-rx acct-rx--edit">
            <caption class="sr-only">Thông số khúc xạ hai mắt</caption>
            <thead>
                <tr>
                    <th scope="col">Mắt</th>
                    <th scope="col">Cầu (SPH)</th>
                    <th scope="col">Trụ (CYL)</th>
                    <th scope="col">Trục (AXIS)</th>
                    <th scope="col">Thị lực</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ([['od', 'Phải (OD)'], ['os', 'Trái (OS)']] as [$eye, $label]): ?>
                    <tr>
                        <th scope="row"><?= e($label) ?></th>
                        <?php
                        /* ══════════ Ô ĐỘ CẦU: DẤU TÁCH RA HAI NÚT ══════════
                           Giống hệt hộp thoại mua hàng (_layout/buy-modal.php).

                           Trước đây ô này là <input type=number min=-20>, tức
                           khách gõ dấu bằng bàn phím. Cùng một con số độ cầu
                           được nhập ở HAI nơi bằng HAI giao diện khác nhau —
                           mà lý do cửa hàng yêu cầu tách dấu ra ("đọc nhầm dấu
                           là mài ngược hẳn một cặp tròng") đúng cho cả hai chỗ
                           như nhau. Ô số còn có một cái bẫy riêng: bánh xe
                           chuột lăn qua nó là đổi độ mà không ai để ý.

                           Hồ sơ thì KHÁC hộp thoại mua ở một điểm: nó ĐIỀN SẴN
                           thứ đã lưu. CSDL giữ "-2.00" nguyên chuỗi, nên phải
                           tách ngược lại thành cặp dấu + độ lớn —
                           LensModel::splitSph(). */
                        [$sphSign, $sphMag] = LensModel::splitSph($prescription[$eye . '_sph'] ?? null);
                        ?>
                        <td>
                            <span class="sr-only" id="rxcap-<?= e($eye) ?>-sph">
                                <?= e($label) ?> độ cầu
                            </span>
                            <div class="acct-rx__sph">
                                <?php /* Hai ô radio THẬT, không phải <button>: bàn phím
                                         và trình đọc màn hình phải biết cái nào đang
                                         được chọn. Cùng cách với .acct-choice ở mục Hồ sơ. */ ?>
                                <div class="acct-rxsign" role="radiogroup"
                                     aria-labelledby="rxcap-<?= e($eye) ?>-sph">
                                    <?php foreach (LensModel::sphSignOptions() as $sg): ?>
                                        <label class="acct-rxsign__opt" title="<?= e($sg['note']) ?>">
                                            <input type="radio" name="<?= e($eye) ?>_dau"
                                                   value="<?= e($sg['value']) ?>"
                                                   <?= $sphSign === $sg['value'] ? 'checked' : '' ?>>
                                            <span><?= e($sg['label']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <label class="sr-only" for="<?= e($eye) ?>_sph">
                                    <?= e($label) ?> độ lớn độ cầu
                                </label>
                                <select class="acct-rx__input" id="<?= e($eye) ?>_sph"
                                        name="<?= e($eye) ?>_sph">
                                    <option value="">—</option>
                                    <?php foreach (LensModel::sphMagnitudeOptions() as $op): ?>
                                        <option value="<?= e($op['value']) ?>"
                                                <?= $sphMag === $op['value'] ? 'selected' : '' ?>>
                                            <?= e($op['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </td>

                        <?php
                        /* Ba ô còn lại giữ nguyên kiểu cũ.
                           CYL và AXIS KHÔNG đổi theo: độ trụ trong đơn thuốc gần
                           như luôn âm và ô số đã mang sẵn dấu, còn trục là số
                           nguyên 0–180 không có dấu nào để mà chọn. Đổi chúng chỉ
                           để "cho đồng bộ" là thêm hai ô chọn dài mà không giải
                           quyết vấn đề nào.

                           step 0.25 — độ kính đi theo bước 0.25 diop.

                           CYL: ±6.00, KHỚP ĐÚNG miền máy chủ nhận.
                           PrescriptionRecordModel kiểm độ trụ trong [-6, +6]
                           (Q63.2). Trước đợt 3 ô này để ±20 vì đường ghi của
                           khách lỏng hơn và nhận mọi con số; nay hai đường ghi
                           đã gộp làm một, nên để ±20 là mời khách gõ một giá
                           trị chắc chắn bị từ chối — và họ mất sạch những gì
                           vừa nhập khi trang tải lại. */
                        $cells = [
                            ['cyl',  'number', ['step' => '0.25', 'min' => '-6', 'max' => '6', 'placeholder' => '0.00']],
                            ['axis', 'number', ['step' => '1', 'min' => '0', 'max' => '180', 'placeholder' => '0']],
                        ];
                        ?>
                        <?php foreach ($cells as [$kind, $type, $attrs]): ?>
                            <?php $field = $eye . '_' . $kind; ?>
                            <td>
                                <label class="sr-only" for="<?= e($field) ?>">
                                    <?= e($label) ?> <?= e(strtoupper($kind)) ?>
                                </label>
                                <input class="acct-rx__input" type="<?= e($type) ?>"
                                       id="<?= e($field) ?>" name="<?= e($field) ?>"
                                       <?php foreach ($attrs as $k => $v): ?>
                                           <?= e($k) ?>="<?= e($v) ?>"
                                       <?php endforeach; ?>
                                       value="<?= e($prescription[$field] ?? '') ?>">
                            </td>
                        <?php endforeach; ?>

                        <?php
                        /*
                         * ─────────────────────────────────────────────────────
                         * THỊ LỰC — HAI Ô SỐ VỚI DẤU "/" CỐ ĐỊNH Ở GIỮA
                         *
                         * Trước đây là MỘT ô chữ và người dùng tự gõ cả "9/10".
                         * Ba thứ hỏng theo cách đó: xoá mất dấu "/" lúc nào
                         * không hay, không có mũi tên tăng giảm, và trần 10 chỉ
                         * là một biểu thức `pattern` — thứ chỉ nói "sai" sau khi
                         * đã gõ xong.
                         *
                         * Nay dấu "/" là CHỮ TRONG MARKUP, không nằm trong ô
                         * nhập nào nên không có cách nào xoá nó. Hai số là
                         * <input type="number" min="0" max="10">: mũi tên lên
                         * xuống chạy sẵn của trình duyệt, bàn phím ↑↓ cũng chạy,
                         * và trần 10 là hàng rào của chính ô chứ không phải một
                         * lời nhắc.
                         *
                         * CSDL KHÔNG ĐỔI: cột vẫn là chuỗi "9/10". Controller
                         * ghép hai ô lại — xem AuthController::updatePrescription.
                         *
                         * GIÁ TRỊ CŨ KHÔNG ĐÚNG DẠNG (hàng nhập từ trước khi có
                         * phép kiểm: "20/20", "ĐNT 3m"…) thì hai ô để TRỐNG và
                         * in nguyên văn giá trị ấy ngay dưới. Nhét bừa vào ô số
                         * thì hoặc trình duyệt tự bỏ (mất dữ liệu không dấu
                         * vết), hoặc hiện một số vượt trần mà người dùng không
                         * biết nó từ đâu ra. Cùng lối đã dùng cho ô "Màu tròng"
                         * ở form sản phẩm.
                         * ─────────────────────────────────────────────────────
                         */
                        $vaCu   = trim((string) ($prescription[$eye . '_va'] ?? ''));
                        $vaSo   = '';
                        $vaThang = '';
                        $vaLa   = $vaCu !== '';

                        if ($vaCu !== '' && preg_match('#^(10|[0-9])/(10|[0-9])$#', $vaCu, $m)) {
                            $vaSo    = $m[1];
                            $vaThang = $m[2];
                            $vaLa    = false;
                        }
                        ?>
                        <td>
                            <span class="acct-rxva">
                                <label class="sr-only" for="<?= e($eye) ?>_va_so">
                                    <?= e($label) ?> thị lực — số đo
                                </label>
                                <input class="acct-rx__input acct-rxva__num" type="number"
                                       id="<?= e($eye) ?>_va_so" name="<?= e($eye) ?>_va_so"
                                       min="0" max="10" step="1" inputmode="numeric"
                                       placeholder="9" value="<?= e($vaSo) ?>">

                                <?php /* aria-hidden: trình đọc màn hình đã nghe hai
                                         nhãn "số đo" và "thang đo", đọc thêm một dấu
                                         gạch chéo ở giữa chỉ thành tiếng ồn. */ ?>
                                <span class="acct-rxva__slash" aria-hidden="true">/</span>

                                <label class="sr-only" for="<?= e($eye) ?>_va_thang">
                                    <?= e($label) ?> thị lực — thang đo
                                </label>
                                <input class="acct-rx__input acct-rxva__num" type="number"
                                       id="<?= e($eye) ?>_va_thang" name="<?= e($eye) ?>_va_thang"
                                       min="0" max="10" step="1" inputmode="numeric"
                                       placeholder="10" value="<?= e($vaThang) ?>">
                            </span>

                            <?php if ($vaLa): ?>
                                <p class="acct-rxva__cu">
                                    Giá trị cũ: <strong><?= e($vaCu) ?></strong> — không đúng dạng
                                    phân số. Nhập lại hai số rồi Lưu để thay nó.
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="acct-form__row">
            <label class="acct-field">
                <span class="acct-field__label">Khoảng cách đồng tử (PD, mm)</span>
                <input class="acct-field__input" type="number" name="pd"
                       step="0.5" min="40" max="80" placeholder="62"
                       value="<?= e($prescription['pd'] ?? '') ?>">
            </label>

            <label class="acct-field">
                <span class="acct-field__label">Ngày đo <span aria-hidden="true">*</span></span>
                <input class="acct-field__input" type="date" name="measured_at"
                       required max="<?= e(date('Y-m-d')) ?>"
                       value="<?= e($prescription['measured_at'] ?? '') ?>">
            </label>
        </div>

        <label class="acct-field">
            <span class="acct-field__label">Cơ sở đo</span>
            <select class="acct-field__input" name="store_id">
                <option value="">— Đo ở nơi khác —</option>
                <?php foreach ($stores as $st): ?>
                    <option value="<?= e($st['id']) ?>"
                            <?= ($prescription['store_id'] ?? '') === $st['id'] ? 'selected' : '' ?>>
                        <?= e($st['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="acct-field">
            <span class="acct-field__label">Khuyến nghị</span>
            <input class="acct-field__input" type="text" name="recommendation" maxlength="255"
                   placeholder="VD: tròng chống ánh sáng xanh"
                   value="<?= e($prescription['recommendation'] ?? '') ?>">
        </label>

        <?php /* Mục "Kính đang đeo" đã gỡ — SRS v2.1.0, A20. Năm ô khách tự
                 khai về cặp kính đang dùng (kiểu tròng, tính chất, loại gọng,
                 dùng bao lâu, ghi chú) không còn. */ ?>

        <div class="acct-form__actions">
            <button type="submit" class="acct-btn acct-btn--primary">Lưu thông số</button>
            <?php if ($prescription !== null): ?>
                <a class="acct-btn acct-btn--outline" href="/tai-khoan?muc=do-mat">Huỷ</a>
            <?php endif; ?>
        </div>
    </form>

<?php else: ?>

    <div class="acct-card acct-rxcard">

        <div class="acct-rxcard__top">
            <span class="acct-rxcard__when">
                <?php
                $when  = $prescription['measured_at'] ?? null;
                $where = $prescription['store_name'] ?? null;

                $parts = [];
                $parts[] = $when !== null ? 'Đo ngày ' . formatDate($when) : 'Chưa ghi ngày đo';
                if ($where !== null) {
                    $parts[] = $where;
                }

                echo e(implode(' · ', $parts));
                ?>
            </span>
            <?php /* Huy hiệu "Còn hiệu lực / Nên đo lại" đã gỡ — SRS v2.1.0, H09.
                     Ngày đo vẫn hiện ngay bên trái, đó là dữ kiện để khách tự
                     đánh giá; hệ thống không kết luận thay họ. */ ?>
        </div>

        <table class="acct-rx">
            <caption class="sr-only">Thông số khúc xạ hai mắt</caption>
            <thead>
                <tr>
                    <th scope="col">Mắt</th>
                    <th scope="col">Cầu (SPH)</th>
                    <th scope="col">Trụ (CYL)</th>
                    <th scope="col">Trục (AXIS)</th>
                    <th scope="col">Thị lực</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ([['od', 'Phải (OD)'], ['os', 'Trái (OS)']] as [$eye, $label]): ?>
                    <tr>
                        <th scope="row"><?= e($label) ?></th>
                        <?php
                        /* Số độ phải giữ dấu + cho viễn thị: "2.25" và "+2.25"
                           là hai đơn thuốc khác nhau hoàn toàn, và cột DECIMAL
                           không mang dấu cộng theo. Dấu gạch ngang cho ô trống —
                           trong đơn thuốc kính, "không đo" khác "bằng không". */
                        $sph = $prescription[$eye . '_sph'] ?? null;
                        $cyl = $prescription[$eye . '_cyl'] ?? null;
                        $fmt = static fn ($v) => $v === null || $v === ''
                            ? '—' : sprintf('%+.2f', (float) $v);
                        ?>
                        <td><?= e($fmt($sph)) ?></td>
                        <td><?= e($fmt($cyl)) ?></td>
                        <td><?= $prescription[$eye . '_axis'] !== null
                                ? e($prescription[$eye . '_axis']) . '°' : '—' ?></td>
                        <td><?= e($prescription[$eye . '_va'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $chips = [];

        if (!empty($prescription['pd'])) {
            $chips[] = 'Khoảng cách đồng tử (PD): ' . rtrim(rtrim((string) $prescription['pd'], '0'), '.') . ' mm';
        }

        if (!empty($prescription['recommendation'])) {
            $chips[] = 'Khuyến nghị: ' . $prescription['recommendation'];
        }
        ?>
        <?php if ($chips !== []): ?>
            <div class="acct-chips">
                <?php foreach ($chips as $chip): ?>
                    <span class="acct-chip"><?= e($chip) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php /* Không còn câu khuyến nghị đo lại theo mốc thời gian — SRS
                 v2.1.0, H09. Ngày đo hiện ở đầu thẻ là đủ. */ ?>
    </div>

<?php endif; ?>
