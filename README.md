# ReactPHP Register Center

A flexible and powerful registration center implementation for ReactPHP applications, enabling service discovery and communication between distributed components.

## Features

- Service registration and discovery
- Master-slave architecture support
- Real-time communication between components
- Built-in logging support
- Asynchronous event-driven architecture
- Easy integration with existing ReactPHP applications

## Requirements

- PHP 8.1 or higher
- ReactPHP Socket ^1.16
- PSR-3 compatible logger

## Installation

You can install the package via composer:

```bash
composer require reactphp-x/register-center
```

## Basic Usage

### Starting a Registration Center

```php
use React\EventLoop\Loop;
use ReactphpX\RegisterCenter\Register;
use Psr\Log\LoggerInterface;

$loop = Loop::get();
$center = new Register(8010, $loop, $logger);
$center->start();

$loop->run();
```

### Example with Logging (using Monolog)

```php
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

// Create logger
$logger = new Logger('registration-center');
$handler = new StreamHandler('php://stdout', Level::Debug);
$handler->setFormatter(new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message% %context%\n",
    null,
    true,
    true
));
$logger->pushHandler($handler);

// Create and start registration center with logger
$center = new Register(8010, $loop, $logger);
$center->start();
```

### Running Code on Connected Masters

```php
$loop->addPeriodicTimer(5, function () use ($center) {
    $masters = $center->getConnectedMasters();
    
    if (empty($masters)) {
        echo "No masters connected\n";
        return;
    }
    
    $streams = $center->runOnAllMasters(function ($stream) {
        $stream->write("Hello from Registration Center!");
        
        $stream->on('data', function ($data) use ($stream) {
            echo "Received from master: $data\n";
            $stream->end("Thank you for your response!");
        });
        
        return $stream;
    });

    foreach ($streams as $masterId => $stream) {
        $stream->on('data', function ($data) use ($masterId) {
            echo "Response from master $masterId: $data\n";
        });
        $stream->write("Hello from Registration Center!");
    }
});
```

## Advanced Usage

For more advanced usage examples, please check the `examples/` directory in the repository:

- `examples/master.php`: Example of setting up a master node
- `examples/register.php`: Example of setting up a registration center

## Logging

The package supports any PSR-3 compatible logger. While not required, we recommend using Monolog for advanced logging capabilities:

```bash
composer require monolog/monolog
```

## Testing

```bash
./vendor/bin/phpunit
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.


## Support

If you have any questions or issues, please [create an issue](https://github.com/reactphp-x/register-center/issues/new) on GitHub. 