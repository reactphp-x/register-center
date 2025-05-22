# ReactPHP-X Service Registry

A lightweight and efficient service registry for PHP applications, designed to work with ReactPHP ecosystem.

## Features

- Static service registry with easy-to-use API
- Support for metadata management
- Dynamic method execution on registered services
- Type-safe service instance management

## Installation

```bash
composer require reactphp-x/register-center
```

## Basic Usage

### Registering a Service

```php
use ReactphpX\RegisterCenter\ServiceRegistry;

// Create your service instance
class MyService {
    public function hello(string $name): string {
        return "Hello, {$name}!";
    }
}

// Register the service
$service = ServiceRegistry::register("myService", new MyService());
```

### Executing Service Methods

```php
// Execute a method on the service
try {
    $result = ServiceRegistry::execute("myService", "hello", ["World"]);
    echo $result; // Outputs: Hello, World!
} catch (\RuntimeException $e) {
    echo "Error: " . $e->getMessage();
}
```

### Working with Metadata

```php
// Register service with metadata
ServiceRegistry::register("myService", new MyService(), [
    "version" => "1.0",
    "environment" => "production"
]);

// Add metadata later
ServiceRegistry::addServiceMetadata("myService", "status", "active");

// Get services by metadata
$productionServices = ServiceRegistry::getServicesByMetadata("environment", "production");

// Get service metadata
$service = ServiceRegistry::get("myService");
$metadata = $service->getMetadata();
```

### Service Management

```php
// Check if service exists
if (ServiceRegistry::has("myService")) {
    // Get service instance
    $service = ServiceRegistry::get("myService");
}

// Remove a service
ServiceRegistry::remove("myService");

// Get all registered services
$allServices = ServiceRegistry::all();

// Clear all services
ServiceRegistry::clear();
```

## API Reference

### ServiceRegistry

#### Static Methods

- `register(string $name, object $instance, array $metadata = []): Service`
  - Registers a new service with optional metadata
  
- `execute(string $name, string $method, array $arguments = []): mixed`
  - Executes a method on the registered service
  
- `get(string $name): ?Service`
  - Retrieves a service by name
  
- `remove(string $name): void`
  - Removes a service by name
  
- `has(string $name): bool`
  - Checks if a service exists
  
- `all(): array`
  - Returns all registered services
  
- `clear(): void`
  - Removes all services
  
- `getServicesByMetadata(string $key, $value): array`
  - Finds services by metadata key-value pair
  
- `setServiceMetadata(string $name, array $metadata): void`
  - Sets metadata for a service
  
- `addServiceMetadata(string $name, string $key, $value): void`
  - Adds a single metadata entry to a service


### Service

#### Methods

- `getName(): string`
  - Gets the service name
  
- `getInstance(): object`
  - Gets the service instance
  
- `getMetadata(): array`
  - Gets all metadata
  
- `setMetadata(array $metadata): void`
  - Sets all metadata
  
- `addMetadata(string $key, $value): void`
  - Adds a single metadata entry
  
- `removeMetadata(string $key): void`
  - Removes a metadata entry
  
- `hasMetadata(string $key): bool`
  - Checks if a metadata key exists
  
- `getMetadataValue(string $key, $default = null): mixed`
  - Gets a metadata value with optional default

## License

MIT License 