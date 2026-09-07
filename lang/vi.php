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

    // ── Trang chủ ────────────────────────────────────────────────────────
    'home.see_all'        => 'Xem tất cả →',
    'home.strip.prev'     => 'Sản phẩm trước',
    'home.strip.next'     => 'Sản phẩm sau',

    'home.hero.eyebrow'   => 'Bộ sưu tập 2026',
    'home.hero.title_1'   => 'Nhìn rõ hơn,',
    'home.hero.title_2'   => 'tự tin hơn.',
    'home.hero.lead'      => 'Gọng titanium và acetate chính hãng, đo khúc xạ miễn phí cùng chuyên viên trước khi bạn chốt đơn.',
    'home.hero.cta_shop'  => 'Khám phá bộ sưu tập',
    'home.hero.cta_book'  => 'Đặt lịch đo mắt miễn phí',
    'home.hero.ar'        => 'Hoặc thử kính ảo bằng camera (AR) →',
    'home.hero.prev'      => 'Ảnh trước',
    'home.hero.next'      => 'Ảnh sau',
    'home.hero.alt1'      => 'Khách hàng thử gọng kính tại Vin Eyewear',
    'home.hero.cap1'      => 'Đo khúc xạ chuẩn phòng khám · Hà Nội',
    'home.hero.alt2'      => 'Kệ trưng bày kính mát tại cửa hàng',
    'home.hero.cap2'      => 'Bộ sưu tập kính mát 2026 · Phân cực UV400',
    'home.hero.alt3'      => 'Gọng titan siêu nhẹ vừa lên kệ',
    'home.hero.cap3'      => 'Gọng titan siêu nhẹ 9 gram · Vừa lên kệ',

    'home.trust.exam'     => 'Đo khúc xạ miễn phí',
    'home.trust.returns'  => 'Đổi trả trong 7 ngày',
    'home.trust.warranty' => 'Bảo hành 24 tháng',
    'home.trust.shipping' => 'Giao nhanh toàn quốc',

    'home.new.eyebrow'    => 'Vừa lên kệ',
    'home.new.title'      => 'Sản phẩm mới về',
    'home.best.eyebrow'   => 'Được yêu thích',
    'home.best.title'     => 'Sản phẩm bán chạy',

    'home.coll.eyebrow'   => 'Tuyển chọn theo chủ đề',
    'home.coll.title'     => 'Bộ sưu tập mới',
    'home.coll.explore'   => '· Khám phá →',
    'home.coll.all'       => 'Tất cả bộ sưu tập →',

    'home.cat.eyebrow'    => 'Khám phá',
    'home.cat.title'      => 'Danh mục',
    'home.cat.prev'       => 'Danh mục trước',
    'home.cat.next'       => 'Danh mục sau',
    'home.cat.count'      => ':n mẫu',
    'home.cat.soon'       => 'Sắp có hàng',
    'home.cat.view'       => 'Xem danh mục',
    'home.cat.all'        => 'Tất cả sản phẩm →',

    'home.lens.eyebrow'   => 'Tròng kính chính hãng',
    'home.lens.title'     => 'Cắt lắp trong ngày, tròng chính hãng',
    'home.lens.alt'       => 'Quầy cắt kính tại Vin Eyewear',
    'home.lens.f1'        => 'Đo mắt miễn phí',
    'home.lens.f2'        => 'Nhận kính sau 60–90 phút',
    'home.lens.f4'        => 'Bảo hành 90 ngày',
    'home.lens.packages'  => 'Gói tròng phổ biến',
    'home.lens.from'      => 'Từ',
    'home.lens.ask'       => 'Liên hệ',
    'home.lens.cta'       => 'Tư vấn chọn tròng',

    'home.exam.eyebrow'   => 'Dịch vụ tại cửa hàng',
    'home.exam.title'     => 'Đo mắt chuẩn phòng khám, miễn phí',
    'home.exam.alt'       => 'Không gian cửa hàng Vin Eyewear',
    'home.exam.s1'        => 'Tiếp nhận',
    'home.exam.s1n'       => '2 phút',
    'home.exam.s2'        => 'Đo khúc xạ tự động',
    'home.exam.s2n'       => 'Thiết bị chuẩn phòng khám',
    'home.exam.s3'        => 'Thử tròng',
    'home.exam.s3n'       => 'Điều chỉnh theo độ thực tế',
    'home.exam.s4'        => 'Tư vấn tròng kính',
    'home.exam.s4n'       => 'Theo độ và nhu cầu',
    'home.exam.s5'        => 'Lắp ráp, chỉnh gọng',
    'home.exam.s5n'       => '60–90 phút',

    'home.rev.eyebrow'    => 'Đánh giá',
    'home.rev.title'      => 'Khách hàng nói về Vin Eyewear',
    'home.rev.prev'       => 'Đánh giá trước',
    'home.rev.next'       => 'Đánh giá sau',

    // ── Thẻ sản phẩm (dùng ở 5 trang) ────────────────────────────────────
    'product.out_of_stock' => 'Hết hàng',
    'product.badge_new'    => 'Mới',
    'product.badge_hot'    => 'Bán chạy',
    'product.no_image'     => 'Chưa có ảnh',
    'product.price_label'  => 'Giá bán',
    'product.was_label'    => 'Giá gốc',
    'product.buy_now'      => 'Mua ngay',
    'product.add_to_cart'  => 'Thêm vào giỏ',
    'product.choose_option' => 'Chọn phương án',
    'product.details'      => 'Chi tiết',

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
