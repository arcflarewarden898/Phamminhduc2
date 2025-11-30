<?php
/**
 * AI Gemini Image Generator - Mission Manager
 * 
 * Admin interface for managing missions.
 */

if (!defined('ABSPATH')) exit;

/**
 * Add mission manager menu item
 */
function ai_gemini_add_mission_menu() {
    add_submenu_page(
        'ai-gemini-dashboard',
        __('Missions', 'ai-gemini-image'),
        __('Missions', 'ai-gemini-image'),
        'manage_options',
        'ai-gemini-missions',
        'ai_gemini_mission_manager_page'
    );
}
add_action('admin_menu', 'ai_gemini_add_mission_menu');

/**
 * Mission Manager page content
 */
function ai_gemini_mission_manager_page() {
    $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
    $mission_id = isset($_GET['mission_id']) ? absint($_GET['mission_id']) : 0;
    
    switch ($action) {
        case 'new':
            ai_gemini_mission_form_page();
            break;
        case 'edit':
            ai_gemini_mission_form_page($mission_id);
            break;
        case 'codes':
            ai_gemini_mission_codes_page($mission_id);
            break;
        case 'completions':
            ai_gemini_mission_completions_page($mission_id);
            break;
        default:
            ai_gemini_mission_list_page();
            break;
    }
}

/**
 * Mission list page
 */
function ai_gemini_mission_list_page() {
    // Handle delete action
    if (isset($_POST['ai_gemini_delete_mission']) && check_admin_referer('ai_gemini_mission_action')) {
        $delete_id = isset($_POST['mission_id']) ? absint($_POST['mission_id']) : 0;
        if ($delete_id) {
            ai_gemini_delete_mission($delete_id);
            echo '<div class="notice notice-success"><p>' . esc_html__('Mission deleted successfully.', 'ai-gemini-image') . '</p></div>';
        }
    }
    
    // Handle toggle active action
    if (isset($_POST['ai_gemini_toggle_mission']) && check_admin_referer('ai_gemini_mission_action')) {
        $toggle_id = isset($_POST['mission_id']) ? absint($_POST['mission_id']) : 0;
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;
        if ($toggle_id) {
            ai_gemini_update_mission($toggle_id, ['is_active' => $is_active]);
            echo '<div class="notice notice-success"><p>' . esc_html__('Mission updated successfully.', 'ai-gemini-image') . '</p></div>';
        }
    }
    
    $missions = ai_gemini_get_all_missions();
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Missions', 'ai-gemini-image'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-gemini-missions&action=new')); ?>" class="page-title-action">
            <?php esc_html_e('Add New Mission', 'ai-gemini-image'); ?>
        </a>
        <hr class="wp-header-end">
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php esc_html_e('ID', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Title', 'ai-gemini-image'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Type', 'ai-gemini-image'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Reward', 'ai-gemini-image'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Completions', 'ai-gemini-image'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Status', 'ai-gemini-image'); ?></th>
                    <th style="width: 200px;"><?php esc_html_e('Actions', 'ai-gemini-image'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($missions) : ?>
                    <?php foreach ($missions as $mission) : ?>
                        <?php $completion_count = ai_gemini_get_mission_completion_count($mission->id); ?>
                        <tr>
                            <td><?php echo esc_html($mission->id); ?></td>
                            <td>
                                <strong><?php echo esc_html($mission->title); ?></strong>
                                <br><small><code><?php echo esc_html($mission->mission_key); ?></code></small>
                            </td>
                            <td>
                                <span class="mission-type-badge type-<?php echo esc_attr($mission->mission_type); ?>">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $mission->mission_type))); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($mission->reward_credits); ?> <?php esc_html_e('credits', 'ai-gemini-image'); ?></td>
                            <td>
                                <?php 
                                echo esc_html($completion_count);
                                if ($mission->max_completions > 0) {
                                    echo ' / ' . esc_html($mission->max_completions);
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($mission->is_active) : ?>
                                    <span style="color: #46b450;"><?php esc_html_e('Active', 'ai-gemini-image'); ?></span>
                                <?php else : ?>
                                    <span style="color: #dc3232;"><?php esc_html_e('Inactive', 'ai-gemini-image'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-gemini-missions&action=edit&mission_id=' . $mission->id)); ?>" class="button button-small">
                                    <?php esc_html_e('Edit', 'ai-gemini-image'); ?>
                                </a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-gemini-missions&action=codes&mission_id=' . $mission->id)); ?>" class="button button-small">
                                    <?php esc_html_e('Codes', 'ai-gemini-image'); ?>
                                </a>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('ai_gemini_mission_action'); ?>
                                    <input type="hidden" name="mission_id" value="<?php echo esc_attr($mission->id); ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $mission->is_active ? '0' : '1'; ?>">
                                    <button type="submit" name="ai_gemini_toggle_mission" class="button button-small">
                                        <?php echo $mission->is_active ? esc_html__('Deactivate', 'ai-gemini-image') : esc_html__('Activate', 'ai-gemini-image'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e('No missions found.', 'ai-gemini-image'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <style>
        .mission-type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .type-code_collect { background: #e1f5fe; color: #0277bd; }
        .type-social_share { background: #f3e5f5; color: #7b1fa2; }
        .type-daily_login { background: #fff3e0; color: #ef6c00; }
    </style>
    <?php
}

/**
 * Mission form page (add/edit)
 * 
 * @param int $mission_id Mission ID (0 for new)
 */
function ai_gemini_mission_form_page($mission_id = 0) {
    $mission = null;
    $is_edit = false;
    
    if ($mission_id > 0) {
        $mission = ai_gemini_get_mission($mission_id);
        if (!$mission) {
            wp_die(__('Mission not found.', 'ai-gemini-image'));
        }
        $is_edit = true;
    }
    
    // Handle form submission
    if (isset($_POST['ai_gemini_save_mission']) && check_admin_referer('ai_gemini_mission_form')) {
        $data = [
            'mission_key' => isset($_POST['mission_key']) ? sanitize_key(wp_unslash($_POST['mission_key'])) : '',
            'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '',
            'description' => isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '',
            'reward_credits' => isset($_POST['reward_credits']) ? absint($_POST['reward_credits']) : 0,
            'mission_type' => isset($_POST['mission_type']) ? sanitize_key(wp_unslash($_POST['mission_type'])) : 'code_collect',
            'target_url' => isset($_POST['target_url']) ? esc_url_raw(wp_unslash($_POST['target_url'])) : '',
            'code_hint' => isset($_POST['code_hint']) ? wp_kses_post(wp_unslash($_POST['code_hint'])) : '',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'max_completions' => isset($_POST['max_completions']) ? absint($_POST['max_completions']) : 0,
            'cooldown_hours' => isset($_POST['cooldown_hours']) ? absint($_POST['cooldown_hours']) : 0,
        ];
        
        if ($is_edit) {
            ai_gemini_update_mission($mission_id, $data);
            echo '<div class="notice notice-success"><p>' . esc_html__('Mission updated successfully.', 'ai-gemini-image') . '</p></div>';
            $mission = ai_gemini_get_mission($mission_id);
        } else {
            $new_id = ai_gemini_create_mission($data);
            if ($new_id) {
                wp_redirect(admin_url('admin.php?page=ai-gemini-missions&action=edit&mission_id=' . $new_id . '&created=1'));
                exit;
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Failed to create mission.', 'ai-gemini-image') . '</p></div>';
            }
        }
    }
    
    if (isset($_GET['created'])) {
        echo '<div class="notice notice-success"><p>' . esc_html__('Mission created successfully. Now add codes for this mission.', 'ai-gemini-image') . '</p></div>';
    }
    
    $defaults = [
        'mission_key' => '',
        'title' => '',
        'description' => '',
        'reward_credits' => 5,
        'mission_type' => 'code_collect',
        'target_url' => '',
        'code_hint' => '',
        'is_active' => 1,
        'max_completions' => 0,
        'cooldown_hours' => 24,
    ];
    
    if ($mission) {
        $values = (array) $mission;
    } else {
        $values = $defaults;
    }
    
    ?>
    <div class="wrap">
        <h1>
            <?php echo $is_edit ? esc_html__('Edit Mission', 'ai-gemini-image') : esc_html__('Add New Mission', 'ai-gemini-image'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-gemini-missions')); ?>" class="page-title-action"><?php esc_html_e('Back to List', 'ai-gemini-image'); ?></a>
        </h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('ai_gemini_mission_form'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="mission_key"><?php esc_html_e('Mission Key', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="mission_key" id="mission_key" value="<?php echo esc_attr($values['mission_key']); ?>" class="regular-text" <?php echo $is_edit ? 'readonly' : ''; ?>>
                        <p class="description"><?php esc_html_e('Unique identifier for this mission. Leave empty to auto-generate.', 'ai-gemini-image'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="title"><?php esc_html_e('Title', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="title" id="title" value="<?php echo esc_attr($values['title']); ?>" class="regular-text" required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="description"><?php esc_html_e('Description', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <textarea name="description" id="description" rows="4" class="large-text"><?php echo esc_textarea($values['description']); ?></textarea>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="mission_type"><?php esc_html_e('Mission Type', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <select name="mission_type" id="mission_type">
                            <option value="code_collect" <?php selected($values['mission_type'], 'code_collect'); ?>>
                                <?php esc_html_e('Code Collect', 'ai-gemini-image'); ?>
                            </option>
                            <option value="social_share" <?php selected($values['mission_type'], 'social_share'); ?>>
                                <?php esc_html_e('Social Share', 'ai-gemini-image'); ?>
                            </option>
                            <option value="daily_login" <?php selected($values['mission_type'], 'daily_login'); ?>>
                                <?php esc_html_e('Daily Login', 'ai-gemini-image'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="reward_credits"><?php esc_html_e('Reward Credits', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="reward_credits" id="reward_credits" value="<?php echo esc_attr($values['reward_credits']); ?>" min="0" class="small-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="target_url"><?php esc_html_e('Target URL', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <input type="url" name="target_url" id="target_url" value="<?php echo esc_url($values['target_url']); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('URL where users will find the code (for code_collect type).', 'ai-gemini-image'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="code_hint"><?php esc_html_e('Code Hint', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <textarea name="code_hint" id="code_hint" rows="2" class="large-text"><?php echo esc_textarea($values['code_hint']); ?></textarea>
                        <p class="description"><?php esc_html_e('Hint to help users find the code on the target page.', 'ai-gemini-image'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_completions"><?php esc_html_e('Max Completions', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="max_completions" id="max_completions" value="<?php echo esc_attr($values['max_completions']); ?>" min="0" class="small-text">
                        <p class="description"><?php esc_html_e('Maximum total completions allowed (0 = unlimited).', 'ai-gemini-image'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="cooldown_hours"><?php esc_html_e('Cooldown Hours', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="cooldown_hours" id="cooldown_hours" value="<?php echo esc_attr($values['cooldown_hours']); ?>" min="0" class="small-text">
                        <p class="description"><?php esc_html_e('Hours between completions per user (0 = one-time only).', 'ai-gemini-image'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="is_active"><?php esc_html_e('Status', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_active" id="is_active" value="1" <?php checked($values['is_active'], 1); ?>>
                            <?php esc_html_e('Active', 'ai-gemini-image'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="ai_gemini_save_mission" class="button button-primary" value="<?php echo $is_edit ? esc_attr__('Update Mission', 'ai-gemini-image') : esc_attr__('Create Mission', 'ai-gemini-image'); ?>">
                
                <?php if ($is_edit) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ai-gemini-missions&action=codes&mission_id=' . $mission_id)); ?>" class="button">
                        <?php esc_html_e('Manage Codes', 'ai-gemini-image'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Mission codes page
 * 
 * @param int $mission_id Mission ID
 */
function ai_gemini_mission_codes_page($mission_id) {
    $mission = ai_gemini_get_mission($mission_id);
    
    if (!$mission) {
        wp_die(__('Mission not found.', 'ai-gemini-image'));
    }
    
    // Handle add code
    if (isset($_POST['ai_gemini_add_code']) && check_admin_referer('ai_gemini_code_action')) {
        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $expires_at = isset($_POST['expires_at']) && !empty($_POST['expires_at']) ? sanitize_text_field(wp_unslash($_POST['expires_at'])) : null;
        
        if (!empty($code)) {
            $code_id = ai_gemini_add_mission_code($mission_id, $code, $expires_at);
            if ($code_id) {
                echo '<div class="notice notice-success"><p>' . esc_html__('Code added successfully.', 'ai-gemini-image') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Failed to add code.', 'ai-gemini-image') . '</p></div>';
            }
        }
    }
    
    // Handle delete code
    if (isset($_POST['ai_gemini_delete_code']) && check_admin_referer('ai_gemini_code_action')) {
        $code_id = isset($_POST['code_id']) ? absint($_POST['code_id']) : 0;
        if ($code_id) {
            ai_gemini_delete_mission_code($code_id);
            echo '<div class="notice notice-success"><p>' . esc_html__('Code deleted successfully.', 'ai-gemini-image') . '</p></div>';
        }
    }
    
    // Handle toggle code
    if (isset($_POST['ai_gemini_toggle_code']) && check_admin_referer('ai_gemini_code_action')) {
        $code_id = isset($_POST['code_id']) ? absint($_POST['code_id']) : 0;
        $is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;
        if ($code_id) {
            ai_gemini_toggle_mission_code($code_id, $is_active);
            echo '<div class="notice notice-success"><p>' . esc_html__('Code updated successfully.', 'ai-gemini-image') . '</p></div>';
        }
    }
    
    $codes = ai_gemini_get_mission_codes($mission_id);
    
    ?>
    <div class="wrap">
        <h1>
            <?php printf(esc_html__('Codes for: %s', 'ai-gemini-image'), esc_html($mission->title)); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-gemini-missions')); ?>" class="page-title-action"><?php esc_html_e('Back to Missions', 'ai-gemini-image'); ?></a>
        </h1>
        
        <!-- Add Code Form -->
        <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 20px 0;">
            <h2><?php esc_html_e('Add New Code', 'ai-gemini-image'); ?></h2>
            <form method="post" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                <?php wp_nonce_field('ai_gemini_code_action'); ?>
                
                <div>
                    <label for="code"><?php esc_html_e('Static Code', 'ai-gemini-image'); ?></label><br>
                    <input type="text" name="code" id="code" required style="text-transform: uppercase;" placeholder="e.g., REWARD2024">
                </div>
                
                <div>
                    <label for="expires_at"><?php esc_html_e('Expires At (optional)', 'ai-gemini-image'); ?></label><br>
                    <input type="datetime-local" name="expires_at" id="expires_at">
                </div>
                
                <div>
                    <input type="submit" name="ai_gemini_add_code" class="button button-primary" value="<?php esc_attr_e('Add Code', 'ai-gemini-image'); ?>">
                </div>
            </form>
        </div>
        
        <!-- Codes Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php esc_html_e('ID', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Code', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Current TOTP', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Full Code (for testing)', 'ai-gemini-image'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Status', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Expires', 'ai-gemini-image'); ?></th>
                    <th style="width: 180px;"><?php esc_html_e('Actions', 'ai-gemini-image'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($codes) : ?>
                    <?php foreach ($codes as $code) : ?>
                        <?php 
                        $totp_info = ai_gemini_get_current_totp($code->totp_secret);
                        $is_expired = $code->expires_at && strtotime($code->expires_at) < time();
                        ?>
                        <tr class="<?php echo $is_expired ? 'expired' : ''; ?>">
                            <td><?php echo esc_html($code->id); ?></td>
                            <td><code><?php echo esc_html($code->code); ?></code></td>
                            <td>
                                <code class="totp-code" data-code-id="<?php echo esc_attr($code->id); ?>">
                                    <?php echo esc_html($totp_info['code']); ?>
                                </code>
                                <small>(<?php printf(esc_html__('%d sec left', 'ai-gemini-image'), $totp_info['expires_in']); ?>)</small>
                            </td>
                            <td>
                                <code class="full-code"><?php echo esc_html($code->code . '-' . $totp_info['code']); ?></code>
                                <button type="button" class="button button-small copy-code" data-code="<?php echo esc_attr($code->code . '-' . $totp_info['code']); ?>">
                                    <?php esc_html_e('Copy', 'ai-gemini-image'); ?>
                                </button>
                            </td>
                            <td>
                                <?php if ($is_expired) : ?>
                                    <span style="color: #999;"><?php esc_html_e('Expired', 'ai-gemini-image'); ?></span>
                                <?php elseif ($code->is_active) : ?>
                                    <span style="color: #46b450;"><?php esc_html_e('Active', 'ai-gemini-image'); ?></span>
                                <?php else : ?>
                                    <span style="color: #dc3232;"><?php esc_html_e('Inactive', 'ai-gemini-image'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if ($code->expires_at) {
                                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($code->expires_at)));
                                } else {
                                    esc_html_e('Never', 'ai-gemini-image');
                                }
                                ?>
                            </td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('ai_gemini_code_action'); ?>
                                    <input type="hidden" name="code_id" value="<?php echo esc_attr($code->id); ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $code->is_active ? '0' : '1'; ?>">
                                    <button type="submit" name="ai_gemini_toggle_code" class="button button-small">
                                        <?php echo $code->is_active ? esc_html__('Deactivate', 'ai-gemini-image') : esc_html__('Activate', 'ai-gemini-image'); ?>
                                    </button>
                                </form>
                                <form method="post" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this code?', 'ai-gemini-image'); ?>');">
                                    <?php wp_nonce_field('ai_gemini_code_action'); ?>
                                    <input type="hidden" name="code_id" value="<?php echo esc_attr($code->id); ?>">
                                    <button type="submit" name="ai_gemini_delete_code" class="button button-small button-link-delete">
                                        <?php esc_html_e('Delete', 'ai-gemini-image'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e('No codes found.', 'ai-gemini-image'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Shortcode Info -->
        <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 20px 0;">
            <h2><?php esc_html_e('Embedding Shortcode', 'ai-gemini-image'); ?></h2>
            <p><?php esc_html_e('Use this shortcode on the target website to display the live code:', 'ai-gemini-image'); ?></p>
            <code style="display: block; padding: 10px; background: #f5f5f5; border-radius: 4px;">
                [ai_gemini_mission_code mission_id="<?php echo esc_attr($mission_id); ?>"]
            </code>
        </div>
    </div>
    
    <style>
        tr.expired { opacity: 0.6; }
        .copy-code { vertical-align: middle; margin-left: 5px; }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Copy code functionality
        document.querySelectorAll('.copy-code').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var code = this.getAttribute('data-code');
                navigator.clipboard.writeText(code).then(function() {
                    btn.textContent = '<?php echo esc_js(__('Copied!', 'ai-gemini-image')); ?>';
                    setTimeout(function() {
                        btn.textContent = '<?php echo esc_js(__('Copy', 'ai-gemini-image')); ?>';
                    }, 2000);
                });
            });
        });
        
        // Auto-refresh TOTP codes every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    });
    </script>
    <?php
}

/**
 * Mission completions page
 * 
 * @param int $mission_id Mission ID
 */
function ai_gemini_mission_completions_page($mission_id) {
    global $wpdb;
    
    $mission = ai_gemini_get_mission($mission_id);
    
    if (!$mission) {
        wp_die(__('Mission not found.', 'ai-gemini-image'));
    }
    
    $table_completions = $wpdb->prefix . 'ai_gemini_mission_completions';
    
    $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;
    
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_completions WHERE mission_id = %d",
        $mission_id
    ));
    
    $completions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_completions WHERE mission_id = %d ORDER BY completed_at DESC LIMIT %d OFFSET %d",
        $mission_id,
        $per_page,
        $offset
    ));
    
    ?>
    <div class="wrap">
        <h1>
            <?php printf(esc_html__('Completions for: %s', 'ai-gemini-image'), esc_html($mission->title)); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-gemini-missions')); ?>" class="page-title-action"><?php esc_html_e('Back to Missions', 'ai-gemini-image'); ?></a>
        </h1>
        
        <p><?php printf(esc_html__('Total completions: %d', 'ai-gemini-image'), $total); ?></p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php esc_html_e('ID', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('User', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Code Used', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Credits Earned', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Completed At', 'ai-gemini-image'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($completions) : ?>
                    <?php foreach ($completions as $completion) : ?>
                        <tr>
                            <td><?php echo esc_html($completion->id); ?></td>
                            <td>
                                <?php
                                if ($completion->user_id) {
                                    $user = get_user_by('id', $completion->user_id);
                                    echo esc_html($user ? $user->user_login : 'User #' . $completion->user_id);
                                } else {
                                    echo '<code>' . esc_html($completion->guest_ip) . '</code>';
                                }
                                ?>
                            </td>
                            <td><code><?php echo esc_html($completion->code_used); ?></code></td>
                            <td><?php echo esc_html($completion->credits_earned); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($completion->completed_at))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e('No completions found.', 'ai-gemini-image'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php
        // Pagination
        $total_pages = ceil($total / $per_page);
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo wp_kses_post(paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'current' => $page,
                'total' => $total_pages,
            ]));
            echo '</div></div>';
        }
        ?>
    </div>
    <?php
}
