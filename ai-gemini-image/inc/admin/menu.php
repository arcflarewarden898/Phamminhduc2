<?php
/**
 * AI Gemini Image Generator - Admin Menu
 * 
 * Creates admin menu and dashboard pages.
 */

if (!defined('ABSPATH')) exit;

/**
 * Add admin menu items
 */
function ai_gemini_admin_menu() {
    // Main menu
    add_menu_page(
        __('AI Gemini', 'ai-gemini-image'),
        __('AI Gemini', 'ai-gemini-image'),
        'manage_options',
        'ai-gemini-dashboard',
        'ai_gemini_dashboard_page',
        'dashicons-format-image',
        30
    );
    
    // Dashboard submenu
    add_submenu_page(
        'ai-gemini-dashboard',
        __('Dashboard', 'ai-gemini-image'),
        __('Dashboard', 'ai-gemini-image'),
        'manage_options',
        'ai-gemini-dashboard',
        'ai_gemini_dashboard_page'
    );
    
    // Settings submenu
    add_submenu_page(
        'ai-gemini-dashboard',
        __('Settings', 'ai-gemini-image'),
        __('Settings', 'ai-gemini-image'),
        'manage_options',
        'ai-gemini-settings',
        'ai_gemini_settings_page'
    );
    
    // Credit Manager submenu
    add_submenu_page(
        'ai-gemini-dashboard',
        __('Credit Manager', 'ai-gemini-image'),
        __('Credit Manager', 'ai-gemini-image'),
        'manage_options',
        'ai-gemini-credits',
        'ai_gemini_credit_manager_page'
    );
    
    // Orders submenu
    add_submenu_page(
        'ai-gemini-dashboard',
        __('Orders', 'ai-gemini-image'),
        __('Orders', 'ai-gemini-image'),
        'manage_options',
        'ai-gemini-orders',
        'ai_gemini_orders_page'
    );
}
add_action('admin_menu', 'ai_gemini_admin_menu');

/**
 * Dashboard page content
 */
function ai_gemini_dashboard_page() {
    global $wpdb;
    
    // Get statistics
    $table_images = $wpdb->prefix . 'ai_gemini_images';
    $table_orders = $wpdb->prefix . 'ai_gemini_orders';
    
    $stats = [
        'total_images' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_images"),
        'unlocked_images' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_images WHERE is_unlocked = 1"),
        'total_orders' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_orders"),
        'completed_orders' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_orders WHERE status = 'completed'"),
        'total_revenue' => (int) $wpdb->get_var("SELECT SUM(amount) FROM $table_orders WHERE status = 'completed'"),
    ];
    
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('AI Gemini Dashboard', 'ai-gemini-image'); ?></h1>
        
        <div class="ai-gemini-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div class="stat-box" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #666;"><?php esc_html_e('Total Images', 'ai-gemini-image'); ?></h3>
                <p style="font-size: 32px; margin: 0; font-weight: bold; color: #0073aa;"><?php echo esc_html(number_format_i18n($stats['total_images'])); ?></p>
            </div>
            
            <div class="stat-box" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #666;"><?php esc_html_e('Unlocked Images', 'ai-gemini-image'); ?></h3>
                <p style="font-size: 32px; margin: 0; font-weight: bold; color: #46b450;"><?php echo esc_html(number_format_i18n($stats['unlocked_images'])); ?></p>
            </div>
            
            <div class="stat-box" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #666;"><?php esc_html_e('Completed Orders', 'ai-gemini-image'); ?></h3>
                <p style="font-size: 32px; margin: 0; font-weight: bold; color: #00a0d2;"><?php echo esc_html($stats['completed_orders'] . '/' . $stats['total_orders']); ?></p>
            </div>
            
            <div class="stat-box" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; color: #666;"><?php esc_html_e('Total Revenue', 'ai-gemini-image'); ?></h3>
                <p style="font-size: 32px; margin: 0; font-weight: bold; color: #826eb4;"><?php echo esc_html(number_format_i18n($stats['total_revenue'])); ?>đ</p>
            </div>
        </div>
        
        <div class="ai-gemini-quick-links" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px;">
            <h2><?php esc_html_e('Quick Links', 'ai-gemini-image'); ?></h2>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-gemini-settings')); ?>" class="button button-primary"><?php esc_html_e('Configure API Key', 'ai-gemini-image'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-gemini-credits')); ?>" class="button"><?php esc_html_e('Manage Credits', 'ai-gemini-image'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-gemini-orders')); ?>" class="button"><?php esc_html_e('View Orders', 'ai-gemini-image'); ?></a>
            </p>
        </div>
        
        <div class="ai-gemini-shortcodes" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px;">
            <h2><?php esc_html_e('Available Shortcodes', 'ai-gemini-image'); ?></h2>
            <table class="widefat" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Shortcode', 'ai-gemini-image'); ?></th>
                        <th><?php esc_html_e('Description', 'ai-gemini-image'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[ai_gemini_generator]</code></td>
                        <td><?php esc_html_e('Image generator form', 'ai-gemini-image'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ai_gemini_dashboard]</code></td>
                        <td><?php esc_html_e('User dashboard showing history and credits', 'ai-gemini-image'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[ai_gemini_buy_credit]</code></td>
                        <td><?php esc_html_e('Credit purchase page', 'ai-gemini-image'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/**
 * Settings page content
 */
function ai_gemini_settings_page() {
    // Handle form submission
    if (isset($_POST['ai_gemini_save_settings']) && check_admin_referer('ai_gemini_settings_nonce')) {
        $api_key = isset($_POST['ai_gemini_api_key']) ? sanitize_text_field(wp_unslash($_POST['ai_gemini_api_key'])) : '';
        $preview_credit = isset($_POST['ai_gemini_preview_credit']) ? absint($_POST['ai_gemini_preview_credit']) : 0;
        $unlock_credit = isset($_POST['ai_gemini_unlock_credit']) ? absint($_POST['ai_gemini_unlock_credit']) : 1;
        $free_trial_credits = isset($_POST['ai_gemini_free_trial_credits']) ? absint($_POST['ai_gemini_free_trial_credits']) : 1;
        
        update_option('ai_gemini_api_key', $api_key);
        update_option('ai_gemini_preview_credit', $preview_credit);
        update_option('ai_gemini_unlock_credit', $unlock_credit);
        update_option('ai_gemini_free_trial_credits', $free_trial_credits);
        
        echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'ai-gemini-image') . '</p></div>';
    }
    
    // Get current settings
    $api_key = get_option('ai_gemini_api_key', '');
    $preview_credit = get_option('ai_gemini_preview_credit', 0);
    $unlock_credit = get_option('ai_gemini_unlock_credit', 1);
    $free_trial_credits = get_option('ai_gemini_free_trial_credits', 1);
    
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('AI Gemini Settings', 'ai-gemini-image'); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('ai_gemini_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ai_gemini_api_key"><?php esc_html_e('Gemini API Key', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="ai_gemini_api_key" id="ai_gemini_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                        <p class="description">
                            <?php 
                            printf(
                                esc_html__('Get your API key from %s', 'ai-gemini-image'),
                                '<a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="ai_gemini_preview_credit"><?php esc_html_e('Preview Credit Cost', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="ai_gemini_preview_credit" id="ai_gemini_preview_credit" value="<?php echo esc_attr($preview_credit); ?>" min="0" class="small-text">
                        <p class="description"><?php esc_html_e('Credits required to generate a preview image (0 = free)', 'ai-gemini-image'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="ai_gemini_unlock_credit"><?php esc_html_e('Unlock Credit Cost', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="ai_gemini_unlock_credit" id="ai_gemini_unlock_credit" value="<?php echo esc_attr($unlock_credit); ?>" min="1" class="small-text">
                        <p class="description"><?php esc_html_e('Credits required to unlock full resolution image', 'ai-gemini-image'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="ai_gemini_free_trial_credits"><?php esc_html_e('Free Trial Credits', 'ai-gemini-image'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="ai_gemini_free_trial_credits" id="ai_gemini_free_trial_credits" value="<?php echo esc_attr($free_trial_credits); ?>" min="0" class="small-text">
                        <p class="description"><?php esc_html_e('Free credits given to new users (0 = no free trial)', 'ai-gemini-image'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="ai_gemini_save_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-gemini-image'); ?>">
            </p>
        </form>
    </div>
    <?php
}

/**
 * Orders page content
 */
function ai_gemini_orders_page() {
    global $wpdb;
    
    $table_orders = $wpdb->prefix . 'ai_gemini_orders';
    
    // Handle order status update
    if (isset($_POST['ai_gemini_update_order']) && check_admin_referer('ai_gemini_order_action')) {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $new_status = isset($_POST['new_status']) ? sanitize_text_field(wp_unslash($_POST['new_status'])) : '';
        
        if ($order_id && in_array($new_status, ['pending', 'completed', 'cancelled'])) {
            $updated = $wpdb->update(
                $table_orders,
                [
                    'status' => $new_status,
                    'completed_at' => $new_status === 'completed' ? current_time('mysql') : null
                ],
                ['id' => $order_id],
                ['%s', '%s'],
                ['%d']
            );
            
            // Add credits if completing order
            if ($new_status === 'completed' && $updated) {
                $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_orders WHERE id = %d", $order_id));
                if ($order && $order->user_id) {
                    ai_gemini_update_credit($order->credits, $order->user_id);
                }
            }
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Order updated successfully!', 'ai-gemini-image') . '</p></div>';
        }
    }
    
    // Get orders with pagination
    $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    $total_orders = $wpdb->get_var("SELECT COUNT(*) FROM $table_orders");
    $orders = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_orders ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));
    
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Orders', 'ai-gemini-image'); ?></h1>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Order Code', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('User', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Amount', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Credits', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Status', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Date', 'ai-gemini-image'); ?></th>
                    <th><?php esc_html_e('Actions', 'ai-gemini-image'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($orders) : ?>
                    <?php foreach ($orders as $order) : ?>
                        <tr>
                            <td><code><?php echo esc_html($order->order_code); ?></code></td>
                            <td>
                                <?php 
                                if ($order->user_id) {
                                    $user = get_user_by('id', $order->user_id);
                                    echo esc_html($user ? $user->user_login : 'User #' . $order->user_id);
                                } else {
                                    echo esc_html($order->guest_ip ?: 'Guest');
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html(number_format_i18n($order->amount)); ?>đ</td>
                            <td><?php echo esc_html(number_format_i18n($order->credits)); ?></td>
                            <td>
                                <span class="status-<?php echo esc_attr($order->status); ?>" style="padding: 3px 8px; border-radius: 3px; font-size: 12px; <?php 
                                    echo $order->status === 'completed' ? 'background: #d4edda; color: #155724;' : 
                                         ($order->status === 'cancelled' ? 'background: #f8d7da; color: #721c24;' : 'background: #fff3cd; color: #856404;');
                                ?>">
                                    <?php echo esc_html(ucfirst($order->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($order->created_at))); ?></td>
                            <td>
                                <?php if ($order->status === 'pending') : ?>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('ai_gemini_order_action'); ?>
                                        <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>">
                                        <input type="hidden" name="new_status" value="completed">
                                        <button type="submit" name="ai_gemini_update_order" class="button button-small button-primary"><?php esc_html_e('Complete', 'ai-gemini-image'); ?></button>
                                    </form>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('ai_gemini_order_action'); ?>
                                        <input type="hidden" name="order_id" value="<?php echo esc_attr($order->id); ?>">
                                        <input type="hidden" name="new_status" value="cancelled">
                                        <button type="submit" name="ai_gemini_update_order" class="button button-small"><?php esc_html_e('Cancel', 'ai-gemini-image'); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e('No orders found.', 'ai-gemini-image'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php
        // Pagination
        $total_pages = ceil($total_orders / $per_page);
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

/**
 * Enqueue admin styles
 */
function ai_gemini_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'ai-gemini') === false) {
        return;
    }
    
    wp_enqueue_style(
        'ai-gemini-admin',
        AI_GEMINI_PLUGIN_URL . 'assets/css/admin.css',
        [],
        AI_GEMINI_VERSION
    );
}
add_action('admin_enqueue_scripts', 'ai_gemini_admin_enqueue_scripts');
