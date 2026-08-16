/**
 * ar-tryon.js — Thử kính AR.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO KHÔNG DÙNG window.FaceDetector NHƯ BẢN LOVABLE
 *
 * Bản gốc gọi `new window.FaceDetector()` (Shape Detection API). API đó chưa
 * từng ship trên Chrome để bàn, Firefox đã từ chối triển khai, Safari không
 * có. Kết quả: trên gần như mọi máy tính, phần tự căn gọng không bao giờ chạy
 * — người dùng phải tự kéo thanh trượt cho gọng vào đúng chỗ, tức là mất hẳn
 * ý nghĩa của chữ "AR".
 *
 * Ở đây dùng MediaPipe FaceLandmarker (WASM). Nó cho 478 điểm mốc trên mặt
 * thay vì một khung chữ nhật, nên tính được:
 *   - VỊ TRÍ  : trung điểm hai mắt
 *   - BỀ RỘNG : khoảng cách hai đuôi mắt (chính là thứ quyết định cỡ gọng)
 *   - ĐỘ NGHIÊNG: góc của đường nối hai mắt, để gọng nghiêng theo đầu
 *
 * Model nặng ~3,7MB nên CHỈ tải khi người dùng bấm bật camera.
 * ─────────────────────────────────────────────────────────────────────────────
 */

(function () {
    'use strict';

    var root = document.getElementById('arRoot');
    if (!root) return;

    // ------------------------------------------------------------------
    // Tham chiếu DOM
    // ------------------------------------------------------------------
    var $ = function (id) { return document.getElementById(id); };

    var stage = $('arStage'), video = $('arVideo'), photo = $('arPhoto'), frameEl = $('arFrame');
    var idle = $('arIdle'), loading = $('arLoading'), loadingText = $('arLoadingText');
    var errBox = $('arError'), errText = $('arErrorText'), status = $('arStatus'), bar = $('arBar');
    var startBtn = $('arStart'), retryBtn = $('arRetry'), stopBtn = $('arStop'), shotBtn = $('arShot');
    var upload = $('arUpload');

    var FRAMES = JSON.parse(root.dataset.frames || '[]');
    var ADVICE = JSON.parse(root.dataset.advice || '{}');

    var stream = null;
    var landmarker = null;
    var rafId = null;
    var running = false;

    // Tinh chỉnh thủ công, cộng thêm vào kết quả tự căn
    var tune = { offsetY: 0, scale: 1, tilt: 0, size: 1 };
    var current = FRAMES[0] || null;

    /* ====================================================================
       HIỂN THỊ TRẠNG THÁI
       ==================================================================== */

    function show(which, message) {
        idle.hidden    = which !== 'idle';
        loading.hidden = which !== 'loading';
        errBox.hidden  = which !== 'error';
        bar.hidden     = which !== 'live';
        frameEl.hidden = which !== 'live';

        if (which === 'loading' && message) loadingText.textContent = message;
        if (which === 'error' && message) errText.textContent = message;
        if (which !== 'live') { status.hidden = true; }
    }

    function setStatus(text) {
        if (!text) { status.hidden = true; return; }
        status.hidden = false;
        status.textContent = text;
    }

    /* ====================================================================
       TẢI BỘ NHẬN DIỆN
       ==================================================================== */

    function loadDetector() {
        if (landmarker) return Promise.resolve(landmarker);

        // import() động: bundle chỉ được tải ở lần bật camera đầu tiên,
        // không nằm trong đường tải của trang.
        return import(root.dataset.visionBundle)
            .then(function (vision) {
                // forVisionTasks — KHÔNG phải resolveForVisionTasks.
                // Tên sai sẽ ném "is not a function" ngay lần bật camera đầu,
                // và thông báo lỗi rơi vào nhánh chung nên rất khó truy.
                return vision.FilesetResolver.forVisionTasks(root.dataset.visionWasm)
                    .then(function (fileset) {
                        return vision.FaceLandmarker.createFromOptions(fileset, {
                            baseOptions: {
                                modelAssetPath: root.dataset.visionModel,
                                // GPU nhanh hơn nhiều; máy không có WebGL sẽ
                                // tự lỗi và rơi xuống nhánh catch bên dưới.
                                delegate: 'GPU',
                            },
                            runningMode: 'VIDEO',
                            numFaces: 1,
                        });
                    });
            })
            .then(function (fl) { landmarker = fl; return fl; });
    }

    /* ====================================================================
       CĂN GỌNG THEO ĐIỂM MỐC
       ==================================================================== */

    /*
     * Chỉ số điểm mốc của MediaPipe Face Mesh:
     *   33  — đuôi mắt TRÁI (theo hướng nhìn của ảnh)
     *   263 — đuôi mắt PHẢI
     *   168 — sống mũi, giữa hai mắt
     * Ba điểm này đủ để đặt gọng; không cần cả 478 điểm.
     */
    var L_EYE = 33, R_EYE = 263, NOSE = 168;

    function applyLandmarks(points) {
        var l = points[L_EYE], r = points[R_EYE], nose = points[NOSE];
        if (!l || !r || !nose) return;

        // Toạ độ chuẩn hoá 0..1 -> phần trăm khung hình.
        // Dùng % thay vì px để gọng tự đúng khi khung đổi kích thước
        // (xoay điện thoại, thu nhỏ cửa sổ) mà không phải tính lại.
        var dx = (r.x - l.x), dy = (r.y - l.y);

        // Khoảng cách hai đuôi mắt = bề rộng tham chiếu của gọng.
        // Chia cho 'anchor' vì gọng trên ảnh PNG rộng hơn khoảng hai mắt.
        var eyeSpan = Math.sqrt(dx * dx + dy * dy);
        var widthPct = (eyeSpan / (current.anchor || 0.92)) * 100 * 1.34 * tune.size * tune.scale;

        // Tâm gọng: trung điểm hai mắt, hạ nhẹ xuống sống mũi
        var cx = ((l.x + r.x) / 2) * 100;
        var cy = (((l.y + r.y) / 2) * 0.72 + nose.y * 0.28) * 100 + tune.offsetY * 0.1;

        // Góc nghiêng của đường nối hai mắt
        var angle = Math.atan2(dy, dx) * 180 / Math.PI + tune.tilt;

        frameEl.style.setProperty('--ar-x', cx + '%');
        frameEl.style.setProperty('--ar-y', cy + '%');
        frameEl.style.setProperty('--ar-w', widthPct + '%');
        frameEl.style.setProperty('--ar-r', angle + 'deg');
        frameEl.style.opacity = '1';
    }

    /** Đoán dáng khuôn mặt từ tỉ lệ cao/rộng — port estimateFaceShape() gốc. */
    function faceShape(points) {
        var xs = [], ys = [];
        for (var i = 0; i < points.length; i += 8) { xs.push(points[i].x); ys.push(points[i].y); }
        var w = Math.max.apply(null, xs) - Math.min.apply(null, xs);
        var h = Math.max.apply(null, ys) - Math.min.apply(null, ys);
        var ratio = h / w;

        if (ratio > 1.12) return 'Heart';
        if (ratio > 1.03) return 'Oval';
        if (ratio > 0.90) return 'Round';
        return 'Square';
    }

    var lastShape = null;

    function showAdvice(shape) {
        if (shape === lastShape) return;
        lastShape = shape;

        var labels = { Round: 'Mặt tròn', Oval: 'Mặt oval', Square: 'Mặt vuông', Heart: 'Mặt trái tim' };
        $('arShape').textContent = labels[shape] || shape;
        $('arAdviceText').textContent = ADVICE[shape] || '';
        $('arAdvice').hidden = false;
    }

    /* ====================================================================
       VÒNG LẶP NHẬN DIỆN
       ==================================================================== */

    var missCount = 0;

    function tick() {
        if (!running) return;

        if (video.readyState >= 2 && landmarker) {
            var result;
            try {
                result = landmarker.detectForVideo(video, performance.now());
            } catch (e) {
                // Lỗi ở đây thường do WebGL mất ngữ cảnh (đổi tab, ngủ máy).
                // Dừng hẳn còn hơn để vòng lặp ném lỗi mỗi khung hình.
                running = false;
                setStatus('Nhận diện bị gián đoạn. Hãy tắt rồi bật lại camera.');
                return;
            }

            var faces = (result && result.faceLandmarks) || [];

            if (faces.length > 0) {
                missCount = 0;
                setStatus(null);
                applyLandmarks(faces[0]);
                showAdvice(faceShape(faces[0]));
            } else {
                // Đợi vài khung hình rồi mới báo: chớp mắt hay quay đầu nhẹ
                // cũng làm mất dấu một hai khung, nhấp nháy thông báo liên tục
                // gây khó chịu hơn là hữu ích.
                missCount++;
                if (missCount > 15) {
                    setStatus('Chưa thấy khuôn mặt. Hãy nhìn thẳng vào camera, đủ sáng.');
                    frameEl.style.opacity = '0';
                }
            }
        }

        rafId = requestAnimationFrame(tick);
    }

    /* ====================================================================
       BẬT / TẮT CAMERA
       ==================================================================== */

    function startCamera() {
        show('loading', 'Đang bật camera…');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            show('error', 'Trình duyệt này không hỗ trợ truy cập camera. Hãy thử Chrome, Edge hoặc Safari bản mới.');
            return;
        }

        navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } },
            audio: false,
        })
        .then(function (s) {
            stream = s;
            video.srcObject = s;
            return video.play();
        })
        .then(function () {
            photo.hidden = true;
            video.hidden = false;
            show('loading', 'Đang tải bộ nhận diện khuôn mặt (~4MB, chỉ lần đầu)…');
            return loadDetector();
        })
        .then(function () {
            show('live');
            running = true;
            missCount = 0;
            tick();
        })
        .catch(function (err) {
            stopCamera();

            // Phân biệt lý do để chỉ đúng cách khắc phục, thay vì một câu
            // chung chung như bản gốc.
            var name = err && err.name;
            if (name === 'NotAllowedError' || name === 'SecurityError') {
                show('error', 'Bạn đã từ chối quyền camera. Hãy bấm vào biểu tượng ổ khoá trên thanh địa chỉ và cho phép camera, rồi thử lại.');
            } else if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
                show('error', 'Không tìm thấy camera nào trên thiết bị này.');
            } else if (name === 'NotReadableError') {
                show('error', 'Camera đang được ứng dụng khác sử dụng. Hãy đóng ứng dụng đó rồi thử lại.');
            } else {
                show('error', 'Không tải được bộ nhận diện khuôn mặt. Kiểm tra kết nối mạng rồi thử lại. (' + (err && err.message ? err.message : name) + ')');
            }
        });
    }

    function stopCamera() {
        running = false;
        if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
        video.srcObject = null;
        show('idle');
    }

    /* ====================================================================
       CHỤP ẢNH
       ==================================================================== */

    function capture() {
        var w = video.videoWidth, h = video.videoHeight;
        if (!w || !h) return;

        var canvas = document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        var ctx = canvas.getContext('2d');

        // Lật ngang cho khớp với những gì người dùng đang thấy trên màn hình
        ctx.translate(w, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, w, h);
        ctx.setTransform(1, 0, 0, 1, 0, 0);

        // Vẽ gọng lên đúng vị trí đang hiển thị.
        // Đọc lại từ biến CSS thay vì tính lại: bảo đảm ảnh chụp khớp hệt
        // những gì trên màn hình, kể cả phần tinh chỉnh tay.
        var cs = getComputedStyle(frameEl);
        var xPct = parseFloat(cs.getPropertyValue('--ar-x'));
        var yPct = parseFloat(cs.getPropertyValue('--ar-y'));
        var wPct = parseFloat(cs.getPropertyValue('--ar-w'));
        var rDeg = parseFloat(cs.getPropertyValue('--ar-r'));

        if (!isNaN(xPct) && frameEl.naturalWidth) {
            var fw = (wPct / 100) * w;
            var fh = fw * (frameEl.naturalHeight / frameEl.naturalWidth);
            // Toạ độ x đo trên video CHƯA lật; ảnh đã lật nên phải soi gương lại
            var fx = w - (xPct / 100) * w;
            var fy = (yPct / 100) * h;

            ctx.save();
            ctx.translate(fx, fy);
            ctx.rotate(-rDeg * Math.PI / 180);
            ctx.filter = frameEl.style.filter || 'none';
            ctx.drawImage(frameEl, -fw / 2, -fh / 2, fw, fh);
            ctx.restore();
        }

        canvas.toBlob(function (blob) {
            if (!blob) return;
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'vin-eyewear-thu-kinh.png';
            a.click();
            // Thu hồi sau khi trình duyệt kịp bắt đầu tải
            setTimeout(function () { URL.revokeObjectURL(url); }, 5000);
        }, 'image/png');
    }

    /* ====================================================================
       ẢNH TẢI LÊN
       ==================================================================== */

    function useUpload(file) {
        var reader = new FileReader();
        reader.onload = function () {
            photo.src = String(reader.result);
            photo.hidden = false;
            video.hidden = true;
            show('loading', 'Đang tải bộ nhận diện khuôn mặt…');

            loadDetector()
                .then(function (fl) {
                    // Ảnh tĩnh cần chế độ IMAGE, không phải VIDEO
                    return fl.setOptions({ runningMode: 'IMAGE' }).then(function () { return fl; });
                })
                .then(function (fl) {
                    show('live');
                    // Đợi ảnh vẽ xong mới nhận diện, nếu không kích thước = 0
                    var run = function () {
                        var res = fl.detect(photo);
                        var faces = (res && res.faceLandmarks) || [];
                        if (faces.length > 0) {
                            setStatus(null);
                            applyLandmarks(faces[0]);
                            showAdvice(faceShape(faces[0]));
                        } else {
                            setStatus('Không tìm thấy khuôn mặt trong ảnh này. Hãy chọn ảnh chụp chính diện, rõ mặt.');
                            frameEl.style.opacity = '0';
                        }
                    };
                    if (photo.complete) run(); else photo.onload = run;
                })
                .catch(function () {
                    show('error', 'Không tải được bộ nhận diện khuôn mặt. Kiểm tra kết nối mạng rồi thử lại.');
                });
        };
        reader.readAsDataURL(file);
    }

    /* ====================================================================
       ĐIỀU KHIỂN
       ==================================================================== */

    function selectFrame(id) {
        var f = FRAMES.filter(function (x) { return x.id === id; })[0];
        if (!f) return;

        current = f;
        frameEl.src = f.image;

        $('arBuyName').textContent  = f.name;
        $('arBuyPrice').textContent = f.priceText || formatVnd(f.price);
        $('arBuyId').value          = f.productId;
        $('arBuyLink').href         = f.url;

        var compare = $('arBuyCompare');
        if (f.compareAt) { compare.textContent = formatVnd(f.compareAt); compare.hidden = false; }
        else { compare.hidden = true; }

        var btn = $('arBuyBtn');
        btn.disabled = !f.inStock;
        btn.textContent = f.inStock ? 'Thêm vào giỏ' : 'Tạm hết hàng';
    }

    /** Định dạng tiền giống helper money() của PHP: 2890000 -> "2.890.000₫" */
    function formatVnd(n) {
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '₫';
    }

    root.querySelectorAll('input[name="ar_frame"]').forEach(function (el) {
        el.addEventListener('change', function () { selectFrame(el.value); });
    });

    root.querySelectorAll('input[name="ar_color"]').forEach(function (el) {
        el.addEventListener('change', function () { frameEl.style.filter = el.value; });
    });

    root.querySelectorAll('input[name="ar_lens"]').forEach(function (el) {
        el.addEventListener('change', function () {
            video.style.filter = el.value;
            photo.style.filter = el.value;
        });
    });

    root.querySelectorAll('input[name="ar_size"]').forEach(function (el) {
        el.addEventListener('change', function () { tune.size = parseFloat(el.value); });
    });

    function bindRange(id, key, factor, format) {
        var el = $(id), out = $(id + 'Val');
        el.addEventListener('input', function () {
            tune[key] = parseFloat(el.value) * factor;
            out.textContent = format(el.value);
        });
    }

    bindRange('arOffsetY', 'offsetY', 1,     function (v) { return v; });
    bindRange('arScale',   'scale',   0.01,  function (v) { return v + '%'; });
    bindRange('arTilt',    'tilt',    1,     function (v) { return v + '°'; });

    $('arReset').addEventListener('click', function () {
        tune.offsetY = 0; tune.scale = 1; tune.tilt = 0;
        $('arOffsetY').value = 0; $('arOffsetYVal').textContent = '0';
        $('arScale').value = 100; $('arScaleVal').textContent = '100%';
        $('arTilt').value = 0;    $('arTiltVal').textContent = '0°';
    });

    startBtn.addEventListener('click', startCamera);
    retryBtn.addEventListener('click', startCamera);
    stopBtn.addEventListener('click', stopCamera);
    shotBtn.addEventListener('click', capture);

    upload.addEventListener('change', function () {
        if (upload.files && upload.files[0]) useUpload(upload.files[0]);
    });

    // Rời trang mà không tắt camera thì đèn camera vẫn sáng — người dùng
    // tưởng site còn đang quay.
    window.addEventListener('pagehide', function () {
        if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
    });
})();
