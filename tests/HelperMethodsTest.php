<?php

namespace Maklad\Permission\Test;

class HelperMethodsTest extends TestCase
{
    // Role Helper Methods

    /** @test */
    public function it_can_get_role_ids()
    {
        $this->testUser->assignRole('testRole', 'testRole2');
        $this->refreshTestUser();

        $roleIds = $this->testUser->getRoleIds();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $roleIds);
        $this->assertCount(2, $roleIds);
    }

    /** @test */
    public function it_can_check_exact_roles()
    {
        $this->testUser->assignRole('testRole');
        $this->refreshTestUser();

        // Has exactly this role
        $this->assertTrue($this->testUser->hasExactRoles('testRole'));

        // Doesn't have exactly these roles (missing testRole2)
        $this->assertFalse($this->testUser->hasExactRoles('testRole', 'testRole2'));

        // Add second role
        $this->testUser->assignRole('testRole2');
        $this->refreshTestUser();

        // Now has exactly these roles
        $this->assertTrue($this->testUser->hasExactRoles('testRole', 'testRole2'));

        // Order shouldn't matter
        $this->assertTrue($this->testUser->hasExactRoles('testRole2', 'testRole'));

        // Wrong set of roles
        $this->assertFalse($this->testUser->hasExactRoles('testRole'));
    }

    /** @test */
    public function it_can_get_roles_count()
    {
        $this->assertEquals(0, $this->testUser->getRolesCount());

        $this->testUser->assignRole('testRole');
        $this->refreshTestUser();
        $this->assertEquals(1, $this->testUser->getRolesCount());

        $this->testUser->assignRole('testRole2');
        $this->refreshTestUser();
        $this->assertEquals(2, $this->testUser->getRolesCount());
    }

    /** @test */
    public function it_can_check_if_has_no_roles()
    {
        $this->assertTrue($this->testUser->hasNoRoles());

        $this->testUser->assignRole('testRole');
        $this->refreshTestUser();

        $this->assertFalse($this->testUser->hasNoRoles());
    }

    // Permission Helper Methods

    /** @test */
    public function it_can_get_permission_ids()
    {
        $this->testUser->givePermissionTo('edit-articles', 'edit-news');
        $this->refreshTestUser();

        $permissionIds = $this->testUser->getPermissionIds();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $permissionIds);
        $this->assertCount(2, $permissionIds);
    }

    /** @test */
    public function it_can_get_total_permissions_count()
    {
        $this->assertEquals(0, $this->testUser->getPermissionsCount());

        // Direct permission
        $this->testUser->givePermissionTo('edit-articles');
        $this->refreshTestUser();
        $this->assertEquals(1, $this->testUser->getPermissionsCount());

        // Permission via role
        $this->testUserRole->givePermissionTo('edit-news');
        $this->testUser->assignRole('testRole');
        $this->refreshTestUser();
        $this->assertEquals(2, $this->testUser->getPermissionsCount());
    }

    /** @test */
    public function it_can_get_direct_permissions_count()
    {
        $this->assertEquals(0, $this->testUser->getDirectPermissionsCount());

        // Direct permission
        $this->testUser->givePermissionTo('edit-articles');
        $this->refreshTestUser();
        $this->assertEquals(1, $this->testUser->getDirectPermissionsCount());

        // Permission via role should NOT count
        $this->testUserRole->givePermissionTo('edit-news');
        $this->testUser->assignRole('testRole');
        $this->refreshTestUser();
        $this->assertEquals(1, $this->testUser->getDirectPermissionsCount());

        // Another direct permission
        $this->testUser->givePermissionTo('edit-blog');
        $this->refreshTestUser();
        $this->assertEquals(2, $this->testUser->getDirectPermissionsCount());
    }

    /** @test */
    public function it_can_check_if_has_no_permissions()
    {
        $this->assertTrue($this->testUser->hasNoPermissions());

        $this->testUser->givePermissionTo('edit-articles');
        $this->refreshTestUser();

        $this->assertFalse($this->testUser->hasNoPermissions());
    }

    /** @test */
    public function it_can_check_if_has_any_direct_permissions()
    {
        $this->assertFalse($this->testUser->hasAnyDirectPermissions());

        // Permission via role should NOT count as direct
        $this->testUserRole->givePermissionTo('edit-news');
        $this->testUser->assignRole('testRole');
        $this->refreshTestUser();
        $this->assertFalse($this->testUser->hasAnyDirectPermissions());

        // Direct permission should count
        $this->testUser->givePermissionTo('edit-articles');
        $this->refreshTestUser();
        $this->assertTrue($this->testUser->hasAnyDirectPermissions());
    }

    /** @test */
    public function it_can_check_if_permission_exists()
    {
        $this->assertTrue($this->testUser->permissionExists('edit-articles'));
        $this->assertTrue($this->testUser->permissionExists('edit-news'));
        $this->assertFalse($this->testUser->permissionExists('non-existent-permission'));
    }

    /** @test */
    public function it_can_check_if_permission_exists_for_specific_guard()
    {
        $this->assertTrue($this->testUser->permissionExists('edit-articles', 'web'));
        $this->assertFalse($this->testUser->permissionExists('edit-articles', 'admin'));
        $this->assertTrue($this->testAdmin->permissionExists('admin-permission', 'admin'));
    }

    // Real-world use cases

    /** @test */
    public function it_can_generate_dashboard_stats()
    {
        $this->testUser->givePermissionTo('edit-articles', 'edit-news');
        $this->testUserRole->givePermissionTo('publish', 'moderate');
        $this->testUser->assignRole('testRole');
        $this->refreshTestUser();

        $stats = [
            'roles' => $this->testUser->getRolesCount(),
            'direct_permissions' => $this->testUser->getDirectPermissionsCount(),
            'total_permissions' => $this->testUser->getPermissionsCount(),
            'role_names' => $this->testUser->getRoleNames()->toArray(),
        ];

        $this->assertEquals(1, $stats['roles']);
        $this->assertEquals(2, $stats['direct_permissions']);
        $this->assertEquals(4, $stats['total_permissions']);
        $this->assertContains('testRole', $stats['role_names']);
    }

    /** @test */
    public function it_can_assign_default_role_if_no_roles()
    {
        $user = User::create(['email' => 'new@test.com']);

        if ($user->hasNoRoles()) {
            $user->assignRole('testRole');
        }

        $user = $user->fresh();
        $this->assertFalse($user->hasNoRoles());
        $this->assertTrue($user->hasRole('testRole'));
    }

    /** @test */
    public function it_can_upgrade_user_based_on_permissions()
    {
        $this->testUser->givePermissionTo('edit-articles', 'edit-news');
        $this->refreshTestUser();

        $required = ['edit-articles', 'edit-news'];
        if ($this->testUser->hasAllPermissions(...$required)) {
            $this->testUser->assignRole('testRole');
        }

        $this->refreshTestUser();
        $this->assertTrue($this->testUser->hasRole('testRole'));
    }

    /** @test */
    public function helper_methods_work_with_empty_user()
    {
        $user = User::create(['email' => 'empty@test.com']);

        $this->assertEquals(0, $user->getRolesCount());
        $this->assertEquals(0, $user->getPermissionsCount());
        $this->assertEquals(0, $user->getDirectPermissionsCount());
        $this->assertTrue($user->hasNoRoles());
        $this->assertTrue($user->hasNoPermissions());
        $this->assertFalse($user->hasAnyDirectPermissions());
        $this->assertCount(0, $user->getRoleIds());
        $this->assertCount(0, $user->getPermissionIds());
    }

    /** @test */
    public function permission_exists_handles_exceptions_gracefully()
    {
        // Should not throw exception, just return false
        $exists = $this->testUser->permissionExists('completely-made-up-permission-12345');

        $this->assertFalse($exists);
    }

    /** @test */
    public function exact_roles_check_works_with_arrays()
    {
        $this->testUser->assignRole(['testRole', 'testRole2']);
        $this->refreshTestUser();

        $this->assertTrue($this->testUser->hasExactRoles(['testRole', 'testRole2']));
        $this->assertFalse($this->testUser->hasExactRoles(['testRole']));
    }

    /** @test */
    public function exact_roles_check_works_with_role_objects()
    {
        $this->testUser->assignRole($this->testUserRole);
        $this->refreshTestUser();

        $this->assertTrue($this->testUser->hasExactRoles($this->testUserRole));
    }

    /** @test */
    public function permissions_count_includes_role_permissions()
    {
        // 2 direct permissions
        $this->testUser->givePermissionTo('edit-articles', 'edit-news');

        // 2 permissions via role
        $this->testUserRole->givePermissionTo('publish', 'moderate');
        $this->testUser->assignRole('testRole');

        $this->refreshTestUser();

        $this->assertEquals(2, $this->testUser->getDirectPermissionsCount());
        $this->assertEquals(4, $this->testUser->getPermissionsCount());
    }

    /** @test */
    public function permissions_count_handles_duplicates_correctly()
    {
        // Same permission both directly and via role
        $this->testUser->givePermissionTo('edit-articles');
        $this->testUserRole->givePermissionTo('edit-articles');
        $this->testUser->assignRole('testRole');

        $this->refreshTestUser();

        $this->assertEquals(1, $this->testUser->getDirectPermissionsCount());
        // Should count unique permissions only
        $this->assertEquals(1, $this->testUser->getPermissionsCount());
    }
}
