<?php

namespace ReactphpX\RegisterCenter;

class Service
{
    private string $name;
    private $instance;
    private array $metadata;

    public function __construct(string $name, object $instance, array $metadata = [])
    {
        $this->name = $name;
        $this->instance = $instance;
        $this->metadata = $metadata;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getInstance(): object
    {
        return $this->instance;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function addMetadata(string $key, $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function removeMetadata(string $key): void
    {
        unset($this->metadata[$key]);
    }

    public function hasMetadata(string $key): bool
    {
        return isset($this->metadata[$key]);
    }

    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            unserialize($data['instance']),
            $data['metadata'] ?? []
        );
    }
}