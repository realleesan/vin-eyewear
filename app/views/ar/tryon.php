<?php

/**
 * ar/tryon.php — thử kính AR
 * Port từ src/components/ar/ar-tryon.tsx.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÁC BẢN LOVABLE: THAY THƯ VIỆN NHẬN DIỆN KHUÔN MẶT
 *
 * Bản gốc dùng `new window.FaceDetector()` — Shape Detection API. API đó
 * CHƯA TỪNG có trên Chrome bản để bàn, Firefox đã từ chối triển khai, Safari
 * không có. Nên biến `cameraSupported` của nó luôn false trên máy tính, nhánh
 * nhận diện thoát ngay từ đầu, và phần TỰ CĂN GỌNG — thứ làm nên chữ "AR" —
 * không bao giờ chạy. Camera bật lên nhưng gọng chỉ nằm im giữa màn hình cho
 * tới khi người dùng tự kéo thanh trượt.
 *
 * Bản này dùng MediaPipe FaceLandmarker (WASM, chạy trong mọi trình duyệt
 * hiện đại). Nó cho 478 điểm mốc thay vì một khung chữ nhật, nên gọng bám
 * theo được cả VỊ TRÍ, BỀ RỘNG lẫn ĐỘ NGHIÊNG của đầu.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$first      = $frames[0] ?? null;
$firstColor = $colors[0];
$firstLens  = $lensEffects[0];

partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Thử kính AR']],
    'head_title'  => 'Thử kính ngay trên khuôn mặt bạn',
    'head_lead'   => 'Bật camera, gọng kính sẽ tự bám theo khuôn mặt. Đổi mẫu, đổi màu '
                   . 'và xem hiệu ứng tròng trước khi quyết định.',
]);
?>

<?php if ($frames === []): ?>
    <section class="ar"><div class="ar__inner">
        <p class="plist__empty"><strong>Hiện chưa có mẫu kính nào để thử.</strong></p>
    </div></section>
<?php else: ?>

<section class="ar"
         id="arRoot"
         data-vision-bundle="<?= e($vision['bundle']) ?>"
         data-vision-wasm="<?= e($vision['wasm']) ?>"
         data-vision-model="<?= e($vision['model']) ?>"
         data-frames="<?= e(json_encode($frames, JSON_UNESCAPED_UNICODE)) ?>"
         data-advice="<?= e(json_encode($faceAdvice, JSON_UNESCAPED_UNICODE)) ?>">

    <div class="ar__inner">
        <div class="ar__layout">

            <!-- ============================================================
                 KHUNG HÌNH
                 ============================================================ -->
            <div class="arstage" id="arStage">

                <!-- Video bị lật ngang (CSS) để người dùng thấy như soi gương.
                     Cả lớp gọng cũng phải lật theo, nếu không nghiêng đầu
                     sang trái thì gọng nghiêng sang phải. -->
                <video class="arstage__video" id="arVideo" playsinline muted></video>

                <img class="arstage__photo" id="arPhoto" alt="" hidden>

                <!-- Lớp gọng: JS đặt vị trí/kích thước/độ nghiêng qua biến CSS -->
                <img class="arstage__frame" id="arFrame"
                     src="<?= e($first['image']) ?>" alt="" hidden>

                <!-- Trạng thái ban đầu -->
                <div class="arstage__idle" id="arIdle">
                    <?= icon('eye', 'arstage__ico', 40) ?>
                    <p class="arstage__title">Bật camera để bắt đầu thử kính</p>
                    <p class="arstage__note">
                        Trình duyệt sẽ hỏi quyền dùng camera. Hình ảnh xử lý ngay trên
                        máy bạn, không gửi đi đâu cả.
                    </p>
                    <button type="button" class="btn-primary btn-inline btn-lg" id="arStart">
                        Bật camera
                    </button>
                    <p class="arstage__alt">
                        Hoặc <label class="arstage__upload" for="arUpload">tải lên một tấm ảnh</label>
                        <input type="file" id="arUpload" accept="image/*" hidden>
                    </p>
                </div>

                <!-- Đang tải -->
                <div class="arstage__loading" id="arLoading" hidden>
                    <span class="arspin" aria-hidden="true"></span>
                    <p id="arLoadingText">Đang bật camera…</p>
                </div>

                <!-- Lỗi -->
                <div class="arstage__error" id="arError" hidden role="alert">
                    <p class="arstage__title" id="arErrorTitle">Không truy cập được camera</p>
                    <p class="arstage__note" id="arErrorText"></p>
                    <button type="button" class="btn-outline btn-inline" id="arRetry">Thử lại</button>
                </div>

                <!-- Dải trạng thái nhận diện -->
                <p class="arstage__status" id="arStatus" hidden aria-live="polite"></p>

                <!-- Thanh công cụ khi đang chạy -->
                <div class="arstage__bar" id="arBar" hidden>
                    <button type="button" class="arbtn" id="arShot">
                        <?= icon('check', '', 16) ?> Chụp ảnh
                    </button>
                    <button type="button" class="arbtn" id="arStop">
                        <?= icon('refresh', '', 16) ?> Tắt camera
                    </button>
                </div>
            </div>

            <!-- ============================================================
                 BẢNG ĐIỀU KHIỂN
                 ============================================================ -->
            <aside class="arpanel">

                <!-- --- Chọn gọng --- -->
                <section class="arblock" aria-labelledby="ar-frames">
                    <h2 id="ar-frames" class="arblock__title">Chọn gọng</h2>

                    <div class="arframes" role="radiogroup" aria-labelledby="ar-frames">
                        <?php foreach ($frames as $i => $f): ?>
                            <input class="arframe__radio" type="radio" name="ar_frame"
                                   id="fr-<?= e($f['id']) ?>" value="<?= e($f['id']) ?>"
                                   <?= $i === 0 ? 'checked' : '' ?>>
                            <label class="arframe" for="fr-<?= e($f['id']) ?>">
                                <img src="<?= e($f['image']) ?>" alt="" width="120" height="60" loading="lazy">
                                <span class="arframe__name notranslate" translate="no"><?= e($f['name']) ?></span>
                                <span class="arframe__meta"><?= e($f['brand']) ?> · <?= e($f['shape']) ?></span>
                                <span class="arframe__price"><?= money($f['price']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- --- Màu gọng --- -->
                <section class="arblock" aria-labelledby="ar-colors">
                    <h2 id="ar-colors" class="arblock__title">Màu gọng</h2>
                    <div class="arswatches" role="radiogroup" aria-labelledby="ar-colors">
                        <?php foreach ($colors as $i => $c): ?>
                            <input class="arswatch__radio" type="radio" name="ar_color"
                                   id="cl-<?= e($c['id']) ?>" value="<?= e($c['filter']) ?>"
                                   <?= $i === 0 ? 'checked' : '' ?>>
                            <label class="arswatch" for="cl-<?= e($c['id']) ?>" title="<?= e($c['label']) ?>">
                                <span class="arswatch__dot" style="background: <?= e($c['swatch']) ?>"></span>
                                <span class="arswatch__label"><?= e($c['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- --- Hiệu ứng tròng --- -->
                <section class="arblock" aria-labelledby="ar-lens">
                    <h2 id="ar-lens" class="arblock__title">Hiệu ứng tròng</h2>
                    <div class="arlens" role="radiogroup" aria-labelledby="ar-lens">
                        <?php foreach ($lensEffects as $i => $l): ?>
                            <input class="arlens__radio" type="radio" name="ar_lens"
                                   id="ln-<?= e($l['id']) ?>" value="<?= e($l['filter']) ?>"
                                   <?= $i === 0 ? 'checked' : '' ?>>
                            <label class="arlens__item" for="ln-<?= e($l['id']) ?>">
                                <strong><?= e($l['label']) ?></strong>
                                <span><?= e($l['desc']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- --- Tinh chỉnh --- -->
                <section class="arblock" aria-labelledby="ar-tune">
                    <h2 id="ar-tune" class="arblock__title">Tinh chỉnh</h2>

                    <div class="artune">
                        <div class="artune__row">
                            <!--
                                KHÔNG dùng <label for="..."> ở đây. Đây là nhãn cho
                                cả NHÓM ba ô chọn S/M/L, mà <label> chỉ gắn được vào
                                đúng MỘT ô. Trước đây nó trỏ tới id "arSize" không hề
                                tồn tại, nên bấm vào chữ "Cỡ gọng" không chọn được gì.
                                Nhãn nhóm đúng cách là một phần tử có id, rồi cho
                                radiogroup trỏ tới bằng aria-labelledby.
                            -->
                            <span class="artune__grouplabel" id="ar-size-label">Cỡ gọng</span>
                            <div class="arsize" role="radiogroup" aria-labelledby="ar-size-label">
                                <?php foreach ($sizes as $s): ?>
                                    <input class="arsize__radio" type="radio" name="ar_size"
                                           id="sz-<?= e($s['id']) ?>" value="<?= e((string) $s['scale']) ?>"
                                           <?= $s['id'] === 'M' ? 'checked' : '' ?>>
                                    <label class="arsize__item" for="sz-<?= e($s['id']) ?>"><?= e($s['id']) ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!--
                            Ba thanh dưới là TINH CHỈNH THÊM trên kết quả tự căn,
                            không phải cách chính để đặt gọng. Bản Lovable bắt
                            người dùng tự kéo từ đầu vì phần tự căn của nó không
                            chạy — ở đây chúng bắt đầu từ 0 (không lệch).
                        -->
                        <div class="artune__row">
                            <label for="arOffsetY">Nhích lên / xuống <span id="arOffsetYVal">0</span></label>
                            <input type="range" id="arOffsetY" min="-40" max="40" step="1" value="0">
                        </div>

                        <div class="artune__row">
                            <label for="arScale">Rộng hơn / hẹp hơn <span id="arScaleVal">100%</span></label>
                            <input type="range" id="arScale" min="70" max="140" step="1" value="100">
                        </div>

                        <div class="artune__row">
                            <label for="arTilt">Xoay thêm <span id="arTiltVal">0°</span></label>
                            <input type="range" id="arTilt" min="-20" max="20" step="1" value="0">
                        </div>

                        <button type="button" class="arreset" id="arReset">Đặt lại tinh chỉnh</button>
                    </div>
                </section>

                <!-- --- Dáng khuôn mặt --- -->
                <section class="arblock arblock--advice" id="arAdvice" hidden aria-live="polite">
                    <h2 class="arblock__title">Dáng khuôn mặt của bạn</h2>
                    <p class="aradvice__shape" id="arShape"></p>
                    <p class="aradvice__text" id="arAdviceText"></p>
                </section>

                <!-- --- Mua --- -->
                <section class="arblock arblock--buy">
                    <p class="arbuy__name notranslate" translate="no" id="arBuyName"><?= e($first['name']) ?></p>
                    <p class="arbuy__price">
                        <span id="arBuyPrice"><?= money($first['price']) ?></span>
                        <?php if ($first['compareAt'] !== null): ?>
                            <s id="arBuyCompare"><?= money($first['compareAt']) ?></s>
                        <?php else: ?>
                            <s id="arBuyCompare" hidden></s>
                        <?php endif; ?>
                    </p>

                    <form action="/gio-hang/them" method="post" class="arbuy__form">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="product_id" id="arBuyId" value="<?= e($first['productId']) ?>">
                        <button type="submit" class="btn-primary btn-inline" id="arBuyBtn"
                                <?= $first['inStock'] ? '' : 'disabled' ?>>
                            <?= $first['inStock'] ? 'Thêm vào giỏ' : 'Tạm hết hàng' ?>
                        </button>
                    </form>

                    <a class="btn-outline btn-inline" id="arBuyLink" href="<?= e($first['url']) ?>">Xem chi tiết</a>
                </section>
            </aside>
        </div>
    </div>
</section>

<?php endif; ?>
