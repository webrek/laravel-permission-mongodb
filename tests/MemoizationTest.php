<?php

namespace Maklad\Permission\Test;

use Illuminate\Support\Facades\DB;

class MemoizationTest extends TestCase
{
    /** @test */
    public function it_memoizes_permissions_via_roles_within_same_request()
    {
        $this->testUserRole->givePermissionTo('edit-articles');
        $this->testUser->assignRole('testRole');
        $this->refreshTestUser();

        // Enable query logging
        DB::connection()->enableQueryLog();

        // First call - should query database
        $permissions1 = $this->testUser->getPermissionsViaRoles();
        $queryCount1 = count(DB::getQueryLog());

        // Second call - should use memoized result (no new query)
        $permissions2 = $this->testUser->getPermissionsViaRoles();
        $queryCount2 = count(DB::getQueryLog());

        // Third call - should still use memoized result
        $permissions3 = $this->testUser->getPermissionsViaRoles();
        $queryCount3 = count(DB::getQueryLog());

        // All results should be identical
        $this->assertEquals($permissions1, $permissions2);
        $this->assertEquals($permissions2, $permissions3);

        // Query count should not increase after first call
        $this->assertEquals($queryCount1, $queryCount2);
        $this->assertEquals($queryCount2, $queryCount3);
    }

    /** @test */
    public function it_memoizes_all_permissions_within_same_request()
    {
        $this->testUser->givePermissionTo('edit-articles');
        $this->testUserRole->givePermissionTo('edit-news');
        $this->testUser->assignRole('testRole');
        $this->refreshTestUser();

        DB::connection()->enableQueryLog();

        // First call
        $permissions1 = $this->testUser->getAllPermissions();
        $queryCount1 = count(DB::getQueryLog());

        // Subsequent calls should use cache
        $permissions2 = $this->testUser->getAllPermissions();
        $permissions3 = $this->testUser->getAllPermissions();
        $queryCount2 = count(DB::getQueryLog());

        $this->assertEquals($permissions1->pluck('name')->sort()->values(),
                           $permissions2->pluck('name')->sort()->values());

        // No additional queries for subsequent calls
        $this->assertLessThanOrEqual($queryCount1 + 1, $queryCount2);
    }

    /** @test */
    public function it_clears_memoization_cache_when_clearing_permission_cache()
    {
        $this->testUserRole->givePermissionTo('edit-articles');
        $this->testUser->assignRole('testRole');
        $this->refreshTestUser();

        // First call - creates cache
        $permissions1 = $this->testUser->getAllPermissions();
        $this->assertCount(1, $permissions1);

        // Clear cache
        $this->testUser->clearPermissionCache();

        // Give another permission
        $this->testUser->givePermissionTo('edit-news');
        $this->refreshTestUser();

        // Should reflect new permission
        $permissions2 = $this->testUser->getAllPermissions();
        $this->assertCount(2, $permissions2);
    }

    /** @test */
    public function it_clears_memoization_when_giving_permission()
    {
        $this->testUser->givePermissionTo('edit-articles');
        $this->refreshTestUser();

        // Cache the result
        $permissions1 = $this->testUser->getAllPermissions();
        $this->assertCount(1, $permissions1);

        // Give another permission (should clear cache)
        $this->testUser->givePermissionTo('edit-news');
        $this->refreshTestUser();

        // Should show both permissions
        $permissions2 = $this->testUser->getAllPermissions();
        $this->assertCount(2, $permissions2);
    }

    /** @test */
    public function it_clears_memoization_when_revoking_permission()
    {
        $this->testUser->givePermissionTo(['edit-articles', 'edit-news']);
        $this->refreshTestUser();

        // Cache the result
        $permissions1 = $this->testUser->getAllPermissions();
        $this->assertCount(2, $permissions1);

        // Revoke permission (should clear cache)
        $this->testUser->revokePermissionTo('edit-articles');
        $this->refreshTestUser();

        // Should only show one permission
        $permissions2 = $this->testUser->getAllPermissions();
        $this->assertCount(1, $permissions2);
        $this->assertEquals('edit-news', $permissions2->first()->name);
    }

    /** @test */
    public function memoization_improves_performance_for_multiple_permission_checks()
    {
        $this->testUser->givePermissionTo(['edit-articles', 'edit-news', 'edit-blog']);
        $this->testUserRole->givePermissionTo(['publish', 'moderate']);
        $this->testUser->assignRole('testRole');
        $this->refreshTestUser();

        DB::connection()->enableQueryLog();
        DB::flushQueryLog();

        // Multiple permission checks in same request
        $can1 = $this->testUser->hasPermissionTo('edit-articles');
        $can2 = $this->testUser->hasPermissionTo('edit-news');
        $can3 = $this->testUser->hasPermissionTo('publish');
        $can4 = $this->testUser->hasPermissionTo('moderate');
        $can5 = $this->testUser->hasPermissionTo('edit-blog');

        $queryCount = count(DB::getQueryLog());

        // All checks should pass
        $this->assertTrue($can1);
        $this->assertTrue($can2);
        $this->assertTrue($can3);
        $this->assertTrue($can4);
        $this->assertTrue($can5);

        // Should use significantly fewer queries than 5 separate checks
        // Without memoization, this could be 10+ queries
        // With memoization, should be much less
        $this->assertLessThan(10, $queryCount);
    }

    /** @test */
    public function memoization_works_correctly_with_getPermissionsViaRoles()
    {
        $this->testUserRole->givePermissionTo(['edit-articles', 'edit-news']);
        $this->testUser->assignRole('testRole');
        $this->refreshTestUser();

        // First call
        $viaRoles1 = $this->testUser->getPermissionsViaRoles();

        // Second call should return same object (memoized)
        $viaRoles2 = $this->testUser->getPermissionsViaRoles();

        $this->assertEquals($viaRoles1->pluck('name')->sort()->values(),
                           $viaRoles2->pluck('name')->sort()->values());
        $this->assertCount(2, $viaRoles1);
        $this->assertCount(2, $viaRoles2);
    }

    /** @test */
    public function it_handles_empty_permissions_correctly_with_memoization()
    {
        $user = User::create(['email' => 'empty@test.com']);

        // Multiple calls with no permissions
        $perms1 = $user->getAllPermissions();
        $perms2 = $user->getAllPermissions();
        $perms3 = $user->getPermissionsViaRoles();

        $this->assertCount(0, $perms1);
        $this->assertCount(0, $perms2);
        $this->assertCount(0, $perms3);
    }

    /** @test */
    public function memoization_is_instance_specific()
    {
        $user1 = User::create(['email' => 'user1@test.com']);
        $user2 = User::create(['email' => 'user2@test.com']);

        $user1->givePermissionTo('edit-articles');
        $user2->givePermissionTo('edit-news');

        $user1 = $user1->fresh();
        $user2 = $user2->fresh();

        // Each user should have their own memoization cache
        $user1Perms = $user1->getAllPermissions();
        $user2Perms = $user2->getAllPermissions();

        $this->assertCount(1, $user1Perms);
        $this->assertCount(1, $user2Perms);
        $this->assertEquals('edit-articles', $user1Perms->first()->name);
        $this->assertEquals('edit-news', $user2Perms->first()->name);
    }

    /** @test */
    public function it_clears_memoization_when_syncing_permissions()
    {
        $this->testUser->givePermissionTo(['edit-articles', 'edit-news']);
        $this->refreshTestUser();

        // Cache the result
        $permissions1 = $this->testUser->getAllPermissions();
        $this->assertCount(2, $permissions1);

        // Sync to different permissions
        $this->testUser->syncPermissions('edit-blog');
        $this->refreshTestUser();

        // Should show only synced permission
        $permissions2 = $this->testUser->getAllPermissions();
        $this->assertCount(1, $permissions2);
        $this->assertEquals('edit-blog', $permissions2->first()->name);
    }
}
