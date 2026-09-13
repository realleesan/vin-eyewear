<?php

/**
 * WishlistController — trang /yeu-thich, danh sách đã lưu XEM ĐƯỢC KHI CHƯA
 * ĐĂNG NHẬP.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * VÌ SAO CÓ TRANG NÀY
 *
 * Từ 13/09/2026 khách vãng lai lưu được sản phẩm (xem app/services/Wishlist.php).
 * Nhưng danh sách vốn chỉ có một cửa: /tai-khoan?muc=da-luu — mà trang tài
 * khoản thì bắt đăng nhập. Nếu để nguyên thì khách bấm được trái tim, thấy số
 * trên huy hiệu nhảy lên, rồi bấm vào huy hiệu ấy và bị ném sang trang đăng
 * nhập. Lưu được mà không xem được là một nửa tính năng.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * MỘT KHỐI MARKUP, HAI CỬA — KHÔNG CHÉP LẠI LƯỚI
 *
 * Trang này KHÔNG dựng lưới riêng. Nó gọi lại đúng file
 * app/views/auth/account/da-luu.php (~250 dòng dựng 1:1 từ "Wishlist.dc.html")
 * và chỉ truyền vào $tabUrl khác. Chép lưới ra bản thứ hai thì sau vài lượt
 * sửa hai bên lệch nhau, và lúc đó cùng một danh sách hiện hai kiểu tuỳ khách
 * vào bằng cửa nào.
 *
 * Dữ liệu cũng lấy qua đúng một cửa (Wishlist), nên hai trang không thể bất
 * đồng về "đã lưu gì" hay "sắp theo thứ tự nào".
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * NGƯỜI ĐÃ ĐĂNG NHẬP VÀO ĐÂY THÌ SAO?
 *
 * Vẫn xem được, và thấy danh sách trong TÀI KHOẢN của họ — Wishlist tự chọn
 * kho theo danh tính. Không chuyển hướng sang /tai-khoan: một đường dẫn khách
 * đã lưu hay gửi cho người khác thì phải mở ra đúng thứ nó hứa, chứ không nhảy
 * đi nơi khác tuỳ theo lúc ấy ai đang đăng nhập.
 */

class WishlistController extends BaseController
{
    public function index(): void
    {
        $saved = Wishlist::danhSach();

        $this->renderView('wishlist/index', [
            'pageTitle' => 'Yêu thích — Vin Eyewear',
            'metaDesc'  => 'Những mẫu kính bạn đã lưu lại tại Vin Eyewear.',

            /* Trang danh sách cá nhân KHÔNG cho máy tìm kiếm lập chỉ mục: nội
               dung khác nhau với từng người và không có giá trị nào cho người
               tìm kiếm. Cùng nếp với trang tài khoản. */
            'noindex'   => true,

            'saved'     => $saved,
            'luuDuoc'   => Wishlist::available(),

            /* Biến thể của CẢ LƯỚI trong một câu, không hỏi từng thẻ — cùng
               phép với tab "Đã lưu" và trang danh mục. */
            'variants'  => $saved === []
                ? []
                : VariantModel::forProducts(array_column($saved, 'id')),

            // ?anh=nguoi-mau — trạng thái nằm trên địa chỉ, xem da-luu.php.
            'anhPhu'    => ($_GET['anh'] ?? '') === 'nguoi-mau',
        ]);
    }
}
