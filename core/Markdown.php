<?php

/**
 * core/Markdown.php — bộ chuyển Markdown RÚT GỌN sang HTML.
 *
 * Dùng cho thân bài viết sự kiện/khuyến mãi ("Vin Eyewear Article.dc.html" vẽ
 * tiêu đề phụ, danh sách đánh số và gạch đầu dòng — văn bản thuần không mang
 * được những thứ đó).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO TỰ VIẾT THAY VÌ NHẬN HTML CỦA NGƯỜI DÙNG
 *
 * Cách thường thấy là cho nhân viên dán HTML rồi lọc lại bằng một bộ "sanitizer"
 * tự viết. Bộ lọc HTML tự viết là một trong những thứ dễ sót lỗi XSS nhất: chỉ
 * cần quên một thuộc tính (`onerror`, `href="javascript:"`), một cách viết thẻ
 * lạ, hay một chuỗi mã hoá là lọt.
 *
 * Ở đây làm ngược lại — DANH SÁCH CHO PHÉP thay vì danh sách cấm:
 *
 *   1. Mọi ký tự của người dùng đi qua e() (htmlspecialchars) TRƯỚC.
 *      Sau bước này, chuỗi không còn chứa dấu `<` hay `>` nào của họ.
 *   2. CHỈ mã của file này mới chèn thẻ vào, và nó chỉ chèn đúng tám thẻ:
 *      h2 · h3 · p · ul · ol · li · strong · blockquote.
 *
 * Không có đường nào để một thẻ khác lọt ra ngoài, vì không có bước nào "giữ
 * lại thẻ của người dùng" cả. Đó là lý do file này không cần bộ lọc.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * CÚ PHÁP ĐƯỢC HỖ TRỢ
 *
 *   ## Tiêu đề            -> <h2>
 *   ### Tiêu đề nhỏ       -> <h3>
 *   - mục                 -> <ul><li>
 *   1. bước               -> <ol><li>   (số nào cũng được, thứ tự do trình duyệt)
 *   > lời nhắc            -> <blockquote>  (hộp mẹo trong bản thiết kế)
 *   **đậm**               -> <strong>
 *   dòng trống            -> ngắt đoạn
 *
 * Bài viết cũ chỉ có văn bản thuần vẫn chạy đúng: không có ký hiệu nào thì mỗi
 * đoạn thành một <p>, y như cách hiển thị trước đây.
 */

final class Markdown
{
    /**
     * Chuyển văn bản Markdown rút gọn thành HTML đã an toàn để in thẳng.
     */
    public static function render(?string $text): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        /*
         * Vá byte hỏng TRƯỚC khi so khớp.
         *
         * Mọi mẫu dưới đây mang cờ /u, mà preg_match với /u trả về FALSE (không
         * phải 0) khi chuỗi không phải UTF-8 hợp lệ. Hậu quả rất khó lần ra:
         * tiêu đề và danh sách lặng lẽ thành đoạn văn thường, in nguyên dấu
         * "##" ra màn hình, mà không có lỗi nào được ghi lại.
         *
         * Cột `content` là utf8mb4 nên chuyện này hiếm, nhưng một lần dán bị
         * cắt ngang ký tự là đủ. substr_chr thay byte hỏng bằng ký tự thay thế
         * để phần còn lại của bài vẫn định dạng đúng.
         */
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        /*
         * Tách dòng. CỜ /u LÀ BẮT BUỘC, không phải cho đẹp.
         *
         * Không có nó, PCRE chạy ở chế độ BYTE và \R khớp cả byte 0x85 (NEL).
         * Mà 0x85 là byte thứ ba của khá nhiều chữ tiếng Việt — "ễ" (U+1EC5)
         * là e1 bb 85. Hậu quả: chuỗi bị cắt NGAY GIỮA một chữ cái, nửa sau
         * thành một "dòng" riêng, và nửa đầu trở thành UTF-8 hỏng nên mọi mẫu
         * /u bên dưới trả về false — tiêu đề và danh sách lặng lẽ biến thành
         * đoạn văn thường.
         *
         * Bắt được vì "1. Đo khúc xạ miễn phí" mãi không thành <li>.
         */
        $lines = preg_split('/\R/u', $text) ?: [];

        $html    = '';
        $list    = null;   // 'ul' | 'ol' | null — danh sách đang mở
        $para    = [];     // các dòng của đoạn văn đang gom

        /** Đóng đoạn văn đang gom (nếu có). */
        $flushPara = static function () use (&$para, &$html): void {
            if ($para !== []) {
                $html .= '<p>' . self::inline(implode(' ', $para)) . '</p>';
                $para = [];
            }
        };

        /** Đóng danh sách đang mở (nếu có). */
        $flushList = static function () use (&$list, &$html): void {
            if ($list !== null) {
                $html .= '</' . $list . '>';
                $list = null;
            }
        };

        foreach ($lines as $raw) {
            $line = trim($raw);

            // Dòng trống = kết thúc đoạn và kết thúc danh sách
            if ($line === '') {
                $flushPara();
                $flushList();
                continue;
            }

            // Nhận diện ký hiệu trên dòng GỐC, trước khi escape: sau e() thì
            // '>' đã thành '&gt;' và không còn khớp được nữa.
            if (preg_match('/^(#{2,3})\s+(.*)$/u', $line, $m)) {
                $flushPara();
                $flushList();
                $tag = strlen($m[1]) === 2 ? 'h2' : 'h3';
                $html .= "<{$tag}>" . self::inline($m[2]) . "</{$tag}>";
                continue;
            }

            if (preg_match('/^>\s?(.*)$/u', $line, $m)) {
                $flushPara();
                $flushList();
                $html .= '<blockquote>' . self::inline($m[1]) . '</blockquote>';
                continue;
            }

            if (preg_match('/^[-*]\s+(.*)$/u', $line, $m)) {
                $flushPara();

                if ($list !== 'ul') {
                    $flushList();
                    $html .= '<ul>';
                    $list = 'ul';
                }

                $html .= '<li>' . self::inline($m[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\d+[.)]\s+(.*)$/u', $line, $m)) {
                $flushPara();

                if ($list !== 'ol') {
                    $flushList();
                    $html .= '<ol>';
                    $list = 'ol';
                }

                $html .= '<li>' . self::inline($m[1]) . '</li>';
                continue;
            }

            // Dòng thường: gom vào đoạn đang mở. Danh sách bị ngắt bởi một dòng
            // thường — nếu không, một đoạn văn viết ngay dưới danh sách sẽ bị
            // nuốt vào trong <ul>.
            $flushList();
            $para[] = $line;
        }

        $flushPara();
        $flushList();

        return $html;
    }

    /**
     * Bài này có lời nhắc (blockquote) riêng không?
     *
     * Trang chi tiết hiện hộp mẹo mặc định (số hotline) khi bài KHÔNG tự viết
     * lời nhắc nào — hỏi trước để không in hai hộp chồng nhau.
     */
    public static function hasQuote(?string $text): bool
    {
        return (bool) preg_match('/^>\s?\S/mu', trim((string) $text));
    }

    /**
     * Định dạng trong một dòng.
     *
     * ESCAPE TRƯỚC, chèn thẻ SAU — thứ tự này là toàn bộ lý do file này an
     * toàn. Đảo lại thì e() sẽ escape chính những thẻ ta vừa chèn và in ra
     * "&lt;strong&gt;".
     */
    private static function inline(string $text): string
    {
        $safe = e($text);

        // **đậm** -> <strong>. Không tham lam (.+?) để hai cặp trên cùng một
        // dòng không dính vào nhau thành một khối dài.
        return preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $safe) ?? $safe;
    }
}
