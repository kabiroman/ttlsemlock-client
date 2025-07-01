# TtlSemLock PHP Client

🔒 **High-performance PHP client for TtlSemLock distributed semaphore**

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

## Features

- 🚀 **High Performance**: Sub-millisecond operation latency
- 🔐 **Full API Coverage**: acquire, release, exists, stats, extend
- ⏰ **TTL Support**: Automatic lock expiration with extend capability
- 🔄 **Retry Logic**: Built-in retry mechanisms for lock contention
- 🛡️ **Error Handling**: Comprehensive exception hierarchy
- 📦 **PSR-4 Compatible**: Modern PHP package structure
- 🧪 **Well Tested**: Comprehensive examples and tests
- 🔗 **Symfony Integration**: Compatible with Symfony Lock Component
- 📋 **Broad Compatibility**: PHP 8.1+ and Symfony 6.4+ support
- 🔐 **Enterprise Security**: API Key authentication (v0.4.0+)

## Requirements

- **PHP**: 8.1+
- **Extensions**: `json`, `sockets` (for TCP connections)
- **Symfony Lock** (optional): 6.4+ or 7.0+

> 📋 **See [COMPATIBILITY.md](COMPATIBILITY.md) for detailed version support and migration guide**

## Compatibility Matrix

| Component | Version | PHP 8.1 | PHP 8.2 | PHP 8.3+ | Status |
|-----------|---------|---------|---------|----------|--------|
| **TtlSemLock Core** | 0.2.x | ✅ | ✅ | ✅ | Fully tested |
| **Symfony Lock 6.4** | LTS | ✅ | ✅ | ✅ | Production ready |
| **Symfony Lock 7.0** | Current | ✅ | ✅ | ✅ | Production ready |
| **Symfony Lock 7.3** | Latest | ❌ | ✅ | ✅ | Requires PHP 8.2+ |

### Framework Support

| Framework | Version | Compatibility | Notes |
|-----------|---------|---------------|-------|
| **Symfony** | 6.4 LTS | ✅ Full | Via TtlSemLockStore adapter |
| **Symfony** | 7.0+ | ✅ Full | Native PersistingStoreInterface |
| **Laravel** | 9.x+ | 🔄 Planned | Custom Lock driver planned |
| **Native PHP** | Any | ✅ Full | Direct TtlSemLock client |

## Installation

### Via Composer (Recommended)

```bash
composer require ttlsemlock/client
```

### For Symfony Integration

```bash
# For Symfony 6.4 (LTS) - PHP 8.1+
composer require ttlsemlock/client symfony/lock:^6.4

# For Symfony 7.0+ - PHP 8.2+
composer require ttlsemlock/client symfony/lock:^7.0
```

### Manual Installation

1. Download the source code
2. Include the autoloader:

```php
require_once 'path/to/ttlsemlock/php/src/TtlSemLock.php';
```

## Quick Start

### Basic Usage

```php
<?php

use TtlSemLock\TtlSemLock;

// Initialize client
$lock = new TtlSemLock('localhost', 8765);

// Acquire lock
if ($lock->acquire('user-123', 300)) {
    // Critical section - your code here
    echo "Lock acquired! Doing work...\n";
    
    // Release lock when done
    $lock->release('user-123');
}
```

### Using with API Key (v0.4.0+)

For production environments with API key authentication enabled:

```php
<?php

use TtlSemLock\TtlSemLock;

// Initialize client with API key
$lock = new TtlSemLock('localhost', 8765, 'your-production-api-key');

// All operations work the same way
if ($lock->acquire('secure-resource', 300)) {
    echo "Secure lock acquired!\n";
    $lock->release('secure-resource');
}

// Multiple environments support
$prodLock = new TtlSemLock('prod.example.com', 8765, 'prod-key-12345');
$devLock = new TtlSemLock('localhost', 8765); // No auth in development
```

### Advanced Usage

```php
<?php

use TtlSemLock\TtlSemLock;

$lock = new TtlSemLock('localhost', 8765);

// Acquire with automatic owner and extend TTL
$owner = $lock->acquireWithOwner('process-lock', 60);
if ($owner) {
    echo "Lock acquired by: {$owner}\n";
    
    // Extend TTL if needed
    $lock->extend('process-lock', 120, $owner);
    
    // Use callback pattern for automatic cleanup
    $result = $lock->withLock('callback-lock', 30, function($data) {
        // Your critical code here
        return "processed: {$data}";
    }, 'input-data');
    
    $lock->release('process-lock');
}
```

## Symfony Integration

TtlSemLock can be used as a backend for Symfony Lock Component:

```php
// Install: composer require symfony/lock
use Symfony\Component\Lock\LockFactory;
use TtlSemLock\TtlSemLock;
use TtlSemLock\Store\TtlSemLockStore;

// Create TtlSemLock client
$client = new TtlSemLock('localhost', 8765);

// Create Symfony Lock store adapter
$store = new TtlSemLockStore($client, 300); // 300s default TTL

// Use with Symfony Lock Factory
$factory = new LockFactory($store);
$lock = $factory->createLock('resource-name', 60);

if ($lock->acquire()) {
    // Critical section - only one process can be here
    // All Symfony Lock features work: TTL, refresh, blocking, etc.
    $lock->refresh(120); // Extend TTL
    $lock->release();
}
```

### Symfony Framework Configuration

#### For Symfony 6.4 LTS (PHP 8.1+)

```yaml
# config/packages/lock.yaml
framework:
    lock:
        ttlsemlock:
            class: 'TtlSemLock\Store\TtlSemLockStore'
            arguments:
                - '@TtlSemLock\TtlSemLock'
                - 300 # default TTL

# config/services.yaml
services:
    TtlSemLock\TtlSemLock:
        arguments:
            $host: '%env(TTLSEMLOCK_HOST)%'
            $port: '%env(int:TTLSEMLOCK_PORT)%'
```

#### For Symfony 7.0+ (PHP 8.2+)

```yaml
# config/packages/lock.yaml
framework:
    lock:
        ttlsemlock:
            class: 'TtlSemLock\Store\TtlSemLockStore'
            arguments:
                - '@TtlSemLock\TtlSemLock'
                - 300

# config/services.yaml - same as 6.4
services:
    TtlSemLock\TtlSemLock:
        arguments:
            $host: '%env(TTLSEMLOCK_HOST)%'
            $port: '%env(int:TTLSEMLOCK_PORT)%'
```

#### Environment Variables

```bash
# .env
TTLSEMLOCK_HOST=localhost
TTLSEMLOCK_PORT=8765
TTLSEMLOCK_API_KEY=your-production-key-here  # v0.4.0+
```

#### Service Configuration with API Key (v0.4.0+)

```yaml
# config/services.yaml
services:
    TtlSemLock\TtlSemLock:
        arguments:
            $host: '%env(TTLSEMLOCK_HOST)%'
            $port: '%env(int:TTLSEMLOCK_PORT)%'
            $apiKey: '%env(TTLSEMLOCK_API_KEY)%'  # Optional for security
```

## Security Configuration (v0.4.0+)

TtlSemLock supports API Key authentication for secure production deployments:

### Server Configuration

Create `config.security.json`:

```json
{
  "server": {
    "host": "0.0.0.0",
    "port": 8765
  },
  "security": {
    "auth_required": true,
    "api_keys": [
      "production-key-32-chars-minimum",
      "backup-key-for-emergencies-only"
    ],
    "cache_auth_results": true,
    "cache_ttl_seconds": 300
  }
}
```

Start server with security:
```bash
./ttl-semlock --config config.security.json
```

### PHP Client with Authentication

```php
<?php
use TtlSemLock\TtlSemLock;

// Production with API key
$client = new TtlSemLock('prod.example.com', 8765, 'production-key-32-chars-minimum');

// Development without auth (backward compatible)
$devClient = new TtlSemLock('localhost', 8765);

// Environment-based configuration
$apiKey = getenv('TTLSEMLOCK_API_KEY') ?: null;
$client = new TtlSemLock('localhost', 8765, $apiKey);
```

### Error Handling

```php
<?php
use TtlSemLock\TtlSemLock;
use TtlSemLock\Exceptions\ConnectionException;

try {
    $client = new TtlSemLock('prod.example.com', 8765, 'wrong-key');
    $client->acquire('test-lock', 60);
} catch (ConnectionException $e) {
    if (strpos($e->getMessage(), 'authentication failed') !== false) {
        error_log('Invalid API key - check configuration');
        // Fallback to development server or handle error
    }
}
```

### Best Practices

- 🔑 **Use strong API keys**: Minimum 32 characters, random generation
- 🔄 **Rotate keys regularly**: Update API keys every 90-180 days  
- 🌍 **Environment separation**: Different keys for prod/staging/dev
- 📝 **Monitor auth failures**: Track failed authentication attempts
- 🚫 **Disable auth in development**: Set `auth_required: false` locally
- 🔒 **Secure key storage**: Use environment variables, not hardcoded keys

## API Reference

### Constructor

```php
new TtlSemLock(string $host = 'localhost', int $port = 8765, int $timeout = 5)
```

### Core Methods

#### `acquire(string $key, int $ttl, string $pid = '', string $host = ''): bool`

Acquire a distributed lock.

- **$key**: Lock identifier
- **$ttl**: Time to live in seconds (max 86400)
- **$pid**: Process identifier (auto-detected if empty)
- **$host**: Host identifier (auto-detected if empty)
- **Returns**: `true` if acquired, `false` if already locked

#### `release(string $key): bool`

Release a distributed lock.

- **$key**: Lock identifier
- **Returns**: `true` if released, `false` if lock didn't exist

#### `exists(string $key): bool`

Check if a lock exists and is active.

- **$key**: Lock identifier
- **Returns**: `true` if lock exists and is active

#### `extend(string $key, int $ttl, string $owner): bool`

Extend TTL of an existing lock.

- **$key**: Lock identifier
- **$ttl**: New TTL in seconds (max 86400)
- **$owner**: Owner in format "pid@host"
- **Returns**: `true` if extended, `false` if failed

#### `stats(): array`

Get server statistics.

- **Returns**: Array with server stats (TotalRequests, ActiveLocks, etc.)

### Convenience Methods

#### `acquireWithOwner(string $key, int $ttl): ?string`

Acquire lock with automatic owner generation.

- **Returns**: Owner string if acquired, `null` if failed

#### `acquireWithRetry(string $key, int $ttl, int $maxRetries = 3, int $retryDelay = 100000): ?string`

Try to acquire lock with retry logic.

- **$maxRetries**: Maximum retry attempts
- **$retryDelay**: Delay between retries in microseconds
- **Returns**: Owner string if acquired, `null` if failed

#### `withLock(string $key, int $ttl, callable $callback, ...$args): mixed`

Execute a callback with distributed lock.

- **$callback**: Function to execute while locked
- **$args**: Arguments to pass to callback
- **Returns**: Return value of callback
- **Throws**: `TtlSemLockException` if lock cannot be acquired

## Examples

### Basic Lock Operations

```php
$lock = new TtlSemLock();

// Simple acquire/release
if ($lock->acquire('my-lock', 60)) {
    // Do work
    $lock->release('my-lock');
}

// Check if lock exists
if ($lock->exists('my-lock')) {
    echo "Lock is active\n";
}

// Get server stats
$stats = $lock->stats();
echo "Active locks: {$stats['ActiveLocks']}\n";
```

### TTL Extension

```php
$lock = new TtlSemLock();

$owner = $lock->acquireWithOwner('long-task', 60);
if ($owner) {
    // Start long-running task
    processLargeFile();
    
    // Need more time? Extend TTL
    $lock->extend('long-task', 120, $owner);
    
    // Continue processing
    processMoreData();
    
    $lock->release('long-task');
}
```

### Retry Logic

```php
$lock = new TtlSemLock();

// Try to acquire with retries
$owner = $lock->acquireWithRetry('contested-resource', 30, 5, 200000);
if ($owner) {
    echo "Got lock after retries!\n";
    $lock->release('contested-resource');
} else {
    echo "Failed to acquire lock after retries\n";
}
```

### Callback Pattern

```php
$lock = new TtlSemLock();

try {
    $result = $lock->withLock('critical-section', 60, function($userId) {
        // This code runs while locked
        return updateUserProfile($userId);
    }, 12345);
    
    echo "Result: {$result}\n";
} catch (TtlSemLockException $e) {
    echo "Lock failed: {$e->getMessage()}\n";
}
```

## Migration Guide

### From Redis/Memcached to TtlSemLock

Replace your existing Symfony Lock store configuration:

```yaml
# Before - Redis Store
framework:
    lock: 'redis://localhost:6379'

# After - TtlSemLock Store  
framework:
    lock:
        ttlsemlock:
            class: 'TtlSemLock\Store\TtlSemLockStore'
            arguments: ['@TtlSemLock\TtlSemLock', 300]
```

### Version Compatibility Testing

Test compatibility with your PHP/Symfony versions:

```bash
# Run compatibility test
php vendor/ttlsemlock/client/examples/compatibility_test.php

# Expected output for PHP 8.1 + Symfony 6.4:
# ✅ All compatibility tests passed
# 📊 Performance: ~4000 ops/sec
```

### Performance Comparison

| Store Type | Latency | Throughput | Memory |
|------------|---------|------------|--------|
| **TtlSemLock** | ~0.2ms | 5K+ ops/sec | Low |
| Redis | ~0.5ms | 3K ops/sec | Medium |
| Memcached | ~0.3ms | 4K ops/sec | Medium |
| Database | ~2ms | 500 ops/sec | High |

## Error Handling

The client throws specific exceptions for different error types:

```php
use TtlSemLock\Exceptions\TtlSemLockException;
use TtlSemLock\Exceptions\ConnectionException;

try {
    $lock = new TtlSemLock('unreachable-host');
    $lock->acquire('test', 60);
} catch (ConnectionException $e) {
    echo "Connection failed: {$e->getMessage()}\n";
} catch (TtlSemLockException $e) {
    echo "Lock operation failed: {$e->getMessage()}\n";
}
```

## Performance

The PHP client is optimized for high performance:

- **~0.1-0.2ms** average operation latency
- **5000+ ops/sec** throughput on modern hardware
- **Minimal memory footprint**
- **Connection pooling ready**

Run the performance test:

```bash
php examples/performance_test.php
```

## Requirements

- **PHP 8.0+**
- **TCP connection** to TtlSemLock server
- **JSON extension** (included in PHP by default)

## Configuration

### Connection Settings

```php
// Custom connection settings
$lock = new TtlSemLock(
    host: 'semlock.example.com',
    port: 8765,
    timeout: 10  // seconds
);

// Get connection info
$info = $lock->getConnectionInfo();
print_r($info);
```

### Server Configuration

Ensure your TtlSemLock server is configured correctly:

```json
{
    "port": 8765,
    "host": "0.0.0.0",
    "timeout": 60
}
```

## Testing

Run the examples to test functionality:

```bash
# Basic usage
php examples/basic_usage.php

# Advanced features
php examples/advanced_usage.php

# Performance benchmarks
php examples/performance_test.php
```

## Contributing

1. Fork the repository
2. Create your feature branch
3. Write tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## License

MIT License - see LICENSE file for details.

## Support

- 📖 **Documentation**: [Main README](../README.md)
- 🐛 **Issues**: [GitHub Issues](https://github.com/ttlsemlock/ttlsemlock/issues)
- 💬 **Discussions**: [GitHub Discussions](https://github.com/ttlsemlock/ttlsemlock/discussions)

---

**TtlSemLock PHP Client** - Part of the TtlSemLock distributed semaphore system. 