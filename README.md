# ReactPHP Register Center

基于 ReactPHP 实现的服务注册中心，用于统一调用master提供的服务。

## 安装

```bash
composer require reactphp-x/register-center
```

## 基础使用

### 启动注册中心服务器

```php
use React\EventLoop\Loop;
use ReactphpX\RegisterCenter\Register;

$loop = Loop::get();
$center = new Register(8010, $loop);
$center->start();

$loop->run();
```

### 注册服务（Master）

```php
use React\EventLoop\Loop;
use ReactphpX\RegisterCenter\Master;
use ReactphpX\RegisterCenter\Service;

$loop = Loop::get();

// 创建服务实例
$service = new Service(
    'user-service',    // 服务名称
    '127.0.0.1',      // 服务主机
    8020,             // 服务端口
    [
        'version' => '1.0',
        'type' => 'user'
    ]
);

// 连接到注册中心并注册服务
$master = new Master('127.0.0.1:8010', $loop);
$master->registerService($service);
$master->start();

$loop->run();
```

### 服务发现和调用

```php
// 获取指定服务
$service = $center->getService('user-service');

// 获取所有服务
$services = $center->getAllServices();

// 按元数据查找服务
$userServices = $center->getServicesByMetadata('type', 'user');

// 调用单个服务
$result = $center->runOnService('user-service', function ($stream) {
    $stream->write("Hello Service!");
    
    $stream->on('data', function ($data) {
        echo "Service Response: $data\n";
    });
    
    return $stream;
});

// 调用所有服务
$streams = $center->runOnAllServices(function ($stream) {
    $stream->write("Hello Service!");
    
    $stream->on('data', function ($data) {
        echo "Service Response: $data\n";
    });
    
    return $stream;
});

// 处理所有服务的响应
foreach ($streams as $serviceName => $stream) {
    $stream->on('data', function ($data) use ($serviceName) {
        echo "Response from $serviceName: $data\n";
    });
}
```

## 示例

完整示例请查看 `examples` 目录：
- `examples/register.php`: 注册中心示例
- `examples/master.php`: 服务节点示例

## License

MIT 