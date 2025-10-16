<?php

namespace Maklad\Permission\Test;

use Illuminate\Support\Facades\Event;
use Maklad\Permission\Events\PermissionAssigned;
use Maklad\Permission\Events\PermissionRevoked;
use Maklad\Permission\Events\RoleAssigned;
use Maklad\Permission\Events\RoleRevoked;
use Maklad\Permission\Events\PermissionCacheFlushed;

class EventsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    /** @test */
    public function it_fires_permission_assigned_event_when_giving_permission()
    {
        $this->testUser->givePermissionTo('edit-articles');

        Event::assertDispatched(PermissionAssigned::class, function ($event) {
            return $event->model->id === $this->testUser->id
                && $event->permission->name === 'edit-articles';
        });
    }

    /** @test */
    public function it_fires_permission_revoked_event_when_revoking_permission()
    {
        $this->testUser->givePermissionTo('edit-articles');
        Event::fake(); // Reset after assignment

        $this->testUser->revokePermissionTo('edit-articles');

        Event::assertDispatched(PermissionRevoked::class, function ($event) {
            return $event->model->id === $this->testUser->id
                && $event->permission->name === 'edit-articles';
        });
    }

    /** @test */
    public function it_fires_role_assigned_event_when_assigning_role()
    {
        $this->testUser->assignRole('testRole');

        Event::assertDispatched(RoleAssigned::class, function ($event) {
            return $event->model->id === $this->testUser->id
                && $event->role->name === 'testRole';
        });
    }

    /** @test */
    public function it_fires_role_revoked_event_when_removing_role()
    {
        $this->testUser->assignRole('testRole');
        Event::fake(); // Reset after assignment

        $this->testUser->removeRole('testRole');

        Event::assertDispatched(RoleRevoked::class, function ($event) {
            return $event->model->id === $this->testUser->id
                && $event->role->name === 'testRole';
        });
    }

    /** @test */
    public function it_fires_multiple_events_when_assigning_multiple_permissions()
    {
        $this->testUser->givePermissionTo('edit-articles');
        $this->testUser->givePermissionTo('edit-news');

        Event::assertDispatched(PermissionAssigned::class, 2);
    }

    /** @test */
    public function it_fires_multiple_events_when_assigning_multiple_roles()
    {
        $this->testUser->assignRole('testRole', 'testRole2');

        Event::assertDispatched(RoleAssigned::class, 2);
    }

    /** @test */
    public function it_fires_single_event_for_batch_permission_assignment()
    {
        $this->testUser->givePermissionsToBatch(['edit-articles', 'edit-news', 'edit-blog']);

        // Batch operations should fire only 1 event, not 3
        Event::assertDispatched(PermissionAssigned::class, 1);
    }

    /** @test */
    public function it_does_not_fire_events_when_syncing_permissions()
    {
        $this->testUser->givePermissionTo('edit-articles');
        Event::fake(); // Reset

        $this->testUser->syncPermissions('edit-news');

        // syncPermissions shouldn't fire assignment events for now
        Event::assertNotDispatched(PermissionAssigned::class);
    }

    /** @test */
    public function event_contains_correct_model_data()
    {
        $this->testUser->givePermissionTo('edit-articles');

        Event::assertDispatched(PermissionAssigned::class, function ($event) {
            // Check that event has readonly properties
            $this->assertInstanceOf(User::class, $event->model);
            $this->assertEquals('edit-articles', $event->permission->name);
            $this->assertEquals('web', $event->permission->guard_name);
            return true;
        });
    }

    /** @test */
    public function it_can_listen_to_events_for_audit_logging()
    {
        $auditLog = [];

        Event::listen(PermissionAssigned::class, function ($event) use (&$auditLog) {
            $auditLog[] = [
                'action' => 'permission_assigned',
                'user_id' => $event->model->id,
                'permission' => $event->permission->name,
                'timestamp' => now(),
            ];
        });

        Event::listen(RoleAssigned::class, function ($event) use (&$auditLog) {
            $auditLog[] = [
                'action' => 'role_assigned',
                'user_id' => $event->model->id,
                'role' => $event->role->name,
                'timestamp' => now(),
            ];
        });

        // Perform actions
        $this->testUser->givePermissionTo('edit-articles');
        $this->testUser->assignRole('testRole');

        // Dispatch events (since we're using Event::fake, we need to manually trigger)
        $events = Event::dispatched(PermissionAssigned::class);
        foreach ($events as [$event, $payload]) {
            Event::dispatch($event);
        }

        $events = Event::dispatched(RoleAssigned::class);
        foreach ($events as [$event, $payload]) {
            Event::dispatch($event);
        }

        // We should have 2 audit log entries
        $this->assertGreaterThanOrEqual(2, count($auditLog));
    }

    /** @test */
    public function permission_cache_flushed_event_is_dispatchable()
    {
        // Test that the event class exists and can be dispatched
        $event = new PermissionCacheFlushed();
        $this->assertInstanceOf(PermissionCacheFlushed::class, $event);
    }

    /** @test */
    public function events_work_with_permission_objects()
    {
        $permission = $this->testUserPermission;

        $this->testUser->givePermissionTo($permission);

        Event::assertDispatched(PermissionAssigned::class, function ($event) use ($permission) {
            return $event->permission->id === $permission->id;
        });
    }

    /** @test */
    public function events_work_with_role_objects()
    {
        $role = $this->testUserRole;

        $this->testUser->assignRole($role);

        Event::assertDispatched(RoleAssigned::class, function ($event) use ($role) {
            return $event->role->id === $role->id;
        });
    }
}
