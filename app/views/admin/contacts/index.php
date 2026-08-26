<?php

/**
 * admin/contacts/index.php — yêu cầu liên hệ.
 *
 * Controller: Admin/ContactAdminController::index()
 *
 * KHÔNG CÒN Ô CHỌN TRẠNG THÁI và không còn dải lọc — bỏ ngày 2026-08-26. Lý do
 * đầy đủ ở đầu controller; ngắn gọn: đó là hàng chờ không ai canh, còn việc
 * theo dõi thật nằm trong cuộc trò chuyện Zalo với CSKH.
 *
 * Cột cuối nay là "Zalo CSKH": tin đã tới chưa, và nút đẩy lại nếu chưa.
 */
?>
<header class="ahead">
    <h1 class="ahead__title">Yêu cầu liên hệ</h1>
    <p class="ahead__lead">
        <?= (int) $total ?> yêu cầu<?= $totalPages > 1 ? ' · trang ' . (int) $page . '/' . (int) $totalPages : '' ?>
    </p>
</header>

<?php if ($coCotZalo && $chuaDay > 0): ?>
    <?php /* DẢI CẢNH BÁO ĐỨNG TRÊN BẢNG, không phải một con số nhỏ trong tiêu đề.

             Một yêu cầu chưa đẩy được nghĩa là có người thật đang chờ gọi lại
             mà CSKH chưa biết là có ai chờ. Đó là hỏng hóc duy nhất trang này
             còn báo được, nên nó phải to bằng mức nghiêm trọng của nó — và
             bình thường thì khối này không tồn tại. */ ?>
    <div class="anote anote--alert" role="alert">
        <p>
            <strong><?= (int) $chuaDay ?> yêu cầu chưa tới được Zalo CSKH.</strong>
            Khách đang chờ gọi lại mà đầu bên kia chưa biết.
        </p>
        <p>
            Gần như luôn là do chưa khai <code>ZALO_ZNS_TEMPLATE_CONTACT</code> hoặc
            token OA hết hạn. Bấm <strong>Gửi sang Zalo</strong> ở từng dòng để đẩy lại.
        </p>
    </div>
<?php endif; ?>

<?php if (!$coCotZalo): ?>
    <div class="anote anote--alert">
        <p>
            <strong>Chưa ghi nhận được việc đẩy Zalo.</strong> Bảng
            <code>contact_requests</code> còn thiếu cột <code>zalo_sent_at</code>,
            nên không biết yêu cầu nào đã tới tay CSKH.
        </p>
        <p>Chạy <code>database/migrations/2026-08-26-lien-he-qua-zalo.sql</code>.</p>
    </div>
<?php endif; ?>

<?php if ($contacts === []): ?>
    <p class="apanel__empty">Chưa có yêu cầu liên hệ nào.</p>
<?php else: ?>
    <div class="atable-wrap">
        <table class="atable atable--full">
            <thead>
                <tr>
                    <th scope="col">Gửi lúc</th>
                    <th scope="col">Người gửi</th>
                    <th scope="col">Nội dung</th>
                    <th scope="col">Zalo CSKH</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contacts as $c): ?>
                    <?php $daDay = $coCotZalo && ($c['zalo_sent_at'] ?? null) !== null; ?>
                    <tr>
                        <td><?= e(formatDate($c['created_at'], 'd/m/Y H:i')) ?></td>

                        <td>
                            <?= e($c['full_name']) ?>
                            <?php /* Số điện thoại là đường trả lời chính — bấm được
                                     để gọi thẳng từ máy ở quầy. */ ?>
                            <span class="atable__sub">
                                <a href="tel:<?= e(preg_replace('/\D/', '', $c['phone'])) ?>"><?= e($c['phone']) ?></a>
                            </span>
                            <?php if (!empty($c['email'])): ?>
                                <span class="atable__sub">
                                    <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="atable__msg"><?= e($c['message']) ?></td>

                        <td>
                            <?php if ($daDay): ?>
                                <span class="badge badge--completed">Đã gửi</span>
                                <span class="atable__sub">
                                    <?= e(formatDate($c['zalo_sent_at'], 'd/m/Y H:i')) ?>
                                </span>
                            <?php elseif ($coCotZalo): ?>
                                <span class="badge badge--out_of_stock">Chưa gửi</span>
                            <?php endif; ?>

                            <?php /* NÚT HIỆN CẢ KHI ĐÃ GỬI. Ca thật: tin đã tới máy
                                     CSKH rồi máy đó hỏng, hoặc người trực xoá nhầm
                                     cuộc trò chuyện. Chặn bằng "đã gửi rồi" thì
                                     người duy nhất bị chặn là người đang cố sửa một
                                     việc hỏng.

                                     Không có data-confirm: gửi lại một tin không
                                     phá gì cả, hỏi lại chỉ làm chậm đúng thao tác
                                     mà người ta đang vội. */ ?>
                            <form method="post" action="/quan-tri/lien-he/zalo" class="apay">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                                <button type="submit" class="apay__btn<?= $daDay ? ' apay__btn--ghost' : '' ?>">
                                    <?= $daDay ? 'Gửi lại' : 'Gửi sang Zalo' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pager" aria-label="Phân trang">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="pager__link is-current" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a class="pager__link" href="/quan-tri/lien-he?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
