<?php

namespace Maklad\Permission\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when permission cache is flushed
 */
class PermissionCacheFlushed
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $reason = 'manual'
    ) {
    }
}
