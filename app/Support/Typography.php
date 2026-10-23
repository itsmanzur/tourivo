<?php

declare(strict_types=1);

namespace Tourivo\Support;

use Tourivo\Config\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Typography
 *
 * Which font Tourivo uses and how it is loaded. By default Tourivo follows the theme's font and shows Bengali
 * characters in Anek Bangla, so it looks like part of the site and Bengali names read well even on an
 * English site. The font file is downloaded only on pages that contain Bengali.
 *
 * Modes (Settings -> Appearance):
 * - theme:  the theme's font for Latin, Anek Bangla for Bengali (default)
 * - anek:   Anek Bangla for everything
 * - system: the device's UI font for Latin, Anek Bangla for Bengali
 *
 * @package Tourivo\Support
 */
class Typography
{
    public const MODES  = ['theme', 'anek', 'system'];
    public const HANDLE = 'tourivo-fonts';

    /** The UI font stack WordPress admin uses. */
    public const SYSTEM_STACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

    public static function mode(): string
    {
        $mode = (string) Config::get('font_family', 'theme');

        return in_array($mode, self::MODES, true) ? $mode : 'theme';
    }

    /**
     * The font-family value for front-end Tourivo elements.
     */
    public static function frontendStack(?string $mode = null): string
    {
        return match ($mode ?? self::mode()) {
            'anek'   => '"Tourivo Anek", ' . self::SYSTEM_STACK,
            'system' => '"Tourivo Bangla", ' . self::SYSTEM_STACK,
            // The theme's family is read in the browser (see themeFontScript()); the system stack covers the moment before.
            default  => '"Tourivo Bangla", var(--trv-font-theme, ' . self::SYSTEM_STACK . ')',
        };
    }

    public static function adminStack(): string
    {
        return '"Tourivo Bangla", ' . self::SYSTEM_STACK;
    }

    /**
     * Load the font faces and the --trv-font / --trv-font-admin variables.
     */
    public static function enqueue(): void
    {
        if (wp_style_is(self::HANDLE, 'enqueued')) {
            return;
        }
        $file = TOURIVO_PLUGIN_DIR . 'assets/css/tourivo-fonts.css';
        wp_enqueue_style(self::HANDLE, TOURIVO_PLUGIN_URL . 'assets/css/tourivo-fonts.css', [], file_exists($file) ? (string) filemtime($file) : TOURIVO_VERSION);
        wp_add_inline_style(self::HANDLE, sprintf(
            ':root{--trv-font:%s;--trv-font-admin:%s;}',
            self::frontendStack(),
            self::adminStack()
        ));
    }

    /**
     * In "theme" mode, hand the theme's font family to Tourivo through --trv-font-theme. CSS cannot put a
     * fallback behind an inherited family, so the name is read once from the page body.
     */
    public static function registerThemeFontScript(): void
    {
        if (self::mode() !== 'theme') {
            return;
        }
        add_action('wp_body_open', [self::class, 'printThemeFontScript'], 1);
        add_action('wp_footer', [self::class, 'printThemeFontScript'], 1); // themes without wp_body_open
    }

    public static function printThemeFontScript(): void
    {
        // Printed on every front-end page (about 200 bytes): shortcodes and blocks load Tourivo's CSS only when
        // the content is rendered, which is after the body opens, and waiting for that would flash the wrong font.
        static $printed = false;
        if ($printed || is_admin()) {
            return;
        }
        $printed = true;
        wp_print_inline_script_tag(self::themeFontScript(), ['id' => 'tourivo-theme-font']);
    }

    public static function themeFontScript(): string
    {
        return "(function(){var r=document.documentElement;if(!document.body||r.style.getPropertyValue('--trv-font-theme')){return;}"
            . "var f=window.getComputedStyle(document.body).fontFamily;if(f){r.style.setProperty('--trv-font-theme',f);}})();";
    }
}
