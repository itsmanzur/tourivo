<?php
/**
 * PHPStan bootstrap: declares runtime constants the plugin defines in tourivo.php / WordPress core,
 * so static analysis does not report them as undefined.
 */

define('ABSPATH', __DIR__ . '/');
define('TOURIVO_VERSION', '0.0.0');
define('TOURIVO_MIN_PHP_VER', '8.0');
define('TOURIVO_PLUGIN_FILE', dirname(__DIR__) . '/tourivo.php');
define('TOURIVO_PLUGIN_DIR', dirname(__DIR__) . '/');
define('TOURIVO_PLUGIN_URL', 'https://example.test/wp-content/plugins/tourivo/');
