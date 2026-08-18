/**
 * assets/js/home.js — phần động của trang chủ.
 *
 * Năm việc, gói trong một file vì cả năm đều chỉ chạy ở đúng một trang:
 *
 *   1. băng ảnh hero (ba ảnh) — TỰ CHẠY
 *   2. đồng hồ đếm ngược của dải ưu đãi
 *   3. hộp thoại "kiểm tra 5 phút" (chọn tròng · chọn gọng)
 *   4. băng trượt khối đánh giá — TỰ CHẠY
 *   5. hai băng trượt sản phẩm ("mới về" và "bán chạy") — chỉ đổi khi bấm
 *
 * HAI BĂNG TỰ CHẠY (1 và 4) đều lấy nhịp từ thuộc tính data-autoplay trong
 * HTML, không gõ cứng ở đây: đổi tốc độ là việc của người dựng trang. Cả hai
 * cũng đứng lại khi con trỏ hoặc tiêu điểm bàn phím ở trong khối, và không bật
 * tự chạy trên máy đặt "giảm chuyển động".
 *
 * TẤT CẢ ĐỀU LÀ TĂNG CƯỜNG. Không có file này thì:
 *   · hero đứng ở ảnh đầu tiên, hai mũi tên không làm gì;
 *   · đồng hồ vẫn hiện đúng số PHP đã tính lúc dựng trang, chỉ không đếm tiếp;
 *   · hai thẻ "chọn tròng / chọn gọng" là liên kết thường sang trang danh mục;
 *   · băng đánh giá đứng ở năm thẻ đầu, hai băng sản phẩm đứng ở bốn thẻ đầu.
 * Không khối nào biến mất, không lối đi nào gãy.
 *
 * Nạp qua bảng $pageScripts trong _layout/master.php ('home/index'), có defer.
 */

(function () {
    'use strict';

    /* ============================================================
       TIỆN ÍCH DÙNG CHUNG
       ============================================================ */

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    /** ev.target có thể là text node hoặc document -> closest() không tồn tại */
    function closestFrom(target, selector) {
        return target instanceof Element ? target.closest(selector) : null;
    }

    /**
     * Người dùng đã tắt hiệu ứng chuyển động trong hệ điều hành?
     *
     * Băng tự chạy là chuyển động KHÔNG do người dùng khởi động, đúng loại mà
     * thiết lập này muốn chặn — với người nhạy cảm tiền đình thì một khối cứ
     * tự trượt là lý do phải rời trang. CSS đã rút mọi transition về 0.01ms
     * (xem layout.css) nhưng nó không tắt được cái setInterval, nên phải hỏi
     * lại ở đây.
     */
    function prefersReducedMotion() {
        return window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    /**
     * Băng trượt ngang dùng chung cho hai khối sản phẩm, khối đánh giá và dải
     * khuôn mặt.
     *
     * Bước trượt ĐO TỪ DOM (khoảng cách giữa hai thẻ đầu) chứ không tính từ
     * một con số phần trăm gõ cứng: số thẻ nhìn thấy đổi theo bề ngang màn
     * hình, mà cùng một công thức phần trăm thì chỉ đúng ở đúng một mốc.
     *
     * Trả về { render } để nơi gọi ép vẽ lại — cần cho dải nằm trong hộp
     * thoại, vì lúc hộp thoại còn ẩn thì mọi phép đo đều ra 0.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * CHẠY VÒNG VÔ HẠN, VÀ CÚ TRƯỢT NÀO CŨNG DÀI ĐÚNG MỘT THẺ.
     *
     * Cách làm thông thường là đếm vị trí rồi tới thẻ cuối thì đặt lại về 0.
     * Khi đó cả băng bị kéo ngược qua toàn bộ chiều dài của nó trong một cú —
     * đúng cái "giật về" phải bỏ.
     *
     * Ở đây băng đi như BĂNG TẢI, và lúc nghỉ nó LUÔN đậu ở vị trí 0:
     *
     *   Bấm "sau": trượt băng sang trái một ô (có hoạt hình). Hết hoạt hình thì
     *   lặng lẽ chuyển những thẻ đã ra khỏi khung xuống cuối hàng, đồng thời
     *   trả băng về 0 mà KHÔNG hoạt hình. Mọi thẻ còn lại dồn sang trái đúng
     *   một ô, nên khung hình y hệt lúc vừa trượt xong: cú trả về đó vô hình.
     *
     *   Bấm "trước": làm ngược lại, và đảo thứ tự hai việc. Đưa thẻ CUỐI lên
     *   đầu hàng rồi dời băng sang phải một ô, cả hai đều không hoạt hình —
     *   khung hình không đổi. Sau đó mới trượt về 0, thẻ vừa đưa lên lộ ra ở
     *   mép trái.
     *
     * Xoay thẻ THẬT chứ không nhân bản: không sinh DOM trùng, không có hai nút
     * "Mua ngay" cùng trỏ một sản phẩm, và trình đọc màn hình không đọc lại
     * cùng một thẻ hai lần. Đổi lại, thứ tự thẻ trong DOM xoay dần theo số lần
     * bấm — với một băng trượt thì điều đó không có nghĩa gì.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * sel.autoplay (mili-giây) bật chế độ TỰ CHẠY: bằng đúng việc bấm "sau"
     * đều đặn, không hơn không kém.
     */
    function makeStrip(root, sel) {
        var frame = root.querySelector(sel.window);
        var track = root.querySelector(sel.track);

        if (!frame || !track || !track.children.length) return null;

        // Danh sách SỐNG: xoay thẻ trong DOM là nó tự cập nhật theo.
        var items = track.children;
        var prev  = root.querySelector(sel.prev);
        var next  = root.querySelector(sel.next);

        /*
         * Số thẻ đã trượt qua nhưng CHƯA kịp dồn xuống cuối hàng — tức khoảng
         * lệch giữa vị trí băng đang đứng và vị trí nghỉ 0. Bình thường bằng 0;
         * chỉ khác 0 trong lúc hoạt hình chạy.
         */
        var shown = 0;

        function stepWidth() {
            return items.length > 1
                ? items[1].offsetLeft - items[0].offsetLeft
                : items[0].offsetWidth;
        }

        /* Số thẻ lọt trong khung nhìn, đo lại mỗi lần vì bề ngang cửa sổ đổi được. */
        function perView() {
            var step = stepWidth();
            return step > 0 ? Math.max(1, Math.round(frame.clientWidth / step)) : 0;
        }

        /*
         * Băng có đủ thẻ để trượt không?
         *
         * Phải THỪA ra ít nhất một thẻ ngoài khung: nếu mọi thẻ đã nằm trong
         * khung thì trượt một ô chỉ để lộ khoảng trống. Đây là trạng thái khối
         * "bán chạy" đang ở trên màn hình rộng, xem _layout/home/best-sellers.php.
         */
        function canSlide() {
            var view = perView();
            return view > 0 && items.length > view;
        }

        /*
         * Đưa băng về vị trí lệch `px` so với thẻ đầu trong DOM.
         *
         * animate = false thì tắt transition, dời, ép trình duyệt tính lại bố
         * cục NGAY TẠI CHỖ (đọc offsetHeight), rồi trả transition về giá trị
         * khai trong CSS. Không có cú đọc ép đó thì trình duyệt gộp cả ba thay
         * đổi vào một lần vẽ và vẫn hoạt hình như thường — vẫn thấy giật.
         */
        function place(px, animate) {
            if (!animate) track.style.transition = 'none';

            track.style.transform = 'translateX(' + (-px) + 'px)';

            if (!animate) {
                void track.offsetHeight;      // ép tính lại bố cục
                track.style.transition = '';  // trả về giá trị khai trong CSS
            }
        }

        /*
         * Băng ĐANG thực sự nằm ở đâu, kể cả khi hoạt hình còn đang chạy dở.
         *
         * Đọc style đã tính chứ không đọc lại con số vừa gán: giữa lúc trượt,
         * con số đã gán là ĐÍCH ĐẾN còn cái mắt thấy là một giá trị trung gian.
         * Cần đúng cái mắt thấy để dời băng mà khung hình không nhảy.
         */
        function shownPx() {
            var value = window.getComputedStyle(track).transform;
            var inside = value ? value.match(/matrix3?d?\(([^)]+)\)/) : null;

            if (!inside) return 0;

            var numbers = inside[1].split(',');

            // matrix() giữ độ dời ngang ở ô thứ 5, matrix3d() ở ô thứ 13.
            return -parseFloat(numbers[numbers.length > 6 ? 12 : 4]);
        }

        /*
         * ĐÁNH THỨC ẢNH loading="lazy" NẰM NGOÀI KHUNG NHÌN CỦA BĂNG.
         *
         * Thẻ thứ 5 trở đi nằm ngoài vùng cắt overflow:hidden của .*__window.
         * Trình duyệt tính "ảnh này có trong tầm nhìn không" theo phần giao với
         * khung nhìn SAU KHI đã cắt bởi các khối cha, nên nó kết luận ngay từ
         * lúc tải trang là chưa cần tải — và KHÔNG tính lại khi băng trượt,
         * vì trượt ở đây là transform chứ không phải cuộn.
         *
         * Hậu quả nếu thiếu hàm này: bấm mũi tên ra thẻ thứ 5, ảnh trắng trơn
         * VĨNH VIỄN. Đã đo được trong Chrome — 3 giây sau cú bấm vẫn chưa tải.
         *
         * Đổi loading sang 'eager' là cách chuẩn để ép tải: theo đặc tả, gỡ
         * trạng thái lazy của một ảnh chưa tải sẽ khởi động việc tải ngay.
         *
         * Chỉ chạy ở lần trượt ĐẦU TIÊN: khách không đụng tới mũi tên thì không
         * phải tải gì thêm, trang chủ giữ nguyên chi phí ảnh như trước.
         */
        var imagesWoken = false;

        function wakeLazyImages() {
            if (imagesWoken) return;
            imagesWoken = true;

            var lazy = track.querySelectorAll('img[loading="lazy"]');

            Array.prototype.forEach.call(lazy, function (img) {
                img.loading = 'eager';
            });
        }

        /*
         * Dồn những thẻ đã trượt qua xuống cuối hàng và trả băng về vị trí nghỉ.
         *
         * Chỉ gọi khi hoạt hình đã chạy xong (transitionend): lúc đó băng đang
         * đứng đúng ở shown ô, nên cú dời bù trừ vừa khít và không ai thấy gì.
         */
        function settle() {
            if (shown <= 0) return;

            while (shown > 0) {
                track.appendChild(items[0]);
                shown -= 1;
            }

            place(0, false);
        }

        track.addEventListener('transitionend', function (ev) {
            if (ev.target === track && ev.propertyName === 'transform') settle();
        });

        /* Một nhịp tới (delta > 0) hoặc lùi (delta < 0). Xem chú thích đầu hàm. */
        function move(delta) {
            wakeLazyImages();

            if (!canSlide()) {
                shown = 0;
                place(0, false);
                return;
            }

            var step = stepWidth();

            /*
             * Lưới an toàn: nếu vì lý do nào đó transitionend không tới (CSS
             * không khai transition cho .*__track chẳng hạn) mà băng thì đã
             * đứng đúng đích, dọn ngay tại đây. Thiếu đoạn này thì `shown` cứ
             * tăng, và tới lúc hết thẻ bên phải là mọi cú bấm đều bị bỏ — băng
             * trông như chết cứng.
             */
            if (shown > 0 && Math.abs(shownPx() - shown * step) < 1) settle();

            if (delta > 0) {
                /*
                 * Bấm nhanh liên tiếp thì cú sau KHÔNG dọn dẹp giữa đường —
                 * dọn lúc đang trượt là thấy giật. Nó chỉ đẩy đích đi thêm một
                 * ô, trình duyệt tự nối tiếp cú trượt đang chạy.
                 *
                 * Chỉ khi bên phải hết thẻ để lộ ra mới phải bỏ cú bấm: muốn
                 * rơi vào đây phải bấm ba lần trong vài phần mười giây.
                 */
                if (shown + perView() >= items.length) return;

                shown += 1;
                place(shown * step, true);
            } else if (shown > 0) {
                // Đang trượt tới mà bấm lùi: chỉ cần kéo đích về, không đụng DOM.
                shown -= 1;
                place(shown * step, true);
            } else {
                var at = shownPx();

                track.insertBefore(items[items.length - 1], items[0]);
                place(at + step, false);  // giữ nguyên khung hình
                place(0, true);           // trượt để lộ thẻ vừa đưa lên đầu
            }
        }

        function render() {
            settle();
            place(0, false);

            /*
             * Băng chạy vòng nên hai mũi tên KHÔNG mờ đi ở hai đầu danh sách:
             * tới thẻ cuối bấm tiếp là sang thẻ đầu, lúc nào cũng còn chỗ để đi.
             *
             * Chỉ còn ĐÚNG MỘT trường hợp mờ: cả băng không thừa thẻ nào ngoài
             * khung (canSlide sai). Lúc đó bấm gì cũng không có gì đổi, mờ đi là
             * nói đúng sự thật.
             */
            var idle = !canSlide();

            if (prev) prev.disabled = idle;
            if (next) next.disabled = idle;
        }

        if (prev) prev.addEventListener('click', function () { move(-1); });
        if (next) next.addEventListener('click', function () { move(1); });

        window.addEventListener('resize', render);
        render();

        /* ---------- Tự chạy ---------- */
        if (sel.autoplay > 0 && !prefersReducedMotion()) {
            var timer = null;

            function step() {
                // canSlide() đọc lại mỗi nhịp chứ không tính sẵn một lần: số
                // thẻ nhìn thấy đổi theo bề ngang cửa sổ, mà người dùng có thể
                // kéo cửa sổ giữa chừng.
                if (!canSlide()) return;

                move(1);
            }

            function start() {
                if (timer === null) timer = setInterval(step, sel.autoplay);
            }

            function stop() {
                if (timer !== null) {
                    clearInterval(timer);
                    timer = null;
                }
            }

            // Dừng khi người dùng đang đọc hoặc đang thao tác. focusin/focusout
            // lo cho bàn phím: Tab vào một thẻ mà băng vẫn trượt thì tiêu điểm
            // bị kéo ra khỏi màn hình.
            root.addEventListener('mouseenter', stop);
            root.addEventListener('mouseleave', start);
            root.addEventListener('focusin', stop);
            root.addEventListener('focusout', start);

            // Tab ẩn đi thì dừng hẳn: trình duyệt dồn các setInterval bị treo
            // lại và bắn một loạt khi quay về, băng sẽ nhảy mấy nấc một lúc.
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) stop(); else start();
            });

            start();
        }

        return { render: render };
    }

    /**
     * Đánh dấu MỘT lựa chọn trong nhóm và mở đúng câu gợi ý của nó.
     *
     * Cả nút lẫn câu gợi ý đều đã in sẵn trong HTML; ở đây chỉ bật/tắt lớp và
     * thuộc tính [hidden]. Dựng chuỗi gợi ý trong JS thì phần chữ đó lọt ra
     * ngoài tầm e() của PHP.
     */
    function pickOne(root, buttonAttr, recAttr, chosen) {
        var value = chosen.getAttribute(buttonAttr);

        root.querySelectorAll('[' + buttonAttr + ']').forEach(function (button) {
            var on = button === chosen;
            button.classList.toggle('is-picked', on);
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
        });

        root.querySelectorAll('[' + recAttr + ']').forEach(function (rec) {
            rec.hidden = rec.getAttribute(recAttr) !== value;
        });
    }

    /* ============================================================
       1. BĂNG ẢNH HERO
       ============================================================ */

    (function heroSlider() {
        var media = document.querySelector('[data-hero-slider]');
        if (!media) return;

        var section = media.closest('.hero');
        var track   = media.querySelector('.hero__track');
        var slides  = media.querySelectorAll('.hero__slide');
        var caption = media.querySelector('[data-hero-caption]');
        var counter = section ? section.querySelector('[data-hero-index]') : null;
        var bars    = section ? section.querySelectorAll('.hero__bar') : [];

        if (!track || slides.length < 2) return;

        /*
         * Ảnh ĐANG HIỆN, giữ bằng chính phần tử chứ không bằng một con số đếm.
         *
         * Băng đi như băng tải (xem makeStrip): thứ tự ảnh trong DOM xoay dần
         * theo số lần bấm, nên "ảnh thứ mấy trong DOM" không còn nói được ảnh
         * nào. Còn `slides` là NodeList tĩnh nên vẫn giữ THỨ TỰ GỐC — đúng thứ
         * mà bộ đếm "01/04" và dải vạch bên dưới cần.
         */
        var current = slides[0];

        /* Số khung ảnh đã trượt qua nhưng chưa kịp dồn xuống cuối hàng. */
        var shown = 0;

        /*
         * Một khung ảnh rộng bằng đúng .hero__track: thẻ này absolute inset:0
         * còn mỗi .hero__slide là flex: 0 0 100% (xem home-sections.css).
         */
        function stepWidth() {
            return track.offsetWidth;
        }

        /* Dời băng. Giống place() trong makeStrip, xem chú thích ở đó. */
        function place(px, animate) {
            if (!animate) track.style.transition = 'none';

            track.style.transform = 'translateX(' + (-px) + 'px)';

            if (!animate) {
                void track.offsetHeight;      // ép tính lại bố cục
                track.style.transition = '';  // trả về giá trị khai trong CSS
            }
        }

        /* Băng đang thực sự nằm ở đâu, kể cả giữa lúc hoạt hình chạy dở. */
        function shownPx() {
            var value = window.getComputedStyle(track).transform;
            var inside = value ? value.match(/matrix3?d?\(([^)]+)\)/) : null;

            if (!inside) return 0;

            var numbers = inside[1].split(',');

            return -parseFloat(numbers[numbers.length > 6 ? 12 : 4]);
        }

        /* Chú thích, bộ đếm, dải vạch và aria-hidden theo ảnh đang hiện. */
        function render() {
            var at = Array.prototype.indexOf.call(slides, current);

            if (caption) caption.textContent = current.getAttribute('data-caption') || '';
            if (counter) counter.textContent = pad(at + 1);

            slides.forEach(function (slide) {
                // Ảnh ngoài khung nhìn không nằm trong luồng đọc của trình đọc
                // màn hình, nhưng vẫn ở lại DOM để không phải tải lại khi quay về.
                slide.setAttribute('aria-hidden', slide === current ? 'false' : 'true');
            });

            bars.forEach(function (bar, i) {
                bar.classList.toggle('is-on', i === at);
            });
        }

        /* Ảnh vừa rời khung xuống cuối hàng, băng về vị trí nghỉ 0. */
        function settle() {
            if (shown <= 0) return;

            while (shown > 0) {
                track.appendChild(track.children[0]);
                shown -= 1;
            }

            place(0, false);
        }

        track.addEventListener('transitionend', function (ev) {
            if (ev.target === track && ev.propertyName === 'transform') settle();
        });

        /*
         * Một nhịp tới (delta > 0) hoặc lùi (delta < 0), CHẠY VÒNG VÔ HẠN mà cú
         * trượt nào cũng dài đúng MỘT khung ảnh.
         *
         * Trước đây vị trí băng tính bằng -index * 100%, nên đi từ ảnh cuối về
         * ảnh đầu là kéo cả băng ngược qua toàn bộ chiều dài của nó trong một
         * cú — đúng cái "giật về" phải bỏ. Nay xoay ảnh trong DOM y như makeStrip,
         * xem chú thích dài ở đó.
         */
        function go(delta) {
            var frames = track.children;

            // Lưới an toàn khi transitionend không tới; xem move() trong makeStrip.
            if (shown > 0 && Math.abs(shownPx() - shown * stepWidth()) < 1) settle();

            if (delta > 0) {
                // Hết ảnh bên phải để lộ ra thì bỏ cú bấm: chỉ xảy ra khi bấm
                // dồn dập trong đúng một nhịp trượt.
                if (shown + 1 >= frames.length) return;

                shown  += 1;
                current = frames[shown];
                place(shown * stepWidth(), true);
            } else if (shown > 0) {
                // Đang trượt tới mà bấm lùi: kéo đích về, không đụng DOM.
                shown  -= 1;
                current = frames[shown];
                place(shown * stepWidth(), true);
            } else {
                var at = shownPx();

                track.insertBefore(frames[frames.length - 1], frames[0]);
                current = frames[0];
                place(at + stepWidth(), false);  // giữ nguyên khung hình
                place(0, true);                  // trượt để lộ ảnh vừa đưa lên đầu
            }

            render();
        }

        (section || media).addEventListener('click', function (ev) {
            var button = closestFrom(ev.target, '[data-hero]');
            if (!button) return;

            go(button.getAttribute('data-hero') === 'next' ? 1 : -1);

            /* Bấm xong thì đếm lại từ đầu. Thiếu dòng này thì cú bấm rơi trúng
               cuối một nhịp sẽ bị máy đổi ảnh tiếp ngay sau đó — người dùng vừa
               chọn ảnh mình muốn xem đã bị đẩy sang ảnh khác. */
            restart();
        });

        render();

        /* ---------- Tự chạy ----------
           Bằng đúng việc bấm mũi tên "sau" đều đặn, không hơn không kém: băng đã
           chạy vòng vô hạn nên không cần trường hợp riêng cho ảnh cuối. */
        var autoplay = parseInt(media.getAttribute('data-autoplay'), 10) || 0;
        var timer    = null;

        function start() {
            if (timer === null && autoplay > 0) {
                timer = setInterval(function () { go(1); }, autoplay);
            }
        }

        function stop() {
            if (timer !== null) {
                clearInterval(timer);
                timer = null;
            }
        }

        function restart() {
            if (timer === null) return;   // đang dừng vì con trỏ ở trong hero
            stop();
            start();
        }

        if (autoplay > 0 && !prefersReducedMotion()) {
            /*
             * DỪNG KHI RÊ CHUỘT — CHỈ TRÊN ẢNH VÀ BỘ ĐIỀU KHIỂN, KHÔNG PHẢI CẢ
             * SECTION.
             *
             * Bản đầu gắn mouseenter/mouseleave lên chính thẻ <section class="hero">.
             * Thẻ đó trải hết bề ngang trang và ôm luôn cột chữ, hai nút bấm, dải
             * đếm ngược và cả dải cam kết bên dưới — nghĩa là con trỏ đậu ở gần
             * như bất kỳ đâu nửa trên màn hình cũng làm băng đứng im vô thời hạn.
             * Người dùng chỉ thấy một hero bất động và tưởng tính năng chưa chạy.
             *
             * Hai vùng dưới đây là hai chỗ DUY NHẤT mà "đang dừng" có nghĩa:
             * người ta đang nhìn tấm ảnh, hoặc đang thao tác với mũi tên.
             */
            var zones = [media];
            var nav   = section ? section.querySelector('.hero__nav') : null;

            if (nav) zones.push(nav);

            zones.forEach(function (zone) {
                zone.addEventListener('mouseenter', stop);
                zone.addEventListener('mouseleave', start);

                /* focusin/focusout lo cho bàn phím: Tab vào hai mũi tên mà ảnh
                   vẫn trôi thì cái nút vừa chọn đã ứng với ảnh khác. */
                zone.addEventListener('focusin', stop);
                zone.addEventListener('focusout', start);
            });

            start();
        }
    })();

    /* ============================================================
       2. ĐỒNG HỒ ĐẾM NGƯỢC
       ============================================================ */

    (function countdown() {
        var box = document.querySelector('[data-countdown]');
        if (!box) return;

        var deadline = Date.parse(box.getAttribute('data-countdown'));
        if (isNaN(deadline)) return;

        var cells = {};
        ['d', 'h', 'm', 's'].forEach(function (key) {
            cells[key] = box.querySelector('[data-cd="' + key + '"]');
        });

        var timer = null;

        function tick() {
            var left = Math.max(0, Math.floor((deadline - Date.now()) / 1000));

            var value = {
                d: Math.floor(left / 86400),
                h: Math.floor((left % 86400) / 3600),
                m: Math.floor((left % 3600) / 60),
                s: left % 60
            };

            Object.keys(value).forEach(function (key) {
                if (cells[key]) cells[key].textContent = pad(value[key]);
            });

            // Hết giờ thì dừng hẳn: đếm tiếp cũng chỉ ra 00:00:00:00 mỗi giây.
            if (left === 0 && timer !== null) clearInterval(timer);
        }

        tick();
        timer = setInterval(tick, 1000);
    })();

    /* ============================================================
       3. HỘP THOẠI "KIỂM TRA 5 PHÚT"
       ============================================================ */

    (function quickCheck() {
        var modal = document.getElementById('quickCheck');
        if (!modal) return;

        var titleEl = modal.querySelector('[data-qcheck-title]');
        var panels  = modal.querySelectorAll('[data-qcheck-panel]');
        var closeEl = modal.querySelector('.qmodal__close');
        var titles  = { lens: 'Chọn tròng', frame: 'Chọn gọng' };

        // Dải khuôn mặt nằm TRONG hộp thoại: lúc còn ẩn mọi phép đo ra 0, nên
        // phải ép vẽ lại ngay sau khi mở.
        var faceStrip = makeStrip(modal, {
            window: '.qface__window',
            track:  '.qface__track',
            prev:   '[data-qface="prev"]',
            next:   '[data-qface="next"]'
        });

        // Nút đã mở hộp thoại — đóng xong trả tiêu điểm bàn phím về đúng đó,
        // nếu không người dùng bàn phím bị ném về đầu trang.
        var opener = null;

        function open(name) {
            panels.forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-qcheck-panel') !== name;
            });

            if (titleEl) titleEl.textContent = titles[name] || '';

            modal.hidden = false;
            document.body.classList.add('is-modal-open');

            if (name === 'frame' && faceStrip) faceStrip.render();
            if (closeEl) closeEl.focus();
        }

        function close() {
            modal.hidden = true;
            document.body.classList.remove('is-modal-open');

            if (opener) opener.focus();
            opener = null;
        }

        document.addEventListener('click', function (ev) {
            var trigger = closestFrom(ev.target, '[data-qcheck-open]');

            if (trigger) {
                // Thẻ <a> có href thật để trang không JS vẫn đi được đâu đó —
                // có JS thì chặn lại và mở hộp thoại thay thế.
                ev.preventDefault();
                opener = trigger;
                open(trigger.getAttribute('data-qcheck-open'));
                return;
            }

            if (closestFrom(ev.target, '[data-qcheck-close]')) close();
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !modal.hidden) close();
        });

        modal.addEventListener('click', function (ev) {
            var lens = closestFrom(ev.target, '[data-qlens]');
            if (lens) {
                pickOne(modal, 'data-qlens', 'data-qlens-rec', lens);
                return;
            }

            var face = closestFrom(ev.target, '[data-qface-pick]');
            if (face) pickOne(modal, 'data-qface-pick', 'data-qface-rec', face);
        });
    })();

    /* ============================================================
       4. BĂNG TRƯỢT KHỐI ĐÁNH GIÁ
       ============================================================ */

    (function reviews() {
        var carousel = document.querySelector('[data-review-carousel]');
        if (!carousel) return;

        makeStrip(carousel, {
            window:   '.hrev__window',
            track:    '.hrev__track',
            prev:     '[data-review="prev"]',
            next:     '[data-review="next"]',
            // Nhịp tự chạy do _layout/home/reviews.php quyết định, không gõ
            // cứng ở đây: đổi tốc độ là việc của người dựng trang, không phải
            // việc phải sửa JavaScript.
            autoplay: parseInt(carousel.getAttribute('data-autoplay'), 10) || 0
        });
    })();

    /* ============================================================
       5. BĂNG TRƯỢT HAI KHỐI SẢN PHẨM ("mới về" và "bán chạy")

       querySelectorAll chứ không querySelector như khối đánh giá: trang chủ có
       HAI băng loại này và mỗi băng cần một bộ đếm vị trí riêng — dùng chung
       một instance thì bấm mũi tên ở khối "bán chạy" sẽ kéo cả khối "mới về".

       KHÔNG truyền autoplay: đánh giá là thứ lướt qua cho biết nên để nó tự
       chạy, còn sản phẩm thì khách đang cân nhắc — món hàng tự trôi đi khi
       người ta đang nhìn nó là một cách gây bực. Xem thêm chú thích đầu
       app/views/_layout/home/new-arrivals.php.
       ============================================================ */

    (function productStrips() {
        var strips = document.querySelectorAll('[data-product-strip]');

        Array.prototype.forEach.call(strips, function (strip) {
            makeStrip(strip, {
                window: '.pstrip__window',
                track:  '.pstrip__track',
                prev:   '[data-strip="prev"]',
                next:   '[data-strip="next"]'
            });
        });
    })();
})();
