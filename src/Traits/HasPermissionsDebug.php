<?php

namespace Maklad\Permission\Traits;

use Illuminate\Support\Collection;

/**
 * Trait HasPermissionsDebug
 *
 * Optional trait for debugging permissions and roles.
 * Provides helpful methods for inspecting permission state.
 *
 * Usage: Simply add this trait to your User model alongside HasRoles
 *
 * @package Maklad\Permission\Traits
 */
trait HasPermissionsDebug
{
    /**
     * Get a detailed breakdown of all permissions and where they come from.
     * Useful for debugging permission issues.
     *
     * @return array
     */
    public function debugPermissions(): array
    {
        return [
            'direct_permissions' => $this->getDirectPermissions()->pluck('name')->toArray(),
            'direct_permissions_count' => $this->getDirectPermissionsCount(),
            'roles' => $this->getRoleNames()->toArray(),
            'roles_count' => $this->getRolesCount(),
            'permissions_via_roles' => $this->getPermissionsViaRoles()->pluck('name')->toArray(),
            'all_permissions' => $this->getAllPermissions()->pluck('name')->toArray(),
            'total_permissions_count' => $this->getPermissionsCount(),
            'guard' => $this->getDefaultGuardName(),
        ];
    }

    /**
     * Check if a permission check would pass and explain why/why not.
     *
     * @param string $permission
     * @return array
     */
    public function explainPermission(string $permission): array
    {
        $hasPermission = $this->hasPermissionTo($permission);
        $hasDirectly = $this->hasDirectPermission($permission);

        $explanation = [
            'permission' => $permission,
            'has_permission' => $hasPermission,
            'has_directly' => $hasDirectly,
            'has_via_role' => $hasPermission && !$hasDirectly,
            'roles_granting_permission' => [],
        ];

        // Find which roles grant this permission
        if ($hasPermission && !$hasDirectly) {
            foreach ($this->roles as $role) {
                if ($role->hasPermissionTo($permission)) {
                    $explanation['roles_granting_permission'][] = $role->name;
                }
            }
        }

        return $explanation;
    }

    /**
     * Get a summary of permission state suitable for logging/monitoring.
     *
     * @return array
     */
    public function getPermissionsSummary(): array
    {
        return [
            'user_id' => $this->id ?? null,
            'user_identifier' => $this->email ?? $this->name ?? 'unknown',
            'direct_permissions' => $this->getDirectPermissionsCount(),
            'roles' => $this->getRolesCount(),
            'total_permissions' => $this->getPermissionsCount(),
            'has_any_permissions' => !$this->hasNoPermissions(),
            'guard' => $this->getDefaultGuardName(),
        ];
    }

    /**
     * Check multiple permissions at once and return which ones pass/fail.
     *
     * @param array $permissions
     * @return array
     */
    public function checkMultiplePermissions(array $permissions): array
    {
        $results = [];

        foreach ($permissions as $permission) {
            $results[$permission] = [
                'granted' => $this->hasPermissionTo($permission),
                'direct' => $this->hasDirectPermission($permission),
            ];
        }

        return $results;
    }

    /**
     * Get all permissions grouped by role.
     *
     * @return array
     */
    public function getPermissionsByRole(): array
    {
        $grouped = [
            'direct' => $this->getDirectPermissions()->pluck('name')->toArray(),
        ];

        foreach ($this->roles as $role) {
            $grouped[$role->name] = $role->permissions->pluck('name')->toArray();
        }

        return $grouped;
    }

    /**
     * Find potential permission conflicts or duplicates.
     *
     * @return array
     */
    public function findPermissionConflicts(): array
    {
        $directPermissions = $this->getDirectPermissions()->pluck('name');
        $rolePermissions = $this->getPermissionsViaRoles()->pluck('name');

        return [
            'duplicates' => $directPermissions->intersect($rolePermissions)->values()->toArray(),
            'direct_only' => $directPermissions->diff($rolePermissions)->values()->toArray(),
            'role_only' => $rolePermissions->diff($directPermissions)->values()->toArray(),
        ];
    }

    /**
     * Export permission state as JSON (useful for support/debugging).
     *
     * @return string
     */
    public function exportPermissionsJson(): string
    {
        return json_encode([
            'timestamp' => now()->toIso8601String(),
            'debug' => $this->debugPermissions(),
            'conflicts' => $this->findPermissionConflicts(),
            'by_role' => $this->getPermissionsByRole(),
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Log current permission state to application log.
     *
     * @param string $level
     * @param string|null $message
     * @return void
     */
    public function logPermissions(string $level = 'info', ?string $message = null): void
    {
        $message = $message ?? 'User permission state';

        \Log::log($level, $message, $this->getPermissionsSummary());
    }
}
