<?php
/**
 * AI Gemini Image Generator - Mission REST API
 * 
 * REST API endpoints for mission management.
 */

if (!defined('ABSPATH')) exit;

/**
 * Register mission API endpoints
 */
function ai_gemini_register_mission_api() {
    // Get active missions
    register_rest_route('ai/v1', '/missions', [
        'methods' => 'GET',
        'callback' => 'ai_gemini_api_get_missions',
        'permission_callback' => '__return_true',
    ]);
    
    // Get single mission
    register_rest_route('ai/v1', '/missions/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'ai_gemini_api_get_mission',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
            ],
        ],
    ]);
    
    // Verify mission code
    register_rest_route('ai/v1', '/missions/(?P<id>\d+)/verify', [
        'methods' => 'POST',
        'callback' => 'ai_gemini_api_verify_mission',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
            ],
        ],
    ]);
    
    // Get mission history
    register_rest_route('ai/v1', '/missions/history', [
        'methods' => 'GET',
        'callback' => 'ai_gemini_api_get_history',
        'permission_callback' => '__return_true',
    ]);
    
    // Get current TOTP for a mission code (for embedding)
    register_rest_route('ai/v1', '/missions/(?P<id>\d+)/totp', [
        'methods' => 'GET',
        'callback' => 'ai_gemini_api_get_totp',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
            ],
        ],
    ]);
}
add_action('rest_api_init', 'ai_gemini_register_mission_api');

/**
 * API handler: Get active missions
 * 
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response Response
 */
function ai_gemini_api_get_missions($request) {
    $user_id = get_current_user_id();
    $missions = ai_gemini_get_active_missions();
    
    $formatted_missions = [];
    
    foreach ($missions as $mission) {
        $eligibility = ai_gemini_check_mission_eligible($mission->id, $user_id ?: null);
        $completion_count = ai_gemini_get_mission_completion_count($mission->id);
        
        $formatted_missions[] = [
            'id' => (int) $mission->id,
            'mission_key' => $mission->mission_key,
            'title' => $mission->title,
            'description' => $mission->description,
            'reward_credits' => (int) $mission->reward_credits,
            'mission_type' => $mission->mission_type,
            'target_url' => $mission->target_url,
            'code_hint' => $mission->code_hint,
            'max_completions' => (int) $mission->max_completions,
            'current_completions' => $completion_count,
            'cooldown_hours' => (int) $mission->cooldown_hours,
            'eligible' => $eligibility['eligible'],
            'eligibility_reason' => $eligibility['reason'],
            'next_available' => $eligibility['next_available'],
        ];
    }
    
    return rest_ensure_response([
        'missions' => $formatted_missions,
    ]);
}

/**
 * API handler: Get single mission
 * 
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response or error
 */
function ai_gemini_api_get_mission($request) {
    $mission_id = (int) $request->get_param('id');
    $mission = ai_gemini_get_mission($mission_id);
    
    if (!$mission) {
        return new WP_Error(
            'mission_not_found',
            __('Mission not found.', 'ai-gemini-image'),
            ['status' => 404]
        );
    }
    
    $user_id = get_current_user_id();
    $eligibility = ai_gemini_check_mission_eligible($mission->id, $user_id ?: null);
    $completion_count = ai_gemini_get_mission_completion_count($mission->id);
    
    return rest_ensure_response([
        'id' => (int) $mission->id,
        'mission_key' => $mission->mission_key,
        'title' => $mission->title,
        'description' => $mission->description,
        'reward_credits' => (int) $mission->reward_credits,
        'mission_type' => $mission->mission_type,
        'target_url' => $mission->target_url,
        'code_hint' => $mission->code_hint,
        'max_completions' => (int) $mission->max_completions,
        'current_completions' => $completion_count,
        'cooldown_hours' => (int) $mission->cooldown_hours,
        'is_active' => (bool) $mission->is_active,
        'eligible' => $eligibility['eligible'],
        'eligibility_reason' => $eligibility['reason'],
        'next_available' => $eligibility['next_available'],
    ]);
}

/**
 * API handler: Verify mission code
 * 
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response or error
 */
function ai_gemini_api_verify_mission($request) {
    $mission_id = (int) $request->get_param('id');
    $code = sanitize_text_field($request->get_param('code'));
    
    if (empty($code)) {
        return new WP_Error(
            'missing_code',
            __('Please enter a code.', 'ai-gemini-image'),
            ['status' => 400]
        );
    }
    
    $mission = ai_gemini_get_mission($mission_id);
    
    if (!$mission) {
        return new WP_Error(
            'mission_not_found',
            __('Mission not found.', 'ai-gemini-image'),
            ['status' => 404]
        );
    }
    
    $user_id = get_current_user_id();
    
    // Check eligibility
    $eligibility = ai_gemini_check_mission_eligible($mission_id, $user_id ?: null);
    if (!$eligibility['eligible']) {
        return new WP_Error(
            'not_eligible',
            $eligibility['reason'],
            ['status' => 403]
        );
    }
    
    // Verify code
    $verification = ai_gemini_verify_mission_code($mission_id, $code);
    
    if (!$verification['valid']) {
        return new WP_Error(
            'invalid_code',
            $verification['message'],
            ['status' => 400]
        );
    }
    
    // Complete mission
    $completion = ai_gemini_complete_mission($mission_id, $user_id ?: null, $code);
    
    if (!$completion['success']) {
        return new WP_Error(
            'completion_failed',
            $completion['message'],
            ['status' => 500]
        );
    }
    
    return rest_ensure_response([
        'success' => true,
        'message' => $completion['message'],
        'credits_earned' => $completion['credits_earned'],
        'new_balance' => ai_gemini_get_credit($user_id ?: null),
    ]);
}

/**
 * API handler: Get mission history
 * 
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response Response
 */
function ai_gemini_api_get_history($request) {
    $user_id = get_current_user_id();
    $page = max(1, (int) $request->get_param('page'));
    $per_page = min(50, max(1, (int) $request->get_param('per_page') ?: 20));
    $offset = ($page - 1) * $per_page;
    
    $history = ai_gemini_get_user_mission_history($user_id ?: null, $per_page, $offset);
    
    $formatted_history = [];
    foreach ($history as $item) {
        $formatted_history[] = [
            'id' => (int) $item->id,
            'mission_id' => (int) $item->mission_id,
            'mission_title' => $item->mission_title,
            'mission_type' => $item->mission_type,
            'credits_earned' => (int) $item->credits_earned,
            'completed_at' => $item->completed_at,
        ];
    }
    
    return rest_ensure_response([
        'history' => $formatted_history,
        'page' => $page,
        'per_page' => $per_page,
    ]);
}

/**
 * API handler: Get current TOTP for a mission
 * 
 * @param WP_REST_Request $request Request object
 * @return WP_REST_Response|WP_Error Response or error
 */
function ai_gemini_api_get_totp($request) {
    $mission_id = (int) $request->get_param('id');
    $code_id = (int) $request->get_param('code_id');
    
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
        return new WP_Error(
            'no_code',
            __('No active code found.', 'ai-gemini-image'),
            ['status' => 404]
        );
    }
    
    $totp_info = ai_gemini_get_current_totp($code_record->totp_secret);
    
    return rest_ensure_response([
        'static_code' => $code_record->code,
        'totp' => $totp_info['code'],
        'full_code' => $code_record->code . '-' . $totp_info['code'],
        'expires_in' => $totp_info['expires_in'],
    ]);
}
