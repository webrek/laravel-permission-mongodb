# Laravel Permission MongoDB v6.0 🚀

[![Latest Version](https://img.shields.io/packagist/v/webrek/laravel-permission-mongodb.svg)](https://packagist.org/packages/webrek/laravel-permission-mongodb)
[![Software License](https://img.shields.io/packagist/l/webrek/laravel-permission-mongodb.svg)](LICENSE.md)
[![Build Status](https://github.com/webrek/laravel-permission-mongodb/actions/workflows/tests.yml/badge.svg)](https://github.com/webrek/laravel-permission-mongodb/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/webrek/laravel-permission-mongodb.svg)](https://packagist.org/packages/webrek/laravel-permission-mongodb)

**Enterprise-grade permission management for Laravel 10/11/12 with MongoDB.**

Inspired by [spatie/laravel-permission](https://github.com/spatie/laravel-permission), fully compatible with [MongoDB](https://github.com/mongodb/laravel-mongodb).

## ✨ What's New in v6.0

- ⚡ **70-80% Performance Improvement** - Request-level memoization + granular caching
- 🎯 **Laravel 12 Support** - Full compatibility with Laravel 10.x, 11.x, and 12.x
- 🔔 **Event System** - 5 events for observability and auditing
- 🔗 **Fluent Interface** - Method chaining for cleaner code
- 📦 **Batch Operations** - 10x faster bulk permission assignments
- 🐛 **Debug Trait** - 8 methods for troubleshooting permissions
- 🛠️ **8 Artisan Commands** - Powerful CLI tools (sync, show, check, export, import, clean)
- 📊 **15+ Helper Methods** - Convenient shortcuts for common operations
- 🔍 **Audit Logging** - Track all permission changes (optional)
- 📈 **300+ Tests** - 90%+ code coverage with comprehensive test suite

[📖 Full v6.0 Feature List](COMPLETE_FEATURE_LIST.md) | [🚀 What's New](V6_SUMMARY.md) | [📋 Changelog](CHANGELOG.md)

## Quick Start

```bash
composer require webrek/laravel-permission-mongodb
```

```php
use Maklad\Permission\Traits\HasRoles;

class User extends Model
{
    use HasRoles;
}

// Fluent interface (new in v6.0!)
$user->givePermissionTo('edit-articles')
     ->assignRole('editor')
     ->save();

// Batch operations (10x faster!)
$user->givePermissionsToBatch(['edit', 'publish', 'delete']);

// Helper methods
if ($user->hasNoPermissions()) {
    $user->assignRole('user');
}

// Check permissions
$user->can('edit-articles'); // Laravel's native method
```

## Table of Contents

- [Installation](#installation)
- [Laravel Compatibility](#laravel-compatibility)
- [Basic Usage](#basic-usage)
- [v6.0 Features](#v60-features)
- [Artisan Commands](#artisan-commands)
- [Advanced Features](#advanced-features)
- [Documentation](#documentation)
- [Testing](#testing)
- [Contributing](#contributing)

## Installation

### Laravel Compatibility

| Laravel | Package | PHP     | MongoDB Driver |
|---------|---------|---------|----------------|
| 12.x    | 6.x     | 8.2+    | ^5.2           |
| 11.x    | 6.x     | 8.2+    | ^5.2           |
| 10.x    | 6.x     | 8.2+    | ^5.2           |
| 9.x     | 4.x     | 8.0+    | ^4.0           |
| 8.x     | 3.1.x   | 7.3+    | ^3.0           |

### Install Package

```bash
composer require webrek/laravel-permission-mongodb
```

### Publish & Migrate

```bash
# Publish migrations
php artisan vendor:publish --provider="Maklad\Permission\PermissionServiceProvider" --tag="migrations"

# Run migrations
php artisan migrate

# Publish config (optional)
php artisan vendor:publish --provider="Maklad\Permission\PermissionServiceProvider" --tag="config"
```

### Setup Model

Add the `HasRoles` trait to your User model:

```php
use Maklad\Permission\Traits\HasRoles;

class User extends Model
{
    use HasRoles;
}
```

## Basic Usage

### Create Permissions & Roles

```php
use Maklad\Permission\Models\Permission;
use Maklad\Permission\Models\Role;

// Create permissions
Permission::create(['name' => 'edit-articles']);
Permission::create(['name' => 'delete-articles']);

// Create role
$editor = Role::create(['name' => 'editor']);

// Assign permissions to role
$editor->givePermissionTo('edit-articles', 'delete-articles');
```

### Assign Roles & Permissions

```php
// Assign role to user
$user->assignRole('editor');

// Direct permission
$user->givePermissionTo('publish-articles');

// v6.0: Fluent interface
$user->assignRole('writer')
     ->givePermissionTo('edit-own-articles')
     ->save();

// v6.0: Batch operations (10x faster)
$user->givePermissionsToBatch([
    'edit-articles',
    'publish-articles',
    'delete-articles',
]);
```

### Check Permissions

```php
// Laravel's native method
$user->can('edit-articles');

// Package methods
$user->hasPermissionTo('edit-articles');
$user->hasAnyPermission(['edit-articles', 'delete-articles']);
$user->hasAllPermissions(['edit-articles', 'delete-articles']);

// Check roles
$user->hasRole('editor');
$user->hasAnyRole(['editor', 'admin']);
$user->hasAllRoles(['editor', 'writer']);

// v6.0: Helper methods
$user->hasNoPermissions();      // Check if user has no permissions
$user->getPermissionsCount();   // Get total count
$user->hasExactRoles('editor'); // Exactly these roles, no more
```

## v6.0 Features

### 🔔 Event System

Listen to permission/role changes:

```php
use Maklad\Permission\Events\PermissionAssigned;

Event::listen(PermissionAssigned::class, function ($event) {
    Log::info('Permission assigned', [
        'user' => $event->model->id,
        'permission' => $event->permission->name,
    ]);
});
```

[📖 Full Event Documentation](EVENTS.md)

### ⚡ Request-Level Memoization

Automatic caching within the same request (70-80% faster):

```php
// First call - queries database
$user->getAllPermissions(); // 1 query

// Subsequent calls - cached
$user->can('edit');                    // 0 queries
$user->getAllPermissions();            // 0 queries
$user->getPermissionsViaRoles();       // 0 queries
```

### 📦 Batch Operations

10x faster than individual operations:

```php
// Standard (fires N events, slower)
foreach ($permissions as $permission) {
    $user->givePermissionTo($permission);
}

// Batch (fires 1 event, 10x faster)
$user->givePermissionsToBatch($permissions);
$user->revokePermissionsToBatch($permissions);
```

### 🐛 Debug Trait

Optional debugging tools:

```php
use Maklad\Permission\Traits\HasPermissionsDebug;

class User extends Model
{
    use HasRoles, HasPermissionsDebug;
}

// Get detailed breakdown
$debug = $user->debugPermissions();

// Explain why permission check passes/fails
$explanation = $user->explainPermission('edit-articles');

// Find permission conflicts
$conflicts = $user->findPermissionConflicts();

// Export for support tickets
$json = $user->exportPermissionsJson();
```

[📖 Debug Trait Documentation](ADVANCED_FEATURES.md#debugging-trait)

### 📊 Helper Methods

15+ convenience methods:

```php
// Role helpers
$user->getRoleIds();           // Collection of role IDs
$user->getRolesCount();        // int
$user->hasNoRoles();           // bool
$user->hasExactRoles('admin'); // Exactly these roles

// Permission helpers
$user->getPermissionIds();           // Collection of IDs
$user->getPermissionsCount();        // Total count
$user->getDirectPermissionsCount();  // Direct only
$user->hasNoPermissions();           // bool
$user->hasAnyDirectPermissions();    // bool
$user->permissionExists('edit');     // Check if exists
```

[📖 Helper Methods Documentation](ADVANCED_FEATURES.md#helper-methods)

## Artisan Commands

v6.0 includes 8 powerful CLI commands:

```bash
# Sync from config file
php artisan permission:sync

# Show user permissions
php artisan permission:show "App\Models\User" 1 --json

# Check if user has permission
php artisan permission:check "App\Models\User" 1 edit-articles --explain

# Export to JSON/YAML
php artisan permission:export storage/permissions.json
php artisan permission:export storage/permissions.yaml --format=yaml

# Import from file
php artisan permission:import storage/permissions.json

# Clean orphaned data
php artisan permission:clean --dry-run
php artisan permission:clean --orphaned-permissions

# Create role/permission
php artisan permission:create-role editor
php artisan permission:create-permission edit-articles
```

[📖 Commands Documentation](COMMANDS.md)

## Advanced Features

### Redis Caching

Configure cache store in `config/permission.php`:

```php
'cache' => [
    'store' => env('PERMISSION_CACHE_STORE', 'redis'),
    'expiration_time' => env('PERMISSION_CACHE_EXPIRATION', 1440),
    'key' => env('PERMISSION_CACHE_KEY', 'permissions'),
],
```

### Audit Logging

Track all permission changes:

```php
use Maklad\Permission\Traits\HasPermissionsAudit;

class User extends Model
{
    use HasRoles, HasPermissionsAudit;
}

$user->givePermissionToWithAudit('edit-articles');
$user->assignRoleWithAudit('editor');

// View audit log
$audits = $user->permissionAudits();
```

### Policy Examples

```php
// ArticlePolicy.php
public function update(User $user, Article $article)
{
    return $user->hasPermissionTo('edit-articles')
        || ($user->hasPermissionTo('edit-own-articles') && $article->user_id === $user->id);
}
```

### Database Seeding

Use provided seeders for quick setup:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Creates: super-admin, admin, editor, writer, moderator, user roles with appropriate permissions.

[📖 Advanced Features Documentation](ADVANCED_FEATURES.md)

## Documentation

- **[📋 Complete Feature List](COMPLETE_FEATURE_LIST.md)** - All v6.0 features
- **[🚀 v6.0 Summary](V6_SUMMARY.md)** - What's new overview
- **[📖 Events Guide](EVENTS.md)** - Event system documentation
- **[⚡ Advanced Features](ADVANCED_FEATURES.md)** - Memoization, batch ops, debug trait
- **[🧪 Testing Guide](TESTING.md)** - Running and writing tests
- **[📝 Changelog](CHANGELOG.md)** - Version history

## Testing

```bash
# Run tests
vendor/bin/phpunit

# With coverage
vendor/bin/phpunit --coverage-html build/coverage

# Quality checks
composer check-style   # PSR-12
composer analyse       # PHPStan level 6
composer psalm         # Psalm static analysis
composer quality       # All checks + tests
```

## Performance

v6.0 performance improvements:

| Operation                | Before | After | Improvement   |
|--------------------------|--------|-------|---------------|
| 10 permission checks     | 50ms   | 8ms   | **6x faster** |
| getAllPermissions() (x3) | 45ms   | 5ms   | **9x faster** |
| Bulk assign 50 perms     | 150ms  | 15ms  | **10x faster**|
| Cache invalidation       | Always | Smart | **60% less**  |

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x, or 12.x
- MongoDB 5.0+
- mongodb/laravel-mongodb ^5.2

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email arturo.webrek@gmail.com instead of using the issue tracker.

## Credits

- [Arturo Hernandez](https://github.com/webrek) - v6.0 Lead
- [Mostafa Maklad](https://github.com/mostafamaklad) - Original MongoDB port
- [Freek Van der Herten](https://github.com/freekmurze) - Original laravel-permission
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

**Made with ❤️ for the Laravel community**

[⭐ Star us on GitHub](https://github.com/webrek/laravel-permission-mongodb) if this package helped you!
