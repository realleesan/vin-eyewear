/**
 * Vin Eyewear - Contact Page JavaScript
 * Handles interactive store location map switching and client-side form validation.
 */
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    /* ============================================================
       1. INTERACTIVE STORE MAP LOCATOR
       ============================================================ */
    var storeCards = document.querySelectorAll('.store-card');
    var mapContainers = document.querySelectorAll('.map-container');

    if (storeCards.length && mapContainers.length) {
        /**
         * Activate the selected store and display its respective map iframe.
         * @param {string} storeId The ID of the store (e.g. 'long-bien', 'tay-ho')
         */
        var activateStore = function(storeId) {
            // Remove active state from all store cards
            storeCards.forEach(function(card) {
                card.classList.remove('active');
            });

            // Remove active state from all map containers
            mapContainers.forEach(function(map) {
                map.classList.remove('active');
            });

            // Add active state to the current store card
            var targetCard = document.querySelector('.store-card[data-store-id="' + storeId + '"]');
            if (targetCard) {
                targetCard.classList.add('active');
            }

            // Add active state to the corresponding map viewport container
            var targetMap = document.getElementById('map-' + storeId);
            if (targetMap) {
                targetMap.classList.add('active');
            }
        };

        // Bind event listeners to each store card
        storeCards.forEach(function(card) {
            var storeId = card.getAttribute('data-store-id');
            if (!storeId) return;

            // Mouse click activation
            card.addEventListener('click', function() {
                activateStore(storeId);
            });

            // Accessibility activation via Enter or Space when focused
            card.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault(); // Prevent browser scrolling on Spacebar keypress
                    activateStore(storeId);
                }
            });
        });
    }

    /* ============================================================
       2. CLIENT-SIDE FORM VALIDATION
       ============================================================ */
    var form = document.querySelector('.vin-form');
    if (form) {
        var nameInput = document.getElementById('name');
        var emailInput = document.getElementById('email');
        var phoneInput = document.getElementById('phone');
        var messageInput = document.getElementById('message');

        form.addEventListener('submit', function(event) {
            var isValid = true;
            clearErrors();

            // 1. Validate Họ Tên (Bắt buộc & chỉ chứa chữ cái + khoảng trắng)
            var nameVal = nameInput.value.trim();
            var nameRegex = /^[\p{L}\s]+$/u;
            if (!nameVal) {
                showError(nameInput, 'Vui lòng nhập họ và tên của bạn.');
                isValid = false;
            } else if (!nameRegex.test(nameVal)) {
                showError(nameInput, 'Họ và tên chỉ được chứa chữ cái và khoảng trắng, không chứa số hoặc ký tự đặc biệt.');
                isValid = false;
            }

            // 2. Validate Email (Bắt buộc & Chuẩn quốc tế)
            var emailVal = emailInput.value.trim();
            var emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!emailVal) {
                showError(emailInput, 'Vui lòng nhập địa chỉ email của bạn.');
                isValid = false;
            } else if (!emailRegex.test(emailVal)) {
                showError(emailInput, 'Địa chỉ email không đúng định dạng quốc tế.');
                isValid = false;
            }

            // 3. Validate Số điện thoại (Bắt buộc & Chuẩn VN)
            var phoneVal = phoneInput.value.trim();
            var phoneRegex = /^0\d{9}$/;
            if (!phoneVal) {
                showError(phoneInput, 'Vui lòng nhập số điện thoại liên hệ.');
                isValid = false;
            } else if (!phoneRegex.test(phoneVal)) {
                showError(phoneInput, 'Số điện thoại Việt Nam phải có đúng 10 số và bắt đầu bằng số 0.');
                isValid = false;
            }

            // 4. Validate Nội dung (Bắt buộc)
            if (!messageInput.value.trim()) {
                showError(messageInput, 'Vui lòng nhập nội dung yêu cầu tư vấn.');
                isValid = false;
            }

            // Ngăn gửi form nếu có lỗi
            if (!isValid) {
                event.preventDefault();
                // Cuộn tới lỗi đầu tiên
                var firstError = form.querySelector('.form-group.has-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                // Chặn submit thực tế do đang ở Phase 1.5 và hiển thị popup thành công
                event.preventDefault();
                showSuccessModal(nameInput.value.trim());
                form.reset();
            }
        });

        /**
         * Hiển thị lỗi dưới input
         */
        function showError(inputElement, errorMessage) {
            var formGroup = inputElement.closest('.form-group');
            if (formGroup) {
                formGroup.classList.add('has-error');
                
                var errorSpan = document.createElement('span');
                errorSpan.className = 'error-message';
                errorSpan.textContent = errorMessage;
                formGroup.appendChild(errorSpan);
            }
        }

        /**
         * Xóa toàn bộ thông báo lỗi cũ
         */
        function clearErrors() {
            var errorGroups = form.querySelectorAll('.form-group.has-error');
            errorGroups.forEach(function(group) {
                group.classList.remove('has-error');
                var errorMsgs = group.querySelectorAll('.error-message');
                errorMsgs.forEach(function(msg) {
                    msg.remove();
                });
            });
        }

        /**
         * Hiển thị Modal thông báo gửi thành công chuẩn phong cách Vin Eyewear
         */
        function showSuccessModal(clientName) {
            // Tạo background overlay
            var overlay = document.createElement('div');
            overlay.className = 'success-modal-overlay';
            
            // Nội dung modal
            overlay.innerHTML = `
                <div class="success-modal-content">
                    <div class="success-modal-header">GỬI YÊU CẦU THÀNH CÔNG</div>
                    <div class="success-modal-body">
                        <p>Cảm ơn <strong>${clientName}</strong> đã liên hệ với <strong>Vin Eyewear</strong>.</p>
                        <p>Thông tin của bạn đã được hệ thống ghi nhận. Chúng tôi sẽ phản hồi lại qua Email hoặc Số điện thoại trong vòng 24 giờ tới.</p>
                    </div>
                    <div class="success-modal-footer">
                        <button type="button" class="btn success-modal-close-btn">ĐỒNG Ý</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(overlay);
            // Disable scroll
            document.body.style.overflow = 'hidden';

            var closeModal = function() {
                overlay.remove();
                document.body.style.overflow = '';
            };

            // Sự kiện đóng modal
            overlay.querySelector('.success-modal-close-btn').addEventListener('click', closeModal);
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    closeModal();
                }
            });
        }
    }
});
