<?php
/**
 * AI Gemini Image Generator - Database Installation
 * 
 * Creates necessary database tables on plugin activation.
 */

if (!defined('ABSPATH')) exit;

/**
 * Install database tables
 */
function ai_gemini_install_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Guest credits table
    $table_guest_credits = $wpdb->prefix . 'ai_gemini_guest_credits';
    $sql_guest_credits = "CREATE TABLE IF NOT EXISTS $table_guest_credits (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        ip varchar(45) NOT NULL,
        credits int(11) NOT NULL DEFAULT 0,
        used_trial tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ip (ip),
        KEY credits (credits)
    ) $charset_collate;";
    
    // Credit orders table
    $table_orders = $wpdb->prefix . 'ai_gemini_orders';
    $sql_orders = "CREATE TABLE IF NOT EXISTS $table_orders (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED DEFAULT NULL,
        guest_ip varchar(45) DEFAULT NULL,
        order_code varchar(50) NOT NULL,
        amount int(11) NOT NULL,
        credits int(11) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        payment_method varchar(50) DEFAULT 'vietqr',
        transaction_id varchar(100) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        completed_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY order_code (order_code),
        KEY user_id (user_id),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    // Generated images table
    $table_images = $wpdb->prefix . 'ai_gemini_images';
    $sql_images = "CREATE TABLE IF NOT EXISTS $table_images (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED DEFAULT NULL,
        guest_ip varchar(45) DEFAULT NULL,
        original_image_url text,
        preview_image_url text,
        full_image_url text,
        prompt text,
        style varchar(100) DEFAULT NULL,
        is_unlocked tinyint(1) NOT NULL DEFAULT 0,
        credits_used int(11) NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        expires_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY guest_ip (guest_ip),
        KEY is_unlocked (is_unlocked),
        KEY expires_at (expires_at)
    ) $charset_collate;";
    
    // Credit transactions table
    $table_transactions = $wpdb->prefix . 'ai_gemini_transactions';
    $sql_transactions = "CREATE TABLE IF NOT EXISTS $table_transactions (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED DEFAULT NULL,
        guest_ip varchar(45) DEFAULT NULL,
        type varchar(50) NOT NULL,
        amount int(11) NOT NULL,
        balance_after int(11) NOT NULL,
        description text,
        reference_id bigint(20) UNSIGNED DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY type (type),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    // Prompts table for managing style prompts
    $table_prompts = $wpdb->prefix . 'ai_gemini_prompts';
    $sql_prompts = "CREATE TABLE IF NOT EXISTS $table_prompts (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        prompt_key varchar(50) NOT NULL,
        prompt_name varchar(100) NOT NULL,
        prompt_text text NOT NULL,
        description text,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        display_order int(11) NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY prompt_key (prompt_key),
        KEY is_active (is_active),
        KEY display_order (display_order)
    ) $charset_collate;";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    dbDelta($sql_guest_credits);
    dbDelta($sql_orders);
    dbDelta($sql_images);
    dbDelta($sql_transactions);
    dbDelta($sql_prompts);
    
    // Seed default prompts if table is empty
    ai_gemini_seed_default_prompts();
    
    // Set plugin version
    update_option('ai_gemini_db_version', AI_GEMINI_VERSION);
    
    ai_gemini_log('Database tables installed successfully', 'info');
}

/**
 * Seed default prompts
 */
function ai_gemini_seed_default_prompts() {
    global $wpdb;
    
    $table_prompts = $wpdb->prefix . 'ai_gemini_prompts';
    
    // Check if prompts already exist
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_prompts");
    if ($count > 0) {
        return;
    }
    
    $default_prompts = [
        [
            'prompt_key' => 'anime',
            'prompt_name' => 'Anime',
            'prompt_text' => 'Transform this portrait photo into high-quality anime art style. Keep the person recognizable but apply anime aesthetics with vibrant colors, smooth skin, and expressive eyes.',
            'description' => 'Japanese animation style with vibrant colors and expressive features',
            'display_order' => 1,
        ],
        [
            'prompt_key' => 'cartoon',
            'prompt_name' => '3D Cartoon',
            'prompt_text' => 'Transform this portrait photo into a Disney/Pixar style 3D cartoon character. Maintain likeness while applying cartoon aesthetics.',
            'description' => 'Disney/Pixar inspired 3D cartoon style',
            'display_order' => 2,
        ],
        [
            'prompt_key' => 'oil_painting',
            'prompt_name' => 'Oil Painting',
            'prompt_text' => 'Transform this portrait photo into a classical oil painting style, reminiscent of Renaissance masters. Rich colors and dramatic lighting.',
            'description' => 'Classical oil painting with Renaissance master aesthetics',
            'display_order' => 3,
        ],
        [
            'prompt_key' => 'watercolor',
            'prompt_name' => 'Watercolor',
            'prompt_text' => 'Transform this portrait photo into a beautiful watercolor painting with soft edges, flowing colors, and artistic brush strokes.',
            'description' => 'Soft watercolor painting with artistic brush strokes',
            'display_order' => 4,
        ],
        [
            'prompt_key' => 'sketch',
            'prompt_name' => 'Pencil Sketch',
            'prompt_text' => 'Transform this portrait photo into a detailed pencil sketch with professional shading and artistic linework.',
            'description' => 'Detailed pencil sketch with professional shading',
            'display_order' => 5,
        ],
        [
            'prompt_key' => 'pop_art',
            'prompt_name' => 'Pop Art',
            'prompt_text' => 'Transform this portrait photo into bold pop art style like Andy Warhol, with vibrant colors and high contrast.',
            'description' => 'Andy Warhol inspired pop art with bold colors',
            'display_order' => 6,
        ],
        [
            'prompt_key' => 'cyberpunk',
            'prompt_name' => 'Cyberpunk',
            'prompt_text' => 'Transform this portrait photo into cyberpunk style with neon colors, futuristic elements, and high-tech aesthetic.',
            'description' => 'Futuristic cyberpunk with neon and high-tech aesthetics',
            'display_order' => 7,
        ],
        [
            'prompt_key' => 'fantasy',
            'prompt_name' => 'Fantasy',
            'prompt_text' => 'Transform this portrait photo into a fantasy style portrait with magical elements, ethereal lighting, and mystical atmosphere.',
            'description' => 'Magical fantasy style with ethereal lighting',
            'display_order' => 8,
        ],
    ];
    
    foreach ($default_prompts as $prompt) {
        $wpdb->insert(
            $table_prompts,
            [
                'prompt_key' => $prompt['prompt_key'],
                'prompt_name' => $prompt['prompt_name'],
                'prompt_text' => $prompt['prompt_text'],
                'description' => $prompt['description'],
                'is_active' => 1,
                'display_order' => $prompt['display_order'],
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );
    }
}

/**
 * Check and update database if needed
 */
function ai_gemini_check_db_update() {
    $installed_version = get_option('ai_gemini_db_version', '0');
    
    if (version_compare($installed_version, AI_GEMINI_VERSION, '<')) {
        ai_gemini_install_tables();
    }
}
add_action('plugins_loaded', 'ai_gemini_check_db_update');
