<?php

namespace Maklad\Permission\Traits;

use Illuminate\Support\Collection;
use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;
use Maklad\Permission\Contracts\PermissionInterface;
use Maklad\Permission\Contracts\PermissionInterface as Permission;
use Maklad\Permission\Events\PermissionAssigned as PermissionAssignedEvent;
use Maklad\Permission\Events\PermissionRevoked as PermissionRevokedEvent;
use Maklad\Permission\Exceptions\GuardDoesNotMatch;
use Maklad\Permission\Guard;
use Maklad\Permission\Helpers;
use Maklad\Permission\PermissionRegistrar;
use ReflectionException;
use function collect;
use function is_array;
use function is_string;

/**
 * Trait HasPermissions
 * @package Maklad\Permission\Traits
 */
trait HasPermissions
{
    private ?Permission $permissionClass = null;

    /**
     * Request-level cache for permissions via roles
     * Prevents multiple queries in the same request
     */
    private ?Collection $cachedPermissionsViaRoles = null;

    /**
     * Request-level cache for all permissions
     */
    private ?Collection $cachedAllPermissions = null;

    public function getPermissionClass(): Permission
    {
        return $this->permissionClass ??= app(PermissionRegistrar::class)->getPermissionClass();
    }

    /**
     * Clear request-level permission cache
     * Useful when permissions change during the same request
     */
    public function clearPermissionCache(): void
    {
        $this->cachedPermissionsViaRoles = null;
        $this->cachedAllPermissions = null;
    }

    /**
     * Query the permissions
     */
    public function permissionsQuery(): Builder
    {
        return $this->getPermissionClass()::whereIn('_id', $this->permission_ids ?? []);
    }

    /**
     * Gets the permissions Attribute
     */
    public function getPermissionsAttribute(): Collection
    {
        return $this->permissionsQuery()->get();
    }

    /**
     * Grant the given permission(s) to a role.
     *
     * @param string|array|Permission|Collection ...$permissions
     *
     * @return self
     * @throws GuardDoesNotMatch
     */
    public function givePermissionTo(string|array|Permission|Collection ...$permissions): self
    {
        $permissionModels = collect($permissions)
            ->flatten()
            ->map(fn($permission) => $this->getStoredPermission($permission));

        $this->permission_ids = collect($this->permission_ids ?? [])
            ->merge($this->extractPermissionIds($permissions))
            ->all();

        $this->save();

        // Dispatch events for each permission assigned
        $permissionModels->each(fn($permission) => PermissionAssignedEvent::dispatch($this, $permission));

        $this->forgetCachedPermissions();
        $this->clearPermissionCache(); // Clear request cache

        return $this;
    }

    /**
     * Remove all current permissions and set the given ones.
     *
     * @param string|array|Permission|Collection ...$permissions
     *
     * @return self
     * @throws GuardDoesNotMatch
     */
    public function syncPermissions(string|array|Permission|Collection ...$permissions): self
    {
        $this->permission_ids = $this->extractPermissionIds($permissions);

        $this->save();

        $this->forgetCachedPermissions();
        $this->clearPermissionCache(); // Clear request cache

        return $this;
    }

    /**
     * Revoke the given permission.
     *
     * @param string|array|Permission|Collection ...$permissions
     *
     * @return self
     * @throws GuardDoesNotMatch
     */
    public function revokePermissionTo(string|array|Permission|Collection ...$permissions): self
    {
        $permissionModels = collect($permissions)
            ->flatten()
            ->map(fn($permission) => $this->getStoredPermission($permission));

        $permissionIds = $this->extractPermissionIds($permissions);

        $this->permission_ids = collect($this->permission_ids ?? [])
            ->filter(function ($permission) use ($permissionIds) {
                return ! in_array($permission, $permissionIds, true);
            })
            ->all();

        $this->save();

        // Dispatch events for each permission revoked
        $permissionModels->each(fn($permission) => PermissionRevokedEvent::dispatch($this, $permission));

        $this->forgetCachedPermissions();
        $this->clearPermissionCache(); // Clear request cache

        return $this;
    }

    /**
     * @param string|Permission $permission
     *
     * @return Permission
     * @throws ReflectionException
     */
    protected function getStoredPermission($permission): Permission
    {
        if (is_string($permission)) {
            return $this->getPermissionClass()->findByName($permission, $this->getDefaultGuardName());
        }

        return $permission;
    }

    /**
     * @param Model $roleOrPermission
     *
     * @throws GuardDoesNotMatch
     * @throws ReflectionException
     */
    protected function ensureModelSharesGuard(Model $roleOrPermission): void
    {
        if (! $this->getGuardNames()->contains($roleOrPermission->guard_name)) {
            $expected = $this->getGuardNames();
            $given    = $roleOrPermission->guard_name;
            $helpers  = new Helpers();

            throw new GuardDoesNotMatch($helpers->getGuardDoesNotMatchMessage($expected, $given));
        }
    }

    /**
     * @return Collection
     * @throws ReflectionException
     */
    protected function getGuardNames(): Collection
    {
        return (new Guard())->getNames($this);
    }

    /**
     * @return string
     * @throws ReflectionException
     */
    protected function getDefaultGuardName(): string
    {
        return (new Guard())->getDefaultName($this);
    }

    /**
     * Forget the cached permissions.
     */
    public function forgetCachedPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Convert to Permission Models
     *
     * @param array|string|Collection $permissions
     *
     * @return Collection
     */
    private function convertToPermissionModels($permissions): Collection
    {
        if (is_array($permissions)) {
            $permissions = collect($permissions);
        }

        if (! $permissions instanceof Collection) {
            $permissions = collect([$permissions]);
        }

        return $permissions->map(function ($permission) {
            return $this->getStoredPermission($permission);
        });
    }

    /**
     * Return a collection of permission names associated with this user.
     *
     * @return Collection
     */
    public function getPermissionNames(): Collection
    {
        return $this->getAllPermissions()->pluck('name');
    }

    /**
     * Return all the permissions the model has via roles.
     * Uses request-level memoization to prevent duplicate queries.
     */
    public function getPermissionsViaRoles(): Collection
    {
        // Return cached result if available
        if ($this->cachedPermissionsViaRoles !== null) {
            return $this->cachedPermissionsViaRoles;
        }

        $permissionIds = $this->roles->pluck('permission_ids')->flatten()->unique()->values();

        $this->cachedPermissionsViaRoles = $this->getPermissionClass()
            ->query()
            ->whereIn('_id', $permissionIds)
            ->get();

        return $this->cachedPermissionsViaRoles;
    }

    /**
     * Return all the permissions the model has, both directly and via roles.
     * Uses request-level memoization to prevent duplicate queries.
     */
    public function getAllPermissions(): Collection
    {
        // Return cached result if available
        if ($this->cachedAllPermissions !== null) {
            return $this->cachedAllPermissions;
        }

        $this->cachedAllPermissions = $this->permissions
            ->merge($this->getPermissionsViaRoles())
            ->sort()
            ->values();

        return $this->cachedAllPermissions;
    }

    /**
     * Determine if the model may perform the given permission.
     *
     * @param string|Permission $permission
     * @param string|null $guardName
     * @return bool
     * @throws ReflectionException
     */
    public function hasPermissionTo(string|Permission $permission, ?string $guardName = null): bool
    {
        if (is_string($permission)) {
            $permission = $this->getPermissionClass()->findByName(
                $permission,
                $guardName ?? $this->getDefaultGuardName()
            );
        }

        return $this->hasDirectPermission($permission) || $this->hasPermissionViaRole($permission);
    }

    /**
     * Determine if the model has any of the given permissions.
     *
     * @param string|array|Permission|Collection ...$permissions
     *
     * @return bool
     * @throws ReflectionException
     */
    public function hasAnyPermission(string|array|Permission|Collection ...$permissions): bool
    {
        if (is_array($permissions[0])) {
            $permissions = $permissions[0];
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the model has all the given permissions(s).
     *
     * @param string|array|Permission|Collection ...$permissions
     *
     * @return bool
     * @throws ReflectionException
     */
    public function hasAllPermissions(string|array|Permission|Collection ...$permissions): bool
    {
        $helpers = new Helpers();
        $permissions = $helpers->flattenArray($permissions);

        foreach ($permissions as $permission) {
            if (!$this->hasPermissionTo($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine if the model has, via roles, the given permission.
     *
     * @param Permission $permission
     *
     * @return bool
     */
    protected function hasPermissionViaRole(Permission $permission): bool
    {
        return $this->hasRole($permission->roles);
    }

    /**
     * Determine if the model has the given permission.
     *
     * @param string|Permission $permission
     *
     * @return bool
     * @throws ReflectionException
     */
    public function hasDirectPermission(string|Permission $permission): bool
    {
        if (is_string($permission)) {
            $permission = $this->getPermissionClass()->findByName($permission, $this->getDefaultGuardName());
        }

        return $this->permissions->contains('_id', $permission->_id);
    }

    /**
     * Return all permissions the directory coupled to the model.
     */
    public function getDirectPermissions(): Collection
    {
        return $this->permissions;
    }

    /**
     * Return a collection of permission IDs associated with this user.
     *
     * @return Collection
     */
    public function getPermissionIds(): Collection
    {
        return collect($this->permission_ids ?? []);
    }

    /**
     * Get the count of permissions assigned to the model.
     *
     * @return int
     */
    public function getPermissionsCount(): int
    {
        return $this->getAllPermissions()->count();
    }

    /**
     * Get the count of direct permissions (not via roles).
     *
     * @return int
     */
    public function getDirectPermissionsCount(): int
    {
        return count($this->permission_ids ?? []);
    }

    /**
     * Determine if the model has no permissions.
     *
     * @return bool
     */
    public function hasNoPermissions(): bool
    {
        return $this->getAllPermissions()->isEmpty();
    }

    /**
     * Determine if the model has any direct permissions.
     *
     * @return bool
     */
    public function hasAnyDirectPermissions(): bool
    {
        return !empty($this->permission_ids);
    }

    /**
     * Check if a permission can be found by name.
     *
     * @param string $name
     * @param string|null $guardName
     * @return bool
     */
    public function permissionExists(string $name, ?string $guardName = null): bool
    {
        try {
            $this->getPermissionClass()->findByName($name, $guardName ?? $this->getDefaultGuardName());
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Scope the model query to certain permissions only.
     *
     * @param Builder $query
     * @param array|string|Permission|Collection $permissions
     *
     * @return Builder
     */
    public function scopePermission(Builder $query, array|string|Permission|Collection $permissions): Builder
    {
        $permissions = $this->convertToPermissionModels($permissions);

        $roles = collect([]);

        foreach ($permissions as $permission) {
            $roles = $roles->merge($permission->roles);
        }
        $roles = $roles->unique();

        return $query->orWhereIn('permission_ids', $permissions->pluck('_id'))
            ->orWhereIn('role_ids', $roles->pluck('_id'));
    }

    /**
     * Extract permission IDs from given permissions (internal use)
     *
     * @param string|array|Permission|Collection $permissions
     * @return array
     */
    protected function extractPermissionIds(...$permissions): array
    {
        return collect($permissions)
            ->flatten()
            ->map(function ($permission) {
                $permission = $this->getStoredPermission($permission);
                $this->ensureModelSharesGuard($permission);
                return $permission->_id;
            })
            ->all();
    }

    /**
     * Give multiple permissions at once without firing events for each (performance optimization).
     * Use this for bulk operations where you don't need individual event tracking.
     *
     * @param array $permissions Array of permission names or objects
     * @return self
     */
    public function givePermissionsToBatch(array $permissions): self
    {
        $permissionIds = collect($permissions)
            ->map(fn($permission) => $this->getStoredPermission($permission))
            ->pluck('_id')
            ->all();

        $this->permission_ids = collect($this->permission_ids ?? [])
            ->merge($permissionIds)
            ->unique()
            ->all();

        $this->save();
        $this->forgetCachedPermissions();
        $this->clearPermissionCache();

        // Fire single event for batch operation
        PermissionAssignedEvent::dispatch($this, collect($permissions)->first());

        return $this;
    }

    /**
     * Revoke multiple permissions at once (batch operation).
     *
     * @param array $permissions
     * @return self
     */
    public function revokePermissionsToBatch(array $permissions): self
    {
        $permissionIds = collect($permissions)
            ->map(fn($permission) => $this->getStoredPermission($permission))
            ->pluck('_id')
            ->all();

        $this->permission_ids = collect($this->permission_ids ?? [])
            ->filter(fn($id) => !in_array($id, $permissionIds, true))
            ->all();

        $this->save();
        $this->forgetCachedPermissions();
        $this->clearPermissionCache();

        return $this;
    }
}
