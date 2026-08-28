<?php

/**
 * admin/_layout/modal-foot.php — đóng hộp thoại: chân hộp và các thẻ đóng.
 *
 * Đi CẶP với modal-head.php. Nhận qua partial():
 *   $dongUrl  — địa chỉ khi bấm Huỷ
 *   $luuNhan  — nhãn nút chính, vd 'Lưu thay đổi'
 *   $luuForm  — id của <form> mà nút Lưu thuộc về (thuộc tính form=)
 *   $ghiChu   — câu nhắc dồn trái ở chân hộp (tuỳ chọn)
 *
 * NÚT LƯU DÙNG THUỘC TÍNH form=, không nằm trong <form>.
 *
 * Chân hộp phải ở NGOÀI vùng cuộn của ruột hộp — form sản phẩm dài hơn màn
 * hình, và nút Lưu mà cuộn mất thì người dùng điền xong không biết bấm đâu.
 * Nhưng HTML cấm một <form> vắt qua hai khối bố cục như vậy mà vẫn giữ được
 * cấu trúc. `form=` gỡ đúng nút đó ra khỏi cây DOM của form mà vẫn gửi cùng
 * nó — cùng cách đã dùng ở bảng đơn hàng.
 */
$ghiChu = $ghiChu ?? '';
?>
        </div>

        <div class="amodal__foot">
            <?php if ($ghiChu !== ''): ?>
                <p class="amodal__note"><?= e($ghiChu) ?></p>
            <?php endif; ?>

            <a class="astatus__save astatus__save--ghost" href="<?= e($dongUrl) ?>" data-modal-close>Huỷ</a>
            <button type="submit" form="<?= e($luuForm) ?>" class="astatus__save"><?= e($luuNhan) ?></button>
        </div>
    </div>
</div>
