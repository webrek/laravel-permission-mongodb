<?php

namespace Maklad\Permission\Traits;

use function app;
use function config;

/**
 * Trait RefreshesPermissionCache
 * @package Maklad\Permission\Traits
 */
trait RefreshesPermissionCache
{
    /**
     * Relevant fields that should trigger cache invalidation
     */
    protected array $permissionCacheFields = [
        'name',
        'guard_name',
        'permission_ids',
        'role_ids'
    ];

    /**
     * Refresh Permission Cache
     *
     * @return void
     */
    public static function bootRefreshesPermissionCache(): void
    {
        static::saved(function ($model) {
            // Only invalidate cache if relevant fields were changed
            if ($model->shouldInvalidatePermissionCache()) {
                app(config('permission.models.permission'))->forgetCachedPermissions();
            }
        });

        static::deleted(function () {
            app(config('permission.models.permission'))->forgetCachedPermissions();
        });
    }

    /**
     * Determine if permission cache should be invalidated
     *
     * @return bool
     */
    protected function shouldInvalidatePermissionCache(): bool
    {
        // If model is new, always invalidate
        if (!$this->exists) {
            return true;
        }

        // Check if any relevant fields have changed
        return collect($this->permissionCacheFields)
            ->contains(fn($field) => $this->isDirty($field));
    }
}
