<?php

namespace ReactphpX\RegisterCenter;

use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use ReactphpX\TunnelStream\TunnelStream;
use React\Stream\DuplexStreamInterface;
use React\Promise\Deferred;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class Register
{
    private $loop;
    private $port;
    private $connectedMasters = [];
    private $logger;
    private $lastActivityTimes = [];
    private $pingTimers = [];
    private $authenticatedMasters = [];  // Track authenticated masters
    private $servicesMaster = [];
    private $authToken = ['register-center-token-2024'];  // Array of valid auth tokens
    private $authTimeoutTimers = [];  // Store auth timeout timers
    private const PING_INTERVAL = 20.0;
    private const AUTH_TIMEOUT = 10.0;  // Authentication timeout in seconds

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

        // 设置认证超时检查
        $this->authTimeoutTimers[$masterId] = $this->loop->addTimer(self::AUTH_TIMEOUT, function () use ($masterId, $connection) {
            if (!isset($this->authenticatedMasters[$masterId])) {
                $this->logger->warning("Authentication timeout, closing connection", ['masterId' => $masterId]);
                $connection->close();
            }
        });

        $tunnelStream->on('cmd', function ($cmd, $message) use ($masterId, $tunnelStream) {
            $this->logger->debug("Received command from master", [
                'masterId' => $masterId,
                'cmd' => $cmd,
                'message' => $message
            ]);
            if ($cmd === 'auth') {
                $this->handleAuth($masterId, $message, $tunnelStream);
            } else {
                // Check if master is authenticated before processing other commands
                if (!isset($this->authenticatedMasters[$masterId])) {
                    $tunnelStream->write([
                        'cmd' => 'auth-failed',
                        'message' => 'Authentication required'
                    ]);
                    return;
                }
                // Handle other commands here
            }
        });
        
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

    private function handleAuth(string $masterId, $message, TunnelStream $tunnelStream): void
    {
        $data = $message;
        
        if (!isset($data['token']) || !in_array($data['token'], $this->authToken)) {
            $this->logger->warning("Failed authentication attempt", ['masterId' => $masterId]);
            $tunnelStream->write([
                'cmd' => 'auth-failed',
                'message' => 'Invalid authentication token'
            ]);
            return;
        }

        $this->authenticatedMasters[$masterId] = true;
        
        // 认证成功后取消超时定时器
        if (isset($this->authTimeoutTimers[$masterId])) {
            $this->loop->cancelTimer($this->authTimeoutTimers[$masterId]);
            unset($this->authTimeoutTimers[$masterId]);
        }
        
        $this->logger->info("Master authenticated successfully", ['masterId' => $masterId]);
        $tunnelStream->write([
            'cmd' => 'auth-success',
            'message' => 'Authentication successful'
        ]);

        $stream = $tunnelStream->run(function ($stream) use ($masterId) {
            $stream->end(\ReactphpX\RegisterCenter\ServiceRegistry::getServiceNameAndMetadata());
        });

        $stream->on('data', function ($serviceNameAndMetadata) use ($masterId) {
            $this->servicesMaster[$masterId] = $serviceNameAndMetadata;
        });
    }

    public function getServicesMaster(): array
    {
        return $this->servicesMaster;
    }

    public function getServicesMasterByMasterId(string $masterId): array
    {
        return $this->servicesMaster[$masterId] ?? [];
    }

    public function getServicesMasterByServiceName(string $serviceName): array
    {
        $_services = [];
        foreach ($this->servicesMaster as $masterId => $services) {
            if (isset($services[$serviceName])) {
                $_services[$masterId] = $services[$serviceName];
            }
        }
        return $_services;
    }

    public function getServicesMasterByServiceNameAndMetadata(string $serviceName, $key, $value): array
    {
        $_services = [];
        foreach ($this->servicesMaster as $masterId => $services) {
            if (isset($services[$serviceName]) && isset($services[$serviceName]['metadata'][$key]) && $services[$serviceName]['metadata'][$key] === $value) {
                $_services[$masterId] = $services[$serviceName];
            }
        }
        return $_services;  
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
        unset($this->authenticatedMasters[$masterId]);
        unset($this->servicesMaster[$masterId]);
        
        // 清理认证超时定时器
        if (isset($this->authTimeoutTimers[$masterId])) {
            $this->loop->cancelTimer($this->authTimeoutTimers[$masterId]);
            unset($this->authTimeoutTimers[$masterId]);
        }
        
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

        if (!isset($this->authenticatedMasters[$masterId])) {
            $this->logger->warning("Attempt to run on unauthenticated master", ['masterId' => $masterId]);
            throw new \Exception("Master not authenticated: {$masterId}");
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

    public function writeRawMessageToAllMasters(array $message): void
    {
        foreach ($this->connectedMasters as $masterId => $master) {
            $master['tunnelStream']->write($message);
        }
    }

    /**
     * Set a new logger instance
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Set authentication tokens
     * @param array $tokens Array of valid authentication tokens
     */
    public function setAuthTokens(array $tokens): void
    {
        $this->authToken = $tokens;
        $this->logger->info("Authentication tokens updated", ['tokenCount' => count($tokens)]);
    }

    /**
     * Add a single authentication token
     * @param string $token The token to add
     */
    public function addAuthToken(string $token): void
    {
        if (!in_array($token, $this->authToken)) {
            $this->authToken[] = $token;
            $this->logger->info("New authentication token added");
        }
    }

    /**
     * Remove an authentication token
     * @param string $token The token to remove
     */
    public function removeAuthToken(string $token): void
    {
        $key = array_search($token, $this->authToken);
        if ($key !== false) {
            unset($this->authToken[$key]);
            $this->authToken = array_values($this->authToken); // 重新索引数组
            $this->logger->info("Authentication token removed");
        }
    }

    /**
     * Get current authentication tokens
     * @return array Current authentication tokens
     */
    public function getAuthTokens(): array
    {
        return $this->authToken;
    }
} 