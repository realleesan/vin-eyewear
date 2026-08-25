<?php

/**
 * config/policy.php
 *
 * Nội dung trang Chính sách & Cam kết chất lượng.
 * Port từ src/routes/chinh-sach.tsx của bản Lovable.
 *
 * Để ở config vì đây là văn bản tĩnh do người làm nội dung sửa, không phải
 * dữ liệu nghiệp vụ cần trang quản trị. Chuyển sang DB sau này thì chỉ việc
 * đổi nguồn trong PolicyController, view giữ nguyên.
 *
 * 'short' là nhãn NGẮN cho cột điều hướng bên trái; 'label' là tiêu đề <h2>
 * đầy đủ của nhóm. Bản thiết kế cố ý để hai chuỗi khác nhau — cột hẹp 256px
 * không chứa nổi "Chính sách Bảo hành & Sửa chữa" trên một dòng.
 *
 * 'id' của mỗi nhóm cũng là neo URL: /chinh-sach#doi-tra — header và footer
 * đang trỏ tới #doi-tra, #bao-mat, #dieu-khoan, ĐỪNG đổi nếu không sửa cả
 * những chỗ đó.
 *
 * SỐ ĐIỆN THOẠI ĐỌC TỪ config/company.php, KHÔNG GÕ CỨNG.
 *
 * Ba câu trả lời bên dưới từng ghi thẳng "1900 6868". Khi cửa hàng đổi tổng
 * đài, company.php đã sửa mà trang chính sách vẫn mời khách gọi số cũ — không
 * có gì báo, vì đó chỉ là một chuỗi trong mảng. Chính vì thế company.php tự
 * nhận là "NGUỒN DUY NHẤT cho hotline, email, địa chỉ".
 *
 * Gọi config() từ trong một file config KHÁC là an toàn: helper nạp theo từng
 * file và có cache riêng, nên company.php chỉ được require một lần và không có
 * vòng lặp nào — miễn là hai file không gọi chéo lẫn nhau.
 */

return [

    // 4 cam kết nổi bật, hiện thành lưới thẻ ngay dưới hero
    'highlights' => [
        [
            'icon'  => 'wrench',
            'title' => 'Bảo Hành Trọn Đời',
            'desc'  => 'Nắn chỉnh gọng, thay ốc & đệm mũi miễn phí tại mọi cơ sở.',
        ],
        [
            'icon'  => 'refresh',
            'title' => '7 Ngày Đổi Mẫu',
            'desc'  => 'Áp dụng cho gọng kính chưa qua sử dụng, còn nguyên phụ kiện.',
        ],
        [
            'icon'  => 'eye',
            'title' => 'Bảo Hành Độ Cận',
            'desc'  => 'Đo lại & hỗ trợ tròng trong 7 ngày nếu bạn chưa êm mắt.',
        ],
        [
            'icon'  => 'truck',
            'title' => 'Đồng Kiểm Khi Nhận',
            'desc'  => 'Mở hàng kiểm tra cùng shipper trước khi thanh toán.',
        ],
    ],

    'groups' => [
        [
            'id'    => 'bao-hanh',
            'short' => 'Bảo hành & Sửa chữa',
            'label' => 'Chính sách Bảo hành & Sửa chữa',
            'icon'  => 'shield',
            'intro' => 'Mọi sản phẩm Vin Eyewear đều được bảo hành chính hãng và hỗ trợ dịch vụ chăm sóc trọn đời.',
            'items' => [
                /* HAI MỨC BẢO HÀNH CHO KÍNH CẮT THEO ĐỘ, chia theo NƠI LẤY SỐ
                   ĐO — không phải theo sản phẩm. Cửa hàng chịu trách nhiệm
                   được tới đâu là tuỳ vào việc số đo do ai lấy: đo tại chỗ thì
                   đó là số của cửa hàng, còn số khách gửi qua thì cửa hàng chỉ
                   mài đúng theo con số nhận được.

                   Đặt câu này ĐẦU nhóm vì nó là thứ khác nhau giữa hai luồng
                   mua, và cũng là thứ khách hỏi trước khi quyết định đo ở đâu. */
                [
                    'q' => 'Kính cắt theo độ được bảo hành bao lâu?',
                    'a' => 'Tuỳ vào nơi lấy số đo. Đo mắt và cắt kính trực tiếp tại cửa hàng: '
                         . 'bảo hành trọn đời — số đo do chúng tôi lấy nên chúng tôi chịu trách '
                         . 'nhiệm tới cùng, kể cả khi cần đo lại và làm lại tròng. '
                         . 'Kính làm theo số đo khách tự gửi: bảo hành 10 ngày kể từ ngày nhận, '
                         . 'đủ để bạn đeo thử và báo lại nếu chưa êm mắt.',
                ],
                [
                    'q' => 'Bảo hành trọn đời gồm những dịch vụ nào?',
                    'a' => 'Nắn chỉnh gọng, siết & thay ốc, thay đệm mũi, vệ sinh kính bằng máy sóng siêu âm — miễn phí trọn đời tại tất cả cơ sở, không giới hạn số lần.',
                ],
                [
                    'q' => 'Thời gian bảo hành lỗi nhà sản xuất là bao lâu?',
                    'a' => '24 tháng kể từ ngày mua đối với gọng kính và tròng kính chính hãng: bong lớp phủ, gãy khớp bản lề, bong tróc màu do lỗi vật liệu.',
                ],
                [
                    'q' => 'Trường hợp nào không được bảo hành?',
                    'a' => 'Sản phẩm bị rơi vỡ, biến dạng do ngoại lực, tự tháo lắp tại nơi khác, hoặc trầy xước tròng do vệ sinh sai cách (dùng nước nóng, hoá chất mạnh).',
                ],
                [
                    'q' => 'Tôi có cần giữ hoá đơn không?',
                    'a' => 'Không bắt buộc. Chúng tôi tra cứu bằng số điện thoại đặt hàng hoặc mã đơn hàng trong hệ thống.',
                ],
            ],
        ],
        [
            'id'    => 'doi-tra',
            'short' => 'Đổi trả & Hoàn tiền',
            'label' => 'Chính sách Đổi trả & Hoàn tiền',
            'icon'  => 'refresh',
            'intro' => 'Đổi mẫu linh hoạt trong 7 ngày để bạn luôn hài lòng với lựa chọn của mình.',
            'items' => [
                /* Câu HỦY ĐƠN đứng đầu nhóm, trước cả điều kiện đổi mẫu.
                   Website không có nút huỷ đơn — cửa hàng tự đi giao và không
                   đồng bộ trạng thái vận chuyển thời gian thực — nên đây là
                   nơi duy nhất khách tra ra được cách huỷ khi họ không còn ở
                   trang tài khoản. Chôn nó xuống cuối nhóm thì coi như không
                   có. */
                [
                    'q' => 'Tôi muốn huỷ đơn hàng thì làm thế nào?',
                    /* Zalo và hotline nay CÙNG một số nên chỉ đọc số MỘT lần —
                       nhắc hai lần trong một câu thì khách tưởng là hai số khác
                       nhau rồi dừng lại đối chiếu. */
                    'a' => 'Bạn nhắn Zalo hoặc gọi hotline ' . config('company.hotline')
                         . ' cho cửa hàng, kèm mã đơn. '
                         . 'Đơn chưa giao đi thì nhân viên huỷ giúp bạn ngay. Website không có '
                         . 'nút huỷ tự động vì chúng tôi tự vận chuyển và muốn xác nhận trực '
                         . 'tiếp với bạn, tránh trường hợp đơn đã lên đường mà hệ thống báo đã huỷ.',
                ],
                /* ĐẶT CỌC — đứng ngay sau câu huỷ đơn vì hai thứ này luôn được
                   hỏi cùng nhau: "tôi huỷ được không" và "tiền cọc của tôi thì
                   sao". Trả lời một mà bỏ câu kia là mời một cuộc gọi hotline. */
                [
                    'q' => 'Vì sao đơn cắt tròng theo độ phải đặt cọc 30%?',
                    'a' => 'Tròng được mài riêng theo số đo của bạn nên không dùng lại cho khách '
                         . 'khác được. Khoản cọc 30% giúp chúng tôi bắt đầu gia công ngay, và áp '
                         . 'dụng cho cả đơn COD lẫn chuyển khoản. Phần còn lại bạn thanh toán khi '
                         . 'nhận kính. Đơn chỉ mua gọng (đã kèm tròng demo chưa cắt độ) không cần '
                         . 'đặt cọc.',
                ],
                [
                    'q' => 'Huỷ đơn rồi thì tiền cọc có được hoàn không?',
                    'a' => 'Nếu chúng tôi chưa bắt đầu mài tròng, tiền cọc được hoàn đủ. Tròng đã '
                         . 'vào máy thì khoản cọc bù cho phần vật tư và công đã bỏ ra. Gọi '
                         . config('company.hotline') . ' sớm nhất có thể để chúng tôi kịp dừng.',
                ],
                [
                    'q' => 'Điều kiện đổi mẫu trong 7 ngày?',
                    'a' => 'Gọng kính chưa qua sử dụng, không trầy xước, còn nguyên tem và đầy đủ phụ kiện (hộp, khăn lau, túi).',
                ],
                [
                    'q' => 'Tròng kính đã cắt theo độ có đổi được không?',
                    'a' => 'Tròng kính cắt theo đơn độ riêng không áp dụng đổi trả, nhưng được hỗ trợ đo lại và điều chỉnh trong 7 ngày nếu chưa êm mắt.',
                ],
                [
                    'q' => 'Quy trình hoàn tiền diễn ra thế nào?',
                    'a' => 'Với sản phẩm lỗi từ nhà sản xuất, chúng tôi hoàn 100% giá trị qua chuyển khoản trong 3-5 ngày làm việc sau khi nhận lại hàng.',
                ],
                [
                    'q' => 'Chi phí đổi trả do ai chịu?',
                    'a' => 'Vin Eyewear chịu toàn bộ phí vận chuyển nếu lỗi thuộc về chúng tôi. Trường hợp đổi vì lý do sở thích, khách hàng hỗ trợ phí giao nhận.',
                ],
                /* HOÁ ĐƠN ĐỎ — website chưa xuất tự động, cửa hàng làm tay.
                   Nói ra thay vì im lặng: khách công ty cần hoá đơn để hoàn
                   thuế sẽ đi tìm nút "xuất hoá đơn" trên trang đơn hàng, không
                   thấy thì tưởng cửa hàng không xuất được. */
                [
                    'q' => 'Tôi cần hoá đơn đỏ để hoàn thuế công ty thì làm thế nào?',
                    'a' => 'Website chưa xuất hoá đơn điện tử tự động. Bạn nhắn Zalo hoặc gọi '
                         . config('company.hotline')
                         . ' kèm mã đơn và thông tin xuất hoá đơn (tên công ty, mã số '
                         . 'thuế, địa chỉ), nhân viên sẽ xuất và gửi cho bạn.',
                ],
            ],
        ],
        [
            'id'    => 'do-mat',
            'short' => 'Đo mắt & Tròng kính',
            'label' => 'Chính sách Đo mắt & Tròng kính',
            'icon'  => 'eye',
            'intro' => 'Quy trình khúc xạ chuẩn phòng khám, thực hiện bởi kỹ thuật viên được đào tạo chuyên sâu.',
            'items' => [
                [
                    'q' => 'Đo mắt tại Vin Eyewear có mất phí không?',
                    'a' => 'Hoàn toàn miễn phí cho mọi khách hàng, kể cả khi bạn không mua kính.',
                ],
                [
                    'q' => 'Quy trình đo khúc xạ gồm những bước nào?',
                    'a' => 'Khai thác tiền sử thị lực, đo máy tự động, thử kính thử, kiểm tra thị lực hai mắt, cân bằng độ và tư vấn tròng phù hợp nhu cầu sử dụng.',
                ],
                [
                    'q' => 'Bao lâu thì lắp xong kính?',
                    'a' => 'Thông thường 30-60 phút với tròng có sẵn. Tròng đặc biệt (đa tiêu, chiết suất cao, đổi màu) cần 3-5 ngày làm việc.',
                ],
                [
                    'q' => 'Kính mới đeo bị mỏi mắt thì sao?',
                    'a' => 'Hãy quay lại trong 7 ngày, kỹ thuật viên sẽ đo lại và điều chỉnh độ hoặc tâm tròng miễn phí.',
                ],
            ],
        ],
        [
            'id'    => 'giao-hang',
            'short' => 'Giao hàng & Đồng kiểm',
            'label' => 'Giao hàng & Đồng kiểm',
            'icon'  => 'truck',
            'intro' => 'Giao toàn quốc, cho phép mở hàng kiểm tra trước khi thanh toán.',
            'items' => [
                [
                    'q' => 'Phí vận chuyển tính thế nào?',
                    'a' => '30.000đ cho đơn dưới 1.000.000đ; miễn phí vận chuyển cho đơn từ 1.000.000đ.',
                ],
                [
                    'q' => 'Thời gian giao hàng bao lâu?',
                    'a' => 'Hà Nội 1-2 ngày, các tỉnh thành khác 2-5 ngày làm việc kể từ khi đơn được xác nhận.',
                ],
                [
                    'q' => 'Tôi được đồng kiểm khi nhận hàng chứ?',
                    'a' => 'Có. Bạn được mở hộp kiểm tra cùng shipper; nếu sản phẩm sai mẫu hoặc hư hỏng, bạn có thể từ chối nhận và không mất phí.',
                ],
                [
                    'q' => 'Có hỗ trợ thanh toán khi nhận hàng (COD)?',
                    'a' => 'Có, áp dụng toàn quốc. Đơn hàng sẽ được gọi xác nhận qua điện thoại trước khi giao.',
                ],
            ],
        ],
        /*
         * ─────────────────────────────────────────────────────────────────
         * ĐIỀU KHOẢN DỊCH VỤ — BẢN NHÁP, CHƯA QUA PHÁP CHẾ
         *
         * Toàn bộ câu trả lời dưới đây MÔ TẢ ĐÚNG những gì hệ thống đang làm,
         * và mọi con số đều lấy từ mã nguồn chứ không bịa:
         *
         *   · cọc 30%            config/app.php ['deposit_rate']
         *   · phí ship 30.000đ   config/app.php ['shipping_fee']
         *   · miễn ship từ 1tr   config/app.php ['free_shipping_threshold']
         *   · đổi/huỷ lịch hẹn   BookingModel::changeBlocker()
         *   · xác minh SĐT       AuthController, chặng 'ma'
         *   · giữ hồ sơ 5 năm    chính mục 'bao-mat' ngay bên dưới
         *
         * CỐ Ý KHÔNG CÓ ba loại điều khoản mà một văn bản pháp lý đầy đủ
         * thường có: giới hạn trách nhiệm, luật áp dụng và cơ quan giải quyết
         * tranh chấp, quyền sở hữu trí tuệ. Chúng là CAM KẾT PHÁP LÝ, không
         * phải mô tả tính năng — viết chúng ra từ việc đọc mã nguồn là bịa
         * ràng buộc thay cho doanh nghiệp. Pháp chế bổ sung.
         *
         * Vì vậy mục này CHƯA được nối vào ô "Tôi đã đọc và đồng ý" ở màn đăng
         * ký: config/auth.php ['consent']['terms_url'] vẫn để rỗng. Bật lên là
         * một dòng, nhưng nên bật SAU khi có người duyệt nội dung — xin người
         * dùng đồng ý với một văn bản chưa ai duyệt thì cái tick mất ý nghĩa.
         * ─────────────────────────────────────────────────────────────────
         */
        [
            'id'    => 'dieu-khoan',
            'short' => 'Điều khoản dịch vụ',
            'label' => 'Điều khoản dịch vụ',
            'icon'  => 'handshake',
            'intro' => 'Những gì bạn và Vin Eyewear cùng thống nhất khi bạn tạo tài khoản, đặt hàng hoặc đặt lịch đo mắt.',
            'items' => [
                [
                    'q' => 'Tạo tài khoản cần những gì?',
                    'a' => 'Một số điện thoại còn dùng được — chúng tôi gửi mã xác minh tới số đó trước khi tài khoản được tạo. Email không bắt buộc, nhưng nên có để đăng nhập được cả bằng email và lấy lại mật khẩu khi bạn đổi số. Bạn cũng có thể đăng nhập bằng tài khoản Google.',
                ],
                [
                    'q' => 'Tôi chịu trách nhiệm gì với tài khoản của mình?',
                    'a' => 'Giữ kín mật khẩu và mã xác minh. Mọi đơn hàng, lịch hẹn đặt từ tài khoản đang đăng nhập được xem là do bạn thực hiện. Nếu nghi ngờ người khác truy cập được, hãy đổi mật khẩu và gọi hotline ngay.',
                ],
                [
                    'q' => 'Thông tin bạn cung cấp phải chính xác tới mức nào?',
                    'a' => 'Họ tên, số điện thoại và địa chỉ nhận hàng cần đúng để chúng tôi giao được hàng và gọi xác nhận. Với đơn có cắt tròng theo độ, số đo bạn khai là căn cứ để mài tròng — sai số đo thì tròng đã mài không dùng lại được cho ai khác, nên hãy kiểm tra kỹ hoặc để kỹ thuật viên đo tại cửa hàng.',
                ],
                [
                    'q' => 'Đặt hàng trên web đã là hợp đồng chưa?',
                    'a' => 'Chưa. Đơn bạn gửi là đề nghị mua; giao dịch được xác lập khi chúng tôi gọi điện xác nhận đơn. Trong khoảng đó, nếu sản phẩm hết hàng hoặc giá hiển thị sai, chúng tôi sẽ báo bạn và hoàn lại toàn bộ khoản đã thanh toán.',
                ],
                [
                    'q' => 'Khi nào phải đặt cọc?',
                    'a' => 'Đơn có cắt tròng theo độ phải cọc trước 30% tổng đơn, áp dụng cho cả COD lẫn chuyển khoản. Lý do: tròng được mài riêng theo số đo của bạn, không bán lại cho người khác được. Đơn chỉ mua gọng thì không cọc đồng nào.',
                ],
                [
                    'q' => 'Giá và phí hiển thị đã gồm những gì?',
                    'a' => 'Giá sản phẩm hiển thị bằng đồng Việt Nam. Phí vận chuyển 30.000đ với đơn dưới 1.000.000đ và miễn phí từ 1.000.000đ, được cộng vào trước khi bạn xác nhận đặt hàng — tổng cuối cùng luôn hiện đầy đủ ở bước thanh toán.',
                ],
                [
                    'q' => 'Mã giảm giá dùng thế nào?',
                    'a' => 'Mỗi mã có điều kiện riêng về giá trị đơn tối thiểu và hạn sử dụng, hiện ngay khi bạn nhập mã. Mã đã hết hạn hoặc đơn chưa đạt mức tối thiểu sẽ bị từ chối kèm lý do. Không cộng dồn nhiều mã trên cùng một đơn.',
                ],
                [
                    'q' => 'Đặt lịch đo mắt có ràng buộc gì không?',
                    'a' => 'Không mất phí và không giữ chỗ theo giờ: bạn chọn ngày, cửa hàng gọi lại để thống nhất giờ cụ thể. Bạn tự đổi ngày hoặc huỷ trong trang tài khoản cho tới trước ngày hẹn; sang tới ngày hẹn thì gọi hotline để nhân viên còn kịp xếp lại lịch.',
                ],
                [
                    'q' => 'Bảo hành và đổi trả theo điều khoản nào?',
                    'a' => 'Theo đúng hai mục "Bảo hành & Sửa chữa" và "Đổi trả & Hoàn tiền" trên chính trang này. Chúng là một phần của điều khoản dịch vụ, không phải cam kết riêng lẻ.',
                ],
                [
                    'q' => 'Dữ liệu của tôi được xử lý ra sao?',
                    'a' => 'Theo mục "Bảo mật thông tin" ngay bên dưới. Riêng hồ sơ khúc xạ là dữ liệu sức khoẻ, chỉ nhân viên chuyên môn của Vin Eyewear truy cập được để phục vụ việc đo mắt và cắt tròng cho bạn; hồ sơ được giữ 5 năm trừ khi bạn yêu cầu xoá sớm hơn.',
                ],
                [
                    'q' => 'Điều khoản thay đổi thì tôi biết bằng cách nào?',
                    'a' => 'Bản đang áp dụng luôn là bản hiển thị tại trang này, và mỗi bản có một số phiên bản riêng. Khi bạn tạo tài khoản, chúng tôi ghi lại bạn đã đồng ý với phiên bản nào — nên nếu về sau có tranh luận, cả hai bên đối chiếu được đúng văn bản tại thời điểm đó.',
                ],
            ],
        ],
        [
            'id'    => 'bao-mat',
            'short' => 'Bảo mật thông tin',
            'label' => 'Bảo mật thông tin',
            'icon'  => 'shield',
            'intro' => 'Dữ liệu cá nhân và hồ sơ khúc xạ của bạn được lưu trữ an toàn, chỉ dùng cho mục đích chăm sóc.',
            'items' => [
                [
                    'q' => 'Vin Eyewear thu thập những thông tin gì?',
                    'a' => 'Họ tên, số điện thoại, địa chỉ giao hàng và hồ sơ khúc xạ — phục vụ giao hàng, đặt lịch và tư vấn tròng kính phù hợp.',
                ],
                [
                    'q' => 'Thông tin của tôi có được chia sẻ cho bên thứ ba?',
                    'a' => 'Không. Chúng tôi chỉ chia sẻ địa chỉ và số điện thoại cho đơn vị vận chuyển để hoàn tất giao hàng.',
                ],
                [
                    'q' => 'Tôi có thể yêu cầu xoá dữ liệu không?',
                    'a' => 'Có. Gửi yêu cầu qua hotline hoặc trang Liên hệ, chúng tôi sẽ xoá hồ sơ trong 7 ngày làm việc.',
                ],
                [
                    'q' => 'Dữ liệu hồ sơ khúc xạ được lưu bao lâu?',
                    'a' => 'Lưu trong 5 năm để tiện theo dõi tiến triển thị lực, trừ khi bạn yêu cầu xoá sớm hơn.',
                ],
            ],
        ],
    ],
];
