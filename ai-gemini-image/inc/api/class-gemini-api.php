<?php
/**
 * AI Gemini Image Generator - Gemini API Class
 * 
 * Handles communication with Google Gemini API for image generation.
 */

if (!defined('ABSPATH')) exit;

/**
 * Class AI_GEMINI_API
 * 
 * Main class for interacting with Google Gemini 2.5 Flash Image API
 */
class AI_GEMINI_API {
    
    const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';
    const MODEL_NAME   = 'gemini-2.5-flash-image';
    
    private $api_key;
    private $last_error = '';
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: ai_gemini_get_api_key();
    }
    
    public function is_configured() {
        return !empty($this->api_key);
    }
    
    public function get_last_error() {
        return $this->last_error;
    }
    
    public function generate_image($source_image, $prompt, $style = '') {
        if (!$this->is_configured()) {
            $this->last_error = __('API key not configured', 'ai-gemini-image');
            return false;
        }
        
        $source_image = ai_gemini_validate_image_data($source_image);
        if (!$source_image) {
            $this->last_error = __('Invalid image data', 'ai-gemini-image');
            return false;
        }

        $decoded = base64_decode($source_image, true);
        if ($decoded === false) {
            $this->last_error = __('Failed to decode image data', 'ai-gemini-image');
            return false;
        }

        $optimized = $this->optimize_image_for_api($decoded);
        if (!$optimized || empty($optimized['binary']) || empty($optimized['mime_type'])) {
            $this->last_error = __('Failed to optimize image for API', 'ai-gemini-image');
            return false;
        }

        $optimized_base64 = base64_encode($optimized['binary']);
        $mime_type        = $optimized['mime_type'];

        // Dùng đúng prompt do bạn cung cấp
        $full_prompt = $this->build_prompt($prompt, $style);
        
        $request_body = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'inlineData' => [
                                'mimeType' => $mime_type,
                                'data'     => $optimized_base64,
                            ],
                        ],
                        [
                            'text' => $full_prompt,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                // Cấu hình tối thiểu, để Gemini chọn default an toàn
                'responseModalities' => ['IMAGE'],
            ],
        ];
        
        $response = $this->make_request('generateContent', $request_body);
        if (!$response) {
            return false;
        }
        
        return $this->parse_response($response);
    }

    private function optimize_image_for_api($binary) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->buffer($binary);

        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return false;
        }

        $src = @imagecreatefromstring($binary);
        if (!$src) {
            return false;
        }

        $width  = imagesx($src);
        $height = imagesy($src);

        if (!$width || !$height) {
            imagedestroy($src);
            return false;
        }

        $max_dim = 768;
        $scale   = min($max_dim / $width, $max_dim / $height, 1);

        $new_width  = (int) floor($width * $scale);
        $new_height = (int) floor($height * $scale);

        if ($scale < 1) {
            $dst   = imagecreatetruecolor($new_width, $new_height);
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst, 0, 0, $white);

            imagecopyresampled(
                $dst,
                $src,
                0,
                0,
                0,
                0,
                $new_width,
                $new_height,
                $width,
                $height
            );

            imagedestroy($src);
            $src = $dst;
        }

        ob_start();
        imagejpeg($src, null, 65);
        $jpeg_data = ob_get_clean();
        imagedestroy($src);

        if (!$jpeg_data) {
            return false;
        }

        return [
            'binary'    => $jpeg_data,
            'mime_type' => 'image/jpeg',
        ];
    }
    
    private function build_prompt($prompt, $style = '') {
        // Không thêm gì vào prompt, hoàn toàn do bạn kiểm soát
        return $prompt;
    }
    
    private function make_request($endpoint, $body) {
        $url = sprintf(
            '%s/models/%s:%s?key=%s',
            self::API_BASE_URL,
            self::MODEL_NAME,
            $endpoint,
            $this->api_key
        );
        
        $args = [
            'method'  => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 120,
        ];
        
        $max_retries = 2;
        $attempt     = 0;

        do {
            $attempt++;
            ai_gemini_log('Making API request to: ' . $endpoint . ' (attempt ' . $attempt . ')', 'info');

            $response = wp_remote_post($url, $args);

            if (is_wp_error($response)) {
                $this->last_error = $response->get_error_message();
                ai_gemini_log('API request failed: ' . $this->last_error, 'error');
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code === 200) {
                $data = json_decode($response_body, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->last_error = __('Invalid JSON response from API', 'ai-gemini-image');
                    return false;
                }

                return $data;
            }

            if ($response_code !== 500) {
                $error_data = json_decode($response_body, true);
                $this->last_error = isset($error_data['error']['message']) 
                    ? $error_data['error']['message'] 
                    : sprintf(__('API error: HTTP %d', 'ai-gemini-image'), $response_code);
                ai_gemini_log('API error response: ' . $response_body, 'error');
                return false;
            }

            // 500 INTERNAL
            $error_data = json_decode($response_body, true);
            $error_msg  = isset($error_data['error']['message']) ? $error_data['error']['message'] : '';

            ai_gemini_log('API 500 INTERNAL: ' . $error_msg, 'error');

            $should_retry = (strpos($error_msg, 'An internal error has occurred') !== false)
                || (isset($error_data['error']['status']) && $error_data['error']['status'] === 'INTERNAL');

            if (!$should_retry || $attempt > $max_retries) {
                $this->last_error = $error_msg ?: sprintf(__('API error: HTTP %d', 'ai-gemini-image'), $response_code);
                return false;
            }

            usleep(500000); // 0.5s

        } while ($attempt <= $max_retries);

        $this->last_error = __('API internal error after retries', 'ai-gemini-image');
        return false;
    }
    
    private function parse_response($response) {
        if (!isset($response['candidates'][0]['content']['parts'])) {
            $this->last_error = __('Invalid response structure', 'ai-gemini-image');
            return false;
        }
        
        $parts = $response['candidates'][0]['content']['parts'];
        $result = [
            'image_data' => null,
            'mime_type'  => null,
            'text'       => '',
        ];
        
        foreach ($parts as $part) {
            if (isset($part['inlineData'])) {
                $result['image_data'] = $part['inlineData']['data'];
                $result['mime_type']  = $part['inlineData']['mimeType'];
            } elseif (isset($part['text'])) {
                $result['text'] = $part['text'];
            }
        }
        
        if (!$result['image_data']) {
            $this->last_error = __('No image data in response', 'ai-gemini-image');
            ai_gemini_log('Response without image: ' . wp_json_encode($response), 'warning');
            return false;
        }
        
        return $result;
    }
    
    public static function get_styles() {
        return [
            'anime'       => __('Anime', 'ai-gemini-image'),
            'cartoon'     => __('3D Cartoon', 'ai-gemini-image'),
            'oil_painting'=> __('Oil Painting', 'ai-gemini-image'),
            'watercolor'  => __('Watercolor', 'ai-gemini-image'),
            'sketch'      => __('Pencil Sketch', 'ai-gemini-image'),
            'pop_art'     => __('Pop Art', 'ai-gemini-image'),
            'cyberpunk'   => __('Cyberpunk', 'ai-gemini-image'),
            'fantasy'     => __('Fantasy', 'ai-gemini-image'),
        ];
    }
    
    public function test_connection() {
        if (!$this->is_configured()) {
            $this->last_error = __('API key not configured', 'ai-gemini-image');
            return false;
        }
        
        $url = sprintf(
            '%s/models?key=%s',
            self::API_BASE_URL,
            $this->api_key
        );
        
        $response = wp_remote_get($url, ['timeout' => 10]);
        
        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            $this->last_error = sprintf(__('API test failed: HTTP %d', 'ai-gemini-image'), $response_code);
            return false;
        }
        
        return true;
    }
}