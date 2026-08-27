<?php

/**
 * SiteTextModel — mấy câu chữ trên trang mà cửa hàng tự sửa được.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỌI NƠI ĐỌC ĐỀU PHẢI TRUYỀN CÂU MẶC ĐỊNH
 *
 * get() bắt buộc có tham số thứ hai, không có bản một tham số. Đó là điều làm
 * cho cả cơ chế này an toàn: bảng chưa tồn tại (máy chưa chạy migration), dòng
 * bị xoá, hay khoá gõ sai — cả ba đều cho ra đúng câu chữ mà trang vẫn đang
 * hiện hôm nay, chứ không để lại một khoảng trắng không ai giải thích được.
 *
 * Hệ quả cố ý: câu mặc định nằm trong MÃ và là nguồn chân lý khi CSDL im lặng.
 * Đừng thay nó bằng chuỗi rỗng cho gọn — chuỗi rỗng là cách một tiêu đề biến
 * mất khỏi trang mà không ai biết vì sao.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHOÁ KHAI THÀNH HẰNG, KHÔNG GÕ TAY
 *
 * Khoá là chuỗi tự do trong CSDL nên không có gì bắt nó phải đúng chính tả.
 * Bù lại ở tầng mã: mỗi khoá đang dùng là một hằng ở đây, và nơi gọi tham
 * chiếu hằng. Gõ sai tên hằng thì PHP báo ngay; gõ sai chuỗi thì trang chỉ
 * lặng lẽ hiện câu mặc định mãi mãi.
 */

class SiteTextModel extends BaseModel
{
    protected static string $table = 'site_texts';

    /** Tiêu đề <h1> ở đầu trang /bo-suu-tap. */
    public const BST_TIEU_DE = 'bo-suu-tap.tieu_de';

    /** Đoạn dẫn bên phải tiêu đề ấy. */
    public const BST_DOAN_DAN = 'bo-suu-tap.doan_dan';

    /**
     * Nhớ CẢ BẢNG cho một lượt request.
     *
     * Trang /bo-suu-tap đọc hai khoá, và một trang khác sau này sẽ đọc thêm
     * vài khoá nữa. Đọc từng khoá một là mỗi câu chữ một lượt truy vấn; bảng
     * này có vài chục dòng ngắn nên kéo hết về một lần rẻ hơn hẳn.
     *
     * null nghĩa là CHƯA đọc lần nào; mảng rỗng nghĩa là đã đọc và bảng trống
     * (hoặc chưa tồn tại). Phân biệt hai thứ đó để bảng trống không bị hỏi lại
     * ở mỗi lần gọi get().
     */
    private static ?array $nho = null;

    /** @return array<string,string> */
    private static function tatCa(): array
    {
        if (self::$nho !== null) {
            return self::$nho;
        }

        /*
         * Bảng ra đời cùng migration 2026-08-27-noi-dung-trang-tong-quan, mà
         * mã lên hosting bằng FTP tự động còn migration thì bấm tay — khoảng
         * giữa dài hàng giờ. Hỏi thẳng một bảng chưa tồn tại là lỗi 1146 và
         * trang /bo-suu-tap trả 500 vì hai câu tiêu đề.
         */
        if (!Database::tableExists('site_texts')) {
            return self::$nho = [];
        }

        return self::$nho = array_column(
            Database::fetchAll('SELECT text_key, value FROM site_texts'),
            'value',
            'text_key'
        );
    }

    /**
     * Câu chữ của một khoá, hoặc $macDinh khi chưa có.
     *
     * Cả chuỗi RỖNG cũng rơi về mặc định: nhân viên xoá sạch một ô rồi lưu là
     * chuyện thường, và ý của họ gần như luôn là "trả về như cũ" chứ không
     * phải "bỏ hẳn tiêu đề trang".
     */
    public static function get(string $khoa, string $macDinh): string
    {
        $v = trim((string) (self::tatCa()[$khoa] ?? ''));

        return $v !== '' ? $v : $macDinh;
    }

    /**
     * Ghi một loạt khoá trong MỘT lần, rồi bỏ bộ nhớ tạm.
     *
     * INSERT ... ON DUPLICATE KEY UPDATE chứ không kiểm-rồi-ghi: hai lượt như
     * thế có một khe ở giữa, và tuy khu quản trị hiếm khi có hai người bấm lưu
     * cùng lúc, viết đúng ngay từ đầu rẻ hơn đi tìm lại lỗi đó về sau.
     *
     * Giá trị rỗng thì XOÁ dòng thay vì ghi chuỗi rỗng — get() coi hai thứ đó
     * như nhau, nên giữ dòng rỗng lại chỉ làm bảng có rác.
     *
     * @param array<string,string> $cap khoá => câu chữ
     */
    public static function saveMany(array $cap): void
    {
        if ($cap === [] || !Database::tableExists('site_texts')) {
            return;
        }

        $ghi  = [];
        $xoa  = [];

        foreach ($cap as $khoa => $giaTri) {
            $giaTri = trim((string) $giaTri);

            if ($giaTri === '') {
                $xoa[] = $khoa;
                continue;
            }

            $ghi[$khoa] = $giaTri;
        }

        if ($ghi !== []) {
            $cho = [];
            $par = [];
            $i   = 0;

            foreach ($ghi as $khoa => $giaTri) {
                $cho[]           = "(:k{$i}, :v{$i})";
                $par["k{$i}"]    = $khoa;
                $par["v{$i}"]    = $giaTri;
                $i++;
            }

            Database::execute(
                'INSERT INTO site_texts (text_key, value) VALUES ' . implode(', ', $cho)
                . ' ON DUPLICATE KEY UPDATE value = VALUES(value)',
                $par
            );
        }

        if ($xoa !== []) {
            $cho = implode(',', array_fill(0, count($xoa), '?'));
            Database::execute("DELETE FROM site_texts WHERE text_key IN ({$cho})", $xoa);
        }

        // Bộ nhớ tạm đã cũ. Đặt null chứ không xoá từng khoá: lần đọc sau tự
        // nạp lại cả bảng, và đó là thứ duy nhất chắc chắn đúng.
        self::$nho = null;
    }
}
