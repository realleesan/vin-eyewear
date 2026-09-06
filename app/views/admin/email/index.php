<?php

/**
 * admin/email/index.php — hàng chờ và sổ kết quả gửi thư. FR-EM-05.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DÒNG QUAN TRỌNG NHẤT TRÊN MÀN NÀY LÀ DẢI CẢNH BÁO ĐẦU TRANG
 *
 * Hosting hiện tại không gửi được thư nào — mail() bị vô hiệu hoá và cổng SMTP
 * ra ngoài bị chặn. Nghĩa là cột "Đang chờ gửi" sẽ dài mãi, và người mở trang
 * lần đầu chắc chắn kết luận hệ thống hỏng.
 *
 * Nó không hỏng. Nó đang làm đúng thứ đã thiết kế: giữ nguyên mọi lá ở 'cho'
 * cho tới ngày có đường gửi thật, rồi cả hàng chờ tự chảy đi. Câu ấy phải nằm
 * ngay trên bảng, không nằm trong tài liệu.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BẢNG SẮP 'HỎNG' VÀ 'CHỜ' LÊN ĐẦU — xem EmailQueueModel::danhSach().
 *
 * Sắp theo thời gian thuần sẽ đẩy một lá hỏng từ tuần trước xuống dưới đống
 * thư của hôm nay, mà lá hỏng mới là dòng duy nhất trên bảng có việc để làm.
 * ─────────────────────────────────────────────────────────────────────────────
 */
?>
<div class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Hàng chờ thư</h1>
        <p class="ahead__lead">
            Mọi lá thư hệ thống soạn cho khách đều đi qua đây. Nội dung dựng sẵn
            lúc sự kiện xảy ra, nên mở một lá cũ là đọc đúng thứ khách đã nhận.
        </p>
    </div>
</div>

<?php if (!$coBang): ?>
    <p class="apanel__empty">
        Chưa có bảng hàng chờ thư trong cơ sở dữ liệu. Chạy
        <code>sudo bash database/migrate.sh</code> rồi mở lại trang này.
        Tới lúc đó hệ thống không soạn lá thư nào — mọi việc khác vẫn chạy bình thường.
    </p>
<?php else: ?>

<?php /* ĐƯỜNG GỬI CHƯA CÓ — nói ngay, và nói rõ đây là trạng thái BÌNH THƯỜNG
         của hosting hiện tại chứ không phải sự cố. Xem khối đầu file. */ ?>
<?php /* .anote--alert, KHÔNG phải .apanel__empty.

         Hai lớp nói hai điều khác hẳn nhau: .apanel__empty là chữ xám nhạt
         canh giữa, dáng của "ở đây không có gì" — đúng cho dải "chưa có bảng"
         ở trên, sai hoàn toàn cho dải này. Đây là câu quan trọng nhất trên cả
         màn hình (xem khối đầu file), và nó cũng là chỗ duy nhất trong khu
         quản trị có <code> trong một dải cảnh báo — .anote--alert code đã có
         sẵn kiểu cho đúng việc ấy. */ ?>
<?php if (!$guiDuoc): ?>
    <div class="anote anote--alert">
        <p>
            <strong>Chưa có đường gửi thư.</strong>
            Cấu hình hiện tại là <code>MAIL_DRIVER=<?= e($mailDriver) ?></code>, và hosting
            miễn phí chặn cả <code>mail()</code> lẫn cổng SMTP ra ngoài.
            Hệ thống vì thế KHÔNG thử gửi lá nào — thử là mỗi lượt truy cập lại đẩy
            thêm vài lá sang “Gửi hỏng” vì một nguyên nhân duy nhất.
            Thư nằm nguyên ở “Đang chờ gửi”; ngày cửa hàng nối được đường gửi, cả
            hàng chờ tự chảy đi, không mất lá nào.
        </p>
    </div>
<?php endif; ?>

<?php /* Dải viên lọc dùng ĐÚNG bộ lớp .atabs của trang Tồn kho và Chờ hàng —
         ba màn cạnh nhau làm cùng một việc lọc, khác dáng là bắt học lại. */ ?>
<nav class="atabs" aria-label="Lọc theo trạng thái gửi">
    <a class="atabs__item<?= $loc === '' ? ' is-active' : '' ?>" href="/quan-tri/email">
        Tất cả <span class="atabs__num"><?= (int) ($dem[''] ?? 0) ?></span>
    </a>
    <?php foreach (EmailQueueModel::TRANG_THAI as $ma => $nhan): ?>
        <a class="atabs__item<?= $loc === $ma ? ' is-active' : '' ?>"
           href="/quan-tri/email?loc=<?= e(rawurlencode($ma)) ?>">
            <?= e($nhan) ?> <span class="atabs__num"><?= (int) ($dem[$ma] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($rows === []): ?>
    <p class="ahead__note">Không có lá thư nào ở mục này.</p>
<?php else: ?>
<div class="atable-wrap">
    <table class="atable">
        <thead>
            <tr>
                <th scope="col">Người nhận</th>
                <th scope="col">Nội dung</th>
                <th scope="col">Xếp hàng lúc</th>
                <th scope="col">Trạng thái</th>
                <th scope="col">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                /* Bốn trạng thái mượn bốn viên thuốc CÓ SẴN của khu quản trị,
                   không đẻ lớp mới. Ánh xạ theo NGHĨA chứ không theo tên:
                   'cho' là đang đợi (vàng), 'xong' là xong việc (xanh), 'hong'
                   là hỏng (đỏ), 'bo' là thôi không làm nữa (xám). */
                $vien = [
                    'cho'  => 'badge--pending',
                    'xong' => 'badge--completed',
                    'hong' => 'badge--cancelled',
                    'bo'   => 'badge--neutral',
                ][$r['trang_thai']] ?? 'badge--neutral';
                ?>
                <tr>
                    <td>
                        <?= e($r['nguoi_nhan']) ?>
                        <span class="atable__sub"><?= e($r['loai']) ?></span>
                    </td>
                    <td>
                        <?php /* Tiêu đề là liên kết mở ngăn kéo: nó là thứ
                                 người ta thật sự muốn bấm vào, và nó dài nên
                                 dễ trúng hơn một nút "Xem" bé tí ở cột cuối. */ ?>
                        <a href="/quan-tri/email?<?= e(http_build_query(array_filter([
                            'loc'  => $loc,
                            'page' => $page > 1 ? (string) $page : '',
                            'xem'  => $r['id'],
                        ], static fn (string $v): bool => $v !== ''))) ?>"
                           data-modal><?= e($r['subject']) ?></a>
                    </td>
                    <td><?= e(formatDate((string) $r['created_at'], 'd/m/Y H:i')) ?></td>
                    <td>
                        <span class="badge <?= $vien ?>">
                            <?= e(EmailQueueModel::TRANG_THAI[$r['trang_thai']] ?? $r['trang_thai']) ?>
                        </span>
                        <?php if ((int) $r['so_lan_thu'] > 0): ?>
                            <span class="atable__sub">Đã thử <?= (int) $r['so_lan_thu'] ?> lần</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php /* THƯ ĐÃ GỬI THÌ KHÔNG CÓ NÚT NÀO. Cả hai thao
                                 tác đều bị model từ chối với trạng thái 'xong'
                                 — vẽ nút ra là mời bấm một thứ sẽ báo lỗi. */ ?>
                        <?php if ($r['trang_thai'] !== 'xong'): ?>
                            <?php
                            /* CẢ HAI NÚT ĐỀU HỎI LẠI, và cả hai đều có lối
                               dự phòng onsubmit cho trường hợp tắt JS —
                               đúng bộ thuộc tính mà mười ba màn khác trong
                               khu quản trị đang dùng (xem stores/index.php).

                               "Gửi lại" cũng hỏi, không chỉ "Bỏ": nó gửi dữ
                               liệu của một khách đi thêm một lần nữa, và nếu
                               địa chỉ nhận sai thì lần nữa là sai thêm lần
                               nữa. */
                            $hoiGui = sprintf('Gửi lại thư này tới %s?', $r['nguoi_nhan']);
                            $hoiBo  = 'Bỏ lá thư này? Khách sẽ không bao giờ nhận được nó.';
                            ?>
                            <div class="arow-actions">
                                <form method="post" action="/quan-tri/email/gui-lai"
                                      data-confirm="<?= e($hoiGui) ?>"
                                      data-confirm-title="Gửi lại thư?"
                                      data-confirm-ok="Gửi lại"
                                      onsubmit="return confirm('<?= e($hoiGui) ?>')">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                                    <input type="hidden" name="loc" value="<?= e($loc) ?>">
                                    <input type="hidden" name="page" value="<?= (int) $page ?>">
                                    <button type="submit" class="arow-btn">Gửi lại</button>
                                </form>
                                <?php if ($r['trang_thai'] !== 'bo'): ?>
                                    <form method="post" action="/quan-tri/email/bo"
                                          data-confirm="<?= e($hoiBo) ?>"
                                          data-confirm-title="Bỏ lá thư?"
                                          data-confirm-ok="Bỏ"
                                          onsubmit="return confirm('<?= e($hoiBo) ?>')">
                                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                                        <input type="hidden" name="loc" value="<?= e($loc) ?>">
                                        <input type="hidden" name="page" value="<?= (int) $page ?>">
                                        <button type="submit" class="arow-btn">Bỏ</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php /* CHÂN BẢNG — cùng bộ lớp và cùng lối phân trang với màn Lịch sử
             thao tác. Câu bên trái phải nói TỔNG THẬT: bảng này bị cắt còn 50
             dòng một trang, và một danh sách bị cắt mà không nói ra thì người
             đọc tưởng mình đang nhìn tất cả. */ ?>
    <div class="apanel__foot">
        <p class="apanel__more">
            <?= number_format((int) $total, 0, ',', '.') ?> lá thư
            <?php if ($totalPages > 1): ?>· trang <?= (int) $page ?>/<?= (int) $totalPages ?><?php endif; ?>
        </p>

        <?php if ($totalPages > 1): ?>
            <nav class="pager" aria-label="Phân trang">
                <?php
                /* Chỉ hiện cửa sổ quanh trang hiện tại kèm hai đầu — sổ thư
                   dài hàng nghìn trang, in hết số trang thì thanh phân trang
                   dài hơn cả bảng. Cùng cách làm với màn Lịch sử thao tác. */
                $tu    = max(1, $page - 2);
                $den   = min($totalPages, $page + 2);
                $moc   = array_unique(array_merge([1], range($tu, $den), [$totalPages]));
                sort($moc);
                $truoc = 0;

                $duongDan = static function (int $i) use ($loc): string {
                    $t = array_filter(
                        ['loc' => $loc, 'page' => $i > 1 ? (string) $i : ''],
                        static fn (string $v): bool => $v !== ''
                    );

                    return '/quan-tri/email' . ($t !== [] ? '?' . http_build_query($t) : '');
                };
                ?>
                <?php foreach ($moc as $i): ?>
                    <?php if ($truoc > 0 && $i > $truoc + 1): ?>
                        <span class="pager__link" aria-hidden="true">…</span>
                    <?php endif; ?>
                    <?php if ($i === $page): ?>
                        <span class="pager__link is-current" aria-current="page"><?= (int) $i ?></span>
                    <?php else: ?>
                        <a class="pager__link" href="<?= e($duongDan((int) $i)) ?>"><?= (int) $i ?></a>
                    <?php endif; ?>
                    <?php $truoc = $i; ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
/* ─────────────────────────────────────────────────────────────────────────────
   NGĂN KÉO ĐỌC MỘT LÁ THƯ — mở bằng ?xem=<id>.

   CHỈ ĐỌC, không có nút Lưu: hai thao tác duy nhất trên một lá thư nằm ở nút
   trong bảng, và cả hai đều là POST có hậu quả. Gọi modal-foot không kèm
   $luuForm thì nó chỉ đẻ ra nút Đóng — xem khối chú thích trong file ấy.

   RUỘT THƯ IN BẰNG <iframe srcdoc>, KHÔNG in thẳng và cũng không e().

   Ruột thư là HTML thật, có bảng và màu, và người mở ngăn kéo cần thấy đúng
   thứ khách thấy. Nhưng nhả nó thẳng vào trang quản trị là cho một chuỗi
   trong CSDL chạy trong cùng ngữ cảnh với phiên đăng nhập của quản trị viên —
   biến thư thành một lối XSS, và thư thì mang tên khách tự nhập.

   iframe có sandbox rỗng thì HTML vẫn vẽ ra đúng nhưng không chạy được script
   nào, không gửi được form, không rời trang được. e() bên trong srcdoc là để
   chuỗi không phá vỡ chính thuộc tính đó.
   ───────────────────────────────────────────────────────────────────────────── */
?>
<?php if ($xem !== null): ?>
    <?php
    // Đóng ngăn kéo là về ĐÚNG trang và đúng viên lọc đang đứng, không về đầu sổ.
    $dongUrl = '/quan-tri/email';
    $thamVe  = array_filter(
        ['loc' => $loc, 'page' => $page > 1 ? (string) $page : ''],
        static fn (string $v): bool => $v !== ''
    );

    if ($thamVe !== []) {
        $dongUrl .= '?' . http_build_query($thamVe);
    }
    ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => 'Thư gửi ' . $xem['nguoi_nhan'],
        'phu'     => 'Mã sự kiện: ' . $xem['loai'],
        'dongUrl' => $dongUrl,
        'rong'    => 'lg',
    ]); ?>

        <dl class="arefund__facts">
            <div>
                <dt>Trạng thái</dt>
                <dd><?= e(EmailQueueModel::TRANG_THAI[$xem['trang_thai']] ?? $xem['trang_thai']) ?></dd>
            </div>
            <div>
                <dt>Xếp hàng lúc</dt>
                <dd><?= e(formatDate((string) $xem['created_at'], 'd/m/Y H:i')) ?></dd>
            </div>
            <div>
                <dt>Số lần đã thử</dt>
                <dd><?= (int) $xem['so_lan_thu'] ?> / <?= EmailQueueModel::THU_TOI_DA ?></dd>
            </div>
            <div>
                <dt>Gửi lúc</dt>
                <dd><?= $xem['gui_luc'] !== null
                        ? e(formatDate((string) $xem['gui_luc'], 'd/m/Y H:i')) : '—' ?></dd>
            </div>
        </dl>

        <?php /* LÝ DO HỎNG là thứ FR-EM-05 gọi đích danh. Nó tới từ máy chủ
                 thư nên có thể dài và khó đọc, nhưng chép nguyên văn vẫn đúng
                 hơn tóm tắt: người đi sửa cấu hình SMTP cần chính câu ấy. */ ?>
        <?php if ($xem['loi_gan_nhat'] !== null && $xem['loi_gan_nhat'] !== ''): ?>
            <p class="arefund__done">Lý do hỏng gần nhất: <?= e($xem['loi_gan_nhat']) ?></p>
        <?php endif; ?>

        <p class="amodal__sub">Tiêu đề: <?= e($xem['subject']) ?></p>

        <?php /* `sandbox` KHÔNG CÓ GIÁ TRỊ — và đừng bao giờ thêm vào.

                 Thuộc tính rỗng là bộ hạn chế ĐẦY ĐỦ: không script, không
                 form, không điều hướng trang cha, gốc mờ. Thêm
                 "allow-scripts allow-same-origin" vào đây là biến mọi ruột
                 thư đã lưu thành một lỗ XSS chạy trong phiên của quản trị
                 viên. Lý do dài ở khối chú thích ngay trên. */ ?>
        <iframe class="amail__xem" sandbox srcdoc="<?= e($xem['body']) ?>"
                title="Nội dung thư"></iframe>

    <?php partial('admin/_layout/modal-foot', ['dongUrl' => $dongUrl]); ?>
<?php endif; ?>
