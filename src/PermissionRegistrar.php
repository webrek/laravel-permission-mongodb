<?php

namespace Maklad\Permission;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Maklad\Permission\Contracts\PermissionInterface as Permission;
use Maklad\Permission\Events\PermissionCacheFlushed;

/**
 * Class PermissionRegistrar
 * @package Maklad\Permission
 */
class PermissionRegistrar
{
    protected readonly string $cacheKey;
    protected readonly string $permissionClass;
    protected readonly string $roleClass;

    /**
     * PermissionRegistrar constructor.
     * @param Gate $gate
     * @param Repository $cache
     */
    public function __construct(
        protected readonly Gate $gate,
        protected readonly Repository $cache
    ) {
        $this->cacheKey = 'maklad.permission.cache';
        $this->permissionClass = config('permission.models.permission');
        $this->roleClass = config('permission.models.role');
    }

    /**
     * Register Permissions
     *
     * @return bool
     */
    public function registerPermissions(): bool
    {
        $this->getPermissions()->map(function (Permission $permission) {
            $this->gate->define($permission->name, function (Authorizable $user) use ($permission) {
                return $user->hasPermissionTo($permission) ?: null;
            });
        });

        return true;
    }

    /**
     * Forget cached permission
     */
    public function forgetCachedPermissions(): void
    {
        $this->cache->forget($this->cacheKey);

        // Dispatch cache flush event
        PermissionCacheFlushed::dispatch('permission_update');
    }

    /**
     * Get Permissions
     *
     * @return Collection
     */
    public function getPermissions(): Collection
    {
        return $this->cache->remember($this->cacheKey, config('permission.cache_expiration_time'), function () {
            return $this->getPermissionClass()->get();
        });
    }

    /**
     * Get Permission class
     *
     * @return Application|mixed
     */
    public function getPermissionClass(): mixed
    {
        return app($this->permissionClass);
    }

    /**
     * Get Role class
     *
     * @return Application|mixed
     */
    public function getRoleClass(): mixed
    {
        return app($this->roleClass);
    }
}
