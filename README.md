# ReactPHP X 服务注册中心

基于 ReactPHP 构建的高性能分布式服务注册与发现中心，支持服务注册、发现、动态节点管理以及主节点与注册中心之间的实时通信。

## 特性

- 🚀 **高性能异步**: 基于 ReactPHP 事件循环，支持高并发
- 🔄 **分布式架构**: 支持多个注册中心和主节点
- 🎯 **服务注册与发现**: 动态服务注册、执行和管理
- 🔗 **实时通信**: 节点间双向实时通信
- 🔄 **自动重连**: 智能重连机制，断线自动恢复
- ⚙️ **可配置重试**: 灵活的重试策略配置
- 📝 **完善日志**: 基于 Monolog 的全方位日志支持
- 🔐 **身份验证**: 基于令牌的安全认证机制
- 🎲 **动态服务执行**: 支持远程服务调用和执行
- 📡 **节点管理**: 动态添加、移除注册中心节点

## 系统要求

- PHP 8.0 或更高版本
- Composer

## 安装

```bash
composer require reactphp-x/register-center
```

## 快速开始

### 1. 启动注册中心

创建一个注册中心服务器，监听端口 8010：

```php
<?php
require 'vendor/autoload.php';

use React\EventLoop\Loop;
use ReactphpX\RegisterCenter\Register;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

// 创建事件循环
$loop = Loop::get();

// 创建日志器
$logger = new Logger('registration-center');
$handler = new StreamHandler('php://stdout', Level::Debug);
$handler->setFormatter(new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message% %context%\n",
    null,
    true,
    true
));
$logger->pushHandler($handler);

// 创建并启动注册中心
$center = new Register(8010, $loop, $logger);
$center->start();

// 运行事件循环
$loop->run();
```

### 2. 创建主节点

创建一个主节点并注册服务：

```php
<?php
require 'vendor/autoload.php';

use React\EventLoop\Loop;
use ReactphpX\RegisterCenter\Master;
use ReactphpX\RegisterCenter\ServiceRegistry;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

// 注册服务
ServiceRegistry::register('hello-world', new class {
    public function sayHello() {
        $date = date('Y-m-d H:i:s');
        return "Hello, world! $date\n";
    }

    public function sayHello2($name) {
        $date = date('Y-m-d H:i:s');
        return [
            'date' => $date,
            'name' => $name,
            'version' => '1.0.0',
            'description' => 'Hello, world!',
            'author' => 'ReactPHP X',
            'email' => 'support@reactphp-x.com',
            'url' => 'https://github.com/reactphp-x',
            'license' => 'MIT',
        ];
    }
});

// 创建日志器
$logger = new Logger('master');
$handler = new StreamHandler('php://stdout', Level::Debug);
$handler->setFormatter(new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message% %context%\n",
    null,
    true,
    true
));
$logger->pushHandler($handler);

// 创建主节点
$master = new Master(
    retryAttempts: PHP_INT_MAX,    // 无限重试
    retryDelay: 3.0,               // 重试间隔3秒
    reconnectOnClose: true,        // 断开时自动重连
    logger: $logger
);

// 设置事件处理器
$master->on('error', function (\Exception $e, $context = []) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Context: " . json_encode($context) . "\n";
});

$master->on('connect', function ($tunnelStream) use ($master) {
    // 身份验证
    $tunnelStream->write([
        'cmd' => 'auth',
        'token' => 'register-center-token-2024'
    ]);
    
    // 监听来自注册中心的命令
    $tunnelStream->on('cmd', function ($cmd, $message) use ($tunnelStream, $master) {
        echo "Received command: $cmd\n";
        echo "Message: " . json_encode($message) . "\n";
        
        if ($cmd === 'register') {
            // 连接到新的注册中心
            $registers = $message['registers'];
            foreach ($registers as $register) {
                $master->connectViaConnector($register['host'], $register['port']);
            }
        } elseif ($cmd === 'remove') {
            // 移除注册中心连接
            $registers = $message['registers'];
            foreach ($registers as $register) {
                $master->removeConnection($register['host'], $register['port']);
            }
        }
    });
});

$master->on('close', function ($id, $url) {
    echo "Disconnected from $url (ID: $id)\n";
});

// 连接到注册中心
$master->connectViaConnector('127.0.0.1', 8010);

// 运行事件循环
Loop::get()->run();
```

### 3. 运行示例

```bash
# 终端 1: 启动主注册中心
php examples/register.php

# 终端 2: 启动从注册中心
php examples/register1.php

# 终端 3: 启动主节点
php examples/master.php
```

## 高级特性

### 服务注册与本地调用

#### 1. 服务注册

```php
use ReactphpX\RegisterCenter\ServiceRegistry;

// 注册简单服务
ServiceRegistry::register('hello-service', new class {
    public function sayHello() {
        return "Hello, world! " . date('Y-m-d H:i:s');
    }
    
    public function greet($name, $title = 'Mr.') {
        return "Hello, {$title} {$name}!";
    }
});

// 注册业务服务
ServiceRegistry::register('user-service', new class {
    private $users = [
        1 => ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
        2 => ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
    ];
    
    public function getUser($id) {
        return $this->users[$id] ?? null;
    }
    
    public function getAllUsers() {
        return array_values($this->users);
    }
    
    public function createUser($name, $email) {
        $id = max(array_keys($this->users)) + 1;
        $this->users[$id] = [
            'id' => $id,
            'name' => $name,
            'email' => $email
        ];
        return $this->users[$id];
    }
    
    public function updateUser($id, $data) {
        if (!isset($this->users[$id])) {
            throw new \Exception("User not found");
        }
        
        $this->users[$id] = array_merge($this->users[$id], $data);
        return $this->users[$id];
    }
});

// 注册异步服务
ServiceRegistry::register('async-service', new class {
    public function processTask($taskId, $data) {
        // 模拟异步处理
        $startTime = microtime(true);
        
        // 处理业务逻辑
        $result = array_map('strtoupper', $data);
        
        $endTime = microtime(true);
        
        return [
            'taskId' => $taskId,
            'result' => $result,
            'processingTime' => $endTime - $startTime,
            'timestamp' => time()
        ];
    }
});
```

#### 2. 本地服务调用

```php
// 检查服务是否存在
if (ServiceRegistry::has('hello-service')) {
    echo "Service registered successfully\n";
}

// 无参数调用
$result = ServiceRegistry::execute('hello-service', 'sayHello');
echo $result; // Hello, world! 2024-01-01 12:00:00

// 带参数调用
$result = ServiceRegistry::execute('hello-service', 'greet', [
    'name' => 'John',
    'title' => 'Dr.'
]);
echo $result; // Hello, Dr. John!

// 业务服务调用
$user = ServiceRegistry::execute('user-service', 'getUser', ['id' => 1]);
print_r($user);

$allUsers = ServiceRegistry::execute('user-service', 'getAllUsers');
print_r($allUsers);

// 错误处理
try {
    $result = ServiceRegistry::execute('user-service', 'updateUser', [
        'id' => 999,
        'data' => ['name' => 'Updated Name']
    ]);
} catch (\Exception $e) {
    echo "Service error: " . $e->getMessage() . "\n";
}
```

### 远程服务调用

#### 1. 单次远程调用

```php
// 在注册中心调用单个主节点上的服务
$masters = $center->getConnectedMasters();
if (!empty($masters)) {
    $masterId = array_key_first($masters);
    
    $stream = $center->runOnMaster($masterId, function ($stream) {
        // 调用用户服务
        $result = ServiceRegistry::execute('user-service', 'getAllUsers');
        $stream->write($result);
        $stream->end();
    });
    
    $stream->on('data', function ($data) use ($masterId) {
        echo "Users from master {$masterId}: " . json_encode($data) . "\n";
    });
}
```

#### 2. 批量远程调用

```php
// 在所有主节点上执行相同的服务
$loop->addPeriodicTimer(10, function () use ($center) {
    $masters = $center->getConnectedMasters();
    
    if (empty($masters)) {
        echo "No masters connected\n";
        return;
    }
    
    echo "Executing services on " . count($masters) . " masters\n";
    
    // 批量执行多个服务方法
    $streams = $center->runOnAllMasters(function ($stream) {
        $results = [];
        
        // 执行多个服务调用
        $results['greeting'] = ServiceRegistry::execute('hello-service', 'sayHello');
        $results['users'] = ServiceRegistry::execute('user-service', 'getAllUsers');
        $results['task'] = ServiceRegistry::execute('async-service', 'processTask', [
            'taskId' => uniqid(),
            'data' => ['hello', 'world', 'reactphp']
        ]);
        
        $stream->write($results);
        $stream->end();
    });

    // 处理所有主节点的响应
    foreach ($streams as $masterId => $stream) {
        $stream->on('data', function ($data) use ($masterId) {
            echo "\n=== Response from master {$masterId} ===\n";
            
            if (is_array($data)) {
                foreach ($data as $service => $result) {
                    echo "Service '{$service}': " . json_encode($result) . "\n";
                }
            } else {
                echo "Raw response: {$data}\n";
            }
        });
        
        $stream->on('error', function ($error) use ($masterId) {
            echo "Error from master {$masterId}: {$error}\n";
        });
        
        $stream->on('close', function () use ($masterId) {
            echo "Connection to master {$masterId} closed\n";
        });
    }
});
```

#### 3. 异步服务调用模式

```php
// 异步调用模式 - 任务分发
function distributeTask($center, $taskData) {
    $masters = $center->getConnectedMasters();
    $taskChunks = array_chunk($taskData, ceil(count($taskData) / count($masters)));
    
    $results = [];
    $completedTasks = 0;
    $totalMasters = count($masters);
    
    foreach ($masters as $masterId => $master) {
        $chunk = array_shift($taskChunks);
        if (!$chunk) continue;
        
        $stream = $center->runOnMaster($masterId, function ($stream) use ($chunk) {
            $result = ServiceRegistry::execute('async-service', 'processTask', [
                'taskId' => uniqid(),
                'data' => $chunk
            ]);
            $stream->write($result);
            $stream->end();
        });
        
        $stream->on('data', function ($data) use (&$results, &$completedTasks, $totalMasters, $masterId) {
            $results[$masterId] = $data;
            $completedTasks++;
            
            echo "Task completed on master {$masterId} ({$completedTasks}/{$totalMasters})\n";
            
            // 所有任务完成后的处理
            if ($completedTasks === $totalMasters) {
                echo "All tasks completed!\n";
                $finalResult = array_merge(...array_column($results, 'result'));
                echo "Final result: " . json_encode($finalResult) . "\n";
            }
        });
    }
}

// 使用示例
$taskData = ['apple', 'banana', 'cherry', 'date', 'elderberry'];
distributeTask($center, $taskData);
```

#### 4. 服务调用性能优化

```php
// 连接池优化调用
class ServiceCaller {
    private $center;
    private $callQueue = [];
    private $processingQueue = false;
    
    public function __construct($center) {
        $this->center = $center;
    }
    
    public function queueCall($service, $method, $params = [], $callback = null) {
        $this->callQueue[] = [
            'service' => $service,
            'method' => $method,
            'params' => $params,
            'callback' => $callback,
            'timestamp' => microtime(true)
        ];
        
        if (!$this->processingQueue) {
            $this->processQueue();
        }
    }
    
    private function processQueue() {
        if (empty($this->callQueue)) {
            $this->processingQueue = false;
            return;
        }
        
        $this->processingQueue = true;
        $batch = array_splice($this->callQueue, 0, 10); // 批量处理10个调用
        
        $masters = $this->center->getConnectedMasters();
        if (empty($masters)) {
            // 重新排队等待主节点连接
            $this->callQueue = array_merge($batch, $this->callQueue);
            $this->processingQueue = false;
            return;
        }
        
        // 选择负载最低的主节点
        $masterId = $this->selectLeastLoadedMaster($masters);
        
        $stream = $this->center->runOnMaster($masterId, function ($stream) use ($batch) {
            $results = [];
            
            foreach ($batch as $call) {
                try {
                    $result = ServiceRegistry::execute(
                        $call['service'],
                        $call['method'],
                        $call['params']
                    );
                    $results[] = [
                        'success' => true,
                        'result' => $result,
                        'call' => $call
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'call' => $call
                    ];
                }
            }
            
            $stream->write($results);
            $stream->end();
        });
        
        $stream->on('data', function ($results) use ($batch) {
            foreach ($results as $result) {
                if (isset($result['call']['callback']) && is_callable($result['call']['callback'])) {
                    $result['call']['callback']($result);
                }
            }
            
            // 继续处理队列
            $this->processQueue();
        });
    }
    
    private function selectLeastLoadedMaster($masters) {
        // 简单的负载均衡算法，实际应用中可以基于更复杂的指标
        return array_rand($masters);
    }
}

// 使用示例
$caller = new ServiceCaller($center);

// 队列化调用
$caller->queueCall('user-service', 'getUser', ['id' => 1], function ($result) {
    if ($result['success']) {
        echo "User: " . json_encode($result['result']) . "\n";
    } else {
        echo "Error: " . $result['error'] . "\n";
    }
});

$caller->queueCall('hello-service', 'greet', ['name' => 'Alice'], function ($result) {
    echo "Greeting: " . $result['result'] . "\n";
});
```

### 服务调用最佳实践

#### 1. 服务设计原则

```php
// ✅ 好的服务设计
ServiceRegistry::register('product-service', new class {
    // 返回明确的数据结构
    public function getProduct($id) {
        return [
            'id' => $id,
            'name' => 'Product Name',
            'price' => 99.99,
            'stock' => 10,
            'status' => 'active'
        ];
    }
    
    // 参数验证
    public function updateProduct($id, $data) {
        if (!is_numeric($id) || $id <= 0) {
            throw new \InvalidArgumentException('Invalid product ID');
        }
        
        $allowedFields = ['name', 'price', 'stock', 'status'];
        $filteredData = array_intersect_key($data, array_flip($allowedFields));
        
        // 更新逻辑...
        return $this->getProduct($id);
    }
    
    // 幂等性操作
    public function activateProduct($id) {
        // 重复调用不会产生副作用
        return $this->updateProduct($id, ['status' => 'active']);
    }
});

// ❌ 避免的服务设计
ServiceRegistry::register('bad-service', new class {
    // 不要返回不一致的数据结构
    public function getData($type) {
        if ($type === 'array') {
            return ['data' => 'value'];
        } else {
            return 'string value'; // 不一致的返回类型
        }
    }
    
    // 不要在服务中直接输出
    public function processData($data) {
        echo "Processing..."; // ❌ 避免直接输出
        return $data;
    }
});
```

#### 2. 错误处理策略

```php
// 统一的错误处理服务
ServiceRegistry::register('error-handler', new class {
    public function handleServiceCall($service, $method, $params = []) {
        try {
            $result = ServiceRegistry::execute($service, $method, $params);
            
            return [
                'success' => true,
                'data' => $result,
                'timestamp' => time()
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => 'validation_error',
                'message' => $e->getMessage(),
                'timestamp' => time()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'service_error',
                'message' => $e->getMessage(),
                'timestamp' => time()
            ];
        }
    }
});

// 远程调用的错误处理
function callServiceWithRetry($center, $service, $method, $params = [], $maxRetries = 3) {
    $attempt = 0;
    
    $executeCall = function() use ($center, $service, $method, $params, &$attempt, $maxRetries, &$executeCall) {
        $attempt++;
        $masters = $center->getConnectedMasters();
        
        if (empty($masters)) {
            if ($attempt < $maxRetries) {
                // 等待后重试
                Loop::get()->addTimer(1, $executeCall);
                return;
            }
            throw new \Exception('No masters available after ' . $maxRetries . ' attempts');
        }
        
        $masterId = array_rand($masters);
        $stream = $center->runOnMaster($masterId, function ($stream) use ($service, $method, $params) {
            $result = ServiceRegistry::execute('error-handler', 'handleServiceCall', [
                'service' => $service,
                'method' => $method,
                'params' => $params
            ]);
            $stream->write($result);
            $stream->end();
        });
        
        $stream->on('data', function ($data) use ($attempt, $maxRetries, $executeCall) {
            if (!$data['success'] && $attempt < $maxRetries) {
                echo "Call failed, retrying... (attempt {$attempt}/{$maxRetries})\n";
                Loop::get()->addTimer(2, $executeCall);
            } else {
                echo "Final result: " . json_encode($data) . "\n";
            }
        });
        
        $stream->on('error', function ($error) use ($attempt, $maxRetries, $executeCall) {
            echo "Stream error: {$error}\n";
            if ($attempt < $maxRetries) {
                Loop::get()->addTimer(2, $executeCall);
            }
        });
    };
    
    $executeCall();
}

// 使用示例
callServiceWithRetry($center, 'product-service', 'getProduct', ['id' => 1]);
```

#### 3. 性能监控与调试

```php
// 服务调用监控
class ServiceMonitor {
    private $metrics = [];
    
    public function trackCall($service, $method, $duration, $success) {
        $key = "{$service}.{$method}";
        
        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = [
                'total_calls' => 0,
                'success_calls' => 0,
                'failed_calls' => 0,
                'total_duration' => 0,
                'avg_duration' => 0,
                'min_duration' => PHP_FLOAT_MAX,
                'max_duration' => 0
            ];
        }
        
        $metric = &$this->metrics[$key];
        $metric['total_calls']++;
        
        if ($success) {
            $metric['success_calls']++;
        } else {
            $metric['failed_calls']++;
        }
        
        $metric['total_duration'] += $duration;
        $metric['avg_duration'] = $metric['total_duration'] / $metric['total_calls'];
        $metric['min_duration'] = min($metric['min_duration'], $duration);
        $metric['max_duration'] = max($metric['max_duration'], $duration);
    }
    
    public function getMetrics() {
        return $this->metrics;
    }
    
    public function reset() {
        $this->metrics = [];
    }
}

// 监控服务包装器
ServiceRegistry::register('monitored-service', new class {
    private $monitor;
    
    public function __construct() {
        $this->monitor = new ServiceMonitor();
    }
    
    public function executeWithMonitoring($service, $method, $params = []) {
        $startTime = microtime(true);
        $success = false;
        
        try {
            $result = ServiceRegistry::execute($service, $method, $params);
            $success = true;
            return $result;
        } catch (\Exception $e) {
            throw $e;
        } finally {
            $duration = microtime(true) - $startTime;
            $this->monitor->trackCall($service, $method, $duration, $success);
        }
    }
    
    public function getMetrics() {
        return $this->monitor->getMetrics();
    }
});

// 定期输出性能指标
$loop->addPeriodicTimer(30, function () {
    $metrics = ServiceRegistry::execute('monitored-service', 'getMetrics');
    echo "\n=== Service Performance Metrics ===\n";
    foreach ($metrics as $service => $metric) {
        echo sprintf(
            "%s: calls=%d, success=%.1f%%, avg=%.3fs, min=%.3fs, max=%.3fs\n",
            $service,
            $metric['total_calls'],
            ($metric['success_calls'] / $metric['total_calls']) * 100,
            $metric['avg_duration'],
            $metric['min_duration'],
            $metric['max_duration']
        );
    }
});
```

#### 4. 服务版本管理

```php
// 版本化服务注册
class VersionedServiceRegistry {
    private static $services = [];
    
    public static function register($name, $version, $instance) {
        $key = "{$name}@{$version}";
        self::$services[$key] = $instance;
        
        // 同时注册为默认版本（如果是最新的）
        if (!isset(self::$services[$name]) || version_compare($version, self::getVersion($name), '>')) {
            self::$services[$name] = $instance;
        }
    }
    
    public static function execute($name, $method, $params = [], $version = null) {
        $key = $version ? "{$name}@{$version}" : $name;
        
        if (!isset(self::$services[$key])) {
            throw new \Exception("Service {$key} not found");
        }
        
        return ServiceRegistry::execute($key, $method, $params);
    }
    
    private static function getVersion($name) {
        // 从服务键中提取版本号
        foreach (array_keys(self::$services) as $key) {
            if (strpos($key, $name . '@') === 0) {
                return substr($key, strlen($name) + 1);
            }
        }
        return '1.0.0';
    }
}

// 注册不同版本的服务
VersionedServiceRegistry::register('api-service', '1.0.0', new class {
    public function getData() {
        return ['version' => '1.0.0', 'data' => 'old format'];
    }
});

VersionedServiceRegistry::register('api-service', '2.0.0', new class {
    public function getData() {
        return [
            'version' => '2.0.0',
            'data' => ['id' => 1, 'name' => 'new format'],
            'metadata' => ['timestamp' => time()]
        ];
    }
});

// 调用指定版本
$oldResult = VersionedServiceRegistry::execute('api-service', 'getData', [], '1.0.0');
$newResult = VersionedServiceRegistry::execute('api-service', 'getData', [], '2.0.0');
$defaultResult = VersionedServiceRegistry::execute('api-service', 'getData'); // 使用最新版本
```

### 身份验证与安全

主节点连接时需要进行身份验证：

```php
$master->on('connect', function ($tunnelStream) {
    // 发送认证令牌
    $tunnelStream->write([
        'cmd' => 'auth',
        'token' => 'register-center-token-2024'
    ]);
    
    // 监听认证结果
    $tunnelStream->on('cmd', function ($cmd, $message) {
        if ($cmd === 'auth-success') {
            echo "Authentication successful\n";
        } elseif ($cmd === 'auth-failed') {
            echo "Authentication failed: " . $message['reason'] . "\n";
        }
    });
});
```

### 动态节点管理

注册中心支持动态添加和移除节点：

```php
// 定时器：10秒后通知所有主节点有新的注册中心可连接
Loop::addTimer(10, function () use ($center) {
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

// 定时器：20秒后移除注册中心
Loop::addTimer(20, function () use ($center) {
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

// 主节点处理注册中心管理命令
$master->on('connect', function ($tunnelStream) use ($master) {
    $tunnelStream->on('cmd', function ($cmd, $message) use ($master) {
        echo "Received command: $cmd\n";
        echo "Message: " . json_encode($message) . "\n";
        
        if ($cmd === 'register') {
            // 连接到新的注册中心
            $registers = $message['registers'];
            foreach ($registers as $register) {
                $master->connectViaConnector($register['host'], $register['port']);
            }
        } elseif ($cmd === 'remove') {
            // 移除注册中心连接
            $registers = $message['registers'];
            foreach ($registers as $register) {
                $master->removeConnection($register['host'], $register['port']);
            }
        }
    });
});
```

### 服务监控

获取连接状态和服务信息：

```php
// 获取已连接的主节点
$masters = $center->getConnectedMasters();
echo "Connected masters: " . count($masters) . "\n";

// 定期获取服务状态
Loop::addPeriodicTimer(1, function () use ($center) {
    $services = $center->getServicesMaster();
    echo "Available services: ";
    var_export($services);
});
```

### 完整的日志配置

```php
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FileHandler;
use Monolog\Formatter\LineFormatter;

// 创建日志器
$logger = new Logger('registration-center');

// 控制台输出
$consoleHandler = new StreamHandler('php://stdout', Level::Debug);
$consoleHandler->setFormatter(new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message% %context%\n",
    null,
    true,
    true
));

// 文件输出
$fileHandler = new FileHandler('logs/register-center.log', Level::INFO);
$fileHandler->setFormatter(new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message% %context%\n"
));

$logger->pushHandler($consoleHandler);
$logger->pushHandler($fileHandler);

// 使用日志器
$center = new Register(8010, $loop, $logger);
$master = new Master(logger: $logger);
```

## 示例与使用场景

### 完整示例

在 `examples` 目录下提供了完整的工作示例：

- **`register.php`**：主注册中心示例 - 监听端口 8010，管理主节点连接
- **`register1.php`**：从注册中心示例 - 监听端口 8011，作为备用注册中心
- **`master.php`**：主节点实现示例 - 注册服务并连接到注册中心

### 运行完整示例

```bash
# 终端 1: 启动主注册中心
php examples/register.php

# 终端 2: 启动从注册中心 
php examples/register1.php

# 终端 3: 启动主节点
php examples/master.php
```

运行后您将看到：
1. 主节点连接到主注册中心 (8010)
2. 注册中心定期在主节点上执行服务方法
3. 10秒后，主注册中心通知主节点连接到从注册中心 (8011)
4. 20秒后，主注册中心通知主节点断开从注册中心连接

### 架构说明

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   注册中心 A     │    │   注册中心 B     │    │   注册中心 C     │
│   (8010)        │    │   (8011)        │    │   (8012)        │
└─────────┬───────┘    └─────────┬───────┘    └─────────┬───────┘
          │                      │                      │
          │                      │                      │
    ┌─────▼──────────────────────▼──────────────────────▼─────┐
    │                主节点 (Master)                           │
    │  ┌─────────────────┐  ┌─────────────────┐              │
    │  │   Service A     │  │   Service B     │              │
    │  │                 │  │                 │              │
    │  └─────────────────┘  └─────────────────┘              │
    └──────────────────────────────────────────────────────────┘
```

### 使用场景

#### 1. 微服务架构
```php
// 用户服务
ServiceRegistry::register('user-service', new UserService());

// 订单服务
ServiceRegistry::register('order-service', new OrderService());

// 支付服务
ServiceRegistry::register('payment-service', new PaymentService());
```

#### 2. 分布式任务处理
```php
// 注册中心分发任务到各个主节点
$center->runOnAllMasters(function ($stream) use ($taskData) {
    $result = ServiceRegistry::execute('task-processor', 'processTask', $taskData);
    $stream->write($result);
});
```

#### 3. 负载均衡
```php
// 获取所有可用的主节点
$masters = $center->getConnectedMasters();

// 选择负载最低的主节点执行任务
$selectedMaster = selectLeastLoadedMaster($masters);
$center->runOnMaster($selectedMaster, function ($stream) {
    // 执行特定任务
});
```

#### 4. 故障转移
当主注册中心不可用时，主节点会自动连接到备用注册中心：

```php
// 主节点会自动重连到可用的注册中心
$master = new Master(
    retryAttempts: PHP_INT_MAX,
    retryDelay: 3.0,
    reconnectOnClose: true
);
```

## API 参考

### Register (注册中心)

#### 构造函数
```php
new Register(int $port, LoopInterface $loop, ?LoggerInterface $logger = null)
```

#### 主要方法
- `start()` - 启动注册中心
- `getConnectedMasters()` - 获取已连接的主节点列表
- `getServicesMaster()` - 获取主节点服务信息
- `runOnAllMasters(callable $callback)` - 在所有主节点上执行回调
- `runOnMaster(string $masterId, callable $callback)` - 在指定主节点上执行回调
- `writeRawMessageToAllMasters(array $message)` - 向所有主节点发送原始消息

### Master (主节点)

#### 构造函数
```php
new Master(
    int $retryAttempts = 3,
    float $retryDelay = 1.0,
    bool $reconnectOnClose = false,
    ?LoggerInterface $logger = null
)
```

#### 主要方法
- `connectViaConnector(string $host, int $port)` - 连接到注册中心
- `removeConnection(string $host, int $port)` - 移除到指定注册中心的连接
- `on(string $event, callable $listener)` - 添加事件监听器

#### 事件
- `connect` - 连接建立时触发
- `error` - 发生错误时触发  
- `close` - 连接关闭时触发

### ServiceRegistry (服务注册表)

#### 静态方法
- `register(string $name, object $instance, array $metadata = [])` - 注册服务
- `get(string $name)` - 获取服务实例
- `has(string $name)` - 检查服务是否存在
- `remove(string $name)` - 移除服务
- `all()` - 获取所有服务
- `execute(string $name, string $method, array $arguments = [])` - 执行服务方法

## 错误处理与调试

### 错误处理

```php
// 主节点错误处理
$master->on('error', function (\Exception $e, $context = []) {
    echo "错误：" . $e->getMessage() . "\n";
    echo "上下文：" . json_encode($context) . "\n";
    
    // 记录错误日志
    $logger->error('Master error', [
        'exception' => $e->getMessage(),
        'context' => $context
    ]);
});

// 连接关闭处理
$master->on('close', function ($id, $url) {
    echo "与 $url 的连接已关闭 (ID: $id)\n";
    
    // 可以在这里实现重连逻辑
    if ($shouldReconnect) {
        $master->connectViaConnector($host, $port);
    }
});
```

### 调试技巧

```php
// 启用详细日志
$logger = new Logger('debug');
$logger->pushHandler(new StreamHandler('php://stdout', Level::DEBUG));

// 监控连接状态
$loop->addPeriodicTimer(10, function () use ($center) {
    $masters = $center->getConnectedMasters();
    echo "当前连接的主节点数量: " . count($masters) . "\n";
    
    foreach ($masters as $id => $master) {
        echo "主节点 ID: $id\n";
    }
});

// 服务执行调试
try {
    $result = ServiceRegistry::execute('service-name', 'method-name', $params);
    echo "服务执行成功: " . json_encode($result) . "\n";
} catch (\Exception $e) {
    echo "服务执行失败: " . $e->getMessage() . "\n";
}
```

## 性能优化

### 连接池管理

```php
// 配置合理的重试参数
$master = new Master(
    retryAttempts: 5,        // 适中的重试次数
    retryDelay: 2.0,         // 合理的重试间隔
    reconnectOnClose: true   // 启用自动重连
);
```

### 日志级别优化

```php
// 生产环境使用较高的日志级别
$handler = new StreamHandler('php://stdout', Level::WARNING);

// 开发环境使用详细日志
$handler = new StreamHandler('php://stdout', Level::DEBUG);
```

### 内存优化

```php
// 定期清理不需要的服务
ServiceRegistry::remove('unused-service');

// 限制连接数量
if (count($center->getConnectedMasters()) > $maxConnections) {
    // 拒绝新连接或关闭旧连接
}
```

## 常见问题

### Q: 如何处理网络中断？
A: 主节点具备自动重连功能，配置 `reconnectOnClose: true` 即可在连接断开时自动重连。

### Q: 如何在生产环境中使用？
A: 建议使用进程管理器如 Supervisor 来管理进程，并配置适当的日志级别和重试参数。

### Q: 支持多少个并发连接？
A: 基于 ReactPHP，可以处理数千个并发连接，具体取决于服务器配置。

### Q: 如何扩展到多个注册中心？
A: 使用动态节点管理功能，可以运行时添加和移除注册中心。

## 贡献

欢迎贡献代码！请遵循以下步骤：

1. Fork 项目
2. 创建功能分支 (`git checkout -b feature/amazing-feature`)
3. 提交更改 (`git commit -m 'Add amazing feature'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 创建 Pull Request

### 开发环境设置

```bash
# 克隆项目
git clone https://github.com/reactphp-x/register-center.git
cd register-center

# 安装依赖
composer install

# 运行测试
vendor/bin/phpunit

# 运行示例
php examples/register.php
```

## 许可证

本项目采用 MIT 许可证 - 详见 [LICENSE](LICENSE) 文件

---

## 链接

- [GitHub 仓库](https://github.com/reactphp-x/register-center)
- [问题反馈](https://github.com/reactphp-x/register-center/issues)
- [ReactPHP 官网](https://reactphp.org/)

---

**ReactPHP X Register Center** - 让分布式服务通信变得简单高效 🚀
