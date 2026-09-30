<?php

declare(strict_types=1);

namespace Tourivo\Common;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Container
 *
 * A lightweight, PSR-11 inspired Dependency Injection Container with automatic autowiring.
 *
 * @package Tourivo\Common
 */
class Container
{
    /**
     * Stored global instance.
     *
     * @var self|null
     */
    protected static ?self $instance = null;

    /**
     * Stored bindings.
     *
     * @var array<string, mixed>
     */
    protected array $bindings = [];

    /**
     * Stored singleton instances.
     *
     * @var array<string, object>
     */
    protected array $instances = [];

    /**
     * Get the global Container instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            if (class_exists(\Tourivo\Core\Plugin::class)) {
                self::$instance = \Tourivo\Core\Plugin::instance()->container();
            } else {
                self::$instance = new self();
            }
        }

        return self::$instance;
    }

    /**
     * Set the global Container instance.
     *
     * @param self $instance
     * @return void
     */
    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Bind a class or interface to a resolver.
     *
     * @param string $id
     * @param mixed  $concrete
     * @return void
     */
    public function bind(string $id, mixed $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $id;
        }

        $this->bindings[$id] = $concrete;
    }

    /**
     * Bind a singleton instance or resolver.
     *
     * @param string $id
     * @param mixed  $concrete
     * @return void
     */
    public function singleton(string $id, mixed $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $id;
        }

        $this->bindings[$id] = [
            'concrete' => $concrete,
            'shared'   => true,
        ];
    }

    /**
     * Store an already resolved instance.
     *
     * @param string $id
     * @param object $instance
     * @return object
     */
    public function instance(string $id, object $instance): object
    {
        $this->instances[$id] = $instance;
        return $instance;
    }

    /**
     * Check if a binding or instance exists.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * Resolve an entry from the container by identifier.
     *
     * @param string $id
     * @return mixed
     * @throws Exception
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            // Attempt autowiring if class exists
            if (class_exists($id)) {
                return $this->resolveClass($id);
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new Exception(esc_html(sprintf('Target [%s] is not bound in Tourivo container and cannot be resolved.', $id)));
        }

        $binding = $this->bindings[$id];
        $isShared = is_array($binding) && ($binding['shared'] ?? false);
        $concrete = is_array($binding) ? $binding['concrete'] : $binding;

        if ($concrete instanceof Closure) {
            $object = $concrete($this);
        } elseif (is_string($concrete) && class_exists($concrete)) {
            $object = $this->resolveClass($concrete);
        } else {
            $object = $concrete;
        }

        if ($isShared && is_object($object)) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    /**
     * Resolve a class using reflection and recursive dependency resolution.
     *
     * @param string $className
     * @return object
     * @throws Exception
     */
    protected function resolveClass(string $className): object
    {
        try {
            $reflector = new ReflectionClass($className);
        } catch (ReflectionException $e) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new Exception(esc_html(sprintf('Class [%s] does not exist: %s', $className, $e->getMessage())), 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new Exception(esc_html(sprintf('Class [%s] is not instantiable.', $className)));
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $className();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependencies[] = $this->resolveParameter($parameter);
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve a constructor parameter.
     *
     * @param ReflectionParameter $parameter
     * @return mixed
     * @throws Exception
     */
    protected function resolveParameter(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $dependencyClass = $type->getName();
            return $this->get($dependencyClass);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        throw new Exception(esc_html(sprintf('Unresolvable parameter [%s] for dependency injection.', $parameter->getName())));
    }
}
