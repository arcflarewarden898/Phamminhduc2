<?php
/**
 * AI Gemini Image Generator - Helper Functions
 * 
 * Common utility functions used throughout the plugin.
 */

if (!defined('ABSPATH')) exit;

/**
 * Get user or guest credit balance
 * 
 * @param int|null $user_id User ID or null for guest
 * @return int Credit balance
 */
function ai_gemini_get_credit($user_id = null) {
    if ($user_id) {
        return (int)get_user_meta($user_id, 'ai_gemini_credits', true);
    } else {
        // Guest credit by IP
        global $wpdb;
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $table_name = $wpdb->prefix . 'ai_gemini_guest_credits';
        
        $guest = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE ip=%s", 
            $ip
        ));
        return $guest ? (int)$guest->credits : 0;
    }
}

/**
 * Update user or guest credit balance
 * 
 * @param int $amount Amount to add (can be negative)
 * @param int|null $user_id User ID or null for guest
 * @return bool Success status
 */
function ai_gemini_update_credit($amount, $user_id = null) {
    if ($user_id) {
        $current = ai_gemini_get_credit($user_id);
        $new_balance = max(0, $current + $amount);
        return update_user_meta($user_id, 'ai_gemini_credits', $new_balance);
    } else {
        global $wpdb;
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $table_name = $wpdb->prefix . 'ai_gemini_guest_credits';
        
        $current = ai_gemini_get_credit(null);
        $new_balance = max(0, $current + $amount);
        
        return $wpdb->replace(
            $table_name,
            [
                'ip' => $ip,
                'credits' => $new_balance,
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s']
        );
    }
}

/**
 * Check if user/guest has used free trial
 * 
 * @param int|null $user_id User ID or null for guest
 * @return bool True if trial has been used
 */
function ai_gemini_has_used_trial($user_id = null) {
    global $wpdb;
    
    if ($user_id) {
        return get_user_meta($user_id, 'ai_gemini_used_trial', true) == '1';
    } else {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $table_name = $wpdb->prefix . 'ai_gemini_guest_credits';
        
        $guest = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE ip=%s", 
            $ip
        ));
        return $guest && $guest->used_trial == 1;
    }
}

/**
 * Mark trial as used for user/guest
 * 
 * @param int|null $user_id User ID or null for guest
 * @return bool Success status
 */
function ai_gemini_mark_trial_used($user_id = null) {
    if ($user_id) {
        return update_user_meta($user_id, 'ai_gemini_used_trial', '1');
    } else {
        global $wpdb;
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $table_name = $wpdb->prefix . 'ai_gemini_guest_credits';
        
        return $wpdb->update(
            $table_name,
            ['used_trial' => 1],
            ['ip' => $ip],
            ['%d'],
            ['%s']
        );
    }
}

/**
 * Log error for debugging
 * 
 * @param string $message Log message
 * @param string $type Log type (info, error, warning)
 */
function ai_gemini_log($message, $type = 'info') {
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log("[AI Gemini {$type}] " . $message);
    }
}

/**
 * Get Gemini API key from options
 * 
 * @return string|false API key or false if not set
 */
function ai_gemini_get_api_key() {
    return get_option('ai_gemini_api_key', false);
}

/**
 * Check if current user can use the generator
 * 
 * @return bool True if user has permission
 */
function ai_gemini_user_can_generate() {
    // Allow logged in users or guests with credits
    $user_id = get_current_user_id();
    $credits = ai_gemini_get_credit($user_id ?: null);
    
    return $credits > 0 || !ai_gemini_has_used_trial($user_id ?: null);
}

/**
 * Format credit amount for display
 * 
 * @param int $amount Credit amount
 * @return string Formatted credit string
 */
function ai_gemini_format_credits($amount) {
    return number_format_i18n($amount) . ' ' . _n('credit', 'credits', $amount, 'ai-gemini-image');
}

/**
 * Get upload directory for generated images
 * 
 * @return array Upload directory info
 */
function ai_gemini_get_upload_dir() {
    $upload_dir = wp_upload_dir();
    $gemini_dir = $upload_dir['basedir'] . '/ai-gemini-images';
    $gemini_url = $upload_dir['baseurl'] . '/ai-gemini-images';
    
    // Create directory if it doesn't exist
    if (!file_exists($gemini_dir)) {
        wp_mkdir_p($gemini_dir);
    }
    
    return [
        'path' => $gemini_dir,
        'url' => $gemini_url,
    ];
}

/**
 * Generate a unique filename for image
 * 
 * @param string $prefix Filename prefix
 * @param string $extension File extension
 * @return string Unique filename
 */
function ai_gemini_generate_filename($prefix = 'gemini', $extension = 'png') {
    return $prefix . '-' . wp_generate_uuid4() . '.' . $extension;
}

/**
 * Sanitize and validate image data
 * 
 * @param string $image_data Base64 encoded image data
 * @return string|false Cleaned image data or false if invalid
 */
function ai_gemini_validate_image_data($image_data) {
    // Remove data URI prefix if present
    if (strpos($image_data, 'data:image/') === 0) {
        $image_data = preg_replace('/^data:image\/\w+;base64,/', '', $image_data);
    }
    
    // Validate base64
    $decoded = base64_decode($image_data, true);
    if ($decoded === false) {
        return false;
    }
    
    // Check if it's a valid image
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($decoded);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed_mimes)) {
        return false;
    }
    
    return $image_data;
}
