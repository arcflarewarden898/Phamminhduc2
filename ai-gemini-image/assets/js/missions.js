/**
 * AI Gemini Image Generator - Missions JavaScript
 */

(function($) {
    'use strict';

    // Current mission data
    var currentMission = null;
    var countdownInterval = null;
    var countdownSeconds = 900; // 15 minutes default

    /**
     * Initialize missions
     */
    function init() {
        bindEvents();
        loadHistory();
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Do mission button
        $(document).on('click', '.btn-do-mission', function(e) {
            e.preventDefault();
            var missionId = $(this).data('mission-id');
            openMissionModal(missionId);
        });

        // Close modal
        $(document).on('click', '.modal-close, .modal-overlay', function() {
            closeModal();
        });

        // Verify code button
        $(document).on('click', '.btn-verify-code', function() {
            verifyCode();
        });

        // Enter key on code input
        $(document).on('keypress', '.mission-code-input', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                verifyCode();
            }
        });

        // Escape key to close modal
        $(document).on('keyup', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    }

    /**
     * Open mission modal
     */
    function openMissionModal(missionId) {
        var $card = $('.mission-card[data-mission-id="' + missionId + '"]');
        
        if (!$card.length) {
            return;
        }

        currentMission = {
            id: missionId,
            title: $card.find('.mission-title').text(),
            description: $card.find('.mission-description').text()
        };

        // Get mission details via AJAX
        $.ajax({
            url: AIGeminiMissions.ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_gemini_get_missions',
                nonce: AIGeminiMissions.nonce
            },
            success: function(response) {
                if (response.success) {
                    var mission = null;
                    for (var i = 0; i < response.data.missions.length; i++) {
                        if (response.data.missions[i].id === missionId) {
                            mission = response.data.missions[i];
                            break;
                        }
                    }

                    if (mission) {
                        currentMission = mission;
                        showModal();
                    }
                }
            }
        });

        // Show modal immediately with basic info
        showModal();
    }

    /**
     * Show modal with current mission data
     */
    function showModal() {
        var $modal = $('#mission-modal');
        
        $modal.find('.modal-title').text(currentMission.title);
        $modal.find('.mission-description').text(currentMission.description || '');
        
        if (currentMission.code_hint) {
            $modal.find('.mission-hint').html('<strong>' + AIGeminiMissions.strings.hint + ':</strong> ' + currentMission.code_hint).show();
        } else {
            $modal.find('.mission-hint').hide();
        }

        if (currentMission.target_url) {
            $modal.find('.target-link-container').show();
            $modal.find('.btn-visit-target').attr('href', currentMission.target_url);
        } else {
            $modal.find('.target-link-container').hide();
        }

        // Reset input and results
        $modal.find('.mission-code-input').val('');
        $modal.find('.verification-result').hide().removeClass('success error');
        $modal.find('.btn-verify-code').prop('disabled', false).text(AIGeminiMissions.strings.verify || 'Verify');

        // Start countdown
        startCountdown();

        // Show modal
        $modal.fadeIn(200);
        $modal.find('.mission-code-input').focus();
    }

    /**
     * Close modal
     */
    function closeModal() {
        var $modal = $('#mission-modal');
        $modal.fadeOut(200);
        stopCountdown();
        currentMission = null;
    }

    /**
     * Start countdown timer
     */
    function startCountdown() {
        stopCountdown();
        countdownSeconds = 900; // 15 minutes
        updateCountdownDisplay();
        
        countdownInterval = setInterval(function() {
            countdownSeconds--;
            updateCountdownDisplay();
            
            if (countdownSeconds <= 0) {
                stopCountdown();
                // Show expired message
                var $result = $('.verification-result');
                $result.addClass('error').removeClass('success');
                $result.find('.result-message').text('Time expired. Please get a new code.');
                $result.show();
            }
        }, 1000);

        $('.countdown-timer').show();
    }

    /**
     * Stop countdown
     */
    function stopCountdown() {
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
        $('.countdown-timer').hide();
    }

    /**
     * Update countdown display
     */
    function updateCountdownDisplay() {
        var mins = Math.floor(countdownSeconds / 60);
        var secs = countdownSeconds % 60;
        var display = mins + ':' + (secs < 10 ? '0' : '') + secs;
        $('.timer-value').text(display);
    }

    /**
     * Verify code
     */
    function verifyCode() {
        if (!currentMission) {
            return;
        }

        var $input = $('.mission-code-input');
        var code = $input.val().trim().toUpperCase();

        if (!code) {
            showResult('error', AIGeminiMissions.strings.enter_code || 'Please enter a code.');
            $input.focus();
            return;
        }

        var $btn = $('.btn-verify-code');
        $btn.prop('disabled', true).text(AIGeminiMissions.strings.verifying || 'Verifying...');

        $.ajax({
            url: AIGeminiMissions.ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_gemini_verify_mission_code',
                nonce: AIGeminiMissions.nonce,
                mission_id: currentMission.id,
                code: code
            },
            success: function(response) {
                if (response.success) {
                    showResult('success', response.data.message);
                    stopCountdown();
                    
                    // Update credit balance
                    if (response.data.new_balance !== undefined) {
                        $('.credit-balance').text(response.data.new_balance.toLocaleString());
                    }

                    // Mark mission as completed
                    var $card = $('.mission-card[data-mission-id="' + currentMission.id + '"]');
                    $card.removeClass('eligible').addClass('not-eligible');
                    $card.find('.btn-do-mission').replaceWith(
                        '<div class="mission-status not-eligible"><span class="status-message">Completed! +' + 
                        response.data.credits_earned + ' credits</span></div>'
                    );

                    // Refresh history
                    loadHistory();

                    // Close modal after delay
                    setTimeout(function() {
                        closeModal();
                    }, 2000);
                } else {
                    showResult('error', response.data.message);
                    $btn.prop('disabled', false).text(AIGeminiMissions.strings.verify || 'Verify');
                }
            },
            error: function() {
                showResult('error', AIGeminiMissions.strings.error || 'An error occurred. Please try again.');
                $btn.prop('disabled', false).text(AIGeminiMissions.strings.verify || 'Verify');
            }
        });
    }

    /**
     * Show result message
     */
    function showResult(type, message) {
        var $result = $('.verification-result');
        $result.removeClass('success error').addClass(type);
        $result.find('.result-message').text(message);
        $result.show();
    }

    /**
     * Load mission history
     */
    function loadHistory() {
        var $container = $('#mission-history-list');
        
        if (!$container.length) {
            return;
        }

        $.ajax({
            url: AIGeminiMissions.ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_gemini_get_mission_history',
                nonce: AIGeminiMissions.nonce,
                page: 1,
                per_page: 10
            },
            success: function(response) {
                if (response.success) {
                    renderHistory(response.data.history);
                } else {
                    $container.html('<p class="history-empty">Failed to load history.</p>');
                }
            },
            error: function() {
                $container.html('<p class="history-empty">Failed to load history.</p>');
            }
        });
    }

    /**
     * Render history list
     */
    function renderHistory(history) {
        var $container = $('#mission-history-list');

        if (!history || history.length === 0) {
            $container.html('<p class="history-empty">No completed missions yet.</p>');
            return;
        }

        var html = '';
        for (var i = 0; i < history.length; i++) {
            var item = history[i];
            var iconClass = 'type-' + (item.mission_type || 'code_collect');
            var icon = '🔍';
            
            if (item.mission_type === 'social_share') {
                icon = '📢';
            } else if (item.mission_type === 'daily_login') {
                icon = '📅';
            }

            html += '<div class="history-item">' +
                '<div class="history-info">' +
                    '<div class="history-icon ' + iconClass + '">' + icon + '</div>' +
                    '<div class="history-details">' +
                        '<h4>' + escapeHtml(item.mission_title) + '</h4>' +
                        '<span class="date">' + escapeHtml(item.completed_at_formatted) + '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="history-credits">+' + item.credits_earned + '</div>' +
            '</div>';
        }

        $container.html(html);
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
