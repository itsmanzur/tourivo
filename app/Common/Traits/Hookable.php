<?php

declare(strict_types=1);

namespace Tourivo\Common\Traits;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait Hookable
 *
 * Provides expressive syntax for registering WordPress action and filter hooks.
 *
 * @package Tourivo\Common\Traits
 */
trait Hookable
{
    /**
     * Add an action hook.
     *
     * @param string   $tag             The name of the action to which the $callback is hooked.
     * @param callable $callback        The callback to be executed.
     * @param int      $priority        Priority. Default 10.
     * @param int      $acceptedArgs    Number of accepted arguments. Default 1.
     * @return bool
     */
    public function addAction(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return (bool) add_action($tag, $callback, $priority, $acceptedArgs);
    }

    /**
     * Add a filter hook.
     *
     * @param string   $tag             The name of the filter to which the $callback is hooked.
     * @param callable $callback        The callback to be executed.
     * @param int      $priority        Priority. Default 10.
     * @param int      $acceptedArgs    Number of accepted arguments. Default 1.
     * @return bool
     */
    public function addFilter(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return (bool) add_filter($tag, $callback, $priority, $acceptedArgs);
    }
}
