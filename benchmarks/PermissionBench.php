<?php

namespace Maklad\Permission\Benchmarks;

/**
 * Permission Performance Benchmarks
 *
 * Run with: vendor/bin/phpbench run benchmarks/PermissionBench.php --report=default
 */
class PermissionBench
{
    private $user;
    private $permissions = [];

    public function setUp(): void
    {
        // Setup would happen here in real benchmark
        // This is a template showing what to benchmark
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchSinglePermissionCheck(): void
    {
        // $this->user->hasPermissionTo('edit-articles');
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchMultiplePermissionChecks(): void
    {
        // for ($i = 0; $i < 10; $i++) {
        //     $this->user->hasPermissionTo("permission-{$i}");
        // }
    }

    /**
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchBatchPermissionAssignment(): void
    {
        // $this->user->givePermissionsToBatch($this->permissions);
    }

    /**
     * @Revs(100)
     * @Iterations(5)
     */
    public function benchIndividualPermissionAssignment(): void
    {
        // foreach ($this->permissions as $permission) {
        //     $this->user->givePermissionTo($permission);
        // }
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchGetAllPermissions(): void
    {
        // $this->user->getAllPermissions();
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     */
    public function benchGetPermissionsViaRoles(): void
    {
        // $this->user->getPermissionsViaRoles();
    }
}
