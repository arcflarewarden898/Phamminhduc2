<?php
/**
 * AI Gemini Image Generator - Preview API
 * 
 * REST API endpoint for generating preview images.
 */

if (!defined('ABSPATH')) exit;

/**
 * Register preview API endpoint
 */
function ai_gemini_register_preview_api() {
    register_rest_route('ai/v1', '/preview', [
        'methods' => 'POST',
        'callback' => 'ai_gemini_handle_preview_request',
        'permission_callback' => 'ai_gemini_preview_permission_check',
    ]);
}
add_action('rest_api_init', 'ai_gemini_register_preview_api');

/**
 * Permission check for preview API
 * 
 * @param WP_REST_Request $request Request object
 * @return bool|WP_Error True if permitted, WP_Error otherwise
 */
function ai_gemini_preview_permission_check($request) {
    // Allow guests but check rate limiting
    $user_id = get_current_user_id();
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    
    // Check if user has credits or free trial available
    $credits = ai_gemini_get_credit($user_id ?: null);
    $preview_cost = (int) get_option('ai_gemini_preview_credit', 0);
    
    if ($preview_cost > 0 && $credits < $preview_cost) {
        // Check for free trial
        if (ai_gemini_has_used_trial($user_id ?: null)) {
            return new WP_Error(
                'insufficient_credits',
                __('Insufficient credits. Please purchase more credits to continue.', 'ai-gemini-image'),
                ['status' => 402]
            );
        }
    }
    
    return true;
}

/**
 * Handle preview generation request
 * 
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response or error
 */
function ai_gemini_handle_preview_request($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    
    // Get request parameters
    $image_data = $request->get_param('image');
    $style = sanitize_text_field($request->get_param('style') ?: '');
    $prompt = sanitize_textarea_field($request->get_param('prompt') ?: '');
    
    if (empty($image_data)) {
        return new WP_Error(
            'missing_image',
            __('Please upload an image', 'ai-gemini-image'),
            ['status' => 400]
        );
    }
    
    // Initialize Gemini API
    $api = new AI_GEMINI_API();
    
    if (!$api->is_configured()) {
        return new WP_Error(
            'api_not_configured',
            __('Image generation service is not configured. Please contact administrator.', 'ai-gemini-image'),
            ['status' => 503]
        );
    }
    
    // Deduct credits if required
    $preview_cost = (int) get_option('ai_gemini_preview_credit', 0);
    $credits_used = 0;
    
    if ($preview_cost > 0) {
        $credits = ai_gemini_get_credit($user_id ?: null);
        
        if ($credits >= $preview_cost) {
            ai_gemini_update_credit(-$preview_cost, $user_id ?: null);
            $credits_used = $preview_cost;
        } else {
            // Use free trial
            if (!ai_gemini_has_used_trial($user_id ?: null)) {
                ai_gemini_mark_trial_used($user_id ?: null);
            } else {
                return new WP_Error(
                    'insufficient_credits',
                    __('Insufficient credits', 'ai-gemini-image'),
                    ['status' => 402]
                );
            }
        }
    }
    
    // Generate image
    $result = $api->generate_image($image_data, $prompt, $style);
    
    if (!$result) {
        // Refund credits on failure
        if ($credits_used > 0) {
            ai_gemini_update_credit($credits_used, $user_id ?: null);
        }
        
        return new WP_Error(
            'generation_failed',
            $api->get_last_error() ?: __('Failed to generate image', 'ai-gemini-image'),
            ['status' => 500]
        );
    }
    
    // Save generated image
    $upload_dir = ai_gemini_get_upload_dir();
    $filename = ai_gemini_generate_filename('preview');
    $filepath = $upload_dir['path'] . '/' . $filename;
    
    // Decode and save image
    $image_binary = base64_decode($result['image_data']);
    
    // Add watermark for preview
    $image_binary = ai_gemini_add_watermark($image_binary);
    
    $saved = file_put_contents($filepath, $image_binary);
    
    if (!$saved) {
        return new WP_Error(
            'save_failed',
            __('Failed to save image', 'ai-gemini-image'),
            ['status' => 500]
        );
    }
    
    $preview_url = $upload_dir['url'] . '/' . $filename;
    
    // Store in database
    $table_images = $wpdb->prefix . 'ai_gemini_images';
    $wpdb->insert(
        $table_images,
        [
            'user_id' => $user_id ?: null,
            'guest_ip' => $user_id ? null : $ip,
            'original_image_url' => null, // We don't store original for privacy
            'preview_image_url' => $preview_url,
            'full_image_url' => null, // Will be set on unlock
            'prompt' => $prompt,
            'style' => $style,
            'is_unlocked' => 0,
            'credits_used' => $credits_used,
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
    );
    
    $image_id = $wpdb->insert_id;
    
    // Log transaction if credits were used
    if ($credits_used > 0) {
        ai_gemini_log_transaction([
            'user_id' => $user_id ?: null,
            'guest_ip' => $user_id ? null : $ip,
            'type' => 'preview_generation',
            'amount' => -$credits_used,
            'description' => __('Preview image generation', 'ai-gemini-image'),
            'reference_id' => $image_id,
        ]);
    }
    
    // Get remaining credits
    $remaining_credits = ai_gemini_get_credit($user_id ?: null);
    $unlock_cost = (int) get_option('ai_gemini_unlock_credit', 1);
    
    return rest_ensure_response([
        'success' => true,
        'image_id' => $image_id,
        'preview_url' => $preview_url,
        'credits_remaining' => $remaining_credits,
        'unlock_cost' => $unlock_cost,
        'can_unlock' => $remaining_credits >= $unlock_cost,
        'message' => __('Preview generated successfully!', 'ai-gemini-image'),
    ]);
}
