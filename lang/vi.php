<?php

/**
 * lang/vi.php — nhãn giao diện tiếng Việt.
 *
 * ĐÂY LÀ BẢN GỐC. Mọi câu chữ của site sinh ra bằng tiếng Việt trước, bản
 * tiếng Anh dịch từ đây — nên khi hai bên lệch nhau thì file này đúng.
 *
 * Nó cũng là LƯỚI AN TOÀN: t() không thấy khoá trong bảng đang chọn thì lùi
 * về bảng này trước khi chịu thua. Thêm một khoá vào en.php mà quên thêm ở
 * đây là mất lưới ấy.
 *
 * QUY ƯỚC KHOÁ: '<vùng>.<thứ>' — vùng là chỗ nó xuất hiện (nav, cart, footer),
 * không phải trang. Nhãn dùng ở nhiều trang thì vùng là 'ui'.
 *
 * KHÔNG dịch: tên sản phẩm, tên danh mục, tên bộ sưu tập, tên cơ sở, nội dung
 * chính sách — tất cả nằm trong CSDL và chỉ có một ngôn ngữ. Xem đầu
 * core/i18n.php.
 */

return [

    // ── Trợ năng và khung trang ──────────────────────────────────────────
    'a11y.skip'        => 'Bỏ qua điều hướng, tới nội dung chính',
    'announce.shipping' => 'Miễn phí giao hàng toàn quốc cho đơn từ 1.000.000₫',
    'lang.label'       => 'Ngôn ngữ',

    // ── Điều hướng ───────────────────────────────────────────────────────
    'nav.aria.main'    => 'Điều hướng chính',
    'nav.home'         => 'Trang chủ',
    'nav.products'     => 'Sản phẩm',
    'nav.ar'           => 'Thử kính ảo',
    'nav.about'        => 'Giới thiệu',
    'nav.collections'  => 'Bộ sưu tập',
    'nav.contact'      => 'Liên hệ',
    'nav.booking'      => 'Đặt lịch đo mắt',
    'nav.policy'       => 'Chính sách & FAQ',

    'mega.view'        => '· Xem ngay →',
    'mega.all_collections' => 'Tất cả bộ sưu tập',
    'mega.collections_count' => ':n bộ đang trưng bày',
    'mega.all_products' => 'Tất cả sản phẩm',

    'menu.open'        => 'Mở menu điều hướng',
    'menu.close'       => 'Đóng menu',
    'menu.aria'        => 'Menu điều hướng',

    // ── Tìm kiếm ─────────────────────────────────────────────────────────
    'search.aria'        => 'Tìm kiếm sản phẩm',
    'search.title'       => 'Tìm kiếm sản phẩm',
    'search.placeholder' => 'Tìm gọng, tròng kính...',
    'search.submit'      => 'Tìm',

    // ── Tài khoản ────────────────────────────────────────────────────────
    'account.title'        => 'Tài khoản',
    'account.mine'         => 'Tài khoản của tôi',
    'account.login'        => 'Đăng nhập',
    'account.register'     => 'Đăng ký',
    'account.info'         => 'Thông tin tài khoản',
    'account.orders'       => 'Đơn hàng của tôi',
    'account.appointments' => 'Lịch hẹn đo mắt',
    'account.logout'       => 'Đăng xuất',

    // ── Giỏ hàng ─────────────────────────────────────────────────────────
    'cart.title'  => 'Giỏ hàng',
    'cart.aria'   => 'Giỏ hàng, :n',
    'cart.empty'  => 'Giỏ hàng đang trống',
    'cart.browse' => 'Xem sản phẩm',
    'cart.recent' => 'Sản phẩm mới thêm',
    'cart.more'   => 'Còn :n sản phẩm nữa trong giỏ',
    'cart.view'   => 'Xem giỏ hàng',

    // ── Nút gọi hành động dùng chung ─────────────────────────────────────
    'cta.book' => 'Đặt lịch đo mắt',
    'cta.call' => 'Gọi :phone',

    // ── Chân trang ───────────────────────────────────────────────────────
    'footer.blurb'      => 'Kính thời trang và tròng kính chính hãng. Đo mắt miễn phí tại hệ thống cửa hàng.',
    'footer.statement'  => 'Kính để đeo mỗi ngày, không phải để cất trong hộp.',
    'footer.products'   => 'Sản phẩm',
    'footer.about'      => 'Về Vin Eyewear',
    'footer.contact'    => 'Liên hệ',
    'footer.hotline'    => 'Hotline',
    'footer.stores'     => 'Hệ thống cửa hàng',
    'footer.email'      => 'Email',
    'footer.cta'        => 'Liên hệ với chúng tôi',
    'footer.legal_nav'  => 'Liên kết pháp lý',
    'footer.privacy'    => 'Chính sách bảo mật',
    'footer.terms'      => 'Điều khoản',
    'footer.tax'        => 'MST',

    'services.about'    => 'Giới thiệu',
    'services.booking'  => 'Đặt lịch đo mắt',
    'services.ar'       => 'Thử kính ảo',
    'services.warranty' => 'Bảo hành & đổi trả',
    'services.policy'   => 'Chính sách & FAQ',
];
