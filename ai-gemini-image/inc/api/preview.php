<?php
/**
 * AI Gemini Image Generator - Preview API
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
 * Permission check
 */
function ai_gemini_preview_permission_check($request) {
    $user_id = get_current_user_id();
    $credits = ai_gemini_get_credit($user_id ?: null);
    $preview_cost = (int) get_option('ai_gemini_preview_credit', 0);
    
    if ($preview_cost > 0 && $credits < $preview_cost) {
        if (ai_gemini_has_used_trial($user_id ?: null)) {
            return new WP_Error(
                'insufficient_credits',
                'Bạn không đủ tín dụng. Vui lòng nạp thêm để tiếp tục.',
                ['status' => 402]
            );
        }
    }
    return true;
}

/**
 * Handle preview generation request
 */
function ai_gemini_handle_preview_request($request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $ip = ai_gemini_get_client_ip();
    
    $image_data = $request->get_param('image');
    $style_slug = sanitize_text_field($request->get_param('style') ?: '');
    $user_prompt = sanitize_textarea_field($request->get_param('prompt') ?: '');
    
    if (empty($image_data)) {
        return new WP_Error(
            'missing_image',
            'Vui lòng tải lên một bức ảnh.',
            ['status' => 400]
        );
    }
    
    // LOGIC TRA CỨU PROMPT
    $final_prompt_text = '';
    $style_title = 'Custom';
    
    if (!empty($style_slug)) {
        $prompt_obj = ai_gemini_get_prompt_by_key($style_slug);
        if ($prompt_obj) {
            $final_prompt_text = $prompt_obj->prompt_text;
            $style_title = $prompt_obj->title;
        } else {
             return new WP_Error(
                'invalid_style',
                'Kiểu phong cách (style) không hợp lệ hoặc đã bị xóa.',
                ['status' => 400]
            );
        }
    }
    
    if (!empty($user_prompt)) {
        $final_prompt_text .= "\nAdditional User Instruction: " . $user_prompt;
    }
    
    $api = new AI_GEMINI_API();
    
    if (!$api->is_configured()) {
        return new WP_Error(
            'api_not_configured',
            'Dịch vụ chưa được cấu hình API Key. Vui lòng liên hệ Admin.',
            ['status' => 503]
        );
    }
    
    // Trừ credit
    $preview_cost = (int) get_option('ai_gemini_preview_credit', 0);
    $credits_used = 0;
    
    if ($preview_cost > 0) {
        $credits = ai_gemini_get_credit($user_id ?: null);
        if ($credits >= $preview_cost) {
            ai_gemini_update_credit(-$preview_cost, $user_id ?: null);
            $credits_used = $preview_cost;
        } else {
            if (!ai_gemini_has_used_trial($user_id ?: null)) {
                ai_gemini_mark_trial_used($user_id ?: null);
            } else {
                return new WP_Error(
                    'insufficient_credits',
                    'Không đủ tín dụng',
                    ['status' => 402]
                );
            }
        }
    }
    
    $result = $api->generate_image($image_data, $final_prompt_text, $style_title);
    
    if (!$result) {
        if ($credits_used > 0) {
            ai_gemini_update_credit($credits_used, $user_id ?: null);
        }
        return new WP_Error(
            'generation_failed',
            $api->get_last_error() ?: 'Tạo ảnh thất bại, vui lòng thử lại.',
            ['status' => 500]
        );
    }
    
    $filename = ai_gemini_generate_filename('preview');
    $image_binary = base64_decode($result['image_data']);
    $stored = ai_gemini_store_image_versions($image_binary, $filename);
    
    if (!$stored || !file_exists($stored['preview_path'])) {
        return new WP_Error(
            'save_failed',
            'Lỗi khi lưu ảnh xuống server.',
            ['status' => 500]
        );
    }
    
    $preview_url = $stored['preview_url'];
    
    $table_images = $wpdb->prefix . 'ai_gemini_images';
    $wpdb->insert(
        $table_images,
        [
            'user_id' => $user_id ?: null,
            'guest_ip' => $user_id ? null : $ip,
            'original_image_url' => null,
            'preview_image_url' => $preview_url,
            'full_image_url' => null,
            'prompt' => $final_prompt_text,
            'style' => $style_slug,
            'is_unlocked' => 0,
            'credits_used' => $credits_used,
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
    );
    
    $image_id = $wpdb->insert_id;
    
    if ($credits_used > 0) {
        ai_gemini_log_transaction([
            'user_id' => $user_id ?: null,
            'guest_ip' => $user_id ? null : $ip,
            'type' => 'preview_generation',
            'amount' => -$credits_used,
            'description' => 'Tạo ảnh xem trước',
            'reference_id' => $image_id,
        ]);
    }
    
    $remaining_credits = ai_gemini_get_credit($user_id ?: null);
    $unlock_cost = (int) get_option('ai_gemini_unlock_credit', 1);
    
    return rest_ensure_response([
        'success' => true,
        'image_id' => $image_id,
        'preview_url' => $preview_url,
        'credits_remaining' => $remaining_credits,
        'unlock_cost' => $unlock_cost,
        'can_unlock' => $remaining_credits >= $unlock_cost,
        'message' => 'Đã tạo ảnh thành công!',
    ]);
}