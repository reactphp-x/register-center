<?php

namespace ReactphpX\RegisterCenter\Tests;

use PHPUnit\Framework\TestCase;
use ReactphpX\RegisterCenter\ServiceRegistry;

class ServiceRegistryTest extends TestCase
{
    private $originalServices;

    protected function setUp(): void
    {
        // 保存原始服务状态
        $reflection = new \ReflectionClass(ServiceRegistry::class);
        $servicesProperty = $reflection->getProperty('services');
        $servicesProperty->setAccessible(true);
        $this->originalServices = $servicesProperty->getValue();
        
        // 清空服务注册表
        $servicesProperty->setValue([]);
    }

    protected function tearDown(): void
    {
        // 恢复原始服务状态
        $reflection = new \ReflectionClass(ServiceRegistry::class);
        $servicesProperty = $reflection->getProperty('services');
        $servicesProperty->setAccessible(true);
        $servicesProperty->setValue($this->originalServices);
    }

    /**
     * 测试服务注册功能 (来自 master.php 示例)
     */
    public function testServiceRegistration()
    {
        // 模拟 master.php 中的服务注册
        $testService = new class {
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
        };

        ServiceRegistry::register('hello-wrold', $testService);

        // 验证服务是否注册成功
        $this->assertTrue(ServiceRegistry::has('hello-wrold'), 'Service should be registered');
        $this->assertFalse(ServiceRegistry::has('non-existent'), 'Non-existent service should return false');
    }

    /**
     * 测试无参数方法执行 (来自 register.php 示例)
     */
    public function testExecuteServiceWithoutParameters()
    {
        // 注册测试服务
        $testService = new class {
            public function sayHello() {
                return "Hello, world! " . date('Y-m-d H:i:s') . "\n";
            }
        };

        ServiceRegistry::register('hello-wrold', $testService);

        // 执行服务方法 (模拟 register.php 中的调用)
        $result = ServiceRegistry::execute('hello-wrold', 'sayHello');

        $this->assertIsString($result, 'Result should be a string');
        $this->assertStringContainsString('Hello, world!', $result, 'Result should contain greeting');
        $this->assertStringContainsString(date('Y-m-d'), $result, 'Result should contain current date');
    }

    /**
     * 测试带参数方法执行 (来自 register.php 示例)
     */
    public function testExecuteServiceWithParameters()
    {
        // 注册测试服务
        $testService = new class {
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
        };

        ServiceRegistry::register('hello-wrold', $testService);

        // 执行带参数的服务方法 (模拟 register.php 中的调用)
        $result = ServiceRegistry::execute('hello-wrold', 'sayHello2', ['name' => 'John Doe']);

        $this->assertIsArray($result, 'Result should be an array');
        $this->assertEquals('John Doe', $result['name'], 'Result should contain correct name');
        $this->assertEquals('1.0.0', $result['version'], 'Result should contain version');
        $this->assertEquals('Hello, world!', $result['description'], 'Result should contain description');
        $this->assertEquals('John Doe', $result['author'], 'Result should contain author');
        $this->assertEquals('john.doe@example.com', $result['email'], 'Result should contain email');
        $this->assertEquals('https://example.com', $result['url'], 'Result should contain URL');
        $this->assertEquals('MIT', $result['license'], 'Result should contain license');
        $this->assertStringContainsString(date('Y-m-d'), $result['date'], 'Result should contain current date');
    }

    /**
     * 测试带数组参数的方法执行
     */
    public function testExecuteServiceWithArrayParameters()
    {
        $testService = new class {
            public function processData($data) {
                return [
                    'processed' => true,
                    'input' => $data,
                    'count' => count($data),
                    'timestamp' => time()
                ];
            }
        };

        ServiceRegistry::register('data-processor', $testService);

        $inputData = ['item1', 'item2', 'item3'];
        $result = ServiceRegistry::execute('data-processor', 'processData', ['data' => $inputData]);

        $this->assertIsArray($result, 'Result should be an array');
        $this->assertTrue($result['processed'], 'Processed flag should be true');
        $this->assertEquals($inputData, $result['input'], 'Input data should be preserved');
        $this->assertEquals(3, $result['count'], 'Count should be correct');
        $this->assertIsInt($result['timestamp'], 'Timestamp should be an integer');
    }

    /**
     * 测试执行不存在的服务
     */
    public function testExecuteNonExistentService()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Service 'non-existent-service' not found");

        ServiceRegistry::execute('non-existent-service', 'someMethod');
    }

    /**
     * 测试执行不存在的方法
     */
    public function testExecuteNonExistentMethod()
    {
        $testService = new class {
            public function existingMethod() {
                return 'success';
            }
        };

        ServiceRegistry::register('test-service', $testService);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Method 'nonExistentMethod' not found in service 'test-service'");

        ServiceRegistry::execute('test-service', 'nonExistentMethod');
    }

    /**
     * 测试私有方法不能被执行
     */
    public function testExecutePrivateMethod()
    {
        $testService = new class {
            public function publicMethod() {
                return 'public';
            }

            private function privateMethod() {
                return 'private';
            }
        };

        ServiceRegistry::register('test-service', $testService);

        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/Call to private method.*privateMethod/');

        ServiceRegistry::execute('test-service', 'privateMethod');
    }

    /**
     * 测试方法参数传递
     */
    public function testMethodParameterPassing()
    {
        $testService = new class {
            public function multipleParams($a, $b, $c = 'default') {
                return [
                    'a' => $a,
                    'b' => $b,
                    'c' => $c,
                    'sum' => $a + $b
                ];
            }
        };

        ServiceRegistry::register('test-service', $testService);

        // 测试传递部分参数
        $result = ServiceRegistry::execute('test-service', 'multipleParams', ['a' => 10, 'b' => 20]);
        $this->assertEquals(10, $result['a']);
        $this->assertEquals(20, $result['b']);
        $this->assertEquals('default', $result['c']);
        $this->assertEquals(30, $result['sum']);

        // 测试传递所有参数
        $result = ServiceRegistry::execute('test-service', 'multipleParams', ['a' => 5, 'b' => 15, 'c' => 'custom']);
        $this->assertEquals(5, $result['a']);
        $this->assertEquals(15, $result['b']);
        $this->assertEquals('custom', $result['c']);
        $this->assertEquals(20, $result['sum']);
    }

    /**
     * 测试获取所有已注册的服务
     */
    public function testGetAllServices()
    {
        // 注册多个服务
        ServiceRegistry::register('service1', new class {
            public function method1() { return 'result1'; }
        });

        ServiceRegistry::register('service2', new class {
            public function method2() { return 'result2'; }
        });

        ServiceRegistry::register('hello-wrold', new class {
            public function sayHello() { return 'Hello!'; }
        });

        $services = ServiceRegistry::all();

        $this->assertIsArray($services, 'Should return array of services');
        $this->assertArrayHasKey('service1', $services, 'Should contain service1');
        $this->assertArrayHasKey('service2', $services, 'Should contain service2');
        $this->assertArrayHasKey('hello-wrold', $services, 'Should contain hello-wrold service');
        $this->assertCount(3, $services, 'Should have exactly 3 services');
    }

    /**
     * 测试服务覆盖注册
     */
    public function testServiceOverride()
    {
        $service1 = new class {
            public function getValue() { return 'original'; }
        };

        $service2 = new class {
            public function getValue() { return 'updated'; }
        };

        ServiceRegistry::register('test-service', $service1);
        $result1 = ServiceRegistry::execute('test-service', 'getValue');
        $this->assertEquals('original', $result1);

        // 覆盖注册
        ServiceRegistry::register('test-service', $service2);
        $result2 = ServiceRegistry::execute('test-service', 'getValue');
        $this->assertEquals('updated', $result2);
    }

    /**
     * 测试复杂对象作为服务
     */
    public function testComplexServiceObject()
    {
        $complexService = new class {
            private $data = [];

            public function addData($key, $value) {
                $this->data[$key] = $value;
                return true;
            }

            public function getData($key = null) {
                if ($key === null) {
                    return $this->data;
                }
                return $this->data[$key] ?? null;
            }

            public function clearData() {
                $this->data = [];
                return true;
            }

            public function getStats() {
                return [
                    'count' => count($this->data),
                    'keys' => array_keys($this->data),
                    'memory_usage' => memory_get_usage()
                ];
            }
        };

        ServiceRegistry::register('data-store', $complexService);

        // 测试添加数据
        $result = ServiceRegistry::execute('data-store', 'addData', ['key' => 'name', 'value' => 'Test']);
        $this->assertTrue($result);

        // 测试获取数据
        $result = ServiceRegistry::execute('data-store', 'getData', ['key' => 'name']);
        $this->assertEquals('Test', $result);

        // 测试获取所有数据
        $result = ServiceRegistry::execute('data-store', 'getData');
        $this->assertIsArray($result);
        $this->assertEquals('Test', $result['name']);

        // 测试统计信息
        $stats = ServiceRegistry::execute('data-store', 'getStats');
        $this->assertIsArray($stats);
        $this->assertEquals(1, $stats['count']);
        $this->assertContains('name', $stats['keys']);
        $this->assertIsInt($stats['memory_usage']);

        // 测试清空数据
        ServiceRegistry::execute('data-store', 'clearData');
        $result = ServiceRegistry::execute('data-store', 'getData');
        $this->assertEmpty($result);
    }
} 