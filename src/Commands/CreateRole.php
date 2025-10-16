<?php

namespace Maklad\Permission\Commands;

use Illuminate\Console\Command;
use function app;
use function config;

/**
 * Class CreateRole
 * @package Maklad\Permission\Commands
 */
class CreateRole extends Command
{
    protected $signature = 'permission:create-role
        {name : The name of the role}
        {guard? : The name of the guard}
        {--permission=* : The name of the permission}';

    protected $description = 'Create a role';

    public function handle()
    {
        $roleClass   = app(config('permission.models.role'));

        $name        = $this->argument('name');
        $guard       = $this->argument('guard');
        $permissions = $this->option('permission');

        // Validate role name
        if (empty($name) || trim($name) === '') {
            $this->error('Role name cannot be empty');
            return 1;
        }

        if (strlen($name) > 255) {
            $this->error('Role name cannot exceed 255 characters');
            return 1;
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\s]+$/', $name)) {
            $this->error('Role name can only contain letters, numbers, hyphens, underscores and spaces');
            return 1;
        }

        // Validate guard if provided
        if ($guard && !array_key_exists($guard, config('auth.guards'))) {
            $this->error("Guard `$guard` is not defined in auth configuration");
            return 1;
        }

        // Check if role already exists
        if ($roleClass::where('name', $name)->where('guard_name', $guard)->exists()) {
            $this->error("Role `$name` already exists for guard `$guard`");
            return 1;
        }

        $role = $roleClass::create([
            'name'       => $name,
            'guard_name' => $guard
        ]);

        $this->info("Role `$role->name` created");

        if (!empty($permissions)) {
            $role->givePermissionTo($permissions);
            $permissionsStr = $role->permissions->implode('name', '`, `');
            $this->info("Permissions `$permissionsStr` has been given to role `$role->name`");
        }

        return 0;
    }
}
