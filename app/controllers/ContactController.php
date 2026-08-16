<?php

/**
 * ContactController — trang Liên hệ (/lien-he).
 *
 * Port từ src/routes/lien-he.tsx.
 *
 * Cơ sở đọc từ bảng `stores` (trước đây gõ cứng trong controller), form liên
 * hệ ghi vào `contact_requests` qua ContactModel.
 */

class ContactController extends BaseController
{
    public function index(): void
    {
        $stores = StoreModel::active();

        /*
         * Cơ sở đang xem trên bản đồ, đọc từ ?cs=<mã>.
         *
         * Đối chiếu lại với danh sách thật chứ không tin thẳng tham số: nó đi
         * vào thuộc tính src của <iframe>, mà một mã lạ sẽ khiến $selected là
         * null rồi cả trang đổ ở dòng đầu tiên chạm tới nó. Mã không khớp thì
         * lặng lẽ quay về cơ sở đầu tiên — người dùng thấy một trang bình
         * thường chứ không phải trang lỗi.
         */
        $wanted   = (string) ($_GET['cs'] ?? '');
        $selected = $stores[0] ?? null;

        foreach ($stores as $store) {
            if ($store['code'] === $wanted) {
                $selected = $store;
                break;
            }
        }

        $this->renderView('contact/index', [
            'pageTitle' => 'Hệ thống cửa hàng — Vin Eyewear',
            'metaDesc'  => 'Địa chỉ, giờ mở cửa và hotline hai cơ sở Vin Eyewear tại Hà Nội. '
                         . 'Gửi câu hỏi để được tư vấn trong ngày.',
            'stores'    => $stores,
            'selected'  => $selected,
            'company'   => config('company'),
            // Giữ lại dữ liệu vừa nhập khi form báo lỗi, để khách không phải gõ lại
            'old'       => $_SESSION['_old_contact'] ?? [],
            'success'   => flash('contact_success'),
            'error'     => flash('contact_error'),
        ]);

        unset($_SESSION['_old_contact']);
    }

    /**
     * Nhận form liên hệ (POST /lien-he/gui).
     */
    public function submit(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            redirect('/lien-he');
        }

        if (!csrfCheck($_POST['_token'] ?? null)) {
            flash('contact_error', 'Phiên làm việc đã hết hạn, vui lòng gửi lại.');
            redirect('/lien-he#form');
        }

        $data = [
            'fullName' => (string) ($_POST['full_name'] ?? ''),
            'phone'    => (string) ($_POST['phone'] ?? ''),
            'email'    => (string) ($_POST['email'] ?? ''),
            'message'  => (string) ($_POST['message'] ?? ''),
        ];

        $result = ContactModel::submit($data);

        if (!$result['ok']) {
            // Nhớ lại nội dung đã nhập trước khi chuyển hướng về form
            $_SESSION['_old_contact'] = $data;
            flash('contact_error', $result['error']);
            redirect('/lien-he#form');
        }

        flash('contact_success', 'Đã gửi câu hỏi. Chúng tôi sẽ liên hệ lại trong ngày làm việc.');

        // Chuyển hướng sau khi POST thành công (mẫu POST/Redirect/GET):
        // không có bước này, khách bấm F5 sẽ gửi lại form lần nữa.
        redirect('/lien-he#form');
    }
}
