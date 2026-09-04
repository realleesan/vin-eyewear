<?php

/**
 * admin/audit-logs/index.php — màn Lịch sử thao tác.
 *
 * Controller: Admin/AuditLogAdminController::index()
 *
 * Chỉ dùng lớp CSS đã có trong admin.css (.ahead, .atabs, .atable, .pager,
 * .apanel__empty…). Không thêm file CSS mới: màn này là một bảng có bộ lọc,
 * đúng khuôn của các màn danh sách khác — thêm một file riêng chỉ để lặp lại
 * cùng những luật ấy là tạo thêm một chỗ nữa để lệch.
 */

/* Dựng lại đường dẫn hiện tại kèm một tham số được thay. Dùng cho cả viên lọc
   nhóm lẫn nút phân trang, nên không có chỗ nào tự nối chuỗi query lấy —
   thiếu một tham số là bấm sang trang 2 xong mất sạch bộ lọc, lỗi kinh điển. */
$duongDan = static function (array $thay = []) use ($loc): string {
    $tham = array_filter([
        'nhom'      => $loc['nhom'],
        'hanh-dong' => $loc['action'],
        'nguoi'     => $loc['actor'],
        'tu'        => $loc['tu'],
        'den'       => $loc['den'],
        'q'         => $loc['q'],
    ], static fn ($v): bool => $v !== '' && $v !== null);

    foreach ($thay as $k => $v) {
        if ($v === '' || $v === null) {
            unset($tham[$k]);
        } else {
            $tham[$k] = $v;
        }
    }

    return '/quan-tri/nhat-ky' . ($tham !== [] ? '?' . http_build_query($tham) : '');
};

$dangLoc = $loc['nhom'] !== '' || $loc['action'] !== '' || $loc['actor'] !== ''
        || $loc['tu'] !== '' || $loc['den'] !== '' || $loc['q'] !== '';
?>

<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Lịch sử thao tác</h1>
        <?php /* Dòng dẫn nói TỔNG SỐ VẾT ĐANG LỌC, không phải tổng toàn hệ
                 thống: con số người đọc cần là "bộ lọc này ra bao nhiêu", còn
                 tổng của cả bảng thì không dùng vào việc gì. */ ?>
        <p class="ahead__lead">
            <?= number_format((int) $total, 0, ',', '.') ?> thao tác<?= $dangLoc ? ' khớp bộ lọc' : '' ?>
        </p>
    </div>

    <div class="ahead__tools">
        <form class="asearch" method="get" action="/quan-tri/nhat-ky" role="search">
            <?php /* Giữ các bộ lọc khác khi gõ tìm: người đang xem "tuần trước,
                     nhóm tiền" mà gõ thêm một cái tên thì phải được lọc CHỒNG
                     lên, không phải bị ném về danh sách đầy đủ. */ ?>
            <?php foreach (['nhom' => $loc['nhom'], 'hanh-dong' => $loc['action'],
                            'nguoi' => $loc['actor'], 'tu' => $loc['tu'], 'den' => $loc['den']] as $k => $v): ?>
                <?php if ($v !== ''): ?>
                    <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
                <?php endif; ?>
            <?php endforeach; ?>

            <label class="sr-only" for="q">Tìm trong lịch sử thao tác</label>
            <input type="search" id="q" name="q" value="<?= e($loc['q']) ?>"
                   placeholder="Tên nhân viên, tên khách, số điện thoại, IP…">
            <button type="submit" class="astatus__save astatus__save--ghost">Tìm</button>
            <?php if ($dangLoc): ?>
                <a href="/quan-tri/nhat-ky" class="apanel__more">Xoá bộ lọc</a>
            <?php endif; ?>
        </form>

        <?php if ($logs !== []): ?>
            <?php /* Không có data-modal: đây là tải file, không phải mở hộp
                     thoại. Gắn nhầm thuộc tính đó thì admin-modal.js nuốt
                     lượt bấm và đi tìm .amodal trong một file CSV. */ ?>
            <a class="astatus__save" href="<?= e(str_replace('/quan-tri/nhat-ky', '/quan-tri/nhat-ky/xuat', $duongDan())) ?>">
                Xuất CSV
            </a>
        <?php endif; ?>
    </div>
</header>

<?php if (!$coBang): ?>
    <p class="apanel__empty">
        Chưa có bảng lịch sử thao tác trong cơ sở dữ liệu. Chạy
        <code>sudo bash database/migrate.sh</code> rồi mở lại trang này.
    </p>
<?php else: ?>

    <?php /* ── Viên lọc theo PHÂN HỆ ─────────────────────────────────────────
             Đặt trên cùng vì đây là câu hỏi đầu tiên của người mở màn này:
             "hôm nay ai đụng vào hồ sơ khúc xạ" hoặc "ai sửa trạng thái tiền".
             Con số trên mỗi viên tính theo các bộ lọc khác đang bật, nên bấm
             qua lại không bao giờ ra một viên có số mà bấm vào lại rỗng. */ ?>
    <nav class="atabs" aria-label="Lọc theo phân hệ">
        <a class="atabs__item<?= $loc['nhom'] === '' ? ' is-active' : '' ?>"
           href="<?= e($duongDan(['nhom' => '', 'page' => ''])) ?>"
           <?= $loc['nhom'] === '' ? 'aria-current="true"' : '' ?>>
            Tất cả <span class="atabs__num"><?= (int) ($demNhom[''] ?? 0) ?></span>
        </a>
        <?php foreach (AuditLogModel::NHOM as $khoa => $n): ?>
            <a class="atabs__item<?= $loc['nhom'] === $khoa ? ' is-active' : '' ?>"
               href="<?= e($duongDan(['nhom' => $khoa, 'page' => ''])) ?>"
               <?= $loc['nhom'] === $khoa ? 'aria-current="true"' : '' ?>>
                <?= e($n['nhan']) ?>
                <span class="atabs__num"><?= (int) ($demNhom[$khoa] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php /* ── Bộ lọc chi tiết ────────────────────────────────────────────────
             Form GET, không JS: gõ xong Enter là địa chỉ đổi — chia sẻ được,
             quay lại được, F5 không hỏi gửi lại dữ liệu. Đúng ràng buộc "tắt
             JavaScript thì mọi luồng vẫn chạy" của dự án. */ ?>
    <form class="aform" method="get" action="/quan-tri/nhat-ky">
        <?php if ($loc['nhom'] !== ''): ?>
            <input type="hidden" name="nhom" value="<?= e($loc['nhom']) ?>">
        <?php endif; ?>
        <?php if ($loc['q'] !== ''): ?>
            <input type="hidden" name="q" value="<?= e($loc['q']) ?>">
        <?php endif; ?>

        <div class="aform__grid">
            <div class="field">
                <label for="f-nguoi">Người thực hiện</label>
                <select id="f-nguoi" name="nguoi">
                    <option value="">Tất cả</option>
                    <?php foreach ($nguoiList as $ng): ?>
                        <option value="<?= e((string) $ng['actor_id']) ?>"
                            <?= $loc['actor'] === (string) $ng['actor_id'] ? 'selected' : '' ?>>
                            <?= e((string) ($ng['ten'] ?? 'Không rõ tên')) ?>
                            (<?= (int) $ng['so_luot'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="f-hanh-dong">Hành động</label>
                <select id="f-hanh-dong" name="hanh-dong">
                    <option value="">Tất cả</option>
                    <?php foreach (AuditLogModel::ACTIONS as $ma => $nhan): ?>
                        <?php /* Chỉ liệt kê hành động thuộc nhóm đang chọn — bày
                                 cả 20 mã trong khi đang lọc nhóm Tồn kho thì
                                 hầu hết lựa chọn bấm vào là ra bảng rỗng. */ ?>
                        <?php if ($loc['nhom'] !== '' && AuditLogModel::nhomCua($ma) !== $loc['nhom']) {
                            continue;
                        } ?>
                        <option value="<?= e($ma) ?>" <?= $loc['action'] === $ma ? 'selected' : '' ?>>
                            <?= e($nhan) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="f-tu">Từ ngày</label>
                <input type="date" id="f-tu" name="tu" value="<?= e($loc['tu']) ?>">
            </div>

            <div class="field">
                <label for="f-den">Đến ngày</label>
                <input type="date" id="f-den" name="den" value="<?= e($loc['den']) ?>">
            </div>

            <?php /* .field--wide: ô này chỉ chứa một cái nút, để nó co theo
                     lưới như bốn ô trên thì nút bị kéo dài bằng cả cột. Lớp
                     --wide đã có sẵn trong admin.css nên không phải thêm CSS
                     mới cho riêng màn này. */ ?>
            <div class="field field--wide">
                <button type="submit" class="astatus__save">Lọc</button>
            </div>
        </div>
    </form>

    <?php if ($logs === []): ?>
        <p class="apanel__empty">
            <?= $dangLoc
                ? 'Không có thao tác nào khớp bộ lọc.'
                : 'Chưa ghi nhận thao tác nào.' ?>
        </p>
    <?php else: ?>
        <div class="atable-wrap">
            <table class="atable">
                <thead>
                    <tr>
                        <th scope="col">Thời điểm</th>
                        <th scope="col">Người thực hiện</th>
                        <th scope="col">Hành động</th>
                        <th scope="col">Khách hàng</th>
                        <th scope="col">Chi tiết</th>
                        <th scope="col">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <?php $nhomCua = AuditLogModel::nhomCua((string) $l['action']); ?>
                        <tr>
                            <td>
                                <?= e(formatDate((string) $l['created_at'], 'd/m/Y')) ?>
                                <span class="atable__sub"><?= e(date('H:i', strtotime((string) $l['created_at']))) ?></span>
                            </td>

                            <td>
                                <?php /* actor_id NULL nghĩa là không có phiên quản
                                         trị nào lúc ghi — webhook SePay chạy, hoặc
                                         chính khách tự thao tác. Ghi "Hệ thống"
                                         chứ không để trống: ô trống đọc ra là
                                         "mất dữ liệu", còn đây là một sự thật. */ ?>
                                <?= e((string) ($l['actor_name'] ?? 'Hệ thống')) ?>
                            </td>

                            <td>
                                <?= e(AuditLogModel::ACTIONS[$l['action']] ?? (string) $l['action']) ?>
                                <span class="atable__sub">
                                    <?= e(AuditLogModel::NHOM[$nhomCua]['nhan'] ?? 'Khác') ?>
                                </span>
                            </td>

                            <td>
                                <?php if (($l['khach_ten'] ?? null) !== null || ($l['khach_sdt'] ?? null) !== null): ?>
                                    <?php /* Bấm vào tên là mở thẳng hồ sơ khách —
                                             câu hỏi tiếp theo sau khi đọc một dòng
                                             vết gần như luôn là "khách này là ai". */ ?>
                                    <a href="/quan-tri/khach-hang/<?= e((string) $l['user_id']) ?>">
                                        <?= e((string) ($l['khach_ten'] ?? 'Khách hàng')) ?>
                                    </a>
                                    <?php if (($l['khach_sdt'] ?? null) !== null): ?>
                                        <span class="atable__sub"><?= e((string) $l['khach_sdt']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="atable__sub">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="atable__msg"><?= e((string) ($l['detail'] ?? '')) ?></td>

                            <td><span class="atable__sub"><?= e((string) ($l['ip'] ?? '—')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="apanel__foot">
                <p class="apanel__more">
                    <?= number_format((int) $total, 0, ',', '.') ?> thao tác
                    <?php if ($totalPages > 1): ?>· trang <?= (int) $page ?>/<?= (int) $totalPages ?><?php endif; ?>
                </p>

                <?php if ($totalPages > 1): ?>
                    <nav class="pager" aria-label="Phân trang">
                        <?php
                        /* Bảng vết dài hàng nghìn trang nên KHÔNG in hết số
                           trang như các màn khác — in ra 2000 thẻ <a> thì
                           thanh phân trang dài hơn cả bảng. Chỉ hiện cửa sổ
                           quanh trang hiện tại, kèm hai đầu. */
                        $tu  = max(1, $page - 2);
                        $den = min($totalPages, $page + 2);
                        $moc = array_unique(array_merge([1], range($tu, $den), [$totalPages]));
                        sort($moc);
                        $truoc = 0;
                        ?>
                        <?php foreach ($moc as $i): ?>
                            <?php if ($truoc > 0 && $i > $truoc + 1): ?>
                                <span class="pager__link" aria-hidden="true">…</span>
                            <?php endif; ?>
                            <?php if ($i === $page): ?>
                                <span class="pager__link is-current" aria-current="page"><?= (int) $i ?></span>
                            <?php else: ?>
                                <a class="pager__link"
                                   href="<?= e($duongDan(['page' => $i > 1 ? (string) $i : ''])) ?>"><?= (int) $i ?></a>
                            <?php endif; ?>
                            <?php $truoc = $i; ?>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
