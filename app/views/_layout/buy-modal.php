<?php

/**
 * _layout/buy-modal.php — hộp thoại mua hàng, NĂM BƯỚC.
 *
 *   hinh-thuc   Chọn hình thức mua     chỉ mua gọng · hay gọng + cắt tròng
 *   so-do       Nhập số đo khúc xạ     SPH / CYL / AXIS + ghi chú, hai mắt
 *   kieu-trong  Chọn loại tròng kính   Đơn · Hai · Đa tròng · Mắt đặt
 *   trong       Chọn gói tròng kính    bảng giá chiết suất trong taxonomy.php
 *   xac-nhan    Xác nhận sản phẩm      số lượng + tổng tiền
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA THAY ĐỔI SO VỚI BẢN THIẾT KẾ "Vin Eyewear Product.dc.html" — VÀ VÌ SAO
 *
 * 1. BỎ KHUNG PHÂN LOẠI TẬT KHÚC XẠ.
 *    Bản thiết kế hỏi mỗi mắt "Cận · Viễn · Loạn · Lão" trước khi cho nhập độ.
 *    Cửa hàng yêu cầu bỏ hẳn: khách bình thường không tự xác định đúng được
 *    mình bị tật gì, nên bốn ô đó thu về một con số đoán mò rồi đặt nó cạnh
 *    những con số đo thật. Người có chuyên môn đọc SPH/CYL/AXIS là ra loại
 *    tật — chính xác hơn ô radio nhiều.
 *
 * 2. SỐ ĐO LÊN TRƯỚC, CHỌN TRÒNG XUỐNG CUỐI.
 *    Bản thiết kế cho chọn gói tròng ngay sau bước 1. Nhưng mô tả từng gói nói
 *    theo DẢI ĐỘ ("dưới −4.00", "trên −6.00"), nên hỏi trước là bắt khách chọn
 *    khi chưa có dữ kiện. Lý do đầy đủ ghi ở nhánh 'hinh-thuc' trong
 *    CartController::buyStep().
 *
 * 3. KHÔNG CÒN BƯỚC "DÙNG HỒ SƠ KHÚC XẠ ĐÃ LƯU".
 *    Trước đây khách có hồ sơ thì bấm một nút là xong. Cửa hàng yêu cầu bỏ:
 *    độ mắt thay đổi theo thời gian, nên số của lần đo trước KHÔNG được mặc
 *    định trở thành số của lần mua này. Hồ sơ vẫn còn nguyên trong trang tài
 *    khoản (/tai-khoan?muc=do-mat) để tra cứu; ở đây thì mọi lượt mua đều đi
 *    qua form nhập, và form ấy để trống. Thông tin hành chính (họ tên, số điện
 *    thoại, địa chỉ) thì ngược lại — vẫn tự điền đủ ở trang thanh toán.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Mở khi khách bấm "Mua ngay" / "Thêm vào giỏ" một chiếc GỌNG hoặc KÍNH MÁT —
 * xem CartController::add(). Mỗi bước là một POST sang CartController::buyStep().
 *
 * CSS: assets/css/components/buy-modal.css
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐỘ BO LẤY THEO TRANG CHỦ, KHÔNG THEO BẢN THIẾT KẾ NÀY
 *
 * "Product.dc.html" vẽ hộp thoại bo 12px, thẻ 8–10px, nút 8px. Cả site thì đi
 * theo "Home.dc.html": --radius 6px cho thẻ/hộp, --radius-sm 4px cho nút và ô
 * nhập, --radius-round chỉ cho thứ tròn thật. Một hộp thoại bo tròn hơn hẳn
 * trang đứng sau nó trông như của website khác dán vào.
 *
 * Nên BỐ CỤC và LUỒNG theo bản thiết kế này, còn hình khối theo token. Xem
 * khối chú thích "ĐỘ BO GÓC" trong assets/css/layout.css.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * KHÔNG MỘT DÒNG JAVASCRIPT NÀO
 *
 * Hộp thoại thường là việc của JS. Ở đây không, vì đây là một bước MUA HÀNG:
 * tắt JS mà không mua được gọng kính thì cả danh mục lớn nhất của cửa hàng
 * ngừng bán, trong im lặng.
 *
 * Bước đang mở nằm trên URL (?mua=<id>&buoc=<bước>), còn những gì khách đã
 * chọn nằm trong $_SESSION['_buy_intent'] — KHÔNG nằm trên URL, vì số đo mắt
 * là dữ liệu sức khoẻ và không nên có mặt trong lịch sử duyệt web hay trong
 * Referer gửi sang bên thứ ba.
 *
 * MỌI MẶT HÀNG ĐỀU ĐI QUA ĐỦ LUỒNG NÀY, từ bước 1.
 *
 * Nhánh "chỉ mua <noun>" đi thẳng từ bước 1 sang bước 5 — không có số đo và
 * không có gói tròng để hỏi, nhưng vẫn phải soát lại hình thức, đơn giá và
 * chốt số lượng trước khi món hàng vào giỏ.
 *
 * Khác nhau đúng MỘT chỗ: bước 4 "Chọn loại tròng kính" chỉ có với gọng và
 * kính mát. Tròng rời và kính áp tròng bỏ qua nó — bản thân chúng đã là tròng,
 * cộng thêm một gói tròng nữa là bán hai cặp tròng cho một đơn và tính tiền cả
 * hai. Nhánh "theo số đo" của chúng đi thẳng từ số đo sang xác nhận.
 *
 * Chữ trên hai lựa chọn ở bước 1 cũng đổi theo loại hàng — xem
 * LensModel::wording(). "Chỉ mua gọng" cho một hộp kính áp tròng là câu vô nghĩa.
 *
 * Nhận qua partial(): $buyModal — mảng do BaseController::renderView dựng.
 *   product · step · takesPackage · intent
 */

$product  = $buyModal['product'];
$intent   = $buyModal['intent'];
$step     = $buyModal['step'];
$takesPkg = $buyModal['takesPackage'];

$wording = LensModel::wording($product);
$noun    = $wording['noun'];

/* Giá GỐC, chưa cộng tròng. Có biến thể đang chọn thì cộng chênh lệch của nó
   vào — khách đã chọn "chiết suất 1.67" ở trang chi tiết thì con số trong hộp
   thoại phải là con số họ sẽ trả. */
$variant = $intent['variant_id'] !== null
    ? VariantModel::findForProduct($intent['variant_id'], $product['id'])
    : null;

$basePrice = VariantModel::priceOf($product, $variant);

/* Kiểu tròng và gói chiết suất gộp thành MỘT mẩu để in — xem LensModel::combo.
   $lensType giữ riêng vì bước lùi cần biết kiểu đang chọn có bảng giá không. */
$lensType    = LensModel::findType($intent['lens_type']);
$typeTakesPkg = LensModel::typeTakesPackage($lensType);
$lens        = LensModel::combo($intent['lens_id'], $intent['lens_type']);
$lensPrice   = (int) ($lens['price'] ?? 0);
$qty         = (int) $intent['quantity'];

$titles = [
    'hinh-thuc'  => 'Chọn hình thức mua',
    'so-do'      => 'Nhập số đo khúc xạ',
    'kieu-trong' => 'Chọn loại tròng kính',
    'trong'      => 'Chọn gói tròng kính',
    'xac-nhan'   => 'Xác nhận sản phẩm',
];

/*
 * Bước lùi của từng bước — PHẢI ĐI NGƯỢC ĐÚNG THỨ TỰ CỦA CartController::buyStep.
 *
 *   chỉ mua gọng:  hình thức ─────────────────────────────────────► xác nhận
 *   cắt tròng:     hình thức → số đo → kiểu tròng → gói ──────────► xác nhận
 *                                       └ "Mắt đặt" ──────────────┘
 *   đã là tròng:   hình thức → số đo ──────────────────────────────► xác nhận
 *
 * Ba nhánh cùng đổ về "xác nhận", nên đường lùi của bước ấy phải hỏi lại
 * đúng ba câu đã rẽ: có cắt tròng không, mặt hàng có bảng giá tròng không,
 * và kiểu tròng đang chọn có đi tiếp sang bảng giá không.
 */
$rxPrev = !$takesPkg
    ? 'so-do'                              // tròng rời, kính áp tròng
    : ($typeTakesPkg ? 'trong' : 'kieu-trong');

$prev = [
    'so-do'      => 'hinh-thuc',
    'kieu-trong' => 'so-do',
    'trong'      => 'kieu-trong',
    'xac-nhan'   => $intent['mode'] === 'combo' ? $rxPrev : 'hinh-thuc',
][$step] ?? null;

/* Đóng hộp thoại = về chính trang này, bỏ hai tham số của nó. Không dùng
   $intent['back']: khách có thể đã đi lang thang rồi mới quay lại đường dẫn có
   ?mua=, và ném họ về trang cũ lúc đó là đưa đi đâu đó khác. */
$closeHref = currentUrlWithout(['mua', 'buoc']);

/** Địa chỉ của một bước bất kỳ, giữ nguyên mọi tham số khác của trang. */
$stepHref = static function (?string $to): string {
    $base = currentUrlWithout(['buoc']);

    return $to === null ? $base : $base . (str_contains($base, '?') ? '&' : '?') . 'buoc=' . $to;
};

/** Ô ẩn của form đi tới bước kế — chỉ cần token và tên bước đang gửi. */
$stepForm = static function (string $buoc): void {
    printf('<input type="hidden" name="_token" value="%s">', e(csrfToken()));
    printf('<input type="hidden" name="buoc" value="%s">', e($buoc));
};
?>

<div class="bmodal">
    <?php /* Nền mờ là một LIÊN KẾT phủ kín màn hình, không phải một <div> nghe
             sự kiện click. Bấm ra ngoài để đóng, không cần script. */ ?>
    <a class="bmodal__scrim" href="<?= e($closeHref) ?>" aria-label="Đóng"></a>

    <div class="bmodal__panel" role="dialog" aria-modal="true" aria-labelledby="bmodal-title">

        <div class="bmodal__head">
            <?php if ($prev !== null): ?>
                <a class="bmodal__back" href="<?= e($stepHref($prev === 'hinh-thuc' ? null : $prev)) ?>"
                   aria-label="Quay lại">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 18l-6-6 6-6"></path>
                    </svg>
                </a>
            <?php endif; ?>

            <h2 class="bmodal__title" id="bmodal-title"><?= e($titles[$step]) ?></h2>

            <a class="bmodal__close" href="<?= e($closeHref) ?>" aria-label="Đóng">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18"></path>
                </svg>
            </a>
        </div>

        <!-- ══════════ THẺ SẢN PHẨM — có ở MỌI bước ══════════ -->
        <div class="bmodal__item">
            <span class="bmodal__thumb">
                <?php if (ProductModel::hasImage($product)): ?>
                    <img src="<?= e(ProductModel::image($product)) ?>" alt=""
                         width="64" height="64" loading="lazy" decoding="async">
                <?php else: ?>
                    <?= icon('glasses', '', 26) ?>
                <?php endif; ?>
            </span>

            <div class="bmodal__itembody">
                <span class="bmodal__name"><?= e($product['name']) ?></span>
                <span class="bmodal__meta">
                    <?= e($product['brand'] ?? 'Vin Eyewear') ?>
                    <?php if (!empty($product['sku'])): ?>
                        · <?= e($product['sku']) ?>
                    <?php endif; ?>
                    <?php if ($variant !== null): ?>
                        · <?= e($variant['label']) ?>
                    <?php endif; ?>
                </span>
                <span class="bmodal__price"><?= money($basePrice) ?></span>
            </div>
        </div>

        <?php if ($step === 'hinh-thuc'): ?>

            <!-- ══════════ 1. CHỌN HÌNH THỨC MUA ══════════ -->
            <div class="bmodal__opts">
                <?php /* "CHỈ MUA <?= $noun ?>" CŨNG ĐI QUA BƯỚC XÁC NHẬN.
                         Trước đây nút này gửi thẳng sang /gio-hang/them và kết
                         thúc luôn tại đây, với lý do "khách vừa chọn xong hình
                         thức thì không còn câu hỏi nào chưa trả lời". Nhưng có
                         một câu chưa hỏi: SỐ LƯỢNG — thẻ sản phẩm không có bộ
                         đếm nào, nên mua trần từ thẻ luôn là đúng một chiếc và
                         không có chỗ nào đổi.

                         Nay nó gửi sang /gio-hang/chon như nhánh cắt tròng.
                         Không cần ô ẩn nào ngoài token và tên bước: số lượng,
                         phương án và việc khách bấm "Mua ngay" hay "Thêm vào
                         giỏ" đều đã nằm trong $_SESSION['_buy_intent'] từ lúc
                         mở hộp thoại. buyStep() đã sẵn định tuyến che_do khác
                         'combo' về thẳng bước "Xác nhận sản phẩm". */ ?>
                <form method="post" action="/gio-hang/chon">
                    <?php $stepForm('hinh-thuc'); ?>

                    <button type="submit" class="bopt" name="che_do" value="gong">
                        <span class="bopt__ico" aria-hidden="true">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round">
                                <circle cx="6.5" cy="12" r="4"></circle>
                                <circle cx="17.5" cy="12" r="4"></circle>
                                <path d="M10.5 12h3"></path>
                            </svg>
                        </span>
                        <span class="bopt__body">
                            <span class="bopt__name">Chỉ mua <?= e($noun) ?></span>
                            <span class="bopt__note"><?= e($wording['plainNote']) ?></span>
                        </span>
                    </button>
                </form>

                <?php /* Nhánh cắt tròng còn ba câu hỏi nữa: số đo, gói tròng,
                         rồi mới tới số lượng ở bước xác nhận. */ ?>
                <form method="post" action="/gio-hang/chon">
                    <?php $stepForm('hinh-thuc'); ?>

                <button type="submit" class="bopt" name="che_do" value="combo">
                    <span class="bopt__ico bopt__ico--brand" aria-hidden="true">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M8.5 12.5l2.5 2.5 4.5-5"></path>
                        </svg>
                    </span>
                    <span class="bopt__body">
                        <?php /* Nhãn đổi theo loại hàng: "Mua gọng + cắt tròng" cho
                                 gọng, nhưng "Mua theo độ của tôi" cho kính áp tròng
                                 — xem LensModel::wording(). */ ?>
                        <span class="bopt__name"><?= e($wording['rxName']) ?></span>
                        <span class="bopt__note"><?= e($wording['rxNote']) ?></span>
                    </span>
                </button>
                </form>
            </div>

        <?php elseif ($step === 'so-do'): ?>

            <!-- ══════════ 2. NHẬP SỐ ĐO KHÚC XẠ ══════════ -->
            <?php
            /*
             * BẢNG BA CỘT × HAI HÀNG — dựng theo "Bảng số đo khúc xạ.dc.html".
             *
             * Trước đây mỗi mắt là một khối riêng xếp chồng, mỗi khối lại có
             * nhãn cột của chính nó. Nay hai mắt nằm trong MỘT bảng dùng chung
             * một hàng nhãn: đó là cách đơn kính giấy in ra, và là cách khách
             * đối chiếu — nhìn dọc một cột để so số hai mắt, không phải nhớ số
             * mắt phải rồi cuộn xuống tìm mắt trái.
             *
             * ─────────────────────────────────────────────────────────────
             * ĐỘ CẦU LÀ MỘT Ô CHỌN MANG SẴN DẤU, không phải cặp nút −/+ như ở
             * trang hồ sơ (mục "Thông số đo mắt").
             *
             * Bản vẽ chốt như vậy, và trong một cái bảng thì nó hợp lý: bốn
             * cột của hai mắt phải thẳng hàng nhau, mà nhét thêm hai nút dấu
             * vào cột đầu là cột đó rộng gấp rưỡi ba cột còn lại.
             *
             * Cái giá là dấu nằm lẫn trong 97 dòng danh sách. Bù bằng hai thứ:
             * nhãn cột ghi thẳng "Cận (−) / Viễn (+)", và ô TÓM TẮT bên dưới
             * đọc ngược con số ra thành chữ ("Cận 2.00 · Loạn 0.75 · Trục
             * 180°") để khách soát lại trước khi bấm xác nhận.
             * ─────────────────────────────────────────────────────────────
             *
             * TRỤC BỊ KHOÁ KHI CHƯA CÓ ĐỘ TRỤ. Trục loạn chỉ có nghĩa khi có
             * độ loạn; chọn "Trục 90°" cho một mắt không loạn là một con số vô
             * nghĩa đi thẳng xuống phiếu mài. Khoá bằng thuộc tính `disabled`
             * dựng sẵn ở máy chủ, JS chỉ mở/đóng lại khi khách đổi ô độ trụ —
             * không có JS thì ô trục mở sẵn và máy chủ vẫn kiểm như cũ.
             */
            $eyes = [
                'od' => ['OD', 'Mắt phải'],
                'os' => ['OS', 'Mắt trái'],
            ];
            $sphOptions  = LensModel::sphSignedOptions();
            $cylOptions  = LensModel::cylOptionsWithZero();
            /* Bỏ mốc 0° đi. Về mặt hình học 0° và 180° là cùng một kinh
               tuyến, nhưng đơn kính luôn ghi 180 — để cả hai trong danh sách
               là bày ra hai dòng cho cùng một thứ và mời khách phân vân.
               Nhãn cột cũng ghi "1–180°" cho khớp. Máy chủ vẫn nhận 0 nếu có
               ai gửi tay: LensModel::AXIS_MIN không đổi. */
            $axisOptions = array_values(array_filter(
                LensModel::axisOptions(),
                static fn (array $op): bool => $op['value'] !== '0'
            ));
            ?>
            <form class="brx" method="post" action="/gio-hang/chon">
                <?php $stepForm('so-do'); ?>

                <div class="brx__head">
                    <span class="brx__eyebrow">Số đo kính thuốc</span>
                    <span class="brx__hint">Nhập theo đơn kính của bạn</span>
                </div>

                <div class="brxtable">

                    <?php /* Hàng nhãn — mỗi cột một tên viết tắt kèm một dòng
                             giải nghĩa, vì SPH/CYL/AXIS là chữ của người trong
                             nghề chứ không phải của khách. */ ?>
                    <div class="brxtable__head">
                        <span></span>
                        <span class="brxtable__col">
                            <span class="brxtable__name">Độ cầu <em>SPH</em></span>
                            <span class="brxtable__sub">Cận (−) / Viễn (+)</span>
                        </span>
                        <span class="brxtable__col">
                            <span class="brxtable__name">Độ trụ <em>CYL</em></span>
                            <span class="brxtable__sub">Độ loạn</span>
                        </span>
                        <span class="brxtable__col">
                            <span class="brxtable__name">Trục <em>AXIS</em></span>
                            <span class="brxtable__sub">1–180°</span>
                        </span>
                    </div>

                    <?php foreach ($eyes as $side => [$code, $label]): ?>
                        <div class="brxtable__row">
                            <span class="brxtable__eye">
                                <span class="brxtable__badge"><?= e($code) ?></span>
                                <span class="brxtable__eyename"><?= e($label) ?></span>
                            </span>

                            <?php
                            /* Ba ô chọn của một mắt. Khai bằng mảng để hàng OD
                               và OS không thể lệch nhau — sửa một ô là cả hai
                               mắt theo. */
                            $cells = [
                                ['sph',  $side,             $sphOptions,  'độ cầu', false],
                                ['cyl',  $side . '_cyl',    $cylOptions,  'độ trụ', false],
                                ['axis', $side . '_axis',   $axisOptions, 'trục',   true],
                            ];
                            ?>
                            <?php foreach ($cells as [$kind, $name, $options, $what, $locked]): ?>
                                <span class="brxtable__cell">
                                    <label class="sr-only" for="<?= e($side . '-' . $kind) ?>">
                                        <?= e($label) ?> — <?= e($what) ?>
                                    </label>
                                    <select class="brxsel" id="<?= e($side . '-' . $kind) ?>"
                                            name="<?= e($name) ?>"
                                            <?php if ($kind === 'cyl'): ?>data-cyl="<?= e($side) ?>"<?php endif; ?>
                                            <?php if ($locked): ?>
                                                data-axis="<?= e($side) ?>" disabled
                                            <?php endif; ?>>
                                        <option value="">— Chọn —</option>
                                        <?php foreach ($options as $op): ?>
                                            <option value="<?= e($op['value']) ?>"><?= e($op['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php
                /* GHI CHÚ GẤP LẠI. Đại đa số khách không có gì để ghi, và hai ô
                   chữ mở sẵn đẩy nút xác nhận xuống dưới mép màn hình điện
                   thoại. Không có JS thì <details> vẫn bấm mở được — đó là hành
                   vi sẵn có của trình duyệt, không phải thứ ta tự dựng. */
                ?>
                <details class="brxnote">
                    <summary class="brxnote__toggle">Thêm ghi chú (không bắt buộc)</summary>

                    <div class="brxnote__grid">
                        <?php foreach ($eyes as $side => [$code, $label]): ?>
                            <label class="brxnote__field">
                                <span class="brxnote__cap">Ghi chú <?= e(utf8Lower($label)) ?></span>
                                <textarea name="<?= e($side) ?>_note" rows="2"
                                          maxlength="<?= LensModel::NOTE_MAX ?>"
                                          placeholder="Ví dụ: hay mỏi khi đọc lâu"></textarea>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </details>

                <?php
                /* Ô TÓM TẮT — đọc ngược con số vừa chọn ra thành chữ.
                   Đây là thứ bù lại cho việc dấu nằm lẫn trong danh sách dài:
                   "−2.00" đọc lướt có thể nhầm, còn "Cận 2.00" thì không.

                   JS điền vào (buy-modal.js). Không có JS thì khối này ở
                   nguyên trạng thái "Chưa nhập" — vô hại, vì bảng ngay trên nó
                   đã hiện đủ thứ khách vừa chọn. */
                ?>
                <div class="brxsum">
                    <div class="brxsum__row">
                        <span class="brxsum__key">OD · Mắt phải</span>
                        <span class="brxsum__val" data-sum="od">Chưa nhập</span>
                    </div>
                    <div class="brxsum__row">
                        <span class="brxsum__key">OS · Mắt trái</span>
                        <span class="brxsum__val" data-sum="os">Chưa nhập</span>
                    </div>
                </div>

                <?php /* Bỏ trống cả hai mắt vẫn đi tiếp được: phần lớn người mua
                         kính không nhớ số đo của mình, và cửa hàng đo lại miễn
                         phí trước khi mài — xem CartController::add(). */ ?>
                <button type="submit" class="bmodal__cta">Xác nhận độ kính</button>
            </form>

            <a class="bmodal__ghost" href="/dat-lich">Không nhớ số đo? Đặt lịch đo mắt miễn phí</a>

        <?php elseif ($step === 'kieu-trong'): ?>

            <!-- ══════════ 3. CHỌN LOẠI TRÒNG KÍNH ══════════ -->
            <?php if ($intent['rx'] !== null): ?>
                <?php /* Nhắc lại số đo vừa nhập ngay trên danh sách: "Mắt đặt"
                         dành cho độ quá cao, nên hai thứ phải nhìn thấy cùng
                         lúc mới quyết được. */ ?>
                <p class="brxecho"><?= e($intent['rx']) ?></p>
            <?php endif; ?>

            <form class="blens" method="post" action="/gio-hang/chon">
                <?php $stepForm('kieu-trong'); ?>

                <?php foreach (LensModel::types() as $ty): ?>
                    <button type="submit" class="blens__item<?= $intent['lens_type'] === $ty['id'] ? ' is-on' : '' ?>"
                            name="kieu" value="<?= e($ty['id']) ?>">
                        <span class="blens__body">
                            <span class="blens__name"><?= e($ty['name']) ?></span>
                            <span class="blens__desc"><?= e($ty['desc']) ?></span>
                        </span>
                        <?php if (empty($ty['takes_package'])): ?>
                            <?php /* Không có bảng giá nào để chọn tiếp — nói ra ở
                                     đây thay vì để khách bấm vào rồi mới thấy bước
                                     kế biến mất. */ ?>
                            <span class="blens__price blens__price--soft">Báo giá sau</span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </form>

        <?php elseif ($step === 'trong'): ?>

            <!-- ══════════ 4. CHỌN GÓI TRÒNG KÍNH ══════════ -->
            <?php /* Nhắc lại KIỂU TRÒNG vừa chọn và SỐ ĐO vừa nhập, ngay trên
                     bảng giá: mô tả của từng gói nói theo DẢI ĐỘ ("dưới −4.00",
                     "trên −6.00"), nên cả ba thứ phải nhìn thấy cùng lúc mới
                     so được. */ ?>
            <?php if ($lensType !== null || $intent['rx'] !== null): ?>
                <p class="brxecho">
                    <?= e(implode(' · ', array_filter([
                        $lensType['name'] ?? null,
                        $intent['rx'],
                    ]))) ?>
                </p>
            <?php endif; ?>

            <form class="blens" method="post" action="/gio-hang/chon">
                <?php $stepForm('trong'); ?>

                <?php foreach (LensModel::packages() as $pk): ?>
                    <?php
                    /* GIÁ CỦA CHÍNH KIỂU TRÒNG KHÁCH VỪA CHỌN, không phải một
                       giá chung của gói: bước này đứng SAU bước chọn kiểu, nên
                       ở đây đã biết đủ hai vế để tra đúng ô trong bảng giá.

                       null = cửa hàng chưa định giá ô đó. Vẫn cho chọn — cắt
                       được tròng đó thì vẫn bán được — nhưng nói rõ là chưa có
                       giá, thay vì in "+0₫" thành ra hứa miễn phí. */
                    $pkPrice = LensModel::priceOf($intent['lens_type'], $pk['id']);
                    ?>
                    <button type="submit" class="blens__item<?= $intent['lens_id'] === $pk['id'] ? ' is-on' : '' ?>"
                            name="lens" value="<?= e($pk['id']) ?>">
                        <span class="blens__body">
                            <span class="blens__name"><?= e($pk['name']) ?></span>
                            <span class="blens__desc"><?= e($pk['desc']) ?></span>
                        </span>
                        <?php if ($pkPrice === null): ?>
                            <span class="blens__price blens__price--soft">Báo giá sau</span>
                        <?php else: ?>
                            <span class="blens__price">+<?= money($pkPrice) ?></span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </form>

        <?php else: ?>

            <!-- ══════════ 5. XÁC NHẬN SẢN PHẨM ══════════ -->
            <div class="bsum">
                <div class="bsum__row">
                    <span>Hình thức:</span>
                    <span class="bsum__val">
                        <?= $intent['mode'] === 'combo'
                            ? e($wording['rxName'])
                            : 'Chỉ ' . e($noun) ?>
                    </span>
                </div>

                <div class="bsum__row">
                    <span>Đơn giá:</span>
                    <span class="bsum__val bsum__val--price"><?= money($basePrice) ?></span>
                </div>

                <?php if ($lens !== null): ?>
                    <div class="bsum__row">
                        <span>Tròng kính · <?= e($lens['name']) ?>:</span>
                        <?php if (!empty($lens['quoted'])): ?>
                            <?php /* "Mắt đặt" — tròng đặt riêng theo đơn, chưa có
                                     giá. Ghi "+0₫" ở đây thì khách đọc ra thành
                                     "phần tròng miễn phí", và con số cuối cùng
                                     cửa hàng báo sẽ thành một bất ngờ. */ ?>
                            <span class="bsum__val bsum__val--soft">Báo giá sau khi tư vấn</span>
                        <?php else: ?>
                            <span class="bsum__val bsum__val--price">+<?= money($lensPrice) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($intent['mode'] === 'combo'): ?>
                    <?php /* Số đo hiện cho MỌI mặt hàng đi nhánh này, kể cả món
                             không có gói tròng — nó vẫn là thứ quyết định hàng
                             giao ra, và là thứ khách phải soát lại. */ ?>
                    <?php if ($intent['rx'] !== null): ?>
                        <div class="bsum__row">
                            <span>Số đo:</span>
                            <span class="bsum__val"><?= e($intent['rx']) ?></span>
                        </div>
                    <?php else: ?>
                        <?php /* Không có số đo vẫn đặt được — cửa hàng đo lại
                                 miễn phí trước khi mài. Nói rõ điều đó ở đây,
                                 chứ để trống thì khách tưởng mình điền sót. */ ?>
                        <div class="bsum__row">
                            <span>Số đo:</span>
                            <span class="bsum__val bsum__val--soft">Đo tại cửa hàng khi nhận</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php /* Bộ đếm số lượng: hai nút GỬI FORM, không phải hai nút JS.
                         Mỗi lần bấm là một vòng máy chủ — chậm hơn một chút, đổi
                         lại là con số trên màn hình luôn là con số máy chủ đang
                         giữ, không bao giờ lệch. */ ?>
                <form class="bqty" method="post" action="/gio-hang/chon">
                    <?php $stepForm('so-luong'); ?>

                    <span class="bqty__label">Số lượng</span>

                    <span class="bqty__stepper">
                        <button type="submit" name="act" value="giam" class="bqty__btn"
                                <?= $qty <= 1 ? 'disabled' : '' ?> aria-label="Giảm số lượng">−</button>
                        <span class="bqty__num"><?= $qty ?></span>
                        <button type="submit" name="act" value="tang" class="bqty__btn"
                                aria-label="Tăng số lượng">+</button>
                    </span>
                </form>

                <div class="bsum__total">
                    <span>Tổng cộng:</span>
                    <span class="bsum__totalnum"><?= money(($basePrice + $lensPrice) * $qty) ?></span>
                </div>

                <?php /* Bản thiết kế để nút cuối luôn là "Mua ngay" vì cả hai nút
                         trên trang sản phẩm đều mở cùng hộp thoại này. Ở đây nhãn
                         đi theo nút khách đã bấm: bấm "Thêm vào giỏ" rồi kết thúc
                         bằng "Mua ngay" là hứa một việc khác với việc sẽ xảy ra. */ ?>
                <form method="post" action="/gio-hang/them">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                    <input type="hidden" name="mode" value="<?= $intent['mode'] === 'combo' ? 'trong' : 'gong' ?>">
                    <input type="hidden" name="quantity" value="<?= $qty ?>">
                    <input type="hidden" name="back" value="<?= e($intent['back']) ?>">
                    <?php if ($intent['variant_id'] !== null): ?>
                        <input type="hidden" name="variant_id" value="<?= e($intent['variant_id']) ?>">
                    <?php endif; ?>
                    <?php /* Kiểu tròng KHÔNG gửi qua ô ẩn — add() đọc thẳng từ
                             $_SESSION['_buy_intent']. Một ô ẩn ở đây là một chỗ
                             sửa tay được, mà kiểu tròng quyết định cả tên dòng
                             hàng lẫn việc có phải chọn gói chiết suất hay không. */ ?>
                    <?php if (($lens['id'] ?? null) !== null): ?>
                        <input type="hidden" name="lens" value="<?= e($lens['id']) ?>">
                    <?php endif; ?>
                    <?php if ($intent['action'] === 'buy'): ?>
                        <input type="hidden" name="action" value="buy">
                    <?php endif; ?>

                    <button type="submit" class="bmodal__cta">
                        <?= $intent['action'] === 'buy' ? 'Mua ngay' : 'Thêm vào giỏ hàng' ?>
                    </button>
                </form>
            </div>

        <?php endif; ?>
    </div>
</div>
