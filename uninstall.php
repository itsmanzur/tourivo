<?php
/**
 * Tourivo Uninstall Handler
 *
 * @package Tourivo
 */

declare(strict_types=1);

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load autoloader if not present
if (!class_exists('Tourivo\\Core\\Uninstaller')) {
    $autoloader = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
    if (file_exists($autoloader)) {
        require_once $autoloader;
    } else {
        spl_autoload_register(static function (string $class) {
            $prefix = 'Tourivo\\';
            $baseDir = plugin_dir_path(__FILE__) . 'app/';
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }
            $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}

\Tourivo\Core\Uninstaller::uninstall();
