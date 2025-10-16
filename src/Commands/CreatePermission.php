<?php

namespace Maklad\Permission\Commands;

use Illuminate\Console\Command;
use function app;
use function config;

/**
 * Class CreatePermission
 * @package Maklad\Permission\Commands
 */
class CreatePermission extends Command
{
    protected $signature = 'permission:create-permission
                {name : The name of the permission}
                {guard? : The name of the guard}';

    protected $description = 'Create a permission';

    public function handle()
    {
        $permissionClass = app(config('permission.models.permission'));

        $name  = $this->argument('name');
        $guard = $this->argument('guard');

        // Validate permission name
        if (empty($name) || trim($name) === '') {
            $this->error('Permission name cannot be empty');
            return 1;
        }

        if (strlen($name) > 255) {
            $this->error('Permission name cannot exceed 255 characters');
            return 1;
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\s]+$/', $name)) {
            $this->error('Permission name can only contain letters, numbers, hyphens, underscores and spaces');
            return 1;
        }

        // Validate guard if provided
        if ($guard && !array_key_exists($guard, config('auth.guards'))) {
            $this->error("Guard `$guard` is not defined in auth configuration");
            return 1;
        }

        // Check if permission already exists
        if ($permissionClass::where('name', $name)->where('guard_name', $guard)->exists()) {
            $this->error("Permission `$name` already exists for guard `$guard`");
            return 1;
        }

        $permission = $permissionClass::create([
            'name'       => $name,
            'guard_name' => $guard
        ]);

        $this->info("Permission `$permission->name` created");

        return 0;
    }
}
