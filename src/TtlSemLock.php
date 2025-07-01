<?php

declare(strict_types=1);

namespace TtlSemLock;

use TtlSemLock\Exceptions\ConnectionException;
use TtlSemLock\Exceptions\TtlSemLockException;

/**
 * TtlSemLock PHP Client
 * 
 * High-performance distributed semaphore with TTL support.
 * 
 * @example
 * ```php
 * $lock = new TtlSemLock('localhost', 8765);
 * 
 * if ($lock->acquire('user-123', 300, 'web-worker', 'server-01')) {
 *     // Critical section
 *     $lock->extend('user-123', 600, 'web-worker@server-01'); // Extend TTL
 *     $lock->release('user-123');
 * }
 * ```
 */
class TtlSemLock
{
    private string $host;
    private int $port;
    private int $timeout;
    private ?string $apiKey;
    
    // v0.7.4: Advanced retry configuration
    private array $retryConfig = [
        'max_attempts' => 5,
        'base_delay' => 100,        // ms
        'max_delay' => 5000,        // ms
        'jitter' => 0.1,            // 10% random
        'backoff_multiplier' => 2.0
    ];

    /**
     * @param string $host Server hostname (default: localhost)
     * @param int $port Server port (default: 8765)
     * @param int $timeout Connection timeout in seconds (default: 5)
     * @param string|null $apiKey API key for authentication (optional)
     */
    public function __construct(string $host = 'localhost', int $port = 8765, int $timeout = 5, ?string $apiKey = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->apiKey = $apiKey;
    }

    /**
     * Set retry configuration for all operations
     * 
     * @param array $config Retry configuration
     * @return void
     */
    public function setRetryConfig(array $config): void
    {
        $this->retryConfig = array_merge($this->retryConfig, $config);
    }

    /**
     * Get current retry configuration
     * 
     * @return array Current retry configuration
     */
    public function getRetryConfig(): array
    {
        return $this->retryConfig;
    }

    /**
     * Calculate exponential backoff delay with jitter
     * 
     * @param int $attempt Current attempt number (0-based)
     * @param array $config Retry configuration
     * @return int Delay in microseconds
     */
    private function calculateBackoffDelay(int $attempt, array $config): int
    {
        $baseDelay = $config['base_delay'] ?? 100;
        $maxDelay = $config['max_delay'] ?? 5000;
        $multiplier = $config['backoff_multiplier'] ?? 2.0;
        $jitter = $config['jitter'] ?? 0.1;

        // Exponential backoff: base_delay * (multiplier ^ attempt)
        $delay = $baseDelay * pow($multiplier, $attempt);
        
        // Cap at max_delay
        $delay = min($delay, $maxDelay);
        
        // Add jitter: ±jitter% random variation
        $jitterAmount = $delay * $jitter;
        $delay += (mt_rand(0, 200) / 100 - 1) * $jitterAmount;
        
        // Convert to microseconds and ensure positive
        return max(1000, (int)($delay * 1000));
    }

    /**
     * Acquire a distributed lock
     * 
     * @param string $key Lock identifier
     * @param int $ttl Time to live in seconds (max 86400)
     * @param string $pid Process identifier (optional)
     * @param string $host Host identifier (optional)
     * @return bool True if lock acquired, false if already locked
     * @throws TtlSemLockException
     */
    public function acquire(string $key, int $ttl, string $pid = '', string $host = ''): bool
    {
        if (empty($pid)) {
            $pid = (string)getmypid();
        }
        if (empty($host)) {
            $host = gethostname() ?: 'php-client';
        }

        $response = $this->sendCommand([
            'action' => 'acquire',
            'key' => $key,
            'ttl' => $ttl,
            'pid' => $pid,
            'host' => $host
        ]);

        return $response['success'] ?? false;
    }

    /**
     * Release a distributed lock
     * 
     * @param string $key Lock identifier
     * @return bool True if lock was released, false if lock didn't exist
     * @throws TtlSemLockException
     */
    public function release(string $key): bool
    {
        $response = $this->sendCommand([
            'action' => 'release',
            'key' => $key
        ]);

        return $response['success'] ?? false;
    }

    /**
     * Check if a lock exists and is active
     * 
     * @param string $key Lock identifier
     * @return bool True if lock exists and is active
     * @throws TtlSemLockException
     */
    public function exists(string $key): bool
    {
        $response = $this->sendCommand([
            'action' => 'exists',
            'key' => $key
        ]);

        return $response['success'] ?? false;
    }

    /**
     * Extend TTL of an existing lock
     * 
     * @param string $key Lock identifier
     * @param int $ttl New TTL in seconds (max 86400)
     * @param string $owner Owner in format "pid@host"
     * @return bool True if TTL was extended, false if lock doesn't exist or wrong owner
     * @throws TtlSemLockException
     */
    public function extend(string $key, int $ttl, string $owner): bool
    {
        $response = $this->sendCommand([
            'action' => 'extend',
            'key' => $key,
            'ttl' => $ttl,
            'owner' => $owner
        ]);

        return $response['success'] ?? false;
    }

    /**
     * Get server statistics
     * 
     * @return array{TotalRequests: int, ActiveLocks: int, AcquireRequests: int, ReleaseRequests: int}
     * @throws TtlSemLockException
     */
    public function stats(): array
    {
        $response = $this->sendCommand([
            'action' => 'stats'
        ]);

        if (!($response['success'] ?? false)) {
            throw new TtlSemLockException('Failed to get stats: ' . ($response['error'] ?? 'Unknown error'));
        }

        return $response['data'] ?? [];
    }

    /**
     * Acquire lock with automatic owner generation
     * 
     * @param string $key Lock identifier
     * @param int $ttl Time to live in seconds
     * @return string|null Owner string if acquired, null if failed
     * @throws TtlSemLockException
     */
    public function acquireWithOwner(string $key, int $ttl): ?string
    {
        $pid = (string)getmypid();
        $host = gethostname() ?: 'php-client';
        $owner = $pid . '@' . $host;

        if ($this->acquire($key, $ttl, $pid, $host)) {
            return $owner;
        }

        return null;
    }

    /**
     * Try to acquire lock with advanced retry logic (v0.7.4)
     * 
     * @param string $key Lock identifier
     * @param int $ttl Time to live in seconds
     * @param array|int $retryConfig Optional retry configuration override or max retries (backward compatibility)
     * @param int $retryDelay Optional retry delay in microseconds (backward compatibility)
     * @return string|null Owner string if acquired, null if failed
     * @throws TtlSemLockException
     */
    public function acquireWithRetry(string $key, int $ttl, $retryConfig = [], int $retryDelay = 0): ?string
    {
        // Backward compatibility: convert old API to new format
        if (is_int($retryConfig)) {
            $retryConfig = [
                'max_attempts' => $retryConfig,
                'base_delay' => $retryDelay > 0 ? $retryDelay / 1000 : 100 // Convert to ms
            ];
        }
        
        // Merge with default config
        $config = array_merge($this->retryConfig, $retryConfig);
        $maxAttempts = $config['max_attempts'] ?? 5;
        
        for ($attempt = 0; $attempt <= $maxAttempts; $attempt++) {
            $owner = $this->acquireWithOwner($key, $ttl);
            if ($owner !== null) {
                return $owner;
            }

            // Don't sleep on the last attempt
            if ($attempt < $maxAttempts) {
                $delay = $this->calculateBackoffDelay($attempt, $config);
                usleep($delay);
            }
        }

        return null;
    }

    /**
     * Execute a callback with distributed lock
     * 
     * @param string $key Lock identifier
     * @param int $ttl Time to live in seconds
     * @param callable $callback Function to execute while locked
     * @param mixed ...$args Arguments to pass to callback
     * @return mixed Return value of callback
     * @throws TtlSemLockException
     */
    public function withLock(string $key, int $ttl, callable $callback, ...$args)
    {
        $owner = $this->acquireWithOwner($key, $ttl);
        if ($owner === null) {
            throw new TtlSemLockException("Failed to acquire lock: {$key}");
        }

        try {
            return $callback(...$args);
        } finally {
            $this->release($key);
        }
    }

    /**
     * Acquire multiple locks with graceful release (v0.7.5)
     * 
     * @param array $locks Array of [lock_key => ttl] pairs
     * @param array $options Configuration options
     * @return array Array of acquired lock owners [lock_key => owner]
     * @throws TtlSemLockException
     */
    public function acquireMultiple(array $locks, array $options = []): array
    {
        $defaultOptions = [
            'auto_release_on_failure' => true,
            'release_strategy' => 'all_or_nothing', // 'all_or_nothing', 'partial'
            'retry_config' => []
        ];
        
        $options = array_merge($defaultOptions, $options);
        $acquiredLocks = [];
        $failedLocks = [];
        
        // Try to acquire all locks
        foreach ($locks as $key => $ttl) {
            $retryConfig = $options['retry_config'] ?? [];
            $owner = $this->acquireWithRetry($key, $ttl, $retryConfig);
            
            if ($owner !== null) {
                $acquiredLocks[$key] = $owner;
            } else {
                $failedLocks[] = $key;
            }
        }
        
        // Handle partial acquisition
        if (!empty($failedLocks)) {
            if ($options['auto_release_on_failure']) {
                // Release all acquired locks
                foreach ($acquiredLocks as $key => $owner) {
                    $this->release($key);
                }
                
                if ($options['release_strategy'] === 'all_or_nothing') {
                    throw new TtlSemLockException(
                        "Failed to acquire all locks. Released acquired locks: " . 
                        implode(', ', array_keys($acquiredLocks)) . 
                        ". Failed locks: " . implode(', ', $failedLocks)
                    );
                }
            }
            
            // Return partial results if strategy allows
            if ($options['release_strategy'] === 'partial') {
                return $acquiredLocks;
            }
        }
        
        return $acquiredLocks;
    }

    /**
     * Send command to TtlSemLock server
     * 
     * @param array $command Command data
     * @return array Response data
     * @throws ConnectionException
     * @throws TtlSemLockException
     */
    private function sendCommand(array $command): array
    {
        // Add API key if set
        if ($this->apiKey !== null) {
            $command['api_key'] = $this->apiKey;
        }

        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        
        if (!$socket) {
            throw new ConnectionException("Cannot connect to {$this->host}:{$this->port} - {$errstr} ({$errno})");
        }

        try {
            $json = json_encode($command, JSON_THROW_ON_ERROR);
            fwrite($socket, $json . "\n");

            $response = fgets($socket);
            if ($response === false) {
                throw new ConnectionException('Failed to read response from server');
            }

            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            
            if (isset($decoded['error'])) {
                throw new TtlSemLockException($decoded['error']);
            }

            return $decoded;

        } catch (\JsonException $e) {
            throw new TtlSemLockException('JSON error: ' . $e->getMessage());
        } finally {
            fclose($socket);
        }
    }

    /**
     * Get connection info
     * 
     * @return array{host: string, port: int, timeout: int}
     */
    public function getConnectionInfo(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'timeout' => $this->timeout
        ];
    }
}
