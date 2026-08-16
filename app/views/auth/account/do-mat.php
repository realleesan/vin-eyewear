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
                        /* step 0.25 — độ kính đi theo bước 0.25 diop.
                           axis 0..180 — trục loạn thị tính bằng độ.
                           Thị lực là phân số ("10/10") nên phải là ô chữ. */
                        $cells = [
                            ['sph',  'number', ['step' => '0.25', 'min' => '-20', 'max' => '20', 'placeholder' => '0.00']],
                            ['cyl',  'number', ['step' => '0.25', 'min' => '-20', 'max' => '20', 'placeholder' => '0.00']],
                            ['axis', 'number', ['step' => '1', 'min' => '0', 'max' => '180', 'placeholder' => '0']],
                            ['va',   'text',   ['maxlength' => '16', 'placeholder' => '10/10']],
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

<?php endif; ?>
