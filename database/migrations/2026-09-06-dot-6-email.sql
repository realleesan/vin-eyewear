-- ============================================================================
-- 2026-09-06 — ĐỢT 6: EMAIL TỰ ĐỘNG GỬI CHO KHÁCH
--
-- Căn cứ: SRS v2.1.0 — FR-EM-01..09, FR-SP-18, FR-LH-08, và vế còn thiếu của
-- FR-TT-11 (báo khách trước khi tự huỷ đơn 2 giờ).
--
-- Hai bảng:
--
--   1. `email_templates`  mẫu thư, mỗi mẫu hai bản Việt / Anh   (FR-EM-09)
--   2. `email_queue`      hàng chờ + sổ kết quả gửi             (FR-EM-05)
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO LÀ HÀNG CHỜ CHỨ KHÔNG GỬI THẲNG
--
-- Ba lý do, và cả ba đều là ràng buộc thật của dự án này:
--
--   FR-EM-06 cấm việc gửi thư chặn nghiệp vụ chính. Đặt hàng, đổi trạng thái
--   hay huỷ đơn phải thành công kể cả khi máy chủ thư không trả lời. Gửi thẳng
--   nghĩa là một lần đặt hàng phải đợi hết hạn kết nối SMTP.
--
--   FR-EM-05 đòi ghi nhận kết quả gửi và cho gửi lại. Không có bảng thì không
--   có gì để ghi và không có gì để gửi lại.
--
--   HOSTING HIỆN TẠI KHÔNG GỬI ĐƯỢC. InfinityFree bản miễn phí vô hiệu hoá
--   hàm mail() và chặn cổng SMTP ra ngoài (xem config/mail.php). Nghĩa là ngay
--   sau đợt này, mọi thư sẽ NẰM LẠI trong bảng ở trạng thái 'cho'. Đó là hành
--   vi đúng và cố ý: ngày cửa hàng đổi hosting hoặc nối một đường gửi khác,
--   toàn bộ hàng chờ tự chảy đi mà không mất thư nào.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- CREATE TABLE IF NOT EXISTS, và phần chèn mẫu thư dùng INSERT IGNORE nên chạy
-- lại không ghi đè câu chữ cửa hàng đã sửa.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. `email_templates` — MẪU THƯ, HAI NGÔN NGỮ
--
-- `key` là mã sự kiện, khớp với chuỗi các lớp EmailEvents và OrderModel
-- truyền vào EmailQueueModel::xepHang(). Nó là
-- khoá chính: mỗi sự kiện đúng một mẫu, và tra theo tên chứ không theo id.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- BỐN CỘT NỘI DUNG, KHÔNG PHẢI HAI
--
-- subject_vi · body_vi · subject_en · body_en. Hai cột tiếng Anh ĐỂ TRỐNG ở đợt
-- này — song ngữ là đợt 9. Dựng sẵn cột ngay bây giờ vì thêm cột vào một bảng
-- đã có dữ liệu thật là một migration nữa, một lần deploy nữa, và một lần nữa
-- phải nhớ ra rằng nó tồn tại. Ô trống thì FR-SN-06 đã có luật sẵn: thiếu bản
-- tiếng Anh thì hiện bản tiếng Việt.
--
-- `body_vi` là HTML PHẦN RUỘT, không phải cả trang thư. Khung bao (logo, chân
-- thư, màu nền) do EmailQueueModel dựng — nếu không thì mười mẫu là mười bản
-- sao của cùng một cái khung, và đổi logo là sửa mười chỗ.
--
-- `mo_ta` và `bien` là để cho NGƯỜI SỬA đọc, không phải cho máy: người mở màn
-- sửa mẫu cần biết mẫu này gửi lúc nào và được phép dùng những biến gì. Không
-- có hai cột đó thì họ phải đoán, và một biến gõ sai chỉ lộ ra ở thư đã gửi.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_templates` (
    `key`        VARCHAR(48)  NOT NULL,
    `nhan`       VARCHAR(120) NOT NULL,
    `mo_ta`      VARCHAR(255) NULL DEFAULT NULL,
    -- Danh sách biến dùng được, phân tách bằng dấu phẩy. Màn sửa mẫu in ra.
    `bien`       VARCHAR(500) NULL DEFAULT NULL,

    `subject_vi` VARCHAR(200) NOT NULL,
    `body_vi`    TEXT         NOT NULL,
    `subject_en` VARCHAR(200) NULL DEFAULT NULL,
    `body_en`    TEXT         NULL DEFAULT NULL,

    /* TẮT ĐƯỢC TỪNG MẪU. Cửa hàng có thể thấy thư "đơn đang chuẩn bị" là thừa
       mà vẫn muốn giữ bốn mốc còn lại. Không có cờ này thì lựa chọn duy nhất
       của họ là xoá mẫu — và xoá xong thì không dựng lại được câu chữ. */
    `bat`        TINYINT(1)   NOT NULL DEFAULT 1,

    `updated_by` CHAR(36)     NULL DEFAULT NULL,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`key`),
    KEY `idx_tpl_by` (`updated_by`),
    CONSTRAINT `fk_tpl_by` FOREIGN KEY (`updated_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 2. `email_queue` — HÀNG CHỜ VÀ SỔ KẾT QUẢ
--
-- Một dòng là MỘT LÁ THƯ gửi cho một người. Không gộp, không nhóm.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHÉP CẢ TIÊU ĐỀ LẪN NỘI DUNG VÀO ĐÂY, KHÔNG DỰNG LẠI LÚC GỬI
--
-- Nội dung được dựng NGAY LÚC SỰ KIỆN XẢY RA rồi cất vào hai cột `subject` và
-- `body`. Bộ quét chỉ việc cầm đi gửi.
--
-- Vì sao không dựng lúc gửi cho gọn: một lá thư "đơn VE-1234 đã xác nhận" nằm
-- trong hàng chờ ba ngày, rồi đơn ấy bị huỷ. Dựng lại lúc gửi là gửi đi một
-- câu nói về hiện tại dưới cái tên của một sự kiện quá khứ. Cùng lý lẽ đã chép
-- `product_name` và `unit_price` vào `order_items`.
--
-- Nó cũng là thứ làm nút "gửi lại" ở FR-EM-05 có nghĩa: gửi lại ĐÚNG lá thư
-- đó, không phải một lá thư mới trông hao hao.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- `lien_quan_loai` + `lien_quan_id` — KHOÁ NGOẠI MỀM, CỐ Ý
--
-- Trỏ tới đơn hàng, lịch hẹn hay mặt hàng tuỳ loại thư. Không có khoá ngoại
-- thật vì nó trỏ tới BA bảng khác nhau; MySQL không có kiểu tham chiếu đa hình.
--
-- Đổi lại: dòng có thể trỏ tới một bản ghi đã bị xoá. Chấp nhận được — cột này
-- chỉ để màn quản trị dựng một liên kết, và một liên kết chết thì kém hơn một
-- liên kết sống chứ không làm hỏng gì. Nội dung thư đã chép sẵn ở trên.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- `khoa_chong_trung` — MỘT SỰ KIỆN CHỈ SINH MỘT THƯ
--
-- Khoá duy nhất. Chuỗi do nơi gọi đặt, thường là "<loại>:<id đối tượng>".
--
-- Không có nó thì: nhân viên bấm nhầm "Đã xác nhận" rồi bấm lại là khách nhận
-- hai thư giống nhau; bộ quét nhắc lịch hẹn chạy hai lượt trong cùng một ngày
-- là hai thư nhắc. Cả hai đều đã xảy ra ở những hệ thống làm kiểu này.
--
-- NULL được, và MySQL cho nhiều dòng NULL trong khoá duy nhất — dùng cho những
-- thư cố ý gửi được nhiều lần.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_queue` (
    `id`             CHAR(36)     NOT NULL DEFAULT (UUID()),

    -- Mã sự kiện, khớp `email_templates`.`key`. KHÔNG khoá ngoại: xoá một mẫu
    -- thư không được xoá theo lịch sử đã gửi.
    `loai`           VARCHAR(48)  NOT NULL,

    `nguoi_nhan`     VARCHAR(190) NOT NULL,
    -- Chủ tài khoản, nếu thư gửi cho khách có tài khoản. NULL với khách vãng lai.
    `user_id`        CHAR(36)     NULL DEFAULT NULL,

    /* NGÔN NGỮ TẠI THỜI ĐIỂM PHÁT SINH SỰ KIỆN — FR-EM-07, FR-SN-19.
       Đợt 6 luôn ghi 'vi'. Cột có sẵn để đợt 9 không phải đụng lại bảng này,
       và để một lá thư nằm trong hàng chờ qua ngày đổi ngôn ngữ vẫn gửi đi
       bằng thứ tiếng khách đang dùng lúc đặt hàng. */
    `ngon_ngu`       VARCHAR(5)   NOT NULL DEFAULT 'vi',

    `lien_quan_loai` VARCHAR(24)  NULL DEFAULT NULL,
    `lien_quan_id`   CHAR(36)     NULL DEFAULT NULL,

    `subject`        VARCHAR(255) NOT NULL,
    `body`           MEDIUMTEXT   NOT NULL,

    /* 'cho' chờ gửi · 'xong' đã gửi · 'hong' thử đủ số lần vẫn không được
       · 'bo' người vận hành chủ động bỏ.

       'hong' KHÁC 'bo': cái đầu là hệ thống bó tay, cái sau là quyết định của
       con người. Gộp làm một thì màn quản trị không phân biệt được "cần xem
       lại cấu hình" với "việc này thôi không gửi nữa". */
    `trang_thai`     VARCHAR(12)  NOT NULL DEFAULT 'cho',
    `so_lan_thu`     SMALLINT     NOT NULL DEFAULT 0,
    `loi_gan_nhat`   VARCHAR(500) NULL DEFAULT NULL,

    /* Sớm nhất lúc nào được thử (lại). Dùng cho cả hai việc:
         · giãn cách giữa các lần thử sau khi hỏng
         · THƯ HẸN GIỜ — nhắc lịch hẹn trước 1 ngày, cảnh báo đơn sắp tự huỷ
           trước 2 giờ. Xếp hàng ngay lúc biết, đặt giờ gửi ở tương lai. */
    `gui_sau`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `gui_luc`        DATETIME     NULL DEFAULT NULL,

    `khoa_chong_trung` VARCHAR(120) NULL DEFAULT NULL,

    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email_chong_trung` (`khoa_chong_trung`),
    /* Chỉ mục của BỘ QUÉT: nó luôn hỏi đúng một câu — "thư nào đang chờ và đã
       tới giờ gửi chưa". Hai cột theo đúng thứ tự ấy. */
    KEY `idx_email_quet` (`trang_thai`, `gui_sau`),
    KEY `idx_email_loai` (`loai`),
    KEY `idx_email_user` (`user_id`),
    KEY `idx_email_lien_quan` (`lien_quan_loai`, `lien_quan_id`),
    /* Sắp xếp của MÀN QUẢN TRỊ, không phải của bộ quét.

       EmailQueueModel::danhSach() sắp `FIELD(trang_thai, …), created_at DESC`.
       FIELD() thì không chỉ mục nào giúp được, nhưng `created_at` thì có — và
       đây là bảng duy nhất trong đợt này chỉ có lớn lên, nên không có chỉ mục
       thì màn Hàng chờ thư là câu truy vấn xuống cấp đầu tiên: quét toàn bảng
       rồi filesort ở mỗi lần mở trang.

       Cột đầu là `trang_thai` để viên lọc (WHERE trang_thai = …) dùng chung
       được đúng chỉ mục này thay vì cần thêm một cái nữa. */
    KEY `idx_email_so` (`trang_thai`, `created_at`),

    -- SET NULL: khách xoá tài khoản thì sổ thư đã gửi vẫn là dữ liệu vận hành.
    CONSTRAINT `fk_email_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 3. MƯỜI LĂM MẪU THƯ MẶC ĐỊNH — chỉ bản tiếng Việt
--
-- INSERT IGNORE: chạy lại migration KHÔNG ghi đè câu chữ cửa hàng đã sửa. Đây
-- là điểm khác quan trọng so với REPLACE — mẫu thư là nội dung của người dùng
-- ngay khi họ chạm vào nó lần đầu.
--
-- `body_vi` là phần RUỘT. Khung bao do EmailQueueModel::dungKhung() lo.
--
-- Biến viết dạng {{ten}}. Biến không có trong dữ liệu thì thay bằng chuỗi rỗng
-- — xem EmailQueueModel::thay().
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `email_templates`
    (`key`, `nhan`, `mo_ta`, `bien`, `subject_vi`, `body_vi`) VALUES

-- ── Mốc đơn hàng — FR-EM-01 ────────────────────────────────────────────────
('don.tao', 'Đơn hàng đã được tạo',
 'Gửi ngay sau khi khách đặt hàng thành công.',
 '{{ten_khach}}, {{ma_don}}, {{tong_tien}}, {{link_don}}',
 'Vin Eyewear — đã nhận đơn {{ma_don}}',
 '<p>Chào {{ten_khach}},</p><p>Cửa hàng đã nhận đơn <strong>{{ma_don}}</strong> trị giá <strong>{{tong_tien}}</strong>. Chúng tôi sẽ liên hệ để xác nhận trong thời gian sớm nhất.</p><p><a href="{{link_don}}">Xem chi tiết đơn hàng</a></p>'),

('don.xac_nhan', 'Đơn hàng đã được xác nhận',
 'Gửi khi nhân viên chuyển đơn sang trạng thái Đã xác nhận.',
 '{{ten_khach}}, {{ma_don}}, {{link_don}}',
 'Đơn {{ma_don}} đã được xác nhận',
 '<p>Chào {{ten_khach}},</p><p>Đơn <strong>{{ma_don}}</strong> đã được xác nhận và đang chờ chuẩn bị hàng.</p><p><a href="{{link_don}}">Theo dõi đơn hàng</a></p>'),

('don.giao', 'Đơn hàng đang giao / sẵn sàng tại cửa hàng',
 'Gửi khi đơn sang trạng thái Đang giao. Câu chữ đổi theo hình thức nhận hàng.',
 '{{ten_khach}}, {{ma_don}}, {{nhan_trang_thai}}, {{link_don}}',
 'Đơn {{ma_don}} — {{nhan_trang_thai}}',
 '<p>Chào {{ten_khach}},</p><p>Đơn <strong>{{ma_don}}</strong> nay ở trạng thái <strong>{{nhan_trang_thai}}</strong>.</p><p><a href="{{link_don}}">Xem chi tiết</a></p>'),

('don.hoan_tat', 'Đơn hàng đã hoàn tất',
 'Gửi khi đơn sang trạng thái Hoàn tất.',
 '{{ten_khach}}, {{ma_don}}, {{link_don}}',
 'Cảm ơn bạn — đơn {{ma_don}} đã hoàn tất',
 '<p>Chào {{ten_khach}},</p><p>Đơn <strong>{{ma_don}}</strong> đã hoàn tất. Cảm ơn bạn đã chọn Vin Eyewear.</p><p>Kính có vấn đề gì trong quá trình sử dụng, bạn cứ liên hệ cửa hàng — bảo hành và cân chỉnh gọng là miễn phí.</p>'),

('don.huy', 'Đơn hàng đã huỷ',
 'Gửi khi đơn chuyển sang Đã huỷ, dù do khách, nhân viên hay hệ thống.',
 '{{ten_khach}}, {{ma_don}}, {{ly_do}}, {{link_don}}',
 'Đơn {{ma_don}} đã được huỷ',
 '<p>Chào {{ten_khach}},</p><p>Đơn <strong>{{ma_don}}</strong> đã được huỷ.</p><p>{{ly_do}}</p><p>Nếu đơn đã phát sinh thanh toán, cửa hàng sẽ liên hệ với bạn về việc hoàn tiền.</p>'),

-- ── Mốc tiền — FR-EM-02 ────────────────────────────────────────────────────
('tien.coc', 'Đã nhận tiền cọc',
 'Gửi khi hệ thống ghi nhận đã nhận tiền cọc của đơn.',
 '{{ten_khach}}, {{ma_don}}, {{so_tien}}, {{link_don}}',
 'Đã nhận tiền cọc đơn {{ma_don}}',
 '<p>Chào {{ten_khach}},</p><p>Cửa hàng đã nhận <strong>{{so_tien}}</strong> tiền cọc cho đơn <strong>{{ma_don}}</strong>.</p><p><a href="{{link_don}}">Xem đơn hàng</a></p>'),

('tien.du', 'Đã thanh toán đủ',
 'Gửi khi đơn được ghi nhận đã trả đủ.',
 '{{ten_khach}}, {{ma_don}}, {{so_tien}}, {{link_don}}',
 'Đã nhận thanh toán đơn {{ma_don}}',
 '<p>Chào {{ten_khach}},</p><p>Cửa hàng đã nhận đủ <strong>{{so_tien}}</strong> cho đơn <strong>{{ma_don}}</strong>.</p><p><a href="{{link_don}}">Xem đơn hàng</a></p>'),

('tien.hoan', 'Đã hoàn tiền cọc',
 'Gửi khi Quản trị viên bấm "Đã hoàn tiền" ở màn Hoàn tiền cọc.',
 '{{ten_khach}}, {{ma_don}}, {{so_tien}}, {{ngay_hoan}}',
 'Đã hoàn {{so_tien}} cho đơn {{ma_don}}',
 '<p>Chào {{ten_khach}},</p><p>Cửa hàng đã chuyển lại <strong>{{so_tien}}</strong> của đơn <strong>{{ma_don}}</strong> vào ngày {{ngay_hoan}}.</p><p>Nếu sau ba ngày làm việc bạn chưa thấy tiền về, vui lòng liên hệ cửa hàng.</p>'),

-- ── Lịch hẹn — FR-EM-03, FR-LH-08 ──────────────────────────────────────────
('lich.dat', 'Đã nhận lịch hẹn đo mắt',
 'Gửi ngay sau khi khách đặt lịch trên website.',
 '{{ten_khach}}, {{ma_lich}}, {{ngay_hen}}, {{co_so}}, {{link_lich}}',
 'Đã nhận lịch hẹn đo mắt ngày {{ngay_hen}}',
 '<p>Chào {{ten_khach}},</p><p>Cửa hàng đã nhận lịch hẹn <strong>{{ma_lich}}</strong> vào ngày <strong>{{ngay_hen}}</strong> tại {{co_so}}.</p><p>Chúng tôi sẽ liên hệ xác nhận với bạn.</p>'),

('lich.xac_nhan', 'Lịch hẹn đã được xác nhận',
 'Gửi khi nhân viên xác nhận lịch hẹn.',
 '{{ten_khach}}, {{ma_lich}}, {{ngay_hen}}, {{co_so}}',
 'Lịch hẹn ngày {{ngay_hen}} đã được xác nhận',
 '<p>Chào {{ten_khach}},</p><p>Lịch hẹn <strong>{{ma_lich}}</strong> ngày <strong>{{ngay_hen}}</strong> tại {{co_so}} đã được xác nhận. Hẹn gặp bạn.</p>'),

('lich.doi_ngay', 'Lịch hẹn đã đổi ngày',
 'Gửi khi lịch hẹn được dời sang ngày khác.',
 '{{ten_khach}}, {{ma_lich}}, {{ngay_hen}}, {{ngay_cu}}, {{co_so}}',
 'Lịch hẹn {{ma_lich}} đã đổi sang ngày {{ngay_hen}}',
 '<p>Chào {{ten_khach}},</p><p>Lịch hẹn <strong>{{ma_lich}}</strong> đã được dời từ ngày {{ngay_cu}} sang <strong>{{ngay_hen}}</strong> tại {{co_so}}.</p>'),

('lich.huy', 'Lịch hẹn đã huỷ',
 'Gửi khi lịch hẹn bị huỷ, dù do khách hay nhân viên.',
 '{{ten_khach}}, {{ma_lich}}, {{ngay_hen}}',
 'Lịch hẹn ngày {{ngay_hen}} đã được huỷ',
 '<p>Chào {{ten_khach}},</p><p>Lịch hẹn <strong>{{ma_lich}}</strong> ngày {{ngay_hen}} đã được huỷ.</p><p>Bạn có thể đặt lịch mới bất cứ lúc nào trên website.</p>'),

('lich.nhac', 'Nhắc lịch hẹn trước một ngày',
 'Bộ quét tự sinh cho lịch hẹn của ngày mai, ở trạng thái chờ hoặc đã xác nhận.',
 '{{ten_khach}}, {{ma_lich}}, {{ngay_hen}}, {{co_so}}',
 'Nhắc bạn: lịch đo mắt ngày mai {{ngay_hen}}',
 '<p>Chào {{ten_khach}},</p><p>Nhắc bạn lịch đo mắt <strong>{{ngay_hen}}</strong> tại {{co_so}}.</p><p>Bận đột xuất thì bạn báo lại giúp cửa hàng nhé — huỷ hoặc đổi ngày được ngay trong trang tài khoản.</p>'),

-- ── Hàng có lại — FR-EM-04, FR-SP-18 ───────────────────────────────────────
('kho.co_hang', 'Hàng đã có lại',
 'Gửi cho khách đã đăng ký chờ, khi kho online của mặt hàng từ 0 tăng lên.',
 '{{ten_khach}}, {{ten_san_pham}}, {{phuong_an}}, {{link_san_pham}}',
 '{{ten_san_pham}} đã có hàng trở lại',
 '<p>Chào {{ten_khach}},</p><p><strong>{{ten_san_pham}}</strong> {{phuong_an}} đã có hàng trở lại.</p><p><a href="{{link_san_pham}}">Xem sản phẩm</a></p><p>Số lượng có hạn — mẫu này đã có người chờ.</p>'),

-- ── Cảnh báo trước khi tự huỷ — FR-TT-11 ───────────────────────────────────
('don.sap_qua_han', 'Đơn chuyển khoản sắp quá hạn',
 'Bộ quét tự sinh, gửi trước thời điểm tự huỷ 2 giờ.',
 '{{ten_khach}}, {{ma_don}}, {{tong_tien}}, {{so_gio}}, {{link_don}}',
 'Đơn {{ma_don}} sẽ tự huỷ sau {{so_gio}} giờ nữa',
 '<p>Chào {{ten_khach}},</p><p>Đơn <strong>{{ma_don}}</strong> ({{tong_tien}}) đặt bằng chuyển khoản nhưng cửa hàng chưa nhận được thanh toán. Đơn sẽ tự huỷ sau <strong>{{so_gio}} giờ</strong> nữa và hàng được trả về kho.</p><p>Nếu bạn đã chuyển khoản rồi, cứ bỏ qua thư này — cửa hàng sẽ đối chiếu và xác nhận.</p><p><a href="{{link_don}}">Xem đơn hàng</a></p>');


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
--   SHOW TABLES LIKE 'email_queue';
--   SHOW TABLES LIKE 'email_templates';
--   SELECT COUNT(*) FROM email_templates;        -- 15
--   SELECT `key`, subject_vi FROM email_templates ORDER BY `key`;
--
-- Rồi đặt một đơn thử và kiểm hàng chờ:
--
--   SELECT loai, nguoi_nhan, trang_thai, LEFT(subject, 50)
--     FROM email_queue ORDER BY created_at DESC LIMIT 5;
--
-- Trên hosting hiện tại trạng thái sẽ là 'cho' và ĐỨNG YÊN ở đó — InfinityFree
-- không gửi được thư. Đó là đúng, không phải hỏng: xem khối đầu file.
-- ----------------------------------------------------------------------------
