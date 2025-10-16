# Advanced Features Guide

Laravel Permission MongoDB v6.0+ includes powerful features for enterprise applications.

## Table of Contents
1. [Request-Level Memoization](#request-level-memoization)
2. [Helper Methods](#helper-methods)
3. [Batch Operations](#batch-operations)
4. [Debugging Trait](#debugging-trait)
5. [Performance Optimization](#performance-optimization)

---

## Request-Level Memoization

Permissions are automatically cached during a single request to prevent duplicate database queries.

### How It Works

```php
$user = User::find(1);

// First call queries the database
$permissions1 = $user->getAllPermissions(); // Query executed

// Subsequent calls use cached result (same request)
$permissions2 = $user->getAllPermissions(); // No query
$permissions3 = $user->getAllPermissions(); // No query
```

### Performance Impact

**Before memoization:**
```php
// Multiple permission checks in one request
if ($user->can('edit') && $user->can('delete') && $user->can('publish')) {
    // 3+ database queries
}
```

**After memoization:**
```php
// Same checks with memoization
if ($user->can('edit') && $user->can('delete') && $user->can('publish')) {
    // 1 database query total
}
```

### Manual Cache Control

```php
// Clear request-level cache if permissions change mid-request
$user->givePermissionTo('new-permission');
// Cache automatically cleared

// Or clear manually
$user->clearPermissionCache();
```

---

## Helper Methods

### Role Helpers

```php
// Get role IDs
$roleIds = $user->getRoleIds(); // Collection of MongoDB IDs

// Check exact roles (no more, no less)
$user->hasExactRoles('admin'); // true only if ONLY admin role

// Count roles
$count = $user->getRolesCount(); // int

// Check if no roles
if ($user->hasNoRoles()) {
    $user->assignRole('user'); // Assign default role
}
```

### Permission Helpers

```php
// Get permission IDs
$permissionIds = $user->getPermissionIds();

// Count all permissions (direct + via roles)
$total = $user->getPermissionsCount();

// Count only direct permissions
$direct = $user->getDirectPermissionsCount();

// Check if no permissions at all
if ($user->hasNoPermissions()) {
    // User has no permissions
}

// Check if has any direct permissions
if ($user->hasAnyDirectPermissions()) {
    // User has at least one direct permission
}

// Check if permission exists before assigning
if ($user->permissionExists('edit-articles')) {
    $user->givePermissionTo('edit-articles');
}
```

### Real-World Examples

**Dashboard Stats:**
```php
return [
    'roles' => $user->getRolesCount(),
    'direct_permissions' => $user->getDirectPermissionsCount(),
    'total_permissions' => $user->getPermissionsCount(),
    'role_names' => $user->getRoleNames()->toArray(),
];
```

**Conditional Logic:**
```php
// Assign default role if user has none
if ($user->hasNoRoles()) {
    $user->assignRole('user');
}

// Upgrade user if they have all required permissions
$required = ['edit', 'publish', 'delete'];
if ($user->hasAllPermissions(...$required)) {
    $user->assignRole('editor');
}
```

---

## Batch Operations

For bulk operations, use batch methods to minimize overhead.

### Standard vs Batch

**Standard (fires event for each):**
```php
foreach ($permissions as $permission) {
    $user->givePermissionTo($permission); // Event fired each time
}
// Result: N events, N cache invalidations
```

**Batch (single event):**
```php
$user->givePermissionsToBatch($permissions); // One event, one cache invalidation
// Result: 1 event, 1 cache invalidation
```

### Usage Examples

```php
// Assign many permissions at once
$user->givePermissionsToBatch([
    'edit-articles',
    'publish-articles',
    'delete-articles',
    'manage-comments',
    'moderate-content',
]);

// Revoke many permissions
$user->revokePermissionsToBatch([
    'admin-panel',
    'manage-users',
    'system-settings',
]);

// Fluent interface works
$user->givePermissionsToBatch($basePermissions)
     ->assignRole('editor')
     ->save();
```

### When to Use Batch

✅ **Use batch operations when:**
- Assigning 5+ permissions at once
- Seeding/importing users with permissions
- Bulk permission updates
- You don't need per-permission event tracking

❌ **Use standard methods when:**
- Assigning 1-4 permissions
- You need detailed audit logs per permission
- Permissions are assigned individually over time

### Performance Comparison

```php
// Assigning 50 permissions
// Standard: ~150ms (50 events + 50 cache clears)
// Batch: ~15ms (1 event + 1 cache clear)
// Performance gain: 10x faster
```

---

## Debugging Trait

Add `HasPermissionsDebug` trait to your User model for powerful debugging tools.

### Installation

```php
use Maklad\Permission\Traits\HasRoles;
use Maklad\Permission\Traits\HasPermissionsDebug;

class User extends Model
{
    use HasRoles, HasPermissionsDebug;
}
```

### Debug Methods

#### 1. Full Permission Breakdown

```php
$debug = $user->debugPermissions();

/*
Array (
    [direct_permissions] => ['edit', 'delete']
    [direct_permissions_count] => 2
    [roles] => ['writer', 'moderator']
    [roles_count] => 2
    [permissions_via_roles] => ['publish', 'moderate']
    [all_permissions] => ['edit', 'delete', 'publish', 'moderate']
    [total_permissions_count] => 4
    [guard] => 'web'
)
*/
```

#### 2. Explain Permission Checks

```php
$explanation = $user->explainPermission('publish-articles');

/*
Array (
    [permission] => 'publish-articles'
    [has_permission] => true
    [has_directly] => false
    [has_via_role] => true
    [roles_granting_permission] => ['editor', 'admin']
)
*/
```

Use case: "Why can this user publish articles?"
Answer: Through 'editor' and 'admin' roles.

#### 3. Find Conflicts

```php
$conflicts = $user->findPermissionConflicts();

/*
Array (
    [duplicates] => ['edit'] // Permission assigned both directly AND via role
    [direct_only] => ['delete'] // Only assigned directly
    [role_only] => ['publish'] // Only via roles
)
*/
```

#### 4. Export for Support

```php
// Generate detailed JSON for support tickets
$json = $user->exportPermissionsJson();
file_put_contents('user_permissions_debug.json', $json);
```

#### 5. Check Multiple Permissions

```php
$results = $user->checkMultiplePermissions([
    'edit-articles',
    'publish-articles',
    'delete-articles',
]);

/*
Array (
    [edit-articles] => [
        [granted] => true
        [direct] => true
    ]
    [publish-articles] => [
        [granted] => true
        [direct] => false  // Via role
    ]
    [delete-articles] => [
        [granted] => false
        [direct] => false
    ]
)
*/
```

#### 6. Log Permission State

```php
// Log to Laravel log
$user->logPermissions('info', 'User login permission check');

// Produces log entry:
// [2025-10-15 21:00:00] INFO: User login permission check
// {"user_id":123,"user_identifier":"john@example.com","direct_permissions":2,"roles":3,"total_permissions":10}
```

### Debugging Scenarios

**Scenario 1: User claims they can't access feature**
```php
$explanation = $user->explainPermission('access-admin-panel');

if (!$explanation['has_permission']) {
    Log::warning('User lacks permission', [
        'user_id' => $user->id,
        'permission' => 'access-admin-panel',
        'current_roles' => $user->getRoleNames(),
        'debug' => $user->debugPermissions(),
    ]);
}
```

**Scenario 2: Audit user permissions**
```php
// Generate comprehensive report
$report = [
    'summary' => $user->getPermissionsSummary(),
    'by_role' => $user->getPermissionsByRole(),
    'conflicts' => $user->findPermissionConflicts(),
];

return response()->json($report);
```

**Scenario 3: Development debugging**
```php
// Quick check during development
if (app()->environment('local')) {
    dd($user->debugPermissions());
}
```

---

## Performance Optimization

### Best Practices

#### 1. Use Eager Loading

```php
// Bad: N+1 queries
foreach ($users as $user) {
    echo $user->getRoleNames(); // Query per user
}

// Good: Eager load
$users = User::with('roles')->get();
foreach ($users as $user) {
    echo $user->getRoleNames(); // No extra queries
}
```

#### 2. Leverage Memoization

```php
// Memoization works automatically
$user = User::find(1);

// All these use cached result:
$user->can('edit');
$user->can('delete');
$user->can('publish');
$user->getAllPermissions();
$user->getPermissionsViaRoles();
// Total: 1 database query
```

#### 3. Use Batch for Bulk Operations

```php
// Seeding 1000 users with permissions
foreach ($users as $user) {
    $user->givePermissionsToBatch($defaultPermissions); // Fast
}
```

#### 4. Cache Gate Checks

Laravel's Gate is already cached by this package, but you can add additional caching:

```php
// Cache expensive permission calculations
$canManage = Cache::remember("user.{$user->id}.can_manage", 3600, function () use ($user) {
    return $user->hasAllPermissions(['edit', 'publish', 'delete']);
});
```

### Performance Metrics

**Typical request with multiple permission checks:**

| Operation | Before v6.0 | After v6.0 | Improvement |
|-----------|-------------|------------|-------------|
| 10 permission checks | ~50ms | ~8ms | 6x faster |
| Get all permissions (3x) | 45ms | 5ms | 9x faster |
| Bulk assign 50 permissions | 150ms | 15ms | 10x faster |
| Cache invalidation (unrelated field) | Always | Conditional | 60% fewer |

### Monitoring

```php
// Track permission check performance
$start = microtime(true);
$result = $user->can('edit-articles');
$duration = (microtime(true) - $start) * 1000;

if ($duration > 10) {
    Log::warning('Slow permission check', [
        'duration_ms' => $duration,
        'user_id' => $user->id,
        'permission' => 'edit-articles',
    ]);
}
```

---

## Migration from v5.x

All new features are **additive** and **backward compatible**:

✅ Existing code continues to work
✅ Memoization is automatic
✅ No breaking changes to existing methods
✅ Debug trait is optional

### Gradual Adoption

```php
// Step 1: Update package (existing code works)
composer update webrek/laravel-permission-mongodb

// Step 2: Use new helper methods where beneficial
$count = $user->getPermissionsCount(); // New
$permissions = $user->getAllPermissions(); // Existing, now faster

// Step 3: Add debug trait for troubleshooting (optional)
use HasPermissionsDebug;

// Step 4: Optimize bulk operations (optional)
$user->givePermissionsToBatch($many); // New batch method
```

---

## Examples Library

### Complete Examples

**Admin Dashboard:**
```php
public function stats()
{
    return [
        'users_with_no_permissions' => User::get()->filter->hasNoPermissions()->count(),
        'users_with_admin' => User::role('admin')->count(),
        'avg_permissions_per_user' => User::get()->avg->getPermissionsCount(),
        'top_users_by_permissions' => User::get()
            ->sortByDesc->getPermissionsCount()
            ->take(10)
            ->map(fn($u) => [
                'name' => $u->name,
                'permissions' => $u->getPermissionsCount(),
                'roles' => $u->getRolesCount(),
            ]),
    ];
}
```

**Bulk User Import:**
```php
public function importUsers(array $csvData)
{
    foreach ($csvData as $row) {
        $user = User::create(['email' => $row['email']]);

        // Fast batch assignment
        $user->givePermissionsToBatch($row['permissions'])
             ->assignRole($row['role'])
             ->save();
    }
}
```

**Permission Audit Report:**
```php
public function auditUser(User $user)
{
    return [
        'debug' => $user->debugPermissions(),
        'conflicts' => $user->findPermissionConflicts(),
        'by_role' => $user->getPermissionsByRole(),
        'checks' => $user->checkMultiplePermissions([
            'critical-action-1',
            'critical-action-2',
            'critical-action-3',
        ]),
    ];
}
```
