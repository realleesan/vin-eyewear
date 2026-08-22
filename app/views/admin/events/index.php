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
                            <form method="post" action="/quan-tri/su-kien/xoa"
                                  onsubmit="return confirm('Xoá sự kiện &quot;<?= e($ev['title']) ?>&quot;?')">
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

        <form id="eventForm" method="post" action="/quan-tri/su-kien/luu" class="aform__grid">
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

            <div class="field field--wide">
                <label>Ảnh bìa</label>
                <div class="upload-zone" id="coverUploadZone">
                    <input type="file" id="coverImageInput" accept="image/png, image/jpeg, image/webp" style="display:none">
                    <input type="hidden" name="existing_cover_image" id="existingCoverImage" value="<?= e($ed['cover_image'] ?? '') ?>">
                    <div class="upload-zone__content">
                        <svg class="upload-zone__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <p class="upload-zone__text">Kéo ảnh bìa vào đây hoặc <span class="upload-zone__link">bấm để chọn</span></p>
                        <p class="upload-zone__hint">PNG, JPG, JPEG, WEBP — tối đa 5 MB</p>
                    </div>
                    <div class="preview-single" id="coverPreview" style="display:none">
                        <img src="" alt="Ảnh bìa xem trước" class="preview-single__img">
                        <button type="button" class="preview-single__remove" aria-label="Xóa ảnh bìa">×</button>
                    </div>
                </div>
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
            </div>

            <button type="submit" class="astatus__save"><?= $ed !== null ? 'Lưu thay đổi' : 'Thêm sự kiện' ?></button>
        </form>

        <button type="button" id="btnSaveEvent" class="btn-save">
            Lưu thay đổi
        </button>
    </section>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        tinymce.init({
            selector: '#content',
            height: 500,
            menubar: false,
            language: 'vi',
            directionality: 'ltr',
            skin: 'oxide',
            content_style: 'body { font-family: var(--font-sans, sans-serif); font-size: 14px; }',
            promotion: false,
            resize: 'both',
            statusbar: true,
            elementpath: false,
            browser_spellcheck: true,
            contextmenu: false,
            toolbar: 'formatselect | bold italic underline strikethrough | link image media | numlist bullist | blockquote | alignleft aligncenter alignright alignjustify | forecolor backcolor',
            toolbar_mode: 'wrap',
            toolbar_location: 'top',
            toolbar_sticky: true,
            block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4',
            style_formats: [
                { title: 'Đậm', inline: 'strong' },
                { title: 'Nghiêng', inline: 'em' },
                { title: 'Gạch chân', inline: 'u' },
                { title: 'Gạch ngang', inline: 'strike' },
                { title: 'Trích dẫn', block: 'blockquote' },
            ],
            content_css: '/assets/css/admin.css',
            branding: false,
            removed_menuitems: 'newdocument',
            paste_data_images: true,
            automatic_uploads: false,
            images_upload_handler: function (blobInfo, success, failure) {
                const formData = new FormData();
                formData.append('file', blobInfo.blob(), blobInfo.filename());
                formData.append('_token', '<?= e(csrfToken()) ?>');
                fetch('/admin/event/upload-image', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.url) {
                        success(result.url);
                    } else {
                        failure('Tải ảnh lên thất bại.');
                    }
                })
                .catch(() => failure('Lỗi kết nối khi tải ảnh.'));
            },
            setup: function (editor) {
                editor.on('change', function () {
                    editor.save();
                });
            }
        });

        const form = document.getElementById('eventForm');
        const btn = document.getElementById('btnSaveEvent');
        const coverZone = document.getElementById('coverUploadZone');
        const coverInput = document.getElementById('coverImageInput');
        const existingCoverInput = document.getElementById('existingCoverImage');
        const coverPreview = document.getElementById('coverPreview');
        const coverPreviewImg = coverPreview ? coverPreview.querySelector('.preview-single__img') : null;
        const coverRemoveBtn = coverPreview ? coverPreview.querySelector('.preview-single__remove') : null;

        if (!form || !btn) return;

        let selectedCoverFile = null;

        function showCoverPreview(file) {
            if (!coverPreview || !coverPreviewImg) return;
            const reader = new FileReader();
            reader.onload = function (e) {
                coverPreviewImg.src = e.target.result;
                coverPreview.style.display = 'inline-block';
            };
            reader.readAsDataURL(file);
        }

        function hideCoverPreview() {
            if (!coverPreview || !coverPreviewImg) return;
            coverPreview.style.display = 'none';
            coverPreviewImg.src = '';
            selectedCoverFile = null;
            if (coverInput) coverInput.value = '';
            if (existingCoverInput) existingCoverInput.value = '';
        }

        function showExistingCover(url) {
            if (!url || !coverPreview || !coverPreviewImg) return;
            coverPreviewImg.src = url;
            coverPreview.style.display = 'inline-block';
        }

        if (coverZone && coverInput) {
            coverZone.addEventListener('click', function (e) {
                if (e.target === coverRemoveBtn || coverRemoveBtn.contains(e.target)) {
                    return;
                }
                coverInput.click();
            });

            coverZone.addEventListener('dragover', function (e) {
                e.preventDefault();
                coverZone.classList.add('upload-zone--over');
            });

            coverZone.addEventListener('dragleave', function (e) {
                e.preventDefault();
                coverZone.classList.remove('upload-zone--over');
            });

            coverZone.addEventListener('drop', function (e) {
                e.preventDefault();
                coverZone.classList.remove('upload-zone--over');
                const files = Array.from(e.dataTransfer.files).filter(function (f) {
                    return f.type.startsWith('image/');
                });
                if (files.length > 0) {
                    selectedCoverFile = files[0];
                    showCoverPreview(selectedCoverFile);
                }
            });

            coverInput.addEventListener('change', function () {
                const files = Array.from(coverInput.files);
                if (files.length > 0) {
                    selectedCoverFile = files[0];
                    showCoverPreview(selectedCoverFile);
                }
                coverInput.value = '';
            });
        }

        if (coverRemoveBtn) {
            coverRemoveBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                hideCoverPreview();
            });
        }

        const existingCover = <?= json_encode($ed['cover_image'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
        if (existingCover && !selectedCoverFile) {
            showExistingCover(existingCover);
        }

        btn.addEventListener('click', async function () {
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Đang lưu...';

            try {
                if (typeof tinyMCE !== 'undefined') {
                    tinyMCE.triggerSave();
                }
                const formData = new FormData(form);

                if (selectedCoverFile) {
                    formData.append('cover_image', selectedCoverFile);
                }

                const response = await fetch('/admin/event/save', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: formData,
                    credentials: 'same-origin'
                });
                let payload = null;
                try { payload = await response.json(); } catch (e) { throw new Error('Phản hồi từ máy chủ không phải JSON hợp lệ.'); }
                if (!response.ok || !payload.success) throw new Error(payload.message || 'Lưu sự kiện thất bại.');
                alert(payload.message || 'Lưu thành công!');
                window.location.href = payload.redirect || '/quan-tri/su-kien';
            } catch (error) {
                alert(error.message || 'Lỗi khi lưu sự kiện.');
                console.error(error);
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    });
</script>
