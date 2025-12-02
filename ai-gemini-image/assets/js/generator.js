(function($) {
    'use strict';

    var AIGeminiGenerator = {
        config: window.AIGeminiConfig || {},
        currentImageData: null,
        currentImageId: null,
        currentImageSessionId: null, // <-- NEW: ID phiên ảnh, dùng lại giữa nhiều preview
        currentMission: null,
        pendingAction: null, // Lưu hành động đang dở dang (generate/unlock) để retry

        // KEY dùng để lưu ảnh hiện tại trong localStorage (dùng chung giữa mọi URL)
        storageKey: 'ai_gemini_current_image',

        init: function() {
            if ($('#selected-style-slug').length) {
                this.selectedStyle = $('#selected-style-slug').val();
            }
            this.bindEvents();
            this.initDropzone();
            this.restoreImageFromStorage(); // Khôi phục ảnh nếu có trong localStorage
        },
        
        bindEvents: function() {
            var self = this;
            
            // Upload & UI Events
            $('#upload-area').on('click', function(e) {
                if (e.target.id !== 'image-input' && !$(e.target).closest('#remove-image').length) {
                    if (!$('#upload-preview').is(':visible')) $('#image-input').trigger('click');
                }
            });
            $('#image-input').on('change', function(e) {
                if (e.target.files.length) self.handleFileSelect(e.target.files[0]);
                $(this).val('');
            });
            $('#remove-image').on('click', function(e) { 
                e.preventDefault(); 
                e.stopPropagation(); 
                self.removeImage(true); // true: cũng xóa localStorage
            });
            
            $('.style-option').on('click', function() {
                $('.style-option').removeClass('active');
                $(this).addClass('active');
                $('#selected-style-slug').val($(this).data('style'));
            });

            // Action Buttons
            $('#btn-generate').on('click', function() { 
                self.pendingAction = 'generate'; 
                self.generatePreview(); 
            });
            
            $('#btn-unlock').on('click', function() { 
                self.pendingAction = 'unlock'; 
                self.unlockImage(); 
            });
            
            $('#btn-regenerate').on('click', function() { self.resetToForm(); });
            $('#btn-new').on('click', function() { self.resetAll(); });
            
            // Error Box Buttons
            $('#btn-retry').on('click', function() { 
                self.hideError(); 
                // Nếu không phải lỗi hết tiền thì reset form, nếu hết tiền thì người dùng chọn làm nv hoặc đóng
            });

            // MISSION EVENTS
            $(document).on('click', '.btn-earn-free', function(e) {
                e.preventDefault();
                self.startMission();
            });
            
            $(document).on('click', '#btn-verify-mission', function() {
                self.verifyMissionCode();
            });
        },

        // ========= LƯU / KHÔI PHỤC ẢNH GIỮA CÁC URL =========

        /**
         * Khôi phục ảnh từ localStorage khi trang load.
         * Dùng chung giữa mọi URL trên cùng domain.
         */
        restoreImageFromStorage: function() {
            var saved = null;
            try {
                var raw = localStorage.getItem(this.storageKey);
                if (raw) {
                    saved = JSON.parse(raw);
                }
            } catch (e) {
                return;
            }

            if (!saved || !saved.dataUrl) return;

            // Giới hạn sống 24h cho ảnh lưu
            var maxAgeMs = 24 * 60 * 60 * 1000;
            if (saved.createdAt && (Date.now() - saved.createdAt > maxAgeMs)) {
                this.clearImageFromStorage();
                return;
            }

            this.currentImageData = saved.dataUrl;
            this.showPreview(saved.dataUrl);
            $('#btn-generate').prop('disabled', false);

            // Nếu bạn muốn, sau này ta có thể lưu cả image_session_id vào localStorage ở đây
            if (saved.imageSessionId) {
                this.currentImageSessionId = saved.imageSessionId;
            }
        },

        /**
         * Lưu ảnh hiện tại vào localStorage.
         * @param {string} dataUrl
         */
        saveImageToStorage: function(dataUrl) {
            try {
                var payload = {
                    dataUrl: dataUrl,
                    createdAt: Date.now(),
                    imageSessionId: this.currentImageSessionId || null
                };
                localStorage.setItem(this.storageKey, JSON.stringify(payload));
            } catch (e) {
                // Bị chặn/quota đầy -> bỏ qua
            }
        },

        /**
         * Xóa ảnh khỏi localStorage.
         */
        clearImageFromStorage: function() {
            try {
                localStorage.removeItem(this.storageKey);
            } catch (e) {}
        },

        // ========= UPLOAD / PREVIEW =========

        initDropzone: function() {
            var self = this; 
            var $d = $('#upload-area'); 
            if(!$d.length) return;
            ['dragenter','dragover','dragleave','drop'].forEach(function(e){ 
                $d[0].addEventListener(e, function(ev){ ev.preventDefault();ev.stopPropagation();}, false); 
            });
            $d.on('drop', function(e){ 
                if(e.originalEvent.dataTransfer.files.length) 
                    self.handleFileSelect(e.originalEvent.dataTransfer.files[0]); 
            });
        },

        handleFileSelect: function(file) {
            var self = this;
            if(file.size > self.config.max_file_size) { 
                self.showError(self.config.strings.error_file_size); 
                return; 
            }
            var reader = new FileReader();
            reader.onload = function(e) { 
                self.currentImageData      = e.target.result; 
                self.currentImageSessionId = null; // ảnh mới -> session mới
                self.showPreview(e.target.result); 
                $('#btn-generate').prop('disabled', false); 
                self.saveImageToStorage(e.target.result); 
            };
            reader.readAsDataURL(file);
        },

        showPreview: function(src) { 
            $('#preview-image').attr('src', src); 
            $('.upload-placeholder').hide(); 
            $('#upload-preview').show(); 
        },

        /**
         * Xóa ảnh khỏi UI
         * @param {boolean} clearStorage
         */
        removeImage: function(clearStorage) { 
            this.currentImageData       = null; 
            this.currentImageId         = null;
            this.currentImageSessionId  = null;
            $('#image-input').val(''); 
            $('#upload-preview').hide(); 
            $('.upload-placeholder').show(); 
            $('#btn-generate').prop('disabled', true); 
            if (clearStorage) {
                this.clearImageFromStorage();
            }
        },

        // ========= GENERATE =========

        generatePreview: function() {
            var self = this;
            if (!this.currentImageData) { 
                this.showError(this.config.strings.error_upload); 
                return; 
            }
            this.selectedStyle = $('#selected-style-slug').val();
            
            $('#btn-generate .btn-text').hide(); 
            $('#btn-generate .btn-loading').show(); 
            $('#btn-generate').prop('disabled', true);
            
            var img = this.currentImageData.includes(',') ? this.currentImageData.split(',')[1] : this.currentImageData;
            
            $.ajax({
                url: this.config.api_preview,
                method: 'POST',
                headers: { 'X-WP-Nonce': this.config.nonce },
                contentType: 'application/json',
                data: JSON.stringify({ 
                    image: img, 
                    image_session_id: this.currentImageSessionId || null,
                    style: this.selectedStyle, 
                    prompt: $('#custom-prompt').val() 
                }),
                success: function(res) { self.handlePreviewSuccess(res); },
                error: function(xhr) { self.handleAPIError(xhr); },
                complete: function() { 
                    $('#btn-generate .btn-text').show(); 
                    $('#btn-generate .btn-loading').hide(); 
                    $('#btn-generate').prop('disabled', false); 
                }
            });
        },
        
        handlePreviewSuccess: function(res) {
            if (res.success && res.preview_url) {
                this.currentImageId        = res.image_id;
                this.currentImageSessionId = res.image_session_id || this.currentImageSessionId || null;

                // Cập nhật lại localStorage để lưu luôn session id
                if (this.currentImageData) {
                    this.saveImageToStorage(this.currentImageData);
                }

                $('#result-image').attr('src', res.preview_url);
                $('.gemini-generator-form').hide(); 
                $('#gemini-result').show();
                if(res.credits_remaining !== undefined) 
                    $('#gemini-credits-display').text(res.credits_remaining);
                if(!res.can_unlock) 
                    $('#btn-unlock').prop('disabled', true).text('Không đủ credit');
            } else { 
                this.showError(res.message || 'Lỗi'); 
            }
        },

        // ========= UNLOCK =========

        unlockImage: function() {
            var self = this;
            if (!this.currentImageId) return;
            var $btn = $('#btn-unlock'); 
            var txt  = $btn.text();
            $btn.prop('disabled', true).text(this.config.strings.unlocking);
            
            $.ajax({
                url: this.config.api_unlock,
                method: 'POST',
                headers: { 'X-WP-Nonce': this.config.nonce },
                contentType: 'application/json',
                data: JSON.stringify({ image_id: this.currentImageId }),
                success: function(res) { 
                    if(res.success){ 
                        $('#unlocked-image').attr('src', res.full_url); 
                        $('#btn-download').attr('href', res.full_url);
                        $('#gemini-result').hide(); 
                        $('#gemini-unlocked').show();
                        if(res.credits_remaining !== undefined) 
                            $('#gemini-credits-display').text(res.credits_remaining);
                    } else { 
                        self.showError(res.message); 
                    }
                },
                error: function(xhr) { 
                    self.handleAPIError(xhr); 
                    $btn.prop('disabled', false).text(txt); 
                }
            });
        },

        // ========= ERROR HANDLING =========

        handleAPIError: function(xhr) {
            var msg = this.config.strings.error_generate;
            var isCreditError = false;
            
            if (xhr.responseJSON) {
                msg = xhr.responseJSON.message;
                if (xhr.status === 402 || xhr.responseJSON.code === 'insufficient_credits') {
                    isCreditError = true;
                }
            }
            
            this.showError(msg, isCreditError);
        },

        showError: function(message, isCreditError) {
            $('#error-message').text(message);
            $('#gemini-error').show();
            
            if (isCreditError) {
                $('#mission-suggestion').show();
                $('#btn-retry').text('Đóng');
            } else {
                $('#mission-suggestion').hide();
                $('#btn-retry').text('Thử Lại');
            }
        },
        
        hideError: function() { $('#gemini-error').hide(); },

        resetToForm: function() { 
            $('#gemini-result').hide(); 
            $('#gemini-unlocked').hide(); 
            $('#gemini-error').hide(); 
            $('.gemini-generator-form').show(); 
        },

        /**
         * Reset toàn bộ trạng thái và xóa cả localStorage.
         */
        resetAll: function() { 
            this.currentImageData       = null; 
            this.currentImageId         = null; 
            this.currentImageSessionId  = null;
            this.removeImage(true); 
            this.resetToForm(); 
        },

        // ========= MISSION LOGIC =========

        startMission: function() {
            var self = this;
            this.hideError();
            
            var $btn = $('.btn-earn-free');
            $btn.prop('disabled', true).text('...');
    
            $.ajax({
                url: rest_url('ai/v1/mission/get'),
                method: 'GET',
                headers: { 'X-WP-Nonce': this.config.nonce },
                success: function(res) {
                    if (res.success) {
                        self.currentMission = res.mission;
                        self.showMissionModal(res.mission);
                    }
                },
                error: function(xhr) { alert('Hiện tại không có nhiệm vụ nào.'); },
                complete: function() { $btn.prop('disabled', false).text('Kiếm Free'); }
            });
        },

        showMissionModal: function(mission) {
            $('#mission-modal').remove();
            var html = `
                <div id="mission-modal" style="display:flex; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.7); align-items:center; justify-content:center;">
                    <div style="background:#fff; padding:25px; border-radius:12px; width:90%; max-width:450px; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.3);">
                        <span class="close-modal" style="position:absolute; top:10px; right:15px; font-size:28px; cursor:pointer;">&times;</span>
                        <h3 style="margin-top:0; color:#0073aa;">${mission.title}</h3>
                        <div style="margin:15px 0; font-size:15px; line-height:1.6; max-height:300px; overflow-y:auto;">${mission.steps}</div>
                        <div style="background:#f0f0f1; padding:15px; border-radius:8px; text-align:center;">
                            <input type="text" id="mission-code" placeholder="Nhập mã 6 số" maxlength="6" style="width:80%; font-size:20px; letter-spacing:3px; text-align:center; padding:8px; border-radius:4px; border:1px solid #ccd0d4;">
                            <div style="margin-top:8px; color:#28a745; font-weight:bold;">+${mission.reward} Credit</div>
                        </div>
                        <button id="btn-verify-mission" style="width:100%; margin-top:15px; padding:12px; background:#0073aa; color:#fff; border:none; border-radius:5px; font-size:16px; cursor:pointer;">Xác Nhận</button>
                    </div>
                </div>
            `;
            $('body').append(html);
            $('.close-modal').click(function(){ $('#mission-modal').hide(); });
        },

        verifyMissionCode: function() {
            var self = this;
            var code = $('#mission-code').val();
            var $btn = $('#btn-verify-mission');
            if(!code || code.length < 6) { alert('Vui lòng nhập mã hợp lệ'); return; }
            
            $btn.prop('disabled', true).text('Đang kiểm tra...');
            
            $.ajax({
                url: rest_url('ai/v1/mission/verify'),
                method: 'POST',
                headers: { 'X-WP-Nonce': this.config.nonce },
                data: { code: code, mission_id: self.currentMission.id },
                success: function(res) {
                    if (res.success) {
                        alert(res.message);
                        $('#mission-modal').hide();
                        $('#gemini-credits-display').text(res.total_credits);
                        
                        if (self.pendingAction === 'generate') {
                            self.generatePreview();
                        } else if (self.pendingAction === 'unlock') {
                            $('#btn-unlock').prop('disabled', false).text('Mở Khóa Ảnh Gốc');
                        }
                        
                        self.pendingAction = null;
                    }
                },
                error: function(xhr) { alert(xhr.responseJSON && xhr.responseJSON.message || 'Mã sai.'); },
                complete: function() { $btn.prop('disabled', false).text('Xác Nhận'); }
            });
        }
    };

    function rest_url(path) { 
        return window.AIGeminiConfig.api_preview.replace('/preview', '') + '/' + path.replace('ai/v1/', ''); 
    }

    $(document).ready(function() { 
        if ($('#ai-gemini-generator').length) AIGeminiGenerator.init(); 
    });

})(jQuery);