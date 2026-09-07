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
    'ui.close'         => 'Đóng',

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

    'home.qc.kicker'      => 'Là một sản phẩm về sức khoẻ con người, hãy',
    'home.qc.title'       => 'Bỏ ra 5 phút để kiểm tra',
    'home.qc.c1'          => 'Chọn tròng',
    'home.qc.c1d'         => 'Trả lời 1 câu hỏi để tìm loại tròng đúng nhu cầu của bạn.',
    'home.qc.c1c'         => 'Chọn tròng kính →',
    'home.qc.c1a'         => 'Tròng kính chụp cận cảnh',
    'home.qc.c2'          => 'Chọn gọng',
    'home.qc.c2d'         => 'Chọn dáng khuôn mặt giống bạn nhất để được gợi ý gọng.',
    'home.qc.c2c'         => 'Chọn gọng kính →',
    'home.qc.c2a'         => 'Kệ trưng bày gọng kính tại cửa hàng',
    'home.qc.c3'          => 'Đặt lịch',
    'home.qc.c3d'         => 'Đặt lịch đo mắt miễn phí tại cơ sở gần bạn.',
    'home.qc.c3c'         => 'Đặt lịch ngay →',
    'home.qc.c3a'         => 'Kỹ thuật viên tư vấn cho khách tại cửa hàng',
    'home.qc.question'    => 'Bạn quan trọng điều gì nhất khi đeo kính?',
    'home.qc.la'          => 'A. Bảo vệ mắt toàn diện khi sử dụng thiết bị điện tử',
    'home.qc.lar'         => 'Tròng chống ánh sáng xanh (Blue-cut) 1.56 – 1.61 — lọc ánh sáng màn hình, phù hợp làm việc máy tính nhiều giờ.',
    'home.qc.lb'          => 'B. Bảo vệ mắt khi trời nắng',
    'home.qc.lbr'         => 'Tròng đổi màu Photochromic hoặc kính mát Polarized UV400 — tự tối màu khi ra nắng, chống chói.',
    'home.qc.lc'          => 'C. Mỏng nhẹ cho người cận thị độ cao',
    'home.qc.lcr'         => 'Tròng chiết suất cao 1.67 – 1.74 — mỏng nhẹ cho độ cận từ 4.00 trở lên.',
    'home.qc.rec'         => 'Gợi ý cho bạn:',
    'home.qc.lens_cta'    => 'Tư vấn chọn tròng →',
    'home.qc.face_title'  => 'Hình dáng khuôn mặt',
    'home.qc.face_sub'    => 'Dáng khuôn mặt nào sau đây trông giống bạn nhất?',
    'home.qc.face_prev'   => 'Dáng mặt trước',
    'home.qc.face_next'   => 'Dáng mặt sau',
    'home.qc.frame_cta'   => 'Xem gọng phù hợp →',
    'home.qc.f1'          => 'Mặt tròn',
    'home.qc.f1d'         => 'Gọng vuông, browline — tạo đường nét góc cạnh, cân lại khuôn mặt',
    'home.qc.f2'          => 'Mặt vuông',
    'home.qc.f2d'         => 'Gọng oval, kim loại mảnh — làm mềm đường quai hàm',
    'home.qc.f3'          => 'Mặt trái xoan',
    'home.qc.f3d'         => 'Gọng tròn, acetate dày — khuôn mặt cân đối, hợp hầu hết kiểu gọng',
    'home.qc.f4'          => 'Mặt dài',
    'home.qc.f4d'         => 'Gọng bản lớn, oversized — rút ngắn tỷ lệ, cân đối chiều dọc',
    'home.qc.f5'          => 'Mặt trái tim',
    'home.qc.f5d'         => 'Gọng cat-eye nhẹ, không viền — cân lại phần cằm thon',
    'home.qc.f6'          => 'Mặt kim cương',
    'home.qc.f6d'         => 'Gọng browline mềm, oval — làm nổi gò má, dịu phần trán',

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
