<?php
/**
 * AI Gemini Image Generator - TOTP System
 * 
 * Time-based One-Time Password implementation for mission verification.
 * Uses HMAC-SHA1 with a 15-minute time step (900 seconds).
 */

if (!defined('ABSPATH')) exit;

/**
 * Generate a random TOTP secret key
 * 
 * Uses cryptographically secure random generation.
 * 
 * @param int $length Length of the secret (default 16 characters)
 * @return string Base32 encoded secret
 */
function ai_gemini_generate_totp_secret($length = 16) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    
    return $secret;
}

/**
 * Decode base32 string to binary
 * 
 * @param string $base32 Base32 encoded string
 * @return string|false Binary string or false on failure
 */
function ai_gemini_base32_decode($base32) {
    $base32 = strtoupper($base32);
    $base32_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    
    $binary = '';
    $buffer = 0;
    $bits_left = 0;
    
    for ($i = 0; $i < strlen($base32); $i++) {
        $char = $base32[$i];
        
        if ($char === '=' || $char === ' ') {
            continue;
        }
        
        $val = strpos($base32_chars, $char);
        if ($val === false) {
            return false;
        }
        
        $buffer = ($buffer << 5) | $val;
        $bits_left += 5;
        
        if ($bits_left >= 8) {
            $bits_left -= 8;
            $binary .= chr(($buffer >> $bits_left) & 0xFF);
        }
    }
    
    return $binary;
}

/**
 * Generate TOTP code for a given secret
 * 
 * @param string $secret Base32 encoded secret
 * @param int|null $timestamp Unix timestamp (null for current time)
 * @param int $time_step Time step in seconds (default 900 = 15 minutes)
 * @param int $digits Number of digits in the OTP (default 6)
 * @return string|false OTP code or false on failure
 */
function ai_gemini_generate_totp($secret, $timestamp = null, $time_step = 900, $digits = 6) {
    if ($timestamp === null) {
        $timestamp = time();
    }
    
    // Decode the secret from base32
    $key = ai_gemini_base32_decode($secret);
    if ($key === false) {
        return false;
    }
    
    // Calculate the time counter
    $counter = floor($timestamp / $time_step);
    
    // Pack counter into 8 bytes (big-endian)
    $counter_bytes = pack('N*', 0, $counter);
    
    // Calculate HMAC-SHA1
    $hash = hash_hmac('sha1', $counter_bytes, $key, true);
    
    // Get offset from last 4 bits of hash
    $offset = ord($hash[19]) & 0x0F;
    
    // Get 4 bytes from offset, mask first bit (to get positive 31-bit value)
    $binary = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    );
    
    // Generate OTP value
    $otp = $binary % pow(10, $digits);
    
    // Pad with leading zeros
    return str_pad((string)$otp, $digits, '0', STR_PAD_LEFT);
}

/**
 * Validate TOTP code
 * 
 * @param string $secret Base32 encoded secret
 * @param string $otp OTP to validate
 * @param int $window Number of time steps to check before/after current (default 1)
 * @param int $time_step Time step in seconds (default 900 = 15 minutes)
 * @return bool True if valid
 */
function ai_gemini_validate_totp($secret, $otp, $window = 1, $time_step = 900) {
    $otp = trim($otp);
    
    if (strlen($otp) !== 6 || !ctype_digit($otp)) {
        return false;
    }
    
    $timestamp = time();
    
    // Check current and adjacent time windows
    for ($i = -$window; $i <= $window; $i++) {
        $check_time = $timestamp + ($i * $time_step);
        $expected_otp = ai_gemini_generate_totp($secret, $check_time, $time_step);
        
        if ($expected_otp !== false && hash_equals($expected_otp, $otp)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get current TOTP code for display (for website embedding)
 * 
 * @param string $secret Base32 encoded secret
 * @param int $time_step Time step in seconds (default 900 = 15 minutes)
 * @return array Array with 'code' and 'expires_in' (seconds until expiry)
 */
function ai_gemini_get_current_totp($secret, $time_step = 900) {
    $timestamp = time();
    $counter = floor($timestamp / $time_step);
    $expires_at = ($counter + 1) * $time_step;
    $expires_in = $expires_at - $timestamp;
    
    return [
        'code' => ai_gemini_generate_totp($secret, $timestamp, $time_step),
        'expires_in' => $expires_in,
        'expires_at' => $expires_at,
    ];
}

/**
 * Get remaining time for current TOTP window
 * 
 * @param int $time_step Time step in seconds (default 900 = 15 minutes)
 * @return int Seconds remaining in current window
 */
function ai_gemini_totp_time_remaining($time_step = 900) {
    $timestamp = time();
    $counter = floor($timestamp / $time_step);
    $expires_at = ($counter + 1) * $time_step;
    
    return $expires_at - $timestamp;
}

/**
 * Format TOTP code with separator (e.g., "123456" -> "123 456")
 * 
 * @param string $code TOTP code
 * @return string Formatted code
 */
function ai_gemini_format_totp_code($code) {
    if (strlen($code) === 6) {
        return substr($code, 0, 3) . ' ' . substr($code, 3);
    }
    return $code;
}
