<?php

/**
 * PolicyController — trang Chính sách & Cam kết chất lượng (/chinh-sach).
 *
 * Port từ src/routes/chinh-sach.tsx.
 *
 * Nội dung lấy từ config/policy.php. Trang này không đụng cơ sở dữ liệu.
 */

class PolicyController extends BaseController
{
    public function index(): void
    {
        $policy = config('policy');

        $this->renderView('policy/index', [
            'pageTitle'   => 'Chính sách & Cam kết chất lượng - Vin Eyewear',
            'metaDesc'    => 'Chính sách bảo hành trọn đời, đổi mẫu 7 ngày, bảo hành độ cận, '
                           . 'giao hàng đồng kiểm và bảo mật thông tin tại Vin Eyewear.',
            'highlights'  => $policy['highlights'],
            'groups'      => $policy['groups'],
            'company'     => config('company'),
        ]);
    }
}
