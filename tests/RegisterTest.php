<?php

namespace ReactphpX\RegisterCenter\Tests;

use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use ReactphpX\RegisterCenter\Register;
use Psr\Log\LoggerInterface;
use React\Socket\SocketServer;
use React\Stream\DuplexStreamInterface;

class RegisterTest extends TestCase
{
    private $loop;
    private $logger;
    private $logMessages = [];
    private $port;
    private $center;
    private $socket;

    protected function setUp(): void
    {
        $this->loop = Loop::get();
        $this->port = rand(10000, 65000); // 随机端口以避免冲突
        
        // 创建模拟日志记录器
        $this->logMessages = [];
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->method('info')->willReturnCallback(function ($message, $context) {
            $this->logMessages[] = ['level' => 'info', 'message' => $message, 'context' => $context];
        });
        $this->logger->method('error')->willReturnCallback(function ($message, $context) {
            $this->logMessages[] = ['level' => 'error', 'message' => $message, 'context' => $context];
        });
        $this->logger->method('warning')->willReturnCallback(function ($message, $context) {
            $this->logMessages[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
        });
        $this->logger->method('debug')->willReturnCallback(function ($message, $context) {
            $this->logMessages[] = ['level' => 'debug', 'message' => $message, 'context' => $context];
        });
        $this->logger->method('critical')->willReturnCallback(function ($message, $context) {
            $this->logMessages[] = ['level' => 'critical', 'message' => $message, 'context' => $context];
        });

        $this->center = new Register($this->port, $this->loop, $this->logger);
    }

    public function testStartServer()
    {
        $this->center->start();
        
        // 验证启动日志
        $startLogFound = false;
        foreach ($this->logMessages as $log) {
            if ($log['level'] === 'info' && 
                $log['message'] === 'Registration Center started successfully' && 
                $log['context']['port'] === $this->port) {
                $startLogFound = true;
                break;
            }
        }
        $this->assertTrue($startLogFound, 'Server should log successful start');
        
        // 验证服务器是否真的在监听
        $connection = stream_socket_client("tcp://127.0.0.1:{$this->port}", $errno, $errstr, 1);
        $this->assertNotFalse($connection, "Server should be listening on port {$this->port}");
        fclose($connection);
    }

    public function testGetConnectedMasters()
    {
        $this->center->start();
        $this->assertEmpty($this->center->getConnectedMasters(), 'Should start with no connected masters');
    }

    public function testSetLogger()
    {
        $newLogger = $this->createMock(LoggerInterface::class);
        $newLogger->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->callback(function ($message) {
                    return in_array($message, [
                        'Starting Registration Center',
                        'Registration Center started successfully'
                    ]);
                }),
                $this->arrayHasKey('port')
            );

        $this->center->setLogger($newLogger);
        $this->center->start();
    }

    public function testStartServerOnUsedPort()
    {
        // 先占用端口
        $this->socket = new SocketServer("127.0.0.1:{$this->port}", [], $this->loop);
        
        $this->expectException(\Exception::class);
        $this->center->start();
        
        // 验证错误日志
        $errorLogFound = false;
        foreach ($this->logMessages as $log) {
            if ($log['level'] === 'critical' && 
                $log['message'] === 'Failed to start Registration Center' && 
                isset($log['context']['error'])) {
                $errorLogFound = true;
                break;
            }
        }
        $this->assertTrue($errorLogFound, 'Should log critical error when port is in use');
    }

    public function testRunOnNonExistentMaster()
    {
        $this->center->start();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Master not found: non-existent-id');
        
        $this->center->runOnMaster('non-existent-id', function() {});
        
        // 验证警告日志
        $warningLogFound = false;
        foreach ($this->logMessages as $log) {
            if ($log['level'] === 'warning' && 
                $log['message'] === 'Attempt to run on non-existent master' && 
                $log['context']['masterId'] === 'non-existent-id') {
                $warningLogFound = true;
                break;
            }
        }
        $this->assertTrue($warningLogFound, 'Should log warning when attempting to run on non-existent master');
    }

    protected function tearDown(): void
    {
        if (isset($this->socket)) {
            $this->socket->close();
        }
        
        // 给事件循环一点时间来清理
        $this->loop->addTimer(0.1, function () {
            $this->loop->stop();
        });
        $this->loop->run();
    }
} 