<?php

/**
 * order/transfer.php — thanh toán QR (/thanh-toan/chuyen-khoan)
 *
 * Màn THỨ HAI của "Vin Eyewear Checkout.dc.html" (Claude Design):
 *
 *   khung rút gọn (logo + "Thanh toán an toàn")
 *   → hàng đầu: nút quay lại + tiêu đề + mã đơn | BA BƯỚC "làm gì tiếp theo"
 *   → MỘT thẻ hai cột: panel QR nền đỏ đô | tóm tắt đơn + chuyển khoản tay
 *     + nút xác nhận
 *
 * CSS: assets/css/checkout.css (bộ lớp .coqr*)
 *
 * Chỉ tới được ngay sau khi vừa đặt một đơn CHUYỂN KHOẢN — mã đơn đọc từ flash
 * chứ không nằm trên URL, nên không ai dò được đơn của người khác. Xem
 * OrderController::transfer().
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MÃ QR LÀ THẬT, KHÔNG PHẢI HÌNH TRANG TRÍ
 *
 * Bản thiết kế vẽ một ô vuông QR bằng SVG — nó chỉ là hình minh hoạ, quét
 * không ra gì. Ở đây ảnh dựng từ SePay theo đúng bốn thứ trong
 * config/company.php + đơn hàng này: mã ngân hàng, số tài khoản, SỐ TIỀN và
 * NỘI DUNG. Khách quét là app ngân hàng điền sẵn cả bốn.
 *
 * Nội dung chuyển khoản LUÔN là mã đơn — đó là khoá đối chiếu sao kê, xem ghi
 * chú ở khối 'bank' trong config/company.php.
 *
 * Chưa cấu hình `bin` hoặc `number` thì khối QR tự nói "chưa có mã" và khách
 * vẫn thấy đủ số tài khoản bên dưới để chuyển tay. Thà vậy còn hơn hiện một mã
 * QR trỏ sai nơi nhận tiền.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$total = (int) $order['total'];

/*
 * ─────────────────────────────────────────────────────────────────────────────
 * SỐ TIỀN QUÉT QR KHÔNG PHẢI LÚC NÀO CŨNG LÀ TỔNG ĐƠN
 *
 * Đơn có cắt tròng theo độ chỉ thu TIỀN CỌC ở màn này; phần còn lại trả khi
 * nhận hàng. Nếu để mã QR mang số tổng thì khách quét xong chuyển thừa gấp ba
 * lần số phải trả bây giờ — và cửa hàng phải hoàn lại, đúng thứ phiền toái mà
 * cả cơ chế đặt cọc sinh ra để tránh.
 *
 * `deposit_amount` là số đã CHỐT lúc ghi đơn, không tính lại từ tỷ lệ trong
 * config — xem OrderModel::place(). 0 nghĩa là đơn không phải cọc và màn này
 * thu trọn tổng đơn như trước.
 * ─────────────────────────────────────────────────────────────────────────────
 */
$deposit = (int) ($order['deposit_amount'] ?? 0);
$isDeposit = $deposit > 0;
$due       = $isDeposit ? $deposit : $total;

/*
 * Ảnh QR — dựng bởi qr.sepay.vn.
 *
 * MỘT NHÀ CHO CẢ HAI ĐẦU. SePay vừa dựng ảnh mã QR, vừa là bên ĐỌC biến động
 * số dư của chính tài khoản này rồi báo về webhook (xem config/sepay.php). Nhờ
 * vậy lúc đối chiếu "mã QR bảo chuyển X, webhook báo về Y" không phải hỏi hai
 * nơi — và cũng không có nhà cung cấp thứ hai nào để mà chết riêng.
 *
 * Mã sinh ra vẫn theo chuẩn NAPAS nên app ngân hàng nào cũng quét được; chuẩn
 * đó là của NAPAS, không phải của một hãng nào.
 *
 * `template=qronly` = chỉ ô mã, không kèm khung logo ngân hàng — khung 210px
 * của bản thiết kế đã có viền riêng, chồng thêm khung nữa là mã bị bóp nhỏ lại
 * và điện thoại khó bắt.
 *
 * `des` LÀ MÃ ĐƠN, không phải tên khách. Đó là sợi dây duy nhất buộc dòng tiền
 * vào đơn hàng, ở cả hai đầu: nhân viên đọc sao kê, và SepayModel tự khớp.
 */
$qrSrc = !empty($bank['bin']) && !empty($bank['number'])
    ? sprintf(
        'https://qr.sepay.vn/img?acc=%s&bank=%s&amount=%d&des=%s&template=%s',
        rawurlencode((string) $bank['number']),
        rawurlencode((string) $bank['bin']),
        $due,
        rawurlencode($order['code']),
        rawurlencode((string) config('sepay.qr_template', 'qronly'))
    )
    : null;
?>

<section class="coqr">

    <!-- ══════════ HÀNG ĐẦU ══════════ -->
    <div class="coqr__head">

        <?php /* Mắt xích ba nấc — của mẫu. Hai nấc đầu là liên kết thật:
                 giỏ hàng luôn về được, còn "Thanh toán" thì KHÔNG (đơn đã ghi
                 vào CSDL, mở lại form là mời khách đặt trùng một đơn nữa), nên
                 nấc giữa ở đây là chữ chứ không phải liên kết — khác mẫu đúng
                 một chỗ, và khác có lý do. */ ?>
        <nav class="coqr__crumbs" aria-label="Đường dẫn">
            <a href="/gio-hang">Giỏ hàng</a>
            <span class="coqr__crumbsep" aria-hidden="true">›</span>
            <span>Thanh toán</span>
            <span class="coqr__crumbsep" aria-hidden="true">›</span>
            <span class="coqr__crumbhere" aria-current="page">Quét mã QR</span>
        </nav>

        <div class="coqr__titles">
            <div class="coqr__titlebox">
                <h1 class="coqr__title">Thanh toán bằng mã QR</h1>

                <?php
                /* ┌─ MỘT DÒNG THAY CHO DÃY BA BƯỚC ───────────────────────────
                   │ Bản trước có một <ol class="coqr__steps"> ba nấc: "Mở app
                   │ ngân hàng → Quét mã & chuyển tiền → Hệ thống xác nhận".
                   │ Mẫu không có dãy ấy — nó nói cùng chừng ấy việc bằng MỘT
                   │ dòng ngay dưới tiêu đề.
                   │
                   │ Đây là ĐỔI CÁCH NÓI, không phải bỏ thông tin: câu dưới vẫn
                   │ trả lời đúng câu hỏi mà dãy ba bước sinh ra để trả lời —
                   │ "giờ tôi phải làm gì". Dãy ba bước bị gỡ hẳn cùng bộ lớp
                   │ .coqr__step* của nó.
                   └──────────────────────────────────────────────────────── */
                ?>
                <span class="coqr__paysub">
                    Mã đơn <strong><?= e($order['code']) ?></strong>
                    · Quét bằng ứng dụng ngân hàng hoặc ví điện tử bất kỳ
                </span>
            </div>

            <?php /* Bản thiết kế cho nút này quay về FORM thanh toán. Ở đây không
                     quay lại được: đơn đã ghi vào CSDL rồi, và mở lại form là mời
                     khách đặt thêm một đơn trùng. Nên nó theo $doneHref — vừa đặt
                     xong thì sang trang xác nhận, quay lại sau thì về đúng thẻ đơn.
                     Xem OrderController::transfer. */ ?>
            <a class="coqr__back" href="<?= e($doneHref) ?>">← Quay lại đơn hàng</a>
        </div>
    </div>

    <!-- ══════════ THẺ CHÍNH ══════════ -->
    <div class="coqr__card">

        <?php
        /* ────── Cột trái: panel QR nền đỏ đô ──────
           NỀN ĐẬM CHO ĐÚNG MỘT VIỆC. Cả trang chỉ có một thứ khách cần làm ngay
           bây giờ là quét cái mã này; đặt nó lên nền thương hiệu thì mắt tìm ra
           trước cả khi đọc chữ. Phần còn lại của thẻ là thông tin để đối chiếu,
           nền sáng như mọi khối khác của site.

           Bộ chữ trên nền đỏ dùng đúng cặp token của CHÂN TRANG
           (--on-brand / --on-brand-soft / --on-brand-muted) — đó là khối nền
           thương hiệu duy nhất khác của site, và hai chỗ phải đọc như nhau. */
        ?>
        <div class="coqr__pay">
            <h2 class="coqr__paytitle">Quét QR để thanh toán</h2>
            <p class="coqr__paysub">Mở app ngân hàng bất kỳ và quét mã</p>

            <div class="coqr__frame">
                <?php if ($qrSrc !== null): ?>
                    <?php /* `alt` nói đủ số tiền và nội dung: mạng hỏng không tải
                             được ảnh thì khách vẫn đọc được phải chuyển bao nhiêu. */ ?>
                    <img src="<?= e($qrSrc) ?>" width="204" height="204"
                         alt="Mã QR chuyển khoản <?= money($due) ?>, nội dung <?= e($order['code']) ?>">
                <?php else: ?>
                    <p class="coqr__nocode">
                        Chưa có mã QR. Vui lòng chuyển khoản theo thông tin bên cạnh.
                    </p>
                <?php endif; ?>

                <?php
                /* Lớp phủ "hết giờ" — nằm SẴN trong trang, ẩn bằng CSS, chỉ hiện
                   khi assets/js/qr-expire.js gắn lớp .is-qr-expired. Dựng sẵn
                   thay vì để JS tạo ra: tắt JS thì không có đồng hồ nào chạy,
                   nên cũng không được có lớp phủ nào che mã. */
                ?>
                <div class="coqr__again">
                    <p class="coqr__againnote">Mã QR đã hết hạn hiển thị. Tạo mã mới để tiếp tục.</p>
                    <button type="button" class="coqr__againbtn" data-qr-again>Tạo mã mới</button>
                </div>
            </div>

            <div class="coqr__amount">
                <span class="coqr__amountlabel">
                    <?= $isDeposit ? 'Tiền cọc cần chuyển' : 'Số tiền cần chuyển' ?>
                </span>
                <span class="coqr__amountval"><?= money($due) ?></span>
                <?php if ($isDeposit): ?>
                    <span class="coqr__amountrest">
                        Còn lại <?= money($total - $deposit) ?> trả khi nhận hàng
                    </span>
                <?php endif; ?>
            </div>

            <p class="coqr__paynote">
                Số tiền và nội dung chuyển khoản<br>đã được điền sẵn trong mã QR.
            </p>
        </div>

        <!-- ────── Cột phải: đơn hàng + chuyển khoản tay ────── -->
        <div class="coqr__side">

            <div class="coqr__orderhead">
                <h2 class="coqr__ordertitle">Đơn hàng #<?= e($order['code']) ?></h2>
                <span class="coqr__wait"><?= $isDeposit ? 'Chờ đặt cọc' : 'Chờ thanh toán' ?></span>
            </div>

            <?php foreach ($items as $item): ?>
                <div class="coqr__line">
                    <span>
                        <span class="notranslate" translate="no" lang="vi"><?= e($item['product_name']) ?></span> × <?= (int) $item['quantity'] ?>
                        <?php if (!empty($item['lens_name'])): ?>
                            <?php /* Tròng cắt kèm đã nằm trong line_total — nói tên nó
                                     ra để khách không thấy con số cao hơn giá gọng mà
                                     không hiểu vì sao. */ ?>
                            <span class="coqr__lens">+ <?= e($item['lens_name']) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="coqr__linesum"><?= money((int) $item['line_total']) ?></span>
                </div>
            <?php endforeach; ?>

            <?php if ((int) $order['discount'] > 0): ?>
                <?php /* Thiếu dòng này thì cộng các dòng hàng không ra "Tổng đơn",
                         và khách đang chuẩn bị chuyển tiền là lúc tệ nhất để họ
                         nghi hoá đơn tính sai. */ ?>
                <div class="coqr__line">
                    <span>Giảm giá</span>
                    <span class="coqr__linesum">−<?= money((int) $order['discount']) ?></span>
                </div>
            <?php endif; ?>

            <?php if ((int) $order['shipping_fee'] > 0): ?>
                <div class="coqr__line">
                    <span>Phí giao hàng</span>
                    <span class="coqr__linesum"><?= money((int) $order['shipping_fee']) ?></span>
                </div>
            <?php endif; ?>

            <div class="coqr__total">
                <span class="coqr__totallabel">Tổng đơn</span>
                <span class="coqr__totalval"><?= money($total) ?></span>
            </div>

            <?php if ($isDeposit): ?>
                <?php /* Chia đôi tổng đơn thành hai lần trả, ngay dưới dòng tổng để
                         mắt đọc được phép tính: hai số cộng lại đúng bằng số ở trên. */ ?>
                <div class="coqr__split">
                    <div class="coqr__line">
                        <span>Đặt cọc <?= (int) ($order['deposit_rate'] ?? 0) ?>% — chuyển ngay</span>
                        <span class="coqr__linesum coqr__linesum--due"><?= money($deposit) ?></span>
                    </div>
                    <div class="coqr__line">
                        <span>Còn lại — trả khi nhận hàng</span>
                        <span class="coqr__linesum"><?= money($total - $deposit) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($bank['number'])): ?>
                <?php
                /* CHUYỂN KHOẢN TAY LÀ ĐƯỜNG LUI, VÀ NHÃN NÓI ĐÚNG NHƯ VẬY.
                   Trước đây khối này đứng ngang hàng với mã QR nên khách phải tự
                   đoán nên dùng cái nào. Quét mã luôn tốt hơn: số tiền và nội
                   dung đã điền sẵn, không gõ sai được. Gõ tay chỉ dành cho lúc
                   quét không ra — máy tính bàn, camera hỏng, app không đọc được. */
                ?>
                <span class="coqr__manual">Chuyển khoản thủ công — nếu không quét được mã</span>

                <?php
                /* ┌─ MỖI DÒNG LÀ MỘT HÀNG THẬT ───────────────────────────────
                   │ Bản trước đổ tám <span> và hai <button> thẳng vào
                   │ .coqr__bank rồi để `flex-direction: column` tự xuống dòng —
                   │ nghĩa là KHÔNG có hàng nào cả, chỉ có mười phần tử xếp dọc.
                   │ Hệ quả: hai nút "Sao chép" thành hai dòng riêng chiếm hết
                   │ bề ngang, và không thể kẻ nét dưới từng cặp khoá–giá trị
                   │ như mẫu, vì không có cái gì để kẻ.
                   │
                   │ Nay mỗi cặp nằm trong một .coqr__line. Nút sao chép đi
                   │ kèm giá trị trong cùng một ô bên phải.
                   │
                   │ ⚠ copy-btn.js bắt theo `.js-copy[data-copy]` bằng uỷ quyền
                   │   từ document, nên bọc thêm một lớp <span> KHÔNG ảnh hưởng
                   │   gì tới nó. Đã kiểm trước khi đổi.
                   └──────────────────────────────────────────────────────── */
                ?>
                <div class="coqr__bank">
                    <div class="coqr__line">
                        <span class="coqr__bankkey">Ngân hàng</span>
                        <span class="coqr__bankval coqr__bankval--wide"><?= e($bank['name']) ?></span>
                    </div>

                    <div class="coqr__line">
                        <span class="coqr__bankkey">Số tài khoản</span>
                        <span class="coqr__bankval">
                            <?= e($bank['number']) ?>
                            <button type="button" class="coqr__copy js-copy"
                                    data-copy="<?= e($bank['number']) ?>">Sao chép</button>
                        </span>
                    </div>

                    <div class="coqr__line">
                        <span class="coqr__bankkey">Chủ tài khoản</span>
                        <span class="coqr__bankval coqr__bankval--wide"><?= e($bank['holder']) ?></span>
                    </div>

                    <div class="coqr__line">
                        <span class="coqr__bankkey">Nội dung</span>
                        <span class="coqr__bankval">
                            <?= e($order['code']) ?>
                            <button type="button" class="coqr__copy js-copy"
                                    data-copy="<?= e($order['code']) ?>">Sao chép</button>
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <?php /* Chưa cấu hình tài khoản nhận tiền — thà hứa một cuộc gọi
                         còn hơn in một số tài khoản không có thật. */ ?>
                <p class="coqr__nocode">
                    Nhân viên sẽ gọi và đọc thông tin chuyển khoản trong ít phút nữa.
                </p>
            <?php endif; ?>

            <?php /* margin-top:auto đẩy cụm này xuống đáy cột — xem .coqr__confirm.
                     Nút xác nhận phải nằm ở cuối đường đọc, sau khi khách đã thấy
                     đủ số tiền và thông tin chuyển khoản. */ ?>
            <?php
            /* ─────────────────────────────────────────────────────────────
               KHÔNG CÒN NÚT "TÔI ĐÃ CHUYỂN KHOẢN"

               Nút đó chỉ là lời khách nói, và nó dẫn thẳng sang trang xác
               nhận — tức website khẳng định đã nhận tiền trong khi chưa ai
               đối chiếu sao kê. Nay chỗ này là một khối CHỜ: trang tự hỏi
               máy chủ vài giây một lần và chỉ chuyển đi khi
               orders.payment_status đã thật sự đổi.

               data-pay-watch là chỗ assets/js/pay-watch.js bám vào. Thiếu
               file đó thì khối này vẫn đọc được — nó nói đúng việc đang xảy
               ra ("đang chờ xác nhận"), chỉ là không tự chuyển trang; lối ra
               nằm ngay dưới, ở liên kết .coqr__slow luôn hiện trong trường
               hợp không có JS.
               ───────────────────────────────────────────────────────────── */
            ?>
            <div class="coqr__confirm"
                 data-pay-watch
                 data-watch-url="/thanh-toan/trang-thai?ma=<?= e(rawurlencode($order['code'])) ?>">

                <p class="coqr__watch">
                    <span class="coqr__watchdot" aria-hidden="true"></span>
                    <span class="coqr__watchtext" role="status">
                        Đang chờ xác nhận chuyển khoản <?= money($due) ?>…
                    </span>
                </p>

                <?php
                /* ┌─ ĐỒNG HỒ 5 PHÚT — thêm 13/09/2026 ────────────────────────────
                   │ Bản thiết kế "Thanh toan.dc.html" vẽ dòng "Mã QR hết hạn sau
                   │ mm:ss". Chủ dự án chốt: cho mã hết hạn thật sau 5 phút nếu
                   │ chưa ai quét.
                   │
                   │ ⚠ "HẾT HẠN" Ở ĐÂY NGHĨA LÀ GÌ — đọc kỹ trước khi sửa.
                   │ Mã QR của SePay KHÔNG có thời hạn ở phía ngân hàng: nó đối
                   │ soát theo NỘI DUNG chuyển khoản (mã đơn), nên một cú chuyển
                   │ tiền sau 5 phút vẫn khớp đúng đơn này và vẫn được ghi nhận.
                   │ Thời hạn này là của TRANG, không phải của ngân hàng, và nó
                   │ làm đúng hai việc có thật:
                   │
                   │   · dừng vòng hỏi máy chủ của pay-watch.js — không để một
                   │     tab bỏ quên hỏi mãi;
                   │   · buộc khách bấm một cái để xác nhận họ vẫn ở đây, rồi
                   │     trang nạp lại và mở một cửa sổ 5 phút mới.
                   │
                   │ Nên CHỮ PHẢI NÓI ĐÚNG CHỪNG ẤY. Đừng đổi thành "mã không còn
                   │ dùng được" hay "ngân hàng đã từ chối" — cả hai đều sai, và
                   │ sai theo hướng làm khách đã chuyển tiền hoảng lên.
                   │
                   │ KHÔNG chặn ở máy chủ, và đó là chủ ý: chặn nghĩa là từ chối
                   │ tiền khách đã chuyển thật. Việc duy nhất máy chủ làm vẫn là
                   │ đối soát theo mã đơn.
                   │
                   │ <time datetime> để trình đọc màn hình đọc ra một quãng thời
                   │ gian chứ không phải hai con số rời. */
                ?>
                <p class="coqr__expire" data-qr-expire="300" hidden>
                    Mã QR hết hạn sau
                    <time class="coqr__expiretime" data-qr-clock datetime="PT5M">05:00</time>
                </p>

                <?php
                /* ─────────────────────────────────────────────────────────────
                   "TÔI ĐÃ CHUYỂN KHOẢN — KIỂM TRA GIÚP TÔI" — FR-TT-05

                   SRS viết yêu cầu này khi trang còn một nút dẫn thẳng sang
                   trang biên nhận, tức website tự khẳng định đã nhận tiền trong
                   khi chưa ai đối chiếu sao kê. Nút ấy đã gỡ từ trước (xem khối
                   trên) và thay bằng khối chờ tự hỏi máy chủ.

                   Phần CÒN THIẾU là chỗ dành cho người sốt ruột: khách chuyển
                   xong, nhìn một dấu chấm nhấp nháy, và không biết mình còn phải
                   làm gì nữa. Nút này trả lời đúng câu đó — nó KHÔNG hứa đã nhận
                   tiền, chỉ đưa họ sang trang đơn hàng kèm đúng câu SRS yêu cầu:
                   "Đơn của bạn đã được ghi nhận. Chúng tôi đang chờ tiền về và
                   sẽ báo lại ngay."

                   Là một liên kết chứ không phải nút gửi form: nó không ghi gì
                   cả. Bấm nhầm, bấm lại, hay mở ở tab mới đều vô hại. */
                ?>
                <?php
                /* KHÔNG nối "&da-chuyen=1" vào $orderHref — nó KẾT THÚC BẰNG
                   MỘT NEO (#<mã đơn>, xem OrderController::transfer). Nối thêm
                   thì tham số rơi vào PHẦN NEO, trình duyệt không gửi lên máy
                   chủ, $_GET['da-chuyen'] không bao giờ có, và câu trấn an ở
                   trang đơn hàng không bao giờ hiện. Neo cũng hỏng theo.

                   Dựng lại địa chỉ từ mã đơn: tham số trước, neo sau. */
                $veDon = '/tai-khoan?muc=don-hang&da-chuyen=1&don='
                       . rawurlencode((string) $order['code'])
                       . '#' . rawurlencode((string) $order['code']);
                ?>
                <a class="coqr__checked" href="<?= e($veDon) ?>">
                    Tôi đã chuyển khoản — kiểm tra giúp tôi
                </a>

                <?php /* Lối ra khi chờ mãi không thấy. pay-watch.js ẩn khối này
                         đi lúc trang mở và chỉ đưa nó ra sau vài phút — không
                         có JS thì nó hiện sẵn, và đó đúng là lúc cần nó nhất. */ ?>
                <p class="coqr__slow js-watch-slow">
                    Đã chuyển tiền nhưng chưa thấy xác nhận? Bạn cứ đóng trang này —
                    đơn vẫn được giữ. Xem lại ở
                    <a href="<?= e($orderHref) ?>">đơn hàng của tôi</a>,
                    hoặc nhắn Zalo
                    <a href="<?= e(config('company.channels.zalo')) ?>"
                       target="_blank" rel="noopener"><?= e(config('company.zalo')) ?></a>
                    để được đối chiếu ngay.
                </p>

                <p class="coqr__note">
                    <?php
                    /* CÂU NÀY ĐỔI THEO VIỆC ĐANG THẬT SỰ XẢY RA. Bản thiết kế viết
                       "xác nhận tự động sau 1–2 phút" — chỉ đúng khi SePay đã bật và
                       đang đọc biến động số dư. Chưa bật thì đó là lời hứa không ai
                       giữ. Chốt theo config('sepay.enabled'). */
                    ?>
                    <?php if (config('sepay.enabled')): ?>
                        Trang này tự chuyển sang biên nhận ngay khi tiền về,
                        thường sau 1–2 phút. Không cần bấm gì thêm.
                    <?php else: ?>
                        Chúng tôi đối chiếu giao dịch trong giờ làm việc
                        (<?= e(config('company.open_hours')) ?>); trang này tự chuyển
                        sang biên nhận ngay khi xong. Không cần bấm gì thêm.
                    <?php endif; ?>
                    <br>
                    Cần hỗ trợ? Gọi
                    <a href="<?= e(config('company.hotline_href')) ?>"><?= e(config('company.hotline')) ?></a>
                    hoặc Zalo
                    <a href="<?= e(config('company.channels.zalo')) ?>"
                       target="_blank" rel="noopener"><?= e(config('company.zalo')) ?></a>.
                </p>
            </div>
        </div>
    </div>
</section>
