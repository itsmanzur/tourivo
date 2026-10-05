<?php
/**
 * Plugin Name:       Tourivo
 * Plugin URI:        https://tourivo.com
 * Description:       The Next-Gen Travel, Tour & Accommodation Booking Engine for WordPress.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Tourivo Team
 * Author URI:        https://tourivo.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tourivo
 * Domain Path:       /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// 1. Define Global Constants
define('TOURIVO_VERSION', '1.2.0');
define('TOURIVO_MIN_PHP_VER', '8.0');
define('TOURIVO_PLUGIN_FILE', __FILE__);
define('TOURIVO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TOURIVO_PLUGIN_URL', plugin_dir_url(__FILE__));

// 2. Minimum PHP Version Check
if (version_compare(PHP_VERSION, TOURIVO_MIN_PHP_VER, '<')) {
    add_action('admin_notices', static function () {
        $message = sprintf(
            /* translators: 1: Required PHP version, 2: Current PHP version */
            esc_html__('Tourivo requires PHP version %1$s or greater. You are running version %2$s. Please upgrade your PHP version.', 'tourivo'),
            TOURIVO_MIN_PHP_VER,
            PHP_VERSION
        );
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    });
    return;
}

// 3. PSR-4 Autoloading Setup
if (file_exists(TOURIVO_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once TOURIVO_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Fallback PSR-4 Autoloader for Tourivo\ namespace
    spl_autoload_register(static function (string $class) {
        $prefix = 'Tourivo\\';
        $baseDir = TOURIVO_PLUGIN_DIR . 'app/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });
}

// 4. Register Activation & Deactivation Hooks
register_activation_hook(TOURIVO_PLUGIN_FILE, ['Tourivo\\Core\\Installer', 'activate']);
register_deactivation_hook(TOURIVO_PLUGIN_FILE, ['Tourivo\\Core\\Deactivator', 'deactivate']);

// 5. Load Public Developer Functions & Template Engine API
require_once TOURIVO_PLUGIN_DIR . 'app/Support/functions.php';

// 6. Global Accessor Helper
if (!function_exists('tourivo')) {
    /**
     * Get the main Tourivo plugin orchestrator instance.
     *
     * @return \Tourivo\Core\Plugin
     */
    function tourivo(): \Tourivo\Core\Plugin
    {
        return \Tourivo\Core\Plugin::instance();
    }
}

// 7. Multi-language Locale Filter & Textdomain Loader
add_filter(
    'plugin_locale',
    static function (string $locale, string $domain): string {
        if ('tourivo' !== $domain) {
            return $locale;
        }

        $lang = (string) \Tourivo\Config\Config::get('plugin_language', 'default');
        if ('bn' === $lang) {
            return 'bn_BD';
        }
        if ('en' === $lang) {
            return 'en_US';
        }

        return $locale;
    },
    10,
    2
);

add_action('init', static function (): void {
    tourivo_load_plugin_textdomain();
}, 1);

// 8. Bootstrap Plugin on plugins_loaded
add_action('plugins_loaded', static function () {
    tourivo()->boot();
}, 10);
