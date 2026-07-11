<section class="page-header">
    <div class="container">
        <h1><?php echo $pageTitle; ?></h1>
        <p>Liên hệ với chúng tôi</p>
    </div>
</section>

<section class="contact-section">
    <div class="container">
        <div class="contact-wrapper">
            <div class="contact-info">
                <h2>Thông tin liên hệ</h2>
                <div class="contact-item">
                    <h3>Địa chỉ</h3>
                    <p>123 Đường ABC, Quận XYZ, Hà Nội</p>
                </div>
                <div class="contact-item">
                    <h3>Điện thoại</h3>
                    <p>0123 456 789</p>
                </div>
                <div class="contact-item">
                    <h3>Email</h3>
                    <p>info@vineyewear.com</p>
                </div>
                <div class="contact-item">
                    <h3>Giờ làm việc</h3>
                    <p>Thứ 2 - Thứ 7: 8:00 - 20:00</p>
                    <p>Chủ nhật: 9:00 - 18:00</p>
                </div>
            </div>
            
            <div class="contact-form">
                <h2>Gửi tin nhắn</h2>
                <form action="#" method="POST">
                    <div class="form-group">
                        <label for="name">Họ tên</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Số điện thoại</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="message">Nội dung</label>
                        <textarea id="message" name="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Gửi tin nhắn</button>
                </form>
            </div>
        </div>
    </div>
</section>