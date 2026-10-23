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
 * Which fonts Tourivo uses and how they are loaded.
 *
 * Text, forms, prices and buttons use Anek Bangla by default: its variable file carries every weight
 * Tourivo needs, so the booking panel looks the same on every theme (a theme's body font is often a reading
 * serif with one weight loaded, which turns small UI text muddy and fakes bold). Headings follow the theme's
 * heading font so pages still belong to the site; Bengali in them falls back to Anek Bangla.
 *
 * Modes (Settings -> Appearance -> Font):
 * - anek:     Anek Bangla for text, the theme's heading font for headings (default)
 * - anek_all: Anek Bangla for text and headings
 * - theme:    the theme's fonts for text and headings, Anek Bangla for Bengali
 *
 * @package Tourivo\Support
 */
class Typography
{
    public const MODES  = ['anek', 'anek_all', 'theme'];
    public const HANDLE = 'tourivo-fonts';

    /** The UI font stack WordPress admin uses. */
    public const SYSTEM_STACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

    public static function mode(): string
    {
        $mode = (string) Config::get('font_family', 'anek');

        return in_array($mode, self::MODES, true) ? $mode : 'anek';
    }

    /**
     * font-family values for front-end text and headings.
     *
     * @return array{text: string, heading: string}
     */
    public static function frontendStacks(?string $mode = null): array
    {
        // The theme's families are read in the browser (themeFontScript()); the fallbacks cover the moment before.
        $themeText    = 'var(--trv-font-theme, ' . self::SYSTEM_STACK . ')';
        $themeHeading = 'var(--trv-font-theme-heading, ' . $themeText . ')';

        return match ($mode ?? self::mode()) {
            'anek_all' => ['text' => '"Tourivo Anek", ' . self::SYSTEM_STACK, 'heading' => '"Tourivo Anek", ' . self::SYSTEM_STACK],
            'theme'    => ['text' => '"Tourivo Bangla", ' . $themeText, 'heading' => '"Tourivo Bangla", ' . $themeHeading],
            default    => ['text' => '"Tourivo Anek", ' . self::SYSTEM_STACK, 'heading' => '"Tourivo Bangla", ' . $themeHeading],
        };
    }

    public static function adminStack(): string
    {
        return '"Tourivo Bangla", ' . self::SYSTEM_STACK;
    }

    /**
     * Load the font faces and the --trv-font, --trv-font-heading and --trv-font-admin variables.
     */
    public static function enqueue(): void
    {
        if (wp_style_is(self::HANDLE, 'enqueued')) {
            return;
        }
        $file = TOURIVO_PLUGIN_DIR . 'assets/css/tourivo-fonts.css';
        $stacks = self::frontendStacks();
        wp_enqueue_style(self::HANDLE, TOURIVO_PLUGIN_URL . 'assets/css/tourivo-fonts.css', [], file_exists($file) ? (string) filemtime($file) : TOURIVO_VERSION);
        wp_add_inline_style(self::HANDLE, sprintf(
            ':root{--trv-font:%s;--trv-font-heading:%s;--trv-font-admin:%s;}',
            $stacks['text'],
            $stacks['heading'],
            self::adminStack()
        ));
    }

    /**
     * Hand the theme's text and heading families to Tourivo (--trv-font-theme, --trv-font-theme-heading).
     * CSS cannot put a fallback behind an inherited family, so the names are read once in the browser.
     */
    public static function registerThemeFontScript(): void
    {
        if (self::mode() === 'anek_all') {
            return;
        }
        add_action('wp_body_open', [self::class, 'printThemeFontScript'], 1);
        add_action('wp_footer', [self::class, 'printThemeFontScript'], 1); // themes without wp_body_open
    }

    public static function printThemeFontScript(): void
    {
        // Printed on every front-end page (a few hundred bytes): shortcodes and blocks load Tourivo's CSS only
        // when the content is rendered, which is after the body opens, and waiting for that would flash fonts.
        static $printed = false;
        if ($printed || is_admin()) {
            return;
        }
        $printed = true;
        wp_print_inline_script_tag(self::themeFontScript(), ['id' => 'tourivo-theme-font']);
    }

    public static function themeFontScript(): string
    {
        return "(function(){var r=document.documentElement,b=document.body;if(!b||r.style.getPropertyValue('--trv-font-theme')){return;}"
            . "var t=window.getComputedStyle(b).fontFamily;if(t){r.style.setProperty('--trv-font-theme',t);}"
            . "var h=document.createElement('h2');h.style.cssText='position:absolute;visibility:hidden;margin:0';b.appendChild(h);"
            . "var f=window.getComputedStyle(h).fontFamily;b.removeChild(h);if(f){r.style.setProperty('--trv-font-theme-heading',f);}})();";
    }
}
