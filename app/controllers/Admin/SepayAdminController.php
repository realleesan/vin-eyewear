<?php

/**
 * Admin/SepayAdminController.php — Sổ giao dịch ngân hàng.
 *
 *   GET  /quan-tri/doi-soat        index()  danh sách, lọc, tìm, phân trang
 *   POST /quan-tri/doi-soat/gan    gan()    gắn một giao dịch vào đơn
 *
 * SRS v2.1.0 — FR-SG-01..07, Quyết định E06.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MÀN HÌNH NÀY LÀ CẢ NỘI DUNG CỦA E06
 *
 * SRS mở đầu mục 4.2 bằng một câu chẩn đoán: *"Hệ thống đã ghi đầy đủ mọi giao
 * dịch nhận được từ ngân hàng, nhưng không có màn hình nào đọc ra. Hệ quả:
 * khách chuyển thiếu hoặc chuyển sai nội dung thì giao dịch nằm im, không ai
 * thấy, đơn vẫn hiện chưa thanh toán."*
 *
 * Bảng `sepay_transactions` đã chạy từ 22/08/2026. Mọi khoản tiền về đều nằm
 * trong đó, kể cả những khoản webhook không khớp được vào đơn nào. Cho tới file
 * này, cách duy nhất đọc chúng là mở phpMyAdmin.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỌI NHÂN VIÊN — XEM VÀ CẢ GẮN ĐƠN. SRS mục 5.2.2 ghi rõ cả hai dòng là "NV",
 * và dòng gắn kèm ghi chú "Có ghi vết".
 *
 * Khác hẳn màn Hoàn tiền cọc ngay bên cạnh, nơi xem là NV còn duyệt là QT. Sự
 * khác nhau ấy hợp lý: duyệt hoàn tiền là quyết định CHI một khoản ra khỏi tài
 * khoản cửa hàng, còn gắn giao dịch chỉ là nhận ra một khoản ĐÃ VỀ thuộc về ai.
 * Việc thứ hai là đối chiếu sổ sách, và người làm nó là người trực quầy đang
 * nghe khách nói "em chuyển rồi mà".
 *
 * Bù lại bằng vết: SepayModel::ganDon() ghi 'sepay.link_order' với đủ hai con
 * số — khoản vừa gắn và tổng đã nhận sau khi cộng dồn.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class SepayAdminController extends AdminController
{
    private const BASE = '/quan-tri/doi-soat';

    public function index(): void
    {
        $loc = (string) ($_GET['loc'] ?? '');

        if (!isset(SepayModel::KET_QUA[$loc])) {
            $loc = '';
        }

        $q     = trim((string) ($_GET['q'] ?? ''));
        $trang = SepayModel::danhSach($loc, $q, max(1, (int) ($_GET['page'] ?? 1)));

        /* MÃ ĐƠN ĐANG CHỜ XÁC NHẬN — bước hai của việc gắn tay.

           gan() không gắn ngay ở lượt POST đầu: nó tra đơn, rồi quay lại đây
           với ?ma=<mã> để ngăn kéo hiện TÊN KHÁCH, TỔNG TIỀN và số đã nhận
           trước khi hỏi lần cuối. Lý do đầy đủ ở gan(). */
        $maChoXacNhan = trim((string) ($_GET['ma'] ?? ''));

        /* NGĂN KÉO MỞ BẰNG ?xem=<id> — cùng lối với mọi màn khác. Ruột nó là
           một giao dịch của chính danh sách đang xem, nên giữ trong cùng URL
           nghĩa là đóng lại là về đúng bộ lọc, đúng từ khoá, đúng trang. */
        $xemId = (string) ($_GET['xem'] ?? '');
        $xem   = $xemId !== '' ? SepayModel::chiTiet($xemId) : null;

        $this->renderAdmin('admin/sepay/index', [
            'pageTitle'  => 'Sổ giao dịch ngân hàng',
            'rows'       => $trang['rows'],
            'total'      => $trang['total'],
            'totalPages' => $trang['totalPages'],
            'page'       => $trang['page'],
            'dem'        => SepayModel::demTheoKetQua($q),
            'loc'        => $loc,
            'q'          => $q,
            'xem'        => $xem,
            /* Đơn đang chờ xác nhận, đã tra sẵn — ngăn kéo chỉ việc in ra. Tra
               ở controller chứ không ở view: view không mở truy vấn. */
            'maXacNhan'  => $maChoXacNhan,
            'donXacNhan' => $maChoXacNhan !== ''
                ? OrderModel::findByCode($maChoXacNhan)
                : null,
            /* Các khoản KHÁC đã gắn cho cùng đơn — chỉ đọc khi ngăn kéo mở, và
               chỉ khi giao dịch ấy đã có đơn. Đây là thứ trả lời câu hỏi thật
               của người đang nhìn: "đơn này còn thiếu bao nhiêu nữa". */
            'khoanKhac'  => ($xem !== null && $xem['order_id'] !== null)
                ? SepayModel::khoanCuaDon((string) $xem['order_id'])
                : [],
            /* Bảng thiếu thì chỉ hiện một dải nhắc, không để trang đổ — cùng
               cách làm với màn Chờ hàng và Hàng chờ thư. Bảng rỗng vì CHƯA CHẠY
               MIGRATION và bảng rỗng vì CHƯA AI CHUYỂN KHOẢN cần hai hành động
               khác hẳn nhau. */
            'coBang'     => SepayModel::available(),
            /* Hai cột dấu vết gắn tay có chưa — migration đợt 7. Thiếu thì bảng
               vẫn chạy, chỉ không hiện được ai đã gắn dòng nào. */
            'coGanTay'   => SepayModel::coGanTay(),
        ]);
    }

    /**
     * Gắn một giao dịch "không tìm thấy đơn" vào một đơn — FR-SG-05.
     *
     * POST, và điều đó không thương lượng: thao tác này cộng một khoản tiền vào
     * một đơn và có thể đẩy đơn ấy sang "đã thanh toán". Thứ đó không được xảy
     * ra vì ai đó bấm F5 hay dán lại một cái link.
     *
     * MỌI LUẬT NẰM Ở SepayModel::ganDon() — kể cả ba phép từ chối và cả việc
     * cộng dồn. Ở đây chỉ đọc form, gọi, và dịch kết quả thành một câu tiếng
     * Việt. Đúng nếp đã ghi ở đầu SepayModel: luật về tiền nằm một chỗ.
     */
    public function gan(): void
    {
        $ve = $this->veDanhSach();

        $this->requirePost($ve);

        $id    = (string) ($_POST['id'] ?? '');
        $maDon = trim((string) ($_POST['ma_don'] ?? ''));

        /* ═════════════════════════════════════════════════════════════════
           HAI BƯỚC, KHÔNG PHẢI MỘT — VÌ THAO TÁC NÀY KHÔNG GỠ RA ĐƯỢC.

           Ô nhập là một ô chữ trống, và mã đơn có dạng DH-yymmdd-XXXX: mọi đơn
           đặt CÙNG MỘT NGÀY chỉ khác nhau ở bốn ký tự hex cuối. Gõ nhầm một ký
           tự là gắn tiền của khách này vào đơn của người lạ — và phép cộng dồn
           có thể đẩy đơn ấy sang "đã thanh toán" luôn. ganDon() từ chối gắn
           lại (FR-SG-07), nên không có đường lùi.

           Bước hai không hỏi "bạn chắc chứ" — câu ấy không thêm thông tin gì.
           Nó hiện TÊN KHÁCH, TỔNG TIỀN và SỐ ĐÃ NHẬN của đơn vừa tra, tức là
           đúng ba thứ để người bấm nhận ra mình gõ nhầm. Một hộp thoại chỉ
           lặp lại mã đơn thì gõ nhầm vẫn đọc ra giống hệt gõ đúng.

           Đi qua redirect + ?ma= chứ không dựng lại trang tại chỗ: cả khu quản
           trị mở ngăn kéo bằng tham số địa chỉ, và lối ấy chạy được khi tắt JS.
           ═════════════════════════════════════════════════════════════════ */
        if (($_POST['xac_nhan'] ?? '') !== '1') {
            $ma  = SepayModel::extractOrderCode($maDon) ?? strtoupper($maDon);
            $don = $ma !== '' ? OrderModel::findByCode($ma) : null;

            if ($don === null) {
                flash('admin_error', 'Không tìm thấy đơn hàng "' . $ma . '".');
                redirect($this->veDanhSach($id));
            }

            redirect($this->veDanhSach($id, $ma));
        }

        $kq = SepayModel::ganDon($id, $maDon, $this->userId);

        if (!$kq['ok']) {
            flash('admin_error', $kq['error']);
            redirect($ve);
        }

        /* CÂU BÁO NÓI ĐỦ BA CON SỐ, không chỉ "đã gắn xong".

           Người vừa bấm đang đối chiếu sổ sách, và câu hỏi ngay sau cú bấm là
           "đơn ấy đủ tiền chưa". Bắt họ mở màn đơn hàng để biết là thêm một
           bước cho một câu trả lời hệ thống vừa tính xong. */
        $nhan = [
            'paid'         => 'đã thanh toán đủ',
            'deposit_paid' => 'đã nhận cọc',
            'unpaid'       => 'vẫn chưa đủ tiền',
        ][$kq['payment_status']] ?? $kq['payment_status'];

        flash('admin_success', sprintf(
            'Đã gắn vào đơn %s. Tổng đã nhận %s / %s — đơn %s.',
            $kq['code'],
            money((int) $kq['da_nhan']),
            money((int) $kq['tong']),
            $nhan
        ));

        redirect($ve);
    }

    /**
     * Về đúng chỗ đang đứng sau một thao tác: bộ lọc, từ khoá và số trang.
     *
     * Người dùng màn này lọc "Không tìm thấy đơn" rồi xử lý từng dòng. Trả họ
     * về danh sách đầy đủ sau mỗi lần gắn là bắt lọc lại từ đầu ở mỗi dòng.
     *
     * KHÔNG mang theo ?xem: ngăn kéo vừa nói về một giao dịch mà thao tác vừa
     * xong đã đổi trạng thái, nên mở lại nó là bày một cảnh cũ.
     */
    private function veDanhSach(string $moLai = '', string $ma = ''): string
    {
        $loc = isset(SepayModel::KET_QUA[(string) ($_POST['loc'] ?? '')])
            ? (string) $_POST['loc'] : '';

        /* ─────────────────────────────────────────────────────────────────
           XỬ LÝ XONG MỘT DÒNG THÌ VỀ TRANG 1, KHÔNG VỀ TRANG ĐANG ĐỨNG.

           Nghe ngược với câu ở trên, nhưng hai câu nói về hai lượt khác nhau.
           Khi lọc "Không tìm thấy đơn" và gắn xong một dòng, dòng ấy RỜI KHỎI
           tập đang lọc — mọi dòng phía sau dịch lên một bậc. Quay lại trang 2
           nghĩa là dòng vừa trôi từ đầu trang 2 xuống cuối trang 1 sẽ không
           bao giờ được nhìn thấy nữa. Làm sạch hàng chờ từ trên xuống mà vẫn
           sót, và huy hiệu thì không nói cho ai biết.

           Nên: còn mở ngăn kéo (chưa xong việc) thì giữ nguyên trang; xử lý
           XONG một dòng trong một viên lọc HÀNG CHỜ thì về trang 1. Lọc các
           viên khác thì dòng không rời tập, giữ trang là đúng.
           ───────────────────────────────────────────────────────────────── */
        $roiTap = $moLai === '' && in_array($loc, SepayModel::CAN_XU_LY, true);
        $trang  = max(1, (int) ($_POST['page'] ?? 1));

        $tham = array_filter([
            'loc'  => $loc,
            'q'    => trim((string) ($_POST['q'] ?? '')),
            'page' => (!$roiTap && $trang > 1) ? (string) $trang : '',
            // Mở lại ngăn kéo ở bước xác nhận — xem gan().
            'xem'  => $moLai,
            'ma'   => $ma,
        ], static fn (string $v): bool => $v !== '');

        return self::BASE . ($tham !== [] ? '?' . http_build_query($tham) : '');
    }

    /**
     * Đánh dấu một khoản KHÔNG PHẢI tiền khách trả — cho huy hiệu về được 0.
     *
     * Không phải một ngoại lệ của FR-SG-07: nó không đụng tới số tiền, nội
     * dung hay thời điểm, chỉ chuyển kết quả đối soát sang 'ignored' — đúng
     * một giá trị SRS đã liệt kê trong dải viên lọc. Lý do đầy đủ ở
     * SepayModel::boQua().
     */
    public function boQua(): void
    {
        $ve = $this->veDanhSach();

        $this->requirePost($ve);

        $kq = SepayModel::boQua(
            (string) ($_POST['id'] ?? ''),
            (string) ($_POST['ly_do'] ?? ''),
            $this->userId
        );

        flash($kq['ok'] ? 'admin_success' : 'admin_error', $kq['ok']
            ? 'Đã đánh dấu khoản này không phải tiền khách trả.'
            : $kq['error']);

        redirect($ve);
    }
}
