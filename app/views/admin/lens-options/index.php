<?php

/**
 * admin/lens-options/index.php — bốn danh sách thuộc tính tròng kính.
 *
 * Controller: Admin/LensOptionAdminController::index()
 *
 * BỐN NHÓM TRÊN BỐN TAB CỦA CÙNG MỘT TRANG, không phải bốn trang. Người sửa
 * thường vào để thêm một màu rồi thêm luôn một lớp phủ; bốn URL rời nhau bắt
 * họ quay ra menu giữa chừng.
 *
 * CỘT "ĐANG DÙNG" LÀ LÝ DO TRANG NÀY KHÁC MÀN DANH MỤC. Ẩn một danh mục thì
 * hàng trong đó vẫn tra được ở nơi khác; ẩn một thuộc tính tròng thì số hàng
 * đang gắn nó lặng lẽ rút khỏi bộ lọc. Con số ấy phải nằm ngay cạnh nút Ẩn,
 * không phải trong một câu hỏi lại sau khi đã bấm.
 */

$ed      = $editing;
$dongUrl = '/quan-tri/thuoc-tinh-trong?nhom=' . rawurlencode($group);
$moHop   = $canEdit && ($ed !== null || isset($_GET['them']));
?>
<?php partial('admin/_layout/crud-head', [
    'title' => 'Thuộc tính tròng',
    /* Dòng dẫn nói ra thứ KHÔNG nhìn thấy trên bảng: bốn danh sách này chính
       là bộ lọc khách đang dùng, không phải một bảng tra nội bộ. */
    'lead'  => LensOptionModel::GROUPS[$group] . ' · ' . count($rows)
             . ' lựa chọn · đây là những mục khách thấy ở bộ lọc trang Tròng kính',
    'base'  => $dongUrl, 'canEdit' => $canEdit, 'editing' => $ed,
    'addLabel' => '+ Thêm lựa chọn',
]); ?>

<?php /* Tab chuyển nhóm. Dùng .afilters của filter-tabs thay vì tự dựng bộ lớp
         mới: bốn cái này hành xử đúng như bộ lọc trạng thái ở các màn khác —
         liên kết thật, đổi một tham số trên URL, không cần JavaScript. */ ?>
<nav class="afilters" aria-label="Nhóm thuộc tính">
    <?php foreach ($groups as $ma => $ten): ?>
        <a class="afilter<?= $ma === $group ? ' is-on' : '' ?>"
           <?= $ma === $group ? 'aria-current="page"' : '' ?>
           href="/quan-tri/thuoc-tinh-trong?nhom=<?= e(rawurlencode($ma)) ?>"><?= e($ten) ?></a>
    <?php endforeach; ?>
</nav>

<div class="anote">
    <p>
        <strong>Mã không sửa được sau khi tạo.</strong> Mã là thứ được ghi vào từng sản
        phẩm; đổi nó thì số hàng đang gắn mục này vẫn giữ mã cũ và biến mất khỏi bộ lọc
        mà không báo gì. Cần đổi thì tạo mục mới rồi gắn lại hàng.
    </p>
    <p>
        Không có nút Xoá, chỉ có <strong>Ẩn</strong> — cùng lý do. Ẩn thì hàng cũ giữ
        nguyên mã, chỉ mục đó rút khỏi bộ lọc và khỏi form nhập hàng.
    </p>
</div>

<?php if ($rows === []): ?>
    <p class="apanel__empty">Nhóm này chưa có lựa chọn nào.</p>
<?php else: ?>
    <div class="atable-wrap">
        <table class="atable admtable">
            <thead>
                <tr>
                    <th scope="col">Thứ tự</th>
                    <th scope="col">Mã</th>
                    <th scope="col">Tên hiển thị</th>
                    <th scope="col">Ghi chú</th>
                    <th scope="col">Đang dùng</th>
                    <th scope="col">Hiển thị</th>
                    <?php if ($canEdit): ?><th scope="col">Thao tác</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php $soDong = count($rows); ?>
                <?php foreach ($rows as $i => $o): ?>
                    <?php $dung = $usage[(string) $o['option_key']] ?? 0; ?>
                    <tr>
                        <?php /* Thứ tự ở đây là thứ tự khách thấy trong ô lọc, và
                                 mục đứng đầu là mục mắt chạm tới trước. Con số
                                 sort_order thô không nói được điều đó — hai cái
                                 nút thì nói. */ ?>
                        <td>
                            <?php if ($canEdit): ?>
                                <?php partial('admin/_layout/thu-tu', [
                                    'base' => '/quan-tri/thuoc-tinh-trong/thu-tu',
                                    'id'   => $o['id'],
                                    'dau'  => $i === 0,
                                    'cuoi' => $i === $soDong - 1,
                                    'ten'  => $o['label'],
                                ]); ?>
                            <?php else: ?>
                                <span class="num"><?= $i + 1 ?></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e($o['option_key']) ?></code></td>
                        <td class="admname"><?= e($o['label']) ?></td>
                        <td class="atable__msg" title="<?= e((string) ($o['note'] ?? '')) ?>">
                            <?php if (($o['note'] ?? '') !== ''): ?>
                                <?= e(excerpt((string) $o['note'], 60)) ?>
                            <?php else: ?>
                                <span class="atable__sub">—</span>
                            <?php endif; ?>
                        </td>
                        <?php /* SỐ SẢN PHẨM ĐANG GẮN MÃ NÀY — câu người ta hỏi
                                 ngay trước khi bấm Ẩn. 0 thì để trung tính: mục
                                 chưa ai dùng, ẩn đi không mất gì. */ ?>
                        <td class="num">
                            <?php if ($dung > 0): ?>
                                <span class="badge badge--in_stock"><?= (int) $dung ?> sản phẩm</span>
                            <?php else: ?>
                                <span class="atable__sub">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php /* VIÊN NHÃN BẤM ĐƯỢC, cùng lối với cột Hiển thị
                                     của màn Danh mục: ẩn/hiện là thao tác một-bit
                                     làm thường xuyên, bắt mở form sửa để tick một
                                     ô là bốn bước cho một cú bấm. */ ?>
                            <?php if ($canEdit): ?>
                                <form method="post" action="/quan-tri/thuoc-tinh-trong/hien">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($o['id']) ?>">
                                    <button type="submit"
                                            class="atoggle atoggle--pill<?= $o['is_visible'] ? '' : ' is-off' ?>"
                                            title="<?= $o['is_visible']
                                                ? 'Bấm để ẩn khỏi bộ lọc và form nhập hàng'
                                                : 'Bấm để hiện lại' ?>">
                                        <?= $o['is_visible'] ? 'Đang hiện' : 'Đang ẩn' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge badge--<?= $o['is_visible'] ? 'in_stock' : 'neutral' ?>">
                                    <?= $o['is_visible'] ? 'Đang hiện' : 'Đang ẩn' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <?php if ($canEdit): ?>
                            <td class="arow-actions">
                                <a href="<?= e($dongUrl) ?>&sua=<?= e($o['id']) ?>" data-modal>Sửa</a>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if ($moHop): ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => $ed !== null ? 'Sửa lựa chọn' : 'Thêm lựa chọn mới',
        'phu'     => $ed !== null
            ? $ed['label'] . ' · mã ' . $ed['option_key'] . ' (không đổi được)'
            : LensOptionModel::GROUPS[$group] . ' · mục mới đứng cuối danh sách',
        'dongUrl' => $dongUrl,
        'rong'    => 'sm',
    ]); ?>

        <?php /* id="lensopt-form" để nút Lưu ở chân hộp trỏ tới bằng thuộc tính
                 form= — nút ấy nằm ngoài vùng cuộn của ruột hộp. */ ?>
        <form method="post" action="/quan-tri/thuoc-tinh-trong/luu" class="aform__grid" id="lensopt-form">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="nhom" value="<?= e($group) ?>">
            <?php /* `cu` là thứ controller chốt đường sửa, KHÔNG phải ô mã bên
                     dưới: ô ấy readonly, mà readonly là chuyện của trình duyệt. */ ?>
            <input type="hidden" name="cu" value="<?= e($ed['id'] ?? '') ?>">

            <div class="field">
                <label for="option_key">Mã *</label>
                <input type="text" id="option_key" name="option_key" maxlength="64"
                       <?= $ed !== null ? 'readonly' : 'required' ?>
                       value="<?= e($ed['option_key'] ?? '') ?>"
                       placeholder="<?= $group === 'chiet-suat' ? '1.61' : 'xam-khoi' ?>">
                <?php if ($ed === null): ?>
                    <p class="field__hint">Chữ thường không dấu, số, dấu chấm và gạch nối. Không đổi được sau khi tạo.</p>
                <?php else: ?>
                    <p class="field__hint">Mã đã dùng cho sản phẩm nên không đổi được. Cần đổi thì tạo mục mới rồi gắn lại hàng.</p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="label">Tên hiển thị *</label>
                <input type="text" id="label" name="label" required maxlength="120"
                       value="<?= e($ed['label'] ?? '') ?>">
            </div>

            <div class="field field--wide">
                <label for="note">Ghi chú <span class="field__opt">(chỉ hiện ở khu quản trị)</span></label>
                <textarea id="note" name="note" rows="2" maxlength="255"><?= e((string) ($ed['note'] ?? '')) ?></textarea>
            </div>
        </form>

    <?php partial('admin/_layout/modal-foot', [
        'dongUrl' => $dongUrl,
        'luuNhan' => $ed !== null ? 'Lưu thay đổi' : 'Thêm lựa chọn',
        'luuForm' => 'lensopt-form',
    ]); ?>
<?php endif; ?>
