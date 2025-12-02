<?php
/**
 * AI Gemini Image Generator - Install / DB Setup
 *
 * Tạo và nâng cấp các bảng database cần thiết cho plugin.
 */

if (!defined('ABSPATH')) exit;

/**
 * Tạo / nâng cấp bảng DB khi kích hoạt plugin.
 */
function ai_gemini_install_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $prefix          = $wpdb->prefix;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $tables_sql = [];

    // 1. Bảng guest credits
    $tables_sql[] = "
        CREATE TABLE {$prefix}ai_gemini_guest_credits (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ip VARCHAR(64) NOT NULL,
            credits INT(11) NOT NULL DEFAULT 0,
            used_trial TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY ip (ip)
        ) $charset_collate;
    ";

    // 2. Bảng orders
    $tables_sql[] = "
        CREATE TABLE {$prefix}ai_gemini_orders (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NULL,
            guest_ip VARCHAR(64) NULL,
            order_code VARCHAR(64) NOT NULL,
            amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            credits INT(11) NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            payment_method VARCHAR(32) DEFAULT NULL,
            transaction_id VARCHAR(128) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_code (order_code),
            KEY user_id (user_id),
            KEY guest_ip (guest_ip),
            KEY status (status)
        ) $charset_collate;
    ";

    // 3. Bảng images (THÊM cột gemini_file_uri)
    $tables_sql[] = "
        CREATE TABLE {$prefix}ai_gemini_images (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NULL,
            guest_ip VARCHAR(64) NULL,
            original_image_url TEXT NULL,
            preview_image_url TEXT NULL,
            full_image_url TEXT NULL,
            prompt LONGTEXT NULL,
            style VARCHAR(191) NULL,
            is_unlocked TINYINT(1) NOT NULL DEFAULT 0,
            credits_used INT(11) NOT NULL DEFAULT 0,
            gemini_file_uri TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY guest_ip (guest_ip),
            KEY is_unlocked (is_unlocked),
            KEY style (style)
        ) $charset_collate;
    ";

    // 4. Bảng transactions
    $tables_sql[] = "
        CREATE TABLE {$prefix}ai_gemini_transactions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NULL,
            guest_ip VARCHAR(64) NULL,
            type VARCHAR(64) NOT NULL,
            amount INT(11) NOT NULL DEFAULT 0,
            balance_after INT(11) NOT NULL DEFAULT 0,
            description TEXT NULL,
            reference_id BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY guest_ip (guest_ip),
            KEY type (type)
        ) $charset_collate;
    ";

    // 5. Bảng prompts
    $tables_sql[] = "
        CREATE TABLE {$prefix}ai_gemini_prompts (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(191) NOT NULL,
            title VARCHAR(255) NOT NULL,
            prompt_text LONGTEXT NOT NULL,
            sample_image TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY is_active (is_active)
        ) $charset_collate;
    ";

    // 6. Bảng missions
    $tables_sql[] = "
        CREATE TABLE {$prefix}ai_gemini_missions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            steps LONGTEXT NOT NULL,
            reward INT(11) NOT NULL DEFAULT 0,
            code VARCHAR(32) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY is_active (is_active)
        ) $charset_collate;
    ";

    foreach ($tables_sql as $sql) {
        dbDelta($sql);
    }

    update_option('ai_gemini_db_version', '1.1.0');
}