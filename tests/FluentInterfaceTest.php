<?php

namespace Maklad\Permission\Test;

class FluentInterfaceTest extends TestCase
{
    /** @test */
    public function give_permission_to_returns_self()
    {
        $result = $this->testUser->givePermissionTo('edit-articles');

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->testUser->id, $result->id);
    }

    /** @test */
    public function revoke_permission_to_returns_self()
    {
        $this->testUser->givePermissionTo('edit-articles');
        $result = $this->testUser->revokePermissionTo('edit-articles');

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->testUser->id, $result->id);
    }

    /** @test */
    public function sync_permissions_returns_self()
    {
        $result = $this->testUser->syncPermissions('edit-articles', 'edit-news');

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->testUser->id, $result->id);
    }

    /** @test */
    public function assign_role_returns_self()
    {
        $result = $this->testUser->assignRole('testRole');

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->testUser->id, $result->id);
    }

    /** @test */
    public function remove_role_returns_self()
    {
        $this->testUser->assignRole('testRole');
        $result = $this->testUser->removeRole('testRole');

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->testUser->id, $result->id);
    }

    /** @test */
    public function sync_roles_returns_self()
    {
        $result = $this->testUser->syncRoles('testRole');

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->testUser->id, $result->id);
    }

    /** @test */
    public function batch_operations_return_self()
    {
        $result1 = $this->testUser->givePermissionsToBatch(['edit-articles', 'edit-news']);
        $this->assertInstanceOf(User::class, $result1);

        $result2 = $this->testUser->revokePermissionsToBatch(['edit-articles']);
        $this->assertInstanceOf(User::class, $result2);
    }

    /** @test */
    public function can_chain_permission_operations()
    {
        $this->testUser
            ->givePermissionTo('edit-articles')
            ->givePermissionTo('edit-news')
            ->givePermissionTo('edit-blog');

        $this->refreshTestUser();

        $this->assertTrue($this->testUser->hasPermissionTo('edit-articles'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-news'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-blog'));
    }

    /** @test */
    public function can_chain_role_operations()
    {
        $this->testUser
            ->assignRole('testRole')
            ->assignRole('testRole2');

        $this->refreshTestUser();

        $this->assertTrue($this->testUser->hasRole('testRole'));
        $this->assertTrue($this->testUser->hasRole('testRole2'));
    }

    /** @test */
    public function can_chain_mixed_operations()
    {
        $this->testUser
            ->givePermissionTo('edit-articles')
            ->assignRole('testRole')
            ->givePermissionTo('edit-news')
            ->assignRole('testRole2');

        $this->refreshTestUser();

        $this->assertTrue($this->testUser->hasPermissionTo('edit-articles'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-news'));
        $this->assertTrue($this->testUser->hasRole('testRole'));
        $this->assertTrue($this->testUser->hasRole('testRole2'));
    }

    /** @test */
    public function can_chain_with_batch_operations()
    {
        $this->testUser
            ->givePermissionsToBatch(['edit-articles', 'edit-news'])
            ->assignRole('testRole')
            ->givePermissionTo('edit-blog');

        $this->refreshTestUser();

        $this->assertTrue($this->testUser->hasPermissionTo('edit-articles'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-news'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-blog'));
        $this->assertTrue($this->testUser->hasRole('testRole'));
    }

    /** @test */
    public function can_chain_with_sync_operations()
    {
        $this->testUser
            ->givePermissionTo('edit-articles')
            ->syncPermissions('edit-news', 'edit-blog')
            ->assignRole('testRole');

        $this->refreshTestUser();

        // syncPermissions should replace, not add
        $this->assertFalse($this->testUser->hasDirectPermission('edit-articles'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-news'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-blog'));
        $this->assertTrue($this->testUser->hasRole('testRole'));
    }

    /** @test */
    public function can_chain_revoke_operations()
    {
        $this->testUser->givePermissionTo(['edit-articles', 'edit-news', 'edit-blog']);
        $this->refreshTestUser();

        $this->testUser
            ->revokePermissionTo('edit-articles')
            ->revokePermissionTo('edit-news');

        $this->refreshTestUser();

        $this->assertFalse($this->testUser->hasPermissionTo('edit-articles'));
        $this->assertFalse($this->testUser->hasPermissionTo('edit-news'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-blog'));
    }

    /** @test */
    public function can_chain_with_save()
    {
        $this->testUser->email = 'updated@test.com';

        $this->testUser
            ->givePermissionTo('edit-articles')
            ->assignRole('testRole')
            ->save();

        $freshUser = User::find($this->testUser->id);

        $this->assertEquals('updated@test.com', $freshUser->email);
        $this->assertTrue($freshUser->hasPermissionTo('edit-articles'));
        $this->assertTrue($freshUser->hasRole('testRole'));
    }

    /** @test */
    public function long_chain_of_operations()
    {
        $result = $this->testUser
            ->givePermissionTo('edit-articles')
            ->givePermissionTo('edit-news')
            ->assignRole('testRole')
            ->givePermissionsToBatch(['edit-blog', 'edit-categories'])
            ->assignRole('testRole2')
            ->givePermissionTo('moderate');

        $this->refreshTestUser();

        $this->assertInstanceOf(User::class, $result);
        $this->assertTrue($this->testUser->hasPermissionTo('edit-articles'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-news'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-blog'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-categories'));
        $this->assertTrue($this->testUser->hasPermissionTo('moderate'));
        $this->assertTrue($this->testUser->hasRole('testRole'));
        $this->assertTrue($this->testUser->hasRole('testRole2'));
    }

    /** @test */
    public function fluent_interface_works_with_role_model()
    {
        $this->testUserRole
            ->givePermissionTo('edit-articles')
            ->givePermissionTo('edit-news');

        $this->testUserRole = $this->testUserRole->fresh();

        $this->assertTrue($this->testUserRole->hasPermissionTo('edit-articles'));
        $this->assertTrue($this->testUserRole->hasPermissionTo('edit-news'));
    }

    /** @test */
    public function fluent_interface_works_in_controllers()
    {
        // Simulating a typical controller action pattern
        $user = User::create(['email' => 'controller@test.com']);

        $user->assignRole('testRole')
             ->givePermissionTo('edit-articles')
             ->save();

        $freshUser = User::find($user->id);

        $this->assertTrue($freshUser->hasRole('testRole'));
        $this->assertTrue($freshUser->hasPermissionTo('edit-articles'));
    }

    /** @test */
    public function fluent_interface_works_in_seeders()
    {
        // Simulating a typical seeder pattern
        $users = [];

        for ($i = 1; $i <= 3; $i++) {
            $users[] = User::create(['email' => "seeder{$i}@test.com"])
                ->assignRole('testRole')
                ->givePermissionsToBatch(['edit-articles', 'edit-news']);
        }

        foreach ($users as $user) {
            $fresh = User::find($user->id);
            $this->assertTrue($fresh->hasRole('testRole'));
            $this->assertTrue($fresh->hasPermissionTo('edit-articles'));
        }
    }

    /** @test */
    public function fluent_interface_preserves_model_state()
    {
        $this->testUser->email = 'before@test.com';

        $result = $this->testUser
            ->givePermissionTo('edit-articles')
            ->assignRole('testRole');

        // Email should be preserved through chain
        $this->assertEquals('before@test.com', $result->email);
    }

    /** @test */
    public function can_continue_chaining_after_conditional()
    {
        $shouldAssignRole = true;

        $query = $this->testUser->givePermissionTo('edit-articles');

        if ($shouldAssignRole) {
            $query = $query->assignRole('testRole');
        }

        $query->givePermissionTo('edit-news');

        $this->refreshTestUser();

        $this->assertTrue($this->testUser->hasPermissionTo('edit-articles'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-news'));
        $this->assertTrue($this->testUser->hasRole('testRole'));
    }

    /** @test */
    public function fluent_interface_works_with_multiple_variadic_arguments()
    {
        $this->testUser
            ->givePermissionTo('edit-articles', 'edit-news', 'edit-blog')
            ->assignRole('testRole', 'testRole2');

        $this->refreshTestUser();

        $this->assertEquals(3, $this->testUser->getDirectPermissionsCount());
        $this->assertEquals(2, $this->testUser->getRolesCount());
    }
}
