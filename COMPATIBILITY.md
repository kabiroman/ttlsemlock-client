# TtlSemLock PHP Client Compatibility Guide

## Overview

TtlSemLock PHP Client provides broad compatibility across modern PHP versions and frameworks while maintaining high performance and reliability.

## PHP Version Support

### Minimum Requirements

- **PHP 8.1+** is required for core functionality
- **JSON extension** (standard in all PHP installations)
- **Socket functions** for TCP communication

### Feature Support by PHP Version

| Feature | PHP 8.1 | PHP 8.2 | PHP 8.3+ |
|---------|---------|---------|----------|
| **Core Client** | ✅ Full | ✅ Full | ✅ Full |
| **Type Declarations** | ✅ Modern | ✅ Modern | ✅ Latest |
| **Error Handling** | ✅ Complete | ✅ Complete | ✅ Complete |
| **Performance** | ✅ Optimized | ✅ Optimized | ✅ Maximum |

### PHP Version Migration

#### From PHP 8.0
```bash
# Update PHP version (Ubuntu/Debian example)
sudo apt update
sudo apt install php8.1-cli php8.1-json

# Verify version
php --version  # Should show 8.1+

# Test compatibility
php vendor/ttlsemlock/client/examples/compatibility_test.php
```

#### From PHP 7.x
```bash
# Major upgrade required
# Review code for PHP 8.1 breaking changes
# Update dependencies and test thoroughly
```

## Symfony Integration Compatibility

### Symfony Lock Component Versions

| Symfony Version | Min PHP | TtlSemLock Support | Status | LTS |
|-----------------|---------|-------------------|--------|-----|
| **6.4 LTS** | 8.1 | ✅ Full | Production | Until 2027 |
| **7.0** | 8.2 | ✅ Full | Production | No |
| **7.1** | 8.2 | ✅ Full | Production | No |
| **7.2** | 8.2 | ✅ Full | Production | No |
| **7.3** | 8.2 | ✅ Full | Current | No |

### Interface Compatibility

| Interface | Symfony 6.4 | Symfony 7.0+ | Implementation |
|-----------|--------------|--------------|----------------|
| `PersistingStoreInterface` | ✅ Full | ✅ Full | Complete |
| `BlockingStoreInterface` | ❌ Not implemented | ❌ Not implemented | Future |
| `SharedLockStoreInterface` | ❌ Not implemented | ❌ Not implemented | Future |

### Symfony Framework Versions

| Framework | Version | PHP | TtlSemLock | Notes |
|-----------|---------|-----|------------|-------|
| **Symfony** | 6.4 LTS | 8.1+ | ✅ Tested | Recommended for production |
| **Symfony** | 7.0 | 8.2+ | ✅ Tested | Current stable |
| **Symfony** | 7.1+ | 8.2+ | ✅ Compatible | Latest features |

## Installation Matrix

### Recommended Combinations

#### Production (LTS)
```bash
# PHP 8.1+ with Symfony 6.4 LTS
composer require ttlsemlock/client symfony/lock:^6.4
```

#### Latest Stable
```bash
# PHP 8.2+ with Symfony 7.0+
composer require ttlsemlock/client symfony/lock:^7.0
```

#### Development
```bash
# PHP 8.3+ with latest Symfony
composer require ttlsemlock/client symfony/lock:*
```

### Version Constraints

```json
{
    "require": {
        "php": ">=8.1",
        "ttlsemlock/client": "^0.2.9"
    },
    "suggest": {
        "symfony/lock": "^6.4|^7.0"
    }
}
```

## Testing Compatibility

### Automated Tests

Run compatibility tests for your environment:

```bash
# Basic compatibility
php vendor/ttlsemlock/client/examples/compatibility_test.php

# Symfony integration tests
php vendor/ttlsemlock/client/examples/symfony_integration.php

# Performance benchmarks
php vendor/ttlsemlock/client/examples/performance_test.php
```

### Manual Verification

```php
<?php
// Check PHP version
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    throw new \Exception('PHP 8.1+ required');
}

// Check Symfony Lock version
$version = \Composer\InstalledVersions::getVersion('symfony/lock');
echo "Symfony Lock: {$version}\n";

// Test TtlSemLock
use TtlSemLock\TtlSemLock;
$client = new TtlSemLock('localhost', 8765);
echo "TtlSemLock client created successfully\n";
```

## Performance by Version

### Benchmark Results

| Environment | Latency | Throughput | Memory |
|-------------|---------|------------|--------|
| **PHP 8.1 + Symfony 6.4** | ~0.25ms | 4K ops/sec | 2MB |
| **PHP 8.2 + Symfony 7.0** | ~0.20ms | 5K ops/sec | 2MB |
| **PHP 8.3 + Symfony 7.3** | ~0.18ms | 5.5K ops/sec | 1.8MB |

### Optimization Recommendations

#### For PHP 8.1
- Use OPcache for better performance
- Consider upgrading to 8.2+ for optimal results

#### For PHP 8.2+
- Enable JIT compiler for maximum performance
- Use preloading for frequently used classes

## Troubleshooting

### Common Issues

#### PHP Version Mismatch
```bash
# Error: syntax error, unexpected '|'
# Solution: Upgrade to PHP 8.1+
```

#### Symfony Version Conflicts
```bash
# Error: Interface not found
# Solution: Check Symfony Lock version
composer show symfony/lock
composer require symfony/lock:^6.4
```

#### Extension Missing
```bash
# Error: Call to undefined function json_encode
# Solution: Install JSON extension
sudo apt install php8.1-json
```

### Performance Issues

#### Slow Response Times
1. Check network latency to TtlSemLock server
2. Verify PHP version (8.2+ recommended)
3. Enable OPcache and JIT
4. Use connection pooling

#### Memory Usage
1. Monitor for memory leaks in long-running processes
2. Use appropriate TTL values
3. Release locks promptly

## Future Compatibility

### Roadmap

| Feature | Target Version | PHP Requirement | Status |
|---------|----------------|-----------------|--------|
| **Laravel Support** | 0.3.x | 8.1+ | Planned |
| **Blocking Locks** | 0.4.x | 8.1+ | Planned |
| **Shared Locks** | 0.5.x | 8.2+ | Research |
| **Symfony Lock Component** | 0.5.0+ | 8.1+ | ✅ Supported |

### Deprecation Policy

- **PHP 8.0**: Not supported (never was)
- **PHP 8.1**: Supported until 2026
- **Symfony 6.x**: Supported until LTS end
- **Symfony 7.x**: Supported throughout lifecycle

## Support Matrix

| Component | Support Level | Until |
|-----------|---------------|--------|
| **PHP 8.1** | Full | November 2026 |
| **PHP 8.2** | Full | December 2027 |
| **PHP 8.3** | Full | November 2028 |
| **Symfony 6.4 LTS** | Full | November 2027 |
| **Symfony 7.x** | Full | Until next LTS |

For questions about compatibility, please check our [GitHub Issues](https://github.com/your-org/ttlsemlock) or contact support. 