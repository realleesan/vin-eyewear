<?php

/**
 * _tab-ghi-chu.php — tab 5: ghi chú nội bộ.
 *
 * Biến: $khach, $notes, $noteEditing, $duongDan.
 *
 * KHÁCH KHÔNG BAO GIỜ ĐỌC ĐƯỢC những dòng này. Không có route nào bên site bán
 * hàng chạm tới bảng `customer_notes`, và đừng thêm: đây là câu nhân viên viết
 * cho nhân viên, viết với giả định người ngoài không đọc. Hở ra một đường đọc
 * là đổi luôn thứ người ta dám viết, và khi đó ghi chú mất hết giá trị.
 */

$sua   = $noteEditing;
$veTab = $duongDan . '?tab=ghi-chu';
?>

<div class="apanel">
    <div class="apanel__head">
        <h2 class="apanel__title">Ghi chú nội bộ (<?= count($notes) ?>)</h2>
        <?php if ($sua !== null): ?>
            <a class="apanel__more" href="<?= e($veTab) ?>">Huỷ sửa</a>
        <?php endif; ?>
    </div>

    <?php if ($notes === []): ?>
        <p class="apanel__empty">Chưa có ghi chú nào.</p>
    <?php else: ?>
        <ul class="acus__notes" role="list">
            <?php foreach ($notes as $gc): ?>
                <li class="acus__note">
                    <p class="acus__note-meta">
                        <strong><?= e($gc['author_name'] ?: '(không rõ người viết)') ?></strong>
                        · <?= e(formatDate($gc['created_at'], 'd/m/Y H:i')) ?>
                        <?php if ($gc['updated_at'] !== $gc['created_at']): ?>
                            <?php /* Nói rõ đã sửa: một ghi chú đọc như lời khai
                                     tại thời điểm viết, mà nội dung lại có thể
                                     đã đổi sau đó. */ ?>
                            <span class="atable__sub">
                                (sửa <?= e(formatDate($gc['updated_at'], 'd/m/Y H:i')) ?>)
                            </span>
                        <?php endif; ?>
                    </p>

                    <?php /* nl2br + e(): người ta xuống dòng khi ghi chú, mà HTML
                             nuốt hết dấu xuống dòng. e() chạy TRƯỚC nl2br —
                             ngược lại thì <br> vừa sinh ra cũng bị thoát thành
                             chữ. */ ?>
                    <p class="acus__note-body"><?= nl2br(e($gc['body'])) ?></p>

                    <p class="acus__note-acts">
                        <a href="<?= e($veTab . '&sua=' . rawurlencode($gc['id'])) ?>">Sửa</a>
                        <form method="post" action="/quan-tri/khach-hang/ghi-chu/xoa"
                              data-confirm="Xoá ghi chú này?"
                              data-confirm-title="Xoá ghi chú?"
                              data-confirm-ok="Xoá">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= e($khach['id']) ?>">
                            <input type="hidden" name="ghi_chu_id" value="<?= e($gc['id']) ?>">
                            <button type="submit" class="arow-del">Xoá</button>
                        </form>
                    </p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<section class="apanel" id="form-ghi-chu">
    <div class="apanel__head">
        <h2 class="apanel__title"><?= $sua !== null ? 'Sửa ghi chú' : 'Thêm ghi chú' ?></h2>
    </div>

    <form class="aform" method="post" action="/quan-tri/khach-hang/ghi-chu/luu">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= e($khach['id']) ?>">
        <?php if ($sua !== null): ?>
            <input type="hidden" name="ghi_chu_id" value="<?= e($sua['id']) ?>">
        <?php endif; ?>

        <div class="aform__grid">
            <div class="field field--wide">
                <label for="noi-dung">Nội dung</label>
                <textarea id="noi-dung" name="body" rows="4" required
                          maxlength="2000"><?= e((string) ($sua['body'] ?? '')) ?></textarea>
                <p class="field__hint">
                    Chỉ nhân viên đọc được. Tối đa 2000 ký tự — dài hơn sẽ bị cắt bớt chứ
                    không bị từ chối.
                </p>
            </div>

            <button type="submit" class="astatus__save">
                <?= $sua !== null ? 'Lưu ghi chú' : 'Thêm ghi chú' ?>
            </button>
        </div>
    </form>
</section>
