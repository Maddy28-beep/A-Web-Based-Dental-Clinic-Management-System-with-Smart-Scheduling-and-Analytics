<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

trait AuthorizesPermissions
{
    protected function requirePermission(Request $request, string $permissionKey): void
    {
        $this->requireAnyPermission($request, [$permissionKey]);
    }

    protected function requireAnyPermission(Request $request, array $permissionKeys): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        foreach ($permissionKeys as $key) {
            if ($user->hasPermission($key)) {
                return;
            }
        }

        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'auth.forbidden',
            'subject_type' => null,
            'subject_id' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'required_permissions' => array_values(array_unique(array_map(fn ($v) => (string) $v, $permissionKeys))),
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
            ],
            'created_at' => now(),
        ]);

        abort(403);
    }
}
