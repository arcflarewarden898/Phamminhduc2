<?php
/**
 * AI Gemini Image Generator - Mission Functions
 * 
 * Core functions for managing missions and rewards.
 */

if (!defined('ABSPATH')) exit;

/**
 * Get all active missions
 * 
 * @return array Array of active missions
 */
function ai_gemini_get_active_missions() {
    global $wpdb;
    
    $table_missions = $wpdb->prefix . 'ai_gemini_missions';
    
    $missions = $wpdb->get_results(
        "SELECT * FROM $table_missions WHERE is_active = 1 ORDER BY id DESC"
    );
    
    return $missions ?: [];
}

/**
 * Get a single mission by ID
 * 
 * @param int $mission_id Mission ID
 * @return object|null Mission object or null
 */
function ai_gemini_get_mission($mission_id) {
    global $wpdb;
    
    $table_missions = $wpdb->prefix . 'ai_gemini_missions';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_missions WHERE id = %d",
        $mission_id
    ));
}

/**
 * Get a mission by key
 * 
 * @param string $mission_key Mission key
 * @return object|null Mission object or null
 */
function ai_gemini_get_mission_by_key($mission_key) {
    global $wpdb;
    
    $table_missions = $wpdb->prefix . 'ai_gemini_missions';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_missions WHERE mission_key = %s",
        $mission_key
    ));
}

/**
 * Check if user is eligible to complete a mission
 * 
 * @param int $mission_id Mission ID
 * @param int|null $user_id User ID (null for guest)
 * @return array ['eligible' => bool, 'reason' => string, 'next_available' => datetime|null]
 */
function ai_gemini_check_mission_eligible($mission_id, $user_id = null) {
    global $wpdb;
    
    $mission = ai_gemini_get_mission($mission_id);
    
    if (!$mission) {
        return [
            'eligible' => false,
            'reason' => __('Mission not found.', 'ai-gemini-image'),
            'next_available' => null,
        ];
    }
    
    if (!$mission->is_active) {
        return [
            'eligible' => false,
            'reason' => __('This mission is no longer active.', 'ai-gemini-image'),
            'next_available' => null,
        ];
    }
    
    $table_completions = $wpdb->prefix . 'ai_gemini_mission_completions';
    $ip = ai_gemini_get_client_ip();
    
    // Check max completions (global)
    if ($mission->max_completions > 0) {
        $total_completions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_completions WHERE mission_id = %d",
            $mission_id
        ));
        
        if ($total_completions >= $mission->max_completions) {
            return [
                'eligible' => false,
                'reason' => __('This mission has reached maximum completions.', 'ai-gemini-image'),
                'next_available' => null,
            ];
        }
    }
    
    // Check cooldown
    if ($mission->cooldown_hours > 0) {
        // Build query based on user or guest
        if ($user_id) {
            $last_completion = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(completed_at) FROM $table_completions WHERE mission_id = %d AND user_id = %d",
                $mission_id,
                $user_id
            ));
        } else {
            $last_completion = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(completed_at) FROM $table_completions WHERE mission_id = %d AND guest_ip = %s",
                $mission_id,
                $ip
            ));
        }
        
        if ($last_completion) {
            $last_time = strtotime($last_completion);
            $cooldown_seconds = $mission->cooldown_hours * 3600;
            $next_available = $last_time + $cooldown_seconds;
            
            if (time() < $next_available) {
                return [
                    'eligible' => false,
                    'reason' => sprintf(
                        __('You can do this mission again in %s.', 'ai-gemini-image'),
                        ai_gemini_format_time_remaining($next_available - time())
                    ),
                    'next_available' => gmdate('Y-m-d H:i:s', $next_available),
                ];
            }
        }
    } else {
        // If cooldown is 0, mission can only be done once
        if ($user_id) {
            $has_completed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_completions WHERE mission_id = %d AND user_id = %d",
                $mission_id,
                $user_id
            ));
        } else {
            $has_completed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_completions WHERE mission_id = %d AND guest_ip = %s",
                $mission_id,
                $ip
            ));
        }
        
        if ($has_completed > 0) {
            return [
                'eligible' => false,
                'reason' => __('You have already completed this mission.', 'ai-gemini-image'),
                'next_available' => null,
            ];
        }
    }
    
    return [
        'eligible' => true,
        'reason' => '',
        'next_available' => null,
    ];
}

/**
 * Verify mission code with TOTP
 * 
 * @param int $mission_id Mission ID
 * @param string $code_input User-provided code (format: STATICCODE-TOTP)
 * @return array ['valid' => bool, 'message' => string, 'code_id' => int|null]
 */
function ai_gemini_verify_mission_code($mission_id, $code_input) {
    global $wpdb;
    
    $mission = ai_gemini_get_mission($mission_id);
    
    if (!$mission) {
        return [
            'valid' => false,
            'message' => __('Mission not found.', 'ai-gemini-image'),
            'code_id' => null,
        ];
    }
    
    // Parse code input (format: STATICCODE-TOTP)
    $code_input = strtoupper(trim($code_input));
    $parts = explode('-', $code_input);
    
    if (count($parts) !== 2) {
        return [
            'valid' => false,
            'message' => __('Invalid code format. Please use format: CODE-123456', 'ai-gemini-image'),
            'code_id' => null,
        ];
    }
    
    $static_code = $parts[0];
    $totp_code = $parts[1];
    
    // Get active codes for this mission
    $table_codes = $wpdb->prefix . 'ai_gemini_mission_codes';
    $mission_codes = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_codes 
         WHERE mission_id = %d 
         AND is_active = 1 
         AND (expires_at IS NULL OR expires_at > NOW())",
        $mission_id
    ));
    
    if (empty($mission_codes)) {
        return [
            'valid' => false,
            'message' => __('No active codes for this mission.', 'ai-gemini-image'),
            'code_id' => null,
        ];
    }
    
    // Check each code
    foreach ($mission_codes as $code_record) {
        if (strtoupper($code_record->code) === $static_code) {
            // Static code matches, now validate TOTP
            if (ai_gemini_validate_totp($code_record->totp_secret, $totp_code)) {
                return [
                    'valid' => true,
                    'message' => __('Code verified successfully!', 'ai-gemini-image'),
                    'code_id' => $code_record->id,
                ];
            } else {
                return [
                    'valid' => false,
                    'message' => __('Verification code has expired. Please get a new code.', 'ai-gemini-image'),
                    'code_id' => null,
                ];
            }
        }
    }
    
    return [
        'valid' => false,
        'message' => __('Invalid code. Please check and try again.', 'ai-gemini-image'),
        'code_id' => null,
    ];
}

/**
 * Complete a mission and award credits
 * 
 * @param int $mission_id Mission ID
 * @param int|null $user_id User ID (null for guest)
 * @param string $code Code used
 * @return array ['success' => bool, 'message' => string, 'credits_earned' => int]
 */
function ai_gemini_complete_mission($mission_id, $user_id = null, $code = '') {
    global $wpdb;
    
    $mission = ai_gemini_get_mission($mission_id);
    
    if (!$mission) {
        return [
            'success' => false,
            'message' => __('Mission not found.', 'ai-gemini-image'),
            'credits_earned' => 0,
        ];
    }
    
    // Check eligibility again
    $eligibility = ai_gemini_check_mission_eligible($mission_id, $user_id);
    if (!$eligibility['eligible']) {
        return [
            'success' => false,
            'message' => $eligibility['reason'],
            'credits_earned' => 0,
        ];
    }
    
    $ip = ai_gemini_get_client_ip();
    $table_completions = $wpdb->prefix . 'ai_gemini_mission_completions';
    
    // Insert completion record
    $inserted = $wpdb->insert(
        $table_completions,
        [
            'mission_id' => $mission_id,
            'user_id' => $user_id,
            'guest_ip' => $user_id ? null : $ip,
            'code_used' => $code,
            'credits_earned' => $mission->reward_credits,
            'completed_at' => current_time('mysql'),
        ],
        ['%d', '%d', '%s', '%s', '%d', '%s']
    );
    
    if (!$inserted) {
        return [
            'success' => false,
            'message' => __('Failed to record completion.', 'ai-gemini-image'),
            'credits_earned' => 0,
        ];
    }
    
    // Add credits to user
    ai_gemini_update_credit($mission->reward_credits, $user_id);
    
    // Log transaction
    ai_gemini_log_transaction([
        'user_id' => $user_id,
        'guest_ip' => $user_id ? null : $ip,
        'type' => 'mission_reward',
        'amount' => $mission->reward_credits,
        'description' => sprintf(
            __('Mission reward: %s', 'ai-gemini-image'),
            $mission->title
        ),
        'reference_id' => $wpdb->insert_id,
    ]);
    
    ai_gemini_log("Mission {$mission_id} completed by user {$user_id}, awarded {$mission->reward_credits} credits", 'info');
    
    return [
        'success' => true,
        'message' => sprintf(
            __('Congratulations! You earned %d credits!', 'ai-gemini-image'),
            $mission->reward_credits
        ),
        'credits_earned' => $mission->reward_credits,
    ];
}

/**
 * Get user's mission completion history
 * 
 * @param int|null $user_id User ID (null for guest)
 * @param int $limit Maximum records to return
 * @param int $offset Offset for pagination
 * @return array Array of completion records
 */
function ai_gemini_get_user_mission_history($user_id = null, $limit = 20, $offset = 0) {
    global $wpdb;
    
    $table_completions = $wpdb->prefix . 'ai_gemini_mission_completions';
    $table_missions = $wpdb->prefix . 'ai_gemini_missions';
    
    if ($user_id) {
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, m.title as mission_title, m.mission_type 
             FROM $table_completions c 
             LEFT JOIN $table_missions m ON c.mission_id = m.id 
             WHERE c.user_id = %d 
             ORDER BY c.completed_at DESC 
             LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        ));
    } else {
        $ip = ai_gemini_get_client_ip();
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, m.title as mission_title, m.mission_type 
             FROM $table_completions c 
             LEFT JOIN $table_missions m ON c.mission_id = m.id 
             WHERE c.guest_ip = %s 
             ORDER BY c.completed_at DESC 
             LIMIT %d OFFSET %d",
            $ip,
            $limit,
            $offset
        ));
    }
    
    return $results ?: [];
}

/**
 * Get mission completion count
 * 
 * @param int $mission_id Mission ID
 * @return int Number of completions
 */
function ai_gemini_get_mission_completion_count($mission_id) {
    global $wpdb;
    
    $table_completions = $wpdb->prefix . 'ai_gemini_mission_completions';
    
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_completions WHERE mission_id = %d",
        $mission_id
    ));
}

/**
 * Get codes for a mission
 * 
 * @param int $mission_id Mission ID
 * @return array Array of code records
 */
function ai_gemini_get_mission_codes($mission_id) {
    global $wpdb;
    
    $table_codes = $wpdb->prefix . 'ai_gemini_mission_codes';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_codes WHERE mission_id = %d ORDER BY id DESC",
        $mission_id
    )) ?: [];
}

/**
 * Add a new code to a mission
 * 
 * @param int $mission_id Mission ID
 * @param string $code Static code
 * @param string|null $expires_at Expiration datetime or null
 * @return int|false Code ID or false on failure
 */
function ai_gemini_add_mission_code($mission_id, $code, $expires_at = null) {
    global $wpdb;
    
    $table_codes = $wpdb->prefix . 'ai_gemini_mission_codes';
    
    // Generate TOTP secret
    $totp_secret = ai_gemini_generate_totp_secret();
    
    $inserted = $wpdb->insert(
        $table_codes,
        [
            'mission_id' => $mission_id,
            'code' => strtoupper($code),
            'totp_secret' => $totp_secret,
            'is_active' => 1,
            'created_at' => current_time('mysql'),
            'expires_at' => $expires_at,
        ],
        ['%d', '%s', '%s', '%d', '%s', '%s']
    );
    
    return $inserted ? $wpdb->insert_id : false;
}

/**
 * Delete a mission code
 * 
 * @param int $code_id Code ID
 * @return bool Success status
 */
function ai_gemini_delete_mission_code($code_id) {
    global $wpdb;
    
    $table_codes = $wpdb->prefix . 'ai_gemini_mission_codes';
    
    return (bool) $wpdb->delete($table_codes, ['id' => $code_id], ['%d']);
}

/**
 * Toggle code active status
 * 
 * @param int $code_id Code ID
 * @param bool $is_active Active status
 * @return bool Success status
 */
function ai_gemini_toggle_mission_code($code_id, $is_active) {
    global $wpdb;
    
    $table_codes = $wpdb->prefix . 'ai_gemini_mission_codes';
    
    return (bool) $wpdb->update(
        $table_codes,
        ['is_active' => $is_active ? 1 : 0],
        ['id' => $code_id],
        ['%d'],
        ['%d']
    );
}

/**
 * Create a new mission
 * 
 * @param array $data Mission data
 * @return int|false Mission ID or false on failure
 */
function ai_gemini_create_mission($data) {
    global $wpdb;
    
    $table_missions = $wpdb->prefix . 'ai_gemini_missions';
    
    $defaults = [
        'mission_key' => '',
        'title' => '',
        'description' => '',
        'reward_credits' => 0,
        'mission_type' => 'code_collect',
        'target_url' => '',
        'code_hint' => '',
        'is_active' => 1,
        'max_completions' => 0,
        'cooldown_hours' => 0,
    ];
    
    $data = wp_parse_args($data, $defaults);
    
    // Generate mission key if not provided
    if (empty($data['mission_key'])) {
        $data['mission_key'] = 'mission_' . wp_generate_uuid4();
    }
    
    $inserted = $wpdb->insert(
        $table_missions,
        [
            'mission_key' => sanitize_key($data['mission_key']),
            'title' => sanitize_text_field($data['title']),
            'description' => wp_kses_post($data['description']),
            'reward_credits' => absint($data['reward_credits']),
            'mission_type' => sanitize_key($data['mission_type']),
            'target_url' => esc_url_raw($data['target_url']),
            'code_hint' => wp_kses_post($data['code_hint']),
            'is_active' => $data['is_active'] ? 1 : 0,
            'max_completions' => absint($data['max_completions']),
            'cooldown_hours' => absint($data['cooldown_hours']),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s']
    );
    
    return $inserted ? $wpdb->insert_id : false;
}

/**
 * Update a mission
 * 
 * @param int $mission_id Mission ID
 * @param array $data Mission data
 * @return bool Success status
 */
function ai_gemini_update_mission($mission_id, $data) {
    global $wpdb;
    
    $table_missions = $wpdb->prefix . 'ai_gemini_missions';
    
    $update_data = [];
    $format = [];
    
    if (isset($data['title'])) {
        $update_data['title'] = sanitize_text_field($data['title']);
        $format[] = '%s';
    }
    if (isset($data['description'])) {
        $update_data['description'] = wp_kses_post($data['description']);
        $format[] = '%s';
    }
    if (isset($data['reward_credits'])) {
        $update_data['reward_credits'] = absint($data['reward_credits']);
        $format[] = '%d';
    }
    if (isset($data['mission_type'])) {
        $update_data['mission_type'] = sanitize_key($data['mission_type']);
        $format[] = '%s';
    }
    if (isset($data['target_url'])) {
        $update_data['target_url'] = esc_url_raw($data['target_url']);
        $format[] = '%s';
    }
    if (isset($data['code_hint'])) {
        $update_data['code_hint'] = wp_kses_post($data['code_hint']);
        $format[] = '%s';
    }
    if (isset($data['is_active'])) {
        $update_data['is_active'] = $data['is_active'] ? 1 : 0;
        $format[] = '%d';
    }
    if (isset($data['max_completions'])) {
        $update_data['max_completions'] = absint($data['max_completions']);
        $format[] = '%d';
    }
    if (isset($data['cooldown_hours'])) {
        $update_data['cooldown_hours'] = absint($data['cooldown_hours']);
        $format[] = '%d';
    }
    
    $update_data['updated_at'] = current_time('mysql');
    $format[] = '%s';
    
    return (bool) $wpdb->update(
        $table_missions,
        $update_data,
        ['id' => $mission_id],
        $format,
        ['%d']
    );
}

/**
 * Delete a mission
 * 
 * @param int $mission_id Mission ID
 * @return bool Success status
 */
function ai_gemini_delete_mission($mission_id) {
    global $wpdb;
    
    $table_missions = $wpdb->prefix . 'ai_gemini_missions';
    $table_codes = $wpdb->prefix . 'ai_gemini_mission_codes';
    $table_completions = $wpdb->prefix . 'ai_gemini_mission_completions';
    
    // Delete related records first
    $wpdb->delete($table_codes, ['mission_id' => $mission_id], ['%d']);
    $wpdb->delete($table_completions, ['mission_id' => $mission_id], ['%d']);
    
    // Delete mission
    return (bool) $wpdb->delete($table_missions, ['id' => $mission_id], ['%d']);
}

/**
 * Get all missions (for admin)
 * 
 * @return array Array of all missions
 */
function ai_gemini_get_all_missions() {
    global $wpdb;
    
    $table_missions = $wpdb->prefix . 'ai_gemini_missions';
    
    return $wpdb->get_results(
        "SELECT * FROM $table_missions ORDER BY id DESC"
    ) ?: [];
}

/**
 * Format time remaining in human readable format
 * 
 * @param int $seconds Seconds remaining
 * @return string Formatted time string
 */
function ai_gemini_format_time_remaining($seconds) {
    if ($seconds < 60) {
        return sprintf(_n('%d second', '%d seconds', $seconds, 'ai-gemini-image'), $seconds);
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        return sprintf(_n('%d minute', '%d minutes', $minutes, 'ai-gemini-image'), $minutes);
    } elseif ($seconds < 86400) {
        $hours = floor($seconds / 3600);
        return sprintf(_n('%d hour', '%d hours', $hours, 'ai-gemini-image'), $hours);
    } else {
        $days = floor($seconds / 86400);
        return sprintf(_n('%d day', '%d days', $days, 'ai-gemini-image'), $days);
    }
}

/**
 * Get mission code by ID
 * 
 * @param int $code_id Code ID
 * @return object|null Code object or null
 */
function ai_gemini_get_mission_code($code_id) {
    global $wpdb;
    
    $table_codes = $wpdb->prefix . 'ai_gemini_mission_codes';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_codes WHERE id = %d",
        $code_id
    ));
}
