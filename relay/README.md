# Cầu nối SePay — dựng trên Render

Máy chủ trung gian giữa **SePay** và **website Vin Eyewear** (InfinityFree).

```
    ┌────────┐  webhook   ┌─────────────────┐   ĐẨY    ┌──────────────────┐
    │ SePay  │ ─────────▶ │  cầu nối        │ ───────▶ │  vreyewear.gt.tc │
    │        │            │  (Render)       │ ◀─────── │  (InfinityFree)  │
    └────────┘            └─────────────────┘   KÉO    └──────────────────┘
```

## Vì sao phải có nó

InfinityFree bản miễn phí đặt một **lớp chống bot trước Apache**. Ai gọi vào mà
không phải trình duyệt thì nhận về một trang HTML đố JavaScript chứ không tới
được `index.php`. Máy chủ của SePay không chạy JavaScript.

Hệ quả: webhook trỏ thẳng vào `https://vreyewear.gt.tc/webhook/sepay` **không
bao giờ tới PHP**, và không để lại dấu vết nào — nhật ký WebHooks bên SePay báo
"200 OK" vì lớp chống bot trả 200 kèm HTML, còn error log của website thì trống
trơn. Tiền về tài khoản, đơn nằm im ở `unpaid`.

Cầu nối này có địa chỉ công khai thật nên nhận được webhook, rồi đưa giao dịch
về website bằng **hai đường độc lập**:

| Đường | Chiều | Cơ chế | Điểm yếu |
| :---- | :---- | :----- | :------- |
| **ĐẨY** | cầu nối → website | Tự giải lời đố AES của lớp chống bot rồi POST vào `/webhook/sepay` | Hỏng ngày InfinityFree đổi cách chặn |
| **KÉO** | website → cầu nối | Website POST `/api/keo` lấy về | Không có — chiều gọi ra ngoài không bị tường nào chắn |

Đường đẩy nhanh hơn, đường kéo chắc hơn. **Giữ cả hai.** Cả hai đổ vào cùng một
hộp thư và chống trùng bằng `id` của SePay, nên giao hai lần cũng không cộng
tiền hai lần.

Website kéo lúc nào: mỗi lần màn QR của khách hỏi thăm trạng thái đơn
(`pay-watch.js` hỏi mỗi 4 giây trong hai phút đầu) và mỗi lần nhân viên mở
`/quan-tri/don-hang`. Tức là đúng lúc cần.

---

## Dựng — 6 bước

### 1. Sinh ba khoá

Ba chặng, ba khoá khác nhau. Lộ một chặng không mở được chặng nào khác.

```bash
openssl rand -hex 32     # khoá A — SePay  -> cầu nối
openssl rand -hex 32     # khoá B — cầu nối -> website
openssl rand -hex 32     # khoá C — website -> cầu nối (đường kéo)
```

Chép ra chỗ nào an toàn, lát nữa dán vào bốn nơi khác nhau.

### 2. Đẩy mã lên GitHub

Thư mục `relay/` đã nằm sẵn trong repo này. Chỉ cần `git push` như thường —
GitHub Actions vẫn deploy phần PHP lên InfinityFree và **bỏ qua** `relay/`
(xem `exclude` trong `.github/workflows/deploy.yml`).

### 3. Tạo dịch vụ trên Render

[render.com](https://render.com) → **New** → **Blueprint** → chọn repo này.
Render đọc `render.yaml` **ở gốc repo** — nó không dò thư mục con, đó là lý do
file ấy không nằm cùng chỗ với mã — rồi tự dựng và hỏi bốn giá trị:

| Biến | Điền |
| :--- | :--- |
| `SEPAY_WEBHOOK_KEY` | khoá **A** |
| `SITE_WEBHOOK_URL`  | `https://vreyewear.gt.tc/webhook/sepay` |
| `SITE_WEBHOOK_KEY`  | khoá **B** |
| `PULL_KEY`          | khoá **C** |

> Dựng tay cũng được (**New → Web Service**), nhưng nhớ đặt
> **Root Directory** = `relay` và **Start Command** = `node server.js`.
> Quên Root Directory là Render đi tìm `package.json` ở gốc repo — nơi không có
> file nào như thế — rồi báo một lỗi build chẳng liên quan gì.

Deploy xong, ghi lại địa chỉ Render cấp, dạng
`https://vin-eyewear-relay.onrender.com`.

### 4. Khai bên website

Sửa `.env` **trên hosting** (vPanel → Online File Manager → `htdocs/.env`):

```ini
SEPAY_WEBHOOK_KEY=<khoá B>      # cầu nối gọi vào bằng khoá này
SEPAY_RELAY_URL=https://vin-eyewear-relay.onrender.com
SEPAY_RELAY_KEY=<khoá C>
SEPAY_ENABLED=true
```

Chưa chạy migration thì chạy nốt: vPanel → phpMyAdmin → tab SQL → dán
`database/migrations/2026-08-22-sepay-doi-soat.sql`.

### 5. Trỏ webhook của SePay sang cầu nối

my.sepay.vn → **Tích hợp webhook**:

| Ô | Điền |
| :--- | :--- |
| URL | `https://vin-eyewear-relay.onrender.com/webhook/sepay` |
| Xác thực | API Key → khoá **A** |
| Sự kiện | Có tiền vào |
| Bộ lọc tiền tố | **để trống** |

> **Địa chỉ ở đây là địa chỉ CẦU NỐI, không phải của website.** Đây là chỗ dễ
> dán nhầm nhất: địa chỉ website vẫn còn đó, vẫn đúng cú pháp, vẫn có vẻ hợp lý.

Bộ lọc để trống là cố ý: lọc theo `DH` thì giao dịch của khách gõ sai nội dung
sẽ không bao giờ tới — mà đó đúng là ca cần thấy nhất, vì tiền đã về thật.

### 6. Kiểm

**Chặng đẩy** (từ cầu nối):

```bash
curl "https://vin-eyewear-relay.onrender.com/kiem-tra?thu=1" \
     -H "Authorization: Apikey <khoá C>"
```

`ban_thu.ket_luan` phải nói `✓ Chặng đẩy THÔNG`. Nó gửi một payload `id: 0` —
website nhận được, hiểu được, và trả 400 vì payload là bịa. Không dữ liệu nào
bị đụng tới.

**Chặng kéo** (từ website):

```
https://vreyewear.gt.tc/kiem-tra-sepay.php?token=<INSTALL_TOKEN>
```

Mục 7 phải xanh và kéo thử được.

**Cả chuỗi:** my.sepay.vn → Giao dịch → **Giả lập giao dịch**, nội dung là một
mã đơn có thật. Xong chạy lại `kiem-tra-sepay.php`, mục 6 phải có thêm một dòng.

---

## Gói miễn phí ngủ sau 15 phút

Render free **dừng dịch vụ sau 15 phút không ai gọi**, và tỉnh dậy mất khoảng
50 giây. Webhook đầu tiên sau khi ngủ có thể quá hạn giờ của SePay — không mất
giao dịch (SePay gửi lại tối đa 7 lần trong 5 giờ), nhưng khách phải chờ lâu
hơn trước màn QR.

Chữa bằng cách gọi `GET /` mỗi 10 phút từ một dịch vụ ping miễn phí
([cron-job.org](https://cron-job.org), UptimeRobot…). Đường đó không cần khoá và
không in ra gì.

Kèm theo: **đĩa của gói free là đĩa tạm.** Hàng đợi ghi xuống `DATA_DIR` mất sau
mỗi lần deploy và mỗi lần dịch vụ tỉnh dậy. Vì thế mặc định `QUEUE_ACK=false` —
đẩy hỏng thì trả 500 để chính SePay giữ giao dịch và gửi lại. Đó là tầng bền
vững duy nhất không phụ thuộc đĩa. Đừng đổi thành `true` khi chưa gắn Render
Disk trả phí.

---

## Đường trên cầu nối

| Đường | Ai gọi | Khoá |
| :--- | :--- | :--- |
| `GET /` | dịch vụ ping | không |
| `POST /webhook/sepay` | SePay | `SEPAY_WEBHOOK_KEY` |
| `POST /api/keo` | website | `PULL_KEY` |
| `GET /kiem-tra[?thu=1]` | người | `PULL_KEY` |

`POST /api/keo` — thân `{"ack": [123, 456], "gioi_han": 20}`, trả
`{"ok": true, "giao_dich": [...], "con_lai": 0}`.

**`ack` chỉ được gửi sau khi website đã ghi giao dịch vào CSDL**, không phải sau
khi nhận được. Ack sớm rồi hỏng giữa chừng là mất hẳn: cầu nối xoá khỏi hộp,
SePay tưởng đã giao xong từ lâu, tiền về mà đơn treo mãi.

---

## Khi có gì đó hỏng

Đọc nhật ký theo thứ tự này, **dừng ở chặng đầu tiên trống**:

| # | Xem ở đâu | Trả lời câu hỏi |
| :- | :--- | :--- |
| 1 | my.sepay.vn → Nhật ký WebHooks | SePay có gọi được cầu nối không |
| 2 | Render → dịch vụ → Logs | cầu nối nhận và đẩy được không |
| 3 | error log của hosting, lọc `[SePay]` | website ghi sổ được không |

Vài triệu chứng hay gặp:

**Render Logs: `WEBSITE TỪ CHỐI KHOÁ (HTTP 401)`**
`SITE_WEBHOOK_KEY` bên Render lệch với `SEPAY_WEBHOOK_KEY` trong `.env` trên
hosting. So vân tay: `GET /kiem-tra` in `khoa_site`, còn `kiem-tra-sepay.php`
mục 2 in vân tay bên kia. Trong lúc chưa sửa, giao dịch vẫn về được bằng đường
kéo.

**Render Logs: `Gặp lớp chống bot nhưng KHÔNG đọc được lời đố`**
InfinityFree đã đổi cách chặn. Đường đẩy chết, đường kéo vẫn chạy nên **không
mất giao dịch nào** — nhưng phải sửa `lib/infinityfree.js` theo trang HTML in
kèm trong chính dòng log đó.

**`kiem-tra-sepay.php` mục 7: không gọi được cầu nối**
Thường là Render đang ngủ; chờ một phút rồi chạy lại. Nếu lặp lại thì so
`SEPAY_RELAY_KEY` với `PULL_KEY`.

**Nhật ký WebHooks bên SePay đỏ nhưng đơn vẫn sang "đã thanh toán"**
Đúng như thiết kế: đẩy hỏng → trả 500 → SePay gửi lại, trong khi website đã kéo
được rồi. Không phải lỗi. Muốn nhật ký xanh thì đọc `QUEUE_ACK` trong
`lib/cau-hinh.js` — kèm theo đánh đổi ghi ở đó.

---

## Chạy thử dưới máy

```bash
cd relay
cp .env.example .env      # rồi điền giá trị
set -a && . ./.env && set +a
node server.js
```

Không có phụ thuộc nào để cài, không có bước build — cùng ràng buộc với phần
PHP (xem `CLAUDE.md`). Cần Node 20 trở lên.
