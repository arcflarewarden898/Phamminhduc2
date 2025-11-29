<?php
/**
 * AI Gemini Image Generator - Unlock API
 * 
 * REST API endpoint for unlocking full resolution images.
 */

if (!defined('ABSPATH')) exit;

/**
 * Register unlock API endpoint
 */
function ai_gemini_register_unlock_api() {
    register_rest_route('ai/v1', '/unlock', [
        'methods' => 'POST',
        'callback' => 'ai_gemini_handle_unlock_request',
        'permission_callback' => 'ai_gemini_unlock_permission_check',
    ]);
}
add_action('rest_api_init', 'ai_gemini_register_unlock_api');

/**
 * Permission check for unlock API
 * 
 * @param WP_REST_Request $request Request object
 * @return bool|WP_Error True if permitted, WP_Error otherwise
 */
function ai_gemini_unlock_permission_check($request) {
    $user_id = get_current_user_id();
    
    // Check credits
    $credits = ai_gemini_get_credit($user_id ?: null);
    $unlock_cost = (int) get_option('ai_gemini_unlock_credit', 1);
    
    if ($credits < $unlock_cost) {
        return new WP_Error(
            'insufficient_credits',
            sprintf(
                __('You need %d credits to unlock this image. Current balance: %d', 'ai-gemini-image'),
                $unlock_cost,
                $credits
            ),
            ['status' => 402]
        );
    }
    
    return true;
}

/**
 * Handle unlock request
 * 
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response or error
 */
function ai_gemini_handle_unlock_request($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $ip = ai_gemini_get_client_ip();
    
    $image_id = absint($request->get_param('image_id'));
    
    if (!$image_id) {
        return new WP_Error(
            'missing_image_id',
            __('Image ID is required', 'ai-gemini-image'),
            ['status' => 400]
        );
    }
    
    // Get image from database
    $table_images = $wpdb->prefix . 'ai_gemini_images';
    $image = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_images WHERE id = %d",
        $image_id
    ));
    
    if (!$image) {
        return new WP_Error(
            'image_not_found',
            __('Image not found', 'ai-gemini-image'),
            ['status' => 404]
        );
    }
    
    // Check ownership
    if ($user_id) {
        if ($image->user_id && $image->user_id != $user_id) {
            return new WP_Error(
                'not_authorized',
                __('You are not authorized to unlock this image', 'ai-gemini-image'),
                ['status' => 403]
            );
        }
    } else {
        if ($image->guest_ip !== $ip) {
            return new WP_Error(
                'not_authorized',
                __('You are not authorized to unlock this image', 'ai-gemini-image'),
                ['status' => 403]
            );
        }
    }
    
    // Check if already unlocked
    if ($image->is_unlocked && $image->full_image_url) {
        return rest_ensure_response([
            'success' => true,
            'already_unlocked' => true,
            'full_url' => $image->full_image_url,
            'message' => __('Image was already unlocked', 'ai-gemini-image'),
        ]);
    }
    
    // Check if image has expired
    if ($image->expires_at && strtotime($image->expires_at) < time()) {
        return new WP_Error(
            'image_expired',
            __('This preview has expired. Please generate a new image.', 'ai-gemini-image'),
            ['status' => 410]
        );
    }
    
    // Deduct credits
    $unlock_cost = (int) get_option('ai_gemini_unlock_credit', 1);
    ai_gemini_update_credit(-$unlock_cost, $user_id ?: null);
    
    // Get preview image and remove watermark
    $upload_dir = ai_gemini_get_upload_dir();
    $preview_path = str_replace($upload_dir['url'], $upload_dir['path'], $image->preview_image_url);
    
    if (!file_exists($preview_path)) {
        // Refund credits
        ai_gemini_update_credit($unlock_cost, $user_id ?: null);
        
        return new WP_Error(
            'preview_not_found',
            __('Preview image not found. Please generate a new image.', 'ai-gemini-image'),
            ['status' => 404]
        );
    }
    
    // Get the original (non-watermarked) image
    $original_content = ai_gemini_get_unlocked_image($image_id);
    
    if (!$original_content) {
        // Fallback: copy the preview file (this shouldn't happen in production)
        // In production, original should always be stored during generation
        ai_gemini_log("Falling back to preview for unlock: Image ID {$image_id}", 'warning');
        
        if (!file_exists($preview_path)) {
            ai_gemini_update_credit($unlock_cost, $user_id ?: null);
            return new WP_Error(
                'preview_not_found',
                __('Preview image not found. Please generate a new image.', 'ai-gemini-image'),
                ['status' => 404]
            );
        }
        $original_content = file_get_contents($preview_path);
    }
    
    // Generate filename for full image
    $full_filename = ai_gemini_generate_filename('full');
    $full_filepath = $upload_dir['path'] . '/' . $full_filename;
    
    $saved = file_put_contents($full_filepath, $original_content);
    
    if (!$saved) {
        // Refund credits
        ai_gemini_update_credit($unlock_cost, $user_id ?: null);
        
        return new WP_Error(
            'save_failed',
            __('Failed to save unlocked image', 'ai-gemini-image'),
            ['status' => 500]
        );
    }
    
    $full_url = $upload_dir['url'] . '/' . $full_filename;
    
    // Update database
    $wpdb->update(
        $table_images,
        [
            'full_image_url' => $full_url,
            'is_unlocked' => 1,
            'credits_used' => $image->credits_used + $unlock_cost,
        ],
        ['id' => $image_id],
        ['%s', '%d', '%d'],
        ['%d']
    );
    
    // Log transaction
    ai_gemini_log_transaction([
        'user_id' => $user_id ?: null,
        'guest_ip' => $user_id ? null : $ip,
        'type' => 'image_unlock',
        'amount' => -$unlock_cost,
        'description' => __('Unlocked full resolution image', 'ai-gemini-image'),
        'reference_id' => $image_id,
    ]);
    
    // Get remaining credits
    $remaining_credits = ai_gemini_get_credit($user_id ?: null);
    
    return rest_ensure_response([
        'success' => true,
        'image_id' => $image_id,
        'full_url' => $full_url,
        'credits_remaining' => $remaining_credits,
        'message' => __('Image unlocked successfully!', 'ai-gemini-image'),
    ]);
}

/**
 * Get user's generated images
 * 
 * @param int|null $user_id User ID or null for guest
 * @param int $limit Maximum number of images to return
 * @param int $offset Offset for pagination
 * @return array Array of image records
 */
function ai_gemini_get_user_images($user_id = null, $limit = 20, $offset = 0) {
    global $wpdb;
    
    $table_images = $wpdb->prefix . 'ai_gemini_images';
    
    if ($user_id) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_images WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        ));
    } else {
        $ip = ai_gemini_get_client_ip();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_images WHERE guest_ip = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $ip,
            $limit,
            $offset
        ));
    }
}
