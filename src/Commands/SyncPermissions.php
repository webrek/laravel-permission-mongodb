<?php

namespace Maklad\Permission\Commands;

use Illuminate\Console\Command;
use Maklad\Permission\Contracts\PermissionInterface as Permission;
use Maklad\Permission\Contracts\RoleInterface as Role;

class SyncPermissions extends Command
{
    protected $signature = 'permission:sync
                            {--config= : Config file path (default: config/permissions.php)}
                            {--fresh : Delete all existing permissions and roles before syncing}';

    protected $description = 'Sync permissions and roles from config file';

    public function handle(): int
    {
        $configPath = $this->option('config') ?? config_path('permissions.php');

        if (!file_exists($configPath)) {
            $this->error("Config file not found: {$configPath}");
            $this->info('Create one with: php artisan vendor:publish --tag=permission-config-example');
            return 1;
        }

        $config = require $configPath;

        if (!is_array($config)) {
            $this->error('Config file must return an array');
            return 1;
        }

        if ($this->option('fresh')) {
            if (!$this->confirm('This will delete ALL existing permissions and roles. Continue?')) {
                $this->info('Cancelled.');
                return 0;
            }

            $this->warn('Deleting all existing permissions and roles...');
            app(Permission::class)->query()->delete();
            app(Role::class)->query()->delete();
            $this->info('✓ Deleted');
        }

        $this->info('Syncing permissions and roles...');

        // Sync permissions
        if (isset($config['permissions']) && is_array($config['permissions'])) {
            $this->syncPermissions($config['permissions']);
        }

        // Sync roles
        if (isset($config['roles']) && is_array($config['roles'])) {
            $this->syncRoles($config['roles']);
        }

        $this->info('✓ Sync completed successfully!');

        return 0;
    }

    protected function syncPermissions(array $permissions): void
    {
        $permissionModel = app(Permission::class);
        $created = 0;
        $updated = 0;

        foreach ($permissions as $permissionData) {
            $name = is_string($permissionData) ? $permissionData : ($permissionData['name'] ?? null);
            $guardName = is_array($permissionData) ? ($permissionData['guard'] ?? 'web') : 'web';

            if (!$name) {
                continue;
            }

            $permission = $permissionModel->query()
                ->where('name', $name)
                ->where('guard_name', $guardName)
                ->first();

            if ($permission) {
                $updated++;
            } else {
                $permissionModel->create([
                    'name' => $name,
                    'guard_name' => $guardName,
                ]);
                $created++;
            }
        }

        $this->line("  Permissions: {$created} created, {$updated} existing");
    }

    protected function syncRoles(array $roles): void
    {
        $roleModel = app(Role::class);
        $permissionModel = app(Permission::class);
        $created = 0;
        $updated = 0;

        foreach ($roles as $roleData) {
            $name = is_string($roleData) ? $roleData : ($roleData['name'] ?? null);
            $guardName = is_array($roleData) ? ($roleData['guard'] ?? 'web') : 'web';
            $permissions = is_array($roleData) ? ($roleData['permissions'] ?? []) : [];

            if (!$name) {
                continue;
            }

            $role = $roleModel->query()
                ->where('name', $name)
                ->where('guard_name', $guardName)
                ->first();

            if (!$role) {
                $role = $roleModel->create([
                    'name' => $name,
                    'guard_name' => $guardName,
                ]);
                $created++;
            } else {
                $updated++;
            }

            // Sync permissions for this role
            if (!empty($permissions)) {
                $permissionObjects = [];
                foreach ($permissions as $permissionName) {
                    $permission = $permissionModel->query()
                        ->where('name', $permissionName)
                        ->where('guard_name', $guardName)
                        ->first();

                    if ($permission) {
                        $permissionObjects[] = $permission;
                    }
                }

                if (!empty($permissionObjects)) {
                    $role->syncPermissions($permissionObjects);
                }
            }
        }

        $this->line("  Roles: {$created} created, {$updated} updated");
    }
}
