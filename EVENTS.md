# Event System Documentation

Laravel Permission MongoDB v6.0 introduces a comprehensive event system for observability and auditing.

## Available Events

### Permission Events

#### `Maklad\Permission\Events\PermissionAssigned`
Fired when a permission is granted to a model.

**Properties:**
- `public readonly Model $model` - The model receiving the permission
- `public readonly Permission $permission` - The permission being assigned

#### `Maklad\Permission\Events\PermissionRevoked`
Fired when a permission is removed from a model.

**Properties:**
- `public readonly Model $model` - The model losing the permission
- `public readonly Permission $permission` - The permission being revoked

### Role Events

#### `Maklad\Permission\Events\RoleAssigned`
Fired when a role is assigned to a model.

**Properties:**
- `public readonly Model $model` - The model receiving the role
- `public readonly Role $role` - The role being assigned

#### `Maklad\Permission\Events\RoleRevoked`
Fired when a role is removed from a model.

**Properties:**
- `public readonly Model $model` - The model losing the role
- `public readonly Role $role` - The role being revoked

### Cache Events

#### `Maklad\Permission\Events\PermissionCacheFlushed`
Fired when the permission cache is invalidated.

**Properties:**
- `public readonly string $reason` - Reason for cache flush (default: 'permission_update')

---

## Usage Examples

### 1. Audit Log for Permission Changes

Create a listener to log all permission changes:

```php
<?php

namespace App\Listeners;

use Maklad\Permission\Events\PermissionAssigned;
use Illuminate\Support\Facades\Log;

class LogPermissionAssigned
{
    public function handle(PermissionAssigned $event): void
    {
        Log::info('Permission assigned', [
            'user_id' => $event->model->id,
            'user_email' => $event->model->email,
            'permission' => $event->permission->name,
            'guard' => $event->permission->guard_name,
            'timestamp' => now(),
        ]);
    }
}
```

Register in `EventServiceProvider`:

```php
protected $listen = [
    \Maklad\Permission\Events\PermissionAssigned::class => [
        \App\Listeners\LogPermissionAssigned::class,
    ],
    \Maklad\Permission\Events\PermissionRevoked::class => [
        \App\Listeners\LogPermissionRevoked::class,
    ],
];
```

### 2. Send Notification on Role Assignment

```php
<?php

namespace App\Listeners;

use Maklad\Permission\Events\RoleAssigned;
use App\Notifications\RoleAssignedNotification;

class NotifyUserOfRoleAssignment
{
    public function handle(RoleAssigned $event): void
    {
        if ($event->role->name === 'admin') {
            $event->model->notify(
                new RoleAssignedNotification($event->role)
            );
        }
    }
}
```

### 3. Sync to External System

```php
<?php

namespace App\Listeners;

use Maklad\Permission\Events\PermissionAssigned;
use App\Services\ExternalAuditService;

class SyncPermissionToExternalSystem
{
    public function __construct(
        private ExternalAuditService $auditService
    ) {}

    public function handle(PermissionAssigned $event): void
    {
        $this->auditService->recordPermissionChange([
            'action' => 'assigned',
            'user_id' => $event->model->id,
            'permission_id' => $event->permission->_id,
            'permission_name' => $event->permission->name,
        ]);
    }
}
```

### 4. Monitor Cache Performance

```php
<?php

namespace App\Listeners;

use Maklad\Permission\Events\PermissionCacheFlushed;
use Illuminate\Support\Facades\Cache;

class TrackCacheFlushMetrics
{
    public function handle(PermissionCacheFlushed $event): void
    {
        $key = 'permission_cache_flushes_' . now()->format('Y-m-d');

        Cache::increment($key, 1);

        // Alert if too many flushes
        if (Cache::get($key) > 100) {
            \Log::warning('Permission cache flushed too many times today', [
                'count' => Cache::get($key),
                'reason' => $event->reason,
            ]);
        }
    }
}
```

### 5. Database Audit Trail

```php
<?php

namespace App\Listeners;

use Maklad\Permission\Events\RoleAssigned;
use Maklad\Permission\Events\RoleRevoked;
use App\Models\AuditLog;

class CreatePermissionAuditLog
{
    public function handleRoleAssigned(RoleAssigned $event): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'role_assigned',
            'subject_type' => get_class($event->model),
            'subject_id' => $event->model->id,
            'metadata' => [
                'role_name' => $event->role->name,
                'role_id' => $event->role->_id,
            ],
        ]);
    }

    public function handleRoleRevoked(RoleRevoked $event): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'role_revoked',
            'subject_type' => get_class($event->model),
            'subject_id' => $event->model->id,
            'metadata' => [
                'role_name' => $event->role->name,
                'role_id' => $event->role->_id,
            ],
        ]);
    }
}
```

Register multiple events to same listener:

```php
protected $listen = [
    \Maklad\Permission\Events\RoleAssigned::class => [
        \App\Listeners\CreatePermissionAuditLog::class . '@handleRoleAssigned',
    ],
    \Maklad\Permission\Events\RoleRevoked::class => [
        \App\Listeners\CreatePermissionAuditLog::class . '@handleRoleRevoked',
    ],
];
```

---

## Queued Listeners

For better performance, you can queue event listeners:

```php
<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Maklad\Permission\Events\PermissionAssigned;

class LogPermissionAssigned implements ShouldQueue
{
    public $queue = 'permissions';

    public function handle(PermissionAssigned $event): void
    {
        // This will be processed asynchronously
        \Log::info('Permission assigned', [
            'user_id' => $event->model->id,
            'permission' => $event->permission->name,
        ]);
    }
}
```

---

## Testing with Events

You can fake events in your tests:

```php
use Illuminate\Support\Facades\Event;
use Maklad\Permission\Events\RoleAssigned;

public function test_user_receives_notification_on_admin_role(): void
{
    Event::fake([RoleAssigned::class]);

    $user = User::factory()->create();
    $user->assignRole('admin');

    Event::assertDispatched(RoleAssigned::class, function ($event) use ($user) {
        return $event->model->id === $user->id
            && $event->role->name === 'admin';
    });
}
```

---

## Best Practices

1. **Use Queued Listeners** for expensive operations (API calls, email sending)
2. **Keep Listeners Focused** - one responsibility per listener
3. **Handle Failures Gracefully** - events should not break core functionality
4. **Log Important Events** - maintain audit trail for security and compliance
5. **Monitor Event Volume** - track cache flushes to optimize performance

---

## Performance Considerations

Events add minimal overhead (~1-2ms per event). However:

- **Avoid N+1 Event Dispatches**: When assigning multiple permissions, events are dispatched for each. Consider batching if you have hundreds of permissions.
- **Queue Heavy Operations**: Don't send emails or make API calls synchronously in listeners.
- **Cache Listener Results**: If listeners query the database, cache results when appropriate.

---

## Migration Guide from v5.x

If you're upgrading from v5.x, note that:

1. **No Events in v5.x**: This is a new feature, so no breaking changes
2. **Optional Feature**: Events work out of the box, no configuration needed
3. **Opt-Out**: If you don't want events, simply don't register listeners

Events are automatically dispatched when using:
- `$model->assignRole()`
- `$model->removeRole()`
- `$model->givePermissionTo()`
- `$model->revokePermissionTo()`
- Cache invalidation (automatic)
