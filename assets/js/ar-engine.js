/**
 * AR ENGINE - Bật camera thiết bị bằng getUserMedia
 * Tự động xin quyền và bật camera mặt trước
 */

(function() {
    'use strict';

    // ============================================
    // DOM ELEMENTS
    // ============================================
    const video = document.getElementById('video');
    const glassOverlay = document.getElementById('glass-overlay');

    // ============================================
    // TRẠNG THÁI
    // ============================================
    let stream = null;
    let facingMode = 'user'; // camera trước

    // ============================================
    // XỬ LÝ LỖI
    // ============================================
    function handleError(error) {
        let message = 'Không thể bật camera. ';
        
        switch (error.name) {
            case 'NotAllowedError':
            case 'PermissionDeniedError':
                message += 'Vui lòng cấp quyền truy cập camera.';
                break;
            case 'NotFoundError':
                message += 'Không tìm thấy camera trên thiết bị.';
                break;
            case 'NotReadableError':
                message += 'Camera đang bị sử dụng bởi ứng dụng khác.';
                break;
            default:
                message += error.message;
        }
        
        alert(message);
    }

    // ============================================
    // 🔥 BẬT CAMERA - DÙNG getUserMedia
    // ============================================
    async function startCamera() {
        try {
            // Dừng stream cũ nếu có
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }

            // Cấu hình camera
            const constraints = {
                video: {
                    facingMode: facingMode, // 'user' = camera trước
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    frameRate: { ideal: 30 }
                },
                audio: false
            };

            // 🔥 GỌI getUserMedia - XIN QUYỀN CAMERA
            stream = await navigator.mediaDevices.getUserMedia(constraints);

            // Gán stream vào video
            video.srcObject = stream;
            await video.play();

            console.log('✅ Camera bật thành công!');

        } catch (error) {
            console.error('❌ Lỗi camera:', error);
            handleError(error);
        }
    }

    // ============================================
    // 🔥 TỰ ĐỘNG BẬT CAMERA KHI LOAD TRANG
    // ============================================
    async function autoStart() {
        try {
            // Kiểm tra trình duyệt hỗ trợ
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Trình duyệt không hỗ trợ API camera.');
            }

            // 🔥 TỰ ĐỘNG XIN QUYỀN VÀ BẬT CAMERA TRƯỚC
            await startCamera();

        } catch (error) {
            console.warn('⚠️ Không thể tự động bật camera:', error.message);
        }
    }

    // ============================================
    // CHẠY KHI LOAD TRANG
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        // 🔥 TỰ ĐỘNG BẬT CAMERA
        autoStart();
    });

    // Dừng camera khi rời trang
    window.addEventListener('beforeunload', function() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
    });

    console.log('🚀 AR Engine đã sẵn sàng!');

})();