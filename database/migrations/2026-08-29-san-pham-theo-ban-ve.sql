-- 2026-08-29 — form thêm/sửa sản phẩm dựng lại theo "Quản lý sản phẩm.dc.html"
--
-- Bản vẽ chia form thành SÁU TAB, và mỗi tab hỏi những thứ cột `products` hiện
-- chưa có chỗ chứa. File này mở đủ chỗ cho chúng: 20 cột trên `products`, 4 cột
-- trên `product_variants`.
--
-- KHÔNG XOÁ CỘT NÀO. Form mới bỏ hỏi 21 trường cũ (thông số gọng chi tiết, phụ
-- kiện, bảo hành, đổi trả, chứng nhận, giá kèm tròng…), nhưng dữ liệu đang nằm
-- trong chúng vẫn được trang bán hàng đọc và in ra. Bỏ HỎI khác bỏ CHỨA — xoá
-- cột là xoá luôn nội dung của những mặt hàng đã nhập đủ, và không lùi được.
--
-- Không có ADD COLUMN IF NOT EXISTS trên MySQL 8.4, nên file này chạy một lần.
-- Sổ `schema_migrations` của migrate.sh là thứ chặn chạy lại (mốc:
-- products.publish_status).

ALTER TABLE `products`
    -- ── Tab "Thông tin" ────────────────────────────────────────────────────
    --
    -- BA TRẠNG THÁI XUẤT BẢN, tách khỏi `status`.
    --
    -- `status` sẵn có là in_stock/out_of_stock và được SUY RA từ tồn kho, cố ý
    -- không cho nhập tay (xem ProductAdminController::save) — hai con số ấy
    -- lệch nhau là nguồn gốc của "mua được hàng đã hết". Còn Hiện/Ẩn/Nháp là
    -- quyết định của người biên tập, không liên quan gì tới kho.
    --
    -- `is_visible` GIỮ NGUYÊN và được đồng bộ theo cột này (visible → 1, hidden
    -- và draft → 0). Cả trang bán hàng lọc theo `is_visible`, sửa chỗ đó là sửa
    -- hàng chục truy vấn; để nguyên thì không trang nào phải biết cột mới tồn
    -- tại.
    ADD COLUMN `publish_status`    VARCHAR(16)  NOT NULL DEFAULT 'visible' AFTER `is_visible`,
    ADD COLUMN `tags`              VARCHAR(255) NULL AFTER `collection`,
    -- Mô tả ngắn (2 dòng, cho thẻ sản phẩm) tách khỏi `description` (đoạn dài,
    -- cho trang chi tiết): hai chỗ cần độ dài rất khác nhau, và cắt cụt đoạn
    -- dài để làm đoạn ngắn thì luôn cắt giữa câu.
    ADD COLUMN `description_short` VARCHAR(500) NULL AFTER `description`,

    -- ── Tab "Giá & kho" ───────────────────────────────────────────────────
    --
    -- Giá vốn CHỈ ĐỂ TÍNH LÃI, không bao giờ in ra trang bán hàng. Nó nằm cùng
    -- bảng với giá bán nên mọi câu `SELECT *` đều kéo nó theo — chỗ nào in dữ
    -- liệu sản phẩm ra ngoài phải tự loại cột này.
    ADD COLUMN `cost_price`        BIGINT       NULL AFTER `compare_at_price`,
    -- Khuyến mãi CÓ HẠN: `sale_price` một mình thì không ai biết bao giờ tắt,
    -- và cửa hàng phải nhớ vào sửa tay đúng ngày. Hai cột ngày cho phép đặt
    -- trước rồi quên đi.
    ADD COLUMN `sale_price`        BIGINT       NULL AFTER `cost_price`,
    ADD COLUMN `sale_from`         DATE         NULL AFTER `sale_price`,
    ADD COLUMN `sale_to`           DATE         NULL AFTER `sale_from`,
    -- Ngưỡng "sắp hết" riêng cho từng mặt hàng: gọng bán chạy còn 5 cái là
    -- đáng lo, gọng bán chậm còn 5 cái là bình thường. NULL = dùng ngưỡng
    -- chung của trang Tồn kho.
    ADD COLUMN `low_stock_at`      INT          NULL AFTER `stock_quantity`,
    ADD COLUMN `allow_backorder`   TINYINT(1)   NOT NULL DEFAULT 0 AFTER `low_stock_at`,

    -- ── Tab "Thuộc tính kính" ─────────────────────────────────────────────
    --
    -- Kiểu viền gọng (full/half/rimless). Trước đây lẫn trong `frame_shape`
    -- cùng với hình dáng, nên không lọc riêng được: "gọng vuông không viền" là
    -- hai thuộc tính chứ không phải một.
    ADD COLUMN `rim_type`          VARCHAR(20)  NULL AFTER `frame_shape`,
    -- `color` sẵn có là MÀU GỌNG. Màu tròng là thứ khác hẳn và kính râm luôn
    -- có cả hai.
    ADD COLUMN `lens_color`        VARCHAR(120) NULL AFTER `color`,
    -- S/M/L do người nhập CHỌN, không suy ra từ số đo.
    --
    -- config/eyewear.php có bảng quy đổi theo tổng bề rộng gọng, nhưng nó cần
    -- `frame_width_mm` — một cột form mới không còn hỏi. Và bảng quy đổi cố ý
    -- bỏ trống khi số đo nằm ngoài dải, nên vẫn phải có chỗ cho người nhập nói
    -- thẳng.
    ADD COLUMN `size_class`        CHAR(1)      NULL AFTER `temple_mm`,
    -- UV400 trước nay nằm trong CSV `lens_coatings`. Bản vẽ đưa nó thành một ô
    -- tick riêng cạnh "phân cực" vì đó là hai thứ khách hỏi nhiều nhất về kính
    -- râm. Cột riêng để lọc được bằng SQL.
    ADD COLUMN `is_uv400`          TINYINT(1)   NOT NULL DEFAULT 0 AFTER `is_polarized`,

    -- ── Tab "Hình ảnh" ────────────────────────────────────────────────────
    --
    -- Alt text để RIÊNG một cột, ánh xạ đường-dẫn → chữ.
    --
    -- Cách hiển nhiên là đổi `images` từ ["a.jpg"] sang [{"url":…,"alt":…}].
    -- Nhưng cột ấy được đọc ở rất nhiều chỗ của trang bán hàng, chỗ nào cũng
    -- coi mỗi phần tử là một chuỗi (ProductAdminController::images còn lọc
    -- 'is_string'). Đổi hình dạng là đổi tất cả những chỗ đó cùng lúc, và chỗ
    -- nào sót thì in ra "Array" thay vì ảnh.
    ADD COLUMN `image_alts`        JSON         NULL AFTER `images`,
    ADD COLUMN `video_url`         VARCHAR(500) NULL AFTER `image_alts`,

    -- ── Tab "Đơn kính" ────────────────────────────────────────────────────
    --
    -- KHÁC `rx_ready`, đừng gộp. `rx_ready` = "gọng này lắp được tròng cận"
    -- (thuộc tính vật lý của gọng). `rx_order_enabled` = "cửa hàng có nhận đặt
    -- kèm tròng cho mẫu này không" (quyết định kinh doanh). Một gọng lắp được
    -- nhưng đang hết tròng phù hợp thì cột đầu là 1, cột sau là 0.
    ADD COLUMN `rx_order_enabled`  TINYINT(1)   NOT NULL DEFAULT 0 AFTER `rx_ready`,
    -- CSV các loại tròng nhận đặt: don-trong, da-trong, doi-mau, anh-sang-xanh.
    ADD COLUMN `lens_types`        VARCHAR(120) NULL AFTER `rx_order_enabled`,
    -- CSV chiết suất hỗ trợ: 1.56,1.61,1.67,1.74.
    --
    -- KHÁC `lens_index` (DECIMAL, một giá trị) — cột cũ tả chiết suất của tròng
    -- ĐI KÈM sẵn trong hộp, cột mới liệt kê những chiết suất ĐẶT THÊM được.
    ADD COLUMN `lens_indexes`      VARCHAR(60)  NULL AFTER `lens_index`,
    -- Để chuỗi chứ không DECIMAL: người nhập gõ "-8.00" và dấu âm là phần
    -- không được mất. Số đo mắt luôn viết kèm dấu (xem chú thích trong
    -- _tab-don-thuoc.php), nên giữ nguyên văn thứ họ gõ.
    ADD COLUMN `sph_max`           VARCHAR(10)  NULL AFTER `lens_indexes`,
    ADD COLUMN `cyl_max`           VARCHAR(10)  NULL AFTER `sph_max`;

-- ── Tab "Biến thể" ────────────────────────────────────────────────────────
--
-- Bản vẽ cho mỗi biến thể một dòng: màu · size · SKU riêng · giá riêng · tồn ·
-- ảnh. Bảng hiện có `label` (một chuỗi gộp), `price_delta` (chênh lệch) và
-- `image`, thiếu ba cột đầu.
--
-- `label` GIỮ NGUYÊN và vẫn NOT NULL: nó là khoá UNIQUE cùng product_id, và
-- trang /quan-tri/bien-the lẫn ngăn kéo thông số ở trang bán hàng đều in nó.
-- Form mới tự ghép label từ màu và size ("Đen nhám · M") thay vì bắt gõ lại.
--
-- `price` là giá TUYỆT ĐỐI, khác `price_delta` là CHÊNH LỆCH. NULL = không đặt
-- giá riêng, vẫn tính theo products.price + price_delta như cũ. Có giá trị thì
-- nó thắng — xem VariantModel::priceOf.
ALTER TABLE `product_variants`
    ADD COLUMN `color` VARCHAR(60) NULL AFTER `label`,
    ADD COLUMN `size`  CHAR(1)     NULL AFTER `color`,
    ADD COLUMN `sku`   VARCHAR(64) NULL AFTER `size`,
    ADD COLUMN `price` BIGINT      NULL AFTER `price_delta`;
