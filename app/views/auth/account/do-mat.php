<?php

/**
 * auth/account/do-mat.php — mục "Thông số đo mắt" (/tai-khoan?muc=do-mat).
 *
 * Bản thiết kế vẽ mục này ở dạng CHỈ ĐỌC: một thẻ gồm dòng "Đo ngày … · Cơ sở
 * …" kèm huy hiệu hiệu lực, bảng 5 cột hai mắt, hai thẻ tròn (PD, khuyến
 * nghị), và câu nhắc đo lại. Đó là trạng thái mặc định ở đây.
 *
 * Form tự nhập nằm ở trạng thái riêng (?sua=1). Nó có từ trước bản thiết kế
 * và là cách duy nhất để khách mang đơn thuốc đo ở nơi khác sang, nên không
 * bỏ — chỉ chuyển ra khỏi màn hình mặc định. Chưa có thông số nào thì
 * controller mở thẳng form, khỏi bắt khách nhìn một thẻ rỗng rồi tự tìm nút.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THẺ THỨ HAI: "KÍNH ĐANG ĐEO"
 *
 * Cửa hàng yêu cầu thêm phần này để có cơ sở tư vấn chính xác hơn — cùng một
 * đơn thuốc −3.00 nhưng người đang đeo đa tròng gọng khoan không viền và người
 * lần đầu cắt kính nhận hai lời khuyên khác hẳn nhau.
 *
 * Nó là một THẺ RIÊNG, không phải mấy dòng thêm vào bảng số đo, vì hai thứ trả
 * lời hai câu khác nhau và cập nhật theo hai nhịp khác nhau: số độ đo lại sau
 * 6–12 tháng, còn cặp kính đang đeo thì đổi khi khách đổi kính. Gộp một thẻ
 * thì sửa loại gọng cũng phải đi qua một form đầy số độ.
 * ─────────────────────────────────────────────────────────────────────────────
 */

/* Đang đeo gì — dùng ở cả hai trạng thái, nên tính một lần ở đây.
   $wearOn là các tính chất ĐÃ CHỌN; $wearFeatures (do controller đưa vào) là
   cả danh sách để dựng ô tick. Hai thứ khác nhau nên tên phải khác nhau. */
$wearType = $prescription['wear_lens_type'] ?? null;
$wearOn   = UserModel::wearFeatureList($prescription['wear_lens_features'] ?? null);
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

                           step 0.25 — độ kính đi theo bước 0.25 diop. */
                        $cells = [
                            ['cyl',  'number', ['step' => '0.25', 'min' => '-20', 'max' => '20', 'placeholder' => '0.00']],
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
                <span class="acct-field__label">Ngày đo</span>
                <input class="acct-field__input" type="date" name="measured_at"
                       max="<?= e(date('Y-m-d')) ?>"
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

        <?php
        /*
         * KÍNH ĐANG ĐEO — nằm TRONG cùng một <form> với số độ, dù ở màn chỉ đọc
         * nó là thẻ riêng.
         *
         * Hai form riêng thì phải có hai nút "Lưu", và khách sửa cả hai phần
         * rồi bấm một nút sẽ mất phần kia mà không có gì báo. Một form một nút
         * là quy ước đang dùng ở mọi mục khác của trang tài khoản.
         */
        ?>
        <hr class="acct-form__rule">

        <h2 class="acct-form__title">Kính đang đeo</h2>
        <p class="acct-form__note acct-form__note--lead">
            Không bắt buộc. Những thông tin này giúp cửa hàng biết bạn đang quen với
            loại kính nào để tư vấn cặp mới sát hơn.
        </p>

        <div class="acct-form__row">
            <label class="acct-field">
                <span class="acct-field__label">Loại tròng đang dùng</span>
                <select class="acct-field__input" name="wear_lens_type">
                    <option value="">— Chưa chọn —</option>
                    <?php /* "Chưa đeo kính" là một câu trả lời THẬT, khác hẳn
                             "chưa chọn": nó nói cho người tư vấn biết đây là
                             lần đầu khách cắt kính. */ ?>
                    <option value="<?= e(UserModel::WEAR_NONE) ?>"
                            <?= $wearType === UserModel::WEAR_NONE ? 'selected' : '' ?>>
                        Chưa đeo kính bao giờ
                    </option>
                    <?php foreach ($lensTypes as $ty): ?>
                        <option value="<?= e($ty['id']) ?>"
                                <?= $wearType === $ty['id'] ? 'selected' : '' ?>>
                            <?= e($ty['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="acct-field">
                <span class="acct-field__label">Loại gọng đang dùng</span>
                <select class="acct-field__input" name="wear_frame_type">
                    <option value="">— Chưa chọn —</option>
                    <?php foreach ($wearFrames as $fr): ?>
                        <option value="<?= e($fr) ?>"
                                <?= ($prescription['wear_frame_type'] ?? '') === $fr ? 'selected' : '' ?>>
                            <?= e($fr) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="acct-field">
            <span class="acct-field__label" id="nhan-tinh-chat">Tính chất tròng đang dùng</span>

            <?php
            /*
             * ─────────────────────────────────────────────────────────────────
             * BẢNG XỔ CHỌN NHIỀU — <details> BỌC CHÍNH CÁC Ô TICK
             *
             * Một cặp tròng thường có vài tính chất cùng lúc (siêu mỏng + chống
             * ánh sáng xanh + chống trầy), nên đây bắt buộc là ô chọn nhiều.
             *
             * VÌ SAO KHÔNG PHẢI <select multiple>, dù cửa hàng nói "dropdown":
             * trên điện thoại nó thu về một danh sách cuộn tí hon mà nhiều
             * người không biết là bấm giữ được nhiều mục, và trên máy tính thì
             * phải giữ Ctrl — một cử chỉ không ai đoán ra. Lý lẽ này có từ bản
             * trước và vẫn đúng.
             *
             * <details> cho đúng thứ cửa hàng muốn — một ô đóng lại, bấm mới xổ
             * — mà bên trong vẫn là ô tick thật: nhìn là biết chọn được nhiều,
             * và KHÔNG CẦN một dòng JavaScript nào để mở/đóng. Cùng thẻ mà cột
             * lọc trang sản phẩm và nhóm "Tài khoản của tôi" ở cột trái đang
             * dùng.
             *
             * DÒNG TÓM TẮT do MÁY CHỦ in ra, đúng ở lúc tải trang. Tắt JS thì
             * tick xong nó chưa đổi cho tới khi Lưu — chấp nhận được vì lúc ấy
             * bảng đang mở và người dùng nhìn thẳng vào các ô tick. Có JS thì
             * account.js cập nhật ngay; xem khối "BẢNG XỔ CHỌN NHIỀU" ở đó.
             * ─────────────────────────────────────────────────────────────────
             */
            $tomTat = $wearOn === [] ? '— Chưa chọn —' : implode(' · ', $wearOn);
            ?>
            <details class="acct-multi" data-multi>
                <summary class="acct-multi__btn">
                    <span class="acct-multi__val" data-multi-val><?= e($tomTat) ?></span>
                    <?php /* Chữ V vẽ tay, cùng hình với mũi tên của các ô chọn
                             khác trên site — xem .catpick__caret. Ký tự ▼ thì
                             mỗi hệ điều hành vẽ một kiểu và không chỉnh được
                             độ dày nét. */ ?>
                    <svg class="acct-multi__caret" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.2"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </summary>

                <div class="acct-multi__panel">
                    <div class="acct-choice" role="group" aria-labelledby="nhan-tinh-chat">
                        <?php foreach ($wearFeatures as $ft): ?>
                            <label class="acct-choice__opt">
                                <input type="checkbox" name="wear_lens_features[]" value="<?= e($ft) ?>"
                                       <?= in_array($ft, $wearOn, true) ? 'checked' : '' ?>>
                                <span><?= e($ft) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        </div>

        <div class="acct-form__row">
            <label class="acct-field">
                <span class="acct-field__label">Đã dùng cặp kính hiện tại bao lâu</span>
                <select class="acct-field__input" name="wear_since">
                    <option value="">— Chưa chọn —</option>
                    <?php foreach ($wearSince as $sn): ?>
                        <option value="<?= e($sn) ?>"
                                <?= ($prescription['wear_since'] ?? '') === $sn ? 'selected' : '' ?>>
                            <?= e($sn) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="acct-field">
                <span class="acct-field__label">Ghi chú thêm</span>
                <input class="acct-field__input" type="text" name="wear_note" maxlength="255"
                       placeholder="VD: hay tuột gọng, đeo máy tính cả ngày"
                       value="<?= e($prescription['wear_note'] ?? '') ?>">
            </label>
        </div>

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
            <span class="acct-badge acct-badge--<?= $rxValid ? 'done' : 'wait' ?>">
                <?= $rxValid ? 'Còn hiệu lực' : 'Nên đo lại' ?>
            </span>
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

        <span class="acct-rxcard__note">Thông số nên được đo lại sau mỗi 6–12 tháng.</span>
    </div>

    <?php
    /*
     * THẺ "KÍNH ĐANG ĐEO" — chỉ hiện khi khách đã điền ít nhất một ô.
     *
     * Chưa điền gì thì hiện một lời mời thay vì một thẻ đầy dấu gạch ngang:
     * bảng số độ ở trên phải in "—" cho ô trống vì trong đơn thuốc "không đo"
     * khác "bằng không", còn ở đây không có gì để phân biệt — trống thì đúng
     * là chưa có thông tin.
     *
     * (Chữ dùng trong mục này CỐ Ý tránh "khai". Đây là thứ khách tự nguyện
     * cho biết để được tư vấn sát hơn, không phải một thủ tục phải làm.)
     */
    $wearRows = array_filter([
        'Loại tròng' => UserModel::wearLensTypeName($wearType),
        'Tính chất'  => $wearOn === [] ? null : implode(' · ', $wearOn),
        'Loại gọng'  => $prescription['wear_frame_type'] ?? null,
        'Đã dùng'    => $prescription['wear_since'] ?? null,
        'Ghi chú'    => $prescription['wear_note'] ?? null,
    ]);
    ?>
    <div class="acct-card acct-wear">
        <div class="acct-rxcard__top">
            <span class="acct-rxcard__when">Kính đang đeo</span>
            <a class="acct-wear__edit" href="/tai-khoan?muc=do-mat&amp;sua=1">
                <?php /* "Điền ngay", không phải "Khai ngay": đây là mục khách
                         tự nguyện cho biết thói quen đeo kính để được tư vấn sát
                         hơn — chữ "khai" đọc như một thủ tục bắt buộc. */ ?>
                <?= $wearRows === [] ? 'Điền ngay' : 'Cập nhật' ?>
            </a>
        </div>

        <?php if ($wearRows === []): ?>
            <span class="acct-rxcard__note">
                Bạn chưa cho biết cặp kính đang đeo. Cửa hàng dùng thông tin này để
                tư vấn loại tròng và gọng sát với thói quen của bạn hơn.
            </span>
        <?php else: ?>
            <dl class="acct-wear__list">
                <?php foreach ($wearRows as $label => $value): ?>
                    <div class="acct-wear__row">
                        <dt><?= e($label) ?></dt>
                        <dd><?= e($value) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
    </div>

<?php endif; ?>
