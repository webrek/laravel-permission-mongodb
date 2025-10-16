<?php

namespace Maklad\Permission\Commands;

use Illuminate\Console\Command;
use Maklad\Permission\Contracts\PermissionInterface as Permission;
use Maklad\Permission\Contracts\RoleInterface as Role;

class ImportPermissions extends Command
{
    protected $signature = 'permission:import
                            {file : Input file path}
                            {--format=json : Import format (json or yaml)}
                            {--fresh : Delete existing permissions/roles before import}';

    protected $description = 'Import permissions and roles from a file';

    public function handle(): int
    {
        $file = $this->argument('file');
        $format = $this->option('format');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        if (!in_array($format, ['json', 'yaml'])) {
            $this->error('Format must be json or yaml');
            return 1;
        }

        if ($format === 'yaml' && !function_exists('yaml_parse_file')) {
            $this->error('YAML extension not installed. Install with: pecl install yaml');
            return 1;
        }

        $this->info('Importing permissions and roles...');

        $data = $this->parseFile($file, $format);

        if (!$data) {
            $this->error('Failed to parse file');
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
        }

        $imported = $this->importData($data);

        $this->info("✓ Imported {$imported['permissions']} permissions and {$imported['roles']} roles");

        return 0;
    }

    protected function parseFile(string $file, string $format): ?array
    {
        if ($format === 'yaml') {
            return yaml_parse_file($file);
        }

        $content = file_get_contents($file);
        return json_decode($content, true);
    }

    protected function importData(array $data): array
    {
        $permissionModel = app(Permission::class);
        $roleModel = app(Role::class);

        $permissionCount = 0;
        $roleCount = 0;

        // Import permissions
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            foreach ($data['permissions'] as $permissionData) {
                $permissionModel->firstOrCreate([
                    'name' => $permissionData['name'],
                    'guard_name' => $permissionData['guard'] ?? 'web',
                ]);
                $permissionCount++;
            }
        }

        // Import roles
        if (isset($data['roles']) && is_array($data['roles'])) {
            foreach ($data['roles'] as $roleData) {
                $role = $roleModel->firstOrCreate([
                    'name' => $roleData['name'],
                    'guard_name' => $roleData['guard'] ?? 'web',
                ]);

                // Assign permissions to role
                if (isset($roleData['permissions']) && is_array($roleData['permissions'])) {
                    $permissions = $permissionModel->query()
                        ->whereIn('name', $roleData['permissions'])
                        ->where('guard_name', $roleData['guard'] ?? 'web')
                        ->get();

                    if ($permissions->isNotEmpty()) {
                        $role->syncPermissions($permissions);
                    }
                }

                $roleCount++;
            }
        }

        return [
            'permissions' => $permissionCount,
            'roles' => $roleCount,
        ];
    }
}
