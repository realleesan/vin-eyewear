# Quy tắc CSS toàn cục — Vin Eyewear

Tài liệu này quy định **cách dùng các biến toàn cục** khi viết CSS cho dự án:
bảng màu, thang chữ `clamp()`, thang khoảng cách, lề trang, breakpoint.

Tất cả khai báo trong khối `:root` của **`assets/css/layout.css`**.
Đó là nguồn duy nhất. File CSS của từng trang chỉ được *dùng* các biến này.

---

## Ba luật bắt buộc

> **Luật 1 — Không viết giá trị tuyệt đối cho thứ đã có biến.**
> Cỡ chữ, màu, khoảng cách đều đã có token. Viết thẳng `14px` hay `#6e1f2a` là
> tách chỗ đó ra khỏi hệ thống: sau này đổi thang chữ hay đổi tông màu, chỗ đó
> không đổi theo và trở thành lỗi lệch tông.

> **Luật 2 — Nền nào thì dùng đúng chữ `--on-*` của nền đó.**
> Nền `--brand` → chữ `--on-brand`. Nền `--ink` → chữ `--on-ink`.
> Không tự đoán "chắc là chữ trắng" hay "chắc là chữ đen".

> **Luật 3 — Không khai lại `font-size` trong `@media`.**
> Thang chữ tiêu đề đã tự co theo bề ngang màn hình. Khai lại vừa thừa, vừa phá
> tính nhất quán giữa các trang.

---

## Tra nhanh

| Cần | Dùng biến |
|---|---|
| Màu chính (header, nút, khối nhấn) | `--brand` + chữ `--on-brand` |
| Nền trang | `--surface` + chữ `--ink` |
| Thẻ / khối nổi | `--surface-raised` |
| Khối tối (footer, thanh thông báo) | `--ink` + chữ `--on-ink` |
| Chữ phụ trên nền sáng | `--muted` |
| Đường kẻ, viền | `--line` |
| Cỡ chữ tiêu đề | `--fs-h1` … `--fs-h6`, `--fs-display` |
| Cỡ chữ nội dung | `--fs-body`, `--fs-body-sm`, `--fs-label`… |
| padding / margin / gap | `--space-1` … `--space-30` |
| Lề hai bên trang | `--margin` |

---

## 1. Màu

### Bảng màu

Tên biến đặt theo **vai trò**, không theo tên màu — đổi tông không làm tên nói dối.

```css
--brand           #6e1f2a   /* màu chính */
--brand-strong    #571620   /* đậm hơn — hover trên nền SÁNG */
--brand-light     #c9808c   /* nhạt hơn — hover/điểm nhấn trên nền TỐI */
--on-brand        #f3e7d5   /* chữ đặt TRÊN --brand */
--on-brand-muted  rgba(243,231,213,.72)   /* chữ phụ trên --brand */

--surface         #f3e7d5   /* nền trang (beige) */
--surface-raised  #f3e7d5   /* thẻ, khối nổi */
--surface-sunken  #ddd2be   /* khối chìm nhẹ */

--ink             #241a1c   /* chữ chính trên nền sáng + nền khối tối */
--on-ink          #f3e7d5   /* chữ đặt TRÊN --ink */
--muted           #574548   /* chữ phụ trên nền sáng */
--line            #8a8178   /* đường kẻ, viền */
```

Màu trạng thái: `--danger`, `--danger-bright`, `--success`, `--surface-focus`.

### Rule: đặt nền thì đặt luôn chữ

```css
/* SAI — chữ tối trên nền brand chỉ đạt 1.5:1, gần như không đọc được */
.promo { background: var(--brand); color: var(--ink); }

/* ĐÚNG */
.promo { background: var(--brand); color: var(--on-brand); }
```

Nếu khối đó có **phần tử con** đang tự đặt màu chữ tối, phải lật cả con:

```css
.promo { background: var(--brand); color: var(--on-brand); }
.promo h3,
.promo p { color: var(--on-brand); }
.promo .label { color: var(--on-brand-muted); }
```

### Rule: hover trên nền tối dùng `--brand-light`

```css
/* SAI — brand đặt trên ink chỉ 1.5:1, hover coi như mất hút */
.footer-social a:hover { color: var(--brand); }

/* ĐÚNG */
.footer-social a:hover { color: var(--brand-light); }
```

Quy ước: nền **sáng** → nhấn bằng `--brand-strong`; nền **tối** → nhấn bằng `--brand-light`.

### Ngưỡng tương phản phải đạt

| Loại chữ | Tối thiểu |
|---|---|
| Chữ thường | 4.5 : 1 |
| Chữ ≥ 24px, hoặc ≥ 18.66px in đậm | 3 : 1 |

### Tên biến cũ vẫn chạy, nhưng đừng dùng cho code mới

`--yellow`, `--black`, `--white`, `--warm-bg`, `--gray`, `--outline` là **bí danh** trỏ
vào bảng trên (`--yellow` → `--brand`, `--black` → `--ink`, `--white` →
`--surface-raised`…). Code cũ giữ nguyên được, nhưng code mới hãy dùng tên vai trò:
`--black` bây giờ **không còn là màu đen**, đọc rất dễ hiểu nhầm.

---

## 2. Cỡ chữ — thang `clamp()`

### Hai tầng, cố ý khác nhau

**Chữ giao diện — px cố định.** Nhãn, chú thích, nội dung:

```css
--fs-micro    10px      --fs-note     13px
--fs-label    11px      --fs-body-sm  14px
--fs-caption  12px      --fs-body     15px
                        --fs-body-lg  16px
```

Cỡ nhỏ **không** co theo màn hình. Nếu co, trên máy hẹp nó tụt xuống 9–10px, không đọc
được. Đây là chủ đích, không phải thiếu responsive — đừng "sửa" nó thành `clamp()`.

**Chữ tiêu đề — `clamp()` co giãn:**

```css
--fs-lead     16 → 18px      --fs-h3       26 → 36px
--fs-subhead  18 → 22px      --fs-h2       28 → 42px
--fs-h6       20 → 25px      --fs-h1       30 → 48px
--fs-h5       22 → 28px      --fs-display  64 → 120px
--fs-h4       24 → 32px
```

(số trái = cỡ ở màn 600px, số phải = ở màn 1440px trở lên)

### Đọc một dòng `clamp()`

```css
--fs-h1: clamp(28px, calc(17.1px + 2.14vw), 48px);
/*             ↑ sàn      ↑ công thức co giãn      ↑ trần */
```

- **Sàn** — hẹp tới đâu cũng không nhỏ hơn mức này.
- **Công thức** `A + B·vw` — `vw` là 1% bề ngang màn hình. Màn 1000px thì
  `2.14vw = 21.4px`, cộng `17.1px` ra `38.5px`.
- **Trần** — rộng tới đâu cũng không lớn hơn mức này.

Nhờ vậy tiêu đề tự vừa ở **mọi** bề ngang, nên mới có Luật 3.

### Rule: dùng token, không viết px

```css
/* SAI */
.card-title { font-size: 24px; }
@media (max-width: 900px) { .card-title { font-size: 20px; } }

/* ĐÚNG — một dòng, tự co ở mọi khổ màn */
.card-title { font-size: var(--fs-h6); }
```

### Rule: `line-height` phải là TỶ LỆ, không phải px

Đây là bẫy hay gặp nhất khi đi cùng chữ co giãn:

```css
/* SAI — chữ co xuống 28px mà dòng vẫn cao 56px, chữ hở toác */
.headline { font-size: var(--fs-h1); line-height: 56px; }

/* ĐÚNG — dòng co theo chữ */
.headline { font-size: var(--fs-h1); line-height: 1.167; }
```

Chuyển đổi: `tỷ lệ = line-height cũ ÷ font-size cũ`. Ví dụ `56 ÷ 48 = 1.167`.

### Cần một bậc chữ mới thì tính thế nào

Muốn chữ đạt `S` px ở màn 600 và `L` px ở màn 1440:

```
B (hệ số vw)     = (L − S) / 840 × 100
A (px cộng thêm) = S − B × 6
```

Ví dụ muốn 30px → 48px: `B = 18/840×100 = 2.14vw`, `A = 30 − 12.86 = 17.1px`
→ `clamp(28px, calc(17.1px + 2.14vw), 48px)`.

**Thêm bậc mới thì thêm vào `:root`**, đừng viết `clamp()` rời trong file trang.

---

## 3. Khoảng cách

Lưới 4px, dùng cho `padding` / `margin` / `gap`:

```css
--space-1   4px     --space-6   24px    --space-14  56px
--space-2   8px     --space-8   32px    --space-16  64px
--space-3   12px    --space-10  40px    --space-20  80px
--space-4   16px    --space-12  48px    --space-30  120px
--space-5   20px
```

```css
/* SAI */                          /* ĐÚNG */
.card { padding: 24px 16px; }      .card { padding: var(--space-6) var(--space-4); }
```

**Số lẻ ngoài lưới thì được viết px** (1, 2, 3, 5, 7, 18, 22, 84…) — đó là tinh chỉnh
riêng như viền hay canh icon, ép vào thang sẽ làm xê dịch bố cục. Nhưng **phải ghi
comment lý do**, để người sau biết đó là chủ đích chứ không phải quên dùng token.

---

## 4. Lề trang & bố cục

```css
--margin       96px    /* lề trắng hai bên của MỌI section */
--section-gap  120px   /* khoảng cách dọc giữa các section */
--header-h     76px    /* chiều cao thanh nav */
--header-pad   96px    /* lề ngang header — luôn giữ BẰNG --margin */
```

`--margin` là núm chỉnh bề rộng nội dung toàn site: tăng `--margin` = nội dung hẹp lại,
khoảng trống hai bên rộng ra.

```css
/* ĐÚNG — mọi section đệm ngang bằng --margin để thẳng mép với nhau */
.my-section { padding: var(--space-20) var(--margin); }
```

**Đừng đặt lề ngang bằng px.** Section nào tự đặt số riêng sẽ lệch mép so với
header, breadcrumb và các section khác.

`--header-pad` luôn giữ **bằng `--margin`** để logo, icon thanh thông báo và nội dung
section cùng nằm trên một mép trái. Đổi cái này nhớ đổi cái kia.

Lưu ý: `--margin` **có ghi đè theo khổ màn** ở cuối `layout.css` — 128px (≥1600),
96px (1101–1599), 64px (901–1100), 16px (≤900), 12px (≤600). Muốn đổi đồng bộ thì sửa
cả 5 chỗ, không chỉ dòng trong `:root`.

---

## 5. Breakpoint

**Chỉ dùng 4 mốc này. Không tự thêm mốc mới.**

| Mốc | Nghĩa |
|---|---|
| ≤ 600px | điện thoại |
| ≤ 900px | máy bảng — mốc chính, nav thu thành hamburger |
| ≤ 1100px | laptop hẹp |
| ≥ 1600px | màn rộng |

Trước đây dự án có 6 mốc rời rạc (480 / 600 / 768 / 900 / 1023 / 1024): cùng một kiểu
chuyển bố cục mà mỗi file chọn một con số khác, sửa một trang thì trang khác lệch.

**`var()` không dùng được trong `@media`:**

```css
@media (max-width: var(--bp-md)) { }   /* KHÔNG CHẠY — giới hạn của CSS thuần */
```

Nên 4 con số này phải gõ tay. Khối comment ở **đầu `layout.css`** là chỗ tra duy nhất.
Bù lại, thang chữ đã dùng `clamp()` nên bạn gần như không cần đụng tới breakpoint nữa.

---

## 6. Không khai lại class dùng chung

Thứ tự nạp CSS: `layout.css` → CSS của trang → CSS của component. **File nạp sau thắng.**

Nên nếu file trang khai lại một class dùng chung, nó sẽ **đè mất bản gốc** — và lỗi này
rất khó nhìn ra vì hai file cách xa nhau.

```css
/* SAI — trong about.css: đè mất .container của layout.css,
   làm nội dung /about lệch mép 48px so với breadcrumb ngay phía trên */
.container { max-width: 1200px; padding: 0 24px; }

/* ĐÚNG — cần khác biệt thì tăng phạm vi */
.about-page .container { ... }
```

Áp dụng cho mọi class dùng chung: `.container`, `.btn`, `.badge`, `.product-card`…

---

## 7. Tóm tắt Sai → Đúng

| Sai | Đúng |
|---|---|
| `font-size: 14px` | `font-size: var(--fs-body-sm)` |
| `font-size: 32px` + khai lại trong `@media` | `font-size: var(--fs-h4)` |
| `line-height: 56px` với chữ co giãn | `line-height: 1.167` |
| `color: #6e1f2a` | `color: var(--brand)` |
| `background: var(--brand); color: var(--ink)` | `… color: var(--on-brand)` |
| `:hover { color: var(--brand) }` trên nền tối | `:hover { color: var(--brand-light) }` |
| `padding: 24px` | `padding: var(--space-6)` |
| `padding: 60px 80px` cho lề section | `padding: var(--space-16) var(--margin)` |
| `@media (max-width: 768px)` | `@media (max-width: 900px)` |
| Khai lại `.container` trong file trang | `.trang-cua-ban .container` |
| Thêm `clamp()` rời trong file trang | Thêm token mới vào `:root` |

---

## Ghi chú

`DESIGN.md` ở gốc repo mô tả bảng màu **cũ** (hệ Heritage Yellow `#ffcc00`), không còn
đúng sau khi chuyển sang burgundy/beige. Phần màu hãy lấy theo tài liệu này.
