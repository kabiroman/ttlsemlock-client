<?php

declare(strict_types=1);

namespace TtlSemLock;

use TtlSemLock\Exceptions\TtlSemLockException;

/**
 * LockCollection - RAII pattern for automatic lock management (v0.7.5)
 * 
 * Automatically releases all locks when the collection goes out of scope.
 * 
 * @example
 * ```php
 * $ttlSemLock = new TtlSemLock();
 * 
 * // Acquire multiple locks
 * $locks = $ttlSemLock->acquireMultiple([
 *     'database' => 30,
 *     'filesystem' => 60,
 *     'cache' => 45
 * ]);
 * 
 * // Create collection for automatic cleanup
 * $lockCollection = new LockCollection($ttlSemLock, $locks);
 * 
 * try {
 *     // Critical section with multiple resources
 *     // All locks automatically released when $lockCollection goes out of scope
 * } catch (Exception $e) {
 *     // Locks are still automatically released
 *     throw $e;
 * }
 * ```
 */
class LockCollection
{
    private TtlSemLock $ttlSemLock;
    private array $locks;
    private bool $released = false;

    /**
     * @param TtlSemLock $ttlSemLock TtlSemLock instance
     * @param array $locks Array of [lock_key => owner] pairs
     */
    public function __construct(TtlSemLock $ttlSemLock, array $locks)
    {
        $this->ttlSemLock = $ttlSemLock;
        $this->locks = $locks;
    }

    /**
     * Get all locks in the collection
     * 
     * @return array Array of [lock_key => owner] pairs
     */
    public function getLocks(): array
    {
        return $this->locks;
    }

    /**
     * Get lock owner for specific key
     * 
     * @param string $key Lock key
     * @return string|null Owner string or null if not found
     */
    public function getOwner(string $key): ?string
    {
        return $this->locks[$key] ?? null;
    }

    /**
     * Check if lock exists in collection
     * 
     * @param string $key Lock key
     * @return bool True if lock exists
     */
    public function hasLock(string $key): bool
    {
        return isset($this->locks[$key]);
    }

    /**
     * Get number of locks in collection
     * 
     * @return int Number of locks
     */
    public function count(): int
    {
        return count($this->locks);
    }

    /**
     * Manually release all locks in collection
     * 
     * @return array Array of [lock_key => success] pairs
     */
    public function releaseAll(): array
    {
        if ($this->released) {
            return [];
        }

        $results = [];
        $releasedKeys = [];
        
        foreach ($this->locks as $key => $owner) {
            $results[$key] = $this->ttlSemLock->release($key);
            if ($results[$key]) {
                $releasedKeys[] = $key;
            }
        }

        // Remove successfully released locks from collection
        foreach ($releasedKeys as $key) {
            unset($this->locks[$key]);
        }

        $this->released = true;
        return $results;
    }

    /**
     * Release specific lock from collection
     * 
     * @param string $key Lock key to release
     * @return bool True if lock was released
     */
    public function release(string $key): bool
    {
        if (!isset($this->locks[$key])) {
            return false;
        }

        $success = $this->ttlSemLock->release($key);
        if ($success) {
            unset($this->locks[$key]);
        }

        return $success;
    }

    /**
     * Extend TTL for specific lock in collection
     * 
     * @param string $key Lock key
     * @param int $ttl New TTL in seconds
     * @return bool True if TTL was extended
     */
    public function extend(string $key, int $ttl): bool
    {
        if (!isset($this->locks[$key])) {
            return false;
        }

        return $this->ttlSemLock->extend($key, $ttl, $this->locks[$key]);
    }

    /**
     * Execute callback with all locks held
     * 
     * @param callable $callback Function to execute
     * @param mixed ...$args Arguments to pass to callback
     * @return mixed Return value of callback
     * @throws TtlSemLockException
     */
    public function withLocks(callable $callback, ...$args)
    {
        try {
            return $callback(...$args);
        } finally {
            $this->releaseAll();
        }
    }

    /**
     * Destructor - automatically release all locks
     */
    public function __destruct()
    {
        $this->releaseAll();
    }
} 