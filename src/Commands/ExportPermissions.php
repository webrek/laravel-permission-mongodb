<?php

namespace Maklad\Permission\Commands;

use Illuminate\Console\Command;
use Maklad\Permission\Contracts\PermissionInterface as Permission;
use Maklad\Permission\Contracts\RoleInterface as Role;

class ExportPermissions extends Command
{
    protected $signature = 'permission:export
                            {file : Output file path}
                            {--format=json : Export format (json or yaml)}
                            {--guard= : Only export for specific guard}';

    protected $description = 'Export all permissions and roles to a file';

    public function handle(): int
    {
        $file = $this->argument('file');
        $format = $this->option('format');
        $guard = $this->option('guard');

        if (!in_array($format, ['json', 'yaml'])) {
            $this->error('Format must be json or yaml');
            return 1;
        }

        if ($format === 'yaml' && !function_exists('yaml_emit')) {
            $this->error('YAML extension not installed. Install with: pecl install yaml');
            return 1;
        }

        $this->info('Exporting permissions and roles...');

        $data = $this->exportData($guard);

        $content = $this->formatData($data, $format);

        // Ensure directory exists
        $directory = dirname($file);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($file, $content);

        $permissionCount = count($data['permissions']);
        $roleCount = count($data['roles']);

        $this->info("✓ Exported {$permissionCount} permissions and {$roleCount} roles to: {$file}");

        return 0;
    }

    protected function exportData(?string $guard): array
    {
        $permissionModel = app(Permission::class);
        $roleModel = app(Role::class);

        $permissionQuery = $permissionModel->query();
        $roleQuery = $roleModel->query();

        if ($guard) {
            $permissionQuery->where('guard_name', $guard);
            $roleQuery->where('guard_name', $guard);
        }

        $permissions = $permissionQuery->get()->map(function ($permission) {
            return [
                'name' => $permission->name,
                'guard' => $permission->guard_name,
            ];
        })->toArray();

        $roles = $roleQuery->get()->map(function ($role) {
            return [
                'name' => $role->name,
                'guard' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ];
        })->toArray();

        return [
            'exported_at' => now()->toIso8601String(),
            'guard' => $guard ?? 'all',
            'permissions' => $permissions,
            'roles' => $roles,
        ];
    }

    protected function formatData(array $data, string $format): string
    {
        if ($format === 'yaml') {
            return yaml_emit($data, YAML_UTF8_ENCODING);
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
