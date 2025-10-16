<?php

namespace Maklad\Permission\Test;

use Maklad\Permission\Traits\HasPermissionsDebug;
use Illuminate\Support\Facades\Log;

// Create a test user model that uses the debug trait
class DebugUser extends User
{
    use HasPermissionsDebug;
}

class DebugTraitTest extends TestCase
{
    protected DebugUser $debugUser;

    public function setUp(): void
    {
        parent::setUp();
        $this->debugUser = DebugUser::create(['email' => 'debug@test.com']);
    }

    /** @test */
    public function it_can_debug_permissions()
    {
        $this->debugUser->givePermissionTo('edit-articles', 'edit-news');
        $this->testUserRole->givePermissionTo('publish', 'moderate');
        $this->debugUser->assignRole('testRole');
        $this->debugUser = $this->debugUser->fresh();

        $debug = $this->debugUser->debugPermissions();

        $this->assertIsArray($debug);
        $this->assertArrayHasKey('direct_permissions', $debug);
        $this->assertArrayHasKey('direct_permissions_count', $debug);
        $this->assertArrayHasKey('roles', $debug);
        $this->assertArrayHasKey('roles_count', $debug);
        $this->assertArrayHasKey('permissions_via_roles', $debug);
        $this->assertArrayHasKey('all_permissions', $debug);
        $this->assertArrayHasKey('total_permissions_count', $debug);
        $this->assertArrayHasKey('guard', $debug);

        $this->assertEquals(2, $debug['direct_permissions_count']);
        $this->assertEquals(1, $debug['roles_count']);
        $this->assertEquals(4, $debug['total_permissions_count']);
        $this->assertEquals('web', $debug['guard']);
    }

    /** @test */
    public function it_can_explain_permission_granted_directly()
    {
        $this->debugUser->givePermissionTo('edit-articles');
        $this->debugUser = $this->debugUser->fresh();

        $explanation = $this->debugUser->explainPermission('edit-articles');

        $this->assertIsArray($explanation);
        $this->assertEquals('edit-articles', $explanation['permission']);
        $this->assertTrue($explanation['has_permission']);
        $this->assertTrue($explanation['has_directly']);
        $this->assertFalse($explanation['has_via_role']);
        $this->assertEmpty($explanation['roles_granting_permission']);
    }

    /** @test */
    public function it_can_explain_permission_granted_via_role()
    {
        $this->testUserRole->givePermissionTo('publish');
        $this->debugUser->assignRole('testRole');
        $this->debugUser = $this->debugUser->fresh();

        $explanation = $this->debugUser->explainPermission('publish');

        $this->assertTrue($explanation['has_permission']);
        $this->assertFalse($explanation['has_directly']);
        $this->assertTrue($explanation['has_via_role']);
        $this->assertContains('testRole', $explanation['roles_granting_permission']);
    }

    /** @test */
    public function it_can_explain_permission_not_granted()
    {
        $explanation = $this->debugUser->explainPermission('non-existent');

        $this->assertFalse($explanation['has_permission']);
        $this->assertFalse($explanation['has_directly']);
        $this->assertFalse($explanation['has_via_role']);
    }

    /** @test */
    public function it_can_explain_permission_granted_via_multiple_roles()
    {
        $this->testUserRole->givePermissionTo('edit-articles');
        $role2 = app(config('permission.models.role'))->findByName('testRole2');
        $role2->givePermissionTo('edit-articles');

        $this->debugUser->assignRole('testRole', 'testRole2');
        $this->debugUser = $this->debugUser->fresh();

        $explanation = $this->debugUser->explainPermission('edit-articles');

        $this->assertTrue($explanation['has_permission']);
        $this->assertTrue($explanation['has_via_role']);
        $this->assertContains('testRole', $explanation['roles_granting_permission']);
        $this->assertContains('testRole2', $explanation['roles_granting_permission']);
    }

    /** @test */
    public function it_can_get_permissions_summary()
    {
        $this->debugUser->givePermissionTo('edit-articles');
        $this->debugUser->assignRole('testRole');
        $this->debugUser = $this->debugUser->fresh();

        $summary = $this->debugUser->getPermissionsSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('user_id', $summary);
        $this->assertArrayHasKey('user_identifier', $summary);
        $this->assertArrayHasKey('direct_permissions', $summary);
        $this->assertArrayHasKey('roles', $summary);
        $this->assertArrayHasKey('total_permissions', $summary);
        $this->assertArrayHasKey('has_any_permissions', $summary);
        $this->assertArrayHasKey('guard', $summary);

        $this->assertEquals($this->debugUser->id, $summary['user_id']);
        $this->assertEquals('debug@test.com', $summary['user_identifier']);
        $this->assertTrue($summary['has_any_permissions']);
    }

    /** @test */
    public function it_can_check_multiple_permissions()
    {
        $this->debugUser->givePermissionTo('edit-articles');
        $this->testUserRole->givePermissionTo('publish');
        $this->debugUser->assignRole('testRole');
        $this->debugUser = $this->debugUser->fresh();

        $results = $this->debugUser->checkMultiplePermissions([
            'edit-articles',
            'publish',
            'non-existent',
        ]);

        $this->assertIsArray($results);
        $this->assertCount(3, $results);

        $this->assertTrue($results['edit-articles']['granted']);
        $this->assertTrue($results['edit-articles']['direct']);

        $this->assertTrue($results['publish']['granted']);
        $this->assertFalse($results['publish']['direct']);

        $this->assertFalse($results['non-existent']['granted']);
        $this->assertFalse($results['non-existent']['direct']);
    }

    /** @test */
    public function it_can_get_permissions_by_role()
    {
        $this->debugUser->givePermissionTo('edit-articles', 'edit-news');
        $this->testUserRole->givePermissionTo('publish');
        $role2 = app(config('permission.models.role'))->findByName('testRole2');
        $role2->givePermissionTo('moderate');
        $this->debugUser->assignRole('testRole', 'testRole2');
        $this->debugUser = $this->debugUser->fresh();

        $grouped = $this->debugUser->getPermissionsByRole();

        $this->assertIsArray($grouped);
        $this->assertArrayHasKey('direct', $grouped);
        $this->assertArrayHasKey('testRole', $grouped);
        $this->assertArrayHasKey('testRole2', $grouped);

        $this->assertContains('edit-articles', $grouped['direct']);
        $this->assertContains('edit-news', $grouped['direct']);
        $this->assertContains('publish', $grouped['testRole']);
        $this->assertContains('moderate', $grouped['testRole2']);
    }

    /** @test */
    public function it_can_find_permission_conflicts()
    {
        // Assign same permission both directly and via role
        $this->debugUser->givePermissionTo('edit-articles');
        $this->testUserRole->givePermissionTo('edit-articles', 'publish');
        $this->debugUser->assignRole('testRole');
        $this->debugUser->givePermissionTo('edit-news');
        $this->debugUser = $this->debugUser->fresh();

        $conflicts = $this->debugUser->findPermissionConflicts();

        $this->assertIsArray($conflicts);
        $this->assertArrayHasKey('duplicates', $conflicts);
        $this->assertArrayHasKey('direct_only', $conflicts);
        $this->assertArrayHasKey('role_only', $conflicts);

        $this->assertContains('edit-articles', $conflicts['duplicates']);
        $this->assertContains('edit-news', $conflicts['direct_only']);
        $this->assertContains('publish', $conflicts['role_only']);
    }

    /** @test */
    public function it_can_export_permissions_json()
    {
        $this->debugUser->givePermissionTo('edit-articles');
        $this->debugUser->assignRole('testRole');
        $this->debugUser = $this->debugUser->fresh();

        $json = $this->debugUser->exportPermissionsJson();

        $this->assertIsString($json);
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('debug', $data);
        $this->assertArrayHasKey('conflicts', $data);
        $this->assertArrayHasKey('by_role', $data);
    }

    /** @test */
    public function it_can_log_permissions()
    {
        Log::shouldReceive('log')
            ->once()
            ->with('info', 'Test log message', \Mockery::type('array'));

        $this->debugUser->logPermissions('info', 'Test log message');
    }

    /** @test */
    public function it_logs_with_default_message_when_none_provided()
    {
        Log::shouldReceive('log')
            ->once()
            ->with('info', 'User permission state', \Mockery::type('array'));

        $this->debugUser->logPermissions();
    }

    /** @test */
    public function debug_methods_work_with_empty_user()
    {
        $emptyUser = DebugUser::create(['email' => 'empty@test.com']);

        $debug = $emptyUser->debugPermissions();
        $this->assertEquals(0, $debug['total_permissions_count']);
        $this->assertEquals(0, $debug['roles_count']);

        $summary = $emptyUser->getPermissionsSummary();
        $this->assertFalse($summary['has_any_permissions']);

        $conflicts = $emptyUser->findPermissionConflicts();
        $this->assertEmpty($conflicts['duplicates']);
        $this->assertEmpty($conflicts['direct_only']);
        $this->assertEmpty($conflicts['role_only']);
    }

    /** @test */
    public function permissions_summary_uses_email_as_identifier()
    {
        $summary = $this->debugUser->getPermissionsSummary();

        $this->assertEquals('debug@test.com', $summary['user_identifier']);
    }

    /** @test */
    public function permissions_summary_falls_back_to_name_if_no_email()
    {
        $user = new class extends DebugUser {
            protected $fillable = ['name', 'email'];
        };

        $testUser = $user::create(['name' => 'John Doe']);

        $summary = $testUser->getPermissionsSummary();

        // Should fallback to name when email is null
        $this->assertContains($summary['user_identifier'], ['John Doe', 'unknown']);
    }

    /** @test */
    public function export_json_is_valid_and_pretty_printed()
    {
        $this->debugUser->givePermissionTo('edit-articles');
        $this->debugUser = $this->debugUser->fresh();

        $json = $this->debugUser->exportPermissionsJson();

        // Should be valid JSON
        $this->assertJson($json);

        // Should be pretty printed (contains newlines)
        $this->assertStringContainsString("\n", $json);
    }

    /** @test */
    public function debug_trait_works_alongside_regular_permission_methods()
    {
        $this->debugUser->givePermissionTo('edit-articles');
        $this->debugUser = $this->debugUser->fresh();

        // Regular permission methods should still work
        $this->assertTrue($this->debugUser->hasPermissionTo('edit-articles'));
        $this->assertFalse($this->debugUser->hasPermissionTo('non-existent'));

        // Debug methods should also work
        $debug = $this->debugUser->debugPermissions();
        $this->assertEquals(1, $debug['total_permissions_count']);
    }
}
