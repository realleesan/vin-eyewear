<?php

/**
 * LensPriceAdminController — bảng giá tròng (/quan-tri/gia-trong).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỘT LƯỚI, MỘT NÚT LƯU — KHÔNG PHẢI CRUD NHƯ CÁC MÀN KHÁC
 *
 * Mọi màn quản trị khác ở đây là danh sách + form thêm/sửa/xoá từng bản ghi.
 * index()/save() thì không, vì thứ chúng sửa không phải một danh sách: đó là
 * một LƯỚI — số kiểu tròng nhân số gói chiết suất. Không ai "thêm một giá mới"
 * hay "xoá một giá"; người ta mở bảng ra, sửa vài con số, rồi lưu.
 *
 * Hình dạng lưới ấy KHÔNG còn cố định từ 2026-08-27: gói chiết suất (các HÀNG)
 * nay là bản ghi trong bảng `lens_packages` và cửa hàng tự thêm/sửa/xoá — đó
 * là packages()/savePackage()/deletePackage() ở nửa dưới file này, và chúng đi
 * đúng nếp CRUD như các màn khác. Kiểu tròng (các CỘT) vẫn khai trong
 * config/taxonomy.php.
 *
 * Nên ở đây một <form> phủ cả lưới và đúng một nút "Lưu bảng giá". Mười lăm
 * form với mười lăm nút thì đổi bảng giá đầu tháng thành mười lăm lượt tải
 * trang, và sửa xong quên bấm một ô là một giá cũ ở lại mà không ai thấy.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class LensPriceAdminController extends AdminController
{
    private const BASE = '/quan-tri/gia-trong';
    private const PKG  = '/quan-tri/gia-trong/goi';

    public function index(): void
    {
        /* Chỉ những kiểu CÓ bảng giá mới thành cột. "Mắt đặt" không có ô nào —
           tròng đặt riêng theo đơn thì cửa hàng báo giá sau khi xem thông số,
           nên vẽ cho nó một cột ô trống là mời người ta điền vào chỗ hệ thống
           sẽ bỏ qua. View nói rõ điều đó bằng một dòng chú thích. */
        $types = array_values(array_filter(
            LensModel::types(),
            static fn (array $t): bool => LensModel::typeTakesPackage($t)
        ));

        $this->renderAdmin('admin/lens-prices/index', [
            'pageTitle' => 'Bảng giá tròng — Quản trị',
            'types'     => $types,
            'packages'  => LensModel::packages(),
            'prices'    => LensModel::priceTable(),
            /* Bảng `lens_packages` đã có chưa. Chưa có thì danh mục đang đọc
               từ đường lùi trong config, và trang phải nói ra — nếu không,
               người dùng bấm "Quản lý gói" rồi sửa một cái form không ghi vào
               đâu cả. Xem LensModel::packages(). */
            'pkgEditable' => LensModel::packagesEditable(),
            // Bảng giá là dữ liệu catalog, cùng mức quyền với sản phẩm và cơ sở.
            'canEdit'   => UserModel::hasRole($this->userId, 'admin')
                        || UserModel::hasRole($this->userId, 'manager'),
            // Kiểu không có bảng giá, để view giải thích vì sao nó vắng mặt.
            'quotedTypes' => array_values(array_filter(
                LensModel::types(),
                static fn (array $t): bool => !LensModel::typeTakesPackage($t)
            )),
        ]);
    }

    public function save(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        /* Ô nhập tên là gia[<mã kiểu>][<mã gói>], nên $_POST['gia'] tới đây đã
           đúng hình dạng mà savePriceTable() nhận. Ép về mảng chứ không tin
           $_POST: "gia=x" gửi tay thì nó là chuỗi, và model sẽ đọc từng ký tự
           của chuỗi đó như thể là một hàng của bảng.

           Toàn bộ việc lọc mã hợp lệ và kiểm con số nằm trong model — nó là
           nơi duy nhất định nghĩa "thế nào là một bảng giá hợp lệ". Kiểm thêm
           ở đây thì thành hai nơi cùng định nghĩa, và hai nơi đó sẽ lệch nhau
           vào lần sửa thứ ba. */
        LensModel::savePriceTable((array) ($_POST['gia'] ?? []));

        flash('admin_success', 'Đã lưu bảng giá tròng.');
        redirect(self::BASE);
    }

    // ========================================================================
    // GÓI CHIẾT SUẤT — DANH MỤC (/quan-tri/gia-trong/goi)
    // ========================================================================

    /**
     * Danh sách gói + form thêm/sửa.
     *
     * TRANG RIÊNG, không gộp vào lưới giá. Hai việc khác nhịp hẳn nhau: giá
     * đổi hằng tháng và sửa hàng loạt trong một lưới, còn thêm một gói là việc
     * vài tháng một lần và đi từng bản ghi. Nhét cả hai vào một trang thì lưới
     * giá — thứ được mở nhiều hơn hẳn — phải cuộn qua một cái form không liên
     * quan mỗi lần.
     */
    public function packages(): void
    {
        /* Chưa chạy file nâng cấp thì KHÔNG vẽ form. Bày một cái form ghi vào
           một bảng không tồn tại là mời người dùng gõ xong rồi nhận lỗi 1146 —
           cùng nếp với module Khách hàng, xem admin/customers/chua-nang-cap.php. */
        if (!LensModel::packagesEditable()) {
            $this->renderAdmin('admin/lens-prices/packages-chua-nang-cap', [
                'pageTitle' => 'Gói chiết suất — Quản trị',
            ]);
            return;
        }

        $editing = isset($_GET['sua']) ? LensModel::findPackageRow((string) $_GET['sua']) : null;

        /* Số ô giá của TỪNG gói, gộp một câu thay vì hỏi trong vòng lặp. Dùng
           cho hai việc: cột "Đã định giá" trong bảng, và con số trong câu hỏi
           lại trước khi xoá. */
        $priceCounts = [];

        foreach (Database::fetchAll(
            'SELECT lens_package, COUNT(*) AS n FROM lens_prices GROUP BY lens_package'
        ) as $row) {
            $priceCounts[$row['lens_package']] = (int) $row['n'];
        }

        $this->renderAdmin('admin/lens-prices/packages', [
            'pageTitle'   => 'Gói chiết suất — Quản trị',
            'packages'    => Database::fetchAll(
                'SELECT * FROM lens_packages ORDER BY sort_order ASC, id ASC'
            ),
            'priceCounts' => $priceCounts,
            'editing'     => $editing,
            'nextSort'    => LensModel::nextPackageSort(),
            // Cùng mức quyền với bảng giá: đây vẫn là dữ liệu catalog.
            'canEdit'     => UserModel::hasRole($this->userId, 'admin')
                          || UserModel::hasRole($this->userId, 'manager'),
        ]);
    }

    /** Thêm mới hoặc sửa một gói (POST /quan-tri/gia-trong/goi/luu). */
    public function savePackage(): void
    {
        $this->requirePost(self::PKG);
        $this->requireManager(self::PKG);

        if (!LensModel::packagesEditable()) {
            flash('admin_error', 'Cơ sở dữ liệu chưa được nâng cấp cho phần này.');
            redirect(self::PKG);
        }

        /* `cu` rỗng = thêm mới, có giá trị = đang sửa gói đó. Tách khỏi ô `id`
           vì khi sửa thì ô mã bị khoá (readonly) — mà readonly là chuyện của
           trình duyệt, ai cũng gửi POST khác đi được. Chốt ở `cu` nghĩa là
           đường sửa không bao giờ đổi được mã, kể cả khi POST nói ngược lại. */
        $cu   = trim((string) ($_POST['cu'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));
        $sort = (int) ($_POST['sort_order'] ?? 0);

        if (utf8Length($name) < 2 || utf8Length($name) > 160) {
            flash('admin_error', 'Tên gói phải từ 2 đến 160 ký tự.');
            redirect(self::PKG);
        }

        if (utf8Length($desc) > 255) {
            flash('admin_error', 'Mô tả không được quá 255 ký tự.');
            redirect(self::PKG);
        }

        // SMALLINT của cột, chặn ở đây để không nhận lỗi tràn số từ MySQL
        if ($sort < 0 || $sort > 32767) {
            flash('admin_error', 'Thứ tự phải là số từ 0 đến 32767.');
            redirect(self::PKG);
        }

        // ---- Sửa ----
        if ($cu !== '') {
            if (LensModel::findPackageRow($cu) === null) {
                flash('admin_error', 'Không tìm thấy gói chiết suất.');
                redirect(self::PKG);
            }

            LensModel::updatePackage($cu, $name, $desc, $sort);

            flash('admin_success', 'Đã cập nhật gói chiết suất.');
            redirect(self::PKG);
        }

        // ---- Thêm mới ----
        $id = strtolower(trim((string) ($_POST['id'] ?? '')));

        /*
         * MÃ LÀ KHOÁ THẬT, KHÔNG PHẢI MỘT CÁI NHÃN.
         *
         * Nó đi vào `order_items.lens_id` của mọi đơn có gói này và vào
         * `lens_prices.lens_package`. Nên siết đúng bộ ký tự an toàn cho một
         * khoá: chữ thường, số, gạch nối; bắt đầu bằng chữ hoặc số. Không cho
         * chữ hoa vì so khớp ở PHP là so chuỗi phân biệt hoa thường —
         * 'Blue-161' và 'blue-161' sẽ thành hai gói khác nhau mà nhìn bằng mắt
         * thì y hệt.
         */
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $id)) {
            flash('admin_error', 'Mã gói chỉ gồm chữ thường không dấu, số và gạch nối (tối đa 40 ký tự).');
            redirect(self::PKG);
        }

        if (LensModel::findPackageRow($id) !== null) {
            flash('admin_error', sprintf('Mã "%s" đã được dùng cho gói khác.', $id));
            redirect(self::PKG);
        }

        LensModel::createPackage($id, $name, $desc, $sort);

        flash('admin_success', 'Đã thêm gói chiết suất mới.');
        redirect(self::PKG);
    }

    /** Xoá một gói (POST /quan-tri/gia-trong/goi/xoa). */
    public function deletePackage(): void
    {
        $this->requirePost(self::PKG);
        $this->requireManager(self::PKG);

        /* Kiểm bảng TRƯỚC khi kiểm bản ghi. findPackageRow() trả null cho cả
           hai trường hợp, nên bỏ qua bước này thì máy chưa nâng cấp sẽ nhận câu
           "Không tìm thấy gói chiết suất." — một câu đúng về mặt kỹ thuật mà
           sai hẳn về thứ người dùng cần làm tiếp. */
        if (!LensModel::packagesEditable()) {
            flash('admin_error', 'Cơ sở dữ liệu chưa được nâng cấp cho phần này.');
            redirect(self::PKG);
        }

        $id = (string) ($_POST['id'] ?? '');

        if (LensModel::findPackageRow($id) === null) {
            flash('admin_error', 'Không tìm thấy gói chiết suất.');
            redirect(self::PKG);
        }

        /*
         * KHÔNG CHO XOÁ GÓI CUỐI CÙNG.
         *
         * Bảng rỗng thì bước "Chọn loại tròng kính" của hộp mua hàng không còn
         * lựa chọn nào, và khách không mua nổi một cặp kính có độ — một cú bấm
         * trong khu quản trị làm đứt luồng bán hàng chính, mà không có gì trên
         * màn hình nói trước điều đó.
         *
         * (LensModel::packages() có lùi về config khi bảng rỗng, nhưng đó là
         * lưới an toàn cho lúc chưa nâng cấp, không phải một trạng thái đáng
         * để người dùng rơi vào có chủ ý: lúc ấy khu quản trị hiện năm gói mà
         * không gói nào sửa được.)
         */
        if ((int) Database::fetchValue('SELECT COUNT(*) FROM lens_packages') <= 1) {
            flash('admin_error', 'Phải còn ít nhất một gói chiết suất — khách cần có gì đó để chọn.');
            redirect(self::PKG);
        }

        $soGia = LensModel::countPricesOf($id);

        LensModel::deletePackage($id);

        /* Nói ra số ô giá vừa mất theo. Người bấm đã được hỏi lại kèm con số
           này, nhưng câu báo sau khi xong mới là thứ họ đối chiếu — và mất một
           bảng giá đã gõ tay là việc không lấy lại được. */
        flash('admin_success', $soGia > 0
            ? sprintf('Đã xoá gói chiết suất và %d mức giá của nó.', $soGia)
            : 'Đã xoá gói chiết suất.');
        redirect(self::PKG);
    }

    /**
     * Đổi chỗ một gói chiết suất với gói liền trên/dưới
     * (POST /quan-tri/gia-trong/goi/thu-tu).
     *
     * Thứ tự này là thứ tự khách thấy ở bước "Chọn loại tròng kính" trong hộp
     * mua hàng — gói đứng đầu là gói được chọn sẵn. Nên nó quyết định gói nào
     * bán được nhiều nhất, không phải chuyện sắp xếp cho gọn mắt.
     *
     * Bảng `lens_packages` dùng khoá chính là MÃ gói (chuỗi) chứ không phải id
     * số, nên dãy truyền vào ThuTuService là dãy mã.
     */
    public function movePackage(): void
    {
        $this->requirePost(self::PKG);
        $this->requireManager(self::PKG);

        $huong = ThuTuService::huongTuRequest($_POST['huong'] ?? null);

        if ($huong === '') {
            flash('admin_error', 'Hướng di chuyển không hợp lệ.');
            redirect(self::PKG);
        }

        $ids = array_column(
            Database::fetchAll('SELECT id FROM lens_packages ORDER BY sort_order ASC, id ASC'),
            'id'
        );

        if (ThuTuService::doiCho('lens_packages', $ids, (string) ($_POST['id'] ?? ''), $huong)) {
            flash('admin_success', 'Đã đổi thứ tự gói chiết suất.');
        }

        redirect(self::PKG);
    }
}
