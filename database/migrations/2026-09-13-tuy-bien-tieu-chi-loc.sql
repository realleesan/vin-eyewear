-- ============================================================================
-- TUỲ BIẾN TIÊU CHÍ LỌC TỪ KHU QUẢN TRỊ — 2026-09-13
--
-- Cửa hàng muốn THÊM · SỬA · XOÁ tiêu chí trong bộ lọc ngay tại khu quản trị,
-- không phải nhờ người sửa mã.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- BẢNG NÀY LÀ BẢNG ĐÈ, KHÔNG PHẢI DANH SÁCH CHỦ — VÀ ĐÓ LÀ ĐIỂM QUAN TRỌNG NHẤT
--
-- Tiêu chí lọc của nhóm Kiểu dáng · Chất liệu · Giới tính · Màu gọng KHÔNG do
-- ai khai ra: chúng được RÚT RA từ chữ mà người nhập hàng gõ vào ô dáng gọng,
-- chất liệu, và màu của từng biến thể. Nhập một gọng "Pantos" là bộ lọc tự có
-- thêm mục "Pantos", không cần khai trước ở đâu cả.
--
-- Nên bảng này KHÔNG liệt kê "có những tiêu chí nào". Nó chỉ nói: với tiêu chí
-- ĐÃ CÓ ấy thì hiện tên gì, gộp vào đâu, có ẩn không, xếp thứ mấy.
--
-- Khác biệt ấy quyết định chuyện gì xảy ra khi THIẾU một dòng:
--
--     bảng danh sách chủ  thiếu dòng -> sản phẩm mang khoá đó BIẾN MẤT khỏi
--                         bộ lọc, âm thầm, không báo gì.
--     bảng đè (bảng này)  thiếu dòng -> mọi thứ chạy đúng y như trước khi có
--                         bảng, chỉ là tiêu chí đó chưa được đặt tên đẹp.
--
-- Vì vậy bảng rỗng = site chạy nguyên như cũ. Xoá cả bảng đi cũng vậy. Đây là
-- tính chất phải giữ; đừng biến nó thành danh sách chủ.
--
-- (Nhóm TRÒNG KÍNH thì ngược lại — bốn nhóm ấy DO cửa hàng khai ở bảng
--  `lens_options`, và ở đó bảng chủ là đúng: sản phẩm tick vào danh sách có
--  sẵn. Hai bảng, hai việc khác nhau, cố ý không gộp.)
--
-- ─────────────────────────────────────────────────────────────────────────────
-- BỐN VIỆC BẢNG NÀY CHO PHÉP
--
--   SỬA    `label`      đổi tên hiện ra. Cửa hàng gõ "Lục giác thời thượng"
--                       mà bộ lọc hiện "Hình học / Oversized" thì sửa ở đây.
--   XOÁ    `is_visible` = 0. Tiêu chí thôi xuất hiện như một lựa chọn, nhưng
--                       hàng mang nó KHÔNG biến mất và địa chỉ ?shape[]=… cũ
--                       vẫn lọc được. Xoá khỏi giao diện, không xoá dữ liệu —
--                       cùng luật với mục ẩn của `lens_options`.
--   GỘP    `merge_into` trỏ sang khoá khác. Hai cách viết cùng một thứ
--                       ("Pantos" và "Pantos tròn cổ điển") nhập về một mục.
--   THÊM   `synonyms`   danh sách slug, cách nhau bằng dấu phẩy. Chữ người
--                       nhập gõ mà khớp một slug trong đây thì quy về khoá
--                       này. Đây mới là cách "thêm tiêu chí" có tác dụng thật:
--                       thêm một dòng trống không khớp món nào là thêm một
--                       mục đếm 0, mờ tịt, không ai bấm được.
--
-- ⚠ `option_key` LÀ THỨ ĐI VÀO URL (?shape[]=cat-eye). Đổi nó là làm hỏng mọi
--   liên kết khách đã lưu và mọi liên kết trong mega menu. Màn quản trị vì thế
--   chỉ cho sửa nhãn; muốn đổi khoá thì xoá dòng cũ rồi thêm dòng mới.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `filter_overrides` (
    `id`         CHAR(36)     NOT NULL DEFAULT (UUID()),

    -- 'shape' · 'material' · 'gender' · 'color' · 'eco' · 'brand' · 'collab'
    -- Đúng tên nhóm trong ProductFacets::GROUPS — tầng mã chặn nhóm lạ, CSDL
    -- không tự chặn được. Cùng đánh đổi đã ghi ở migration `lens_options`.
    `group_key`  VARCHAR(32)  NOT NULL,

    -- Khoá chuẩn mà ProductTaxonomy rút ra được, ví dụ 'cat-eye'.
    `option_key` VARCHAR(64)  NOT NULL,

    -- NULL = giữ nguyên nhãn máy tự dựng. Chuỗi rỗng cũng coi như NULL — xem
    -- FilterOverrideModel::forGroup().
    `label`      VARCHAR(120) NULL,

    -- Gộp mục này vào mục khác. NULL = đứng riêng.
    `merge_into` VARCHAR(64)  NULL,

    -- Slug đồng nghĩa, cách nhau bằng dấu phẩy: 'pantos,pantos-tron'.
    `synonyms`   VARCHAR(500) NULL,

    -- 0 = không hiện như một lựa chọn nữa (vẫn lọc được qua URL).
    `is_visible` TINYINT(1)   NOT NULL DEFAULT 1,

    -- Cách nhau 10 để chèn vào giữa không phải đánh số lại. 0 = chưa xếp tay,
    -- để bộ lọc tự xếp theo số lượng hàng như trước.
    `sort_order` SMALLINT     NOT NULL DEFAULT 0,

    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    -- Duy nhất TRONG một nhóm: khoá 'den' của nhóm màu và 'den' của một nhóm
    -- khác là hai chuyện.
    UNIQUE KEY `uniq_filter_overrides_key` (`group_key`, `option_key`),
    KEY `idx_filter_overrides_sort` (`group_key`, `sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KHÔNG seed dòng nào. Bảng rỗng nghĩa là site chạy y như trước — xem khối
-- "BẢNG NÀY LÀ BẢNG ĐÈ" ở đầu file. Cửa hàng tự thêm dòng khi muốn đổi.
