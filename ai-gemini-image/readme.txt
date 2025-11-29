=== AI Gemini Image Generator ===
Contributors: yourname
Tags: ai, image generator, gemini, google ai, art generator
Requires at least: 5.6
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Transform portrait photos into artistic images using Google Gemini 2.5 Flash Image API with a credit-based system.

== Description ==

AI Gemini Image Generator is a WordPress plugin that allows users to transform their portrait photos into various artistic styles using Google's Gemini AI.

**Features:**

* Multiple art styles (Anime, Cartoon, Oil Painting, Watercolor, Sketch, Pop Art, Cyberpunk, Fantasy)
* Credit-based system for monetization
* VietQR payment integration for Vietnam
* Watermarked previews with paid unlock for full quality
* User dashboard to view history
* Guest support with IP-based credits
* Admin dashboard with statistics
* REST API endpoints

**Shortcodes:**

* `[ai_gemini_generator]` - Display the image generation form
* `[ai_gemini_dashboard]` - Display user dashboard with history
* `[ai_gemini_buy_credit]` - Display credit purchase page

== Installation ==

1. Upload the `ai-gemini-image` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to AI Gemini > Settings to configure your Gemini API key
4. Configure VietQR payment settings if needed

== Configuration ==

**Gemini API Key:**

1. Go to [Google AI Studio](https://aistudio.google.com/apikey)
2. Create a new API key
3. Enter the key in AI Gemini > Settings

**VietQR Payment:**

1. Edit the settings in `inc/payment/vietqr-config.php`
2. Configure your bank ID, account number, and account name

**Watermark:**

The default watermark text can be changed in AI Gemini > Settings.

== Frequently Asked Questions ==

= What API key do I need? =

You need a Google Gemini API key from [Google AI Studio](https://aistudio.google.com/apikey).

= How does the credit system work? =

Users purchase credits to unlock full-resolution images. Preview generation can be free or require credits based on settings.

= Can guests use the plugin? =

Yes, guests can use the plugin with IP-based credit tracking.

== Screenshots ==

1. Image generator form
2. Generated preview with watermark
3. User dashboard
4. Credit purchase page
5. Admin settings

== Changelog ==

= 1.0.0 =
* Initial release
* Image generation with multiple styles
* Credit system
* VietQR payment integration
* Admin dashboard

== Upgrade Notice ==

= 1.0.0 =
Initial release.
