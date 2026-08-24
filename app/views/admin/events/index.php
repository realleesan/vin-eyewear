<?php

/**
 * admin/events/index.php — sự kiện
 * Port từ quan-tri/su-kien.tsx + admin-event-form.tsx.
 */

$ed = $editing;

/** DATETIME của MySQL -> giá trị <input type="datetime-local"> */
$toLocal = static fn (?string $v): string =>
    $v === null || $v === '' ? '' : date('Y-m-d\TH:i', strtotime($v));
?>
<?php partial('admin/_layout/crud-head', [
    'title' => 'Sự kiện', 'lead' => count($events) . ' bài viết',
    'base' => '/quan-tri/su-kien', 'canEdit' => $canEdit, 'editing' => $ed,
    'addLabel' => '+ Thêm sự kiện',
]); ?>

<div class="atable-wrap">
    <table class="atable atable--full">
        <thead>
            <tr>
                <th scope="col">Tiêu đề</th>
                <th scope="col">Nhóm</th>
                <th scope="col">Thời gian</th>
                <th scope="col">Địa điểm</th>
                <th scope="col">Hiển thị</th>
                <?php if ($canEdit): ?><th scope="col">Thao tác</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $ev): ?>
                <tr>
                    <td>
                        <a href="/su-kien/<?= e(rawurlencode($ev['slug'])) ?>" target="_blank" rel="noopener"><?= e($ev['title']) ?></a>
                        <span class="atable__sub"><code><?= e($ev['slug']) ?></code></span>
                    </td>
                    <td><?= e($ev['category'] ?? '—') ?></td>
                    <td><?= e(dateRange($ev['starts_at'], $ev['ends_at'])) ?: '—' ?></td>
                    <td class="atable__msg"><?= e($ev['location'] ?? '—') ?></td>
                    <td>
                        <span class="badge badge--<?= $ev['is_visible'] ? 'in_stock' : 'cancelled' ?>">
                            <?= $ev['is_visible'] ? 'Hiện' : 'Ẩn' ?>
                        </span>
                    </td>
                    <?php if ($canEdit): ?>
                        <td class="arow-actions">
                            <a href="/quan-tri/su-kien?sua=<?= e($ev['id']) ?>#form">Sửa</a>
                            <?php $hoi = sprintf('Xoá sự kiện “%s”?', $ev['title']); ?>
                            <form method="post" action="/quan-tri/su-kien/xoa"
                                  data-confirm="<?= e($hoi) ?>"
                                  data-confirm-title="Xoá sự kiện?"
                                  data-confirm-ok="Xoá"
                                  onsubmit="return confirm('<?= e($hoi) ?>')">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($ev['id']) ?>">
                                <button type="submit" class="arow-del">Xoá</button>
                            </form>
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
            <?= $ed !== null ? 'Sửa sự kiện: ' . e($ed['title']) : 'Thêm sự kiện mới' ?>
        </h2>

        <?php /* enctype BẮT BUỘC: thiếu nó thì trình duyệt gửi mỗi TÊN file
                 dưới dạng text, $_FILES rỗng, và form "chạy" mà ảnh không lên —
                 không có lỗi nào để lần ra. */ ?>
        <form method="post" action="/quan-tri/su-kien/luu" class="aform__grid"
              enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= e($ed['id'] ?? '') ?>">

            <div class="field field--wide">
                <label for="title">Tiêu đề *</label>
                <input type="text" id="title" name="title" required maxlength="255"
                       value="<?= e($ed['title'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="slug">Slug <span class="field__opt">(bỏ trống để tự sinh)</span></label>
                <input type="text" id="slug" name="slug" maxlength="160" value="<?= e($ed['slug'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="category">Nhóm</label>
                <input type="text" id="category" name="category" list="ev-cats" maxlength="60"
                       value="<?= e($ed['category'] ?? '') ?>">
                <datalist id="ev-cats">
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= e($c) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="field">
                <label for="starts_at">Bắt đầu</label>
                <input type="datetime-local" id="starts_at" name="starts_at"
                       value="<?= e($toLocal($ed['starts_at'] ?? null)) ?>">
            </div>

            <div class="field">
                <label for="ends_at">Kết thúc <span class="field__opt">(để trống nếu chỉ một ngày)</span></label>
                <input type="datetime-local" id="ends_at" name="ends_at"
                       value="<?= e($toLocal($ed['ends_at'] ?? null)) ?>">
            </div>

            <div class="field field--wide">
                <label for="location">Địa điểm</label>
                <input type="text" id="location" name="location" maxlength="255"
                       value="<?= e($ed['location'] ?? '') ?>">
            </div>

            <?php /* Ảnh bìa: chọn file từ máy, không gõ đường dẫn.
                     Ô cũ là <input type="text"> nhận đường dẫn tay — chỉ dùng
                     được với ảnh đã nằm sẵn trong assets/images/ do lập trình
                     viên chép vào, nên người quản trị không tự đặt được ảnh bìa
                     cho sự kiện mới. Đổi cùng đợt với ảnh sản phẩm để trong khu
                     quản trị không còn chỗ nào bắt gõ đường dẫn.

                     Không có JS: ô file + ô tick là điều khiển form thuần. */ ?>
            <div class="field field--wide">
                <span class="field__label">Ảnh bìa</span>

                <?php if (!empty($ed['cover_image'])): ?>
                    <div class="aimgs__one">
                        <img class="aimgs__thumb" src="<?= e($ed['cover_image']) ?>" alt="" loading="lazy">
                        <label class="aimgs__keep">
                            <input type="checkbox" name="cover_remove" value="1">
                            Bỏ ảnh bìa này
                        </label>
                    </div>
                <?php endif; ?>

                <label class="aimgs__pick" for="cover_file">
                    <?= !empty($ed['cover_image']) ? 'Chọn ảnh khác để thay' : 'Chọn ảnh từ máy' ?>
                </label>

                <?php /* MAX_FILE_SIZE phải đứng TRƯỚC ô file mới có tác dụng.
                         Chỉ là gợi ý để PHP dừng sớm; giá trị do form gửi lên nên
                         sửa được, máy chủ vẫn đo lại trong ImageUploader. */ ?>
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int) EventCoverStorage::MAX_BYTES ?>">
                <input type="file" id="cover_file" name="cover_file"
                       accept="<?= e(EventCoverStorage::accept()) ?>">

                <p class="field__hint">
                    Định dạng <?= e(EventCoverStorage::formatLabel()) ?>, tối đa
                    <?= e(EventCoverStorage::limitLabel()) ?>. Chọn ảnh mới là thay ảnh cũ.
                </p>
            </div>

            <div class="field field--check">
                <label>
                    <input type="checkbox" name="is_visible" <?= ($ed === null || $ed['is_visible']) ? 'checked' : '' ?>>
                    Hiển thị trên trang sự kiện
                </label>
            </div>

            <div class="field field--wide">
                <label for="excerpt">Tóm tắt</label>
                <textarea id="excerpt" name="excerpt" rows="2"><?= e($ed['excerpt'] ?? '') ?></textarea>
                <p class="field__hint">
                    Hiện ở thẻ ngoài trang danh sách, và làm câu dẫn in nghiêng
                    mở đầu bài viết.
                </p>
            </div>

            <div class="field field--wide">
                <label for="content">Nội dung</label>
                <textarea id="content" name="content" rows="10"><?= e($ed['content'] ?? '') ?></textarea>

                <!-- Bảng ký hiệu nằm NGAY DƯỚI ô nhập, không giấu trong tài
                     liệu riêng: cú pháp mà người viết không nhìn thấy thì coi
                     như không tồn tại. Xem core/Markdown.php. -->
                <p class="field__hint">
                    Mỗi dòng trống tạo một đoạn văn mới. Có thể dùng thêm các ký
                    hiệu sau ở đầu dòng để bài viết có tiêu đề và danh sách:
                </p>
                <ul class="field__hint field__hint--list">
                    <li><code>## Tiêu đề</code> — tiêu đề mục (<code>###</code> cho mục nhỏ hơn)</li>
                    <li><code>- nội dung</code> — gạch đầu dòng</li>
                    <li><code>1. nội dung</code> — các bước có đánh số</li>
                    <li><code>&gt; lời nhắc</code> — hộp ghi chú nền hồng nhạt</li>
                    <li><code>**chữ đậm**</code> — in đậm giữa dòng</li>
                </ul>
                <p class="field__hint">
                    Không dùng được thẻ HTML — mọi thứ khác hiện nguyên văn.
                </p>
            </div>

            <button type="submit" class="astatus__save"><?= $ed !== null ? 'Lưu thay đổi' : 'Thêm sự kiện' ?></button>
        </form>
    </section>
<?php endif; ?>
