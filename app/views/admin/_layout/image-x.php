<?php

/**
 * admin/_layout/image-x.php — nút "×" xoá một ảnh đã tải lên.
 *
 * Dùng ở cả ba màn có ảnh: bộ sưu tập, sự kiện, sản phẩm.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG PHẢI NÚT JAVASCRIPT — LÀ MỘT Ô TICK ĐƯỢC VẼ THÀNH DẤU ×
 *
 * CLAUDE.md quy định tắt JS thì mọi luồng vẫn phải chạy bằng form POST. Một nút
 * <button> gọi fetch() để xoá ảnh sẽ chết ngay khi JS tắt, và tệ hơn: nó xoá
 * NGAY, khác hẳn phần còn lại của form vốn chỉ ghi khi bấm "Lưu thay đổi".
 * Mở form ra, lỡ tay bấm ×, rồi đóng tab — với nút JS thì ảnh đã mất.
 *
 * Nên đây vẫn là <input type="checkbox"> như trước, chỉ khác cách vẽ: ô tick
 * ẩn khỏi mắt (KHÔNG khỏi bàn phím), còn <label> nổi lên góc ảnh dưới dạng
 * dấu ×. Bấm × = tick ô = "xoá ảnh này khi lưu".
 *
 * Ô tick đặt TRƯỚC ảnh trong HTML, cố ý: nhờ vậy CSS dùng được bộ chọn anh-em
 * (`~`) để làm mờ ảnh và đổi × thành ↺ khi đã đánh dấu — không cần :has(),
 * không cần một dòng JS nào.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HAI CHIỀU NGƯỢC NHAU, GIỮ NGUYÊN DÂY GỬI LÊN
 *
 * Sản phẩm gửi `image_keep[]` — TICK NGHĨA LÀ GIỮ, bỏ tick mới là xoá.
 * Sự kiện và bộ sưu tập gửi `cover_remove` — tick nghĩa là XOÁ.
 *
 * Đã cân nhắc thống nhất về một chiều rồi bỏ: đổi ý nghĩa của `image_keep[]`
 * bắt phải sửa cả đoạn so sánh danh sách ảnh trong ProductAdminController, mà
 * sai một dấu ở đó là xoá nhầm ảnh thật của cửa hàng. Đổi cách VẼ thì không
 * rủi ro gì. `x_keep` cho biết đang ở chiều nào để CSS bật đúng trạng thái.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Nhận qua partial():
 *   $x_id      — id duy nhất trong trang (ảnh sản phẩm có nhiều, phải kèm chỉ số)
 *   $x_name    — tên trường gửi lên
 *   $x_value   — giá trị gửi lên
 *   $x_checked — tick sẵn hay không
 *   $x_keep    — true: tick = GIỮ (sản phẩm) · false: tick = XOÁ (ảnh bìa)
 *   $x_label   — câu cho trình đọc màn hình, ví dụ "Xoá ảnh bìa khi lưu"
 */

$x_keep = $x_keep ?? false;
?>

<input class="aimgx__box<?= $x_keep ? ' aimgx__box--keep' : '' ?>"
       type="checkbox"
       id="<?= e($x_id) ?>"
       name="<?= e($x_name) ?>"
       value="<?= e($x_value) ?>"
       <?= !empty($x_checked) ? 'checked' : '' ?>>
