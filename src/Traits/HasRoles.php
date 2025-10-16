<?php

namespace Maklad\Permission\Traits;

use Illuminate\Support\Collection;
use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsToMany;
use Maklad\Permission\Contracts\RoleInterface as Role;
use Maklad\Permission\Events\RoleAssigned as RoleAssignedEvent;
use Maklad\Permission\Events\RoleRevoked as RoleRevokedEvent;
use Maklad\Permission\Helpers;
use Maklad\Permission\PermissionRegistrar;
use ReflectionException;
use function collect;

/**
 * Trait HasRoles
 * @package Maklad\Permission\Traits
 */
trait HasRoles
{
    use HasPermissions;

    private ?Role $roleClass = null;

    public static function bootHasRoles(): void
    {
        static::deleting(function (Model $model) {
            if (isset($model->forceDeleting) && !$model->forceDeleting) {
                return;
            }

            $model->roles()->sync([]);
        });
    }

    public function getRoleClass(): Role
    {
        return $this->roleClass ??= app(PermissionRegistrar::class)->getRoleClass();
    }

    /**
     * A model may have multiple roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(config('permission.models.role'));
    }

    /**
     * Scope the model query to certain roles only.
     *
     * @param Builder $query
     * @param string|array|Role|Collection $roles
     *
     * @return Builder
     */
    public function scopeRole(Builder $query, string|array|Role|Collection $roles): Builder
    {
        $roles = $this->convertToRoleModels($roles);

        return $query->whereIn('role_ids', $roles->pluck('_id'));
    }

    /**
     * Assign the given role to the model.
     *
     * @param array|string|Role ...$roles
     *
     * @return self
     * @throws ReflectionException
     */
    public function assignRole(string|array|Role ...$roles): self
    {
        $roles = collect($roles)
            ->flatten()
            ->map(fn($role) => $this->getStoredRole($role))
            ->each(fn($role) => $this->ensureModelSharesGuard($role))
            ->all();

        $this->roles()->saveMany($roles);

        // Dispatch events for each role assigned
        collect($roles)->each(fn($role) => RoleAssignedEvent::dispatch($this, $role));

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Revoke the given role from the model.
     *
     * @param array|string|Role ...$roles
     *
     * @return self
     */
    public function removeRole(string|array|Role ...$roles): self
    {
        collect($roles)
            ->flatten()
            ->map(function ($role) {
                $role = $this->getStoredRole($role);
                $this->roles()->detach($role);

                // Dispatch event for role revoked
                RoleRevokedEvent::dispatch($this, $role);

                return $role;
            });

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Remove all current roles and set the given ones.
     *
     * @param array|string|Role ...$roles
     *
     * @return self
     * @throws ReflectionException
     */
    public function syncRoles(string|array|Role ...$roles): self
    {
        $this->roles()->sync([]);

        return $this->assignRole(...$roles);
    }

    /**
     * Determine if the model has (one of) the given role(s).
     *
     * @param string|array|Role|Collection $roles
     *
     * @return bool
     */
    public function hasRole(string|array|Role|Collection $roles): bool
    {
        if (\is_string($roles) && str_contains($roles, '|')) {
            $roles = \explode('|', $roles);
        }

        if (\is_string($roles) || $roles instanceof Role) {
            return $this->roles->contains('name', $roles->name ?? $roles);
        }

        $roles = collect()->make($roles)->map(function ($role) {
            return $role instanceof Role ? $role->name : $role;
        });

        return !$roles->intersect($this->roles->pluck('name'))->isEmpty();
    }

    /**
     * Determine if the model has any of the given role(s).
     *
     * @param string|array|Role|Collection $roles
     *
     * @return bool
     */
    public function hasAnyRole(string|array|Role|Collection $roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Determine if the model has all the given role(s).
     *
     * @param string|array|Role|Collection ...$roles
     *
     * @return bool
     */
    public function hasAllRoles(string|array|Role|Collection ...$roles): bool
    {
        $helpers = new Helpers();
        $roles = $helpers->flattenArray($roles);

        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Return Role object
     *
     * @param string|Role $role role name
     *
     * @return Role
     * @throws ReflectionException
     */
    protected function getStoredRole(string|Role $role): Role
    {
        if (\is_string($role)) {
            return $this->getRoleClass()->findByName($role, $this->getDefaultGuardName());
        }

        return $role;
    }

    /**
     * Return a collection of role names associated with this user.
     *
     * @return Collection
     */
    public function getRoleNames(): Collection
    {
        return $this->roles()->pluck('name');
    }

    /**
     * Return a collection of role IDs associated with this user.
     *
     * @return Collection
     */
    public function getRoleIds(): Collection
    {
        return $this->roles()->pluck('_id');
    }

    /**
     * Determine if the model has exactly the given roles (no more, no less).
     *
     * @param string|array|Role|Collection ...$roles
     * @return bool
     */
    public function hasExactRoles(string|array|Role|Collection ...$roles): bool
    {
        $helpers = new Helpers();
        $roles = collect($helpers->flattenArray($roles));

        $currentRoles = $this->getRoleNames();

        return $currentRoles->sort()->values()->toArray() ===
               $roles->map(fn($role) => is_string($role) ? $role : $role->name)
                     ->sort()
                     ->values()
                     ->toArray();
    }

    /**
     * Get the count of roles assigned to the model.
     *
     * @return int
     */
    public function getRolesCount(): int
    {
        return $this->roles()->count();
    }

    /**
     * Determine if the model has no roles.
     *
     * @return bool
     */
    public function hasNoRoles(): bool
    {
        return $this->roles()->isEmpty();
    }

    /**
     * Convert to Role Models
     *
     * @param string|array|Role|Collection $roles
     *
     * @return Collection
     */
    private function convertToRoleModels(string|array|Role|Collection $roles): Collection
    {
        if (is_array($roles)) {
            $roles = collect($roles);
        }

        if (!$roles instanceof Collection) {
            $roles = collect([$roles]);
        }

        return $roles->map(function ($role) {
            return $this->getStoredRole($role);
        });
    }
}
