<?php

/**
 * _layout/home/quick-check.php — "Bỏ ra 5 phút để kiểm tra" (S06).
 *
 * Dựng theo "Vin Eyewear Home.dc.html". THAY CHO khối "chọn theo khuôn mặt"
 * (_layout/home/style-guide.php) của bản thiết kế trước: cùng chỗ trên trang,
 * nhưng đổi từ ba tấm ảnh dẫn sang bộ lọc thành ba việc khách tự làm được
 * ngay trên trang chủ.
 *
 *   ba thẻ ảnh cao 400px — Chọn tròng · Chọn gọng · Đặt lịch
 *   hai thẻ đầu mở một HỘP THOẠI hỏi đúng một câu rồi đưa ra gợi ý
 *
 * TẮT JS VẪN DÙNG ĐƯỢC. Hai nút mở hộp thoại là thẻ <a> trỏ sẵn tới trang
 * danh mục tương ứng; assets/js/home.js chặn cú bấm để mở hộp thoại thay thế.
 * Không có JS thì bấm vào là sang thẳng trang danh mục — mất phần tư vấn,
 * không mất lối đi. Bản thiết kế dùng <button>, ở đây cố ý lệch vì lý do đó.
 *
 * Hộp thoại chỉ được in ra MỘT lần và chứa cả hai bảng (tròng · gọng); JS đổi
 * tiêu đề và bảng đang mở. Hai hộp thoại song song sẽ phải chép đôi phần khung,
 * nút đóng và bẫy tiêu điểm bàn phím.
 *
 * BẤM VÀO CHỖ NÀO TRONG THẺ CŨNG ĂN, kể cả tấm ảnh — làm bằng lớp phủ CSS
 * .qcard__btn::after trải kín thẻ, xem ghi chú tại chỗ trong home-sections.css.
 * Ở view không có gì phải khai thêm; chỉ nhớ một điều: thẻ nào về sau có thêm
 * liên kết thứ hai bên trong thì liên kết đó bị lớp phủ che mất.
 */

/*
 * Ba thẻ. Ô ảnh "check-1..3" của bản thiết kế; chưa tải ảnh thiết kế về thì
 * dùng ảnh có sẵn trong repo.
 *
 * 'panel' là tên bảng mà JS sẽ mở; thẻ thứ ba không có nên nó chỉ là liên kết
 * thường sang trang đặt lịch.
 */
$cards = [
    [
        'panel' => 'lens',
        'name'  => 'Chọn tròng',
        'desc'  => 'Trả lời 1 câu hỏi để tìm loại tròng đúng nhu cầu của bạn.',
        'cta'   => 'Chọn tròng kính →',
        'url'   => danhMucUrl('trong-kinh'),
        'image' => designImage('check-1', 'assets/images/product-5.jpg'),
        'alt'   => 'Tròng kính chụp cận cảnh',
    ],
    [
        'panel' => 'frame',
        'name'  => 'Chọn gọng',
        'desc'  => 'Chọn dáng khuôn mặt giống bạn nhất để được gợi ý gọng.',
        'cta'   => 'Chọn gọng kính →',
        'url'   => danhMucUrl('gong-kinh'),
        'image' => designImage('check-2', 'assets/images/showroom-frames.jpg'),
        'alt'   => 'Kệ trưng bày gọng kính tại cửa hàng',
    ],
    [
        'panel' => null,
        'name'  => 'Đặt lịch',
        'desc'  => 'Đặt lịch đo mắt miễn phí tại cơ sở gần bạn.',
        'cta'   => 'Đặt lịch ngay →',
        'url'   => '/dat-lich',
        'image' => designImage('check-3', 'assets/images/showroom-exam-room.jpg'),
        'alt'   => 'Kỹ thuật viên tư vấn cho khách tại cửa hàng',
    ],
];

/*
 * Câu hỏi chọn tròng. Ba lựa chọn, mỗi lựa chọn một câu gợi ý và một đường
 * lọc có thật trong catalog — bấm vào là ra hàng, không phải một trang giới
 * thiệu suông.
 *
 * ĐÃ ĐỔI TỪ ?q= SANG ?lens[]=: 'q' chỉ tìm trong name/brand/sku, nên
 * "/san-pham?q=blue" chỉ ra hàng có chữ "blue" TRONG TÊN — gần như không món
 * nào, dù kho có cả chục gọng ghi tính năng chống ánh sáng xanh trong `specs`.
 * Khoá dưới đây khớp với nhóm "Tính năng tròng" của cột lọc (ProductTaxonomy).
 */
$lensOptions = [
    [
        'label' => 'A. Bảo vệ mắt toàn diện khi sử dụng thiết bị điện tử',
        'rec'   => 'Tròng chống ánh sáng xanh (Blue-cut) 1.56 – 1.61 — lọc ánh sáng '
                 . 'màn hình, phù hợp làm việc máy tính nhiều giờ.',
        'url'   => '/san-pham?lens%5B%5D=blue-light',
    ],
    [
        'label' => 'B. Bảo vệ mắt khi trời nắng',
        'rec'   => 'Tròng đổi màu Photochromic hoặc kính mát Polarized UV400 — tự tối '
                 . 'màu khi ra nắng, chống chói.',
        'url'   => '/san-pham?lens%5B%5D=photochromic',
    ],
    [
        'label' => 'C. Mỏng nhẹ cho người cận thị độ cao',
        'rec'   => 'Tròng chiết suất cao 1.67 – 1.74 — mỏng nhẹ cho độ cận từ 4.00 trở lên.',
        'url'   => '/san-pham?lens%5B%5D=rx',
    ],
];

/*
 * Sáu dáng khuôn mặt. 'path' là đường viền mặt vẽ trong khung SVG 200×270,
 * lấy nguyên từ bản thiết kế; 'shape' là giá trị cột products.frame_shape để
 * dẫn sang bộ lọc (khớp config('taxonomy.frame_styles')).
 *
 * KHÔNG dùng config('taxonomy.face_shapes'): mảng đó phục vụ khối
 * khối "chọn theo khuôn mặt" của bản Lovable (đã gỡ) — nó mang 'hint' và một
 * DANH SÁCH dáng gọng gợi ý, còn ở đây mỗi khuôn mặt cần đúng một câu mô tả
 * và một hình vẽ. Nhập hai thứ làm một sẽ phải nhét 'path' vào config rồi để
 * đó một trường mà khối kia không bao giờ đọc.
 */
$faces = [
    ['num' => '01', 'name' => 'Mặt tròn',      'shape' => 'Square',
     'desc' => 'Gọng vuông, browline — tạo đường nét góc cạnh, cân lại khuôn mặt',
     'path' => 'M100 32 C156 32 180 84 180 132 C180 188 148 234 100 234 C52 234 20 188 20 132 C20 84 44 32 100 32 Z'],
    ['num' => '02', 'name' => 'Mặt vuông',     'shape' => 'Oval',
     'desc' => 'Gọng oval, kim loại mảnh — làm mềm đường quai hàm',
     'path' => 'M48 40 C70 26 130 26 152 40 C168 50 172 84 172 126 C172 172 166 202 146 220 C126 236 74 236 54 220 C34 202 28 172 28 126 C28 84 32 50 48 40 Z'],
    ['num' => '03', 'name' => 'Mặt trái xoan', 'shape' => 'Round',
     'desc' => 'Gọng tròn, acetate dày — khuôn mặt cân đối, hợp hầu hết kiểu gọng',
     'path' => 'M100 28 C148 28 170 74 170 118 C170 174 138 236 100 236 C62 236 30 174 30 118 C30 74 52 28 100 28 Z'],
    ['num' => '04', 'name' => 'Mặt dài',       'shape' => 'Wayfarer',
     'desc' => 'Gọng bản lớn, oversized — rút ngắn tỷ lệ, cân đối chiều dọc',
     'path' => 'M100 22 C140 22 156 66 156 124 C156 190 134 246 100 246 C66 246 44 190 44 124 C44 66 60 22 100 22 Z'],
    ['num' => '05', 'name' => 'Mặt trái tim',  'shape' => 'Cat-eye',
     'desc' => 'Gọng cat-eye nhẹ, không viền — cân lại phần cằm thon',
     'path' => 'M100 30 C146 30 172 58 172 98 C172 152 136 208 100 238 C64 208 28 152 28 98 C28 58 54 30 100 30 Z'],
    ['num' => '06', 'name' => 'Mặt kim cương', 'shape' => 'Oval',
     'desc' => 'Gọng browline mềm, oval — làm nổi gò má, dịu phần trán',
     'path' => 'M100 28 C122 28 152 62 166 112 C172 136 148 192 100 240 C52 192 28 136 34 112 C48 62 78 28 100 28 Z'],
];
?>

<section class="qcheck" data-section="s06" aria-labelledby="qcheck-title">
    <div class="qcheck__inner">

        <div class="qcheck__head">
            <p class="qcheck__kicker">Là một sản phẩm về sức khoẻ con người, hãy</p>
            <h2 id="qcheck-title" class="section-h2 section-h2--plain">Bỏ ra 5 phút để kiểm tra</h2>
        </div>

        <ul class="qcheck__grid" role="list">
            <?php foreach ($cards as $card): ?>
                <li class="qcard">
                    <div class="qcard__media">
                        <img src="<?= e($card['image']) ?>" alt="<?= e($card['alt']) ?>"
                             loading="lazy" decoding="async">
                    </div>

                    <div class="qcard__body">
                        <h3 class="qcard__name"><?= e($card['name']) ?></h3>
                        <p class="qcard__desc"><?= e($card['desc']) ?></p>
                    </div>

                    <div class="qcard__foot">
                        <a class="qcard__btn" href="<?= e($card['url']) ?>"
                           <?= $card['panel'] !== null ? 'data-qcheck-open="' . e($card['panel']) . '"' : '' ?>>
                            <?= e($card['cta']) ?>
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- ============================================================
         HỘP THOẠI — chỉ hoạt động khi có JS, xem ghi chú đầu file.
         ============================================================ -->
    <div class="qmodal" id="quickCheck" hidden>
        <div class="qmodal__backdrop" data-qcheck-close></div>

        <div class="qmodal__panel" role="dialog" aria-modal="true" aria-labelledby="qmodal-title">

            <div class="qmodal__head">
                <h3 class="qmodal__title" id="qmodal-title" data-qcheck-title>Chọn tròng</h3>
                <button type="button" class="qmodal__close" data-qcheck-close aria-label="Đóng">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <!-- --- Bảng 1: chọn tròng ---------------------------- -->
            <div class="qmodal__panel-lens" data-qcheck-panel="lens">
                <div class="qlens">
                    <p class="qlens__question">Bạn quan trọng điều gì nhất khi đeo kính?</p>

                    <div class="qlens__options">
                        <?php foreach ($lensOptions as $i => $opt): ?>
                            <button type="button" class="qlens__option"
                                    data-qlens="<?= $i ?>"
                                    aria-pressed="false"><?= e($opt['label']) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php /* Ba câu gợi ý in sẵn, JS chỉ bỏ [hidden] của đúng một câu.
                         Dựng chuỗi trong JS thì phần chữ này lọt ra ngoài PHP và
                         không còn qua e() nữa. */ ?>
                <?php foreach ($lensOptions as $i => $opt): ?>
                    <div class="qrec" data-qlens-rec="<?= $i ?>" hidden>
                        <p class="qrec__text"><strong>Gợi ý cho bạn:</strong> <?= e($opt['rec']) ?></p>
                        <a class="qrec__link" href="<?= e($opt['url']) ?>">Tư vấn chọn tròng →</a>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- --- Bảng 2: chọn gọng theo khuôn mặt -------------- -->
            <div class="qmodal__panel-frame" data-qcheck-panel="frame" hidden>
                <div class="qface__head">
                    <p class="qface__title">Hình dáng khuôn mặt</p>
                    <p class="qface__sub">Dáng khuôn mặt nào sau đây trông giống bạn nhất?</p>
                </div>

                <div class="qface">
                    <button type="button" class="qface__arrow qface__arrow--prev"
                            data-qface="prev" aria-label="Dáng mặt trước">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                    </button>
                    <button type="button" class="qface__arrow qface__arrow--next"
                            data-qface="next" aria-label="Dáng mặt sau">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M9 6l6 6-6 6"/>
                        </svg>
                    </button>

                    <div class="qface__window">
                        <ul class="qface__track" role="list">
                            <?php foreach ($faces as $i => $face): ?>
                                <li class="qface__item">
                                    <button type="button" class="qface__card"
                                            data-qface-pick="<?= $i ?>" aria-pressed="false">
                                        <svg class="qface__draw" viewBox="0 0 200 270"
                                             aria-hidden="true" focusable="false">
                                            <path d="<?= e($face['path']) ?>" stroke="#33272a" stroke-width="2" fill="#fff"/>
                                            <path d="M52 254 H148" stroke="#8a2432" stroke-width="1.5"
                                                  stroke-dasharray="2 6" stroke-linecap="round"/>
                                            <circle cx="52" cy="254" r="3.5" stroke="#8a2432" stroke-width="1.5" fill="none"/>
                                            <circle cx="148" cy="254" r="3.5" stroke="#8a2432" stroke-width="1.5" fill="none"/>
                                        </svg>
                                        <span class="qface__name"><?= e($face['name']) ?></span>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <?php foreach ($faces as $i => $face): ?>
                    <div class="qrec" data-qface-rec="<?= $i ?>" hidden>
                        <p class="qrec__text"><strong><?= e($face['name']) ?>:</strong> <?= e($face['desc']) ?></p>
                        <a class="qrec__link" href="/san-pham?shape=<?= e(rawurlencode($face['shape'])) ?>">Xem gọng phù hợp →</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
