<?php

declare(strict_types=1);

namespace Tourivo\Common\Traits;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait Singleton
 *
 * Implements a thread-safe Singleton pattern for services and core orchestrators.
 *
 * @package Tourivo\Common\Traits
 */
trait Singleton
{
    /**
     * Singleton instance.
     *
     * @var static|null
     */
    protected static ?self $instance = null;

    /**
     * Protected constructor to prevent direct instantiation.
     */
    protected function __construct()
    {
    }

    /**
     * Prevent cloning.
     */
    protected function __clone()
    {
    }

    /**
     * Prevent unserializing.
     *
     * @throws \Exception
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize a singleton.');
    }

    /**
     * Get or create the singleton instance.
     *
     * @param mixed ...$args
     * @return static
     */
    public static function instance(...$args): static
    {
        if (static::$instance === null) {
            static::$instance = new static(...$args);
        }

        return static::$instance;
    }
}
