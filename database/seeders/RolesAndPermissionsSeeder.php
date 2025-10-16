<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Maklad\Permission\Models\Permission;
use Maklad\Permission\Models\Role;

/**
 * Example Roles and Permissions Seeder
 *
 * Creates a complete permission structure for a blog application.
 *
 * Run with: php artisan db:seed --class=RolesAndPermissionsSeeder
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Maklad\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            // Article permissions
            'view-articles',
            'create-articles',
            'edit-articles',
            'edit-own-articles',
            'delete-articles',
            'delete-own-articles',
            'publish-articles',
            'unpublish-articles',

            // Comment permissions
            'view-comments',
            'create-comments',
            'edit-comments',
            'edit-own-comments',
            'delete-comments',
            'moderate-comments',

            // User management permissions
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',

            // Role management permissions
            'view-roles',
            'create-roles',
            'edit-roles',
            'delete-roles',
            'assign-roles',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['guard_name' => 'web']
            );
        }

        // Create Roles and assign permissions

        // Super Admin - all permissions
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super-admin'],
            ['guard_name' => 'web']
        );
        $superAdmin->givePermissionTo(Permission::all());

        // Admin - user and content management
        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['guard_name' => 'web']
        );
        $admin->givePermissionTo([
            'view-articles',
            'create-articles',
            'edit-articles',
            'delete-articles',
            'publish-articles',
            'unpublish-articles',
            'view-comments',
            'moderate-comments',
            'delete-comments',
            'view-users',
            'create-users',
            'edit-users',
        ]);

        // Editor - content management
        $editor = Role::firstOrCreate(
            ['name' => 'editor'],
            ['guard_name' => 'web']
        );
        $editor->givePermissionTo([
            'view-articles',
            'create-articles',
            'edit-articles',
            'publish-articles',
            'unpublish-articles',
            'view-comments',
            'moderate-comments',
        ]);

        // Writer - basic content creation
        $writer = Role::firstOrCreate(
            ['name' => 'writer'],
            ['guard_name' => 'web']
        );
        $writer->givePermissionTo([
            'view-articles',
            'create-articles',
            'edit-own-articles',
            'delete-own-articles',
            'view-comments',
            'create-comments',
        ]);

        // Moderator - comment moderation
        $moderator = Role::firstOrCreate(
            ['name' => 'moderator'],
            ['guard_name' => 'web']
        );
        $moderator->givePermissionTo([
            'view-articles',
            'view-comments',
            'moderate-comments',
            'delete-comments',
        ]);

        // User - basic reader
        $user = Role::firstOrCreate(
            ['name' => 'user'],
            ['guard_name' => 'web']
        );
        $user->givePermissionTo([
            'view-articles',
            'view-comments',
            'create-comments',
            'edit-own-comments',
        ]);

        $this->command->info('Roles and permissions created successfully!');
        $this->command->info('Created roles: super-admin, admin, editor, writer, moderator, user');
        $this->command->info('Created ' . count($permissions) . ' permissions');
    }
}
