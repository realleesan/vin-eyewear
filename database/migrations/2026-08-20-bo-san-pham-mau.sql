-- ============================================================================
-- NÂNG CẤP 2026-08-20
-- Bỏ 5 sản phẩm mẫu khỏi cơ sở dữ liệu — catalog nhập hoàn toàn từ trang quản trị
--
-- ─────────────────────────────────────────────────────────────────────────────
-- FILE NÀY ĐỔI DỮ LIỆU, KHÔNG ĐỔI CẤU TRÚC
--
-- Không có bảng/cột/khoá nào mới, nên trong database/migrate.sh nó khai cột mốc
-- kiểu 'data': sổ ghi `schema_migrations` là thứ duy nhất chặn chạy lại. Không
-- sao cả — câu lệnh dưới đây chạy bao nhiêu lần cũng ra cùng một kết quả (lần
-- thứ hai xoá 0 dòng).
--
-- ─────────────────────────────────────────────────────────────────────────────
-- XOÁ HẲN, KHÔNG PHẢI ẨN ĐI
--
-- Ẩn (is_visible = 0) là cách xử lý đúng cho HÀNG THẬT đã ngừng bán: đơn cũ còn
-- tra ngược được về trang sản phẩm. Năm dòng này thì khác — chúng chưa bao giờ
-- là hàng của cửa hàng, chỉ là dữ liệu mẫu đi kèm bản cài. Giữ lại một danh
-- sách ẩn gồm toàn hàng không có thật thì mỗi lần mở trang quản trị lại phải
-- đọc lướt qua chúng.
--
-- NHỮNG GÌ ĐI THEO (khoá ngoại tự lo, không phải xoá tay):
--
--   product_variants  ON DELETE CASCADE  -> biến thể của chúng biến mất
--   reviews           ON DELETE CASCADE  -> đánh giá của chúng biến mất
--   favorites         ON DELETE CASCADE  -> khách nào lỡ thích thì mất mục đó
--   order_items       ON DELETE SET NULL -> ĐƠN HÀNG CŨ KHÔNG MẤT
--
-- Dòng cuối là chỗ đáng nói: order_items đã chụp lại tên, giá, số lượng ngay
-- lúc đặt (cố ý, xem ghi chú ở bảng đó trong schema.sql), nên hoá đơn cũ vẫn in
-- ra đủ chữ và đủ tiền. Thứ mất đi chỉ là đường dẫn bấm từ dòng hàng sang trang
-- sản phẩm — mà trang đó nay cũng không còn.
--
-- Giỏ hàng nằm trong session chứ không phải trong DB: khách nào đang để một
-- món mẫu trong giỏ thì CartController tự bỏ dòng không tra được sản phẩm.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- LỌC THEO slug, KHÔNG PHẢI THEO sku HAY name
--
-- slug là khoá UNIQUE và là thứ seed cũ ghi cứng; tên và SKU thì admin sửa
-- được, lọc theo chúng có thể trượt (đã đổi tên) hoặc quét nhầm (một mặt hàng
-- thật lỡ trùng SKU).
-- ============================================================================

DELETE FROM `products`
 WHERE `slug` IN (
    'gong-kinh-titan-vin-t01',
    'gong-kinh-acetate-vin-a02',
    'kinh-mat-polarized-vin-s03',
    'kinh-mat-mat-meo-vin-s04',
    'trong-kinh-chong-anh-sang-xanh'
 );
