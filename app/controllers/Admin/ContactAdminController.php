<?php

/**
 * ContactAdminController — yêu cầu liên hệ (/quan-tri/lien-he).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TRANG NÀY LÀ SỔ LƯU TRỮ, KHÔNG PHẢI HÀNG CHỜ — đổi ngày 2026-08-26.
 *
 * Trước đây mỗi yêu cầu có một ô chọn ba nấc (Mới -> Đang xử lý -> Đã xử lý).
 * Bỏ hẳn, vì không ai đứng canh nó: nhân viên cửa hàng kính ngồi ở quầy và trả
 * lời khách bằng Zalo, không ngồi trước bảng quản trị chờ có dòng mới. Một
 * hàng chờ không người trực thì TRÔNG như đã có người lo, mà thật ra không.
 *
 * Nay yêu cầu chạy thẳng sang Zalo của CSKH ngay lúc khách bấm gửi
 * (ContactModel::submit -> Zalo::contact), và trang này còn lại đúng hai việc:
 *
 *   1. TRA CỨU — khách gọi lại hỏi "tôi đã nhắn tuần trước", nhân viên mở ra
 *      đọc nguyên văn thứ họ đã gửi.
 *   2. GỬI LẠI — cho những tin Zalo nuốt mất. ZNS hỏng IM LẶNG (token hết hạn,
 *      mẫu tin bị gỡ, mạng ra ngoài bị chặn), nên phải có một đường đẩy tay.
 *
 * KHÔNG có nút xoá, cố ý: đây là lời khách nói với cửa hàng, và xoá nó đi thì
 * không còn gì đối chiếu khi hai bên nhớ khác nhau về một cuộc trao đổi.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class ContactAdminController extends AdminController
{
    private const BASE = '/quan-tri/lien-he';

    public function index(): void
    {
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $result = ContactModel::paginateAdmin($page, 20);

        $this->renderAdmin('admin/contacts/index', [
            'pageTitle'  => 'Liên hệ — Quản trị',
            'contacts'   => $result['items'],
            'total'      => $result['total'],
            'page'       => $result['page'],
            'totalPages' => $result['totalPages'],
            'chuaDay'    => ContactModel::countChuaDayZalo(),
            // Chưa chạy migration thì cả trang vẫn đọc được, chỉ là không có
            // cột nào để biết tin đã đi chưa — view ẩn cột đó đi thay vì đổ lỗi.
            'coCotZalo'  => Database::columnExists('contact_requests', 'zalo_sent_at'),
        ]);
    }

    /**
     * Đẩy (hoặc đẩy lại) một yêu cầu sang Zalo CSKH.
     *
     * Cho phép bấm CẢ KHI đã gửi thành công trước đó. Nghe thì thừa, nhưng ca
     * thật là: tin đã tới máy CSKH rồi máy đó hỏng, hoặc người trực xoá nhầm
     * cuộc trò chuyện. Chặn lại bằng "đã gửi rồi" thì người duy nhất bị chặn là
     * người đang cố sửa một việc hỏng.
     */
    public function sendZalo(): void
    {
        $this->requirePost(self::BASE);

        $id      = (string) ($_POST['id'] ?? '');
        $contact = ContactModel::find($id);

        if ($contact === null) {
            flash('admin_error', 'Không tìm thấy yêu cầu liên hệ.');
            redirect(self::BASE);
        }

        if (Zalo::contact($contact)) {
            ContactModel::markZaloSent($id);
            flash('admin_success', 'Đã đẩy yêu cầu sang Zalo CSKH.');
            redirect(self::BASE);
        }

        /* NÓI RA THỨ PHẢI KIỂM, đừng chỉ nói "thất bại".

           Người đọc câu này đang đứng ở quầy và không có cách nào tự đoán
           nguyên nhân — mà nguyên nhân gần như luôn là một trong hai thứ dưới
           đây, cả hai đều là việc cấu hình chứ không phải việc bấm lại. */
        flash('admin_error',
            'Không đẩy được sang Zalo. Kiểm tra ZALO_ZNS_TEMPLATE_CONTACT '
            . 'và token OA trong .env — chi tiết nằm ở error log.');
        redirect(self::BASE);
    }
}
