<?php
/**
 * AI Gemini Image Generator - Unlock API
 */

if (!defined('ABSPATH')) exit;

/**
 * Register unlock API endpoint
 */
function ai_gemini_register_unlock_api() {
    register_rest_route('ai/v1', '/unlock', [
        'methods' => 'POST',
        'callback' => 'ai_gemini_handle_unlock_request',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'ai_gemini_register_unlock_api');

/**
 * Handle unlock request
 */
function ai_gemini_handle_unlock_request($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $image_id = $request->get_param('image_id');
    
    if (empty($image_id)) {
        return new WP_Error(
            'missing_image_id',
            'Thiếu ID hình ảnh.',
            ['status' => 400]
        );
    }
    
    // Get image details
    $table_images = $wpdb->prefix . 'ai_gemini_images';
    $image = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_images WHERE id = %d", 
        $image_id
    ));
    
    if (!$image) {
        return new WP_Error(
            'image_not_found',
            'Không tìm thấy hình ảnh.',
            ['status' => 404]
        );
    }
    
    // Verify ownership
    if ($image->user_id) {
        if ($user_id != $image->user_id) {
            return new WP_Error(
                'unauthorized',
                'Bạn không có quyền mở khóa ảnh này.',
                ['status' => 403]
            );
        }
    } else {
        // Check IP for guest
        $ip = ai_gemini_get_client_ip();
        if ($image->guest_ip !== $ip) {
            return new WP_Error(
                'unauthorized',
                'Bạn không có quyền mở khóa ảnh này (Sai IP).',
                ['status' => 403]
            );
        }
    }
    
    // Return if already unlocked
    if ($image->is_unlocked) {
        // Construct full URL if not saved in DB (backward compatibility)
        $full_url = $image->full_image_url;
        if (empty($full_url)) {
            $upload_dir = ai_gemini_get_upload_dir();
            $filename = basename($image->preview_image_url); // Assuming naming convention matches
            $full_url = $upload_dir['url'] . '/' . str_replace('-preview', '', $filename);
        }
        
        return rest_ensure_response([
            'success' => true,
            'full_url' => $full_url,
            'credits_remaining' => ai_gemini_get_credit($user_id ?: null),
            'message' => 'Ảnh đã được mở khóa trước đó.'
        ]);
    }
    
    // Check credits
    $unlock_cost = (int) get_option('ai_gemini_unlock_credit', 1);
    $credits = ai_gemini_get_credit($user_id ?: null);
    
    if ($credits < $unlock_cost) {
        return new WP_Error(
            'insufficient_credits',
            sprintf('Bạn cần %d credit để mở khóa ảnh này.', $unlock_cost),
            ['status' => 402]
        );
    }
    
    // Process unlock
    // 1. Deduct credits
    ai_gemini_update_credit(-$unlock_cost, $user_id ?: null);
    
    // 2. Generate full image URL (removing -preview suffix logic handled in store function)
    // Since we stored both at generation time, we just need to find the original path
    // In preview.php we stored: $stored['full_path'] and $stored['full_url']
    
    // Re-construct the full URL based on the preview URL
    // Expect preview url like: .../uploads/ai-gemini-images/preview-xyz-preview.png
    // We want: .../uploads/ai-gemini-images/preview-xyz.png
    
    $full_url = str_replace('-preview.', '.', $image->preview_image_url);
    
    // Update database
    $wpdb->update(
        $table_images,
        [
            'is_unlocked' => 1,
            'full_image_url' => $full_url,
            'credits_used' => $image->credits_used + $unlock_cost
        ],
        ['id' => $image_id]
    );
    
    // Log transaction
    ai_gemini_log_transaction([
        'user_id' => $user_id ?: null,
        'guest_ip' => $user_id ? null : ai_gemini_get_client_ip(),
        'type' => 'image_unlock',
        'amount' => -$unlock_cost,
        'description' => 'Mở khóa ảnh gốc',
        'reference_id' => $image_id,
    ]);
    
    return rest_ensure_response([
        'success' => true,
        'full_url' => $full_url,
        'credits_remaining' => ai_gemini_get_credit($user_id ?: null),
        'message' => 'Mở khóa ảnh thành công!'
    ]);
}