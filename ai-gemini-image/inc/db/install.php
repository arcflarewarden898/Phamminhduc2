<?php
if (!defined('ABSPATH')) exit;

function ai_gemini_install_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // ... (Giữ các bảng 1-6 như cũ) ...
    
    // 7. Mission Logs (CẤU TRÚC CHUẨN)
    $table_mission_logs = $wpdb->prefix . 'ai_gemini_mission_logs';
    $sql_mission_logs = "CREATE TABLE IF NOT EXISTS $table_mission_logs (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        mission_id mediumint(9) NOT NULL,
        otp_code varchar(20) NOT NULL, -- Cột bắt buộc phải có
        user_id bigint(20) UNSIGNED DEFAULT NULL, -- Giữ lại cho tương thích
        guest_ip varchar(45) NOT NULL,
        meta text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        verified_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY mission_id (mission_id),
        KEY otp_code (otp_code)
    ) $charset_collate;";
    
    // 8. Mission Stats
    $table_mission_stats = $wpdb->prefix . 'ai_gemini_mission_stats';
    $sql_mission_stats = "CREATE TABLE IF NOT EXISTS $table_mission_stats (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        mission_id mediumint(9) NOT NULL,
        date date NOT NULL,
        views int(11) DEFAULT 0,
        completed int(11) DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY mission_date (mission_id, date)
    ) $charset_collate;";

    // Chạy dbDelta cho tất cả các bảng (tôi rút gọn các bảng trên để tập trung vào logs)
    dbDelta($sql_mission_logs);
    dbDelta($sql_mission_stats);
    
    // --- FORCE FIX CỘT THIẾU CHO BẢNG LOGS ---
    $row_otp = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '$table_mission_logs' AND column_name = 'otp_code'");
    if (empty($row_otp)) {
        $wpdb->query("ALTER TABLE $table_mission_logs ADD COLUMN otp_code varchar(20) NOT NULL");
    }
    
    update_option('ai_gemini_db_version', AI_GEMINI_VERSION);
}
add_action('plugins_loaded', 'ai_gemini_install_tables'); // Chạy luôn khi load để fix ngay