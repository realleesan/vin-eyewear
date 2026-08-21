<?php

/**
 * LensPriceAdminController — bảng giá tròng (/quan-tri/gia-trong).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỘT LƯỚI, MỘT NÚT LƯU — KHÔNG PHẢI CRUD NHƯ CÁC MÀN KHÁC
 *
 * Mọi màn quản trị khác ở đây là danh sách + form thêm/sửa/xoá từng bản ghi.
 * Màn này thì không, vì thứ nó sửa không phải một danh sách: đó là một LƯỚI có
 * hình dạng CỐ ĐỊNH — số kiểu tròng nhân số gói chiết suất, cả hai đều khai
 * trong config/taxonomy.php. Không ai "thêm một giá mới" hay "xoá một giá";
 * người ta mở bảng ra, sửa vài con số, rồi lưu.
 *
 * Nên ở đây một <form> phủ cả lưới và đúng một nút "Lưu bảng giá". Mười lăm
 * form với mười lăm nút thì đổi bảng giá đầu tháng thành mười lăm lượt tải
 * trang, và sửa xong quên bấm một ô là một giá cũ ở lại mà không ai thấy.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class LensPriceAdminController extends AdminController
{
    private const BASE = '/quan-tri/gia-trong';

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
}
