<?php
/**
 * AI Gemini Image Generator - Generator Shortcode
 * 
 * Shortcode for the image generation form.
 */

if (!defined('ABSPATH')) exit;

/**
 * Register generator shortcode
 */
function ai_gemini_register_generator_shortcode() {
    add_shortcode('ai_gemini_generator', 'ai_gemini_generator_shortcode');
}
add_action('init', 'ai_gemini_register_generator_shortcode');

/**
 * Render generator shortcode
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function ai_gemini_generator_shortcode($atts) {
    $atts = shortcode_atts([
        'show_credits' => 'true',
        'show_styles' => 'true',
        'default_style' => 'anime',
    ], $atts, 'ai_gemini_generator');
    
    // Enqueue styles and scripts
    ai_gemini_enqueue_generator_assets();
    
    // Get user info
    $user_id = get_current_user_id();
    $credits = ai_gemini_get_credit($user_id ?: null);
    $has_trial = !ai_gemini_has_used_trial($user_id ?: null);
    $preview_cost = (int) get_option('ai_gemini_preview_credit', 0);
    $unlock_cost = (int) get_option('ai_gemini_unlock_credit', 1);
    
    // Get available styles
    $styles = AI_GEMINI_API::get_styles();
    
    ob_start();
    ?>
    <div class="ai-gemini-generator" id="ai-gemini-generator">
        <?php if ($atts['show_credits'] === 'true') : ?>
        <div class="gemini-credits-bar">
            <div class="credits-info">
                <span class="credits-label"><?php esc_html_e('Your Credits:', 'ai-gemini-image'); ?></span>
                <span class="credits-value" id="gemini-credits-display"><?php echo esc_html(number_format_i18n($credits)); ?></span>
                <?php if ($has_trial && $credits === 0) : ?>
                    <span class="free-trial-badge"><?php esc_html_e('Free trial available!', 'ai-gemini-image'); ?></span>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url(home_url('/buy-credit')); ?>" class="btn-buy-credits">
                <?php esc_html_e('+ Buy Credits', 'ai-gemini-image'); ?>
            </a>
        </div>
        <?php endif; ?>
        
        <div class="gemini-generator-form">
            <div class="upload-section">
                <div class="upload-area" id="upload-area">
                    <div class="upload-placeholder">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        <p><?php esc_html_e('Drop your photo here or click to upload', 'ai-gemini-image'); ?></p>
                        <span class="upload-hint"><?php esc_html_e('Supports: JPG, PNG, WebP (max 10MB)', 'ai-gemini-image'); ?></span>
                    </div>
                    <div class="upload-preview" id="upload-preview" style="display: none;">
                        <img src="" alt="Preview" id="preview-image">
                        <button type="button" class="remove-image" id="remove-image">&times;</button>
                    </div>
                    <input type="file" id="image-input" accept="image/jpeg,image/png,image/webp" style="display: none;">
                </div>
            </div>
            
            <?php if ($atts['show_styles'] === 'true') : ?>
            <div class="style-section">
                <label><?php esc_html_e('Choose Style:', 'ai-gemini-image'); ?></label>
                <div class="style-options" id="style-options">
                    <?php foreach ($styles as $style_id => $style_name) : ?>
                        <div class="style-option <?php echo $style_id === $atts['default_style'] ? 'active' : ''; ?>" 
                             data-style="<?php echo esc_attr($style_id); ?>">
                            <span class="style-name"><?php echo esc_html($style_name); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="prompt-section">
                <label for="custom-prompt"><?php esc_html_e('Custom Prompt (Optional):', 'ai-gemini-image'); ?></label>
                <p class="prompt-description"><?php esc_html_e('Add additional instructions to customize your image transformation.', 'ai-gemini-image'); ?></p>
                <textarea id="custom-prompt" placeholder="<?php esc_attr_e('Add additional instructions for the AI...', 'ai-gemini-image'); ?>"></textarea>
            </div>
            
            <div class="action-section">
                <button type="button" class="btn-generate" id="btn-generate" disabled>
                    <span class="btn-text"><?php esc_html_e('Generate Preview', 'ai-gemini-image'); ?></span>
                    <span class="btn-loading" style="display: none;">
                        <span class="spinner"></span>
                        <?php esc_html_e('Generating...', 'ai-gemini-image'); ?>
                    </span>
                </button>
                <?php if ($preview_cost > 0) : ?>
                    <p class="cost-info"><?php printf(esc_html__('Cost: %d credit(s)', 'ai-gemini-image'), $preview_cost); ?></p>
                <?php else : ?>
                    <p class="cost-info"><?php esc_html_e('Preview is free!', 'ai-gemini-image'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="gemini-result" id="gemini-result" style="display: none;">
            <div class="result-header">
                <h3><?php esc_html_e('Your Generated Image', 'ai-gemini-image'); ?></h3>
            </div>
            <div class="result-image">
                <img src="" alt="Generated Image" id="result-image">
                <div class="watermark-notice">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    <?php esc_html_e('Preview includes watermark. Unlock for full quality.', 'ai-gemini-image'); ?>
                </div>
            </div>
            <div class="result-actions">
                <button type="button" class="btn-unlock" id="btn-unlock">
                    <?php printf(esc_html__('Unlock Full Image (%d credits)', 'ai-gemini-image'), $unlock_cost); ?>
                </button>
                <button type="button" class="btn-regenerate" id="btn-regenerate">
                    <?php esc_html_e('Try Different Style', 'ai-gemini-image'); ?>
                </button>
            </div>
        </div>
        
        <div class="gemini-unlocked" id="gemini-unlocked" style="display: none;">
            <div class="unlocked-header">
                <h3><?php esc_html_e('🎉 Image Unlocked!', 'ai-gemini-image'); ?></h3>
            </div>
            <div class="unlocked-image">
                <img src="" alt="Unlocked Image" id="unlocked-image">
            </div>
            <div class="unlocked-actions">
                <a href="#" class="btn-download" id="btn-download" download>
                    <?php esc_html_e('Download Image', 'ai-gemini-image'); ?>
                </a>
                <button type="button" class="btn-new" id="btn-new">
                    <?php esc_html_e('Create Another', 'ai-gemini-image'); ?>
                </button>
            </div>
        </div>
        
        <div class="gemini-error" id="gemini-error" style="display: none;">
            <p id="error-message"></p>
            <button type="button" class="btn-retry" id="btn-retry"><?php esc_html_e('Try Again', 'ai-gemini-image'); ?></button>
        </div>
    </div>
    <?php
    
    return ob_get_clean();
}

/**
 * Enqueue generator assets
 */
function ai_gemini_enqueue_generator_assets() {
    wp_enqueue_style(
        'ai-gemini-generator',
        AI_GEMINI_PLUGIN_URL . 'assets/css/generator.css',
        [],
        AI_GEMINI_VERSION
    );
    
    wp_enqueue_script(
        'ai-gemini-generator',
        AI_GEMINI_PLUGIN_URL . 'assets/js/generator.js',
        ['jquery'],
        AI_GEMINI_VERSION,
        true
    );
    
    wp_localize_script('ai-gemini-generator', 'AIGeminiConfig', [
        'api_preview' => rest_url('ai/v1/preview'),
        'api_unlock' => rest_url('ai/v1/unlock'),
        'api_credit' => rest_url('ai/v1/credit'),
        'nonce' => wp_create_nonce('wp_rest'),
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'strings' => [
            'error_file_size' => __('File is too large. Maximum size is 10MB.', 'ai-gemini-image'),
            'error_file_type' => __('Invalid file type. Please upload JPG, PNG, or WebP.', 'ai-gemini-image'),
            'error_upload' => __('Failed to upload image. Please try again.', 'ai-gemini-image'),
            'error_generate' => __('Failed to generate image. Please try again.', 'ai-gemini-image'),
            'error_unlock' => __('Failed to unlock image. Please try again.', 'ai-gemini-image'),
            'generating' => __('Generating...', 'ai-gemini-image'),
            'unlocking' => __('Unlocking...', 'ai-gemini-image'),
        ],
    ]);
}
