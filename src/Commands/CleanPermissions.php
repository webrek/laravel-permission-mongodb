<?php

namespace Maklad\Permission\Commands;

use Illuminate\Console\Command;
use Maklad\Permission\Contracts\PermissionInterface as Permission;
use Maklad\Permission\Contracts\RoleInterface as Role;

class CleanPermissions extends Command
{
    protected $signature = 'permission:clean
                            {--dry-run : Show what would be cleaned without actually deleting}
                            {--orphaned-permissions : Clean orphaned permissions (not assigned to any role or user)}
                            {--orphaned-roles : Clean orphaned roles (not assigned to any user)}
                            {--invalid-guards : Remove permissions/roles with invalid guards}';

    protected $description = 'Clean up orphaned permissions, roles, and invalid data';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $cleanPermissions = $this->option('orphaned-permissions');
        $cleanRoles = $this->option('orphaned-roles');
        $cleanInvalidGuards = $this->option('invalid-guards');

        // If no specific option, clean everything
        if (!$cleanPermissions && !$cleanRoles && !$cleanInvalidGuards) {
            $cleanPermissions = true;
            $cleanRoles = true;
            $cleanInvalidGuards = true;
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No actual changes will be made');
            $this->line('');
        }

        $totalCleaned = 0;

        if ($cleanInvalidGuards) {
            $totalCleaned += $this->cleanInvalidGuards($dryRun);
        }

        if ($cleanPermissions) {
            $totalCleaned += $this->cleanOrphanedPermissions($dryRun);
        }

        if ($cleanRoles) {
            $totalCleaned += $this->cleanOrphanedRoles($dryRun);
        }

        if ($totalCleaned === 0) {
            $this->info('✓ Nothing to clean - all data is valid!');
        } else {
            if ($dryRun) {
                $this->warn("Would clean {$totalCleaned} items (use without --dry-run to actually delete)");
            } else {
                $this->info("✓ Cleaned {$totalCleaned} items");
            }
        }

        return 0;
    }

    protected function cleanInvalidGuards(bool $dryRun): int
    {
        $this->info('─── Checking for invalid guards ───');

        $validGuards = array_keys(config('auth.guards', []));
        $permissionModel = app(Permission::class);
        $roleModel = app(Role::class);

        $invalidPermissions = $permissionModel->query()
            ->whereNotIn('guard_name', $validGuards)
            ->get();

        $invalidRoles = $roleModel->query()
            ->whereNotIn('guard_name', $validGuards)
            ->get();

        $count = $invalidPermissions->count() + $invalidRoles->count();

        if ($count === 0) {
            $this->line('  No invalid guards found');
            return 0;
        }

        $this->warn("  Found {$count} items with invalid guards:");

        foreach ($invalidPermissions as $permission) {
            $this->line("    Permission: {$permission->name} (guard: {$permission->guard_name})");
        }

        foreach ($invalidRoles as $role) {
            $this->line("    Role: {$role->name} (guard: {$role->guard_name})");
        }

        if (!$dryRun) {
            foreach ($invalidPermissions as $permission) {
                $permission->delete();
            }
            foreach ($invalidRoles as $role) {
                $role->delete();
            }
        }

        return $count;
    }

    protected function cleanOrphanedPermissions(bool $dryRun): int
    {
        $this->info('─── Checking for orphaned permissions ───');

        $permissionModel = app(Permission::class);
        $orphaned = [];

        foreach ($permissionModel->all() as $permission) {
            // Check if permission is assigned to any user or role
            $usedInRoles = $permission->roles()->count();

            // For users, we need to check the users collection
            // This is tricky with MongoDB - we'll check if permission_ids contains this permission's ID
            $userModel = config('auth.providers.users.model');
            $usedByUsers = 0;

            if ($userModel && class_exists($userModel)) {
                $usedByUsers = $userModel::where('permission_ids', $permission->_id)->count();
            }

            if ($usedInRoles === 0 && $usedByUsers === 0) {
                $orphaned[] = $permission;
            }
        }

        $count = count($orphaned);

        if ($count === 0) {
            $this->line('  No orphaned permissions found');
            return 0;
        }

        $this->warn("  Found {$count} orphaned permissions:");

        foreach ($orphaned as $permission) {
            $this->line("    • {$permission->name} (guard: {$permission->guard_name})");
        }

        if (!$dryRun) {
            foreach ($orphaned as $permission) {
                $permission->delete();
            }
        }

        return $count;
    }

    protected function cleanOrphanedRoles(bool $dryRun): int
    {
        $this->info('─── Checking for orphaned roles ───');

        $roleModel = app(Role::class);
        $orphaned = [];

        foreach ($roleModel->all() as $role) {
            // Check if role is assigned to any user
            $userModel = config('auth.providers.users.model');
            $usedByUsers = 0;

            if ($userModel && class_exists($userModel)) {
                $usedByUsers = $userModel::where('role_ids', $role->_id)->count();
            }

            if ($usedByUsers === 0) {
                $orphaned[] = $role;
            }
        }

        $count = count($orphaned);

        if ($count === 0) {
            $this->line('  No orphaned roles found');
            return 0;
        }

        $this->warn("  Found {$count} orphaned roles:");

        foreach ($orphaned as $role) {
            $permissionCount = $role->permissions()->count();
            $this->line("    • {$role->name} (guard: {$role->guard_name}, {$permissionCount} permissions)");
        }

        if (!$dryRun) {
            foreach ($orphaned as $role) {
                $role->delete();
            }
        }

        return $count;
    }
}
