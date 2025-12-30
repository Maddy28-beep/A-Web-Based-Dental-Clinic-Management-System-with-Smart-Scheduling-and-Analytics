<?php

use App\Models\ActivityLog;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

$staffHomePath = function (User $user): string {
    if ($user->hasPermission('clinical.view')) {
        return '/charting';
    }

    if ($user->hasPermission('appointments.view')) {
        return '/appointments-dashboard';
    }

    if ($user->hasPermission('appointments.checkin')) {
        return '/check-in';
    }

    if ($user->hasPermission('billing.view')) {
        return '/billing';
    }

    if ($user->hasPermission('analytics.view_widgets')) {
        return '/analytics';
    }

    $roleSlug = $user->normalizedRoleSlug();

    return match ($roleSlug) {
        'admin', 'dentist' => '/charting',
        'receptionist' => '/appointments-dashboard',
        default => '/staff/login',
    };
};

if (! function_exists('isAllowedStaffUser')) {
    function isAllowedStaffUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $roleSlug = $user->normalizedRoleSlug();
        if (! $roleSlug) {
            return false;
        }

        return in_array($roleSlug, ['admin', 'dentist', 'receptionist'], true);
    }
}

$validateStaffSession = function (Request $request, User $user, string $context) {
    $roleSlug = $user->normalizedRoleSlug();
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
                'context' => $context,
                'session_role' => $sessionRole,
                'user_role' => $roleSlug,
            ],
            'created_at' => now(),
        ]);

        Log::warning('Auth session role invalid', [
            'context' => $context,
            'user_id' => $user->id,
            'session_role' => $sessionRole,
            'user_role' => $roleSlug,
            'ip' => $request->ip(),
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/staff/login')->withErrors([
            'email' => 'Please sign in again.',
        ]);
    }

    return null;
};

Route::get('/login', function (Request $request) use ($staffHomePath) {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user instanceof User && isAllowedStaffUser($user)) {
            return redirect()->intended($staffHomePath($user));
        }

        Auth::logout();
        $request->session()->forget(['auth_role', 'auth_interface']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/staff/login');
    }

    return redirect('/staff/login');
})->name('login');

Route::get('/staff/login', function (Request $request) use ($staffHomePath, $validateStaffSession) {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user instanceof User && isAllowedStaffUser($user)) {
            $redirect = $validateStaffSession($request, $user, 'staff_login');
            if ($redirect) {
                return $redirect;
            }

            return redirect()->intended($staffHomePath($user));
        }

        Auth::logout();
        $request->session()->forget(['auth_role', 'auth_interface']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    return view('welcome', [
        'showStaffLogin' => true,
        'currentInterface' => 'staff',
    ]);
});

Route::post('/login', function (Request $request) use ($staffHomePath) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();

        $user = Auth::user();
        $roleSlug = $user?->normalizedRoleSlug();
        $allowedRoleSlugs = ['admin', 'dentist', 'receptionist'];
        if (! $roleSlug || ! in_array($roleSlug, $allowedRoleSlugs, true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            ActivityLog::create([
                'actor_user_id' => null,
                'patient_id' => null,
                'action' => 'auth.login_rejected',
                'subject_type' => User::class,
                'subject_id' => $user?->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta' => [
                    'reason' => 'role_not_allowed',
                    'role' => $roleSlug,
                ],
                'created_at' => now(),
            ]);

            return back()->withErrors([
                'email' => 'This login is for clinic staff only.',
            ]);
        }

        $request->session()->put('auth_role', $roleSlug);
        $request->session()->put('auth_interface', 'staff');

        ActivityLog::create([
            'actor_user_id' => $user?->id,
            'patient_id' => null,
            'action' => 'auth.login_succeeded',
            'subject_type' => User::class,
            'subject_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => null,
            'created_at' => now(),
        ]);

        if ($user instanceof User) {
            return redirect()->intended($staffHomePath($user));
        }

        return redirect()->intended('/welcome');
    }

    ActivityLog::create([
        'actor_user_id' => null,
        'patient_id' => null,
        'action' => 'auth.login_failed',
        'subject_type' => User::class,
        'subject_id' => null,
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'meta' => [
            'email' => strtolower(trim((string) $credentials['email'])),
        ],
        'created_at' => now(),
    ]);

    return back()->withErrors([
        'email' => 'Invalid credentials.',
    ]);
});

Route::post('/logout', function (Request $request) {
    $userId = $request->user()?->id;
    if ($userId) {
        ActivityLog::create([
            'actor_user_id' => $userId,
            'patient_id' => null,
            'action' => 'auth.logout',
            'subject_type' => User::class,
            'subject_id' => $userId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => null,
            'created_at' => now(),
        ]);
    }

    Auth::logout();

    $request->session()->forget(['auth_role', 'auth_interface']);
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/welcome');
})->name('logout');

Route::get('/charting', function (Request $request) use ($validateStaffSession) {
    $user = $request->user();
    if (! $user instanceof User) {
        abort(401);
    }

    if (! isAllowedStaffUser($user)) {
        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.denied_role',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'route' => '/charting',
                'role' => $user->normalizedRoleSlug(),
            ],
            'created_at' => now(),
        ]);

        abort(403, 'Access denied.');
    }

    $redirect = $validateStaffSession($request, $user, 'charting');
    if ($redirect) {
        return $redirect;
    }

    if (! $user->hasPermission('clinical.view')) {
        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.denied_permission',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'route' => '/charting',
                'permission' => 'clinical.view',
                'role' => $user->normalizedRoleSlug(),
            ],
            'created_at' => now(),
        ]);

        abort(403, 'Access denied.');
    }

    return view('charting', [
        'currentInterface' => 'staff',
        'userRole' => $user->normalizedRoleSlug(),
    ]);
})->middleware('auth');

Route::get('/appointments-dashboard', function (Request $request) use ($validateStaffSession) {
    $user = $request->user();
    if (! $user instanceof User) {
        abort(401);
    }

    if (! isAllowedStaffUser($user)) {
        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.denied_role',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'route' => '/appointments-dashboard',
                'role' => $user->normalizedRoleSlug(),
            ],
            'created_at' => now(),
        ]);

        abort(403, 'Access denied.');
    }

    $redirect = $validateStaffSession($request, $user, 'appointments_dashboard');
    if ($redirect) {
        return $redirect;
    }

    if (! $user->hasPermission('appointments.view')) {
        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.denied_permission',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'route' => '/appointments-dashboard',
                'permission' => 'appointments.view',
                'role' => $user->normalizedRoleSlug(),
            ],
            'created_at' => now(),
        ]);

        abort(403, 'Access denied.');
    }

    return view('appointments_dashboard', [
        'currentInterface' => 'staff',
        'userRole' => $user->normalizedRoleSlug(),
    ]);
})->middleware('auth');

Route::get('/check-in', function (Request $request) use ($validateStaffSession) {
    $user = $request->user();
    if (! $user instanceof User) {
        abort(401);
    }

    if (! isAllowedStaffUser($user)) {
        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.denied_role',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'route' => '/check-in',
                'role' => $user->normalizedRoleSlug(),
            ],
            'created_at' => now(),
        ]);

        abort(403, 'Access denied.');
    }

    $redirect = $validateStaffSession($request, $user, 'check_in');
    if ($redirect) {
        return $redirect;
    }

    if (! $user->hasPermission('appointments.checkin')) {
        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.denied_permission',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'route' => '/check-in',
                'permission' => 'appointments.checkin',
                'role' => $user->normalizedRoleSlug(),
            ],
            'created_at' => now(),
        ]);

        abort(403, 'Access denied.');
    }

    return view('checkin', [
        'currentInterface' => 'staff',
        'userRole' => $user->normalizedRoleSlug(),
    ]);
})->middleware('auth');

Route::get('/analytics', function (Request $request) use ($validateStaffSession) {
    $user = $request->user();
    if (! $user instanceof User) {
        abort(401);
    }

    if (! isAllowedStaffUser($user)) {
        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.denied_role',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'route' => '/analytics',
                'role' => $user->normalizedRoleSlug(),
            ],
            'created_at' => now(),
        ]);

        abort(403, 'Access denied.');
    }

    $redirect = $validateStaffSession($request, $user, 'analytics');
    if ($redirect) {
        return $redirect;
    }

    if (! $user->hasPermission('analytics.view_widgets')) {
        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.denied_permission',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'route' => '/analytics',
                'permission' => 'analytics.view_widgets',
                'role' => $user->normalizedRoleSlug(),
            ],
            'created_at' => now(),
        ]);

        abort(403, 'Access denied.');
    }

    return view('analytics', [
        'currentInterface' => 'staff',
        'userRole' => $user->normalizedRoleSlug(),
    ]);
})->middleware('auth');

Route::get('/booking/{bookingReferenceCode}', function (Request $request, string $bookingReferenceCode) use ($staffHomePath, $validateStaffSession) {
    $user = $request->user();
    if ($user instanceof User && isAllowedStaffUser($user)) {
        $redirect = $validateStaffSession($request, $user, 'booking_confirmation');
        if ($redirect) {
            return $redirect;
        }

        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.staff_redirected_from_client_view',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'from' => '/booking/{bookingReferenceCode}',
                'to' => $staffHomePath($user),
            ],
            'created_at' => now(),
        ]);

        Log::warning('Staff user redirected from client view', [
            'from' => '/booking/{bookingReferenceCode}',
            'to' => $staffHomePath($user),
            'user_id' => $user->id,
            'role' => $user->normalizedRoleSlug(),
            'ip' => $request->ip(),
        ]);

        return redirect()->to($staffHomePath($user));
    }

    return view('booking_confirmation', [
        'bookingReferenceCode' => $bookingReferenceCode,
        'currentInterface' => 'client',
    ]);
});

Route::get('/billing', function (Request $request) use ($validateStaffSession) {
    $user = $request->user();
    if (! $user instanceof User) {
        abort(401);
    }

    if (! isAllowedStaffUser($user)) {
        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.denied_role',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'route' => '/billing',
                'role' => $user->normalizedRoleSlug(),
            ],
            'created_at' => now(),
        ]);

        abort(403, 'Access denied.');
    }

    $redirect = $validateStaffSession($request, $user, 'billing');
    if ($redirect) {
        return $redirect;
    }

    if (! $user->hasPermission('billing.view')) {
        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.denied_permission',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'route' => '/billing',
                'permission' => 'billing.view',
                'role' => $user->normalizedRoleSlug(),
            ],
            'created_at' => now(),
        ]);

        abort(403, 'Access denied.');
    }

    return view('billing', [
        'currentInterface' => 'staff',
        'userRole' => $user->normalizedRoleSlug(),
    ]);
})->middleware('auth');

Route::get('/design-system', function (Request $request) use ($validateStaffSession) {
    $user = $request->user();
    if (! $user instanceof User) {
        abort(401);
    }

    if (! isAllowedStaffUser($user)) {
        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.denied_role',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'route' => '/design-system',
                'role' => $user->normalizedRoleSlug(),
            ],
            'created_at' => now(),
        ]);

        abort(403, 'Access denied.');
    }

    $redirect = $validateStaffSession($request, $user, 'design_system');
    if ($redirect) {
        return $redirect;
    }

    return view('design_system', [
        'currentInterface' => 'staff',
        'userRole' => $user->normalizedRoleSlug(),
    ]);
})->middleware('auth');

Route::get('/welcome', function (Request $request) use ($staffHomePath, $validateStaffSession) {
    $user = $request->user();
    if ($user instanceof User && isAllowedStaffUser($user)) {
        $redirect = $validateStaffSession($request, $user, 'welcome');
        if ($redirect) {
            return $redirect;
        }

        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.staff_redirected_from_client_view',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'from' => '/welcome',
                'to' => $staffHomePath($user),
            ],
            'created_at' => now(),
        ]);

        Log::warning('Staff user redirected from client view', [
            'from' => '/welcome',
            'to' => $staffHomePath($user),
            'user_id' => $user->id,
            'role' => $user->normalizedRoleSlug(),
            'ip' => $request->ip(),
        ]);

        return redirect()->to($staffHomePath($user));
    }

    $services = Service::query()
        ->where('is_active', true)
        ->orderBy('name')
        ->limit(8)
        ->get();

    return view('welcome', [
        'services' => $services,
        'currentInterface' => 'client',
    ]);
});

Route::get('/', function (Request $request) use ($staffHomePath, $validateStaffSession) {
    $user = $request->user();
    if ($user instanceof User && isAllowedStaffUser($user)) {
        $redirect = $validateStaffSession($request, $user, 'booking');
        if ($redirect) {
            return $redirect;
        }

        ActivityLog::create([
            'actor_user_id' => $user->id,
            'patient_id' => null,
            'action' => 'access.staff_redirected_from_client_view',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'from' => '/',
                'to' => $staffHomePath($user),
            ],
            'created_at' => now(),
        ]);

        Log::warning('Staff user redirected from client view', [
            'from' => '/',
            'to' => $staffHomePath($user),
            'user_id' => $user->id,
            'role' => $user->normalizedRoleSlug(),
            'ip' => $request->ip(),
        ]);

        return redirect()->to($staffHomePath($user));
    }

    return view('booking', [
        'currentInterface' => 'client',
    ]);
});
