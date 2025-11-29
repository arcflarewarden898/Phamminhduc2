<?php
/**
 * AI Gemini Image Generator - Watermark Functions
 * 
 * Functions for adding and removing watermarks from images.
 */

if (!defined('ABSPATH')) exit;

/**
 * Add watermark to image
 * 
 * @param string $image_data Binary image data
 * @param string $watermark_text Optional custom watermark text
 * @return string Watermarked image data
 */
function ai_gemini_add_watermark($image_data, $watermark_text = null) {
    // Default watermark text - CHANGE THIS TO YOUR DOMAIN
    $text = $watermark_text ?: get_option('ai_gemini_watermark_text', 'AI Gemini Preview');
    
    // Create image from string
    $image = @imagecreatefromstring($image_data);
    
    if (!$image) {
        ai_gemini_log('Failed to create image from string for watermark', 'error');
        return $image_data;
    }
    
    // Get image dimensions
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Create watermark color (white with transparency)
    $white = imagecolorallocatealpha($image, 255, 255, 255, 50);
    $shadow = imagecolorallocatealpha($image, 0, 0, 0, 80);
    
    // Try to use a font file, fallback to built-in font
    $font_file = AI_GEMINI_PLUGIN_DIR . 'assets/fonts/OpenSans-Bold.ttf';
    
    if (file_exists($font_file) && function_exists('imagettftext')) {
        // Calculate font size based on image width
        $font_size = max(12, min(48, $width / 20));
        
        // Get text bounding box
        $bbox = imagettfbbox($font_size, 0, $font_file, $text);
        $text_width = abs($bbox[4] - $bbox[0]);
        $text_height = abs($bbox[5] - $bbox[1]);
        
        // Position: bottom-right corner with padding
        $x = $width - $text_width - 20;
        $y = $height - 20;
        
        // Add shadow
        imagettftext($image, $font_size, 0, $x + 2, $y + 2, $shadow, $font_file, $text);
        
        // Add main text
        imagettftext($image, $font_size, 0, $x, $y, $white, $font_file, $text);
        
        // Add diagonal watermark pattern for security
        ai_gemini_add_diagonal_watermark($image, $text, $font_file, $font_size / 2);
    } else {
        // Fallback to built-in font
        $font = 5; // Largest built-in font
        $text_width = imagefontwidth($font) * strlen($text);
        $text_height = imagefontheight($font);
        
        $x = $width - $text_width - 10;
        $y = $height - $text_height - 10;
        
        // Add shadow
        imagestring($image, $font, $x + 1, $y + 1, $text, $shadow);
        
        // Add main text
        imagestring($image, $font, $x, $y, $text, $white);
    }
    
    // Output image to string
    ob_start();
    imagepng($image);
    $watermarked_data = ob_get_clean();
    
    // Clean up
    imagedestroy($image);
    
    return $watermarked_data;
}

/**
 * Add diagonal watermark pattern
 * 
 * @param resource $image GD image resource
 * @param string $text Watermark text
 * @param string $font_file Path to TTF font
 * @param float $font_size Font size
 */
function ai_gemini_add_diagonal_watermark($image, $text, $font_file, $font_size) {
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Very transparent color for diagonal pattern
    $color = imagecolorallocatealpha($image, 255, 255, 255, 110);
    
    // Add diagonal text pattern
    $angle = 45;
    $step_x = 300;
    $step_y = 200;
    
    for ($y = -$height; $y < $height * 2; $y += $step_y) {
        for ($x = -$width; $x < $width * 2; $x += $step_x) {
            if (function_exists('imagettftext')) {
                @imagettftext($image, $font_size, $angle, $x, $y, $color, $font_file, $text);
            }
        }
    }
}

/**
 * Remove watermark from image (for unlocked images)
 * 
 * Note: This is a placeholder. In a real implementation,
 * you should store the non-watermarked version separately
 * and return that instead of trying to remove watermarks.
 * 
 * @param string $image_data Binary image data
 * @return string Image data (same as input in this placeholder)
 */
function ai_gemini_remove_watermark($image_data) {
    // IMPORTANT: In a production environment, you should:
    // 1. Store the original (non-watermarked) image separately
    // 2. Return the stored original when unlocking
    // 
    // Programmatically removing watermarks is:
    // - Technically challenging
    // - Can degrade image quality
    // - Not recommended for production use
    
    // For now, we return the same image
    // The proper solution is to regenerate without watermark
    // or store both versions
    
    return $image_data;
}

/**
 * Get watermark settings
 * 
 * @return array Watermark settings
 */
function ai_gemini_get_watermark_settings() {
    return [
        'text' => get_option('ai_gemini_watermark_text', 'AI Gemini Preview'),
        'position' => get_option('ai_gemini_watermark_position', 'bottom-right'),
        'opacity' => (int) get_option('ai_gemini_watermark_opacity', 50),
        'diagonal' => get_option('ai_gemini_watermark_diagonal', 'yes') === 'yes',
    ];
}

/**
 * Update watermark settings
 * 
 * @param array $settings New settings
 * @return bool Success status
 */
function ai_gemini_update_watermark_settings($settings) {
    if (isset($settings['text'])) {
        update_option('ai_gemini_watermark_text', sanitize_text_field($settings['text']));
    }
    if (isset($settings['position'])) {
        update_option('ai_gemini_watermark_position', sanitize_text_field($settings['position']));
    }
    if (isset($settings['opacity'])) {
        update_option('ai_gemini_watermark_opacity', absint($settings['opacity']));
    }
    if (isset($settings['diagonal'])) {
        update_option('ai_gemini_watermark_diagonal', $settings['diagonal'] ? 'yes' : 'no');
    }
    
    return true;
}

/**
 * Create preview image with heavy watermark
 * 
 * @param string $image_data Binary image data
 * @return string Preview image data
 */
function ai_gemini_create_preview_image($image_data) {
    $image = @imagecreatefromstring($image_data);
    
    if (!$image) {
        return $image_data;
    }
    
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Reduce quality for preview
    // Resize to smaller dimension if too large
    $max_preview_size = 800;
    
    if ($width > $max_preview_size || $height > $max_preview_size) {
        $ratio = min($max_preview_size / $width, $max_preview_size / $height);
        $new_width = (int) ($width * $ratio);
        $new_height = (int) ($height * $ratio);
        
        $resized = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($image);
        $image = $resized;
    }
    
    // Output reduced quality
    ob_start();
    imagepng($image, null, 8); // Higher compression
    $preview_data = ob_get_clean();
    
    imagedestroy($image);
    
    // Add watermark to preview
    return ai_gemini_add_watermark($preview_data);
}
