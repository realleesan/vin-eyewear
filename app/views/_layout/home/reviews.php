<?php

/**
 * _layout/home/reviews.php — BĂNG ĐÁNH GIÁ KHÁCH HÀNG trên trang chủ.
 *
 * Nằm NGAY TRÊN khối "Ghé thăm cửa hàng" (yêu cầu chủ dự án): lời khen của
 * người đã mua đứng ngay trước lời mời tới cửa hàng — đọc xong thì có chỗ để
 * đi tiếp, chứ không phải một lời khen treo lơ lửng cuối trang.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VIẾT LẠI TỪ ĐẦU (12/09/2026)
 *
 * File này trước đây là khối S16 của bản thiết kế cũ: lấy dữ liệu từ
 * config('taxonomy.google_reviews') — tức mấy đánh giá Google chép tay vào
 * file cấu hình — và dựng bằng lớp CSS của home-sections.css, file đã không
 * còn. Nó cũng không được trang chủ require từ lâu.
 *
 * Nay nó đọc ĐÁNH GIÁ THẬT của khách trong CSDL (bảng `reviews`, chỉ những
 * dòng đã duyệt) và dựng bằng ngôn ngữ của oa.css như mọi khối khác.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BĂNG TRƯỢT LÀ CUỘN THẬT, KHÔNG PHẢI TRANSFORM
 *
 * Khối này KHÔNG dùng makeStrip() của assets/js/home.js. Nó là một vùng cuộn
 * ngang thật (overflow-x: auto) có scroll-snap, nên:
 *
 *   · vuốt trên điện thoại và trackpad chạy y như mọi vùng cuộn khác của hệ
 *     điều hành — đúng cái "lướt nhanh" mà chủ dự án yêu cầu, không phải một
 *     băng chỉ nhúc nhích khi bấm mũi tên;
 *   · TẮT JAVASCRIPT VẪN LƯỚT ĐƯỢC. Hai mũi tên là tăng cường thuần tuý:
 *     home.js gắn cú bấm cho chúng, không có JS thì chúng vẫn hiện nhưng
 *     không làm gì — nên chúng mang `hidden` sẵn và home.js gỡ ra;
 *   · bàn phím: dải mang tabindex="0" nên Tab tới rồi bấm mũi tên trái/phải
 *     là cuộn, đúng hành vi mặc định của vùng cuộn.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THẺ TRỐNG KHI CHƯA CÓ ĐÁNH GIÁ — CỐ Ý, THEO YÊU CẦU
 *
 * Chưa đủ một khung (5 thẻ) đánh giá đã duyệt thì in tiếp thẻ trống cho đủ
 * năm, thay vì giấu cả khối. Cửa hàng mới mở là lúc khối này trống nhất mà
 * cũng là lúc chủ dự án cần nhìn thấy chỗ của nó để đi duyệt đánh giá trong
 * khu quản trị.
 *
 * Thẻ trống CHỈ bù cho đủ một khung, không bù thêm để "có cái mà lướt": kéo
 * qua lại giữa mấy ô trống là một băng trượt giả vờ có nội dung.
 *
 * Thẻ trống KHÔNG giả vờ là đánh giá: không sao, không tên người, chỉ một dòng
 * nói thẳng "chưa có đánh giá" — bịa một lời khen vào đó là thứ tuyệt đối
 * không được làm.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SỐ THẺ IN RA KHÁC SỐ THẺ NHÌN THẤY
 *
 * $reviewSlots (5) là số thẻ NHÌN THẤY trong một khung, đồng thời là số thẻ
 * TỐI THIỂU: thiếu thì bù bằng thẻ trống. Còn $reviews có thể nhiều hơn thế
 * (controller lấy 10) — và đó chính là thứ làm băng lướt được: phần dôi ra
 * nằm ngoài khung, chờ được kéo tới.
 *
 * Nhận qua renderView():
 *   $reviews     — đánh giá đã duyệt, tối đa 10 (ReviewModel::latestPublished)
 *   $reviewSlots — số thẻ nhìn thấy trong một khung, cũng là số thẻ tối thiểu
 */

$reviews     = $reviews     ?? [];
$reviewSlots = $reviewSlots ?? 5;

/* Cùng cách vẽ sao với trang chi tiết sản phẩm — xem $stars ở đầu
   app/views/product/detail.php. Sao ĐẶC cho điểm đã làm tròn, sao rỗng cho
   phần còn lại; không có nửa sao vì phông hệ thống không có ký tự ấy. */
$hrevStars = static function (float $score): string {
    $full = (int) round($score);

    return str_repeat('★', max(0, min(5, $full))) . str_repeat('☆', max(0, 5 - $full));
};

/* Số thẻ trống phải in thêm. max(0,...) chứ không trừ thẳng: nếu ngày nào
   controller lấy nhiều hơn số ô thì đây vẫn là 0, không phải một số âm chạy
   vào vòng lặp. */
$hrevTrong = max(0, $reviewSlots - count($reviews));
?>

<section class="oa-band hrev" aria-labelledby="hrev-title">

    <div class="oa-band__head">
        <h2 class="oa-band-title" id="hrev-title"><?= e(t('home.rev.title')) ?></h2>
    </div>

    <div class="hrev__wrap">

        <?php /* Hai mũi tên mang `hidden`: không có JavaScript thì chúng không
                 làm gì được, mà một nút bấm vào không có gì xảy ra còn tệ hơn
                 là không có nút. assets/js/home.js gỡ `hidden` khi đã gắn xong
                 cú bấm. */ ?>
        <button type="button" class="hrev__arrow hrev__arrow--prev" data-rev="prev"
                aria-label="<?= e(t('home.rev.prev')) ?>" hidden>
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </button>

        <ul class="hrev__strip" role="list" data-rev-strip tabindex="0"
            aria-label="<?= e(t('home.rev.title')) ?>">

            <?php foreach ($reviews as $rv): ?>
                <li class="hrev__card">
                    <span class="hrev__stars" aria-label="<?= (int) $rv['rating'] ?>/5">
                        <span aria-hidden="true"><?= $hrevStars((float) $rv['rating']) ?></span>
                    </span>

                    <?php /* Nội dung đã bị chặn ở 400 ký tự lúc gửi
                             (ReviewModel::BODY_MAX), và CSS còn cắt ở 6 dòng —
                             hai lớp cho cùng một việc, vì đánh giá cũ trong CSDL
                             có thể dài hơn trần mới. */ ?>
                    <p class="hrev__body"><?= e($rv['body']) ?></p>

                    <div class="hrev__foot">
                        <span class="hrev__who">
                            <?= e($rv['author_name']) ?><?php if ($rv['order_id'] !== null): ?><span class="hrev__buy"> · Đã mua</span><?php endif; ?>
                        </span>

                        <?php /* Dẫn sang đúng mặt hàng được nói tới. Câu truy vấn
                                 đã lọc `is_visible = 1` nên liên kết này không
                                 bao giờ ra trang 404 — xem
                                 ReviewModel::latestPublished(). */ ?>
                        <a class="hrev__what notranslate" translate="no" lang="vi"
                           href="/san-pham/<?= e(rawurlencode($rv['product_slug'])) ?>"><?= e($rv['product_name']) ?></a>
                    </div>
                </li>
            <?php endforeach; ?>

            <?php for ($i = 0; $i < $hrevTrong; $i++): ?>
                <?php /* aria-hidden: thẻ trống là chỗ giữ chỗ cho MẮT. Trình đọc
                         màn hình đọc ra bốn lần "chưa có đánh giá" thì chỉ tốn
                         thời gian của người dùng. */ ?>
                <li class="hrev__card hrev__card--empty" aria-hidden="true">
                    <span class="hrev__stars hrev__stars--off">☆☆☆☆☆</span>
                    <p class="hrev__body hrev__body--empty"><?= e(t('home.rev.empty_lead')) ?></p>
                    <div class="hrev__foot">
                        <span class="hrev__who"><?= e(t('home.rev.empty')) ?></span>
                    </div>
                </li>
            <?php endfor; ?>
        </ul>

        <button type="button" class="hrev__arrow hrev__arrow--next" data-rev="next"
                aria-label="<?= e(t('home.rev.next')) ?>" hidden>
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M9 6l6 6-6 6"/>
            </svg>
        </button>
    </div>
</section>
