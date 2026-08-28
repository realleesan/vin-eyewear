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
        $page = max(1, (int) ($_GET['page'] ?? 1));

        /* Ô tìm và dải viên lọc thêm theo bản thiết kế "Liên hệ.dc.html".
           Giá trị lạ ở ?zalo= thì coi như không lọc — một đường dẫn bị sửa tay
           nên trả về danh sách đầy đủ, không nên trả về trang lỗi. */
        $q    = trim((string) ($_GET['q'] ?? ''));
        $zalo = (string) ($_GET['zalo'] ?? '');

        if (!in_array($zalo, ['chua', 'da'], true)) {
            $zalo = '';
        }

        $result = ContactModel::paginateAdmin($page, 20, $q, $zalo);

        $this->renderAdmin('admin/contacts/index', [
            'pageTitle'  => 'Liên hệ — Quản trị',
            'contacts'   => $result['items'],
            'total'      => $result['total'],
            'page'       => $result['page'],
            'totalPages' => $result['totalPages'],
            'q'          => $q,
            'zalo'       => $zalo,
            'zaloCounts' => ContactModel::zaloCounts($q),
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

    /**
     * Đẩy MỌI yêu cầu chưa tới Zalo sang CSKH một lượt
     * (POST /quan-tri/lien-he/zalo-tat-ca).
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VÌ SAO CÓ NÚT NÀY
     *
     * Yêu cầu kẹt lại gần như luôn kẹt theo LÔ, không lẻ tẻ: token OA hết hạn
     * hoặc thiếu khai template thì mọi yêu cầu trong quãng đó đều nằm lại. Sửa
     * xong cấu hình mà phải bấm "Gửi sang Zalo" từng dòng một là bắt người ta
     * lặp đúng một thao tác mười lăm lần, và lần thứ mười hai thì họ bỏ dở.
     *
     * KHÔNG DỪNG Ở LỖI ĐẦU TIÊN. Mỗi yêu cầu là một lượt gọi độc lập tới Zalo;
     * một cái hỏng (số điện thoại rác chẳng hạn) không nói gì về mười cái sau.
     * Dừng lại ở đó là bỏ lại mười khách vẫn đang chờ.
     *
     * CHỈ ĐÁNH DẤU CÁI NÀO GỬI ĐƯỢC. Cái nào trượt vẫn nguyên trạng thái "chưa
     * gửi" và vẫn còn nút riêng ở dòng của nó — đánh dấu bừa cả loạt là xoá mất
     * dấu vết duy nhất cho biết ai chưa được gọi lại.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function sendZaloAll(): void
    {
        $this->requirePost(self::BASE);

        if (!Database::columnExists('contact_requests', 'zalo_sent_at')) {
            flash('admin_error', 'Chưa ghi nhận được việc đẩy Zalo — cơ sở dữ liệu còn thiếu cột.');
            redirect(self::BASE);
        }

        /* Giới hạn 200: nút này chạy đồng bộ trong một request, mỗi yêu cầu là
           một lượt gọi mạng. Hàng chờ mà dài hơn thế thì bấm thêm một lần nữa
           — chậm hơn một cú bấm, nhưng không có lượt tải nào chết giữa chừng
           vì hết thời gian và để lại một nửa số dòng đã đánh dấu. */
        $chuaDay = Database::fetchAll(
            'SELECT * FROM contact_requests
              WHERE zalo_sent_at IS NULL
              ORDER BY created_at ASC
              LIMIT 200'
        );

        if ($chuaDay === []) {
            flash('admin_success', 'Không còn yêu cầu nào đang chờ đẩy.');
            redirect(self::BASE);
        }

        $xong = 0;

        foreach ($chuaDay as $contact) {
            if (Zalo::contact($contact)) {
                ContactModel::markZaloSent((string) $contact['id']);
                $xong++;
            }
        }

        $truot = count($chuaDay) - $xong;

        if ($xong === 0) {
            /* Không cái nào đi được nghĩa là cấu hình vẫn hỏng, không phải dữ
               liệu xấu. Nói thẳng ra chỗ phải kiểm thay vì "gửi thất bại". */
            flash(
                'admin_error',
                'Không đẩy được yêu cầu nào. Kiểm ZALO_ZNS_TEMPLATE_CONTACT trong .env '
                . 'và hạn token OA ở config/zalo.php.'
            );
            redirect(self::BASE);
        }

        flash(
            'admin_success',
            $truot === 0
                ? sprintf('Đã đẩy %d yêu cầu sang Zalo CSKH.', $xong)
                : sprintf(
                    'Đã đẩy %d yêu cầu sang Zalo CSKH. Còn %d yêu cầu chưa đi được — bấm "Gửi sang Zalo" ở từng dòng để xem lý do.',
                    $xong,
                    $truot
                )
        );

        redirect(self::BASE);
    }
}
