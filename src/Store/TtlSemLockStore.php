<?php

declare(strict_types=1);

namespace TtlSemLock\Store;

use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\Exception\NotSupportedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use TtlSemLock\TtlSemLock;
use TtlSemLock\Exceptions\TtlSemLockException;

/**
 * TtlSemLockStore - Symfony Lock Component Store adapter for TtlSemLock
 * 
 * This adapter allows using TtlSemLock as a backend for Symfony Lock Component,
 * enabling integration with Symfony applications while keeping the high performance
 * characteristics of TtlSemLock.
 * 
 * @example
 * ```php
 * use Symfony\Component\Lock\LockFactory;
 * use TtlSemLock\TtlSemLock;
 * use TtlSemLock\Store\TtlSemLockStore;
 * 
 * $client = new TtlSemLock('localhost', 8765);
 * $store = new TtlSemLockStore($client);
 * $factory = new LockFactory($store);
 * 
 * $lock = $factory->createLock('user-processing-'.$userId, 300);
 * if ($lock->acquire()) {
 *     // Critical section
 *     $lock->release();
 * }
 * ```
 */
class TtlSemLockStore implements PersistingStoreInterface
{
    private TtlSemLock $client;
    private int $defaultTtl;
    private array $lockOwners = [];

    /**
     * @param TtlSemLock $client TtlSemLock client instance
     * @param int $defaultTtl Default TTL in seconds when none specified (default: 300)
     */
    public function __construct(TtlSemLock $client, int $defaultTtl = 300)
    {
        $this->client = $client;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * {@inheritdoc}
     */
    public function save(Key $key): void
    {
        $resource = (string)$key;
        
        // Set TTL on the key like other Symfony stores
        $key->reduceLifetime($this->defaultTtl);
        
        try {
            $owner = $this->client->acquireWithOwner($resource, $this->defaultTtl);
            if ($owner === null) {
                throw new LockConflictedException(sprintf('Lock "%s" is already acquired.', $resource));
            }
            
            // Store owner for later use
            $this->lockOwners[$resource] = $owner;
            
            // Set the token for Symfony compatibility
            $key->setState(self::class, $owner);
            
        } catch (TtlSemLockException $e) {
            throw new InvalidArgumentException(sprintf('Failed to acquire lock "%s": %s', $resource, $e->getMessage()), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key): void
    {
        $resource = (string)$key;
        
        try {
            if (!$this->client->release($resource)) {
                throw new LockReleasingException(sprintf('Failed to release lock "%s".', $resource));
            }
            
            // Clean up stored owner
            unset($this->lockOwners[$resource]);
            
        } catch (TtlSemLockException $e) {
            throw new LockReleasingException(sprintf('Failed to release lock "%s": %s', $resource, $e->getMessage()), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key): bool
    {
        try {
            return $this->client->exists((string)$key);
        } catch (TtlSemLockException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, float $ttl): void
    {
        $resource = (string)$key;
        
        // Get the owner for this lock
        $owner = $this->lockOwners[$resource] ?? $key->getState(self::class);
        
        if (!$owner) {
            throw new LockReleasingException(sprintf('Cannot extend lock "%s": owner not found.', $resource));
        }
        
        try {
            if (!$this->client->extend($resource, (int)ceil($ttl), $owner)) {
                throw new LockReleasingException(sprintf('Failed to extend lock "%s".', $resource));
            }
        } catch (TtlSemLockException $e) {
            throw new LockReleasingException(sprintf('Failed to extend lock "%s": %s', $resource, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Get TtlSemLock client for advanced operations
     * 
     * @return TtlSemLock
     */
    public function getClient(): TtlSemLock
    {
        return $this->client;
    }

    /**
     * Get server statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        try {
            return $this->client->stats();
        } catch (TtlSemLockException $e) {
            return [];
        }
    }

    /**
     * Get connection information
     * 
     * @return array
     */
    public function getConnectionInfo(): array
    {
        return $this->client->getConnectionInfo();
    }


}
