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
$center = new Register(8010, $loop, $logger);
$center->start();

// Example of running code on connected masters
// $loop->addPeriodicTimer(5, function () use ($center) {
//     $masters = $center->getConnectedMasters();
    
//     if (empty($masters)) {
//         echo "No masters connected\n";
//         return;
//     }
    
//     echo "Running code on all connected masters...\n";
    
//     $streams = $center->runOnAllMasters(function ($stream) {
//         // This code will run on each master
//         $stream->write("Hello from Registration Center!");
        
//         $stream->on('data', function ($data) use ($stream) {
//             echo "Received from Registration Center: $data\n";
//             // Send response back
//             $stream->end("Thank you for your response!");
//         });
        
//         return $stream;
//     });
    
//     // Process response streams from masters
//     foreach ($streams as $masterId => $stream) {
//         $stream->on('data', function ($data) use ($masterId) {
//             echo "Response from master $masterId: $data\n";
//         });
//         $stream->write("Hello from Registration Center!");
//     }
// });

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

Loop::addTimer(10, function () use ($center) {
    // 通知master 有8011 的注册中心可以连接
    $center->writeRawMessageToAllMasters([
        'cmd' => 'register',
        'registers' => [
            [
                'host' => '127.0.0.1',
                'port' => 8011,
            ]
        ]
    ]);
});

Loop::addTimer(20, function () use ($center) {
    // 通知master 有8011 的注册中心可以连接
    $center->writeRawMessageToAllMasters([
        'cmd' => 'remove',
        'registers' => [
            [
                'host' => '127.0.0.1',
                'port' => 8011,
            ]
        ]
    ]);
});

// Run the loop
$loop->run(); 