<?php

namespace Maklad\Permission\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShowPermissions extends Command
{
    protected $signature = 'permission:show
                            {model : Model class name (e.g., App\\Models\\User)}
                            {id : Model ID}
                            {--guard=web : Guard name}
                            {--json : Output as JSON}';

    protected $description = 'Show permissions and roles for a specific model instance';

    public function handle(): int
    {
        $modelClass = $this->argument('model');
        $modelId = $this->argument('id');
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

        if (!method_exists($model, 'getAllPermissions')) {
            $this->error('Model must use HasRoles or HasPermissions trait');
            return 1;
        }

        $data = [
            'model' => class_basename($modelClass),
            'id' => $modelId,
            'identifier' => $model->email ?? $model->name ?? $modelId,
            'guard' => $guard,
            'roles' => $model->getRoleNames()->toArray(),
            'roles_count' => $model->getRolesCount(),
            'direct_permissions' => $model->getDirectPermissions()->pluck('name')->toArray(),
            'direct_permissions_count' => $model->getDirectPermissionsCount(),
            'permissions_via_roles' => $model->getPermissionsViaRoles()->pluck('name')->toArray(),
            'all_permissions' => $model->getAllPermissions()->pluck('name')->toArray(),
            'total_permissions_count' => $model->getPermissionsCount(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->displayResults($data);

        return 0;
    }

    protected function displayResults(array $data): void
    {
        $this->info("═══════════════════════════════════════════════════════");
        $this->info(" Permission Report");
        $this->info("═══════════════════════════════════════════════════════");
        $this->line("");

        $this->line("<fg=cyan>Model:</> {$data['model']} #{$data['id']} ({$data['identifier']})");
        $this->line("<fg=cyan>Guard:</> {$data['guard']}");
        $this->line("");

        $this->info("─── Roles ({$data['roles_count']}) ───");
        if (empty($data['roles'])) {
            $this->line("  <fg=gray>No roles assigned</>");
        } else {
            foreach ($data['roles'] as $role) {
                $this->line("  • {$role}");
            }
        }
        $this->line("");

        $this->info("─── Direct Permissions ({$data['direct_permissions_count']}) ───");
        if (empty($data['direct_permissions'])) {
            $this->line("  <fg=gray>No direct permissions</>");
        } else {
            foreach ($data['direct_permissions'] as $permission) {
                $this->line("  • {$permission}");
            }
        }
        $this->line("");

        $this->info("─── Permissions via Roles ───");
        if (empty($data['permissions_via_roles'])) {
            $this->line("  <fg=gray>No permissions via roles</>");
        } else {
            foreach ($data['permissions_via_roles'] as $permission) {
                $this->line("  • {$permission}");
            }
        }
        $this->line("");

        $this->info("─── Summary ───");
        $this->line("  Total Unique Permissions: <fg=green>{$data['total_permissions_count']}</>");
        $this->line("");
    }
}
