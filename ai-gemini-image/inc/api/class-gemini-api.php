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
    
    /**
     * API endpoint base URL
     */
    const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';
    
    /**
     * Model name for image generation
     */
    const MODEL_NAME = 'gemini-2.0-flash-exp-image-generation';
    
    /**
     * API key
     * 
     * @var string
     */
    private $api_key;
    
    /**
     * Last error message
     * 
     * @var string
     */
    private $last_error = '';
    
    /**
     * Constructor
     * 
     * @param string|null $api_key Optional API key, will use saved option if not provided
     */
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: ai_gemini_get_api_key();
    }
    
    /**
     * Check if API is configured
     * 
     * @return bool True if API key is set
     */
    public function is_configured() {
        return !empty($this->api_key);
    }
    
    /**
     * Get the last error message
     * 
     * @return string Last error message
     */
    public function get_last_error() {
        return $this->last_error;
    }
    
    /**
     * Generate image from source image and prompt
     * 
     * @param string $source_image Base64 encoded source image
     * @param string $prompt Text prompt for transformation
     * @param string $style Optional style preset
     * @return array|false Generated image data or false on failure
     */
    public function generate_image($source_image, $prompt, $style = '') {
        if (!$this->is_configured()) {
            $this->last_error = __('API key not configured', 'ai-gemini-image');
            return false;
        }
        
        // Validate image data and get MIME type info
        $image_info = null;
        $source_image = ai_gemini_validate_image_data($source_image, $image_info);
        if (!$source_image) {
            $this->last_error = __('Invalid image data', 'ai-gemini-image');
            return false;
        }
        
        // Use detected MIME type or fallback to jpeg
        $mime_type = isset($image_info['mime_type']) ? $image_info['mime_type'] : 'image/jpeg';
        
        // Build the full prompt with style
        $full_prompt = $this->build_prompt($prompt, $style);
        
        // Prepare request body
        $request_body = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'inlineData' => [
                                'mimeType' => $mime_type,
                                'data' => $source_image
                            ]
                        ],
                        [
                            'text' => $full_prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE']
            ]
        ];
        
        // Make API request
        $response = $this->make_request('generateContent', $request_body);
        
        if (!$response) {
            return false;
        }
        
        // Extract generated image from response
        return $this->parse_response($response);
    }
    
    /**
     * Build the full prompt with style modifiers
     * 
     * @param string $prompt Base prompt
     * @param string $style Style preset name
     * @return string Full prompt
     */
    private function build_prompt($prompt, $style = '') {
        $style_prompts = [
            'anime' => 'Transform this portrait photo into high-quality anime art style. Keep the person recognizable but apply anime aesthetics with vibrant colors, smooth skin, and expressive eyes.',
            'cartoon' => 'Transform this portrait photo into a Disney/Pixar style 3D cartoon character. Maintain likeness while applying cartoon aesthetics.',
            'oil_painting' => 'Transform this portrait photo into a classical oil painting style, reminiscent of Renaissance masters. Rich colors and dramatic lighting.',
            'watercolor' => 'Transform this portrait photo into a beautiful watercolor painting with soft edges, flowing colors, and artistic brush strokes.',
            'sketch' => 'Transform this portrait photo into a detailed pencil sketch with professional shading and artistic linework.',
            'pop_art' => 'Transform this portrait photo into bold pop art style like Andy Warhol, with vibrant colors and high contrast.',
            'cyberpunk' => 'Transform this portrait photo into cyberpunk style with neon colors, futuristic elements, and high-tech aesthetic.',
            'fantasy' => 'Transform this portrait photo into a fantasy style portrait with magical elements, ethereal lighting, and mystical atmosphere.',
        ];
        
        $base_prompt = isset($style_prompts[$style]) ? $style_prompts[$style] : $prompt;
        
        // Add safety and quality instructions
        $base_prompt .= ' Ensure the output is high quality, artistic, and appropriate for all audiences.';
        
        return $base_prompt;
    }
    
    /**
     * Make API request to Gemini
     * 
     * @param string $endpoint API endpoint
     * @param array $body Request body
     * @return array|false Response data or false on failure
     */
    private function make_request($endpoint, $body) {
        $url = sprintf(
            '%s/models/%s:%s?key=%s',
            self::API_BASE_URL,
            self::MODEL_NAME,
            $endpoint,
            $this->api_key
        );
        
        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 120, // Image generation can take time
        ];
        
        ai_gemini_log('Making API request to: ' . $endpoint, 'info');
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            ai_gemini_log('API request failed: ' . $this->last_error, 'error');
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $this->last_error = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : sprintf(__('API error: HTTP %d', 'ai-gemini-image'), $response_code);
            ai_gemini_log('API error response: ' . $response_body, 'error');
            return false;
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->last_error = __('Invalid JSON response from API', 'ai-gemini-image');
            return false;
        }
        
        return $data;
    }
    
    /**
     * Parse API response and extract image data
     * 
     * @param array $response API response data
     * @return array|false Image data or false on failure
     */
    private function parse_response($response) {
        if (!isset($response['candidates'][0]['content']['parts'])) {
            $this->last_error = __('Invalid response structure', 'ai-gemini-image');
            return false;
        }
        
        $parts = $response['candidates'][0]['content']['parts'];
        $result = [
            'image_data' => null,
            'mime_type' => null,
            'text' => '',
        ];
        
        foreach ($parts as $part) {
            if (isset($part['inlineData'])) {
                $result['image_data'] = $part['inlineData']['data'];
                $result['mime_type'] = $part['inlineData']['mimeType'];
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
    
    /**
     * Get available style presets
     * 
     * @return array Array of style preset options
     */
    public static function get_styles() {
        return [
            'anime' => __('Anime', 'ai-gemini-image'),
            'cartoon' => __('3D Cartoon', 'ai-gemini-image'),
            'oil_painting' => __('Oil Painting', 'ai-gemini-image'),
            'watercolor' => __('Watercolor', 'ai-gemini-image'),
            'sketch' => __('Pencil Sketch', 'ai-gemini-image'),
            'pop_art' => __('Pop Art', 'ai-gemini-image'),
            'cyberpunk' => __('Cyberpunk', 'ai-gemini-image'),
            'fantasy' => __('Fantasy', 'ai-gemini-image'),
        ];
    }
    
    /**
     * Test API connection
     * 
     * @return bool True if connection is successful
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            $this->last_error = __('API key not configured', 'ai-gemini-image');
            return false;
        }
        
        // Simple test request
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
