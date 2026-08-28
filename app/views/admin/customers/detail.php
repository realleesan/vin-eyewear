<?php

/**
 * admin/customers/detail.php — hồ sơ một khách hàng, bốn tab.
 *
 * Controller: Admin/CustomerAdminController::show()
 *
 * File này chỉ dựng KHUNG: tiêu đề, dải số liệu, thanh tab. Nội dung từng tab
 * nằm ở _tab-*.php ngay cạnh — mỗi tab một file vì gộp cả bốn vào đây thì file
 * dài hơn tám trăm dòng và không ai tìm nổi cái mình cần sửa.
 *
 * Controller CHỈ NẠP DỮ LIỆU CỦA TAB ĐANG MỞ, nên biến của tab khác không tồn
 * tại ở đây. Đó là lý do khối switch bên dưới require đúng một file.
 */

$daXoa   = $khach['deleted_at'] !== null;
$daKhoa  = $khach['status'] === 'locked';
$ten     = $khach['full_name'] ?: '(chưa đặt tên)';
$duongDan = '/quan-tri/khach-hang/' . rawurlencode($khach['id']);
?>

<?php /* Đường quay lại đứng TRÊN tiêu đề, không nằm trong cụm nút bên phải:
         nó không phải một hành động trên khách hàng này mà là lối ra khỏi
         trang, và mắt tìm lối ra ở góc trên bên trái. */ ?>
<p class="acus__back">
    <a href="/quan-tri/khach-hang"><?= icon('arrow-left', '', 14) ?> Danh sách khách hàng</a>
</p>

<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title"><?= e($ten) ?></h1>
        <p class="ahead__lead">
            <?php if ($daXoa): ?>
                <span class="badge badge--cancelled">Đã xoá</span>
                <?= e(formatDate($khach['deleted_at'], 'd/m/Y H:i')) ?>
            <?php elseif ($daKhoa): ?>
                <span class="badge badge--out_of_stock">Đã khoá</span>
                <?php if ($khach['locked_at'] !== null): ?>
                    từ <?= e(formatDate($khach['locked_at'], 'd/m/Y')) ?>
                <?php endif; ?>
            <?php else: ?>
                <span class="badge badge--in_stock">Hoạt động</span>
            <?php endif; ?>
            · Khách từ <?= e(formatDate($khach['created_at'], 'd/m/Y')) ?>
        </p>
    </div>
</header>

<?php if ($daKhoa && !$daXoa): ?>
    <?php /* LÝ DO KHOÁ HIỆN TO NGAY DƯỚI TIÊU ĐỀ, không giấu trong tab Hồ sơ.

             Người mở hồ sơ này gần như luôn đang cầm điện thoại nghe khách hỏi
             "vì sao tôi không đăng nhập được". Câu trả lời phải nằm ở chỗ mắt
             chạm đầu tiên, không phải sau một cú bấm nữa. */ ?>
    <div class="anote anote--alert" role="status">
        <p><strong>Tài khoản đang bị khoá.</strong> <?= e((string) $khach['locked_reason']) ?></p>
        <?php if ($khach['locked_by_name'] !== null): ?>
            <p>Khoá bởi <?= e($khach['locked_by_name']) ?><?= $khach['locked_at'] !== null
                ? ' lúc ' . e(formatDate($khach['locked_at'], 'd/m/Y H:i')) : '' ?>.</p>
        <?php endif; ?>
        <p>Khách chỉ thấy dòng “Tài khoản đã bị khoá. Vui lòng liên hệ cửa hàng.” — họ không đọc được lý do trên.</p>
    </div>
<?php endif; ?>

<?php /* DẢI SỐ LIỆU ĐỨNG TRÊN THANH TAB, tức là nó ở NGOÀI mọi tab.

         Ba con số này là thứ trả lời câu "khách này quan trọng tới đâu", và câu
         đó cần trả lời dù đang đứng ở tab nào. Đẩy chúng vào tab Hoạt động thì
         người đang đọc sổ địa chỉ không còn biết mình đang đọc của ai. */ ?>
<ul class="astats" role="list">
    <li>
        <div class="astat">
            <span class="astat__label">Tổng chi tiêu</span>
            <span class="astat__value"><?= e(money($stats['tong_tien'])) ?></span>
            <span class="astat__note">không tính đơn đã huỷ</span>
        </div>
    </li>
    <li>
        <div class="astat">
            <span class="astat__label">Số đơn</span>
            <span class="astat__value"><?= (int) $stats['so_don'] ?></span>
            <span class="astat__note">
                <?= $stats['don_gan_nhat'] !== null
                    ? 'gần nhất ' . e(formatDate($stats['don_gan_nhat'], 'd/m/Y'))
                    : 'chưa mua lần nào' ?>
            </span>
        </div>
    </li>
    <li>
        <div class="astat">
            <span class="astat__label">Đăng nhập gần nhất</span>
            <span class="astat__value astat__value--nho">
                <?= $khach['last_login_at'] !== null
                    ? e(formatDate($khach['last_login_at'], 'd/m/Y'))
                    : '—' ?>
            </span>
            <span class="astat__note">
                <?= $khach['last_login_at'] !== null
                    ? e(formatDate($khach['last_login_at'], 'H:i'))
                    : 'chưa đăng nhập lần nào' ?>
            </span>
        </div>
    </li>
</ul>

<?php /* THANH TAB LÀ BỐN ĐƯỜNG DẪN THẬT, không phải nút JavaScript.

         Không có file JS nào thì trang vẫn đủ bốn tab — đúng quy ước "JS chỉ
         là tăng cường" của dự án. Và vì tab nằm trên địa chỉ, mọi form bên
         trong sau khi POST xong đều quay về đúng tab vừa đứng; làm bằng JS thì
         cứ lưu xong là bật về tab đầu tiên. */ ?>
<nav class="atabs" aria-label="Nội dung hồ sơ khách hàng">
    <?php foreach ($tabs as $key => $label): ?>
        <?php
        // Tab đơn thuốc chỉ hiện với vai trò quản trị. Ẩn ở đây là chuyện GỌN
        // MẮT; chặn thật nằm ở controller (canRx) — xem CLAUDE.md quy tắc 4.
        if ($key === 'don-thuoc' && !$canRx) {
            continue;
        }
        $dangMo = $tab === $key;
        ?>
        <a class="atabs__item<?= $dangMo ? ' is-active' : '' ?>"
           href="<?= e($duongDan) ?>?tab=<?= e(rawurlencode($key)) ?>"
           <?= $dangMo ? 'aria-current="true"' : '' ?>>
            <?= e($label) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php
/*
 * Nạp đúng file của tab đang mở.
 *
 * require thẳng chứ không partial(): partial() trích biến từ mảng truyền vào,
 * mà ở đây các tab cần rất nhiều biến khác nhau ($khach, $addresses,
 * $rxRecords, $activity…). Liệt kê lại từng cái cho mỗi tab là một danh sách
 * dài sẽ lệch với thực tế ngay lần sửa đầu tiên. require giữ nguyên phạm vi
 * biến của file này, nên tab nào cần gì thì dùng thẳng cái đó.
 */
/* $tab ĐÃ ĐƯỢC ĐỐI CHIẾU với CustomerAdminController::TABS trước khi tới đây,
   nên nó chỉ có thể là một trong bốn chuỗi gõ sẵn. Đừng bỏ bước đối chiếu ấy:
   ghép thẳng ?tab= vào đường dẫn file là mở đường cho '../../..'. */
require __DIR__ . '/_tab-' . $tab . '.php';
