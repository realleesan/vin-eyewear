/**
 * account.js — trang tài khoản (/tai-khoan)
 *
 * CHỈ LÀ TĂNG CƯỜNG. Không có file này trang vẫn chạy đủ — mỗi khối bên dưới
 * nói rõ nó bỏ được bước nào.
 *
 * Nút "Tải ảnh lên" bị CSS ẩn khi có JS (.js .acct-nav__send), và class .js do
 * <script> đầu master.php đặt — nên không có kịch bản nào mà cả nút lẫn chức
 * năng tự gửi đều vắng mặt.
 */

/* ── Đổi ảnh đại diện: chọn ảnh xong gửi luôn, khỏi bấm nút thứ hai ───────── */
(function () {
    'use strict';

    var input = document.querySelector('.acct-nav__face input[type="file"]');
    if (!input) return;

    var form = input.form;
    if (!form) return;

    input.addEventListener('change', function () {
        if (!input.files || !input.files.length) return;

        // Chặn tại chỗ để khách biết ngay, khỏi chờ một vòng lên máy chủ.
        // Máy chủ VẪN kiểm lại — xem core/AvatarStorage.php; giới hạn ở đây
        // sửa được bằng công cụ nhà phát triển nên nó không phải hàng rào.
        var limit = form.querySelector('input[name="MAX_FILE_SIZE"]');

        if (limit && input.files[0].size > Number(limit.value)) {
            window.alert('Ảnh vượt quá dung lượng cho phép (tối đa 1 MB).');
            input.value = '';
            return;
        }

        form.submit();
    });
}());

/* ── Đổi ngày trong form đổi giờ hẹn: gửi form luôn ───────────────────────────
 *
 * Danh sách giờ trống do máy chủ dựng, nên đổi ngày là phải tải lại. File này bỏ
 * đi cái bấm thứ hai: chọn ngày xong là đi luôn, khỏi bấm "Xem giờ trống".
 *
 * Nút đó bị CSS ẩn khi có JS (.js .acct-resched__go) — nên không có kịch bản nào
 * mà cả nút lẫn chức năng tự gửi đều vắng mặt.
 */
(function () {
    'use strict';

    var day = document.querySelector('.acct-resched__date[data-autosubmit]');
    if (!day || !day.form) return;

    day.addEventListener('change', function () {
        // Ngày rỗng (khách xoá ô) thì đừng gửi: máy chủ sẽ về ngày mặc định và
        // trông như cú bấm bị bỏ qua.
        if (day.value) day.form.submit();
    });
}());

/* ── "Xem chi tiết" / "Thu gọn" đơn hàng: bật tắt tại chỗ ─────────────────────
 *
 * Không có JS thì hai nút này là link thường (?don=<mã>) và trang tải lại —
 * vẫn đúng, chỉ chậm hơn. Ở đây chặn cái tải lại đó: khối chi tiết đã nằm sẵn
 * trong trang (xem chú thích ở app/views/auth/account/don-hang.php), nên chỉ
 * cần gỡ/đặt lại thuộc tính hidden.
 *
 * URL vẫn được cập nhật bằng replaceState để F5 hay chia sẻ link ra đúng cái
 * đang thấy — replaceState chứ không pushState: mở rồi thu gọn cùng một đơn là
 * quay về đúng chỗ ban đầu, ghi lại thì nút "lùi" của trình duyệt phải bấm mấy
 * lần mới ra khỏi trang tài khoản.
 */
(function () {
    'use strict';

    var list = document.querySelector('.acct-list');
    if (!list) return;

    list.addEventListener('click', function (ev) {
        // Bấm giữ Ctrl/Shift/giữa chuột là ý muốn mở tab khác — để nguyên.
        if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || ev.button !== 0) return;

        /* ĐÃ GỠ nhánh [data-reveal] của nút "Xem thông tin chuyển khoản".
           Nút đó nay đi thẳng tới màn quét QR chứ không mở panel nữa, nên nhánh
           này không còn ai gọi — xem chú thích ở chân thẻ trong don-hang.php. */
        var btn = ev.target.closest('.acct-order__more');
        if (!btn) return;

        var panel = document.getElementById(btn.getAttribute('aria-controls'));
        if (!panel) return;   // không tìm thấy khối chi tiết thì cứ để link chạy

        ev.preventDefault();

        var open = panel.hidden;   // đang ẩn ⇒ lần bấm này là để mở

        panel.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.textContent = open ? 'Thu gọn' : 'Xem chi tiết';

        var here = btn.getAttribute(open ? 'data-open-href' : 'data-close-href');

        if (here && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', here);
        }

        // href giờ phải là hành động NGƯỢC lại, để mở tab mới từ nút này (hoặc
        // một lần bấm nữa khi JS lỗi giữa đường) ra đúng thứ chữ trên nút hứa.
        btn.setAttribute('href', btn.getAttribute(open ? 'data-close-href' : 'data-open-href'));

        // Cả hai href đều trỏ tới #<mã đơn>. Khi mở thì bỏ qua: thẻ đang nằm
        // trong tầm mắt rồi, cuộn thêm chỉ làm giật. Khi thu gọn mới cuộn —
        // đầu thẻ có thể đã bị đẩy lên trên mép màn hình.
        if (!open) {
            var card = btn.closest('.acct-order');
            var top  = card ? card.getBoundingClientRect().top : 0;

            if (card && top < 0) {
                card.scrollIntoView({ block: 'start', behavior: 'smooth' });
            }
        }
    });
}());
