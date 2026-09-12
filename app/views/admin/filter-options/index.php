<?php

/**
 * admin/filter-options/index.php — tuỳ biến tiêu chí lọc.
 *
 * Controller: Admin/FilterOptionAdminController::index()
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BẢNG NÀY LIỆT KÊ TIÊU CHÍ ĐANG SỐNG, KHÔNG PHẢI MỘT DANH SÁCH KHAI BÁO
 *
 * Mỗi dòng là một tiêu chí mà bộ lọc NGOÀI KIA đang có, rút ra từ chữ người
 * nhập hàng gõ vào ô dáng gọng, chất liệu, giới tính và màu của từng biến thể.
 * Nhập một gọng "Pantos" là mục "Pantos" tự xuất hiện ở đây, không ai phải
 * khai trước.
 *
 * Cột "Đang có" là số sản phẩm mang tiêu chí đó. Nó phải nằm NGAY CẠNH nút Ẩn
 * chứ không nằm trong một câu hỏi lại sau khi đã bấm — cùng lẽ với cột "Sản
 * phẩm" ở màn Danh mục.
 *
 * ⚠ ĐỪNG đổi bảng này thành form một cục "Lưu tất cả". Sửa một dòng mà kéo
 * theo ba chục dòng khác đi cùng là cách chắc chắn nhất để ghi đè nhầm thứ
 * người ta không đụng tới. Mỗi dòng mở hộp riêng, gửi riêng.
 */

$nhom    = (string) ($nhom ?? '');
$cacNhom = (array) ($cacNhom ?? []);
$de      = (array) ($de ?? []);
$canEdit = (bool) ($canEdit ?? false);
$ed      = $ed ?? null;

/* Mục mồ côi (dòng đè còn, nhưng không còn món nào mang tiêu chí ấy) xếp
   xuống cuối — chúng là thứ cần dọn, không phải thứ sửa hằng ngày. */
$tatCa = array_merge((array) ($dangSong ?? []), (array) ($moCoi ?? []));

$dongUrl = $base . '?nhom=' . rawurlencode($nhom);
?>

<?php partial('admin/_layout/crud-head', [
    'title'    => 'Tiêu chí lọc',
    'lead'     => 'Những mục khách nhìn thấy ở bộ lọc trang gọng kính, tròng kính và bộ sưu tập.',
    'base'     => $base,
    'canEdit'  => $canEdit,
    'editing'  => $ed,
    'addLabel' => '+ Thêm tiêu chí',
    /* Giữ nhóm đang mở trên địa chỉ nút Thêm — không có nó thì hộp thêm mở ra
       ở nhóm đầu tiên, và người bấm ghi nhầm tiêu chí sang nhóm khác. */
    'addUrl'   => $base . '?nhom=' . rawurlencode($nhom) . '&them=1',
]); ?>

<?php /* Dải nhóm dùng .atabs như trang đơn hàng — KHÔNG gọi partial
         filter-tabs: partial ấy luôn chèn sẵn một tab "Tất cả" kèm số đếm, mà
         ở đây không có khái niệm "tất cả các nhóm" (một tiêu chí chỉ thuộc
         đúng một nhóm) và cũng không có con số nào để in lên đó. */ ?>
<nav class="atabs" aria-label="Nhóm tiêu chí">
    <?php foreach ($cacNhom as $ma => $ten): ?>
        <a class="atabs__item<?= $ma === $nhom ? ' is-active' : '' ?>"
           href="<?= e($base) ?>?nhom=<?= e(rawurlencode((string) $ma)) ?>"
           <?= $ma === $nhom ? 'aria-current="true"' : '' ?>><?= e((string) $ten) ?></a>
    <?php endforeach; ?>
</nav>

<div class="anote">
    <p>
        <strong>Tiêu chí ở đây không do bạn khai ra.</strong> Chúng được rút ra từ chính
        chữ bạn gõ khi nhập hàng — ô dáng gọng, chất liệu, giới tính, và màu của từng
        biến thể. Nhập một gọng “Pantos” là mục “Pantos” tự có mặt ở bảng này.
    </p>
    <p>
        <strong>Đổi tên</strong> chỉ đổi chữ khách nhìn thấy; mã giữ nguyên nên mọi liên
        kết cũ vẫn trúng. <strong>Gộp vào</strong> nhập hai cách viết cùng một thứ làm
        một. <strong>Đồng nghĩa</strong> kéo chữ bạn đã gõ khi nhập hàng về mục này —
        đây mới là cách “thêm” một tiêu chí có tác dụng thật, vì một mục không khớp
        món nào chỉ là một dòng đếm 0.
    </p>
    <p>
        <strong>Ẩn</strong> chỉ bỏ mục khỏi bộ lọc — sản phẩm mang nó không biến mất, và
        địa chỉ lọc cũ vẫn chạy. <strong>Xoá</strong> ở đây an toàn: nó chỉ bỏ phần tuỳ
        biến, tiêu chí quay về tên mặc định, hàng hoá không suy suyển.
    </p>
</div>

<?php if ($tatCa === []): ?>
    <p class="apanel__empty">
        Nhóm này chưa có tiêu chí nào — chưa sản phẩm nào mang thuộc tính đó.
    </p>
<?php else: ?>
    <div class="atable-wrap">
        <table class="atable aftable">
            <thead>
                <tr>
                    <th scope="col">Ghim</th>
                    <th scope="col">Tiêu chí</th>
                    <th scope="col">Mã</th>
                    <th scope="col">Đang có</th>
                    <th scope="col">Gộp vào</th>
                    <th scope="col">Hiển thị</th>
                    <?php if ($canEdit): ?><th scope="col">Thao tác</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tatCa as $o): ?>
                    <?php
                    $khoa = (string) $o['key'];
                    $d    = $de[$khoa] ?? null;
                    $an   = $d !== null && (int) ($d['is_visible'] ?? 1) === 0;
                    $ghim = $d !== null && (int) ($d['sort_order'] ?? 0) > 0;
                    ?>
                    <tr>
                        <?php /* CỘT GHIM CHỈ HIỆN VỚI DÒNG ĐÃ TUỲ BIẾN.

                                 Ghim được ghi vào `sort_order` của bảng đè, mà
                                 dòng đè chỉ sinh ra khi cửa hàng đã đổi tên /
                                 gộp / ẩn mục đó. Vẽ cặp ↑↓ cho mọi dòng thì
                                 mỗi cú bấm đầu tiên lại lặng lẽ tạo một dòng
                                 mới — người bấm không thấy, nhưng nút "Bỏ tuỳ
                                 biến" thì tự dưng mọc ra ở cột bên cạnh. */ ?>
                        <td>
                            <?php if ($canEdit && $d !== null): ?>
                                <div class="aord">
                                    <?php foreach ([['len', '↑', 'lên'], ['xuong', '↓', 'xuống']] as [$huong, $mui, $chu]): ?>
                                        <form method="post" action="<?= e($base) ?>/thu-tu">
                                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= e((string) $d['id']) ?>">
                                            <input type="hidden" name="huong" value="<?= e($huong) ?>">
                                            <button type="submit" class="aord__btn"
                                                    aria-label="Chuyển <?= e((string) $o['label']) ?> <?= e($chu) ?> một bậc"
                                                    title="Ghim mục này lên đầu bộ lọc"><?= $mui ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="num"><?= $ghim ? '•' : '—' ?></span>
                            <?php endif; ?>
                        </td>

                        <td class="admname"><?= e((string) $o['label']) ?></td>
                        <td><code><?= e($khoa) ?></code></td>

                        <?php /* Số 0 = không còn món nào mang tiêu chí này (bán hết,
                                 hoặc người nhập đã sửa lại chữ). Dòng vẫn phải hiện
                                 ra để cửa hàng xoá được phần tuỳ biến của nó. */ ?>
                        <td class="num"><?= (int) $o['count'] ?></td>

                        <td>
                            <?php if ($d !== null && (string) ($d['merge_into'] ?? '') !== ''): ?>
                                <code><?= e((string) $d['merge_into']) ?></code>
                            <?php else: ?>
                                <span class="num">—</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php /* VIÊN NHÃN BẤM ĐƯỢC, không phải nhãn chỉ-đọc —
                                     cùng lối với cột "Hiển thị" ở màn Danh mục. Ẩn
                                     một tiêu chí là thao tác một-bit và làm thường
                                     xuyên; bắt mở hộp sửa rồi tick một ô là bốn
                                     bước cho một cú bấm. */ ?>
                            <?php if ($canEdit): ?>
                                <form method="post" action="<?= e($base) ?>/hien">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="nhom" value="<?= e($nhom) ?>">
                                    <input type="hidden" name="option_key" value="<?= e($khoa) ?>">
                                    <button type="submit"
                                            class="atoggle atoggle--pill<?= $an ? ' is-off' : '' ?>"
                                            title="<?= $an ? 'Bấm để hiện lại trong bộ lọc' : 'Bấm để ẩn khỏi bộ lọc' ?>">
                                        <?= $an ? 'Đang ẩn' : 'Đang hiện' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge badge--<?= $an ? 'neutral' : 'in_stock' ?>">
                                    <?= $an ? 'Đang ẩn' : 'Đang hiện' ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <?php if ($canEdit): ?>
                            <td class="arow-actions">
                                <a href="<?= e($base) ?>?nhom=<?= e(rawurlencode($nhom)) ?>&sua=<?= e(rawurlencode($khoa)) ?>"
                                   data-modal>Sửa</a>

                                <?php /* Chỉ dòng ĐÃ tuỳ biến mới có gì để xoá. Tiêu chí
                                         máy tự rút ra thì không xoá được ở đây — muốn nó
                                         biến mất khỏi bộ lọc thì bấm "Đang hiện" để ẩn,
                                         hoặc sửa lại chữ ở sản phẩm. */ ?>
                                <?php if ($d !== null): ?>
                                    <?php $hoi = sprintf('Bỏ tuỳ biến của “%s”? Tiêu chí quay về tên mặc định.', (string) $o['label']); ?>
                                    <form method="post" action="<?= e($base) ?>/xoa"
                                          data-confirm="<?= e($hoi) ?>"
                                          data-confirm-title="Bỏ tuỳ biến?"
                                          data-confirm-ok="Bỏ tuỳ biến"
                                          onsubmit="return confirm('<?= e($hoi) ?>')">
                                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= e((string) $d['id']) ?>">
                                        <button type="submit" class="arow-del">Bỏ tuỳ biến</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
/*
 * HỘP SỬA MỞ THEO ĐỊA CHỈ, không theo JavaScript — cùng nếp với Danh mục và
 * Bộ sưu tập: ?them=1 mở hộp trống, ?sua=<mã> mở hộp đã điền. Nút ✕, nút Huỷ
 * và lớp nền mờ đều là <a> trỏ về chính trang này, nên tắt JS vẫn dùng được.
 */
$moHop = $canEdit && $ed !== null;
?>
<?php if ($moHop): ?>
    <?php $them = (bool) ($ed['them'] ?? false); ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => $them ? 'Thêm tiêu chí' : 'Tuỳ biến tiêu chí',
        'phu'     => $them
            ? 'Mục mới chỉ có tác dụng khi ô “Đồng nghĩa” khớp chữ bạn đã gõ lúc nhập hàng.'
            : (string) $ed['label'],
        'dongUrl' => $dongUrl,
        'rong'    => 'sm',
    ]); ?>

        <form method="post" action="<?= e($base) ?>/luu" class="aform__grid" id="fo-form">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="nhom" value="<?= e($nhom) ?>">

            <div class="field field--wide">
                <label for="option_key">Mã tiêu chí *</label>
                <?php /* MÃ KHOÁ KHI SỬA.

                         Mã là thứ đi vào địa chỉ (?shape[]=cat-eye) và là thứ
                         ProductTaxonomy sinh ra từ chữ người nhập hàng gõ. Đổi
                         nó ở đây không đổi được dữ liệu — chỉ tạo ra một dòng
                         đè trỏ vào hư không, còn mục cũ thì mất tuỳ biến.

                         `readonly` là hàng rào của trình duyệt, ai cũng gửi
                         POST khác đi được — nhưng ở bảng ĐÈ thì hậu quả bằng
                         không: cùng lắm sinh ra một dòng mồ côi, mà bảng liệt
                         kê luôn cả dòng mồ côi kèm nút Bỏ tuỳ biến. Không cần
                         chốt thêm ở server như màn Thuộc tính tròng, nơi khoá
                         nằm trong dữ liệu thật của sản phẩm. */ ?>
                <input type="text" id="option_key" name="option_key" required maxlength="64"
                       pattern="[a-z0-9][a-z0-9.\-]*"
                       value="<?= e((string) $ed['key']) ?>"
                       <?= $them ? '' : 'readonly' ?>>
                <p class="field__hint">
                    <?= $them
                        ? 'Chữ thường không dấu, số, dấu gạch nối. Vd: cat-eye'
                        : 'Mã không sửa được — mọi liên kết lọc cũ đang trỏ vào nó.' ?>
                </p>
            </div>

            <div class="field field--wide">
                <label for="label">Tên hiển thị <span class="field__opt">(bỏ trống để dùng tên mặc định)</span></label>
                <input type="text" id="label" name="label" maxlength="120"
                       placeholder="<?= e((string) $ed['label']) ?>"
                       value="<?= e((string) ($ed['row']['label'] ?? '')) ?>">
            </div>

            <div class="field field--wide">
                <label for="merge_into">Gộp vào mã <span class="field__opt">(bỏ trống nếu không gộp)</span></label>
                <input type="text" id="merge_into" name="merge_into" maxlength="64"
                       pattern="[a-z0-9][a-z0-9.\-]*"
                       value="<?= e((string) ($ed['row']['merge_into'] ?? '')) ?>">
                <p class="field__hint">
                    Mục này biến mất khỏi bộ lọc, sản phẩm của nó dồn sang mục kia. Gộp
                    một bậc thôi: A gộp vào B, B lại gộp vào C thì A chỉ tới B.
                </p>
            </div>

            <div class="field field--wide">
                <label for="synonyms">Đồng nghĩa <span class="field__opt">(cách nhau bằng dấu phẩy)</span></label>
                <input type="text" id="synonyms" name="synonyms" maxlength="500"
                       placeholder="vd: pantos, panto, kiểu pantos"
                       value="<?= e((string) ($ed['row']['synonyms'] ?? '')) ?>">
                <p class="field__hint">
                    Chữ bạn đã gõ ở sản phẩm mà khớp một trong những cách viết này sẽ
                    được xếp vào mục trên, thay vì đứng riêng thành một mục mới.
                </p>
            </div>
        </form>

    <?php partial('admin/_layout/modal-foot', [
        'dongUrl' => $dongUrl,
        'luuNhan' => $them ? 'Thêm tiêu chí' : 'Lưu thay đổi',
        'luuForm' => 'fo-form',
    ]); ?>
<?php endif; ?>
