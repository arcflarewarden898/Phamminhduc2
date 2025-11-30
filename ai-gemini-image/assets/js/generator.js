/**
 * AI Gemini Image Generator - Generator JavaScript
 */

(function($) {
    'use strict';

    // Main generator class
    var AIGeminiGenerator = {
        config: window.AIGeminiConfig || {},
        currentImageData: null,
        currentImageId: null,
        selectedStyle: 'anime',
        
        init: function() {
            this.bindEvents();
            this.initDropzone();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Upload area click
            $('#upload-area').on('click', function() {
                if (!$('#upload-preview').is(':visible')) {
                    $('#image-input').trigger('click');
                }
            });
            
            // File input change
            $('#image-input').on('change', function(e) {
                self.handleFileSelect(e.target.files[0]);
            });
            
            // Remove image
            $('#remove-image').on('click', function(e) {
                e.stopPropagation();
                self.removeImage();
            });
            
            // Style selection
            $('.style-option').on('click', function() {
                $('.style-option').removeClass('active');
                $(this).addClass('active');
                self.selectedStyle = $(this).data('style');
            });
            
            // Generate button
            $('#btn-generate').on('click', function() {
                self.generatePreview();
            });
            
            // Unlock button
            $('#btn-unlock').on('click', function() {
                self.unlockImage();
            });
            
            // Regenerate button
            $('#btn-regenerate').on('click', function() {
                self.resetToForm();
            });
            
            // New image button
            $('#btn-new').on('click', function() {
                self.resetAll();
            });
            
            // Retry button
            $('#btn-retry').on('click', function() {
                self.hideError();
                self.resetToForm();
            });
        },
        
        initDropzone: function() {
            var self = this;
            var $dropzone = $('#upload-area');
            
            // Prevent default drag behaviors
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
                $dropzone[0].addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });
            
            // Highlight on drag
            ['dragenter', 'dragover'].forEach(function(eventName) {
                $dropzone[0].addEventListener(eventName, function() {
                    $dropzone.addClass('dragover');
                }, false);
            });
            
            // Remove highlight
            ['dragleave', 'drop'].forEach(function(eventName) {
                $dropzone[0].addEventListener(eventName, function() {
                    $dropzone.removeClass('dragover');
                }, false);
            });
            
            // Handle drop
            $dropzone[0].addEventListener('drop', function(e) {
                var files = e.dataTransfer.files;
                if (files.length) {
                    self.handleFileSelect(files[0]);
                }
            }, false);
        },
        
        handleFileSelect: function(file) {
            var self = this;
            
            if (!file) return;
            
            // Validate file type
            var validTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (validTypes.indexOf(file.type) === -1) {
                this.showError(this.config.strings.error_file_type);
                return;
            }
            
            // Validate file size
            if (file.size > this.config.max_file_size) {
                this.showError(this.config.strings.error_file_size);
                return;
            }
            
            // Read file
            var reader = new FileReader();
            reader.onload = function(e) {
                self.currentImageData = e.target.result;
                self.showPreview(e.target.result);
                $('#btn-generate').prop('disabled', false);
            };
            reader.readAsDataURL(file);
        },
        
        showPreview: function(src) {
            $('#preview-image').attr('src', src);
            $('#upload-placeholder').hide();
            $('#upload-preview').show();
        },
        
        removeImage: function() {
            this.currentImageData = null;
            $('#image-input').val('');
            $('#upload-preview').hide();
            $('#upload-placeholder').show();
            $('#btn-generate').prop('disabled', true);
        },
        
        generatePreview: function() {
            var self = this;
            
            if (!this.currentImageData) {
                this.showError(this.config.strings.error_upload);
                return;
            }
            
            // Show loading state
            $('#btn-generate .btn-text').hide();
            $('#btn-generate .btn-loading').show();
            $('#btn-generate').prop('disabled', true);
            
            // Extract base64 data with validation
            var imageData;
            if (this.currentImageData && this.currentImageData.indexOf(',') !== -1) {
                imageData = this.currentImageData.split(',')[1];
            } else if (this.currentImageData) {
                // Assume it's already base64 without data URI prefix
                imageData = this.currentImageData;
            } else {
                this.showError(this.config.strings.error_upload || 'Invalid image data');
                $('#btn-generate .btn-text').show();
                $('#btn-generate .btn-loading').hide();
                $('#btn-generate').prop('disabled', false);
                return;
            }
            
            // Make API request
            $.ajax({
                url: this.config.api_preview,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': this.config.nonce
                },
                contentType: 'application/json',
                data: JSON.stringify({
                    image: imageData,
                    style: this.selectedStyle,
                    prompt: $('#custom-prompt').val()
                }),
                success: function(response) {
                    self.handlePreviewSuccess(response);
                },
                error: function(xhr) {
                    self.handlePreviewError(xhr);
                },
                complete: function() {
                    $('#btn-generate .btn-text').show();
                    $('#btn-generate .btn-loading').hide();
                    $('#btn-generate').prop('disabled', false);
                }
            });
        },
        
        handlePreviewSuccess: function(response) {
            if (response.success && response.preview_url) {
                this.currentImageId = response.image_id;
                
                // Show result
                $('#result-image').attr('src', response.preview_url);
                $('.gemini-generator-form').hide();
                $('#gemini-result').show();
                
                // Update credits display
                if (response.credits_remaining !== undefined) {
                    $('#gemini-credits-display').text(response.credits_remaining);
                }
                
                // Update unlock button state
                if (!response.can_unlock) {
                    $('#btn-unlock')
                        .prop('disabled', true)
                        .text(this.config.strings.error_unlock || 'Not enough credits');
                }
            } else {
                this.showError(response.message || this.config.strings.error_generate);
            }
        },
        
        handlePreviewError: function(xhr) {
            var message = this.config.strings.error_generate;
            
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            
            this.showError(message);
        },
        
        unlockImage: function() {
            var self = this;
            
            if (!this.currentImageId) {
                this.showError('No image to unlock');
                return;
            }
            
            // Show loading
            var $btn = $('#btn-unlock');
            var originalText = $btn.text();
            $btn.prop('disabled', true).text(this.config.strings.unlocking || 'Unlocking...');
            
            $.ajax({
                url: this.config.api_unlock,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': this.config.nonce
                },
                contentType: 'application/json',
                data: JSON.stringify({
                    image_id: this.currentImageId
                }),
                success: function(response) {
                    self.handleUnlockSuccess(response);
                },
                error: function(xhr) {
                    self.handleUnlockError(xhr);
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        handleUnlockSuccess: function(response) {
            if (response.success && response.full_url) {
                // Show unlocked image
                $('#unlocked-image').attr('src', response.full_url);
                $('#btn-download').attr('href', response.full_url);
                
                $('#gemini-result').hide();
                $('#gemini-unlocked').show();
                
                // Update credits display
                if (response.credits_remaining !== undefined) {
                    $('#gemini-credits-display').text(response.credits_remaining);
                }
            } else {
                this.showError(response.message || this.config.strings.error_unlock);
            }
        },
        
        handleUnlockError: function(xhr) {
            var message = this.config.strings.error_unlock;
            
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            
            this.showError(message);
        },
        
        showError: function(message) {
            $('#error-message').text(message);
            $('#gemini-error').show();
        },
        
        hideError: function() {
            $('#gemini-error').hide();
        },
        
        resetToForm: function() {
            $('#gemini-result').hide();
            $('#gemini-unlocked').hide();
            $('#gemini-error').hide();
            $('.gemini-generator-form').show();
        },
        
        resetAll: function() {
            this.currentImageData = null;
            this.currentImageId = null;
            this.removeImage();
            this.resetToForm();
        },
        
        updateCredits: function() {
            var self = this;
            
            $.ajax({
                url: this.config.api_credit,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': this.config.nonce
                },
                success: function(response) {
                    if (response.credits !== undefined) {
                        $('#gemini-credits-display').text(response.credits);
                    }
                }
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        if ($('#ai-gemini-generator').length) {
            AIGeminiGenerator.init();
        }
    });
    
})(jQuery);
