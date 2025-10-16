<?php

namespace Maklad\Permission\Commands;

use Illuminate\Console\Command;

class CheckPermission extends Command
{
    protected $signature = 'permission:check
                            {model : Model class name (e.g., App\\Models\\User)}
                            {id : Model ID}
                            {permission : Permission name to check}
                            {--guard=web : Guard name}
                            {--explain : Show detailed explanation}';

    protected $description = 'Check if a model has a specific permission';

    public function handle(): int
    {
        $modelClass = $this->argument('model');
        $modelId = $this->argument('id');
        $permissionName = $this->argument('permission');
        $guard = $this->option('guard');

        if (!class_exists($modelClass)) {
            $this->error("Model class not found: {$modelClass}");
            return 1;
        }

        $model = $modelClass::find($modelId);

        if (!$model) {
            $this->error("Model not found with ID: {$modelId}");
            return 1;
        }

        if (!method_exists($model, 'hasPermissionTo')) {
            $this->error('Model must use HasRoles or HasPermissions trait');
            return 1;
        }

        $hasPermission = $model->hasPermissionTo($permissionName, $guard);

        $identifier = $model->email ?? $model->name ?? $modelId;

        if ($hasPermission) {
            $this->info("✓ {$identifier} HAS permission: {$permissionName}");

            if ($this->option('explain') && method_exists($model, 'explainPermission')) {
                $this->showExplanation($model, $permissionName);
            }

            return 0;
        } else {
            $this->error("✗ {$identifier} DOES NOT HAVE permission: {$permissionName}");

            if ($this->option('explain') && method_exists($model, 'explainPermission')) {
                $this->showExplanation($model, $permissionName);
            }

            return 1;
        }
    }

    protected function showExplanation($model, string $permissionName): void
    {
        $this->line("");
        $this->info("─── Explanation ───");

        $explanation = $model->explainPermission($permissionName);

        $this->line("Permission: <fg=cyan>{$explanation['permission']}</>");
        $this->line("Has Permission: " . ($explanation['has_permission'] ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line("Has Directly: " . ($explanation['has_directly'] ? '<fg=green>Yes</>' : '<fg=gray>No</>'));
        $this->line("Has via Role: " . ($explanation['has_via_role'] ? '<fg=green>Yes</>' : '<fg=gray>No</>'));

        if (!empty($explanation['roles_granting_permission'])) {
            $this->line("");
            $this->line("Granted by roles:");
            foreach ($explanation['roles_granting_permission'] as $role) {
                $this->line("  • {$role}");
            }
        }
    }
}
