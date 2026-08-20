/**
 * catalog.js — ba tiện ích cho trang danh sách sản phẩm (/san-pham).
 *
 * CẢ BA ĐỀU LÀ TĂNG CƯỜNG, KHÔNG PHẢI ĐIỀU KIỆN ĐỂ TRANG CHẠY. Bộ lọc của
 * trang này là liên kết và form GET thật; tắt JavaScript thì mọi thứ vẫn lọc
 * được, chỉ mất ba chỗ tiện tay:
 *
 *   1. Đổi ô "Sắp xếp theo" là gửi form luôn, không phải bấm "Áp dụng".
 *   2. Gõ vào ô "Tìm thương hiệu" là danh sách lọc ngay tại chỗ, không phải
 *      tải lại trang.
 *   3. Trên màn hình hẹp, bottom-sheet bộ lọc đóng được bằng nút "Xem N sản
 *      phẩm", bằng nền mờ và bằng phím Esc — thay vì phải cuộn ngược lên tìm
 *      lại chữ "Bộ lọc".
 *
 * Hai nút "Áp dụng" và "Lọc" bị CSS ẩn đi khi <html> có class .js (master.php
 * gắn class đó ngay đầu <head>). File này chỉ lo phần hành vi.
 */

(function () {
    'use strict';

    /* ------------------------------------------------------------------
       1. Đổi ô sắp xếp -> gửi form
       ------------------------------------------------------------------ */

    var sortSelect = document.getElementById('f-sort');

    if (sortSelect && sortSelect.form) {
        sortSelect.addEventListener('change', function () {
            sortSelect.form.submit();
        });
    }

    /* ------------------------------------------------------------------
       2. Lọc danh sách thương hiệu ngay khi gõ
       ------------------------------------------------------------------ */

    var brandForm = document.querySelector('[data-brand-filter]');

    if (brandForm) {
        initBrandSearch(brandForm);
    }

    function initBrandSearch(form) {
        var input = form.querySelector('input[name="bq"]');
        var list  = form.parentNode.querySelector('.pfacet__list');

        if (!input || !list) return;

        var rows = Array.prototype.slice.call(list.querySelectorAll('[data-brand]'));

        // Câu "không có thương hiệu nào khớp" — server đã in sẵn khi lọc phía
        // nó ra rỗng. Lọc phía trình duyệt thì phải tự dựng, nhưng chỉ dựng
        // MỘT lần rồi bật/tắt, không tạo lại mỗi lần gõ.
        var empty = list.querySelector('.pfacet__none');

        if (!empty) {
            empty = document.createElement('p');
            empty.className = 'pfacet__none';
            empty.textContent = 'Không có thương hiệu nào khớp.';
            empty.hidden = true;
            list.appendChild(empty);
        }

        // Không cho Enter tải lại trang: danh sách đã lọc sẵn trước mắt rồi.
        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });

        input.addEventListener('input', function () {
            // Hai phép so, khớp đúng hai phép so ở phía server (xem $checkGroup
            // trong app/views/product/index.php):
            //
            //   data-brand        chữ thường CÒN DẤU  -> gõ "saint" ra Saint Laurent
            //   data-brand-plain  slug ĐÃ BỎ DẤU      -> gõ "gioi" ra "Giới"
            //
            // Thiếu vế thứ hai thì người gõ không dấu (cách gõ nhanh phổ biến
            // nhất trên điện thoại) không tìm ra hãng nào có tên tiếng Việt.
            var typed = input.value.trim();
            var lower = typed.toLowerCase();
            var plain = deaccent(lower);
            var shown = 0;

            rows.forEach(function (row) {
                var match = typed === ''
                    || row.getAttribute('data-brand').indexOf(lower) !== -1
                    || (row.getAttribute('data-brand-plain') || '').indexOf(plain) !== -1;

                row.hidden = !match;
                if (match) shown++;
            });

            empty.hidden = shown > 0;
        });
    }

    /**
     * Bỏ dấu tiếng Việt, cho ra cùng dạng với slugify() bên PHP.
     *
     * normalize('NFD') tách chữ khỏi dấu rồi xoá dải dấu kết hợp; 'đ' không
     * phải chữ có dấu kết hợp nên phải thay tay. Trình duyệt không có
     * String.normalize (rất cũ) thì bỏ qua bước bỏ dấu — người gõ đủ dấu vẫn
     * tìm được, chỉ mất đường gõ tắt.
     */
    function deaccent(text) {
        if (!String.prototype.normalize) return text;

        return text.normalize('NFD')
                   .replace(/[\u0300-\u036f]/g, '')
                   .replace(/đ/g, 'd')
                   .replace(/[^a-z0-9]+/g, '-');
    }

    /* ------------------------------------------------------------------
       3. Bottom-sheet bộ lọc (dưới 1101px)
       ------------------------------------------------------------------ */

    var sheet = document.querySelector('[data-filter-sheet]');

    if (!sheet) return;

    // Nền mờ bị 'hidden' trong HTML: không có JavaScript thì nó không đóng
    // được gì, mà vẫn phủ kín màn hình chặn mọi cú bấm. Có JS mới bật lên.
    var scrim = sheet.querySelector('.cfilter__scrim');

    if (scrim) scrim.hidden = false;

    sheet.addEventListener('click', function (event) {
        if (event.target.closest('[data-sheet-close]')) {
            sheet.open = false;
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sheet.open && isSheetMode()) {
            sheet.open = false;
        }
    });

    /*
     * Khoá cuộn trang nền khi sheet mở.
     *
     * Không có nó, cuộn hết danh sách trong sheet là trang phía sau bắt đầu
     * trôi theo — người dùng đóng sheet ra thì thấy mình đang đứng ở một chỗ
     * khác hẳn trong lưới sản phẩm. (overscroll-behavior của panel chặn được
     * phần lớn, nhưng không chặn cú vuốt bắt đầu ngay trên nền mờ.)
     *
     * <details> không phát sự kiện riêng cho việc mở/đóng ở mọi trình duyệt,
     * nên nghe 'toggle' — sự kiện chuẩn của chính nó.
     */
    sheet.addEventListener('toggle', function () {
        document.body.style.overflow = (sheet.open && isSheetMode()) ? 'hidden' : '';
    });

    // Xoay ngang máy hoặc kéo rộng cửa sổ qua mốc 1101px: sheet biến thành cột
    // lọc thường, mà khoá cuộn thì vẫn còn — cả trang đứng im không hiểu vì sao.
    window.addEventListener('resize', function () {
        if (!isSheetMode()) document.body.style.overflow = '';
    });

    /** Bề rộng hiện tại có đang vẽ bộ lọc dưới dạng sheet không? */
    function isSheetMode() {
        return window.matchMedia('(max-width: 1100px)').matches;
    }
})();
