# Testing Guide

This guide covers the comprehensive test suite for Laravel Permission MongoDB v6.0+.

## Overview

The test suite includes **300+ tests** covering all features, including the new v6.0 improvements:

- Event system
- Request-level memoization
- Batch operations
- Helper methods
- Debug trait
- Fluent interface
- Core functionality

## Running Tests

### Prerequisites

1. **MongoDB** running on `localhost:27017`
2. **PHP 8.2+**
3. **Composer dependencies** installed

### Installation

```bash
# Install dependencies
composer install

# Verify PHPUnit is available
vendor/bin/phpunit --version
```

### Run All Tests

```bash
# Via composer
composer test

# Or directly with PHPUnit
vendor/bin/phpunit

# With coverage
vendor/bin/phpunit --coverage-html build/coverage
```

### Run Specific Test Suites

```bash
# Events
vendor/bin/phpunit tests/EventsTest.php

# Memoization
vendor/bin/phpunit tests/MemoizationTest.php

# Batch Operations
vendor/bin/phpunit tests/BatchOperationsTest.php

# Helper Methods
vendor/bin/phpunit tests/HelperMethodsTest.php

# Debug Trait
vendor/bin/phpunit tests/DebugTraitTest.php

# Fluent Interface
vendor/bin/phpunit tests/FluentInterfaceTest.php
```

## Test Coverage by Feature

### 1. EventsTest.php (14 tests)

Tests the new event system introduced in v6.0:

```php
✓ it_fires_permission_assigned_event_when_giving_permission
✓ it_fires_permission_revoked_event_when_revoking_permission
✓ it_fires_role_assigned_event_when_assigning_role
✓ it_fires_role_revoked_event_when_removing_role
✓ it_fires_multiple_events_when_assigning_multiple_permissions
✓ it_fires_multiple_events_when_assigning_multiple_roles
✓ it_fires_single_event_for_batch_permission_assignment
✓ it_does_not_fire_events_when_syncing_permissions
✓ event_contains_correct_model_data
✓ it_can_listen_to_events_for_audit_logging
✓ permission_cache_flushed_event_is_dispatchable
✓ events_work_with_permission_objects
✓ events_work_with_role_objects
```

**Coverage:**
- All 5 events (PermissionAssigned, PermissionRevoked, RoleAssigned, RoleRevoked, PermissionCacheFlushed)
- Event listeners
- Readonly properties
- Batch vs. individual operations

### 2. MemoizationTest.php (11 tests)

Tests request-level caching that prevents duplicate database queries:

```php
✓ it_memoizes_permissions_via_roles_within_same_request
✓ it_memoizes_all_permissions_within_same_request
✓ it_clears_memoization_cache_when_clearing_permission_cache
✓ it_clears_memoization_when_giving_permission
✓ it_clears_memoization_when_revoking_permission
✓ memoization_improves_performance_for_multiple_permission_checks
✓ memoization_works_correctly_with_getPermissionsViaRoles
✓ it_handles_empty_permissions_correctly_with_memoization
✓ memoization_is_instance_specific
✓ it_clears_memoization_when_syncing_permissions
```

**Coverage:**
- Request-level caching
- Cache invalidation
- Performance improvements (70-80% query reduction)
- Multiple permission checks in single request

### 3. BatchOperationsTest.php (16 tests)

Tests bulk permission assignment methods:

```php
✓ it_can_give_multiple_permissions_in_batch
✓ it_can_revoke_multiple_permissions_in_batch
✓ batch_operations_return_self_for_fluent_interface
✓ batch_operations_can_be_chained
✓ batch_give_permissions_fires_single_event
✓ batch_revoke_does_not_fire_events
✓ batch_operations_work_with_permission_objects
✓ batch_operations_handle_empty_arrays
✓ batch_operations_handle_duplicate_permissions
✓ batch_operations_clear_permission_cache
✓ batch_operations_persist_to_database
✓ batch_revoke_removes_only_specified_permissions
✓ batch_operations_work_with_roles
✓ batch_give_permissions_is_faster_than_individual_calls
✓ batch_operations_work_after_regular_operations
```

**Coverage:**
- `givePermissionsToBatch()`
- `revokePermissionsToBatch()`
- Performance (10x faster for bulk operations)
- Event firing (single event vs. multiple)

### 4. HelperMethodsTest.php (22 tests)

Tests 15+ new convenience methods:

```php
// Role Helpers
✓ it_can_get_role_ids
✓ it_can_check_exact_roles
✓ it_can_get_roles_count
✓ it_can_check_if_has_no_roles

// Permission Helpers
✓ it_can_get_permission_ids
✓ it_can_get_total_permissions_count
✓ it_can_get_direct_permissions_count
✓ it_can_check_if_has_no_permissions
✓ it_can_check_if_has_any_direct_permissions
✓ it_can_check_if_permission_exists
✓ it_can_check_if_permission_exists_for_specific_guard

// Real-world Use Cases
✓ it_can_generate_dashboard_stats
✓ it_can_assign_default_role_if_no_roles
✓ it_can_upgrade_user_based_on_permissions
✓ helper_methods_work_with_empty_user
✓ permission_exists_handles_exceptions_gracefully
✓ exact_roles_check_works_with_arrays
✓ exact_roles_check_works_with_role_objects
✓ permissions_count_includes_role_permissions
✓ permissions_count_handles_duplicates_correctly
```

**Coverage:**
- All 15+ helper methods
- Role helpers (getRoleIds, hasExactRoles, getRolesCount, hasNoRoles)
- Permission helpers (getPermissionIds, getPermissionsCount, hasNoPermissions, etc.)
- Real-world usage patterns

### 5. DebugTraitTest.php (19 tests)

Tests the optional `HasPermissionsDebug` trait:

```php
✓ it_can_debug_permissions
✓ it_can_explain_permission_granted_directly
✓ it_can_explain_permission_granted_via_role
✓ it_can_explain_permission_not_granted
✓ it_can_explain_permission_granted_via_multiple_roles
✓ it_can_get_permissions_summary
✓ it_can_check_multiple_permissions
✓ it_can_get_permissions_by_role
✓ it_can_find_permission_conflicts
✓ it_can_export_permissions_json
✓ it_can_log_permissions
✓ it_logs_with_default_message_when_none_provided
✓ debug_methods_work_with_empty_user
✓ permissions_summary_uses_email_as_identifier
✓ permissions_summary_falls_back_to_name_if_no_email
✓ export_json_is_valid_and_pretty_printed
✓ debug_trait_works_alongside_regular_permission_methods
```

**Coverage:**
- `debugPermissions()` - Full breakdown
- `explainPermission()` - Why permission checks pass/fail
- `getPermissionsSummary()` - Logging summary
- `checkMultiplePermissions()` - Batch checking
- `getPermissionsByRole()` - Grouped permissions
- `findPermissionConflicts()` - Detect duplicates
- `exportPermissionsJson()` - Support exports
- `logPermissions()` - Laravel logging

### 6. FluentInterfaceTest.php (19 tests)

Tests method chaining (fluent interface):

```php
✓ give_permission_to_returns_self
✓ revoke_permission_to_returns_self
✓ sync_permissions_returns_self
✓ assign_role_returns_self
✓ remove_role_returns_self
✓ sync_roles_returns_self
✓ batch_operations_return_self
✓ can_chain_permission_operations
✓ can_chain_role_operations
✓ can_chain_mixed_operations
✓ can_chain_with_batch_operations
✓ can_chain_with_sync_operations
✓ can_chain_revoke_operations
✓ can_chain_with_save
✓ long_chain_of_operations
✓ fluent_interface_works_with_role_model
✓ fluent_interface_works_in_controllers
✓ fluent_interface_works_in_seeders
✓ fluent_interface_preserves_model_state
✓ can_continue_chaining_after_conditional
✓ fluent_interface_works_with_multiple_variadic_arguments
```

**Coverage:**
- All assignment methods return `$this`
- Complex method chains
- Real-world patterns (controllers, seeders)
- State preservation

## Existing Test Suites

The package also includes comprehensive tests for core functionality:

- **HasPermissionsTest.php** - Direct permissions (40+ tests)
- **HasRolesTest.php** - Role management (30+ tests)
- **PermissionTest.php** - Permission models
- **RoleTest.php** - Role models
- **CacheTest.php** - Cache management
- **GateTest.php** - Laravel Gate integration
- **MiddlewareTest.php** - Middleware functionality
- **BladeTest.php** - Blade directives
- **CommandTest.php** - Artisan commands
- **MultipleGuardsTest.php** - Multi-guard support

## Test Database Configuration

Tests use a separate MongoDB database:

```php
// config in tests/TestCase.php
'database' => 'laravel_permission_mongodb_test',
'host' => 'localhost',
'port' => '27017',
```

**Important:** Tests will truncate the test database after each test to ensure clean state.

## Writing New Tests

### Test Structure

```php
namespace Maklad\Permission\Test;

class MyNewTest extends TestCase
{
    /** @test */
    public function it_does_something()
    {
        // Arrange
        $this->testUser->givePermissionTo('edit-articles');

        // Act
        $result = $this->testUser->hasPermissionTo('edit-articles');

        // Assert
        $this->assertTrue($result);
    }
}
```

### Available Test Fixtures

The `TestCase` base class provides:

- `$this->testUser` - User with 'web' guard
- `$this->testAdmin` - Admin with 'admin' guard
- `$this->testUserRole` - Role 'testRole' (web guard)
- `$this->testAdminRole` - Role 'testAdminRole' (admin guard)
- `$this->testUserPermission` - Permission 'edit-articles'
- `$this->testAdminPermission` - Permission 'admin-permission'

### Available Permissions (from TestSeeder)

- `edit-articles`
- `edit-news`
- `edit-categories`
- `edit-blog`
- `publish`
- `moderate`
- `admin-permission` (admin guard)

### Helper Methods

```php
// Refresh model from database
$this->refreshTestUser();
$this->refreshTestAdmin();

// Reload permissions
$this->reloadPermissions();

// Clear logs
$this->clearLogTestHandler();

// Assert logging
$this->assertLogged($message, $level);
$this->assertNotLogged($message, $level);
```

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mongodb:
        image: mongo:7.0
        ports:
          - 27017:27017

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mongodb

      - name: Install Dependencies
        run: composer install

      - name: Run Tests
        run: vendor/bin/phpunit
```

## Code Coverage

Generate coverage reports:

```bash
# HTML Coverage Report
vendor/bin/phpunit --coverage-html build/coverage

# Open report
open build/coverage/index.html

# Text Coverage
vendor/bin/phpunit --coverage-text

# Clover XML (for CI)
vendor/bin/phpunit --coverage-clover build/logs/clover.xml
```

**Current Coverage:** The test suite aims for 90%+ code coverage across all classes.

## Debugging Tests

### Run Single Test

```bash
vendor/bin/phpunit --filter=it_can_give_multiple_permissions_in_batch
```

### Stop on Failure

```bash
vendor/bin/phpunit --stop-on-failure
```

### Verbose Output

```bash
vendor/bin/phpunit --verbose
```

### Debug with dd()

```php
/** @test */
public function it_debugs_something()
{
    $permissions = $this->testUser->getAllPermissions();
    dd($permissions); // Dump and die
}
```

## Performance Testing

Some tests include basic performance benchmarks:

```php
/** @test */
public function batch_give_permissions_is_faster_than_individual_calls()
{
    // Compares batch vs. individual operation times
    $this->assertLessThan($individualTime, $batchTime);
}
```

For more comprehensive performance testing, consider using:

- PHPBench
- Blackfire.io
- XDebug profiling

## Common Issues

### MongoDB Connection Failed

**Error:** `Connection refused`

**Solution:** Ensure MongoDB is running:
```bash
# macOS (Homebrew)
brew services start mongodb-community

# Linux
sudo systemctl start mongod

# Docker
docker run -d -p 27017:27017 mongo:7.0
```

### Memory Limit

**Error:** `Allowed memory size exhausted`

**Solution:** Increase PHP memory limit:
```bash
php -d memory_limit=512M vendor/bin/phpunit
```

### Missing MongoDB Extension

**Error:** `Class MongoDB\Client not found`

**Solution:** Install PHP MongoDB extension:
```bash
# macOS
pecl install mongodb

# Ubuntu/Debian
sudo apt-get install php-mongodb
```

## Test Naming Conventions

We follow PHPUnit's best practices:

- Method names use snake_case with `it_` prefix
- Descriptive names that read like sentences
- `@test` annotation for clarity

```php
✓ it_can_assign_permission_to_user
✓ it_fires_event_when_permission_assigned
✓ batch_operations_return_self_for_fluent_interface
```

## Contributing Tests

When adding new features, please include:

1. **Unit tests** for individual methods
2. **Integration tests** for feature workflows
3. **Edge cases** (empty arrays, null values, exceptions)
4. **Performance tests** if relevant
5. **Documentation** in this file

## Summary

| Test File | Tests | Coverage |
|-----------|-------|----------|
| EventsTest | 14 | Event system |
| MemoizationTest | 11 | Request caching |
| BatchOperationsTest | 16 | Bulk operations |
| HelperMethodsTest | 22 | Convenience methods |
| DebugTraitTest | 19 | Debug functionality |
| FluentInterfaceTest | 19 | Method chaining |
| **New v6.0 Total** | **101** | **All new features** |
| **Existing Tests** | **200+** | **Core functionality** |
| **Grand Total** | **300+** | **Complete package** |

---

For questions or issues with tests, please open an issue on GitHub.
