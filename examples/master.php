<?php

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use ReactphpX\RegisterCenter\Master;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

// Create logger
$logger = new Logger('master');
$handler = new StreamHandler('php://stdout', Level::Debug);
$handler->setFormatter(new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message% %context%\n",
    null,
    true,
    true
));
$logger->pushHandler($handler);

// Create master with logger and custom settings
$master = new Master(
    retryAttempts: PHP_INT_MAX,    // 无限重试
    retryDelay: 3.0,               // 重试间隔3秒
    reconnectOnClose: true,        // 断开时自动重连
    logger: $logger
);

$master->on('error', function (\Exception $e, $context = []) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Context: " . json_encode($context) . "\n";
});

$master->on('connect', function ($tunnelStream) {
    $tunnelStream->write([
        'cmd' => 'auth',
        'token' => 'register-center-token-2024'
    ]);
    $tunnelStream->on('cmd', function ($cmd, $message) use ($tunnelStream) {
        echo "Received command: $cmd\n";
        echo "Message: " . json_encode($message) . "\n";
    });
});

$master->on('close', function ($id, $url) {
    echo "Disconnected from $url (ID: $id)\n";
});



// 连接到注册中心
$master->connectViaConnector('127.0.0.1', 8010);


// 连接到另一个注册中心，禁用自动重连
$master->connectViaConnector('127.0.0.1', 8010);


// 运行事件循环
Loop::get()->run(); 