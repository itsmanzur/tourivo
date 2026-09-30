<?php

declare(strict_types=1);

namespace Tourivo\Common\Abstracts;

use Tourivo\Common\Container;
use Tourivo\Common\Traits\Hookable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Controller
 *
 * Base controller for handling HTTP, AJAX, REST API or Admin requests.
 *
 * @package Tourivo\Common\Abstracts
 */
abstract class Controller
{
    use Hookable;

    /**
     * The container instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Controller constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Send a JSON success response.
     *
     * @param mixed $data
     * @param int   $statusCode
     * @return void
     */
    protected function jsonSuccess(mixed $data = null, int $statusCode = 200): void
    {
        status_header($statusCode);
        wp_send_json_success($data, $statusCode);
    }

    /**
     * Send a JSON error response.
     *
     * @param mixed $data
     * @param int   $statusCode
     * @return void
     */
    protected function jsonError(mixed $data = null, int $statusCode = 400): void
    {
        status_header($statusCode);
        wp_send_json_error($data, $statusCode);
    }

    /**
     * Render a template file with extracted parameters.
     *
     * @param string $templatePath Relative to plugin templates directory
     * @param array  $args
     * @return string
     */
    protected function render(string $templatePath, array $args = []): string
    {
        $template = untrailingslashit(TOURIVO_PLUGIN_DIR) . '/templates/' . ltrim($templatePath, '/');

        // Check if theme overrides exist: {theme}/tourivo/{templatePath}
        $themeTemplate = locate_template(['tourivo/' . ltrim($templatePath, '/')]);
        if (!empty($themeTemplate) && file_exists($themeTemplate)) {
            $template = $themeTemplate;
        }

        if (!file_exists($template)) {
            return '';
        }

        extract($args, EXTR_SKIP);

        ob_start();
        include $template;
        return ob_get_clean() ?: '';
    }
}
