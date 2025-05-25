<?php

namespace ReactphpX\RegisterCenter\Tests;

use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use ReactphpX\RegisterCenter\Register;
use ReactphpX\RegisterCenter\Master;
use ReactphpX\RegisterCenter\ServiceRegistry;
use Psr\Log\LoggerInterface;
use function React\Async\delay;

class ExamplesIntegrationTest extends TestCase
{
    private $loop;
    private $logger;
    private $logMessages = [];
    private $registerPort1;
    private $registerPort2;
    private $register1;
    private $register2;
    private $master;

    protected function setUp(): void
    {
        $this->loop = Loop::get();
        $this->registerPort1 = rand(20000, 30000);
        $this->registerPort2 = rand(30001, 40000);
        
        // 创建模拟日志记录器
        $this->logMessages = [];
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->method('info')->willReturnCallback(function ($message, $context = []) {
            $this->logMessages[] = ['level' => 'info', 'message' => $message, 'context' => $context];
        });
        $this->logger->method('error')->willReturnCallback(function ($message, $context = []) {
            $this->logMessages[] = ['level' => 'error', 'message' => $message, 'context' => $context];
        });
        $this->logger->method('warning')->willReturnCallback(function ($message, $context = []) {
            $this->logMessages[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
        });
        $this->logger->method('debug')->willReturnCallback(function ($message, $context = []) {
            $this->logMessages[] = ['level' => 'debug', 'message' => $message, 'context' => $context];
        });
        $this->logger->method('critical')->willReturnCallback(function ($message, $context = []) {
            $this->logMessages[] = ['level' => 'critical', 'message' => $message, 'context' => $context];
        });

        // 注册测试服务，模拟 master.php 示例
        ServiceRegistry::register('hello-wrold', new class {
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
                    'author' => 'John Doe',
                    'email' => 'john.doe@example.com',
                    'url' => 'https://example.com',
                    'license' => 'MIT',
                ];
            }
        });
    }

    /**
     * 测试 register.php 示例的基础功能
     * 验证注册中心启动和基本连接
     */
    public function testRegisterExampleBasicFunctionality()
    {
        // 创建注册中心 (模拟 register.php)
        $this->register1 = new Register($this->registerPort1, $this->loop, $this->logger);
        $this->register1->start();

        // 验证注册中心启动成功
        $this->assertTrue(true, 'Register center should start without exceptions');

        // 验证初始状态
        $masters = $this->register1->getConnectedMasters();
        $this->assertIsArray($masters, 'Should return array of masters');
        $this->assertEmpty($masters, 'Should start with no connected masters');

        // 验证服务状态获取 (模拟 register.php 中的 getServicesMaster)
        $services = $this->register1->getServicesMaster();
        $this->assertIsArray($services, 'Should return services array');
    }

    /**
     * 测试 register1.php 示例的功能
     * 验证第二个注册中心的独立运行
     */
    public function testRegister1ExampleFunctionality()
    {
        // 创建第二个注册中心 (模拟 register1.php)
        $this->register2 = new Register($this->registerPort2, $this->loop, $this->logger);
        $this->register2->start();

        // 验证第二个注册中心启动成功
        $this->assertTrue(true, 'Second register center should start without exceptions');

        // 验证初始状态
        $masters = $this->register2->getConnectedMasters();
        $this->assertIsArray($masters, 'Should return array of masters');
        $this->assertEmpty($masters, 'Should start with no connected masters');

        // 验证服务状态获取 (模拟 register1.php 中的 getServicesMaster)
        $services = $this->register2->getServicesMaster();
        $this->assertIsArray($services, 'Should return services array');
    }

    /**
     * 测试 Master 示例的服务注册功能
     * 验证 ServiceRegistry 的使用 (来自 master.php)
     */
    public function testMasterExampleServiceRegistration()
    {
        // 验证服务已经注册 (在 setUp 中注册)
        $this->assertTrue(ServiceRegistry::has('hello-wrold'), 'hello-wrold service should be registered');

        // 测试服务执行 (模拟 master.php 中的服务调用)
        $result1 = ServiceRegistry::execute('hello-wrold', 'sayHello');
        $this->assertIsString($result1, 'sayHello should return string');
        $this->assertStringContainsString('Hello, world!', $result1, 'Should contain greeting');

        // 测试带参数的服务执行
        $result2 = ServiceRegistry::execute('hello-wrold', 'sayHello2', ['name' => 'John Doe']);
        $this->assertIsArray($result2, 'sayHello2 should return array');
        $this->assertEquals('John Doe', $result2['name'], 'Should contain correct name');
        $this->assertEquals('1.0.0', $result2['version'], 'Should contain version');
        $this->assertEquals('Hello, world!', $result2['description'], 'Should contain description');
    }

    /**
     * 测试 Master 创建和配置 (来自 master.php 示例)
     */
    public function testMasterExampleConfiguration()
    {
        // 创建 Master (模拟 master.php 的配置)
        $this->master = new Master(
            retryAttempts: 3,
            retryDelay: 0.1,
            reconnectOnClose: true,
            logger: $this->logger
        );

        $this->assertInstanceOf(Master::class, $this->master, 'Master should be created successfully');

        $connected = false;
        $errors = [];

        // 设置事件处理器 (模拟 master.php)
        $this->master->on('error', function (\Exception $e, $context = []) use (&$errors) {
            $errors[] = [
                'message' => $e->getMessage(),
                'context' => $context
            ];
        });

        $this->master->on('connect', function ($tunnelStream) use (&$connected) {
            $connected = true;
            
            // 模拟认证 (来自 master.php)
            $tunnelStream->write([
                'cmd' => 'auth',
                'token' => 'register-center-token-2024'
            ]);
        });

        // 创建注册中心进行连接测试
        $this->register1 = new Register($this->registerPort1, $this->loop, $this->logger);
        $this->register1->start();

        // 尝试连接
        $this->master->connectViaConnector('127.0.0.1', $this->registerPort1);

        // 等待连接建立
        $this->waitForCondition(function () use (&$connected) {
            return $connected;
        }, 'Master should connect to register center', 1.0);

        $this->assertTrue($connected, 'Master should successfully connect');
    }

    /**
     * 测试注册中心的原始消息发送功能 (来自 register.php 示例)
     */
    public function testRegisterCenterRawMessageSending()
    {
        // 创建注册中心
        $this->register1 = new Register($this->registerPort1, $this->loop, $this->logger);
        $this->register1->start();

        // 创建 Master
        $this->master = new Master(
            retryAttempts: 1,
            retryDelay: 0.1,
            reconnectOnClose: false,
            logger: $this->logger
        );

        $connected = false;
        $receivedCommands = [];

        $this->master->on('connect', function ($tunnelStream) use (&$connected, &$receivedCommands) {
            $connected = true;
            
            $tunnelStream->write([
                'cmd' => 'auth',
                'token' => 'register-center-token-2024'
            ]);

            $tunnelStream->on('cmd', function ($cmd, $message) use (&$receivedCommands) {
                $receivedCommands[] = ['cmd' => $cmd, 'message' => $message];
            });
        });

        $this->master->connectViaConnector('127.0.0.1', $this->registerPort1);

        // 等待连接
        $this->waitForCondition(function () use (&$connected) {
            return $connected;
        }, 'Master should connect', 1.0);

        // 测试原始消息发送 (模拟 register.php 中的 writeRawMessageToAllMasters)
        $testMessage = [
            'cmd' => 'register',
            'registers' => [
                [
                    'host' => '127.0.0.1',
                    'port' => $this->registerPort2,
                ]
            ]
        ];

        $this->register1->writeRawMessageToAllMasters($testMessage);

        // 等待消息接收
        $this->waitForCondition(function () use (&$receivedCommands) {
            return count($receivedCommands) >= 2; // 等待至少两个命令 (auth-success 和 register)
        }, 'Should receive command message', 1.0);

        $this->assertNotEmpty($receivedCommands, 'Should receive commands');
        
        // 查找 register 命令（可能在 auth-success 之后）
        $registerCommand = null;
        foreach ($receivedCommands as $command) {
            if ($command['cmd'] === 'register') {
                $registerCommand = $command;
                break;
            }
        }
        
        $this->assertNotNull($registerCommand, 'Should receive register command');
        $this->assertEquals('register', $registerCommand['cmd'], 'Should receive register command');
    }

    /**
     * 辅助方法：等待条件满足或超时
     */
    private function waitForCondition(callable $condition, string $message, float $timeout = 2.0): void
    {
        $start = microtime(true);
        
        while (microtime(true) - $start < $timeout) {
            if ($condition()) {
                return;
            }
            
            // 使用 React\Async\delay 进行异步延迟
            delay(0.01);
        }
        
        $this->fail("Timeout waiting for condition: $message");
    }

    protected function tearDown(): void
    {
        // 清理资源
        if (isset($this->register1)) {
            unset($this->register1);
        }
        if (isset($this->register2)) {
            unset($this->register2);
        }
        if (isset($this->master)) {
            unset($this->master);
        }

        // 给事件循环时间清理
        delay(0.01);
    }
} 