<?php

namespace ReactphpX\RegisterCenter;

use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use ReactphpX\TunnelStream\TunnelStream;
use React\Stream\DuplexStreamInterface;
use React\Promise\Deferred;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Register
{
    private $loop;
    private $port;
    private $connectedMasters = [];
    private $logger;
    private $lastActivityTimes = [];
    private $pingTimers = [];
    private const PING_INTERVAL = 20.0;

    public function __construct(int $port = 8080, LoopInterface $loop, ?LoggerInterface $logger = null)
    {
        $this->loop = $loop;
        $this->port = $port;
        $this->logger = $logger ?? new NullLogger();
    }

    public function start(): void
    {
        $this->logger->info("Starting Registration Center", ['port' => $this->port]);

        try {
            $socket = new SocketServer("0.0.0.0:{$this->port}", [], $this->loop);
            
            $socket->on('connection', function (DuplexStreamInterface $connection) {
                $this->onMasterConnected($connection);
            });

            $socket->on('error', function (\Exception $e) {
                $this->logger->error("Socket server error", [
                    'error' => $e->getMessage()
                ]);
            });
            
            $this->logger->info("Registration Center started successfully", ['port' => $this->port]);
        } catch (\Exception $e) {
            $this->logger->critical("Failed to start Registration Center", [
                'port' => $this->port,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function onMasterConnected(DuplexStreamInterface $connection): void
    {
        $masterId = bin2hex(random_bytes(16));
        $tunnelStream = new TunnelStream($connection, $connection, true);
        
        $this->connectedMasters[$masterId] = [
            'connection' => $connection,
            'tunnelStream' => $tunnelStream
        ];

        // 初始化最后活动时间
        $this->lastActivityTimes[$masterId] = time();
        
        // 设置数据接收处理
        $tunnelStream->on('data', function ($data) use ($masterId) {
            $this->lastActivityTimes[$masterId] = time();
            $this->logger->debug("Received data from master", [
                'masterId' => $masterId,
                'data' => $data
            ]);
        });

        // 启动 ping 定时器
        $this->startPingTimer($masterId, $tunnelStream);
        
        $this->logger->info("New master connected", ['masterId' => $masterId]);
        
        $connection->on('close', function () use ($masterId) {
            $this->cleanupMaster($masterId);
            $this->logger->info("Master disconnected", ['masterId' => $masterId]);
        });

        $connection->on('error', function (\Exception $e) use ($masterId) {
            $this->logger->error("Master connection error", [
                'masterId' => $masterId,
                'error' => $e->getMessage()
            ]);
        });
    }

    private function startPingTimer(string $masterId, TunnelStream $tunnelStream): void
    {
        $this->pingTimers[$masterId] = $this->loop->addPeriodicTimer(self::PING_INTERVAL, function () use ($masterId, $tunnelStream) {
            $timeSinceLastActivity = time() - $this->lastActivityTimes[$masterId];
            
            if ($timeSinceLastActivity >= self::PING_INTERVAL) {
                $this->logger->debug("Sending ping to master", ['masterId' => $masterId]);

                $tunnelStream->ping()
                    ->then(function () use ($masterId) {
                        $this->logger->debug("Received pong from master", ['masterId' => $masterId]);
                        $this->lastActivityTimes[$masterId] = time();
                    }, function (\Exception $e) use ($masterId) {
                        $this->logger->error("Ping failed", [
                            'masterId' => $masterId,
                            'error' => $e->getMessage()
                        ]);
                    });
            }
        });
    }

    private function cleanupMaster(string $masterId): void
    {
        unset($this->connectedMasters[$masterId]);
        unset($this->lastActivityTimes[$masterId]);
        
        if (isset($this->pingTimers[$masterId])) {
            $this->loop->cancelTimer($this->pingTimers[$masterId]);
            unset($this->pingTimers[$masterId]);
        }
    }

    public function runOnMaster(string $masterId, callable $callback)
    {
        if (!isset($this->connectedMasters[$masterId])) {
            $this->logger->warning("Attempt to run on non-existent master", ['masterId' => $masterId]);
            throw new \Exception("Master not found: {$masterId}");
        }
        
        $this->logger->debug("Running code on master", ['masterId' => $masterId]);
        
        $tunnelStream = $this->connectedMasters[$masterId]['tunnelStream'];
        $stream = $tunnelStream->run($callback);
        
        return $stream;
    }

    public function runOnAllMasters(callable $callback): array
    {
        $masterCount = count($this->connectedMasters);
        $this->logger->info("Running code on all masters", ['masterCount' => $masterCount]);
        
        $streams = [];
        
        foreach ($this->connectedMasters as $masterId => $master) {
            try {
                $streams[$masterId] = $this->runOnMaster($masterId, $callback);
            } catch (\Exception $e) {
                $this->logger->error("Failed to run code on master", [
                    'masterId' => $masterId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $streams;
    }

    public function getConnectedMasters(): array
    {
        return array_keys($this->connectedMasters);
    }

    /**
     * Set a new logger instance
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
} 