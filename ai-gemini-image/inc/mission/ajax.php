<?php
/**
 * AI Gemini Image Generator - Mission AJAX Handlers
 * 
 * AJAX handlers for mission operations.
 */

if (!defined('ABSPATH')) exit;

/**
 * Register AJAX actions
 */
add_action('wp_ajax_ai_gemini_get_missions', 'ai_gemini_ajax_get_missions');
add_action('wp_ajax_nopriv_ai_gemini_get_missions', 'ai_gemini_ajax_get_missions');

add_action('wp_ajax_ai_gemini_verify_mission_code', 'ai_gemini_ajax_verify_mission_code');
add_action('wp_ajax_nopriv_ai_gemini_verify_mission_code', 'ai_gemini_ajax_verify_mission_code');

add_action('wp_ajax_ai_gemini_get_mission_history', 'ai_gemini_ajax_get_mission_history');
add_action('wp_ajax_nopriv_ai_gemini_get_mission_history', 'ai_gemini_ajax_get_mission_history');

add_action('wp_ajax_ai_gemini_get_totp_code', 'ai_gemini_ajax_get_totp_code');
add_action('wp_ajax_nopriv_ai_gemini_get_totp_code', 'ai_gemini_ajax_get_totp_code');

/**
 * AJAX handler: Get active missions
 */
function ai_gemini_ajax_get_missions() {
    check_ajax_referer('ai_gemini_missions_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $missions = ai_gemini_get_active_missions();
    
    $formatted_missions = [];
    
    foreach ($missions as $mission) {
        $eligibility = ai_gemini_check_mission_eligible($mission->id, $user_id ?: null);
        $completion_count = ai_gemini_get_mission_completion_count($mission->id);
        
        $formatted_missions[] = [
            'id' => (int) $mission->id,
            'mission_key' => $mission->mission_key,
            'title' => esc_html($mission->title),
            'description' => wp_kses_post($mission->description),
            'reward_credits' => (int) $mission->reward_credits,
            'mission_type' => $mission->mission_type,
            'target_url' => esc_url($mission->target_url),
            'code_hint' => wp_kses_post($mission->code_hint),
            'max_completions' => (int) $mission->max_completions,
            'current_completions' => $completion_count,
            'cooldown_hours' => (int) $mission->cooldown_hours,
            'eligible' => $eligibility['eligible'],
            'eligibility_reason' => $eligibility['reason'],
            'next_available' => $eligibility['next_available'],
        ];
    }
    
    wp_send_json_success([
        'missions' => $formatted_missions,
        'user_credits' => ai_gemini_get_credit($user_id ?: null),
    ]);
}

/**
 * AJAX handler: Verify mission code and complete mission
 */
function ai_gemini_ajax_verify_mission_code() {
    check_ajax_referer('ai_gemini_missions_nonce', 'nonce');
    
    $mission_id = isset($_POST['mission_id']) ? absint($_POST['mission_id']) : 0;
    $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
    
    if (!$mission_id) {
        wp_send_json_error([
            'message' => __('Invalid mission ID.', 'ai-gemini-image'),
        ]);
    }
    
    if (empty($code)) {
        wp_send_json_error([
            'message' => __('Please enter a code.', 'ai-gemini-image'),
        ]);
    }
    
    $user_id = get_current_user_id();
    
    // Check eligibility first
    $eligibility = ai_gemini_check_mission_eligible($mission_id, $user_id ?: null);
    if (!$eligibility['eligible']) {
        wp_send_json_error([
            'message' => $eligibility['reason'],
        ]);
    }
    
    // Verify code
    $verification = ai_gemini_verify_mission_code($mission_id, $code);
    
    if (!$verification['valid']) {
        wp_send_json_error([
            'message' => $verification['message'],
        ]);
    }
    
    // Complete mission
    $completion = ai_gemini_complete_mission($mission_id, $user_id ?: null, $code);
    
    if (!$completion['success']) {
        wp_send_json_error([
            'message' => $completion['message'],
        ]);
    }
    
    wp_send_json_success([
        'message' => $completion['message'],
        'credits_earned' => $completion['credits_earned'],
        'new_balance' => ai_gemini_get_credit($user_id ?: null),
    ]);
}

/**
 * AJAX handler: Get mission history
 */
function ai_gemini_ajax_get_mission_history() {
    check_ajax_referer('ai_gemini_missions_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? min(absint($_POST['per_page']), 50) : 20;
    $offset = ($page - 1) * $per_page;
    
    $history = ai_gemini_get_user_mission_history($user_id ?: null, $per_page, $offset);
    
    $formatted_history = [];
    foreach ($history as $item) {
        $formatted_history[] = [
            'id' => (int) $item->id,
            'mission_id' => (int) $item->mission_id,
            'mission_title' => esc_html($item->mission_title),
            'mission_type' => $item->mission_type,
            'credits_earned' => (int) $item->credits_earned,
            'completed_at' => $item->completed_at,
            'completed_at_formatted' => date_i18n(
                get_option('date_format') . ' ' . get_option('time_format'),
                strtotime($item->completed_at)
            ),
        ];
    }
    
    wp_send_json_success([
        'history' => $formatted_history,
        'page' => $page,
        'per_page' => $per_page,
    ]);
}

/**
 * AJAX handler: Get current TOTP code (for website display)
 * 
 * Note: This endpoint is designed to be accessible from external websites
 * where the mission code shortcode is embedded. It only returns the
 * time-based code which changes every 15 minutes.
 */
function ai_gemini_ajax_get_totp_code() {
    $mission_id = isset($_GET['mission_id']) ? absint($_GET['mission_id']) : 0;
    $code_id = isset($_GET['code_id']) ? absint($_GET['code_id']) : 0;
    
    if (!$mission_id && !$code_id) {
        wp_send_json_error([
            'message' => __('Invalid request.', 'ai-gemini-image'),
        ]);
    }
    
    // Basic rate limiting using transient
    $ip = ai_gemini_get_client_ip();
    $rate_key = 'totp_rate_' . md5($ip);
    $rate_count = (int) get_transient($rate_key);
    
    if ($rate_count > 60) { // Max 60 requests per minute
        wp_send_json_error([
            'message' => __('Too many requests. Please wait.', 'ai-gemini-image'),
        ]);
    }
    
    set_transient($rate_key, $rate_count + 1, 60);
    
    global $wpdb;
    $table_codes = $wpdb->prefix . 'ai_gemini_mission_codes';
    
    if ($code_id) {
        $code_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_codes WHERE id = %d AND is_active = 1",
            $code_id
        ));
    } else {
        // Get first active code for mission
        $code_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_codes 
             WHERE mission_id = %d AND is_active = 1 
             AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1",
            $mission_id
        ));
    }
    
    if (!$code_record) {
        wp_send_json_error([
            'message' => __('No active code found.', 'ai-gemini-image'),
        ]);
    }
    
    $totp_info = ai_gemini_get_current_totp($code_record->totp_secret);
    
    wp_send_json_success([
        'static_code' => $code_record->code,
        'totp' => $totp_info['code'],
        'full_code' => $code_record->code . '-' . $totp_info['code'],
        'expires_in' => $totp_info['expires_in'],
    ]);
}

/**
 * Enqueue scripts for mission AJAX
 */
function ai_gemini_enqueue_mission_scripts() {
    wp_localize_script('ai-gemini-missions', 'AIGeminiMissions', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ai_gemini_missions_nonce'),
        'strings' => [
            'loading' => __('Loading...', 'ai-gemini-image'),
            'verifying' => __('Verifying...', 'ai-gemini-image'),
            'success' => __('Success!', 'ai-gemini-image'),
            'error' => __('Error', 'ai-gemini-image'),
            'copied' => __('Copied!', 'ai-gemini-image'),
        ],
    ]);
}
add_action('wp_enqueue_scripts', 'ai_gemini_enqueue_mission_scripts', 20);
