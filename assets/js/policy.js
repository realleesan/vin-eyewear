/**
 * policy.js — Chuyển nhóm và tìm kiếm cho trang /chinh-sach.
 *
 * Cải tiến dần (progressive enhancement): server đã in đủ 5 nhóm chính sách.
 * File này chỉ THÊM khả năng lọc. Tắt JavaScript thì trang vẫn đọc được đủ
 * nội dung, chỉ mất phần lọc — nên ô tìm kiếm mặc định `hidden`, script bật
 * nó lên khi chạy được.
 */

(function () {
    'use strict';

    var root = document.querySelector('.policy');
    if (!root) return;

    var tabs     = Array.prototype.slice.call(root.querySelectorAll('[data-policy-tab]'));
    var groups   = Array.prototype.slice.call(root.querySelectorAll('[data-policy-group]'));
    var input    = root.querySelector('#policySearch');
    var resultEl = root.querySelector('[data-policy-result]');
    var emptyEl  = root.querySelector('[data-policy-empty]');

    if (!tabs.length || !groups.length) return;

    // Bật những phần chỉ có ý nghĩa khi JavaScript chạy
    root.querySelectorAll('[data-needs-js]').forEach(function (el) {
        el.hidden = false;
    });

    // Báo cho CSS biết được phép ẩn bớt nhóm. Trước dòng này mọi nhóm đều
    // hiện — xem ghi chú trong policy.css.
    root.classList.add('js-ready');

    var activeId = groups[0].getAttribute('data-policy-group');

    /* ====================================================================
       CHUYỂN NHÓM
       ==================================================================== */

    function showGroup(id) {
        activeId = id;

        groups.forEach(function (g) {
            g.classList.toggle('is-active', g.getAttribute('data-policy-group') === id);
        });

        tabs.forEach(function (t) {
            t.classList.toggle('is-active', t.getAttribute('data-policy-tab') === id);
        });

        // Rời chế độ tìm kiếm: bỏ nhãn nhóm trên từng câu hỏi
        root.querySelectorAll('[data-policy-badge]').forEach(function (b) {
            b.hidden = true;
        });

        if (resultEl) resultEl.hidden = true;
        if (emptyEl) emptyEl.hidden = true;
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            // Chặn nhảy neo để trang không giật; đổi nhóm tại chỗ.
            // Không có JS thì hành vi neo mặc định vẫn chạy — đó là chủ ý.
            e.preventDefault();

            if (input) input.value = '';
            showGroup(tab.getAttribute('data-policy-tab'));

            // Cập nhật URL để người dùng chia sẻ được đúng nhóm đang xem
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#' + tab.getAttribute('data-policy-tab'));
            }
        });
    });

    /* ====================================================================
       TÌM KIẾM
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

    function search(term) {
        var needle = deaccent(term.trim());

        if (needle === '') {
            showGroup(activeId);
            return;
        }

        var found = 0;

        groups.forEach(function (group) {
            var groupLabel = group.getAttribute('data-policy-label') || '';
            var items = group.querySelectorAll('[data-policy-item]');
            var visibleInGroup = 0;

            items.forEach(function (item) {
                var haystack = deaccent(groupLabel + ' ' + item.textContent);
                var match = haystack.indexOf(needle) !== -1;

                item.hidden = !match;

                // Hiện nhãn nhóm vì kết quả trộn từ nhiều nhóm khác nhau
                var badge = item.querySelector('[data-policy-badge]');
                if (badge) badge.hidden = !match;

                if (match) {
                    visibleInGroup++;
                    found++;
                }
            });

            // Khi tìm kiếm thì hiện MỌI nhóm còn kết quả, không chỉ nhóm đang mở
            group.classList.toggle('is-active', visibleInGroup > 0);
            group.classList.add('is-searching');
        });

        tabs.forEach(function (t) { t.classList.remove('is-active'); });

        if (resultEl) {
            resultEl.hidden = false;
            resultEl.textContent = found + ' kết quả cho “' + term.trim() + '”';
        }
        if (emptyEl) emptyEl.hidden = found > 0;
    }

    if (input) {
        var timer = null;

        input.addEventListener('input', function () {
            // Hoãn 150ms: gõ nhanh sẽ bắn nhiều sự kiện input, mỗi lần lọc là
            // một lượt duyệt toàn bộ DOM câu hỏi.
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                if (input.value.trim() === '') {
                    groups.forEach(function (g) {
                        g.classList.remove('is-searching');
                        g.querySelectorAll('[data-policy-item]').forEach(function (i) {
                            i.hidden = false;
                        });
                    });
                    showGroup(activeId);
                    return;
                }
                search(input.value);
            }, 150);
        });

        // Esc xoá ô tìm kiếm — thói quen quen thuộc với ô tìm kiếm
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && input.value !== '') {
                e.preventDefault();
                input.value = '';
                input.dispatchEvent(new Event('input'));
            }
        });
    }

    /* ====================================================================
       MỞ ĐÚNG NHÓM KHI VÀO BẰNG LIÊN KẾT NEO (/chinh-sach#doi-tra)
       ==================================================================== */

    function openFromHash() {
        var id = window.location.hash.replace('#', '');
        if (id && groups.some(function (g) { return g.getAttribute('data-policy-group') === id; })) {
            showGroup(id);
        }
    }

    openFromHash();
    window.addEventListener('hashchange', openFromHash);
})();
