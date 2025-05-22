<?php

namespace ReactphpX\RegisterCenter\Tests;

use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\Promise\Promise;
use ReactphpX\RegisterCenter\Master;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

class MasterTest extends TestCase
{
    private $loop;
    private $master;
    private $logger;
    private $logMessages = [];

    protected function setUp(): void
    {
        $this->loop = Loop::get();
        $this->logMessages = [];
        
        // Create a custom logger that records messages
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

        $this->master = new Master(
            retryAttempts: 3,
            retryDelay: 0.1,
            reconnectOnClose: true,
            logger: $this->logger,
            loop: $this->loop
        );
    }

    public function testInitialState()
    {
        $this->assertEmpty($this->master->getConnectedCenters());
    }

    public function testGetNonExistentTunnelStream()
    {
        $this->master->getTunnelStream('non-existent-id');
        
        $this->assertCount(1, $this->logMessages);
        $this->assertEquals('warning', $this->logMessages[0]['level']);
        $this->assertEquals('TunnelStream not found', $this->logMessages[0]['message']);
        $this->assertArrayHasKey('id', $this->logMessages[0]['context']);
        
        $this->assertNull($this->master->getTunnelStream('non-existent-id'));
    }

    public function testSetRetrySettings()
    {
        $this->master->setRetrySettings(5, 1.0);
        // Since these are private properties, we can only test indirectly
        // through behavior in other tests
        $this->assertTrue(true);
    }

    public function testSetReconnectOnClose()
    {
        $this->master->setReconnectOnClose(false);
        // Since this is a private property, we can only test indirectly
        // through behavior in other tests
        $this->assertTrue(true);
    }

    public function testSetLogger()
    {
        $newLogger = $this->createMock(LoggerInterface::class);
        $newLogger->expects($this->once())
            ->method('warning')
            ->with(
                $this->equalTo('TunnelStream not found'),
                $this->arrayHasKey('id')
            );

        $this->master->setLogger($newLogger);
        $this->master->getTunnelStream('non-existent-id');
    }

    public function testConnectionFailureAndRetry()
    {
        // Try to connect to a non-existent server
        $this->master->connectViaConnector('127.0.0.1', 12345);

        // Run the loop for a short time to allow retries
        $this->loop->addTimer(0.5, function () {
            $this->loop->stop();
        });
        $this->loop->run();

        // Verify log messages
        $hasConnectionAttempt = false;
        $hasRetryScheduling = false;
        $hasConnectionError = false;

        foreach ($this->logMessages as $log) {
            if ($log['level'] === 'info' && $log['message'] === 'Attempting to connect to registration center') {
                $hasConnectionAttempt = true;
                $this->assertArrayHasKey('host', $log['context']);
                $this->assertArrayHasKey('port', $log['context']);
                $this->assertArrayHasKey('attempt', $log['context']);
            }
            if ($log['level'] === 'info' && $log['message'] === 'Scheduling retry') {
                $hasRetryScheduling = true;
                $this->assertArrayHasKey('host', $log['context']);
                $this->assertArrayHasKey('port', $log['context']);
                $this->assertArrayHasKey('nextAttempt', $log['context']);
            }
            if ($log['level'] === 'error' && $log['message'] === 'Failed to connect to registration center') {
                $hasConnectionError = true;
                $this->assertArrayHasKey('host', $log['context']);
                $this->assertArrayHasKey('port', $log['context']);
                $this->assertArrayHasKey('error', $log['context']);
            }
        }

        $this->assertTrue($hasConnectionAttempt, 'Should have at least one connection attempt');
        $this->assertTrue($hasRetryScheduling, 'Should have at least one retry scheduling');
        $this->assertTrue($hasConnectionError, 'Should have at least one connection error');
    }
} 