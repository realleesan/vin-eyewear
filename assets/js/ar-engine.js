/**
 * AR ENGINE — Bật camera thiết bị + chọn mẫu kính overlay
 */

(function() {
    'use strict';

    const video = document.getElementById('video');
    const glassOverlay = document.getElementById('glass-overlay');
    const cameraStatus = document.getElementById('camera-status');
    const cameraLoading = document.getElementById('camera-loading');
    const cameraPlaceholder = document.getElementById('camera-placeholder');
    const glassesItems = document.querySelectorAll('.glasses-item');

    let stream = null;
    const facingMode = 'user';

    function setStatus(state, text) {
        if (!cameraStatus) return;

        cameraStatus.classList.remove('is-loading', 'is-active', 'is-error');
        cameraStatus.classList.add('is-' + state);

        const statusText = cameraStatus.querySelector('.status-text');
        if (statusText) {
            statusText.textContent = text;
        }
    }

    function hideLoading() {
        if (cameraLoading) {
            cameraLoading.classList.add('is-hidden');
        }
    }

    function showLoading() {
        if (cameraLoading) {
            cameraLoading.classList.remove('is-hidden');
        }
    }

    function handleError(error) {
        hideLoading();
        setStatus('error', 'Không thể bật camera');

        let message = 'Không thể bật camera. ';

        switch (error.name) {
            case 'NotAllowedError':
            case 'PermissionDeniedError':
                message += 'Vui lòng cấp quyền truy cập camera trong cài đặt trình duyệt.';
                break;
            case 'NotFoundError':
                message += 'Không tìm thấy camera trên thiết bị.';
                break;
            case 'NotReadableError':
                message += 'Camera đang bị sử dụng bởi ứng dụng khác.';
                break;
            default:
                message += error.message || 'Vui lòng thử lại.';
        }

        alert(message);
    }

    async function startCamera() {
        try {
            if (stream) {
                stream.getTracks().forEach(function(track) { track.stop(); });
                stream = null;
            }

            setStatus('loading', 'Đang bật camera…');
            showLoading();

            const constraints = {
                video: {
                    facingMode: facingMode,
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    frameRate: { ideal: 30 }
                },
                audio: false
            };

            stream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = stream;
            await video.play();

            hideLoading();
            setStatus('active', 'Camera đang hoạt động');

        } catch (error) {
            console.error('Lỗi camera:', error);
            handleError(error);
        }
    }

    async function autoStart() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            hideLoading();
            setStatus('error', 'Trình duyệt không hỗ trợ');
            alert('Trình duyệt không hỗ trợ API camera. Vui lòng dùng Chrome, Edge hoặc Safari.');
            return;
        }

        await startCamera();
    }

    function selectGlasses(item) {
        const overlaySrc = item.getAttribute('data-overlay');
        if (!overlaySrc || !glassOverlay) return;

        glassesItems.forEach(function(el) {
            el.classList.remove('active');
            el.setAttribute('aria-selected', 'false');
        });

        item.classList.add('active');
        item.setAttribute('aria-selected', 'true');

        if (cameraPlaceholder) {
            cameraPlaceholder.classList.add('is-switching');
        }

        glassOverlay.src = overlaySrc;

        glassOverlay.onload = function() {
            if (cameraPlaceholder) {
                cameraPlaceholder.classList.remove('is-switching');
            }
        };
    }

    function initGlassesSelector() {
        glassesItems.forEach(function(item) {
            item.addEventListener('click', function() {
                selectGlasses(item);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initGlassesSelector();
        autoStart();
    });

    window.addEventListener('beforeunload', function() {
        if (stream) {
            stream.getTracks().forEach(function(track) { track.stop(); });
        }
    });

})();
