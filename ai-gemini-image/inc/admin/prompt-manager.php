<?php
/**
 * AI Gemini Image Generator - Prompt Manager
 * 
 * Admin interface for managing style prompts.
 */

if (!defined('ABSPATH')) exit;

/**
 * Render prompt manager page
 */
function ai_gemini_prompt_manager_page() {
    global $wpdb;
    
    $table_prompts = $wpdb->prefix . 'ai_gemini_prompts';
    $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
    $prompt_id = isset($_GET['prompt_id']) ? absint($_GET['prompt_id']) : 0;
    
    // Handle form submissions
    if (isset($_POST['ai_gemini_save_prompt']) && check_admin_referer('ai_gemini_prompt_action')) {
        ai_gemini_handle_prompt_save();
    }
    
    if (isset($_POST['ai_gemini_delete_prompt']) && check_admin_referer('ai_gemini_prompt_action')) {
        ai_gemini_handle_prompt_delete();
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'toggle' && check_admin_referer('ai_gemini_toggle_prompt')) {
        ai_gemini_handle_prompt_toggle($prompt_id);
    }
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Prompt Manager', 'ai-gemini-image'); ?></h1>
        
        <?php if ($action === 'list') : ?>
            <a href="<?php echo esc_url(add_query_arg('action', 'add')); ?>" class="page-title-action">
                <?php esc_html_e('Add New Prompt', 'ai-gemini-image'); ?>
            </a>
            <?php ai_gemini_render_prompt_list(); ?>
        <?php elseif ($action === 'add' || $action === 'edit') : ?>
            <?php ai_gemini_render_prompt_form($action, $prompt_id); ?>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render prompt list
 */
function ai_gemini_render_prompt_list() {
    global $wpdb;
    
    $table_prompts = $wpdb->prefix . 'ai_gemini_prompts';
    $prompts = $wpdb->get_results("SELECT * FROM $table_prompts ORDER BY display_order ASC, id ASC");
    
    ?>
    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
        <thead>
            <tr>
                <th style="width: 60px;"><?php esc_html_e('Order', 'ai-gemini-image'); ?></th>
                <th><?php esc_html_e('Key', 'ai-gemini-image'); ?></th>
                <th><?php esc_html_e('Name', 'ai-gemini-image'); ?></th>
                <th><?php esc_html_e('Description', 'ai-gemini-image'); ?></th>
                <th style="width: 80px;"><?php esc_html_e('Status', 'ai-gemini-image'); ?></th>
                <th style="width: 150px;"><?php esc_html_e('Actions', 'ai-gemini-image'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($prompts) : ?>
                <?php foreach ($prompts as $prompt) : ?>
                    <tr>
                        <td><?php echo esc_html($prompt->display_order); ?></td>
                        <td><code><?php echo esc_html($prompt->prompt_key); ?></code></td>
                        <td><strong><?php echo esc_html($prompt->prompt_name); ?></strong></td>
                        <td><?php echo esc_html(wp_trim_words($prompt->description, 10, '...')); ?></td>
                        <td>
                            <?php if ($prompt->is_active) : ?>
                                <span style="color: #46b450;">&#9679; <?php esc_html_e('Active', 'ai-gemini-image'); ?></span>
                            <?php else : ?>
                                <span style="color: #dc3232;">&#9679; <?php esc_html_e('Inactive', 'ai-gemini-image'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'prompt_id' => $prompt->id])); ?>" class="button button-small">
                                <?php esc_html_e('Edit', 'ai-gemini-image'); ?>
                            </a>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'toggle', 'prompt_id' => $prompt->id]), 'ai_gemini_toggle_prompt')); ?>" class="button button-small">
                                <?php echo $prompt->is_active ? esc_html__('Deactivate', 'ai-gemini-image') : esc_html__('Activate', 'ai-gemini-image'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6"><?php esc_html_e('No prompts found. Click "Add New Prompt" to create one.', 'ai-gemini-image'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="ai-gemini-prompt-help" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px;">
        <h3><?php esc_html_e('How Prompts Work', 'ai-gemini-image'); ?></h3>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><?php esc_html_e('Prompts define the transformation styles available to users.', 'ai-gemini-image'); ?></li>
            <li><?php esc_html_e('The "Key" is used internally to identify the style.', 'ai-gemini-image'); ?></li>
            <li><?php esc_html_e('The "Name" is displayed to users in the style selector.', 'ai-gemini-image'); ?></li>
            <li><?php esc_html_e('The "Prompt Text" is sent to the AI to generate images.', 'ai-gemini-image'); ?></li>
            <li><?php esc_html_e('Only active prompts are shown to users.', 'ai-gemini-image'); ?></li>
            <li><?php esc_html_e('Use "Display Order" to control the order of styles in the selector.', 'ai-gemini-image'); ?></li>
        </ul>
    </div>
    <?php
}

/**
 * Render prompt add/edit form
 * 
 * @param string $action Form action (add or edit)
 * @param int $prompt_id Prompt ID for editing
 */
function ai_gemini_render_prompt_form($action, $prompt_id) {
    global $wpdb;
    
    $prompt = null;
    if ($action === 'edit' && $prompt_id) {
        $table_prompts = $wpdb->prefix . 'ai_gemini_prompts';
        $prompt = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_prompts WHERE id = %d", $prompt_id));
        
        if (!$prompt) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Prompt not found.', 'ai-gemini-image') . '</p></div>';
            return;
        }
    }
    
    ?>
    <h2><?php echo $action === 'edit' ? esc_html__('Edit Prompt', 'ai-gemini-image') : esc_html__('Add New Prompt', 'ai-gemini-image'); ?></h2>
    
    <form method="post" action="" style="max-width: 800px;">
        <?php wp_nonce_field('ai_gemini_prompt_action'); ?>
        <input type="hidden" name="prompt_id" value="<?php echo esc_attr($prompt_id); ?>">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="prompt_key"><?php esc_html_e('Prompt Key', 'ai-gemini-image'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" name="prompt_key" id="prompt_key" 
                           value="<?php echo esc_attr($prompt ? $prompt->prompt_key : ''); ?>" 
                           class="regular-text" required 
                           pattern="[a-z0-9_]+" 
                           <?php echo $action === 'edit' ? 'readonly' : ''; ?>>
                    <p class="description"><?php esc_html_e('Unique identifier (lowercase letters, numbers, underscores only). Cannot be changed after creation.', 'ai-gemini-image'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="prompt_name"><?php esc_html_e('Display Name', 'ai-gemini-image'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" name="prompt_name" id="prompt_name" 
                           value="<?php echo esc_attr($prompt ? $prompt->prompt_name : ''); ?>" 
                           class="regular-text" required>
                    <p class="description"><?php esc_html_e('Name shown to users in the style selector.', 'ai-gemini-image'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="prompt_text"><?php esc_html_e('Prompt Text', 'ai-gemini-image'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <textarea name="prompt_text" id="prompt_text" rows="5" class="large-text" required><?php echo esc_textarea($prompt ? $prompt->prompt_text : ''); ?></textarea>
                    <p class="description"><?php esc_html_e('The instruction sent to the AI. Be specific about the transformation style desired.', 'ai-gemini-image'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="description"><?php esc_html_e('Description', 'ai-gemini-image'); ?></label>
                </th>
                <td>
                    <textarea name="description" id="description" rows="2" class="large-text"><?php echo esc_textarea($prompt ? $prompt->description : ''); ?></textarea>
                    <p class="description"><?php esc_html_e('Optional description for admin reference.', 'ai-gemini-image'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="display_order"><?php esc_html_e('Display Order', 'ai-gemini-image'); ?></label>
                </th>
                <td>
                    <input type="number" name="display_order" id="display_order" 
                           value="<?php echo esc_attr($prompt ? $prompt->display_order : 0); ?>" 
                           min="0" class="small-text">
                    <p class="description"><?php esc_html_e('Lower numbers appear first in the style selector.', 'ai-gemini-image'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="is_active"><?php esc_html_e('Status', 'ai-gemini-image'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="is_active" id="is_active" value="1" 
                               <?php checked($prompt ? $prompt->is_active : 1, 1); ?>>
                        <?php esc_html_e('Active (visible to users)', 'ai-gemini-image'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="ai_gemini_save_prompt" class="button button-primary" 
                   value="<?php echo $action === 'edit' ? esc_attr__('Update Prompt', 'ai-gemini-image') : esc_attr__('Add Prompt', 'ai-gemini-image'); ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-gemini-prompts')); ?>" class="button">
                <?php esc_html_e('Cancel', 'ai-gemini-image'); ?>
            </a>
            
            <?php if ($action === 'edit') : ?>
                <button type="submit" name="ai_gemini_delete_prompt" class="button button-link-delete" 
                        onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this prompt?', 'ai-gemini-image'); ?>');">
                    <?php esc_html_e('Delete Prompt', 'ai-gemini-image'); ?>
                </button>
            <?php endif; ?>
        </p>
    </form>
    <?php
}

/**
 * Handle prompt save
 */
function ai_gemini_handle_prompt_save() {
    global $wpdb;
    
    $table_prompts = $wpdb->prefix . 'ai_gemini_prompts';
    
    $prompt_id = isset($_POST['prompt_id']) ? absint($_POST['prompt_id']) : 0;
    $prompt_key = isset($_POST['prompt_key']) ? sanitize_key(wp_unslash($_POST['prompt_key'])) : '';
    $prompt_name = isset($_POST['prompt_name']) ? sanitize_text_field(wp_unslash($_POST['prompt_name'])) : '';
    $prompt_text = isset($_POST['prompt_text']) ? sanitize_textarea_field(wp_unslash($_POST['prompt_text'])) : '';
    $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
    $display_order = isset($_POST['display_order']) ? absint($_POST['display_order']) : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($prompt_key) || empty($prompt_name) || empty($prompt_text)) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Please fill in all required fields.', 'ai-gemini-image') . '</p></div>';
        return;
    }
    
    if ($prompt_id) {
        // Update existing prompt
        $result = $wpdb->update(
            $table_prompts,
            [
                'prompt_name' => $prompt_name,
                'prompt_text' => $prompt_text,
                'description' => $description,
                'display_order' => $display_order,
                'is_active' => $is_active,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $prompt_id],
            ['%s', '%s', '%s', '%d', '%d', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Prompt updated successfully!', 'ai-gemini-image') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to update prompt.', 'ai-gemini-image') . '</p></div>';
        }
    } else {
        // Check if key already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_prompts WHERE prompt_key = %s",
            $prompt_key
        ));
        
        if ($exists) {
            echo '<div class="notice notice-error"><p>' . esc_html__('A prompt with this key already exists.', 'ai-gemini-image') . '</p></div>';
            return;
        }
        
        // Insert new prompt
        $result = $wpdb->insert(
            $table_prompts,
            [
                'prompt_key' => $prompt_key,
                'prompt_name' => $prompt_name,
                'prompt_text' => $prompt_text,
                'description' => $description,
                'display_order' => $display_order,
                'is_active' => $is_active,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );
        
        if ($result) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Prompt added successfully!', 'ai-gemini-image') . '</p></div>';
            // Redirect to list
            echo '<script>window.location.href = "' . esc_url(admin_url('admin.php?page=ai-gemini-prompts')) . '";</script>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to add prompt.', 'ai-gemini-image') . '</p></div>';
        }
    }
}

/**
 * Handle prompt delete
 */
function ai_gemini_handle_prompt_delete() {
    global $wpdb;
    
    $prompt_id = isset($_POST['prompt_id']) ? absint($_POST['prompt_id']) : 0;
    
    if (!$prompt_id) {
        return;
    }
    
    $table_prompts = $wpdb->prefix . 'ai_gemini_prompts';
    $result = $wpdb->delete($table_prompts, ['id' => $prompt_id], ['%d']);
    
    if ($result) {
        echo '<div class="notice notice-success"><p>' . esc_html__('Prompt deleted successfully!', 'ai-gemini-image') . '</p></div>';
        echo '<script>window.location.href = "' . esc_url(admin_url('admin.php?page=ai-gemini-prompts')) . '";</script>';
    } else {
        echo '<div class="notice notice-error"><p>' . esc_html__('Failed to delete prompt.', 'ai-gemini-image') . '</p></div>';
    }
}

/**
 * Handle prompt toggle (activate/deactivate)
 * 
 * @param int $prompt_id Prompt ID
 */
function ai_gemini_handle_prompt_toggle($prompt_id) {
    global $wpdb;
    
    if (!$prompt_id) {
        return;
    }
    
    $table_prompts = $wpdb->prefix . 'ai_gemini_prompts';
    $prompt = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_prompts WHERE id = %d", $prompt_id));
    
    if (!$prompt) {
        return;
    }
    
    $new_status = $prompt->is_active ? 0 : 1;
    $wpdb->update(
        $table_prompts,
        ['is_active' => $new_status, 'updated_at' => current_time('mysql')],
        ['id' => $prompt_id],
        ['%d', '%s'],
        ['%d']
    );
    
    wp_safe_redirect(admin_url('admin.php?page=ai-gemini-prompts'));
    exit;
}

/**
 * Get prompts from database
 * 
 * @param bool $active_only Whether to return only active prompts
 * @return array Array of prompts
 */
function ai_gemini_get_prompts($active_only = true) {
    global $wpdb;
    
    $table_prompts = $wpdb->prefix . 'ai_gemini_prompts';
    
    $where = $active_only ? 'WHERE is_active = 1' : '';
    $prompts = $wpdb->get_results("SELECT * FROM $table_prompts $where ORDER BY display_order ASC, id ASC");
    
    return $prompts ?: [];
}

/**
 * Get prompt text by key
 * 
 * @param string $key Prompt key
 * @return string|null Prompt text or null if not found
 */
function ai_gemini_get_prompt_text($key) {
    global $wpdb;
    
    $table_prompts = $wpdb->prefix . 'ai_gemini_prompts';
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT prompt_text FROM $table_prompts WHERE prompt_key = %s AND is_active = 1",
        $key
    ));
}
