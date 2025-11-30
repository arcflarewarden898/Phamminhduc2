<?php
/**
 * AI Gemini Image Generator - Mission Shortcodes
 * 
 * Shortcodes for displaying missions on the frontend.
 */

if (!defined('ABSPATH')) exit;

/**
 * Register mission shortcodes
 */
function ai_gemini_register_mission_shortcodes() {
    add_shortcode('ai_gemini_missions', 'ai_gemini_missions_shortcode');
    add_shortcode('ai_gemini_mission_code', 'ai_gemini_mission_code_shortcode');
}
add_action('init', 'ai_gemini_register_mission_shortcodes');

/**
 * Missions list shortcode
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function ai_gemini_missions_shortcode($atts) {
    $atts = shortcode_atts([
        'show_history' => 'yes',
        'columns' => 2,
    ], $atts, 'ai_gemini_missions');
    
    // Enqueue styles and scripts
    wp_enqueue_style(
        'ai-gemini-missions',
        AI_GEMINI_PLUGIN_URL . 'assets/css/missions.css',
        [],
        AI_GEMINI_VERSION
    );
    
    wp_enqueue_script(
        'ai-gemini-missions',
        AI_GEMINI_PLUGIN_URL . 'assets/js/missions.js',
        ['jquery'],
        AI_GEMINI_VERSION,
        true
    );
    
    wp_localize_script('ai-gemini-missions', 'AIGeminiMissions', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ai_gemini_missions_nonce'),
        'strings' => [
            'loading' => __('Loading...', 'ai-gemini-image'),
            'verifying' => __('Verifying...', 'ai-gemini-image'),
            'success' => __('Success!', 'ai-gemini-image'),
            'error' => __('Error', 'ai-gemini-image'),
            'copied' => __('Copied!', 'ai-gemini-image'),
            'enter_code' => __('Enter the code you found', 'ai-gemini-image'),
            'code_placeholder' => __('e.g., CODE-123456', 'ai-gemini-image'),
            'verify' => __('Verify', 'ai-gemini-image'),
            'close' => __('Close', 'ai-gemini-image'),
            'time_remaining' => __('Time remaining:', 'ai-gemini-image'),
        ],
    ]);
    
    $user_id = get_current_user_id();
    $missions = ai_gemini_get_active_missions();
    $credits = ai_gemini_get_credit($user_id ?: null);
    
    ob_start();
    ?>
    <div class="ai-gemini-missions-container" data-columns="<?php echo esc_attr($atts['columns']); ?>">
        <div class="missions-header">
            <h2><?php esc_html_e('Earn Free Credits', 'ai-gemini-image'); ?></h2>
            <p><?php esc_html_e('Complete missions to earn credits for free!', 'ai-gemini-image'); ?></p>
            <div class="current-balance">
                <?php esc_html_e('Your Balance:', 'ai-gemini-image'); ?>
                <strong class="credit-balance"><?php echo esc_html(number_format_i18n($credits)); ?></strong>
                <?php esc_html_e('credits', 'ai-gemini-image'); ?>
            </div>
        </div>
        
        <?php if (empty($missions)) : ?>
            <div class="no-missions">
                <p><?php esc_html_e('No missions available at this time. Check back later!', 'ai-gemini-image'); ?></p>
            </div>
        <?php else : ?>
            <div class="missions-grid" style="--columns: <?php echo esc_attr($atts['columns']); ?>;">
                <?php foreach ($missions as $mission) : ?>
                    <?php 
                    $eligibility = ai_gemini_check_mission_eligible($mission->id, $user_id ?: null);
                    $completion_count = ai_gemini_get_mission_completion_count($mission->id);
                    $progress = $mission->max_completions > 0 ? ($completion_count / $mission->max_completions) * 100 : 0;
                    ?>
                    <div class="mission-card <?php echo $eligibility['eligible'] ? 'eligible' : 'not-eligible'; ?>" data-mission-id="<?php echo esc_attr($mission->id); ?>">
                        <div class="mission-type-icon type-<?php echo esc_attr($mission->mission_type); ?>">
                            <?php if ($mission->mission_type === 'code_collect') : ?>
                                <span class="dashicons dashicons-search"></span>
                            <?php elseif ($mission->mission_type === 'social_share') : ?>
                                <span class="dashicons dashicons-share"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-calendar"></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mission-content">
                            <h3 class="mission-title"><?php echo esc_html($mission->title); ?></h3>
                            <p class="mission-description"><?php echo wp_kses_post($mission->description); ?></p>
                            
                            <div class="mission-reward">
                                <span class="reward-icon">🎁</span>
                                <span class="reward-credits">+<?php echo esc_html($mission->reward_credits); ?></span>
                                <span class="reward-label"><?php esc_html_e('credits', 'ai-gemini-image'); ?></span>
                            </div>
                            
                            <?php if ($mission->max_completions > 0) : ?>
                                <div class="mission-progress">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo esc_attr(min(100, $progress)); ?>%;"></div>
                                    </div>
                                    <span class="progress-text">
                                        <?php printf(
                                            esc_html__('%d / %d completed', 'ai-gemini-image'),
                                            $completion_count,
                                            $mission->max_completions
                                        ); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($eligibility['eligible']) : ?>
                                <button type="button" class="btn-do-mission" data-mission-id="<?php echo esc_attr($mission->id); ?>">
                                    <?php esc_html_e('Do Mission', 'ai-gemini-image'); ?>
                                </button>
                            <?php else : ?>
                                <div class="mission-status not-eligible">
                                    <span class="status-message"><?php echo esc_html($eligibility['reason']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($atts['show_history'] === 'yes') : ?>
            <div class="mission-history">
                <h3><?php esc_html_e('Your Mission History', 'ai-gemini-image'); ?></h3>
                <div class="history-container" id="mission-history-list">
                    <p class="loading"><?php esc_html_e('Loading history...', 'ai-gemini-image'); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Mission Modal -->
    <div class="ai-gemini-modal" id="mission-modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <button type="button" class="modal-close">&times;</button>
            <div class="modal-header">
                <h3 class="modal-title"></h3>
            </div>
            <div class="modal-body">
                <div class="mission-instructions">
                    <p class="mission-description"></p>
                    <p class="mission-hint"></p>
                    
                    <div class="target-link-container" style="display: none;">
                        <p><?php esc_html_e('Click the button below to visit the website and find the code:', 'ai-gemini-image'); ?></p>
                        <a href="#" class="btn-visit-target" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Visit Website', 'ai-gemini-image'); ?>
                            <span class="dashicons dashicons-external"></span>
                        </a>
                    </div>
                </div>
                
                <div class="code-input-container">
                    <label for="mission-code-input"><?php esc_html_e('Enter the code you found:', 'ai-gemini-image'); ?></label>
                    <input type="text" id="mission-code-input" class="mission-code-input" placeholder="<?php esc_attr_e('e.g., CODE-123456', 'ai-gemini-image'); ?>" style="text-transform: uppercase;">
                    <button type="button" class="btn-verify-code"><?php esc_html_e('Verify Code', 'ai-gemini-image'); ?></button>
                </div>
                
                <div class="verification-result" style="display: none;">
                    <div class="result-message"></div>
                </div>
                
                <div class="countdown-timer" style="display: none;">
                    <span class="timer-label"><?php esc_html_e('Time remaining:', 'ai-gemini-image'); ?></span>
                    <span class="timer-value">15:00</span>
                </div>
            </div>
        </div>
    </div>
    <?php
    
    return ob_get_clean();
}

/**
 * Mission code display shortcode (for embedding on target websites)
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function ai_gemini_mission_code_shortcode($atts) {
    $atts = shortcode_atts([
        'mission_id' => 0,
        'code_id' => 0,
        'style' => 'default',
    ], $atts, 'ai_gemini_mission_code');
    
    $mission_id = absint($atts['mission_id']);
    $code_id = absint($atts['code_id']);
    
    if (!$mission_id && !$code_id) {
        return '<p>' . esc_html__('Invalid mission or code ID.', 'ai-gemini-image') . '</p>';
    }
    
    global $wpdb;
    $table_codes = $wpdb->prefix . 'ai_gemini_mission_codes';
    
    if ($code_id) {
        $code_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_codes WHERE id = %d AND is_active = 1",
            $code_id
        ));
    } else {
        $code_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_codes 
             WHERE mission_id = %d AND is_active = 1 
             AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1",
            $mission_id
        ));
    }
    
    if (!$code_record) {
        return '<p>' . esc_html__('No active code available.', 'ai-gemini-image') . '</p>';
    }
    
    $totp_info = ai_gemini_get_current_totp($code_record->totp_secret);
    
    // Inline styles for standalone embedding
    $style_class = 'style-' . sanitize_html_class($atts['style']);
    
    ob_start();
    ?>
    <div class="ai-gemini-mission-code-display <?php echo esc_attr($style_class); ?>" data-mission-id="<?php echo esc_attr($mission_id); ?>" data-code-id="<?php echo esc_attr($code_record->id); ?>">
        <style>
            .ai-gemini-mission-code-display {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 12px;
                padding: 24px;
                text-align: center;
                color: white;
                max-width: 400px;
                margin: 20px auto;
                box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            }
            .ai-gemini-mission-code-display .code-label {
                font-size: 14px;
                opacity: 0.9;
                margin-bottom: 8px;
            }
            .ai-gemini-mission-code-display .code-value {
                font-family: 'Courier New', monospace;
                font-size: 28px;
                font-weight: bold;
                letter-spacing: 3px;
                background: rgba(255,255,255,0.2);
                padding: 16px 24px;
                border-radius: 8px;
                display: inline-block;
                margin: 12px 0;
                user-select: all;
            }
            .ai-gemini-mission-code-display .code-expires {
                font-size: 13px;
                opacity: 0.85;
            }
            .ai-gemini-mission-code-display .expires-countdown {
                font-weight: bold;
            }
            .ai-gemini-mission-code-display .btn-copy {
                background: white;
                color: #667eea;
                border: none;
                padding: 10px 24px;
                border-radius: 6px;
                font-weight: 600;
                cursor: pointer;
                margin-top: 12px;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .ai-gemini-mission-code-display .btn-copy:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            }
            .ai-gemini-mission-code-display.style-minimal {
                background: #f8f9fa;
                color: #333;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .ai-gemini-mission-code-display.style-minimal .code-value {
                background: #e9ecef;
                color: #495057;
            }
            .ai-gemini-mission-code-display.style-minimal .btn-copy {
                background: #667eea;
                color: white;
            }
        </style>
        
        <div class="code-label"><?php esc_html_e('Your Code:', 'ai-gemini-image'); ?></div>
        <div class="code-value" id="live-code-<?php echo esc_attr($code_record->id); ?>">
            <?php echo esc_html($code_record->code . '-' . $totp_info['code']); ?>
        </div>
        <div class="code-expires">
            <?php esc_html_e('Expires in:', 'ai-gemini-image'); ?>
            <span class="expires-countdown" id="countdown-<?php echo esc_attr($code_record->id); ?>">
                <?php echo esc_html(floor($totp_info['expires_in'] / 60) . ':' . str_pad($totp_info['expires_in'] % 60, 2, '0', STR_PAD_LEFT)); ?>
            </span>
        </div>
        <button type="button" class="btn-copy" onclick="copyCode<?php echo esc_attr($code_record->id); ?>()">
            <?php esc_html_e('Copy Code', 'ai-gemini-image'); ?>
        </button>
    </div>
    
    <script>
    (function() {
        var codeId = <?php echo (int) $code_record->id; ?>;
        var missionId = <?php echo (int) $mission_id; ?>;
        var expiresIn = <?php echo (int) $totp_info['expires_in']; ?>;
        var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
        
        // Countdown timer
        function updateCountdown() {
            if (expiresIn <= 0) {
                refreshCode();
                return;
            }
            
            expiresIn--;
            var mins = Math.floor(expiresIn / 60);
            var secs = expiresIn % 60;
            var el = document.getElementById('countdown-' + codeId);
            if (el) {
                el.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
            }
        }
        
        // Refresh code from server
        function refreshCode() {
            fetch(ajaxUrl + '?action=ai_gemini_get_totp_code&mission_id=' + missionId + '&code_id=' + codeId)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        var codeEl = document.getElementById('live-code-' + codeId);
                        if (codeEl) {
                            codeEl.textContent = data.data.full_code;
                        }
                        expiresIn = data.data.expires_in;
                    }
                });
        }
        
        // Start countdown
        setInterval(updateCountdown, 1000);
        
        // Refresh code every 30 seconds
        setInterval(refreshCode, 30000);
    })();
    
    function copyCode<?php echo esc_attr($code_record->id); ?>() {
        var codeEl = document.getElementById('live-code-<?php echo esc_attr($code_record->id); ?>');
        if (codeEl) {
            var code = codeEl.textContent.trim();
            navigator.clipboard.writeText(code).then(function() {
                var btn = event.target;
                var originalText = btn.textContent;
                btn.textContent = '<?php echo esc_js(__('Copied!', 'ai-gemini-image')); ?>';
                setTimeout(function() {
                    btn.textContent = originalText;
                }, 2000);
            });
        }
    }
    </script>
    <?php
    
    return ob_get_clean();
}

/**
 * Enqueue mission styles on frontend
 */
function ai_gemini_enqueue_mission_styles() {
    // Only enqueue if shortcode is used on the page
    global $post;
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'ai_gemini_missions') || has_shortcode($post->post_content, 'ai_gemini_mission_code'))) {
        wp_enqueue_style('dashicons');
    }
}
add_action('wp_enqueue_scripts', 'ai_gemini_enqueue_mission_styles');
