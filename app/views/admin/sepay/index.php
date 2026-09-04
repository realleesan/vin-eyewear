<?php

/**
 * admin/sepay/index.php — màn "Giao dịch chưa khớp".
 *
 * Controller: Admin/SepayAdminController::index()
 * Biến: $rows, $coBang, $coHaiBuoc, $toiLa, $lyDoToiThieu
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỘT DÒNG, HAI BỘ MẶT — theo X13
 *
 * Cùng một giao dịch hiện ra khác hẳn tuỳ nó đang ở bước nào:
 *
 *   chưa gán      ô nhập mã đơn + ô lý do + nút "Gán vào đơn"
 *   chờ xác nhận  hiện AI đã gán, VÌ SAO, rồi hai nút Xác nhận / Từ chối
 *
 * Không gộp thành một form "làm tất cả": hai bước có hai người và hai câu hỏi
 * khác nhau, mà một form chung sẽ khiến người ở bước 2 nhìn thấy ô nhập mã đơn
 * và tưởng mình được sửa nó.
 *
 * ⚠ MỌI PHÉP KIỂM Ở FILE NÀY CHỈ ĐỂ VẼ. Chặn thật nằm ở SepayModel (gan /
 * xacNhan / tuChoi) và requireManager() trong controller. Ẩn một cái nút không
 * chặn được cú POST dựng tay — mà đây đúng là màn đáng để ai đó dựng tay.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$nhanTrangThai = [
    'no_order'     => 'Chưa khớp đơn',
    'partial'      => 'Chuyển thiếu',
    'cho_xac_nhan' => 'Chờ xác nhận',
];
?>

<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Giao dịch chưa khớp</h1>
        <p class="ahead__lead">
            <?= count($rows) ?> giao dịch cần xử lý
        </p>
        <?php /* Nói ra quy trình NGAY TRÊN MÀN HÌNH. Người mở màn này vài ngày
                 một lần, và "vì sao tôi bấm Xác nhận mà nó báo lỗi" là câu hỏi
                 sẽ tới nếu luật hai người chỉ nằm trong tài liệu. */ ?>
        <p class="ahead__lead ahead__lead--muted">
            Hai bước: nhân viên gán vào đơn, rồi <strong>một người khác</strong>
            xác nhận thì tiền mới vào đơn. Cả hai bước bắt buộc ghi lý do.
        </p>
    </div>
</header>

<?php if (!$coBang): ?>
    <p class="apanel__empty">
        Chưa có bảng đối soát SePay trong cơ sở dữ liệu. Chạy
        <code>sudo bash database/migrate.sh</code> rồi mở lại trang này.
    </p>
<?php elseif (!$coHaiBuoc): ?>
    <?php /* Bảng có nhưng chưa có sáu cột của X13. Nói rõ và KHÔNG vẽ nút nào:
             vẽ ra rồi bấm vào chỉ nhận một câu lỗi từ model là bắt người dùng
             tự khám phá ra điều mà màn hình đã có thể nói trước. */ ?>
    <div class="anote anote--alert">
        Cơ sở dữ liệu chưa có bộ cột đối soát hai bước. Chạy
        <code>sudo bash database/migrate.sh</code> để dùng được màn này.
    </div>
<?php elseif ($rows === []): ?>
    <p class="apanel__empty">
        Không có giao dịch nào chờ xử lý. Mọi khoản tiền về đều đã khớp đơn tự động.
    </p>
<?php else: ?>

    <div class="atable-wrap">
        <table class="atable atable--full">
            <thead>
                <tr>
                    <th scope="col">Thời điểm</th>
                    <th scope="col">Số tiền</th>
                    <th scope="col">Nội dung chuyển khoản</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col">Xử lý</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $gd): ?>
                    <?php
                    $trangThai = (string) $gd['applied'];
                    $choDuyet  = $trangThai === 'cho_xac_nhan';
                    // Người gán không tự duyệt được — X13. Model chặn thật.
                    $toiDaGan  = (string) ($gd['gan_boi'] ?? '') === $toiLa;
                    ?>
                    <tr>
                        <td>
                            <?= e(formatDate($gd['transaction_date'] ?? $gd['created_at'], 'd/m/Y H:i')) ?>
                            <?php if (!empty($gd['reference_code'])): ?>
                                <span class="atable__sub"><?= e($gd['reference_code']) ?></span>
                            <?php endif; ?>
                        </td>

                        <td class="num"><strong><?= money((int) $gd['amount']) ?></strong></td>

                        <td>
                            <?php /* NGUYÊN VĂN, KHÔNG CẮT. Đây là thứ duy nhất
                                     người đối soát có để đoán khoản tiền thuộc
                                     đơn nào — cắt ngắn nó là bỏ mất đúng phần
                                     họ cần đọc. */ ?>
                            <span class="agd__ct"><?= e((string) ($gd['content'] ?? '—')) ?></span>
                            <?php if (!empty($gd['gateway'])): ?>
                                <span class="atable__sub"><?= e($gd['gateway']) ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge badge--<?= $choDuyet ? 'neutral' : 'out_stock' ?>">
                                <?= e($nhanTrangThai[$trangThai] ?? $trangThai) ?>
                            </span>

                            <?php if ($choDuyet): ?>
                                <span class="atable__sub">
                                    Đề xuất: đơn <strong><?= e((string) $gd['order_code']) ?></strong>
                                </span>
                                <?php if (!empty($gd['don_tong'])): ?>
                                    <span class="atable__sub">
                                        Tổng đơn <?= money((int) $gd['don_tong']) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="atable__sub">
                                    Gán bởi <?= e((string) ($gd['gan_ten'] ?? 'người đã nghỉ')) ?>
                                    <?php if (!empty($gd['gan_luc'])): ?>
                                        · <?= e(formatDate($gd['gan_luc'], 'd/m H:i')) ?>
                                    <?php endif; ?>
                                </span>
                                <?php /* LÝ DO GÁN in đậm hơn phần còn lại: đây
                                         là toàn bộ cơ sở để người duyệt quyết
                                         định, không phải một chú thích. */ ?>
                                <span class="agd__lydo">“<?= e((string) $gd['gan_ly_do']) ?>”</span>
                            <?php elseif ($trangThai === 'partial' && !empty($gd['order_code'])): ?>
                                <span class="atable__sub">
                                    Đã khớp đơn <?= e((string) $gd['order_code']) ?> nhưng chưa đủ tiền
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="arow-actions">
                            <?php if (!$choDuyet): ?>
                                <?php /* ── BƯỚC 1 — GÁN ────────────────────── */ ?>
                                <form class="agd__form" method="post"
                                      action="/quan-tri/giao-dich/gan">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($gd['id']) ?>">

                                    <label class="sr-only" for="md-<?= e($gd['id']) ?>">Mã đơn</label>
                                    <input class="agd__ma" type="text" id="md-<?= e($gd['id']) ?>"
                                           name="ma_don" required maxlength="40"
                                           placeholder="DH-260908-XXXX"
                                           value="<?= e((string) ($gd['order_code'] ?? '')) ?>">

                                    <label class="sr-only" for="ly-<?= e($gd['id']) ?>">Lý do gán</label>
                                    <input class="agd__ly" type="text" id="ly-<?= e($gd['id']) ?>"
                                           name="ly_do" required
                                           minlength="<?= (int) $lyDoToiThieu ?>" maxlength="255"
                                           placeholder="Vì sao khoản này thuộc đơn đó?">

                                    <button type="submit" class="agd__go">Gán vào đơn</button>
                                </form>
                                <p class="agd__hint">
                                    Gán chưa cộng tiền vào đơn. Phải có người khác xác nhận.
                                </p>

                            <?php elseif ($toiDaGan): ?>
                                <?php /* Chính người vừa gán đang nhìn dòng này.
                                         Nói thẳng vì sao không có nút, thay vì
                                         để một khoảng trống khó hiểu. */ ?>
                                <p class="agd__hint">
                                    Bạn là người đã gán giao dịch này, nên người khác
                                    phải xác nhận. Đây là yêu cầu của quy trình hai bước.
                                </p>

                            <?php else: ?>
                                <?php /* ── BƯỚC 2 — XÁC NHẬN hoặc TỪ CHỐI ──── */ ?>
                                <form class="agd__form" method="post"
                                      action="/quan-tri/giao-dich/xac-nhan">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($gd['id']) ?>">
                                    <label class="sr-only" for="xn-<?= e($gd['id']) ?>">Lý do xác nhận</label>
                                    <input class="agd__ly" type="text" id="xn-<?= e($gd['id']) ?>"
                                           name="ly_do" required
                                           minlength="<?= (int) $lyDoToiThieu ?>" maxlength="255"
                                           placeholder="Đã đối chiếu sao kê, khớp số tiền và ngày">
                                    <button type="submit" class="agd__go">Xác nhận — cộng tiền vào đơn</button>
                                </form>

                                <form class="agd__form" method="post"
                                      action="/quan-tri/giao-dich/tu-choi">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($gd['id']) ?>">
                                    <label class="sr-only" for="tc-<?= e($gd['id']) ?>">Lý do từ chối</label>
                                    <input class="agd__ly" type="text" id="tc-<?= e($gd['id']) ?>"
                                           name="ly_do" required
                                           minlength="<?= (int) $lyDoToiThieu ?>" maxlength="255"
                                           placeholder="Vì sao không phải đơn này?">
                                    <button type="submit" class="agd__no">Từ chối</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="field__hint">
        Chỉ hiện tiền VÀO tài khoản. Tiền ra (hoàn tiền, phí ngân hàng) được ghi
        vào sổ để khớp sao kê nhưng không thuộc về đơn nào.
    </p>

<?php endif; ?>
