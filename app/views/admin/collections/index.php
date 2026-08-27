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
?>
<?php partial('admin/_layout/crud-head', [
    'title' => 'Bộ sưu tập', 'lead' => count($collections) . ' bộ',
    'base' => '/quan-tri/bo-suu-tap', 'canEdit' => $canEdit, 'editing' => $ed,
    'addLabel' => '+ Thêm bộ sưu tập',
]); ?>

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

            <button type="submit" class="astatus__save"><?= $ed !== null ? 'Lưu thay đổi' : 'Thêm bộ sưu tập' ?></button>
        </form>
    </section>
<?php endif; ?>
