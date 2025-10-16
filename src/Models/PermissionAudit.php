<?php

namespace Maklad\Permission\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Permission Audit Log Model
 *
 * Tracks all permission and role changes for audit purposes.
 */
class PermissionAudit extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'permission_audits';

    protected $fillable = [
        'model_type',
        'model_id',
        'action',
        'permission_name',
        'role_name',
        'user_id',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public $timestamps = true;
    const UPDATED_AT = null; // Audit logs are write-once

    public static function log(string $action, $model, ?string $permissionName = null, ?string $roleName = null): void
    {
        static::create([
            'model_type' => get_class($model),
            'model_id' => $model->id ?? $model->_id ?? null,
            'action' => $action,
            'permission_name' => $permissionName,
            'role_name' => $roleName,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => [
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ],
        ]);
    }
}
