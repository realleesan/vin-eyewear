<?php

/**
 * config/lang/vi.php — chuỗi tiếng Việt của KHUNG GIAO DIỆN.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * PHẠM VI: CHỈ KHUNG, KHÔNG PHẢI CẢ SITE
 *
 * File này phủ đầu trang, chân trang, bảng xổ và cụm nút nổi — những thứ có
 * mặt ở MỌI trang. Nội dung của từng trang (tiêu đề khối, mô tả, form) và
 * DỮ LIỆU TỪ CSDL (tên sản phẩm, tên danh mục, bài viết, đánh giá) vẫn là
 * tiếng Việt ở cả hai ngôn ngữ.
 *
 * Đó là giới hạn có chủ ý, không phải làm dở: dịch nội dung trang cần người
 * biên tập, còn dịch dữ liệu CSDL cần thêm cột/bảng cho từng ngôn ngữ. Cả hai
 * là việc riêng, và đều KHÔNG làm được bằng cách thêm chuỗi vào file này.
 *
 * Ai mở rộng sang tiếng Anh cho phần nội dung: đừng nhét câu dài vào đây theo
 * kiểu 'trang_chu_tieu_de_hero'. Chuỗi thuộc về một trang thì nên đi cùng
 * trang đó, nếu không file này phình thành nơi chứa toàn bộ chữ của site.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * KHOÁ PHẢI TRÙNG NHAU giữa vi.php và en.php. Thiếu khoá bên nào thì t() rơi
 * về tiếng Việt rồi về chính cái khoá — xem core/helpers.php.
 */

return [
    // Dải thông báo trên cùng
    'announce'          => 'Miễn phí giao hàng toàn quốc cho đơn từ 1.000.000₫',

    // Thanh điều hướng
    'nav.home'            => 'Trang chủ',
    'nav.products'        => 'Sản phẩm',
    'nav.tryon'           => 'Thử kính ảo',
    'nav.about'           => 'Giới thiệu',
    'nav.collections'     => 'Bộ sưu tập',
    'nav.contact'         => 'Liên hệ',
    'nav.booking'         => 'Đặt lịch đo mắt',
    'nav.policy'          => 'Chính sách & FAQ',
    'nav.all_products'    => 'Tất cả sản phẩm',
    'nav.all_collections' => 'Tất cả bộ sưu tập',

    // Cụm tác vụ bên phải đầu trang
    'action.search'     => 'Tìm kiếm sản phẩm',
    'action.account'    => 'Tài khoản của tôi',
    'action.login'      => 'Đăng nhập',
    'action.cart'       => 'Giỏ hàng',
    'action.open_menu'  => 'Mở menu điều hướng',
    'action.close_menu' => 'Đóng menu',
    'action.skip'       => 'Bỏ qua điều hướng, tới nội dung chính',

    // Bảng xổ dưới bốn nút tác vụ trên header (ngôn ngữ · tìm kiếm · tài
    // khoản · giỏ hàng) — xem app/views/_layout/header.php
    'pop.account'       => 'Tài khoản',
    'pop.profile'       => 'Thông tin tài khoản',
    'pop.orders'        => 'Đơn hàng của tôi',
    'pop.bookings'      => 'Lịch hẹn đo mắt',
    'pop.logout'        => 'Đăng xuất',
    'pop.admin'         => 'Khu quản trị',
    'pop.register'      => 'Đăng ký',
    'pop.cart_empty'    => 'Giỏ hàng đang trống',
    'pop.cart_count'    => '%d sản phẩm đang chờ',
    'pop.cart_view'     => 'Xem giỏ hàng',
    'pop.checkout'      => 'Thanh toán',
    'pop.shop'          => 'Xem sản phẩm',

    'search.placeholder' => 'Tìm gọng, tròng kính...',
    'search.submit'      => 'Tìm',

    // Nút chuyển ngôn ngữ
    'lang.label'        => 'Ngôn ngữ',
    'lang.vi'           => 'Tiếng Việt',
    'lang.en'           => 'English',

    // Chân trang
    'footer.blurb'      => 'Kính thời trang và tròng kính chính hãng. '
                         . 'Đo mắt miễn phí tại hệ thống cửa hàng.',
    'footer.products'   => 'Sản phẩm',
    'footer.about'      => 'Về Vin Eyewear',
    'footer.contact'    => 'Liên hệ',
    'footer.hotline'    => 'Hotline:',
    'footer.exam'       => 'Đặt lịch đo mắt',
    'footer.warranty'   => 'Bảo hành & đổi trả',
    'footer.stores'     => 'Hệ thống cửa hàng',
    'footer.privacy'    => 'Chính sách bảo mật',
    'footer.terms'      => 'Điều khoản',

    // Cụm nút nổi
    'fab.call'          => 'Gọi %s',
    'fab.zalo'          => 'Nhắn Zalo',
    'fab.messenger'     => 'Chat Messenger',
    'fab.top'           => 'Về đầu trang',
    'fab.open'          => 'Mở kênh hỗ trợ',
];
