<?php

/**
 * admin/waitlist/index.php — danh sách khách đang chờ hàng về.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ NƠI MỘT LỜI HỨA ĐƯỢC GIỮ
 *
 * Khách bấm "Thông báo khi có hàng" ở trang sản phẩm và tin rằng sẽ có người
 * gọi. Hosting hiện tại không gửi được email (xem WaitlistModel), nên người
 * gọi là NHÂN VIÊN, và bảng này là thứ duy nhất cho họ biết phải gọi cho ai.
 *
 * Vì thế bảng sắp CŨ NHẤT TRƯỚC, ngược với mọi bảng khác trong khu quản trị:
 * người chờ lâu nhất là người đáng được gọi đầu tiên.
 *
 * Cột "Tồn hiện tại" đứng cạnh tên hàng để trả lời ngay câu hỏi thật của người
 * đang nhìn bảng: món này về chưa? Còn 0 thì chưa có gì để gọi.
 * ─────────────────────────────────────────────────────────────────────────────
 */
?>
<div class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Chờ hàng</h1>
        <p class="ahead__lead">
            <?= (int) $dangCho ?> người đang chờ.
            Hosting chưa gửi được email nên hãy gọi hoặc nhắn Zalo, xong bấm “Đã báo”.
        </p>
    </div>

</div>

<?php /* Hai viên lọc dùng ĐÚNG bộ lớp của trang Tồn kho (.atabs). Không đặt
         lớp mới: hai màn này cạnh nhau trên thanh điều hướng và làm cùng một
         việc lọc, khác dáng là người dùng phải học lại. */ ?>
<nav class="atabs" aria-label="Lọc danh sách chờ">
    <a class="atabs__item<?= $tatCa ? '' : ' is-active' ?>" href="/quan-tri/cho-hang">
        Đang chờ <span class="atabs__num"><?= (int) $dangCho ?></span>
    </a>
    <a class="atabs__item<?= $tatCa ? ' is-active' : '' ?>" href="/quan-tri/cho-hang?loc=tat-ca">
        Tất cả
    </a>
</nav>

<?php /* BẢNG THIẾU KHÁC HẲN DANH SÁCH RỖNG — phải kiểm TRƯỚC.

         Cả hai đều cho $rows === [], nhưng "chưa có ai đăng ký" là tin tốt còn
         "chưa có bảng" là việc phải làm ngay: nút chờ hàng ở trang bán hàng
         đang từ chối khách. Gộp hai thứ vào một câu là giấu mất cái thứ hai.
         Cùng cách làm với màn Nhật ký thao tác. */ ?>
<?php if (!$coBang): ?>
    <p class="apanel__empty">
        Chưa có bảng danh sách chờ trong cơ sở dữ liệu. Chạy
        <code>sudo bash database/migrate.sh</code> rồi mở lại trang này.
        Tới lúc đó nút “Thông báo khi có hàng” ở trang sản phẩm cũng đang tạm ngưng.
    </p>
<?php elseif ($rows === []): ?>
    <p class="ahead__note">Chưa có ai đăng ký chờ hàng.</p>
<?php else: ?>
<div class="atable-wrap">
    <table class="atable achtable">
        <thead>
            <tr>
                <th scope="col">Sản phẩm</th>
                <th scope="col">Phương án</th>
                <th scope="col">Tồn hiện tại</th>
                <th scope="col">Liên lạc</th>
                <th scope="col">Đăng ký lúc</th>
                <th scope="col">Trạng thái</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                /* Tồn của ĐÚNG thứ khách chờ: biến thể nếu có, không thì mặt
                   hàng. Hiện tồn của cả mặt hàng cho một người chờ màu đen là
                   trả lời sai câu họ hỏi. */
                $ton = $r['variant_id'] !== null
                    ? (int) ($r['variant_stock'] ?? 0)
                    : (int) ($r['stock_quantity'] ?? 0);
                ?>
                <tr>
                    <td>
                        <a href="/san-pham/<?= e(rawurlencode($r['product_slug'])) ?>"
                           target="_blank" rel="noopener"><?= e($r['product_name']) ?></a>
                        <span class="atable__sub"><?= e($r['sku']) ?></span>
                    </td>
                    <td><?= e($r['variant_label'] ?? '—') ?></td>
                    <td class="num">
                        <?php /* Hàng đã về thì đây là dòng cần gọi NGAY. Gắn thêm
                                 một .atag — lớp huy hiệu dùng chung của khu quản
                                 trị — để mắt bắt được giữa một bảng toàn số 0,
                                 mà không phải đẻ ra một lớp mới chỉ dùng ở đây. */ ?>
                        <?= $ton ?><?php if ($ton > 0): ?><span class="atag">đã về</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($r['phone'])): ?>
                            <a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a>
                        <?php endif; ?>
                        <?php if (!empty($r['email'])): ?>
                            <span class="atable__sub"><?= e($r['email']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(formatDate($r['created_at'], 'd/m/Y H:i')) ?></td>
                    <td>
                        <?php if ($r['notified_at'] !== null): ?>
                            <span class="atable__sub">Đã báo <?= e(formatDate($r['notified_at'], 'd/m/Y')) ?></span>
                        <?php else: ?>
                            <?php /* .arow-btn CHỈ ăn kiểu khi nằm trong .arow-actions
                                     — xem admin.css. Bọc đúng khuôn ấy thay vì bịa
                                     một lớp nút mới cho một cái nút. */ ?>
                            <div class="arow-actions">
                                <form method="post" action="/quan-tri/cho-hang/da-bao">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                                    <input type="hidden" name="loc" value="<?= $tatCa ? 'tat-ca' : '' ?>">
                                    <button type="submit" class="arow-btn">Đã báo</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
