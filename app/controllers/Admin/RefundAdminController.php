<?php

/**
 * app/controllers/Admin/RefundAdminController.php — duyệt hoàn tiền cọc.
 *
 * SRS v2.1.0, UC-04 · FR-DH-14.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * XEM THÌ MỌI NHÂN VIÊN, DUYỆT THÌ CHỈ QUẢN TRỊ VIÊN — BR-DH-14.4
 *
 * Hai mức khác nhau, cố ý. Nhân viên trực quầy cần trả lời được câu "tiền của
 * tôi đến đâu rồi" khi khách gọi điện, mà không cần quyền chi tiền. Bắt họ đi
 * tìm Quản trị viên chỉ để ĐỌC một con số là biến một câu trả lời một phút
 * thành một cuộc gọi lại.
 *
 * Chặn ở từng action ghi, không ở constructor — cùng lối với mọi màn khác của
 * khu quản trị.
 */

class RefundAdminController extends AdminController
{
    private const BASE = '/quan-tri/hoan-tien';

    public function index(): void
    {
        $status = (string) ($_GET['status'] ?? '');

        if ($status !== '' && !isset(RefundRequestModel::STATUSES[$status])) {
            $status = '';
        }

        $this->renderAdmin('admin/refunds/index', [
            'pageTitle' => 'Hoàn tiền cọc — Quản trị',
            'refunds'   => RefundRequestModel::danhSach($status),
            'counts'    => RefundRequestModel::demTheoTrangThai(),
            'status'    => $status,
            'statuses'  => RefundRequestModel::STATUSES,
            /* CHỈ QUẢN TRỊ VIÊN THẤY NÚT DUYỆT — cờ vẽ, khớp với phép chặn ở ba
               action ghi bên dưới. Nhân viên vẫn xem được toàn bộ danh sách. */
            'canDuyet'  => UserModel::hasRole($this->userId, 'admin'),
            /* ĐƠN RƠI QUA KHE — xem RefundRequestModel::donCocChuaCoYeuCau().

               taoChoDonHuy() cố ý KHÔNG chặn luồng huỷ đơn khi việc ghi sổ hoàn
               tiền hỏng, nên phải có chỗ tìm ra những đơn ấy. Khác 0 nghĩa là
               có khách đang chờ tiền mà hàng chờ duyệt không biết. */
            'soSot'     => RefundRequestModel::demSot(),
            // Vài mã đơn để dải cảnh báo bắt đầu làm được ngay, không chỉ báo
            // là "có việc". Câu đếm ở trên là câu riêng — xem demSot().
            'sot'       => RefundRequestModel::donCocChuaCoYeuCau(),
            'sanSang'   => RefundRequestModel::available(),
            'xem'       => $this->dangXem(),
        ]);
    }

    /** Yêu cầu đang mở trong ngăn kéo, hoặc null. */
    private function dangXem(): ?array
    {
        $id = trim((string) ($_GET['xem'] ?? ''));

        return $id === '' ? null : RefundRequestModel::chiTiet($id);
    }

    /** Địa chỉ quay về, giữ nguyên bộ lọc và ngăn kéo đang mở. */
    private function ve(): string
    {
        return currentUrlWithout([]) !== '' ? currentUrlWithout([]) : self::BASE;
    }

    public function approve(): void
    {
        $this->requirePost(self::BASE);
        $this->requireAdmin(self::BASE);

        $id  = (string) ($_POST['id'] ?? '');
        $ket = RefundRequestModel::duyet(
            $id,
            $this->userId,
            ($_POST['loi_cua_hang'] ?? '') === '1',
            (string) ($_POST['so_tien'] ?? ''),
            (string) ($_POST['ly_do'] ?? '')
        );

        if (!$ket['ok']) {
            flash('admin_error', $ket['error']);
            redirect(self::BASE . '?xem=' . rawurlencode($id));
        }

        $yc = RefundRequestModel::chiTiet($id);

        /* VẾT GẮN VÀO CHỦ ĐƠN, không phải NULL.

           AuditLogModel join `profiles` theo `user_id`, và màn Lịch sử thao
           tác dựng liên kết tới hồ sơ khách từ cột đó. Ghi NULL nghĩa là ba
           dòng quan trọng nhất về tiền của một khách lại là ba dòng duy nhất
           không lọc và không tra ngược về họ được — trong khi dòng
           'order.cancel' ngay cạnh thì có. NULL với đơn khách vãng lai, đúng
           như quy ước đã ghi ở schema. */
        AuditLogModel::write($yc['order_user_id'] ?? null, 'refund.approve', sprintf(
            'Duyệt hoàn %s cho đơn %s',
            money((int) ($ket['amount'] ?? 0)),
            (string) ($yc['order_code'] ?? '?')
        ));

        flash('admin_success', 'Đã duyệt hoàn ' . money((int) ($ket['amount'] ?? 0))
            . '. Chuyển tiền cho khách xong thì quay lại bấm «Đã hoàn tiền».');
        redirect(self::BASE);
    }

    public function reject(): void
    {
        $this->requirePost(self::BASE);
        $this->requireAdmin(self::BASE);

        $id  = (string) ($_POST['id'] ?? '');
        $ket = RefundRequestModel::tuChoi($id, $this->userId, (string) ($_POST['ly_do'] ?? ''));

        if (!$ket['ok']) {
            flash('admin_error', $ket['error']);
            redirect(self::BASE . '?xem=' . rawurlencode($id));
        }

        $yc = RefundRequestModel::chiTiet($id);

        // Chủ đơn, không NULL — lý do ở approve().
        AuditLogModel::write($yc['order_user_id'] ?? null, 'refund.reject',
            'Từ chối hoàn tiền đơn ' . (string) ($yc['order_code'] ?? '?'));

        flash('admin_success', 'Đã ghi nhận từ chối hoàn tiền.');
        redirect(self::BASE);
    }

    /**
     * Ghi nhận tiền ĐÃ trả lại khách — bước 4 của UC-04.
     *
     * Hệ thống không chuyển tiền (BR-DH-14.5). Nút này chỉ ghi lại rằng việc
     * chuyển khoản đã làm xong ở ngân hàng, kèm NGÀY do người bấm nhập.
     */
    public function markRefunded(): void
    {
        $this->requirePost(self::BASE);
        $this->requireAdmin(self::BASE);

        $id  = (string) ($_POST['id'] ?? '');
        $ket = RefundRequestModel::danhDauDaHoan(
            $id,
            $this->userId,
            (string) ($_POST['ngay'] ?? ''),
            (string) ($_POST['ghi_chu'] ?? '')
        );

        if (!$ket['ok']) {
            flash('admin_error', $ket['error']);
            redirect(self::BASE . '?xem=' . rawurlencode($id));
        }

        $yc = RefundRequestModel::chiTiet($id);

        // Chủ đơn, không NULL — lý do ở approve().
        AuditLogModel::write($yc['order_user_id'] ?? null, 'refund.paid', sprintf(
            'Đã hoàn %s cho đơn %s ngày %s',
            money((int) ($yc['approved_amount'] ?? 0)),
            (string) ($yc['order_code'] ?? '?'),
            formatDate((string) ($yc['refunded_on'] ?? ''))
        ));

        flash('admin_success', 'Đã ghi nhận hoàn tiền.');
        redirect(self::BASE);
    }
}
