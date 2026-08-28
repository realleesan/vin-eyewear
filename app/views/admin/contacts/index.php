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
<?php
/* Đường dẫn cho từng viên lọc, giữ nguyên từ khoá đang gõ. Không mang `page`
   theo: đổi bộ lọc thì phải về trang 1, chứ không phải trang 3 của kết quả cũ. */
$duongDanZalo = static function (string $key) use ($q): string {
    $tham = array_filter(['q' => $q, 'zalo' => $key]);

    return '/quan-tri/lien-he' . ($tham !== [] ? '?' . http_build_query($tham) : '');
};
?>
<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Yêu cầu liên hệ</h1>
        <?php /* Dòng dẫn đếm luôn số CHƯA TỚI ZALO — theo bản thiết kế. Tổng
                 số yêu cầu một mình không nói được gì để làm; con số đáng đọc
                 là bao nhiêu người đang chờ gọi lại mà CSKH chưa biết. Số trang
                 chuyển xuống chân bảng. */ ?>
        <p class="ahead__lead">
            <?= (int) $total ?> yêu cầu<?php if ($coCotZalo && $chuaDay > 0): ?>
                · <?= (int) $chuaDay ?> chưa tới Zalo CSKH
            <?php endif; ?>
        </p>
    </div>

    <div class="ahead__tools">
        <form class="asearch" method="get" action="/quan-tri/lien-he" role="search">
            <?php if ($zalo !== ''): ?>
                <input type="hidden" name="zalo" value="<?= e($zalo) ?>">
            <?php endif; ?>
            <label class="sr-only" for="lhTim">Tìm yêu cầu liên hệ</label>
            <input type="search" id="lhTim" name="q" value="<?= e($q) ?>"
                   placeholder="Tìm tên, SĐT, email, nội dung…">
            <button type="submit" class="astatus__save astatus__save--ghost">Tìm</button>
            <?php if ($q !== ''): ?>
                <a class="apanel__more" href="<?= e($duongDanZalo($zalo)) ?>">Xoá tìm kiếm</a>
            <?php endif; ?>
        </form>
    </div>
</header>

<?php if ($coCotZalo && $chuaDay > 0): ?>
    <?php /* DẢI CẢNH BÁO ĐỨNG TRÊN BẢNG, không phải một con số nhỏ trong tiêu đề.

             Một yêu cầu chưa đẩy được nghĩa là có người thật đang chờ gọi lại
             mà CSKH chưa biết là có ai chờ. Đó là hỏng hóc duy nhất trang này
             còn báo được, nên nó phải to bằng mức nghiêm trọng của nó — và
             bình thường thì khối này không tồn tại. */ ?>
    <div class="anote anote--alert anote--act" role="alert">
        <div>
            <p>
                <strong><?= (int) $chuaDay ?> yêu cầu chưa tới được Zalo CSKH.</strong>
                Khách đang chờ gọi lại mà đầu bên kia chưa biết.
            </p>
            <p>
                Gần như luôn là do chưa khai <code>ZALO_ZNS_TEMPLATE_CONTACT</code> hoặc
                token OA hết hạn. Sửa xong thì bấm nút bên cạnh để đẩy lại cả loạt.
            </p>
        </div>

        <?php
        /*
         * NÚT ĐẨY CẢ LOẠT, đặt ngay trong dải cảnh báo.
         *
         * Yêu cầu kẹt lại gần như luôn kẹt theo LÔ: token hết hạn hay thiếu
         * khai template thì mọi yêu cầu trong quãng đó đều nằm lại. Sửa xong
         * cấu hình mà phải bấm từng dòng là lặp một thao tác mười lăm lần, và
         * lần thứ mười hai thì người ta bỏ dở — để lại đúng những khách chưa
         * ai gọi.
         *
         * Có hỏi lại: nó gửi tin ra ngoài cho người thật, và số lượng thì
         * người bấm chỉ ước chừng qua con số trong câu trên. Nút từng dòng thì
         * KHÔNG hỏi (một tin, gửi lại vô hại) — khác nhau ở chỗ đó.
         */
        $hoiDayHet = sprintf(
            'Đẩy cả %d yêu cầu sang Zalo CSKH?',
            (int) $chuaDay
        );
        ?>
        <form method="post" action="/quan-tri/lien-he/zalo-tat-ca"
              data-confirm="<?= e($hoiDayHet) ?>"
              data-confirm-title="Gửi tất cả sang Zalo?"
              data-confirm-ok="Gửi tất cả"
              onsubmit="return confirm('<?= e($hoiDayHet) ?>')">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <button type="submit" class="astatus__save">Gửi tất cả sang Zalo</button>
        </form>
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

<?php /* Dải viên lọc theo tình trạng đẩy Zalo. Chỉ hiện khi CSDL có cột ấy —
         chưa chạy migration thì cả ba viên đều đếm cùng một tập, tức là ba
         cái nút không phân biệt được gì, và dải cảnh báo ngay trên đã nói rõ
         phải chạy file nào. */ ?>
<?php if ($coCotZalo): ?>
    <nav class="atabs" aria-label="Lọc theo tình trạng gửi Zalo">
        <?php foreach (['' => 'Tất cả', 'chua' => 'Chưa gửi', 'da' => 'Đã gửi'] as $key => $nhan): ?>
            <a class="atabs__item<?= $zalo === $key ? ' is-active' : '' ?>"
               href="<?= e($duongDanZalo((string) $key)) ?>"
               <?= $zalo === $key ? 'aria-current="true"' : '' ?>>
                <?= e($nhan) ?>
                <span class="atabs__num"><?= (int) ($zaloCounts[$key === '' ? '' : $key] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php if ($contacts === []): ?>
    <p class="apanel__empty">
        <?= $q !== '' || $zalo !== ''
            ? 'Không có yêu cầu nào khớp bộ lọc.'
            : 'Chưa có yêu cầu liên hệ nào.' ?>
    </p>
<?php else: ?>
    <div class="atable-wrap">
        <table class="atable alctable">
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
                            <span class="alcname"><?= e($c['full_name']) ?></span>
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

                        <?php /* Ô nội dung LÀ MỘT ĐƯỜNG DẪN mở hộp chi tiết.

                                 Ô này cắt còn một dòng để hàng không cao gấp bốn
                                 hàng bên cạnh. Trước đây phần bị cắt chỉ đọc được
                                 bằng cách rê chuột chờ tooltip — thao tác không ai
                                 đoán ra, và không dùng được bằng bàn phím hay trên
                                 điện thoại. Nay bấm vào là mở trọn nội dung.

                                 Vẫn giữ title= làm lối tắt cho người đã quen rê
                                 chuột. */ ?>
                        <td class="atable__msg">
                            <a class="alcmsg" data-modal title="<?= e($c['message']) ?>"
                               href="<?= e($duongDanZalo($zalo)) ?><?= str_contains($duongDanZalo($zalo), '?') ? '&amp;' : '?' ?>xem=<?= e($c['id']) ?>"><?= e($c['message']) ?></a>
                        </td>

                        <td class="alczalo">
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

        <?php /* Chân bảng nằm TRONG khung, cùng lối với các bảng khác. Đường
                 dẫn phân trang phải mang theo cả bộ lọc, nếu không bấm sang
                 trang 2 là mất từ khoá vừa gõ. */ ?>
        <div class="aofoot">
            <p class="aofoot__count">
                Đang hiện <?= count($contacts) ?> / <?= (int) $total ?> yêu cầu
                <?php if ($totalPages > 1): ?>· trang <?= (int) $page ?>/<?= (int) $totalPages ?><?php endif; ?>
            </p>

            <?php if ($totalPages > 1): ?>
                <nav class="pager" aria-label="Phân trang">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php
                        $thamTrang = array_filter(['q' => $q, 'zalo' => $zalo])
                            + ($i > 1 ? ['page' => $i] : []);
                        ?>
                        <?php if ($i === $page): ?>
                            <span class="pager__link is-current" aria-current="page"><?= $i ?></span>
                        <?php else: ?>
                            <a class="pager__link"
                               href="/quan-tri/lien-he<?= $thamTrang !== [] ? '?' . e(http_build_query($thamTrang)) : '' ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
/*
 * HỘP CHI TIẾT MỘT YÊU CẦU — theo bản thiết kế "Liên hệ.dc.html".
 *
 * Mở theo địa chỉ (?xem=<id>) như mọi hộp khác của khu quản trị. Nội dung đầy
 * đủ để nguyên xuống dòng của người gửi — đây là chỗ DUY NHẤT đọc được trọn
 * một yêu cầu, nên nó không cắt gì cả.
 *
 * Chân hộp KHÔNG có nút Lưu: không có gì để sửa ở đây. Thay vào đó là nút đẩy
 * Zalo (nếu chưa đi được) và nút Đóng — nên hộp này tự dựng chân riêng thay vì
 * gọi modal-foot.
 */
$chiTiet = $detail ?? null;
$dongXem = currentUrlWithout(['xem']);
?>
<?php if ($chiTiet !== null): ?>
    <?php $daDayCT = $coCotZalo && ($chiTiet['zalo_sent_at'] ?? null) !== null; ?>

    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => $chiTiet['full_name'],
        'phu'     => 'Gửi lúc ' . formatDate($chiTiet['created_at'], 'd/m/Y H:i'),
        'dongUrl' => $dongXem,
        'rong'    => 'sm',
    ]); ?>

        <dl class="alcdet">
            <div class="alcdet__row">
                <dt>Điện thoại</dt>
                <dd><a href="tel:<?= e(preg_replace('/\D/', '', $chiTiet['phone'])) ?>"><?= e($chiTiet['phone']) ?></a></dd>
            </div>

            <?php if (!empty($chiTiet['email'])): ?>
                <div class="alcdet__row">
                    <dt>Email</dt>
                    <dd><a href="mailto:<?= e($chiTiet['email']) ?>"><?= e($chiTiet['email']) ?></a></dd>
                </div>
            <?php endif; ?>
        </dl>

        <p class="alcdet__label">Nội dung yêu cầu</p>
        <?php /* white-space: pre-wrap trong CSS — giữ nguyên chỗ khách xuống
                 dòng. Gộp thành một khối chữ liền thì một yêu cầu có gạch đầu
                 dòng đọc ra thành một câu dài lê thê. */ ?>
        <p class="alcdet__msg"><?= e($chiTiet['message']) ?></p>

        <?php if ($coCotZalo): ?>
            <p class="alcdet__zalo">
                Zalo CSKH:
                <span class="badge badge--<?= $daDayCT ? 'completed' : 'out_of_stock' ?>">
                    <?= $daDayCT ? 'Đã gửi' : 'Chưa gửi' ?>
                </span>
                <?php if ($daDayCT): ?>
                    <span class="alcdet__when"><?= e(formatDate($chiTiet['zalo_sent_at'], 'd/m/Y H:i')) ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        </div>

        <div class="amodal__foot">
            <?php /* Nút đẩy Zalo hiện CẢ KHI đã gửi — cùng luật với nút ở bảng:
                     ca thật là tin đã tới máy CSKH rồi máy đó hỏng. */ ?>
            <form method="post" action="/quan-tri/lien-he/zalo">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= e($chiTiet['id']) ?>">
                <button type="submit" class="astatus__save<?= $daDayCT ? ' astatus__save--ghost' : '' ?>">
                    <?= $daDayCT ? 'Gửi lại sang Zalo' : 'Gửi sang Zalo' ?>
                </button>
            </form>

            <a class="astatus__save astatus__save--ghost" href="<?= e($dongXem) ?>">Đóng</a>
        </div>
    </div>
</div>
<?php endif; ?>
