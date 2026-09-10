<?php

/**
 * collection/index.php — Bộ sưu tập (/bo-suu-tap).
 *
 * Một thẻ cho mỗi bộ: ảnh bìa · ngày ra mắt · tên · giới thiệu · nút
 * "Xem chi tiết" dẫn sang /bo-suu-tap/<slug>.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NÚT ĐÃ ĐỔI ĐÍCH (2026-08-27)
 *
 * Trước đó nó dẫn thẳng sang /san-pham?collection=<slug>, tức là bỏ qua hẳn
 * trang chi tiết. Nay có trang chi tiết thật — lý do vì sao nó đáng tồn tại
 * ghi ở đầu CollectionController; chính trang đó mới là nơi đặt nút sang danh
 * mục đã lọc sẵn.
 *
 * `slug` đi vào ĐƯỜNG DẪN chứ không còn vào chuỗi truy vấn, nên mã hoá bằng
 * rawurlencode() (đúng như trang chi tiết sản phẩm làm), không phải
 * http_build_query().
 * ─────────────────────────────────────────────────────────────────────────────
 */

/*
 * Ngày ra mắt: chỉ hiện khi CÓ, và chỉ hiện THÁNG/NĂM.
 *
 * Bộ sưu tập là chuyện theo mùa — "Ra mắt 03/2026" nói đúng nhịp cửa hàng làm
 * việc, còn "Ra mắt 14/03/2026" gợi ý một sự kiện diễn ra đúng hôm đó, điều
 * không có thật. Ngày đầy đủ vẫn nằm trong CSDL cho khu quản trị.
 */
$ngayRaMat = static function (?string $date): string {
    if (empty($date) || !preg_match('/^(\d{4})-(\d{2})-/', (string) $date, $m)) {
        return '';
    }

    return 'Ra mắt ' . $m[2] . '/' . $m[1];
};
?>

<?php
/*
 * TIÊU ĐỀ VÀ ĐOẠN DẪN NAY DO CỬA HÀNG SỬA, không còn gõ cứng ở đây.
 *
 * Chúng tới từ bảng `site_texts` qua CollectionController::index(), và khi
 * bảng im lặng thì controller đã thay bằng đúng hai câu vẫn đang hiện — xem
 * hằng DAU_TRANG trong đó. Nên chỗ này không cần phòng thủ thêm lần nữa.
 *
 * Breadcrumb thì VẪN cứng: nó là vị trí trong cây điều hướng, không phải nội
 * dung. Đổi chữ "Bộ sưu tập" ở đó là đổi tên một mục điều hướng có mặt trên
 * mọi trang, không phải sửa lời giới thiệu của một trang.
 */
?>
<?php partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Bộ sưu tập']],
    'head_title'  => $headTitle,
    'head_lead'   => $headLead,
]); ?>

<?php
/*
 * ═════════════════════════════════════════════════════════════════════════════
 * DỰNG LẠI THEO TRANG COLLECTION CỦA GENTLE MONSTER (bỏ bố cục so le cũ)
 *
 * BẢN CŨ: mỗi bộ là một hàng hai cột — ảnh 3:2 một bên, một khối chữ bốn dòng
 * bên kia, và các hàng ĐỔI BÊN so le nhau. Bố cục ấy thuộc về một trang giới
 * thiệu ("về chúng tôi", "quy trình"), không thuộc về một trang TRƯNG BÀY:
 *
 *   · mỗi bộ chiếm trọn một màn hình, nên xem hết chín bộ là cuộn chín màn và
 *     không lúc nào so sánh được hai bộ với nhau
 *   · so le trái/phải bắt mắt zigzag, mà thứ cần so sánh lại là các TẤM ẢNH —
 *     chúng không bao giờ nằm cạnh nhau
 *   · nút "Xem chi tiết" nền đen bo góc: dáng nút của một trang quản trị, và
 *     nó là thứ nặng nhất trong mỗi hàng, hơn cả ảnh
 *
 * BẢN MỚI là một LƯỚI ẢNH. Nhà mốt bày bộ sưu tập đúng như thế: ảnh chiến dịch
 * cỡ lớn xếp cạnh nhau, tên nằm dưới ảnh bằng chữ nhỏ, không nút, cả ô là một
 * liên kết. Mắt quét được nhiều bộ trong một lượt và so sánh bằng HÌNH — thứ
 * duy nhất phân biệt được các bộ với nhau.
 *
 * BỘ ĐẦU ĂN CẢ HÀNG (.collcard--lead). Đây là bộ đứng đầu thứ tự trưng bày, tức
 * là bộ cửa hàng muốn đẩy; cho nó một khung rộng gấp đôi là cách một trang biên
 * tập nói "bắt đầu từ đây" mà không cần chữ "nổi bật".
 *
 * KHÔNG MẤT MỘT THÔNG TIN NÀO so với bản cũ: ngày ra mắt, tên, tagline đều còn.
 * Riêng `intro` (đoạn dài) bỏ khỏi trang danh sách — nó là phần MỞ ĐẦU của
 * trang chi tiết, và in cả ở đây thì khách đọc xong không còn lý do bấm vào.
 * ═════════════════════════════════════════════════════════════════════════════
 */
?>
<section class="colls">
    <?php if ($collections === []): ?>
        <?php /* Chưa có bộ nào đang hiện: nói ra thay vì để trang trắng. Xảy
                 ra thật khi cửa hàng ẩn hết để chuẩn bị mùa mới. */ ?>
        <p class="colls__empty">
            Chưa có bộ sưu tập nào đang trưng bày. Mời bạn xem
            <a href="/san-pham/gong-kinh">gọng kính</a> hoặc <a href="/san-pham/trong-kinh">tròng kính</a>.
        </p>
    <?php else: ?>
        <ul class="collgrid" role="list">
            <?php foreach ($collections as $i => $c): ?>
                <?php
                $cover = CollectionModel::cover($c);
                $when  = $ngayRaMat($c['launched_at'] ?? null);
                ?>
                <li class="collcard<?= $i === 0 ? ' collcard--lead' : '' ?>">
                    <?php
                    /* CẢ Ô LÀ MỘT LIÊN KẾT, không phải "ảnh bấm được + nút bấm
                       được" như bản cũ. Bên trong không có nút nào nên không
                       phạm luật cấm lồng thẻ tương tác, và đích chạm rộng bằng
                       cả tấm ảnh — đúng thứ ngón tay cần trên điện thoại. */
                    ?>
                    <a class="collcard__link" href="/bo-suu-tap/<?= e(rawurlencode($c['slug'])) ?>">
                        <span class="collcard__media">
                            <?php if ($cover !== ''): ?>
                                <?php /* loading="lazy" từ ô THỨ HAI trở đi: ô đầu
                                         nằm ngay trong màn hình đầu, hoãn tải nó
                                         chỉ làm trang trông chậm hơn. */ ?>
                                <img class="collcard__img" src="<?= e(asset($cover)) ?>"
                                     alt=""
                                     width="1200" height="900"
                                     <?= $i === 0 ? '' : 'loading="lazy"' ?> decoding="async">
                            <?php else: ?>
                                <?php /* Bộ vừa tạo chưa kịp có ảnh. Ô giữ chỗ đúng
                                         tỷ lệ để lưới không sập, thay vì một khoảng
                                         trắng cao 0px. */ ?>
                                <span class="collcard__ph" aria-hidden="true">
                                    <?= icon('glasses', '', 40) ?>
                                </span>
                            <?php endif; ?>
                        </span>

                        <span class="collcard__body">
                            <?php if ($when !== ''): ?>
                                <span class="collcard__when"><?= e($when) ?></span>
                            <?php endif; ?>

                            <span class="collcard__name" lang="vi"><?= e($c['name']) ?></span>

                            <?php if (!empty($c['tagline'])): ?>
                                <span class="collcard__tagline"><?= e($c['tagline']) ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
