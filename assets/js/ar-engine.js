/**
 * AR ENGINE — Bật camera thiết bị + chọn mẫu kính overlay
 */

(function() {
    'use strict';

    const video = document.getElementById('video');
    const glassOverlay = document.getElementById('glass-overlay');
    const cameraStatus = document.getElementById('camera-status');
    const cameraLoading = document.getElementById('camera-loading');
    const cameraError = document.getElementById('camera-error');
    const cameraPermissionDenied = document.getElementById('camera-permission-denied');
    const cameraActive = document.getElementById('camera-active');
    const cameraPlaceholder = document.getElementById('camera-placeholder');
    const cameraInitial = document.getElementById('camera-initial');
    const errorTitle = document.getElementById('error-title');
    const errorMessage = document.getElementById('error-message');
    const btnRetry = document.getElementById('btn-retry');
    const btnReload = document.getElementById('btn-reload');
    const btnCameraToggle = document.getElementById('btn-camera-toggle');
    const glassesItems = document.querySelectorAll('.glasses-item');

    let stream = null;
    let isCameraOn = false;
    const facingMode = 'user';

    function showState(state) {
        if (cameraLoading) cameraLoading.setAttribute('aria-hidden', 'true');
        if (cameraError) cameraError.setAttribute('aria-hidden', 'true');
        if (cameraPermissionDenied) cameraPermissionDenied.setAttribute('aria-hidden', 'true');
        if (cameraActive) cameraActive.setAttribute('aria-hidden', 'true');
        if (cameraInitial) cameraInitial.setAttribute('aria-hidden', 'true');

        switch (state) {
            case 'initial':
                if (cameraInitial) cameraInitial.setAttribute('aria-hidden', 'false');
                break;
            case 'loading':
                if (cameraLoading) cameraLoading.setAttribute('aria-hidden', 'false');
                break;
            case 'error':
                if (cameraError) cameraError.setAttribute('aria-hidden', 'false');
                break;
            case 'permission-denied':
                if (cameraPermissionDenied) cameraPermissionDenied.setAttribute('aria-hidden', 'false');
                break;
            case 'active':
                if (cameraActive) cameraActive.setAttribute('aria-hidden', 'false');
                break;
        }
    }

    function setStatus(text) {
        if (!cameraStatus) return;
        const statusText = cameraStatus.querySelector('.status-text');
        if (statusText) {
            statusText.textContent = text;
        }
    }

    function handleError(error) {
        let title = 'Không thể truy cập camera';
        let message = 'Vui lòng cấp quyền camera để sử dụng tính năng thử kính.';
        let state = 'error';

        switch (error.name) {
            case 'NotAllowedError':
            case 'PermissionDeniedError':
                title = 'Quyền camera bị từ chối';
                message = 'Bạn đã từ chối quyền truy cập camera. Vui lòng làm theo hướng dẫn bên dưới để cấp quyền.';
                state = 'permission-denied';
                break;
            case 'NotFoundError':
                title = 'Không tìm thấy camera';
                message = 'Không tìm thấy camera trên thiết bị của bạn.';
                break;
            case 'NotReadableError':
                title = 'Camera đang bận';
                message = 'Camera đang được sử dụng bởi ứng dụng khác. Vui lòng đóng ứng dụng khác và thử lại.';
                break;
            case 'NotSupportedError':
                title = 'Trình duyệt không hỗ trợ';
                message = 'Trình duyệt của bạn không hỗ trợ truy cập camera. Vui lòng dùng Chrome, Edge hoặc Safari.';
                break;
            default:
                message = error.message || 'Đã xảy ra lỗi không xác định. Vui lòng thử lại.';
        }

        if (errorTitle) errorTitle.textContent = title;
        if (errorMessage) errorMessage.textContent = message;
        showState(state);
    }

    async function startCamera() {
        try {
            if (stream) {
                stream.getTracks().forEach(function(track) { track.stop(); });
                stream = null;
            }

            showState('loading');

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

            showState('active');
            setStatus('Camera đang hoạt động');
            isCameraOn = true;
            updateCameraToggleButton();

        } catch (error) {
            console.error('Lỗi camera:', error);
            handleError(error);
            isCameraOn = false;
            updateCameraToggleButton();
        }
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(function(track) { track.stop(); });
            stream = null;
        }
        isCameraOn = false;
        showState('initial');
        updateCameraToggleButton();
    }

    function toggleCamera() {
        if (isCameraOn) {
            stopCamera();
        } else {
            startCamera();
        }
    }

    function updateCameraToggleButton() {
        if (!btnCameraToggle) return;

        const iconOn = btnCameraToggle.querySelector('.icon-camera-on');
        const iconOff = btnCameraToggle.querySelector('.icon-camera-off');
        const btnText = btnCameraToggle.querySelector('.btn-text');

        if (isCameraOn) {
            if (iconOn) iconOn.style.display = 'none';
            if (iconOff) iconOff.style.display = 'block';
            if (btnText) btnText.textContent = 'Tắt camera';
            btnCameraToggle.setAttribute('aria-label', 'Tắt camera');
        } else {
            if (iconOn) iconOn.style.display = 'block';
            if (iconOff) iconOff.style.display = 'none';
            if (btnText) btnText.textContent = 'Bật camera';
            btnCameraToggle.setAttribute('aria-label', 'Bật camera');
        }
    }

    function checkBrowserSupport() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            if (errorTitle) errorTitle.textContent = 'Trình duyệt không hỗ trợ';
            if (errorMessage) errorMessage.textContent = 'Trình duyệt của bạn không hỗ trợ truy cập camera. Vui lòng dùng Chrome, Edge hoặc Safari.';
            showState('error');
            return false;
        }
        return true;
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

        glassOverlay.style.opacity = '0.5';
        glassOverlay.src = overlaySrc;

        glassOverlay.onload = function() {
            glassOverlay.style.opacity = '1';
        };
    }

    function initGlassesSelector() {
        glassesItems.forEach(function(item) {
            item.addEventListener('click', function() {
                selectGlasses(item);
            });
        });
    }

    function initRetryButtons() {
        if (btnRetry) {
            btnRetry.addEventListener('click', function() {
                startCamera();
            });
        }

        if (btnReload) {
            btnReload.addEventListener('click', function() {
                location.reload();
            });
        }
    }

    function initCameraToggle() {
        if (btnCameraToggle) {
            btnCameraToggle.addEventListener('click', function() {
                if (checkBrowserSupport()) {
                    toggleCamera();
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        initGlassesSelector();
        initRetryButtons();
        initCameraToggle();
        checkBrowserSupport();
        showState('initial');
    });

    window.addEventListener('beforeunload', function() {
        if (stream) {
            stream.getTracks().forEach(function(track) { track.stop(); });
        }
    });

})();
