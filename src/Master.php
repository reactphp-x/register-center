<?php

namespace ReactphpX\RegisterCenter;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\Connector;
use ReactphpX\TunnelStream\TunnelStream;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Stream\DuplexStreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class Master implements \Evenement\EventEmitterInterface
{
    use \Evenement\EventEmitterTrait;
    
    private $loop;
    private $connections = [];
    private $tunnelStreams = [];
    private $logger;
    private $retryAttempts;
    private $retryDelay;
    private $connectionRetries = [];
    private $reconnectOnClose;
    private $connectionConfigs = [];

    public function __construct(
        int $retryAttempts = PHP_INT_MAX,
        float $retryDelay = 2.0,
        bool $reconnectOnClose = true,
        ?LoggerInterface $logger = null, 
        ?LoopInterface $loop = null
    ) {
        $this->loop = $loop ?? Loop::get();
        $this->logger = $logger ?? new NullLogger();
        $this->retryAttempts = $retryAttempts;
        $this->retryDelay = $retryDelay;
        $this->reconnectOnClose = $reconnectOnClose;
    }

    /**
     * Connect to a registration center using React\Socket\Connector
     */
    public function connectViaConnector(string $host, int $port, ?bool $reconnectOnClose = null): void
    {

        if (isset($this->connectionConfigs[$host . ':' . $port])) {
            return;
        }

        // Store connection config for potential reconnection
        $connectionId = "$host:$port";
        $this->connectionConfigs[$connectionId] = [
            'host' => $host,
            'port' => $port,
            'reconnectOnClose' => $reconnectOnClose ?? $this->reconnectOnClose
        ];

        $this->connectWithRetry($host, $port)
            ->then(null, function (\Exception $e) use ($host, $port) {
                // Connection failure is already logged in connectWithRetry
                $this->emit('error', [$e, compact('host', 'port')]);
            });
    }

    public function removeConnection(string $host, int $port): void
    {
        $connectionId = "$host:$port";
        
        // Remove connection config to prevent reconnection
        unset($this->connectionConfigs[$connectionId]);
        
        // Find and close the connection if it exists
        foreach ($this->connections as $id => $conn) {
            if (strpos($id, $connectionId) === 0) {
                $conn->close();
            }
        }
        
        $this->logger->info("No active connection found to remove", [
            'host' => $host,
            'port' => $port
        ]);
    }

    public function close()
    {
        foreach ($this->connections as $id => $conn) {
            $conn->close();
        }
        $this->connections = [];
        $this->tunnelStreams = [];
        $this->connectionConfigs = [];
        $this->connectionRetries = [];
        $this->logger->info("All connections closed");
    }

    /**
     * Internal method to handle connection with retry mechanism
     */
    private function connectWithRetry(string $host, int $port, int $attempt = 1): PromiseInterface
    {
        $connectionId = "$host:$port";

        if (!isset($this->connectionConfigs[$connectionId])) {
            $this->logger->error("Connection config not found", [
                'connectionId' => $connectionId
            ]);
            return \React\Promise\reject(new \Exception("Connection config not found"));
        }
        
        $this->logger->info("Attempting to connect to registration center", [
            'host' => $host,
            'port' => $port,
            'attempt' => $attempt,
            'maxAttempts' => $this->retryAttempts
        ]);

        $connector = new Connector(loop: $this->loop);
        return $connector->connect("$host:$port")
            ->then(function (DuplexStreamInterface $conn) use ($host, $port, $connectionId) {
                
                // Reset retry count on successful connection
                unset($this->connectionRetries[$connectionId]);
                
                return $this->setupConnection($connectionId, $conn);
            }, function (\Exception $e) use ($host, $port, $attempt, $connectionId) {
                $this->logger->error("Failed to connect to registration center", [
                    'host' => $host,
                    'port' => $port,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt
                ]);

                $this->emit('error', [$e, compact('host', 'port', 'attempt')]);

                // Check if we should retry
                if ($attempt < $this->retryAttempts) {
                    $this->logger->info("Scheduling retry", [
                        'host' => $host,
                        'port' => $port,
                        'nextAttempt' => $attempt + 1,
                        'delay' => $this->retryDelay
                    ]);

                    return new Promise(function ($resolve, $reject) use ($host, $port, $attempt) {
                        $this->loop->addTimer($this->retryDelay, function () use ($resolve, $reject, $host, $port, $attempt) {
                            $this->connectWithRetry($host, $port, $attempt + 1)
                                ->then($resolve, $reject);
                        });
                    });
                }
            });
    }

    /**
     * Set up the connection with TunnelStream
     */
    private function setupConnection(string $connectionId, DuplexStreamInterface $conn): string
    {
        $id = $connectionId;
        $this->connections[$id] = $conn;
        
        // Create TunnelStream
        $tunnelStream = new TunnelStream($conn, $conn, true);
        $this->emit('connect', [$tunnelStream]);
        $this->tunnelStreams[$id] = $tunnelStream;
        
        $this->logger->info("Connected to registration center", [
            'connectionId' => $connectionId,
            'id' => $id
        ]);

        // Handle connection errors
        $conn->on('error', function (\Exception $e) use ($id, $connectionId) {
            $this->logger->error("Connection error", [
                'connectionId' => $connectionId,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            $this->emit('error', [$e, ['id' => $id, 'connectionId' => $connectionId]]);
        });
        
        // Handle connection close
        $conn->on('close', function () use ($id, $connectionId) {
            $this->emit('close', [$id, $connectionId]);
            unset($this->connections[$id]);
            unset($this->tunnelStreams[$id]);
            
            $this->logger->info("Disconnected from registration center", [
                'connectionId' => $connectionId,
                'id' => $id
            ]);

            // Handle reconnection if enabled
            list($host, $port) = explode(':', $connectionId);
            $connectionId = "$host:$port";
            if (isset($this->connectionConfigs[$connectionId]) && 
                $this->connectionConfigs[$connectionId]['reconnectOnClose']) {
                $this->logger->info("Scheduling reconnection", [
                    'connectionId' => $connectionId,
                    'delay' => $this->retryDelay
                ]);
                
                $this->loop->addTimer($this->retryDelay, function () use ($host, $port) {
                    $this->connectWithRetry($host, $port)
                        ->then(null, function (\Exception $e) use ($host, $port) {
                            $this->logger->error("Reconnection failed", [
                                'host' => $host,
                                'port' => $port,
                                'error' => $e->getMessage()
                            ]);
                            $this->emit('error', [$e, compact('host', 'port')]);
                        });
                });
            }
        });
        
        return $id;
    }

    /**
     * Get all connected registration centers
     */
    public function getConnectedCenters(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Get a specific TunnelStream by ID
     */
    public function getTunnelStream(string $id): ?TunnelStream
    {
        if (!isset($this->tunnelStreams[$id])) {
            $this->logger->warning("TunnelStream not found", ['id' => $id]);
            return null;
        }
        return $this->tunnelStreams[$id];
    }

    /**
     * Set a new logger instance
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Configure retry settings
     */
    public function setRetrySettings(int $attempts, float $delay): void
    {
        $this->retryAttempts = $attempts;
        $this->retryDelay = $delay;
    }

    /**
     * Configure reconnection behavior
     */
    public function setReconnectOnClose(bool $reconnect): void
    {
        $this->reconnectOnClose = $reconnect;
    }
} 