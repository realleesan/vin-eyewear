<?php

/**
 * _layout/buy-modal.php — hộp thoại mua hàng, NĂM BƯỚC.
 *
 * Dựng theo khối "LUỒNG MUA HÀNG" của "Vin Eyewear Product.dc.html":
 *
 *   hinh-thuc  Chọn hình thức mua     chỉ mua gọng · hay gọng + cắt tròng
 *   khuc-xa    Số đo khúc xạ          dùng hồ sơ đã lưu · hay nhập mới
 *   so-do      Nhập số đo khúc xạ     loại tật + độ hai mắt
 *   trong      Chọn loại tròng kính   năm gói trong config/taxonomy.php
 *   xac-nhan   Xác nhận sản phẩm      số lượng + tổng tiền
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
 *   product · step · saved (hồ sơ khúc xạ đã lưu) · takesPackage · intent
 */

$product  = $buyModal['product'];
$intent   = $buyModal['intent'];
$saved    = $buyModal['saved'];
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
$lens      = LensModel::find($intent['lens_id']);
$lensPrice = (int) ($lens['price'] ?? 0);
$qty       = (int) $intent['quantity'];

$titles = [
    'hinh-thuc' => 'Chọn hình thức mua',
    'khuc-xa'   => 'Số đo khúc xạ',
    'so-do'     => 'Nhập số đo khúc xạ',
    'trong'     => 'Chọn loại tròng kính',
    'xac-nhan'  => 'Xác nhận sản phẩm',
];

/* Bước lùi của từng bước. Bản thiết kế cho "xác nhận" lùi về "chọn tròng" khi
   mua kèm tròng, và về "hình thức" khi mua trần — cùng một nút, hai đích, vì
   hai đường đi khác nhau dẫn tới nó. */
$rxPrev = $saved !== null ? 'khuc-xa' : 'so-do';

$prev = [
    'khuc-xa'  => 'hinh-thuc',
    'so-do'    => 'khuc-xa',
    'trong'    => $rxPrev,
    /* HAI ĐÍCH, một nút — vì hai đường đi khác nhau cùng dẫn tới bước này.
       Mua kèm tròng thì lùi về bước ngay trước trên nhánh đó (chọn tròng, hoặc
       số đo với mặt hàng không có gói tròng). Mua trần thì cả nhánh chỉ có một
       bước, nên lùi thẳng về "Chọn hình thức mua".

       Trước đây dòng này chỉ có nhánh theo số đo, vì nhánh mua trần kết thúc
       ngay ở bước 1 và không bao giờ tới đây. Nay nó có tới. */
    'xac-nhan' => $intent['mode'] === 'combo' ? ($takesPkg ? 'trong' : $rxPrev) : 'hinh-thuc',
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

        <?php elseif ($step === 'khuc-xa'): ?>

            <!-- ══════════ 2. SỐ ĐO KHÚC XẠ ══════════ -->
            <?php if ($saved !== null && LensModel::formatSavedRx($saved) !== null): ?>
                <?php /* Bản thiết kế chỉ vẽ trạng thái "chưa có hồ sơ" — một nút
                         duy nhất. Nhưng dự án CÓ bảng `prescriptions` và một mục
                         "Thông số đo mắt" trong trang tài khoản, nên khách đã đo ở
                         Vin Eyewear là đã có sẵn số đo. Bắt họ gõ lại từng con số
                         mình vừa được đo là hỏi một câu đã có câu trả lời. */ ?>
                <form class="brxsaved" method="post" action="/gio-hang/chon">
                    <?php $stepForm('khuc-xa'); ?>

                    <div class="brxsaved__card">
                        <span class="brxsaved__label">Hồ sơ khúc xạ của bạn</span>
                        <span class="brxsaved__val"><?= e(LensModel::formatSavedRx($saved)) ?></span>
                        <span class="brxsaved__meta">
                            <?php if (!empty($saved['measured_at'])): ?>
                                Đo ngày <?= e(date('d/m/Y', strtotime($saved['measured_at']))) ?>
                                <?php if (!empty($saved['store_name'])): ?>
                                    · <?= e($saved['store_name']) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                Bạn tự nhập trong trang tài khoản
                            <?php endif; ?>
                            <?php if (!UserModel::prescriptionIsValid($saved)): ?>
                                <?php /* Quá 12 tháng thì độ có thể đã đổi. Không
                                         chặn — chỉ nói ra, vì chỉ khách mới biết
                                         mắt mình có thay đổi hay không. */ ?>
                                · <strong class="brxsaved__old">nên đo lại</strong>
                            <?php endif; ?>
                        </span>
                    </div>

                    <button type="submit" class="bmodal__cta">Dùng số đo này</button>
                </form>

                <a class="bmodal__ghost" href="<?= e($stepHref('so-do')) ?>">Nhập số đo khác</a>

            <?php else: ?>
                <div class="bnote">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M12 8h.01M12 11v5"></path>
                    </svg>
                    <span class="bnote__body">
                        <span class="bnote__title">Bạn chưa có hồ sơ khúc xạ</span>
                        <span class="bnote__text">
                            Nhập số đo từ đơn thuốc khúc xạ gần nhất, hoặc đặt lịch đo mắt
                            miễn phí tại cửa hàng.
                        </span>
                    </span>
                </div>

                <a class="bmodal__cta bmodal__cta--link" href="<?= e($stepHref('so-do')) ?>">
                    Nhập số đo khúc xạ
                </a>
                <a class="bmodal__ghost" href="/dat-lich">Không nhớ số đo? Đặt lịch đo mắt miễn phí</a>
            <?php endif; ?>

        <?php elseif ($step === 'so-do'): ?>

            <!-- ══════════ 3. NHẬP SỐ ĐO KHÚC XẠ ══════════ -->
            <form class="brx" method="post" action="/gio-hang/chon">
                <?php $stepForm('so-do'); ?>

                <fieldset class="brx__group">
                    <legend class="brx__legend">Loại tật khúc xạ</legend>

                    <div class="brx__types">
                        <?php foreach (LensModel::RX_TYPES as $key => [$name, $desc]): ?>
                            <label class="btype">
                                <input type="radio" name="loai" value="<?= e($key) ?>"
                                       <?= ($intent['rx_type'] ?? 'can') === $key ? 'checked' : '' ?>>
                                <span class="btype__name"><?= e($name) ?></span>
                                <span class="btype__desc"><?= e($desc) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <?php
                /* Hai ô độ. Bản thiết kế chỉ hỏi ĐỘ CẦU (SPH) của từng mắt —
                   không trụ, không trục, không PD. Giữ đúng vậy: đây là bước
                   mua hàng, không phải phiếu đo khúc xạ, và mỗi ô thêm vào là
                   một lý do nữa để khách bỏ dở. Phần còn thiếu do kỹ thuật
                   viên đo lại trước khi mài. */
                $eyes = [
                    'od' => ['Mắt phải (OD)', 'MP'],
                    'os' => ['Mắt trái (OS)', 'MT'],
                ];
                $options = LensModel::sphOptions();
                ?>
                <?php foreach ($eyes as $side => [$label, $short]): ?>
                    <div class="beye">
                        <span class="beye__label"><?= e($label) ?></span>
                        <label class="beye__field">
                            <span class="beye__cap">Độ cầu (SPH)</span>
                            <select name="<?= e($side) ?>">
                                <option value="">— Chọn độ —</option>
                                <?php foreach ($options as $o): ?>
                                    <option value="<?= e($o['value']) ?>"><?= e($o['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="bmodal__cta">Xác nhận độ kính</button>
            </form>

            <a class="bmodal__ghost" href="/dat-lich">Không nhớ số đo? Đặt lịch đo mắt miễn phí</a>

        <?php elseif ($step === 'trong'): ?>

            <!-- ══════════ 4. CHỌN LOẠI TRÒNG KÍNH ══════════ -->
            <?php if ($intent['rx'] !== null): ?>
                <?php /* Nhắc lại số đo vừa nhập ngay trên danh sách: mô tả của
                         từng gói nói theo DẢI ĐỘ ("dưới -4.00", "trên -6.00"),
                         nên hai thứ phải nhìn thấy cùng lúc mới so được. */ ?>
                <p class="brxecho"><?= e($intent['rx']) ?></p>
            <?php endif; ?>

            <form class="blens" method="post" action="/gio-hang/chon">
                <?php $stepForm('trong'); ?>

                <?php foreach (LensModel::packages() as $pk): ?>
                    <button type="submit" class="blens__item<?= $intent['lens_id'] === $pk['id'] ? ' is-on' : '' ?>"
                            name="lens" value="<?= e($pk['id']) ?>">
                        <span class="blens__body">
                            <span class="blens__name"><?= e($pk['name']) ?></span>
                            <span class="blens__desc"><?= e($pk['desc']) ?></span>
                        </span>
                        <span class="blens__price">+<?= money((int) $pk['price']) ?></span>
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
                        <span class="bsum__val bsum__val--price">+<?= money($lensPrice) ?></span>
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
                    <?php if ($lens !== null): ?>
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
