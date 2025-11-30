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
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    dbDelta($sql_guest_credits);
    dbDelta($sql_orders);
    dbDelta($sql_images);
    dbDelta($sql_transactions);
    
    // Set plugin version
    update_option('ai_gemini_db_version', AI_GEMINI_VERSION);
    
    ai_gemini_log('Database tables installed successfully', 'info');
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
