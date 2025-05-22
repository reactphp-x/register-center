<?php

namespace ReactphpX\RegisterCenter;

class ServiceRegistry
{
    private static array $services = [];

    public static function register(string $name, object $instance, array $metadata = []): Service
    {
        $service = new Service($name, $instance, $metadata);
        self::$services[$name] = $service;
        return $service;
    }

    public static function get(string $name): ?Service
    {
        return self::$services[$name] ?? null;
    }

    public static function remove(string $name): void
    {
        unset(self::$services[$name]);
    }

    public static function has(string $name): bool
    {
        return isset(self::$services[$name]);
    }

    public static function all(): array
    {
        return self::$services;
    }

    public static function clear(): void
    {
        self::$services = [];
    }

    public static function getServicesByMetadata(string $key, $value): array
    {
        return array_filter(self::$services, function (Service $service) use ($key, $value) {
            $metadata = $service->getMetadata();
            return isset($metadata[$key]) && $metadata[$key] === $value;
        });
    }

    public static function setServiceMetadata(string $name, array $metadata): void
    {
        if (isset(self::$services[$name])) {
            self::$services[$name]->setMetadata($metadata);
        }
    }

    public static function addServiceMetadata(string $name, string $key, $value): void
    {
        if (isset(self::$services[$name])) {
            self::$services[$name]->addMetadata($key, $value);
        }
    }

    /**
     * Execute a method on the service instance
     *
     * @param string $name Service name
     * @param string $method Method name to call
     * @param array $arguments Arguments to pass to the method
     * @return mixed|null Returns the result of the method call or null if service not found
     * @throws \RuntimeException If method doesn't exist
     */
    public static function execute(string $name, string $method, array $arguments = []): mixed
    {
        if (!isset(self::$services[$name])) {
            throw new \RuntimeException("Service '{$name}' not found");
        }

        $service = self::$services[$name];
        $instance = $service->getInstance();

        if (!method_exists($instance, $method)) {
            throw new \RuntimeException("Method '{$method}' not found in service '{$name}'");
        }

        return $instance->$method(...$arguments);
    }
} 