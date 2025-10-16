<?php

namespace Maklad\Permission\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MongoDB\Laravel\Eloquent\Model;
use Maklad\Permission\Contracts\PermissionInterface as Permission;

/**
 * Event fired when a permission is revoked from a model
 */
class PermissionRevoked
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Model $model,
        public readonly Permission $permission
    ) {
    }
}
