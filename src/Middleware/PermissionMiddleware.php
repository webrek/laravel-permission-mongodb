<?php

namespace Maklad\Permission\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Maklad\Permission\Exceptions\UnauthorizedException;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission, ?string $guard = null)
    {
        $authGuard = app('auth')->guard($guard);

        if ($authGuard->guest()) {
            throw UnauthorizedException::notLoggedIn();
        }

        $permissions = is_array($permission)
            ? $permission
            : explode('|', $permission);

        foreach ($permissions as $perm) {
            // Check for rate limiting syntax: permission|rate:60,1
            if (str_contains($perm, 'rate:')) {
                [$perm, $rateLimit] = explode('|rate:', $perm);
                $this->applyRateLimit($request, $perm, $rateLimit);
            }

            // Check for logging syntax: permission|log:channel
            if (str_contains($perm, 'log:')) {
                [$perm, $logChannel] = explode('|log:', $perm);
                $this->logPermissionCheck($request, $perm, $logChannel);
            }

            if ($authGuard->user()->can($perm)) {
                return $next($request);
            }
        }

        throw UnauthorizedException::forPermissions($permissions);
    }

    protected function applyRateLimit(Request $request, string $permission, string $limitConfig): void
    {
        [$maxAttempts, $decayMinutes] = explode(',', $limitConfig);

        $key = 'permission:' . $permission . ':' . $request->user()->id;

        if (RateLimiter::tooManyAttempts($key, (int)$maxAttempts)) {
            throw UnauthorizedException::rateLimitExceeded($permission);
        }

        RateLimiter::hit($key, (int)$decayMinutes * 60);
    }

    protected function logPermissionCheck(Request $request, string $permission, string $channel): void
    {
        Log::channel($channel)->info('Permission check', [
            'permission' => $permission,
            'user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'route' => $request->path(),
        ]);
    }
}
