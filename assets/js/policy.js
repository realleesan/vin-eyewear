/**
 * policy.js — Mục lục tự sáng theo vị trí cuộn + tìm nhanh cho /chinh-sach.
 *
 * Cải tiến dần (progressive enhancement): server đã in đủ MỌI nhóm chính
 * sách và mục lục là những thẻ <a href="#…"> thật. File này chỉ thêm hai
 * thứ — đánh dấu mục đang xem, và lọc theo từ khoá. Tắt JavaScript thì
 * trang vẫn đọc và nhảy neo được đủ, chỉ mất hai thứ đó.
 *
 * KHÁC BẢN TRƯỚC: cột trái không còn là TAB. Trước đây bấm một mục thì bốn
 * nhóm còn lại bị display:none — xem chú thích đầu app/views/policy/index.php
 * về lý do bỏ.
 */

(function () {
    'use strict';

    var root = document.querySelector('.policy');
    if (!root) return;

    var tocItems = Array.prototype.slice.call(root.querySelectorAll('[data-policy-toc]'));
    var groups   = Array.prototype.slice.call(root.querySelectorAll('[data-policy-group]'));
    var input    = root.querySelector('#policySearch');
    var resultEl = root.querySelector('[data-policy-result]');
    var emptyEl  = root.querySelector('[data-policy-empty]');

    if (!tocItems.length) return;

    // Bật những phần chỉ có ý nghĩa khi JavaScript chạy
    root.querySelectorAll('[data-needs-js]').forEach(function (el) {
        el.hidden = false;
    });

    /* ====================================================================
       MỤC LỤC TỰ SÁNG THEO VỊ TRÍ CUỘN

       Mốc 140px là chiều cao header dính — cùng con số với scroll-padding-top
       trong layout.css. Section nào có mép trên đã vượt qua mốc đó thì coi
       như đang đọc; lấy cái CUỐI CÙNG thoả điều kiện.
       ==================================================================== */

    var HEADER_OFFSET = 140;

    // Neo cần theo dõi = đúng thứ tự các mục trong mục lục, nên không thể
    // lệch với những gì người dùng nhìn thấy bên trái.
    var anchors = tocItems.map(function (item) {
        return item.getAttribute('data-policy-toc');
    });

    var activeId  = anchors[0];
    var lock      = false;   // đang cuộn mượt theo lệnh bấm
    var lockTimer = null;
    var rafId     = null;

    function setActive(id) {
        if (id === activeId) return;
        activeId = id;
        tocItems.forEach(function (item) {
            item.classList.toggle('is-active', item.getAttribute('data-policy-toc') === id);
        });
    }

    function clearActive() {
        activeId = null;
        tocItems.forEach(function (item) { item.classList.remove('is-active'); });
    }

    function spy() {
        var current = null;

        for (var i = 0; i < anchors.length; i++) {
            var el = document.getElementById(anchors[i]);
            // Nhóm đang bị ẩn vì lọc thì không tính
            if (!el || el.hidden) continue;
            if (el.getBoundingClientRect().top <= HEADER_OFFSET) current = anchors[i];
        }

        /* Cuối trang: mục cuối cùng thường quá ngắn để mép trên của nó kịp
           vượt mốc 140px trước khi hết trang cuộn, nên nếu không có dòng này
           thì mục "Liên hệ hỗ trợ" không bao giờ sáng. */
        if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) {
            for (var j = anchors.length - 1; j >= 0; j--) {
                var last = document.getElementById(anchors[j]);
                if (last && !last.hidden) { current = anchors[j]; break; }
            }
        }

        setActive(current || anchors[0]);
    }

    function onScroll() {
        /* Đang cuộn mượt tới mục vừa bấm: giữ nguyên mục sáng, nếu không thì
           mục lục nhấp nháy chạy qua từng nhóm trên đường đi. Chỉ mở khoá khi
           cuộn đã dừng hẳn. */
        if (lock) {
            window.clearTimeout(lockTimer);
            lockTimer = window.setTimeout(function () { lock = false; }, 150);
            return;
        }
        if (rafId) return;
        rafId = window.requestAnimationFrame(function () {
            rafId = null;
            spy();
        });
    }

    window.addEventListener('scroll', onScroll, { passive: true });

    /* Bấm mục lục: KHÔNG chặn hành vi mặc định — thẻ neo tự cuộn mượt nhờ
       `scroll-behavior: smooth` và tự chừa chỗ cho header nhờ
       `scroll-padding-top`, cả hai đã khai ở layout.css. Ở đây chỉ khoá
       scrollspy lại trong lúc trang đang trôi. */
    tocItems.forEach(function (item) {
        item.addEventListener('click', function () {
            lock = true;
            window.clearTimeout(lockTimer);
            lockTimer = window.setTimeout(function () { lock = false; }, 600);
            setActive(item.getAttribute('data-policy-toc'));
        });
    });

    spy();

    /* ====================================================================
       TÌM NHANH
       ==================================================================== */

    /**
     * Bỏ dấu tiếng Việt để "bao hanh" tìm được "bảo hành".
     *
     * normalize('NFD') tách nguyên âm khỏi dấu thanh thành hai ký tự riêng,
     * rồi xoá dải dấu kết hợp U+0300–U+036F. Riêng đ/Đ không phải nguyên âm
     * có dấu tách được nên phải thay tay.
     */
    function deaccent(str) {
        return str
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'D')
            .toLowerCase();
    }

    function resetFilter() {
        groups.forEach(function (group) {
            group.hidden = false;
            group.querySelectorAll('[data-policy-item]').forEach(function (item) {
                item.hidden = false;
            });
        });
        if (resultEl) resultEl.hidden = true;
        if (emptyEl) emptyEl.hidden = true;
        spy();
    }

    function filter(term) {
        var needle = deaccent(term.trim());

        if (needle === '') {
            resetFilter();
            return;
        }

        var found = 0;

        groups.forEach(function (group) {
            var visible = 0;

            group.querySelectorAll('[data-policy-item]').forEach(function (item) {
                var match = deaccent(item.textContent).indexOf(needle) !== -1;
                item.hidden = !match;
                if (match) { visible++; found++; }
            });

            // Nhóm không còn câu nào khớp thì giấu cả tiêu đề lẫn lời dẫn,
            // nếu không thì màn hình đầy tiêu đề rỗng.
            group.hidden = visible === 0;
        });

        if (resultEl) {
            resultEl.hidden = false;
            resultEl.textContent = found + ' nội dung phù hợp với “' + term.trim() + '”';
        }
        if (emptyEl) emptyEl.hidden = found > 0;

        /* Đang lọc thì "mục đang đọc" không còn nghĩa gì — kết quả trộn từ
           nhiều nhóm, và phần lớn nhóm đã biến mất khỏi trang. */
        clearActive();
    }

    if (input) {
        var timer = null;

        input.addEventListener('input', function () {
            // Hoãn 150ms: gõ nhanh sẽ bắn nhiều sự kiện input, mỗi lần lọc là
            // một lượt duyệt toàn bộ DOM câu hỏi.
            window.clearTimeout(timer);
            timer = window.setTimeout(function () { filter(input.value); }, 150);
        });

        // Esc xoá ô tìm kiếm — thói quen quen thuộc với ô tìm kiếm
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && input.value !== '') {
                e.preventDefault();
                input.value = '';
                window.clearTimeout(timer);
                resetFilter();
            }
        });
    }

    /* ====================================================================
       VÀO TRANG BẰNG LIÊN KẾT NEO (/chinh-sach#doi-tra)

       Trình duyệt tự cuộn tới neo; ở đây chỉ cần đồng bộ mục lục. Hoãn một
       nhịp vì lúc sự kiện chạy thì trang chưa cuộn xong.
       ==================================================================== */

    function syncFromHash() {
        var id = window.location.hash.replace('#', '');
        if (anchors.indexOf(id) !== -1) {
            lock = true;
            window.clearTimeout(lockTimer);
            lockTimer = window.setTimeout(function () { lock = false; }, 600);
            setActive(id);
        }
    }

    syncFromHash();
    window.addEventListener('hashchange', syncFromHash);
})();
