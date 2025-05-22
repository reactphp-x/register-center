# ReactPHP-X 服务注册中心

一个基于 ReactPHP 的轻量级分布式服务注册中心，支持主从架构和实时通信。

## 主要特性

- 主从架构设计，支持多主节点连接
- 基于 ReactPHP 的异步事件驱动
- 内置心跳检测机制，自动检测节点存活状态
- 支持断线自动重连
- 支持远程代码执行和双向通信
- 完整的日志记录系统
- 高度可配置的重试机制

## 安装

```bash
composer require reactphp-x/register-center
```

## 基本用法

### 启动注册中心服务器

```php
use React\EventLoop\Loop;
use ReactphpX\RegisterCenter\Register;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;

// 创建事件循环
$loop = Loop::get();

// 配置日志
$logger = new Logger('registration-center');
$handler = new StreamHandler('php://stdout', Level::Debug);
$logger->pushHandler($handler);

// 创建并启动注册中心（默认端口 8080）
$center = new Register(8010, $loop, $logger);
$center->start();

// 运行事件循环
$loop->run();
```

### 创建主节点并连接到注册中心

```php
use ReactphpX\RegisterCenter\Master;
use Monolog\Logger;

// 配置日志
$logger = new Logger('master');
$handler = new StreamHandler('php://stdout', Level::Debug);
$logger->pushHandler($handler);

// 创建主节点实例
$master = new Master(
    retryAttempts: PHP_INT_MAX,    // 无限重试
    retryDelay: 3.0,               // 重试间隔3秒
    reconnectOnClose: true,        // 断开时自动重连
    logger: $logger
);

// 监听事件
$master->on('error', function (\Exception $e, $context = []) {
    echo "错误: " . $e->getMessage() . "\n";
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
    echo "与 $url 的连接已断开 (ID: $id)\n";
});

// 连接到注册中心
$master->connectViaConnector('127.0.0.1', 8010);

// 运行事件循环
Loop::get()->run();
```

### 在注册中心执行远程操作

```php
// 在所有连接的主节点上执行代码
$streams = $center->runOnAllMasters(function ($stream) {
    // 这段代码将在每个主节点上执行
    $stream->write("来自注册中心的消息！");
    
    $stream->on('data', function ($data) use ($stream) {
        echo "收到主节点响应: $data\n";
        $stream->end("感谢您的响应！");
    });
    
    return $stream;
});

foreach ($streams as $stream) {
    $stream->on('data', function ($data) use ($stream) {
        echo "收到主节点响应: $data\n";
    });
    $stream->write("来自注册中心的消息！");
}
```

## API 参考

### Register 类（注册中心）

#### 构造函数参数
- `port`: 监听端口（默认 8080）
- `loop`: ReactPHP 事件循环实例
- `logger`: PSR-3 日志记录器（可选）

#### 主要方法
- `start()`: 启动注册中心服务
- `runOnMaster(string $masterId, callable $callback)`: 在指定主节点上执行代码
- `runOnAllMasters(callable $callback)`: 在所有主节点上执行代码
- `getConnectedMasters()`: 获取所有已连接的主节点列表

### Master 类（主节点）

#### 构造函数参数
- `retryAttempts`: 重连尝试次数（默认无限）
- `retryDelay`: 重连延迟时间（秒）
- `reconnectOnClose`: 是否自动重连
- `logger`: PSR-3 日志记录器（可选）
- `loop`: ReactPHP 事件循环实例（可选）

#### 主要方法
- `connectViaConnector(string $host, int $port)`: 连接到注册中心
- `close()`: 关闭所有连接
- `getConnectedCenters()`: 获取已连接的注册中心列表
- `setRetrySettings(int $attempts, float $delay)`: 配置重试设置

#### 事件
- `error`: 发生错误时触发
- `connect`: 成功连接时触发
- `close`: 连接关闭时触发

## 高级特性

### 心跳检测
系统内置心跳检测机制，默认每 20 秒进行一次检测，确保节点存活状态。

### 自动重连
主节点支持断线自动重连功能，可通过配置调整重试次数和间隔时间。

### 日志系统
完整支持 PSR-3 日志接口，可配置不同级别的日志记录。

## 注意事项

1. 确保防火墙允许相应端口的 TCP 连接
2. 建议在生产环境中配置适当的日志级别
3. 根据网络环境调整重连参数
4. 建议使用 Supervisor 等进程管理工具确保服务持续运行

## 许可证

MIT 许可证 