<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

/**
 * Example Seeder for assigning roles to existing users
 *
 * Run with: php artisan db:seed --class=AssignRolesToUsersSeeder
 */
class AssignRolesToUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Example: Assign roles to first 5 users
        // Customize this based on your needs

        $users = User::take(5)->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Create users first.');
            return;
        }

        // Assign super-admin to first user
        if ($users->count() > 0) {
            $users[0]->assignRole('super-admin');
            $this->command->info("Assigned 'super-admin' to {$users[0]->email}");
        }

        // Assign admin to second user
        if ($users->count() > 1) {
            $users[1]->assignRole('admin');
            $this->command->info("Assigned 'admin' to {$users[1]->email}");
        }

        // Assign editor to third user
        if ($users->count() > 2) {
            $users[2]->assignRole('editor');
            $this->command->info("Assigned 'editor' to {$users[2]->email}");
        }

        // Assign writer to fourth user
        if ($users->count() > 3) {
            $users[3]->assignRole('writer');
            $this->command->info("Assigned 'writer' to {$users[3]->email}");
        }

        // Assign user to fifth user
        if ($users->count() > 4) {
            $users[4]->assignRole('user');
            $this->command->info("Assigned 'user' to {$users[4]->email}");
        }

        $this->command->info('Roles assigned successfully!');
    }
}
