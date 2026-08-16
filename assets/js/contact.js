/**
 * contact.js — đổi cơ sở trên bản đồ mà không tải lại trang.
 *
 * LÀ TĂNG CƯỜNG, KHÔNG PHẢI ĐIỀU KIỆN ĐỂ TRANG CHẠY. Mỗi thẻ cơ sở là một
 * <a href="?cs=MÃ"> thật; tắt JavaScript thì bấm vào vẫn đổi được bản đồ, chỉ
 * là qua một lượt tải trang. File này bắt cú bấm đó lại và đổi ngay tại chỗ,
 * giống hệt hành vi trong bản thiết kế.
 *
 * Mọi dữ liệu cần thiết đã nằm sẵn trong thuộc tính data-* của từng thẻ, nên
 * không phải gọi mạng lần nào.
 */

(function () {
    'use strict';

    var list  = document.querySelector('.cstores__list');
    var frame = document.getElementById('storeMap');

    if (!list || !frame) return;

    var card = {
        name:    document.querySelector('[data-map-name]'),
        address: document.querySelector('[data-map-address]'),
        link:    document.querySelector('[data-map-link]'),
    };

    if (!card.name || !card.address || !card.link) return;

    list.addEventListener('click', function (event) {
        var store = event.target.closest('.cstore');

        if (!store || !list.contains(store)) return;

        // Ctrl/Cmd/giữa chuột = người dùng cố ý mở tab mới -> để trình duyệt lo
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;

        event.preventDefault();

        // Đang xem sẵn rồi thì không làm gì — tránh nạp lại iframe vô ích
        if (store.classList.contains('is-on')) return;

        list.querySelectorAll('.cstore').forEach(function (el) {
            el.classList.remove('is-on');
            el.removeAttribute('aria-current');
        });

        store.classList.add('is-on');
        store.setAttribute('aria-current', 'true');

        var name = store.getAttribute('data-name');

        frame.src = store.getAttribute('data-map');
        frame.title = 'Bản đồ ' + name;

        card.name.textContent    = name;
        card.address.textContent = store.getAttribute('data-address');
        card.link.href           = store.getAttribute('data-directions');

        // Địa chỉ trên thanh URL đi theo nội dung đang xem, để sao chép gửi
        // cho người khác vẫn ra đúng cơ sở này. replaceState chứ không
        // pushState: chọn qua lại giữa hai cơ sở không đáng để nút Back phải
        // lùi từng bước một.
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', store.getAttribute('href'));
        }
    });
})();
