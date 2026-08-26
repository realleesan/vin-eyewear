/**
 * hang-doi.js — sổ giao dịch đang chờ giao về website.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CẦU NỐI NÀY KHÔNG PHẢI CÁI ỐNG, NÓ LÀ CÁI HỘP THƯ
 *
 * Bản đầu chỉ chuyển tiếp thẳng: nhận từ SePay, gọi sang InfinityFree, trả về
 * kết quả. Sai ở chỗ nó biến lớp chống bot của hosting miễn phí thành điểm chết
 * duy nhất — chặng đó hỏng là giao dịch bốc hơi, và không ai biết cho tới lúc
 * khách gọi điện hỏi vì sao chuyển tiền rồi mà đơn vẫn treo.
 *
 * Nên mọi giao dịch đều được XẾP VÀO HỘP TRƯỚC, rồi mới thử giao. Giao được thì
 * đánh dấu xong. Giao không được thì nó nằm lại, và website tự sang lấy bằng
 * POST /api/keo — chiều đó là website gọi RA NGOÀI, lớp chống bot không đứng
 * chắn. Hai đường, một hộp thư, cùng một khoá chống trùng.
 * ─────────────────────────────────────────────────────────────────────────────
 * KHOÁ CHỐNG TRÙNG LÀ `id` CỦA SEPAY, GIỐNG HỆT BÊN CSDL
 *
 * Cùng một giao dịch có thể tới đây nhiều lần: SePay gửi lại tối đa 7 lần trong
 * 5 giờ mỗi khi không nhận được 200. Và cùng một giao dịch có thể được giao HAI
 * đường (đẩy xong mới thấy website cũng vừa kéo).
 *
 * Cả ba tầng đều chống trùng bằng chính con số đó:
 *   · hộp thư này          — Map khoá theo sepay id
 *   · SepayModel::handle   — chèn sổ trước, bắt lỗi trùng
 *   · UNIQUE uq_sepay_txn  — chốt chặn cuối trong MySQL
 * Tầng cuối mới là tầng thật; hai tầng trên chỉ để đỡ phải gọi tới nó.
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐĨA TRÊN RENDER GÓI MIỄN PHÍ LÀ ĐĨA TẠM
 *
 * Không có Render Disk trả phí thì thư mục ghi được sẽ bị xoá mỗi lần deploy,
 * mỗi lần tiến trình bị dựng dậy sau khi ngủ. Ghi file ở đây vẫn có ích — nó
 * sống qua được lần restart do sập, và đó đã là phần lớn các lần restart — nhưng
 * ĐỪNG coi nó là bảo đảm.
 *
 * Bảo đảm thật nằm ở chỗ khác: mặc định QUEUE_ACK=false, tức giao hỏng thì trả
 * 500 cho SePay để chính SePay giữ giao dịch và gửi lại. Xem cau-hinh.js.
 * ─────────────────────────────────────────────────────────────────────────────
 */

'use strict';

const fs   = require('node:fs');
const path = require('node:path');

/* Giữ bao lâu sau khi đã giao xong? SePay gửi lại trong vòng 5 giờ, nên 12 giờ
   là dư để nhận ra "cái này giao rồi". Quá hạn thì dọn, vì chống trùng thật sự
   nằm ở UNIQUE trong MySQL chứ không ở đây. */
const GIU_SAU_KHI_GIAO = 12 * 60 * 60 * 1000;

class HangDoi {
    constructor(cauHinh, nhatKy) {
        this.cauHinh = cauHinh;
        this.nhatKy  = nhatKy;
        this.muc     = new Map();   // sepayId -> bản ghi
        this.tepTin  = path.join(cauHinh.thuMucDuLieu, 'hang-doi.json');
        this.dangGhi = false;

        this.doc();
    }

    /**
     * Xếp một giao dịch vào hộp.
     *
     * @return {{moi: boolean, ban: object}} moi=false nghĩa là đã có sẵn —
     *         SePay gửi lại, hoặc website vừa kéo cái này về rồi.
     */
    them(txn) {
        const id = Number(txn && txn.id);

        if (!Number.isFinite(id) || id <= 0) {
            throw new Error('Giao dịch không có id hợp lệ');
        }

        const daCo = this.muc.get(id);

        if (daCo) {
            daCo.lanNhan += 1;

            return { moi: false, ban: daCo };
        }

        const ban = {
            id,
            txn,
            nhanLuc: new Date().toISOString(),
            lanNhan: 1,
            lanThuGiao: 0,
            daGiao: false,
            giaoLuc: null,
            duong: null,          // 'day' (cầu nối đẩy sang) | 'keo' (website lấy về)
            loiCuoi: null,
        };

        this.muc.set(id, ban);
        this.donDep();
        this.ghi();

        return { moi: true, ban };
    }

    /** Đánh dấu đã giao. `duong` để về sau còn biết đường nào đang thật sự chạy. */
    danhDauGiao(id, duong) {
        const ban = this.muc.get(Number(id));

        if (!ban || ban.daGiao) return false;

        ban.daGiao  = true;
        ban.giaoLuc = new Date().toISOString();
        ban.duong   = duong;
        ban.loiCuoi = null;

        this.ghi();

        return true;
    }

    /** Ghi lại một lần giao hỏng, để `GET /kiem-tra` nói được vì sao. */
    ghiLoi(id, loi) {
        const ban = this.muc.get(Number(id));

        if (!ban) return;

        ban.lanThuGiao += 1;
        ban.loiCuoi = String(loi || '').slice(0, 300);

        this.ghi();
    }

    /**
     * Những giao dịch website còn phải nhận.
     *
     * Cũ trước mới sau: tiền về theo thứ tự nào thì đơn nên đổi theo thứ tự đó,
     * nhất là với đơn trả làm hai lần (cọc rồi tất toán) — xem SepayModel.
     */
    choXuLy(gioiHan = 20) {
        const ds = [];

        for (const ban of this.muc.values()) {
            if (!ban.daGiao) ds.push(ban);
        }

        ds.sort((a, b) => a.nhanLuc.localeCompare(b.nhanLuc));

        return ds.slice(0, gioiHan);
    }

    demChoXuLy() {
        let n = 0;

        for (const ban of this.muc.values()) {
            if (!ban.daGiao) n += 1;
        }

        return n;
    }

    /**
     * Dọn hộp.
     *
     * Hai luật, theo đúng thứ tự ưu tiên:
     *   1. Đã giao và quá 12 giờ  -> bỏ, không còn ai cần.
     *   2. Chạm trần             -> bỏ cái ĐÃ GIAO cũ nhất trước; hết cái đã
     *      giao mới đụng tới cái chưa giao.
     *
     * Luật 2 phải giữ được cái CHƯA giao đến cùng: mất một giao dịch chưa xử lý
     * là mất tiền thật của một đơn hàng thật, còn mất một giao dịch đã xử lý thì
     * chỉ là mất một dòng nhật ký.
     */
    donDep() {
        const nay = Date.now();

        for (const [id, ban] of this.muc) {
            if (ban.daGiao && (nay - Date.parse(ban.giaoLuc || ban.nhanLuc)) > GIU_SAU_KHI_GIAO) {
                this.muc.delete(id);
            }
        }

        if (this.muc.size <= this.cauHinh.tranHangDoi) return;

        const theoTuoi = [...this.muc.values()]
            .sort((a, b) => (Number(a.daGiao) - Number(b.daGiao))
                         || a.nhanLuc.localeCompare(b.nhanLuc));

        let duThua = this.muc.size - this.cauHinh.tranHangDoi;

        for (const ban of theoTuoi) {
            if (duThua <= 0) break;

            if (!ban.daGiao) {
                /* Tới đây nghĩa là hộp đầy TOÀN đồ chưa giao — website đã im
                   lặng rất lâu. Kêu to, vì đây là lúc bắt đầu mất tiền thật. */
                this.nhatKy.loi('Hàng đợi đầy mà vẫn còn ' + duThua
                    + ' giao dịch CHƯA GIAO. Đang buộc phải bỏ #' + ban.id
                    + '. Kiểm tra ngay đường kéo của website.');
            }

            this.muc.delete(ban.id);
            duThua -= 1;
        }
    }

    // ── Đọc/ghi xuống đĩa ────────────────────────────────────────────────

    doc() {
        try {
            const tho = fs.readFileSync(this.tepTin, 'utf8');
            const ds  = JSON.parse(tho);

            if (Array.isArray(ds)) {
                for (const ban of ds) {
                    if (ban && Number.isFinite(Number(ban.id))) {
                        this.muc.set(Number(ban.id), ban);
                    }
                }
            }

            this.nhatKy.tin('Đọc lại hàng đợi từ đĩa: ' + this.muc.size
                + ' bản ghi, ' + this.demChoXuLy() + ' chưa giao.');
        } catch (e) {
            /* Không có file là chuyện bình thường ở lần chạy đầu và sau mỗi lần
               deploy trên gói miễn phí. Chỉ kêu khi file CÓ mà đọc không được —
               đó mới là dấu hiệu hỏng thật. */
            if (e.code !== 'ENOENT') {
                this.nhatKy.loi('Không đọc được hàng đợi cũ: ' + e.message);
            }
        }
    }

    /**
     * Ghi cả hộp xuống đĩa.
     *
     * Ghi file tạm rồi đổi tên: rename trong cùng một hệ tập tin là thao tác
     * nguyên tử, nên tiến trình chết giữa chừng cũng không để lại một file JSON
     * cụt đầu — mà JSON cụt thì lần khởi động sau đọc không ra, mất sạch hộp.
     *
     * Ghi đồng bộ (writeFileSync) là cố ý: hộp thư này nhỏ (tối đa vài trăm bản
     * ghi) và mỗi lần ghi đều đứng ngay sau một sự kiện tiền bạc. Ghi bất đồng
     * bộ để nhanh hơn vài mili giây, đổi lấy nguy cơ tiến trình bị Render dừng
     * đúng lúc chưa kịp xuống đĩa, là đổi sai chiều.
     */
    ghi() {
        try {
            fs.mkdirSync(this.cauHinh.thuMucDuLieu, { recursive: true });

            const tam = this.tepTin + '.tmp';

            fs.writeFileSync(tam, JSON.stringify([...this.muc.values()]), 'utf8');
            fs.renameSync(tam, this.tepTin);

            this.dangGhi = true;
        } catch (e) {
            /* Đĩa chỉ đọc là cấu hình bình thường trên gói miễn phí. Kêu MỘT lần
               rồi thôi: hộp vẫn chạy trong bộ nhớ, và tầng bền vững thật là việc
               SePay gửi lại. Kêu mỗi lần ghi chỉ làm ngập nhật ký. */
            if (this.dangGhi !== false) {
                this.nhatKy.loi('Không ghi được hàng đợi xuống đĩa ('
                    + e.message + '). Hộp thư chỉ còn sống trong bộ nhớ — '
                    + 'giữ QUEUE_ACK=false để SePay còn gửi lại.');
                this.dangGhi = false;
            }
        }
    }

    /** Số liệu cho GET /kiem-tra. */
    tomTat() {
        let daGiao = 0, chuaGiao = 0, hong = 0;

        for (const ban of this.muc.values()) {
            if (ban.daGiao) daGiao += 1;
            else {
                chuaGiao += 1;
                if (ban.lanThuGiao > 0) hong += 1;
            }
        }

        return { tong: this.muc.size, daGiao, chuaGiao, hong, ghiDuocDia: this.dangGhi };
    }
}

module.exports = HangDoi;
