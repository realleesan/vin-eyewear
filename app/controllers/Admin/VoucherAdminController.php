<?php

/**
 * VoucherAdminController — mã giảm giá (/quan-tri/ma-giam-gia).
 *
 * Bảng `vouchers` ra đời cùng trang tài khoản (mục "Ưu đãi của tôi") và ô nhập
 * mã ở giỏ hàng, nhưng tới giờ chỉ tạo/sửa được bằng SQL tay. Màn hình này lấp
 * chỗ đó.
 *
 * Giới hạn ở admin/manager giống các màn hình catalog khác (sản phẩm, danh
 * mục, cơ sở): mã giảm giá là tiền, không phải nội dung.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KIỂM DỮ LIỆU Ở ĐÂY LÀ ĐỂ CHỐNG GÕ NHẦM, KHÔNG PHẢI ĐỂ CHỐNG TẤN CÔNG
 *
 * Người vào được màn hình này đã là quản lý. Các phép kiểm dưới đây tồn tại vì
 * một con số gõ nhầm ở đây thành tiền mất thật: "giảm 100%" thay vì "giảm 10%",
 * hay quên điền hạn dùng cho một mã lẽ ra chỉ chạy một tuần.
 *
 * Việc chống gian lận nằm ở chỗ khác — VoucherModel::evaluate(), chạy lại mỗi
 * lần hiện giỏ hàng và một lần nữa bên trong transaction đặt hàng.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class VoucherAdminController extends AdminController
{
    private const BASE = '/quan-tri/ma-giam-gia';

    /** Trần phần trăm giảm. 100% nghĩa là cho không, gần như luôn là gõ nhầm. */
    private const MAX_PERCENT = 90;

    public function index(): void
    {
        $this->renderAdmin('admin/vouchers/index', [
            'pageTitle' => 'Mã giảm giá — Quản trị',
            'vouchers'  => VoucherModel::adminList(),
            'types'     => VoucherModel::TYPES,
            'canEdit'   => UserModel::hasRole($this->userId, 'admin')
                        || UserModel::hasRole($this->userId, 'manager'),
            'editing'   => isset($_GET['sua']) ? VoucherModel::find((string) $_GET['sua']) : null,
        ]);
    }

    public function save(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id   = (string) ($_POST['id'] ?? '');
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $type = (string) ($_POST['discount_type'] ?? '');

        if (!preg_match('/^[A-Z0-9_]{3,40}$/', $code)) {
            $this->fail('Mã chỉ gồm chữ IN HOA, số và gạch dưới (3–40 ký tự).');
        }

        // Khách gõ mã ở giỏ hàng không phân biệt hoa thường, nên hai mã chỉ
        // khác nhau ở kiểu chữ là cùng một mã. Đã strtoupper() ở trên nên phép
        // so sánh này bắt được cả trường hợp đó.
        $clash = VoucherModel::findBy('code', $code);
        if ($clash !== null && $clash['id'] !== $id) {
            $this->fail(sprintf('Mã "%s" đã tồn tại.', $code));
        }

        if (!isset(VoucherModel::TYPES[$type])) {
            $this->fail('Kiểu giảm giá không hợp lệ.');
        }

        $tag   = trim((string) ($_POST['tag'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));

        if (utf8Length($tag) < 1 || utf8Length($tag) > 16) {
            $this->fail('Nhãn ngắn phải từ 1 đến 16 ký tự (vd: -10%, 100K, FS).');
        }

        if (utf8Length($title) < 4) {
            $this->fail('Vui lòng nhập tên chương trình khuyến mãi.');
        }

        $value       = max(0, (int) ($_POST['discount_value'] ?? 0));
        $minOrder    = max(0, (int) ($_POST['min_order'] ?? 0));
        $maxDiscount = trim((string) ($_POST['max_discount'] ?? ''));
        $maxUses     = trim((string) ($_POST['max_uses'] ?? ''));
        $expires     = trim((string) ($_POST['expires_at'] ?? ''));

        // Mỗi kiểu giảm giá đọc `discount_value` một cách khác nhau, nên mỗi
        // kiểu có phép kiểm riêng thay vì một luật chung cho cả ba.
        switch ($type) {
            case 'percent':
                if ($value < 1 || $value > self::MAX_PERCENT) {
                    $this->fail(sprintf('Phần trăm giảm phải từ 1 đến %d.', self::MAX_PERCENT));
                }
                break;

            case 'amount':
                if ($value < 1000) {
                    $this->fail('Số tiền giảm phải từ 1.000₫ trở lên.');
                }

                if ($minOrder > 0 && $value >= $minOrder) {
                    $this->fail('Số tiền giảm phải nhỏ hơn giá trị đơn tối thiểu.');
                }
                break;

            case 'shipping':
                // Mã miễn ship không mang số tiền: nó đưa phí vận chuyển về 0.
                // Ép về 0 thay vì báo lỗi — người nhập không cần biết chi tiết đó.
                $value = 0;
                break;
        }

        // Trần số tiền giảm CHỈ có nghĩa với phần trăm. Để lại giá trị cũ khi
        // đổi mã sang kiểu khác sẽ thành một con số nằm im trong CSDL mà không
        // hàm nào đọc — lần sau đọc bảng sẽ tưởng nó đang có tác dụng.
        $maxDiscountVal = ($type === 'percent' && $maxDiscount !== '' && (int) $maxDiscount > 0)
            ? (int) $maxDiscount : null;

        $maxUsesVal = ($maxUses !== '' && (int) $maxUses > 0) ? (int) $maxUses : null;

        if ($expires !== '' && date_create($expires) === false) {
            $this->fail('Hạn sử dụng không hợp lệ.');
        }

        $data = [
            'code'           => $code,
            'tag'            => $tag,
            'title'          => $title,
            'condition_text' => trim((string) ($_POST['condition_text'] ?? '')) ?: null,
            'discount_type'  => $type,
            'discount_value' => $value,
            'min_order'      => $minOrder,
            'max_discount'   => $maxDiscountVal,
            'expires_at'     => $expires ?: null,
            'is_active'      => isset($_POST['is_active']) ? 1 : 0,
            'is_public'      => isset($_POST['is_public']) ? 1 : 0,
            'is_reward'      => isset($_POST['is_reward']) ? 1 : 0,
            'max_uses'       => $maxUsesVal,
        ];

        if ($id !== '' && VoucherModel::exists(['id' => $id])) {
            // `used_count` CỐ TÌNH không nằm trong $data: nó là số đếm do hệ
            // thống ghi mỗi lần đặt hàng thành công, không phải trường nhập.
            VoucherModel::update($id, $data);
            flash('admin_success', sprintf('Đã cập nhật mã %s.', $code));
        } else {
            $id = VoucherModel::insert($data);
            flash('admin_success', sprintf('Đã tạo mã %s.', $code));
        }

        /* CHỈ MỘT MÃ LÀM QUÀ TẶNG. Tắt cờ ở mọi mã khác NGAY SAU khi lưu, chứ
           không bắt nhân viên tự nhớ đi tắt mã cũ — quên một cái là hai mã
           cùng bật, và lúc đó VoucherModel::reward() lấy đại một trong hai
           theo thứ tự CSDL trả về. Không sai đến mức hỏng, nhưng cửa hàng sẽ
           không hiểu vì sao khách nhận mã này chứ không phải mã kia.

           Chạy cả khi vừa TẮT cờ: lúc đó $data['is_reward'] = 0 nên câu này
           không tắt nhầm ai — điều kiện `id <> :id` chỉ chừa lại chính nó. */
        if ((int) $data['is_reward'] === 1 && $id !== '') {
            VoucherModel::clearRewardFlag((string) $id);
        }

        redirect(self::BASE);
    }

    public function delete(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id      = (string) ($_POST['id'] ?? '');
        $voucher = VoucherModel::find($id);

        if ($voucher === null) {
            $this->fail('Không tìm thấy mã.');
        }

        // orders.voucher_id là ON DELETE SET NULL, nên xoá KHÔNG gây lỗi CSDL —
        // nó lặng lẽ cắt đứt liên kết giữa hoá đơn cũ và chương trình đã giảm
        // giá cho nó. Sổ sách vẫn còn số tiền trong cột `discount`, nhưng không
        // ai trả lời được "khoản giảm đó thuộc chương trình nào" nữa.
        //
        // Cùng cách xử lý với cơ sở còn lịch hẹn — xem StoreAdminController.
        // find() trả về dòng thô, không có order_count như adminList() — nên
        // phải đếm lại ở đây thay vì đọc từ $voucher.
        $used = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM orders WHERE voucher_id = :id',
            ['id' => $id]
        );

        if ($used > 0) {
            $this->fail(sprintf(
                'Không xoá được: đã có %d đơn dùng mã này. Hãy bỏ tick "đang bật" thay vì xoá.',
                $used
            ));
        }

        VoucherModel::delete($id);

        flash('admin_success', sprintf('Đã xoá mã %s.', $voucher['code']));
        redirect(self::BASE);
    }

    /**
     * Phát một mã riêng cho toàn bộ khách hàng hiện có.
     *
     * Chỉ có nghĩa với mã KHÔNG công khai: mã công khai thì ai gõ đúng cũng
     * dùng được, không cần phát cho ai cả.
     */
    public function grant(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $voucher = VoucherModel::find((string) ($_POST['id'] ?? ''));

        if ($voucher === null) {
            $this->fail('Không tìm thấy mã.');
        }

        if ((int) $voucher['is_public'] === 1) {
            $this->fail('Mã công khai không cần phát — ai gõ đúng mã cũng dùng được.');
        }

        $added = VoucherModel::grantToAll($voucher['id']);

        flash('admin_success', $added > 0
            ? sprintf('Đã phát mã %s cho %d khách hàng.', $voucher['code'], $added)
            : sprintf('Mọi khách hàng đã có mã %s từ trước.', $voucher['code']));

        redirect(self::BASE);
    }

    // ========================================================================
    // NỘI BỘ
    // ========================================================================

    /**
     * Báo lỗi rồi quay về danh sách.
     *
     * KHÔNG nhớ lại những gì vừa gõ: form này nằm ngay dưới bảng và người dùng
     * là nhân viên, nên gõ lại nhanh hơn là mang theo một mảng $_SESSION nữa —
     * cùng cách làm với các màn hình CRUD quản trị khác.
     */
    private function fail(string $message): never
    {
        flash('admin_error', $message);
        redirect(self::BASE);
    }

    /**
     * Bật hoặc tắt một mã giảm giá (POST .../bat-tat).
     *
     * Tắt KHÔNG phải xoá: mã đã tắt vẫn còn trong bảng, đơn cũ đã áp nó vẫn
     * tra ngược được, và bật lại là một cú bấm. Đó là thứ cần khi một chương
     * trình tạm dừng — còn xoá thì chỉ dành cho mã gõ nhầm, và nút Xoá cũng
     * chỉ hiện khi mã chưa đơn nào dùng.
     *
     * Bật lại một mã ĐÃ HẾT HẠN cũng cho phép: cột `is_active` và ngày hết hạn
     * là hai điều kiện độc lập, và người bấm có thể đang định gia hạn ngay sau
     * đó. Chặn ở đây chỉ bắt họ làm hai việc theo đúng một thứ tự.
     */
    public function toggle(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id = (string) ($_POST['id'] ?? '');
        $ma = VoucherModel::find($id);

        if ($ma === null) {
            flash('admin_error', 'Không tìm thấy mã giảm giá.');
            redirect(self::BASE);
        }

        $bat = (int) $ma['is_active'] !== 1;
        VoucherModel::update($id, ['is_active' => $bat ? 1 : 0]);

        flash(
            'admin_success',
            sprintf($bat ? 'Đã bật mã %s.' : 'Đã tắt mã %s.', $ma['code'])
        );

        redirect(self::BASE);
    }
}
