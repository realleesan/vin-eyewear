<?php

/**
 * _layout/home/face-shape-guide.php
 * Port từ src/components/home/face-shape-guide.tsx.
 *
 * Bản React dùng state để đổi dáng mặt đang chọn. Ở đây dùng radio + CSS
 * (:checked ~) — đổi được bằng bàn phím, không cần JavaScript.
 */

$shapes = config('taxonomy.face_shapes');
?>
<section class="fshape" aria-labelledby="fshape-title">
    <div class="fshape__inner">
        <p class="eyebrow">Chọn gọng theo khuôn mặt</p>
        <h2 id="fshape-title" class="section-h2">Dáng mặt nào cũng có gọng phù hợp</h2>
        <p class="fshape__lead">
            Chọn dáng khuôn mặt của bạn để xem những kiểu gọng cân đối nhất.
        </p>

        <div class="fshape__tabs" role="radiogroup" aria-label="Dáng khuôn mặt">
            <?php foreach ($shapes as $i => $s): ?>
                <input class="fshape__radio" type="radio" name="face_shape"
                       id="fs-<?= e($s['id']) ?>" <?= $i === 0 ? 'checked' : '' ?>>
                <label class="fshape__tab" for="fs-<?= e($s['id']) ?>"><?= e($s['label']) ?></label>
            <?php endforeach; ?>

            <!-- Các panel đặt SAU toàn bộ radio để bộ chọn `~` với tới được.
                 CSS dùng :nth-of-type ghép cặp radio thứ n với panel thứ n. -->
            <?php foreach ($shapes as $s): ?>
                <div class="fshape__panel" data-shape="<?= e($s['id']) ?>">
                    <p class="fshape__hint"><?= e($s['hint']) ?></p>
                    <ul class="fshape__recs" role="list">
                        <?php foreach ($s['recommend'] as $shape): ?>
                            <li>
                                <a href="/san-pham?shape=<?= e(rawurlencode($shape)) ?>">
                                    <?= e($shape) ?>
                                    <?= icon('arrow-right', '', 14) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
