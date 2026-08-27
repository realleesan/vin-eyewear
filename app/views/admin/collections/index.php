<?php

/**
 * admin/collections/index.php — bộ sưu tập (/quan-tri/bo-suu-tap).
 *
 * Cùng lối với admin/categories/index.php: một trang vừa là bảng vừa là form,
 * mở form sửa bằng ?sua=<id>.
 *
 * Cột "Sản phẩm" không chỉ để biết — nó GIẢI THÍCH TẠI CHỖ vì sao ô slug và
 * nút Xoá của một bộ lại bị khoá. Xem CollectionAdminController.
 */

$ed = $editing;

/* Còn bao nhiêu sản phẩm đang thuộc bộ đang sửa. 0 nghĩa là slug đổi được. */
$dangDung = $ed === null ? 0 : (int) ($counts[$ed['slug']] ?? 0);

/*
 * Ba cột JSON hiện ra ô nhập dưới dạng DÒNG CHỮ, không phải JSON thô.
 *
 * Nhân viên cửa hàng không gõ JSON — một dấu phẩy thừa là cả khối biến mất
 * khỏi trang mà không có gì báo. Nên form nhận từng dòng, controller dựng
 * JSON, và ở đây dựng ngược lại để mở form ra thấy đúng thứ mình đã gõ.
 */
$dongAudience = implode("\n", array_map(
    static fn (array $o): string => implode(' | ', [
        (string) ($o['tieu_de'] ?? ''),
        (string) ($o['gia_tri'] ?? ''),
        (string) ($o['ghi_chu'] ?? ''),
    ]),
    $ed === null ? [] : CollectionModel::jsonField($ed, 'audience')
));

$dongPalette = implode("\n", array_map(
    static fn (array $o): string => (string) ($o['ten'] ?? '') . ' | ' . (string) ($o['ma_mau'] ?? ''),
    $ed === null ? [] : CollectionModel::jsonField($ed, 'palette')
));

$dongSignature = implode("\n", array_map(
    'strval',
    $ed === null ? [] : CollectionModel::jsonField($ed, 'signature')
));
?>
<?php partial('admin/_layout/crud-head', [
    'title' => 'Bộ sưu tập', 'lead' => count($collections) . ' bộ',
    'base' => '/quan-tri/bo-suu-tap', 'canEdit' => $canEdit, 'editing' => $ed,
    'addLabel' => '+ Thêm bộ sưu tập',
]); ?>

<?php
/*
 * NỘI DUNG TRANG TỔNG QUAN — panel riêng, đứng TRƯỚC bảng danh sách.
 *
 * Đây là chữ của trang /bo-suu-tap nói chung, không thuộc bộ nào. Đặt nó ở
 * đầu trang quản trị này là đúng chỗ: người vào đây để sửa bộ sưu tập cũng
 * chính là người viết câu giới thiệu cho cả mục ấy, và họ không phải nhớ ra
 * một trang cài đặt thứ hai ở đâu đó.
 *
 * Form RIÊNG chứ không gộp vào form sửa bộ bên dưới — gộp thì sửa bộ nào cũng
 * ghi đè được chữ này, mà giao diện chẳng có gì nói ra điều đó.
 */
?>
<?php if ($canEdit && $hasTexts): ?>
    <section class="aform" id="tong-quan" aria-labelledby="tong-quan-title">
        <h2 id="tong-quan-title" class="apanel__title">Nội dung trang tổng quan</h2>

        <form method="post" action="/quan-tri/bo-suu-tap/tong-quan" class="aform__grid">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

            <div class="aform__sect">
                <span class="aform__sect-name">Đầu trang</span>
                <span class="aform__sect-note">
                    hiện ở <a href="/bo-suu-tap" target="_blank" rel="noopener">/bo-suu-tap</a>,
                    trên khối nền hồng phía trên danh sách các bộ
                </span>
            </div>

            <div class="field field--wide">
                <label for="head_title">Tiêu đề trang</label>
                <input type="text" id="head_title" name="head_title" maxlength="120"
                       value="<?= e($headTitle) ?>">
                <p class="field__hint">
                    Cũng là tên hiện trên tab trình duyệt. Để trống thì quay về câu
                    mặc định, không phải để trống tiêu đề.
                </p>
            </div>

            <div class="field field--wide">
                <label for="head_lead">Đoạn dẫn</label>
                <textarea id="head_lead" name="head_lead" rows="3"><?= e($headLead) ?></textarea>
                <p class="field__hint">
                    Đoạn chữ nhỏ bên phải tiêu đề, và cũng là mô tả trang gửi cho
                    công cụ tìm kiếm. Viết cho người vừa vào trang và chưa biết nên
                    bấm bộ nào.
                </p>
            </div>

            <button type="submit" class="astatus__save">Lưu nội dung trang</button>
        </form>
    </section>
<?php elseif ($canEdit && !$hasTexts): ?>
    <section class="aform">
        <p class="field__hint">
            Phần sửa nội dung trang tổng quan cần nâng cấp cơ sở dữ liệu
            (<code>2026-08-27-noi-dung-trang-tong-quan.sql</code>) — chạy xong thì
            khối này hiện ra.
        </p>
    </section>
<?php endif; ?>

<div class="atable-wrap">
    <table class="atable atable--full">
        <thead>
            <tr>
                <th scope="col">Tên</th>
                <th scope="col">Ra mắt</th>
                <th scope="col">Thứ tự</th>
                <th scope="col">Sản phẩm</th>
                <th scope="col">Hiển thị</th>
                <?php if ($canEdit): ?><th scope="col">Thao tác</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($collections as $c): ?>
                <?php $soHang = (int) ($counts[$c['slug']] ?? 0); ?>
                <tr>
                    <td>
                        <?php /* Link mở thẳng danh mục ĐÃ LỌC, để nhân viên kiểm
                                 được ngay bộ này ra hàng gì.

                                 KHÔNG trỏ sang /bo-suu-tap/<slug> dù đó mới là
                                 nơi nút "Xem chi tiết" ngoài trang công khai
                                 dẫn tới: trang đó trả 404 cho bộ đang ẩn, mà
                                 bảng này thì hiện CẢ bộ ẩn — nửa số dòng sẽ là
                                 link chết. Đường xem trang chi tiết nằm trong
                                 ô "Câu chuyện" của form, chỗ chỉ mở ra khi
                                 đang sửa đúng một bộ. */ ?>
                        <a href="/san-pham?collection=<?= e(rawurlencode($c['slug'])) ?>"
                           target="_blank" rel="noopener"><?= e($c['name']) ?></a>
                        <span class="atable__sub"><code><?= e($c['slug']) ?></code></span>
                    </td>
                    <td><?= !empty($c['launched_at']) ? e(formatDate($c['launched_at'])) : '—' ?></td>
                    <td class="num"><?= (int) $c['sort_order'] ?></td>
                    <td class="num">
                        <?php /* 0 in gạch trần chứ không in số 0: bộ chưa gắn
                                 hàng nào là chuyện bình thường lúc mới tạo, còn
                                 một cột đầy số 0 thì đọc như lỗi. */ ?>
                        <?= $soHang > 0 ? (int) $soHang : '—' ?>
                    </td>
                    <td>
                        <span class="badge badge--<?= $c['is_visible'] ? 'in_stock' : 'cancelled' ?>">
                            <?= $c['is_visible'] ? 'Hiện' : 'Ẩn' ?>
                        </span>
                    </td>
                    <?php if ($canEdit): ?>
                        <td class="arow-actions">
                            <a href="/quan-tri/bo-suu-tap?sua=<?= e($c['id']) ?>#form">Sửa</a>

                            <?php if ($soHang > 0): ?>
                                <?php /* KHÔNG in nút Xoá khi còn hàng. Máy chủ vẫn
                                         chặn (CollectionAdminController::delete),
                                         nhưng để nút đó ra rồi từ chối là mời người
                                         ta bấm một thứ không bao giờ chạy. */ ?>
                                <span class="atable__sub">Còn hàng — ẩn thay vì xoá</span>
                            <?php else: ?>
                                <?php $hoi = sprintf('Xoá bộ sưu tập “%s”?', $c['name']); ?>
                                <form method="post" action="/quan-tri/bo-suu-tap/xoa"
                                      data-confirm="<?= e($hoi) ?>"
                                      data-confirm-title="Xoá bộ sưu tập?"
                                      data-confirm-ok="Xoá"
                                      onsubmit="return confirm('<?= e($hoi) ?>')">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                                    <button type="submit" class="arow-del">Xoá</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($canEdit): ?>
    <section class="aform" id="form" aria-labelledby="form-title">
        <h2 id="form-title" class="apanel__title">
            <?= $ed !== null ? 'Sửa bộ sưu tập: ' . e($ed['name']) : 'Thêm bộ sưu tập mới' ?>
        </h2>

        <?php /* enctype BẮT BUỘC: thiếu nó thì trình duyệt gửi mỗi TÊN file dưới
                 dạng text, $_FILES rỗng, và form "chạy" mà ảnh không lên. */ ?>
        <form method="post" action="/quan-tri/bo-suu-tap/luu" class="aform__grid"
              enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= e($ed['id'] ?? '') ?>">

            <div class="aform__sect">
                <span class="aform__sect-name">Cơ bản</span>
                <span class="aform__sect-note">tên, đường dẫn và thứ tự trưng bày</span>
            </div>

            <div class="field field--wide">
                <label for="name">Tên bộ sưu tập *</label>
                <input type="text" id="name" name="name" required maxlength="160"
                       value="<?= e($ed['name'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="slug">Slug <span class="field__opt">(bỏ trống để tự sinh)</span></label>
                <input type="text" id="slug" name="slug" maxlength="64"
                       value="<?= e($ed['slug'] ?? '') ?>"
                       <?= $dangDung > 0 ? 'readonly' : '' ?>>
                <?php if ($dangDung > 0): ?>
                    <?php /* readonly chứ không disabled: disabled thì trình duyệt
                             KHÔNG gửi trường này, máy chủ nhận slug rỗng rồi tự
                             sinh lại từ tên — đúng cái việc mà khoá ô là để chặn. */ ?>
                    <p class="field__hint">
                        Khoá vì đang có <?= (int) $dangDung ?> sản phẩm thuộc bộ này.
                        Slug là thứ nối bộ sưu tập với sản phẩm và với mọi link đã
                        chia sẻ — đổi là cả hai cùng đứt. Chuyển hàng sang bộ khác
                        trước rồi mới đổi được.
                    </p>
                <?php else: ?>
                    <p class="field__hint">
                        Nằm trong HAI địa chỉ: /bo-suu-tap/<strong>slug</strong>
                        (trang chi tiết) và /san-pham?collection=<strong>slug</strong>
                        (danh mục đã lọc). Đặt xong thì đừng đổi nữa.
                    </p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="launched_at">Ngày ra mắt</label>
                <input type="date" id="launched_at" name="launched_at"
                       value="<?= e($ed['launched_at'] ?? '') ?>">
                <p class="field__hint">Trang công khai chỉ hiện tháng/năm.</p>
            </div>

            <div class="field">
                <label for="sort_order">Thứ tự trưng bày</label>
                <input type="number" id="sort_order" name="sort_order" step="10"
                       value="<?= e((string) ($ed['sort_order'] ?? 0)) ?>">
                <p class="field__hint">
                    Số nhỏ đứng trước. Nhảy 10 một bậc để sau này chèn thêm bộ vào
                    giữa mà không phải đánh số lại cả bảng.
                </p>
            </div>

            <div class="aform__sect">
                <span class="aform__sect-name">Chữ ngắn</span>
                <span class="aform__sect-note">hiện ở trang chủ, mega menu, thẻ trên /bo-suu-tap và đầu trang chi tiết</span>
            </div>

            <div class="field field--wide">
                <label for="tagline">Câu dẫn <span class="field__opt">(một dòng)</span></label>
                <input type="text" id="tagline" name="tagline" maxlength="255"
                       value="<?= e($ed['tagline'] ?? '') ?>">
                <p class="field__hint">
                    Hiện trên thẻ ở trang chủ và trong menu Sản phẩm — chỗ chỉ vừa
                    một dòng.
                </p>
            </div>

            <div class="field field--wide">
                <label for="intro">Giới thiệu <span class="field__opt">(một đoạn)</span></label>
                <textarea id="intro" name="intro" rows="4"><?= e($ed['intro'] ?? '') ?></textarea>
                <p class="field__hint">
                    Hiện trên thẻ ở <a href="/bo-suu-tap" target="_blank"
                    rel="noopener">/bo-suu-tap</a> và ở đầu trang chi tiết của bộ.
                    Viết cho người đang phân vân bộ nào hợp với mình. Dài quá thì
                    thẻ cao hơn ảnh và hàng thẻ so le bị gãy nhịp — chuyện dài
                    để dành cho ô ngay dưới.
                </p>
            </div>

            <?php /* Ô này biến mất trên máy chưa chạy migration
                     2026-08-27-bo-suu-tap-trang-chi-tiet: gõ vào một ô không có
                     cột nào đỡ thì bấm Lưu xong chữ bốc hơi không một lời nào
                     — xem khối chú thích trong CollectionAdminController::save(). */ ?>
            <?php
            /*
             * TIÊU ĐỀ NHÓM PHẢI ĐI THEO ĐIỀU KIỆN CỦA CÁC Ô DƯỚI NÓ.
             *
             * Năm ô trong nhóm này đến từ HAI migration khác nhau (`story` từ
             * bản trang-chi-tiet, bốn ô còn lại từ bản khung-ba-lop), nên máy
             * chưa chạy nâng cấp thì cả năm cùng ẩn — mà tiêu đề thì vẫn hiện,
             * và người dùng nhìn thấy một dòng chữ nói "nội dung trang chi
             * tiết" với đúng khoảng trắng bên dưới.
             *
             * Đó là chuyện ĐÃ XẢY RA THẬT trên hosting: mã lên bằng FTP tự
             * động, migration bấm tay, và trong khoảng giữa hai việc đó trang
             * quản trị nói mình có một mục mà không có ô nào để nhập.
             *
             * Ba nhóm còn lại (Mùa và xuất xứ · Ưu đãi · SEO) nằm gọn trong
             * một khối if nên không dính; nhóm này là nhóm DUY NHẤT vắt qua
             * hai điều kiện, và đó là lý do nó là nhóm duy nhất hỏng.
             */
            ?>
            <?php
            /*
             * CHƯA CHẠY NÂNG CẤP THÌ NÓI RA, ĐỪNG IM LẶNG.
             *
             * Trước bản vá này, máy thiếu cột chỉ đơn giản không hiện ô nào —
             * người dùng thấy một form ngắn hơn bình thường mà không có gì
             * giải thích, và cách duy nhất để biết là đi đọc mã. Khối câu hỏi
             * thường gặp ở cuối trang đã nói ra từ đầu; nhóm này thì quên.
             */
            ?>
            <?php if (!$hasStory || !$hasFrame): ?>
                <div class="aform__sect">
                    <span class="aform__sect-name">Nội dung trang chi tiết</span>
                </div>
                <p class="field__hint field--wide">
                    Nhóm ô này cần nâng cấp cơ sở dữ liệu, chạy xong thì hiện ra:
                    <?php if (!$hasStory): ?>
                        <code>2026-08-27-bo-suu-tap-trang-chi-tiet.sql</code>
                    <?php endif; ?>
                    <?php if (!$hasFrame): ?>
                        <code>2026-08-27-bo-suu-tap-khung-ba-lop.sql</code>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ($hasStory || $hasFrame): ?>
                <div class="aform__sect">
                    <span class="aform__sect-name">Nội dung trang chi tiết</span>
                    <span class="aform__sect-note">
                        chỉ hiện ở trang riêng của bộ này. Chữ cho thẻ ngoài
                        /bo-suu-tap và cho mega menu thì nằm ở nhóm "Chữ ngắn" bên trên.
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($hasStory): ?>
            <div class="field field--wide">
                <label for="story">Câu chuyện <span class="field__opt">(nhiều đoạn)</span></label>
                <textarea id="story" name="story" rows="10"><?= e($ed['story'] ?? '') ?></textarea>
                <p class="field__hint">
                    Chỉ hiện ở trang chi tiết của chính bộ này
                    <?php /* Chỉ trỏ link khi bộ ĐANG HIỆN: trang chi tiết trả
                             404 cho bộ ẩn (cố ý — xem CollectionController::show),
                             nên với bộ đang chuẩn bị thì in đường dẫn ra dưới
                             dạng chữ, đủ để biết nó sẽ nằm ở đâu. */ ?>
                    <?php if (!empty($ed['slug'])): ?>
                        <?php if (!empty($ed['is_visible'])): ?>
                            (<a href="/bo-suu-tap/<?= e(rawurlencode($ed['slug'])) ?>"
                                target="_blank" rel="noopener">/bo-suu-tap/<?= e($ed['slug']) ?></a>)
                        <?php else: ?>
                            (<code>/bo-suu-tap/<?= e($ed['slug']) ?></code>, mở được
                            khi bật "Đang hiển thị")
                        <?php endif; ?>
                    <?php endif; ?>
                    — nơi duy nhất có đủ chỗ để kể: bộ này ra đời từ đâu, hợp với
                    ai, chọn chất liệu và dáng gọng thế vì lý do gì.
                    <?php /* Nói rõ luật ngắt đoạn NGAY Ở ĐÂY: nếp gõ tự nhiên
                             trong <textarea> là xuống dòng liên tục, mà trang
                             công khai chỉ ngắt đoạn ở DÒNG TRỐNG — không nói
                             thì cả bài ra thành một khối chữ liền. */ ?>
                    Cách nhau một <strong>dòng trống</strong> để sang đoạn mới.
                    Bỏ trống thì trang chi tiết không hiện khối này.
                </p>
            </div>
            <?php endif; ?>

            <?php if ($hasFrame): ?>
            <div class="field field--wide">
                <label for="design_style">Ngôn ngữ thiết kế</label>
                <input type="text" id="design_style" name="design_style" maxlength="160"
                       value="<?= e($ed['design_style'] ?? '') ?>">
                <p class="field__hint">Một câu: phong cách chung của bộ (retro, tối giản, thể thao…).</p>
            </div>

            <div class="field field--wide">
                <label for="audience">Bộ này hợp với ai <span class="field__opt">(mỗi dòng một ô)</span></label>
                <textarea id="audience" name="audience" rows="5"
                          placeholder="Độ tuổi | 25 – 45 | Đã qua tuổi chạy theo mốt&#10;Phong cách | Tối giản ấm | Linen, da bò mộc&#10;Nhu cầu | Ngoài trời | Lái xe ban ngày, đi biển&#10;Không hợp nếu | Nhìn màn hình | Tròng phân cực làm màn hình tối đi"><?= e($dongAudience) ?></textarea>
                <p class="field__hint">
                    Dạng <code>Tiêu đề | Giá trị | Ghi chú</code>, ngăn bằng dấu gạch
                    đứng. Ô CUỐI nên nói ai ĐỪNG mua bộ này — nó tiết kiệm cho cửa
                    hàng nhiều lượt đổi trả hơn ba ô đầu cộng lại.
                </p>
            </div>

            <div class="field field--wide">
                <label for="palette">Bảng màu chủ đạo <span class="field__opt">(mỗi dòng một màu)</span></label>
                <textarea id="palette" name="palette" rows="4"
                          placeholder="Cát ướt | #d8c3ac&#10;San hô | #c96f5c&#10;Nước sâu | #2f4858"><?= e($dongPalette) ?></textarea>
                <p class="field__hint">
                    Dạng <code>Tên | #rrggbb</code>. Dòng nào mã màu không đúng dạng
                    thì bị BỎ khi lưu — giá trị này vẽ thẳng ra ô màu trên trang.
                </p>
            </div>

            <div class="field field--wide">
                <label for="signature">Chi tiết nhận diện <span class="field__opt">(mỗi dòng một ý)</span></label>
                <textarea id="signature" name="signature" rows="4"
                          placeholder="Cạnh vát tay ba lớp ở mặt trước gọng&#10;Đinh tán đồng ở khớp càng"><?= e($dongSignature) ?></textarea>
            </div>
            <?php endif; ?>

            <?php if ($hasFrame): ?>
                <div class="aform__sect">
                    <span class="aform__sect-name">Mùa và xuất xứ</span>
                    <span class="aform__sect-note">dòng chữ nhỏ và bốn ô ngay dưới tên bộ ở trang chi tiết</span>
                </div>

                <?php /* Mười bốn ô dưới đây nuôi trang chi tiết /bo-suu-tap/<slug>.
                         Ô nào bỏ trống thì khối tương ứng KHÔNG hiện ra ngoài —
                         không có ô nào bắt buộc, và một bộ chỉ nhập tên với ảnh
                         bìa vẫn ra một trang trông xong. */ ?>

                <div class="field">
                    <label for="season_code">Mã mùa <span class="field__opt">(huy hiệu)</span></label>
                    <input type="text" id="season_code" name="season_code" maxlength="12"
                           placeholder="SS26" value="<?= e($ed['season_code'] ?? '') ?>">
                    <p class="field__hint">Ngắn, in hoa. Hiện thành ô đỏ cạnh ngày lên kệ.</p>
                </div>

                <div class="field">
                    <label for="season_label">Tên mùa <span class="field__opt">(đọc được)</span></label>
                    <input type="text" id="season_label" name="season_label" maxlength="60"
                           placeholder="Xuân–Hè 2026" value="<?= e($ed['season_label'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="brand">Thương hiệu</label>
                    <input type="text" id="brand" name="brand" maxlength="120"
                           value="<?= e($ed['brand'] ?? '') ?>">
                    <p class="field__hint">
                        Hãng đứng tên CẢ BỘ. Khác ô "Thương hiệu" của từng sản phẩm —
                        bộ hợp tác thì hai chỗ ghi khác nhau là đúng.
                    </p>
                </div>

                <div class="field">
                    <label for="product_line">Dòng sản phẩm</label>
                    <input type="text" id="product_line" name="product_line" maxlength="120"
                           value="<?= e($ed['product_line'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="designed_in">Thiết kế tại</label>
                    <input type="text" id="designed_in" name="designed_in" maxlength="120"
                           value="<?= e($ed['designed_in'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="made_in">Sản xuất tại</label>
                    <input type="text" id="made_in" name="made_in" maxlength="120"
                           value="<?= e($ed['made_in'] ?? '') ?>">
                </div>

                <div class="aform__sect">
                    <span class="aform__sect-name">Ưu đãi và phân phối</span>
                    <span class="aform__sect-note">dải ưu đãi dưới khoảng giá, và câu cuối ở khối kêu gọi</span>
                </div>

                <div class="field field--wide">
                    <label for="launch_offer">Ưu đãi ra mắt</label>
                    <input type="text" id="launch_offer" name="launch_offer" maxlength="255"
                           value="<?= e($ed['launch_offer'] ?? '') ?>">
                    <p class="field__hint">
                        Áp cho CẢ BỘ, hiện ngay dưới khoảng giá và trong ngăn kéo thông số
                        của từng mẫu. Hết ưu đãi thì xoá ô này — không có ngày hết hạn tự động.
                    </p>
                </div>

                <div class="field field--wide">
                    <label for="channels">Kênh phân phối</label>
                    <input type="text" id="channels" name="channels" maxlength="255"
                           value="<?= e($ed['channels'] ?? '') ?>">
                    <p class="field__hint">Một câu, hiện ở khối kêu gọi cuối trang.</p>
                </div>
            <?php endif; ?>

            <div class="aform__sect">
                <span class="aform__sect-name">Ảnh bìa và hiển thị</span>
                <span class="aform__sect-note">ảnh dùng chung cho thẻ, mega menu và đầu trang chi tiết</span>
            </div>

            <div class="field field--wide">
                <span class="field__label">Ảnh bìa</span>

                <?php if (!empty($ed['cover_image'])): ?>
                    <div class="aimgs__one">
                        <?php partial('admin/_layout/image-x', [
                            'x_id' => 'x-cover', 'x_name' => 'cover_remove', 'x_value' => '1',
                            'x_label' => 'Xoá ảnh bìa khi lưu',
                        ]); ?>
                        <img class="aimgs__thumb" src="<?= e(asset($ed['cover_image'])) ?>" alt="" loading="lazy">
                        <?php partial('admin/_layout/image-x-btn', [
                            'x_id' => 'x-cover', 'x_label' => 'Xoá ảnh bìa khi lưu',
                        ]); ?>
                    </div>
                <?php endif; ?>

                <label class="aimgs__pick" for="cover_file">
                    <?= !empty($ed['cover_image']) ? 'Chọn ảnh khác để thay' : 'Chọn ảnh từ máy' ?>
                </label>

                <?php /* MAX_FILE_SIZE phải đứng TRƯỚC ô file mới có tác dụng. Chỉ
                         là gợi ý để PHP dừng sớm; máy chủ vẫn đo lại trong
                         ImageUploader. */ ?>
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int) CollectionCoverStorage::MAX_BYTES ?>">
                <input type="file" id="cover_file" name="cover_file"
                       accept="<?= e(CollectionCoverStorage::accept()) ?>">

                <p class="field__hint">
                    Ảnh LOOKBOOK — người đeo kính, không phải ảnh sản phẩm nền
                    trắng. Định dạng <?= e(CollectionCoverStorage::formatLabel()) ?>,
                    tối đa <?= e(CollectionCoverStorage::limitLabel()) ?>.
                </p>
            </div>

            <div class="field field--check">
                <label>
                    <input type="checkbox" name="is_visible" <?= ($ed === null || $ed['is_visible']) ? 'checked' : '' ?>>
                    Đang hiển thị
                </label>
                <p class="field__hint">
                    Bỏ tick để ẩn khi hết mùa. Sản phẩm vẫn giữ nguyên bộ, và gắn
                    hàng vào bộ đang ẩn vẫn được — dùng khi chuẩn bị bộ sắp ra mắt.
                </p>
            </div>

            <?php if ($hasFrame): ?>
                <div class="aform__sect">
                    <span class="aform__sect-name">SEO</span>
                    <span class="aform__sect-note">chỉ ảnh hưởng kết quả tìm kiếm — bỏ trống thì trang tự dựng, xem gợi ý dưới mỗi ô</span>
                </div>

                <div class="field">
                    <label for="meta_title">SEO — tiêu đề</label>
                    <input type="text" id="meta_title" name="meta_title" maxlength="255"
                           value="<?= e($ed['meta_title'] ?? '') ?>">
                    <p class="field__hint">Bỏ trống thì tự dựng: "&lt;tên bộ&gt; — Bộ sưu tập — Vin Eyewear".</p>
                </div>

                <div class="field">
                    <label for="meta_description">SEO — mô tả</label>
                    <input type="text" id="meta_description" name="meta_description" maxlength="320"
                           value="<?= e($ed['meta_description'] ?? '') ?>">
                    <p class="field__hint">Bỏ trống thì lấy câu dẫn, không có nữa thì lấy phần đầu ô Giới thiệu.</p>
                </div>
            <?php endif; ?>

            <button type="submit" class="astatus__save"><?= $ed !== null ? 'Lưu thay đổi' : 'Thêm bộ sưu tập' ?></button>
        </form>
    </section>

    <?php
    /*
     * CÂU HỎI THƯỜNG GẶP — khối riêng, và chỉ hiện khi ĐANG SỬA một bộ.
     *
     * Không gộp vào form trên vì hai lý do:
     *   · mỗi câu hỏi cần nút xoá của riêng nó, mà HTML không cho lồng <form>;
     *   · lúc THÊM MỚI thì bộ chưa có id, mà collection_faqs.collection_id là
     *     khoá ngoại NOT NULL — không có gì để gắn câu hỏi vào.
     *
     * Nên: lưu bộ trước, mở lại bằng ?sua= rồi mới thêm câu hỏi. Dòng gợi ý
     * dưới đây nói đúng câu đó thay vì để người dùng tự đoán.
     */
    ?>
    <?php if ($ed !== null && $hasFaq): ?>
        <section class="aform" id="faq" aria-labelledby="faq-title">
            <h2 id="faq-title" class="apanel__title">
                Câu hỏi thường gặp của bộ "<?= e($ed['name']) ?>"
            </h2>

            <?php if ($faqs === []): ?>
                <p class="field__hint" style="margin-bottom: 18px;">
                    Chưa có câu hỏi nào. Khối này chỉ hiện trên trang chi tiết khi có ít
                    nhất một câu — viết câu mà khách hay hỏi qua Zalo trước.
                </p>
            <?php else: ?>
                <div class="atable-wrap">
                    <table class="atable atable--full">
                        <thead>
                            <tr>
                                <th scope="col">Thứ tự</th>
                                <th scope="col">Câu hỏi</th>
                                <th scope="col">Trả lời</th>
                                <th scope="col">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faqs as $q): ?>
                                <tr>
                                    <td class="num"><?= (int) $q['sort_order'] ?></td>
                                    <td><?= e($q['question']) ?></td>
                                    <td><?= e(excerpt($q['answer'], 120)) ?></td>
                                    <td>
                                        <?php /* Xoá đi qua POST, không phải một liên kết:
                                                 xoá bằng GET nghĩa là một thẻ <img src="..."> ở
                                                 trang khác cũng xoá được câu hỏi. Cùng lý do mà
                                                 sổ địa chỉ của khách phải POST — xem routes.php. */ ?>
                                        <?php /* data-confirm nuôi hộp thoại xác nhận của khu
                                                 quản trị; onsubmit là bản dự phòng khi tắt JS —
                                                 cùng bộ thuộc tính mà trang danh mục dùng. */ ?>
                                        <form method="post" action="/quan-tri/bo-suu-tap/faq/xoa"
                                              data-confirm="Xoá câu hỏi này khỏi bộ sưu tập?"
                                              data-confirm-title="Xoá câu hỏi?"
                                              data-confirm-ok="Xoá"
                                              onsubmit="return confirm('Xoá câu hỏi này khỏi bộ sưu tập?')">
                                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= e($q['id']) ?>">
                                            <button type="submit" class="arow-del">Xoá</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <form method="post" action="/quan-tri/bo-suu-tap/faq/luu" class="aform__grid">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="collection_id" value="<?= e($ed['id']) ?>">

                <div class="field field--wide">
                    <label for="faq_question">Câu hỏi *</label>
                    <input type="text" id="faq_question" name="question" required maxlength="255"
                           placeholder="Kính râm bộ này lắp được độ cận không?">
                </div>

                <div class="field field--wide">
                    <label for="faq_answer">Trả lời *</label>
                    <textarea id="faq_answer" name="answer" rows="4" required
                              placeholder="Bốn trong sáu mẫu lắp được, tới -6.00 hoặc -8.00 tuỳ mẫu…"></textarea>
                    <p class="field__hint">
                        Trả lời thẳng, có con số. Nhắc đích danh mẫu nào được mẫu nào
                        không — đó là thứ trang danh mục không nói được.
                    </p>
                </div>

                <div class="field">
                    <label for="faq_sort">Thứ tự</label>
                    <input type="number" id="faq_sort" name="sort_order" step="10" value="0">
                    <p class="field__hint">
                        Số nhỏ đứng trước. Câu ĐẦU TIÊN mở sẵn trên trang, nên để câu
                        quyết định nhất lên đầu.
                    </p>
                </div>

                <button type="submit" class="astatus__save">Thêm câu hỏi</button>
            </form>
        </section>
    <?php elseif ($ed !== null && !$hasFaq): ?>
        <section class="aform">
            <p class="field__hint">
                Phần câu hỏi thường gặp cần nâng cấp cơ sở dữ liệu
                (<code>2026-08-27-bo-suu-tap-khung-ba-lop.sql</code>) — chạy xong thì khối
                này hiện ra.
            </p>
        </section>
    <?php endif; ?>
<?php endif; ?>
