# ReactPHP X 服务注册中心

基于 ReactPHP 构建的分布式服务注册与发现中心，支持服务注册、发现以及主节点与注册中心之间的通信。

## 特性

- 分布式架构，支持主节点和注册中心
- 服务注册与发现
- 节点间实时通信
- 自动重连机制
- 可配置的重试机制
- 完善的日志支持
- 基于令牌的身份验证
- 动态服务执行

## 系统要求

- PHP 8.0 或更高版本
- Composer

## 安装

```bash
composer require reactphp-x/register-center
```

## 基本用法

### 1. 启动注册中心

```php
use React\EventLoop\Loop;
use ReactphpX\RegisterCenter\Register;

$loop = Loop::get();
$center = new Register(8010, $loop);
$center->start();
$loop->run();
```

### 2. 创建主节点

```php
use ReactphpX\RegisterCenter\Master;
use ReactphpX\RegisterCenter\ServiceRegistry;

// 注册你的服务
ServiceRegistry::register('your-service', new YourServiceClass());

// 创建并配置主节点
$master = new Master(
    retryAttempts: PHP_INT_MAX,    // 无限重试
    retryDelay: 3.0,               // 重试间隔3秒
    reconnectOnClose: true         // 断开时自动重连
);

// 连接到注册中心
$master->connectViaConnector('127.0.0.1', 8010);

// 运行事件循环
Loop::get()->run();
```

## 高级特性

### 服务注册

```php
ServiceRegistry::register('hello-world', new class {
    public function sayHello() {
        return "Hello, world! " . date('Y-m-d H:i:s');
    }
});
```

### 身份验证

主节点可以通过令牌与注册中心进行身份验证：

```php
$master->on('connect', function ($tunnelStream) {
    $tunnelStream->write([
        'cmd' => 'auth',
        'token' => 'your-auth-token'
    ]);
});
```

### 动态节点管理

注册中心可以动态管理连接的节点：

```php
// 添加新的注册中心
$center->writeRawMessageToAllMasters([
    'cmd' => 'register',
    'registers' => [
        [
            'host' => '127.0.0.1',
            'port' => 8011,
        ]
    ]
]);

// 移除注册中心
$center->writeRawMessageToAllMasters([
    'cmd' => 'remove',
    'registers' => [
        [
            'host' => '127.0.0.1',
            'port' => 8011,
        ]
    ]
]);
```

### 日志系统

系统使用 Monolog 提供全面的日志支持：

```php
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;

$logger = new Logger('master');
$handler = new StreamHandler('php://stdout', Level::Debug);
$master = new Master(logger: $logger);
```

## 示例

在 `examples` 目录下提供了完整的工作示例：

- `master.php`：主节点实现示例
- `register.php`：主注册中心示例
- `register1.php`：从注册中心示例

## 错误处理

```php
$master->on('error', function (\Exception $e, $context = []) {
    echo "错误：" . $e->getMessage() . "\n";
    echo "上下文：" . json_encode($context) . "\n";
});
```

## 开源协议

MIT 开源协议
