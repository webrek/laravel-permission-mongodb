<?php

namespace Maklad\Permission\Traits;

use Maklad\Permission\Models\PermissionAudit;

/**
 * Trait HasPermissionsAudit
 *
 * Add this trait to your User model to automatically log all permission
 * and role changes for audit purposes.
 *
 * Usage:
 * use HasRoles, HasPermissionsAudit;
 */
trait HasPermissionsAudit
{
    public static function bootHasPermissionsAudit(): void
    {
        // Override permission methods to add audit logging
        static::creating(function ($model) {
            if (isset($model->permission_ids) || isset($model->role_ids)) {
                PermissionAudit::log('model_created', $model);
            }
        });
    }

    /**
     * Override givePermissionTo to add audit logging
     */
    public function givePermissionToWithAudit(...$permissions): self
    {
        $result = $this->givePermissionTo(...$permissions);

        foreach (collect($permissions)->flatten() as $permission) {
            $permissionName = is_string($permission) ? $permission : $permission->name;
            PermissionAudit::log('permission_assigned', $this, $permissionName);
        }

        return $result;
    }

    /**
     * Override revokePermissionTo to add audit logging
     */
    public function revokePermissionToWithAudit(...$permissions): self
    {
        foreach (collect($permissions)->flatten() as $permission) {
            $permissionName = is_string($permission) ? $permission : $permission->name;
            PermissionAudit::log('permission_revoked', $this, $permissionName);
        }

        return $this->revokePermissionTo(...$permissions);
    }

    /**
     * Override assignRole to add audit logging
     */
    public function assignRoleWithAudit(...$roles): self
    {
        $result = $this->assignRole(...$roles);

        foreach (collect($roles)->flatten() as $role) {
            $roleName = is_string($role) ? $role : $role->name;
            PermissionAudit::log('role_assigned', $this, null, $roleName);
        }

        return $result;
    }

    /**
     * Override removeRole to add audit logging
     */
    public function removeRoleWithAudit(...$roles): self
    {
        foreach (collect($roles)->flatten() as $role) {
            $roleName = is_string($role) ? $role : $role->name;
            PermissionAudit::log('role_revoked', $this, null, $roleName);
        }

        return $this->removeRole(...$roles);
    }

    /**
     * Get audit log for this model
     */
    public function permissionAudits()
    {
        return PermissionAudit::where('model_type', get_class($this))
            ->where('model_id', $this->id ?? $this->_id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get recent audit log
     */
    public function recentPermissionAudits(int $limit = 10)
    {
        return $this->permissionAudits()->take($limit);
    }
}
