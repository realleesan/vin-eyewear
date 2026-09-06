<?php

/**
 * admin/email/mau.php — sửa câu chữ trong thư gửi khách. FR-EM-09.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BẢNG LIỆT KÊ BIẾN LÀ PHẦN KHÔNG ĐƯỢC BỎ
 *
 * Người sửa mẫu gõ `{{ma_don}}` vào ruột thư và mong nó thành "VE-1042". Không
 * có chỗ nào nói cho họ biết tên biến, họ sẽ đoán — `{ma_don}` một ngoặc,
 * `{{madon}}`, `{{order_code}}` — và EmailQueueModel::thay() thay mọi thứ nó
 * không nhận ra bằng CHUỖI RỖNG. Thư gửi đi thiếu một mẩu chữ, lặng lẽ.
 *
 * HAI DẤU NGOẶC, và câu liệt kê ở dưới in ra đúng dạng ấy chứ không chỉ tên
 * biến trần: một danh sách "ten_khach, ma_don" dạy người đọc gõ thiếu ngoặc.
 * EmailTemplateModel::luu() cũng từ chối mọi biến lạ, nên lỗi này nay dừng ở
 * màn hình chứ không đi tới hộp thư của khách — nhưng dừng được là nhờ ô liệt
 * kê này nói đúng ngay từ đầu.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Ô TIẾNG ANH ĐỂ TRỐNG LÀ BÌNH THƯỜNG Ở ĐỢT 6
 *
 * Song ngữ là đợt 9. Cột đã dựng sẵn để bật lên không phải thêm migration, và
 * EmailTemplateModel::noiDung() lùi về bản tiếng Việt khi ô Anh trống —
 * FR-SN-06. Câu ấy phải nằm ngay cạnh ô, không thì người sửa tưởng mình đang
 * để lại một chỗ hỏng.
 * ─────────────────────────────────────────────────────────────────────────────
 */
?>
<div class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Mẫu thư</h1>
        <p class="ahead__lead">
            Câu chữ trong thư gửi khách. Sửa ở đây là đổi mọi lá thư loại đó từ
            lúc lưu trở đi — thư đã xếp hàng rồi thì giữ nguyên nội dung cũ.
        </p>
    </div>
</div>

<?php if (!$coBang): ?>
    <p class="apanel__empty">
        Chưa có bảng mẫu thư trong cơ sở dữ liệu. Chạy
        <code>sudo bash database/migrate.sh</code> rồi mở lại trang này.
        Tới lúc đó hệ thống không soạn lá thư nào.
    </p>
<?php elseif ($rows === []): ?>
    <p class="ahead__note">Bảng mẫu thư đang rỗng. Chạy lại migration đợt 6 để nạp 15 mẫu gốc.</p>
<?php else: ?>
<div class="atable-wrap">
    <table class="atable">
        <thead>
            <tr>
                <th scope="col">Sự kiện</th>
                <th scope="col">Tiêu đề tiếng Việt</th>
                <th scope="col">Bản tiếng Anh</th>
                <th scope="col">Trạng thái</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <a href="/quan-tri/mau-thu?sua=<?= e(rawurlencode($r['key'])) ?>" data-modal>
                            <?= e($r['nhan']) ?>
                        </a>
                        <span class="atable__sub"><?= e($r['key']) ?></span>
                    </td>
                    <td><?= e($r['subject_vi']) ?></td>
                    <td>
                        <?php /* "Chưa dịch" KHÔNG đeo viên đỏ: nó không phải
                                 lỗi mà là trạng thái đã lường trước của đợt 6.
                                 Đỏ ở đây là dạy người ta lo về mười lăm dòng
                                 không có gì để làm. */ ?>
                        <span class="atable__sub">
                            <?= ($r['subject_en'] ?? '') !== '' ? 'Đã dịch' : 'Chưa dịch — dùng bản tiếng Việt' ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= (int) $r['bat'] === 1 ? 'badge--completed' : 'badge--neutral' ?>">
                            <?= (int) $r['bat'] === 1 ? 'Đang bật' : 'Đã tắt' ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($sua !== null): ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => $sua['nhan'],
        'phu'     => (string) ($sua['mo_ta'] ?? ''),
        'dongUrl' => '/quan-tri/mau-thu',
        'rong'    => 'lg',
    ]); ?>

        <p class="amodal__sub">
            Biến dùng được (chép nguyên cả hai dấu ngoặc):
            <?php /* IN THÔ, không cắt nhỏ thành từng viên: cột này là một
                     chuỗi do migration ghi ở đúng dạng cần chép, và người đọc
                     cần chép nguyên cả dấu ngoặc nhọn chứ không cần một hàng
                     thẻ đẹp. */ ?>
            <code><?= e((string) ($sua['bien'] ?? '')) ?></code>
        </p>

        <form method="post" action="/quan-tri/mau-thu/luu" class="aform__grid" id="mau-thu">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="key" value="<?= e($sua['key']) ?>">

            <div class="field field--wide">
                <label for="subject_vi">Tiêu đề (tiếng Việt) *</label>
                <input type="text" id="subject_vi" name="subject_vi" required maxlength="200"
                       value="<?= e((string) $sua['subject_vi']) ?>">
            </div>

            <div class="field field--wide">
                <label for="body_vi">Nội dung (tiếng Việt) *</label>
                <textarea id="body_vi" name="body_vi" rows="10" required><?= e((string) $sua['body_vi']) ?></textarea>
                <p class="field__hint">
                    Nhận HTML đơn giản. Hệ thống tự bọc thêm khung thư có tên và
                    hotline cửa hàng, nên không cần viết phần đầu và chân thư.
                </p>
            </div>

            <div class="field field--wide">
                <label for="subject_en">Tiêu đề (tiếng Anh) <span class="field__opt">(để trống được)</span></label>
                <input type="text" id="subject_en" name="subject_en" maxlength="200"
                       value="<?= e((string) ($sua['subject_en'] ?? '')) ?>">
            </div>

            <div class="field field--wide">
                <label for="body_en">Nội dung (tiếng Anh) <span class="field__opt">(để trống được)</span></label>
                <textarea id="body_en" name="body_en" rows="10"><?= e((string) ($sua['body_en'] ?? '')) ?></textarea>
                <p class="field__hint">
                    Bỏ trống thì khách chọn tiếng Anh vẫn nhận bản tiếng Việt.
                    Song ngữ đầy đủ là phần việc của đợt sau.
                </p>
            </div>

            <div class="field field--check field--wide">
                <label>
                    <input type="checkbox" name="bat" value="1" <?= (int) $sua['bat'] === 1 ? 'checked' : '' ?>>
                    Bật mẫu này
                </label>
                <p class="field__hint">
                    Tắt là hệ thống thôi soạn thư cho sự kiện này — không phải
                    xếp hàng rồi không gửi, mà không sinh ra lá nào cả.
                </p>
            </div>
        </form>

    <?php partial('admin/_layout/modal-foot', [
        'dongUrl' => '/quan-tri/mau-thu',
        'luuForm' => 'mau-thu',
        'luuNhan' => 'Lưu mẫu thư',
        'ghiChu'  => 'Thư đã xếp hàng giữ nguyên nội dung cũ.',
    ]); ?>
<?php endif; ?>
