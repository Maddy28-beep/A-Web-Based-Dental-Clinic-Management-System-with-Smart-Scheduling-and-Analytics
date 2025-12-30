<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! ($user instanceof User)) {
            return $this->deny($request, 401, 'Unauthenticated.');
        }

        $roleSlug = $user->normalizedRoleSlug();
        $isStaff = $roleSlug && in_array($roleSlug, ['admin', 'dentist', 'receptionist'], true);
        if (! $isStaff) {
            ActivityLog::create([
                'actor_user_id' => $user->id,
                'patient_id' => null,
                'action' => $request->is('api/*') ? 'access.api_denied_role' : 'access.denied_role',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta' => [
                    'path' => $request->path(),
                    'role' => $roleSlug,
                ],
                'created_at' => now(),
            ]);

            return $this->deny($request, 403, 'Access denied.');
        }

        $sessionRole = $request->session()->get('auth_role');
        if (! $roleSlug || ! $sessionRole || $sessionRole !== $roleSlug) {
            ActivityLog::create([
                'actor_user_id' => $user->id,
                'patient_id' => null,
                'action' => 'auth.session_role_invalid',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta' => [
                    'context' => $request->is('api/*') ? 'api' : 'web',
                    'path' => $request->path(),
                    'session_role' => $sessionRole,
                    'user_role' => $roleSlug,
                ],
                'created_at' => now(),
            ]);

            Log::warning('Auth session role invalid', [
                'path' => $request->path(),
                'user_id' => $user->id,
                'session_role' => $sessionRole,
                'user_role' => $roleSlug,
                'ip' => $request->ip(),
            ]);

            Auth::logout();
            $request->session()->forget(['auth_role', 'auth_interface']);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $this->deny($request, 401, 'Please sign in again.');
        }

        return $next($request);
    }

    private function deny(Request $request, int $status, string $message): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message], $status);
        }

        if ($status === 401) {
            return redirect('/staff/login')->withErrors(['email' => $message]);
        }

        abort($status, $message);
    }
}
