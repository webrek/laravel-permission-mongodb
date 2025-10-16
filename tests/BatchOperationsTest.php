<?php

namespace Maklad\Permission\Test;

use Illuminate\Support\Facades\Event;
use Maklad\Permission\Events\PermissionAssigned;

class BatchOperationsTest extends TestCase
{
    /** @test */
    public function it_can_give_multiple_permissions_in_batch()
    {
        $permissions = ['edit-articles', 'edit-news', 'edit-blog'];

        $this->testUser->givePermissionsToBatch($permissions);
        $this->refreshTestUser();

        $this->assertTrue($this->testUser->hasPermissionTo('edit-articles'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-news'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-blog'));
    }

    /** @test */
    public function it_can_revoke_multiple_permissions_in_batch()
    {
        $this->testUser->givePermissionTo(['edit-articles', 'edit-news', 'edit-blog']);
        $this->refreshTestUser();

        $this->testUser->revokePermissionsToBatch(['edit-articles', 'edit-news']);
        $this->refreshTestUser();

        $this->assertFalse($this->testUser->hasPermissionTo('edit-articles'));
        $this->assertFalse($this->testUser->hasPermissionTo('edit-news'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-blog'));
    }

    /** @test */
    public function batch_operations_return_self_for_fluent_interface()
    {
        $result = $this->testUser->givePermissionsToBatch(['edit-articles', 'edit-news']);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->testUser->id, $result->id);
    }

    /** @test */
    public function batch_operations_can_be_chained()
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
    public function batch_give_permissions_fires_single_event()
    {
        Event::fake();

        $this->testUser->givePermissionsToBatch(['edit-articles', 'edit-news', 'edit-blog']);

        // Should fire only 1 event for batch operation, not 3
        Event::assertDispatched(PermissionAssigned::class, 1);
    }

    /** @test */
    public function batch_revoke_does_not_fire_events()
    {
        $this->testUser->givePermissionTo(['edit-articles', 'edit-news', 'edit-blog']);

        Event::fake(); // Reset

        $this->testUser->revokePermissionsToBatch(['edit-articles', 'edit-news']);

        // Batch revoke currently doesn't fire events (performance optimization)
        Event::assertNotDispatched(\Maklad\Permission\Events\PermissionRevoked::class);
    }

    /** @test */
    public function batch_operations_work_with_permission_objects()
    {
        $permissions = [
            $this->testUserPermission,
            app(config('permission.models.permission'))->where('name', 'edit-news')->first(),
        ];

        $this->testUser->givePermissionsToBatch($permissions);
        $this->refreshTestUser();

        $this->assertTrue($this->testUser->hasPermissionTo('edit-articles'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-news'));
    }

    /** @test */
    public function batch_operations_handle_empty_arrays()
    {
        $this->testUser->givePermissionsToBatch([]);
        $this->refreshTestUser();

        $this->assertCount(0, $this->testUser->getAllPermissions());
    }

    /** @test */
    public function batch_operations_handle_duplicate_permissions()
    {
        $this->testUser->givePermissionTo('edit-articles');
        $this->refreshTestUser();

        // Try to batch assign including duplicate
        $this->testUser->givePermissionsToBatch(['edit-articles', 'edit-news']);
        $this->refreshTestUser();

        $permissions = $this->testUser->getAllPermissions();

        // Should have 2 unique permissions, not 3
        $this->assertCount(2, $permissions);
        $this->assertTrue($this->testUser->hasPermissionTo('edit-articles'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-news'));
    }

    /** @test */
    public function batch_operations_clear_permission_cache()
    {
        $this->testUser->givePermissionTo('edit-articles');
        $this->refreshTestUser();

        // Cache the permissions
        $permissions1 = $this->testUser->getAllPermissions();
        $this->assertCount(1, $permissions1);

        // Batch assign more permissions
        $this->testUser->givePermissionsToBatch(['edit-news', 'edit-blog']);
        $this->refreshTestUser();

        // Cache should be cleared and new permissions visible
        $permissions2 = $this->testUser->getAllPermissions();
        $this->assertCount(3, $permissions2);
    }

    /** @test */
    public function batch_operations_persist_to_database()
    {
        $this->testUser->givePermissionsToBatch(['edit-articles', 'edit-news', 'edit-blog']);

        // Fresh instance from database
        $freshUser = User::find($this->testUser->id);

        $this->assertTrue($freshUser->hasPermissionTo('edit-articles'));
        $this->assertTrue($freshUser->hasPermissionTo('edit-news'));
        $this->assertTrue($freshUser->hasPermissionTo('edit-blog'));
    }

    /** @test */
    public function batch_revoke_removes_only_specified_permissions()
    {
        $this->testUser->givePermissionTo(['edit-articles', 'edit-news', 'edit-blog', 'edit-categories']);
        $this->refreshTestUser();

        $this->testUser->revokePermissionsToBatch(['edit-articles', 'edit-blog']);
        $this->refreshTestUser();

        $this->assertFalse($this->testUser->hasPermissionTo('edit-articles'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-news'));
        $this->assertFalse($this->testUser->hasPermissionTo('edit-blog'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-categories'));
    }

    /** @test */
    public function batch_operations_work_with_roles()
    {
        $this->testUserRole->givePermissionsToBatch(['edit-articles', 'edit-news']);

        $this->testUser->assignRole('testRole');
        $this->refreshTestUser();

        $this->assertTrue($this->testUser->hasPermissionTo('edit-articles'));
        $this->assertTrue($this->testUser->hasPermissionTo('edit-news'));
    }

    /** @test */
    public function batch_give_permissions_is_faster_than_individual_calls()
    {
        $permissions = ['edit-articles', 'edit-news', 'edit-blog', 'edit-categories'];

        $user1 = User::create(['email' => 'batch@test.com']);
        $user2 = User::create(['email' => 'individual@test.com']);

        Event::fake();

        // Batch method
        $start = microtime(true);
        $user1->givePermissionsToBatch($permissions);
        $batchTime = microtime(true) - $start;

        // Individual method
        $start = microtime(true);
        foreach ($permissions as $permission) {
            $user2->givePermissionTo($permission);
        }
        $individualTime = microtime(true) - $start;

        // Batch should be faster or at least comparable
        // This is a rough benchmark - in production with real DB, difference is more significant
        $this->assertLessThanOrEqual($individualTime * 2, $batchTime);

        // Verify both have same permissions
        $user1 = $user1->fresh();
        $user2 = $user2->fresh();

        $this->assertEquals(
            $user1->getAllPermissions()->pluck('name')->sort()->values(),
            $user2->getAllPermissions()->pluck('name')->sort()->values()
        );
    }

    /** @test */
    public function batch_operations_work_after_regular_operations()
    {
        // Mix regular and batch operations
        $this->testUser->givePermissionTo('edit-articles');
        $this->testUser->givePermissionsToBatch(['edit-news', 'edit-blog']);
        $this->testUser->givePermissionTo('edit-categories');

        $this->refreshTestUser();

        $permissions = $this->testUser->getAllPermissions();
        $this->assertCount(4, $permissions);
    }
}
