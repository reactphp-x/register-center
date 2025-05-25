<?php

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use ReactphpX\RegisterCenter\Register;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

// Create event loop
$loop = Loop::get();

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
$center = new Register(8011, $loop, $logger);
$center->start();

$loop->addPeriodicTimer(5, function () use ($center) {
    $masters = $center->getConnectedMasters();
    
    if (empty($masters)) {
        echo "No masters connected\n";
        return;
    }
    
    $service = 'hello-wrold';
    $method1 = 'sayHello';
    $method2 = 'sayHello2';
    $method2Params = ['name' => 'John Doe'];


    $streams = $center->runOnAllMasters(function ($stream) use ($service, $method1, $method2, $method2Params) {
        $stream->write(\ReactphpX\RegisterCenter\ServiceRegistry::execute($service, $method1));
        $stream->end(\ReactphpX\RegisterCenter\ServiceRegistry::execute($service, $method2, $method2Params));
    });

    foreach ($streams as $masterId => $stream) {
        $stream->on('data', function ($data) use ($masterId) {
            if (is_array($data)) {  
                echo "Response from master $masterId: " . json_encode($data) . "\n";
            } else {
                echo "Response from master $masterId: $data\n";
            }
        });
    }

});

Loop::addPeriodicTimer(5, function () use ($center) {
    $services = $center->getServicesMaster();
    echo "Services: " . json_encode($services) . "\n";
});

// Run the loop
$loop->run(); 