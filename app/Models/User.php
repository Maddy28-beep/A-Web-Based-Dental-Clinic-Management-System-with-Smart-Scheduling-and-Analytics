<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user) {
            $slug = $user->normalizedRoleSlug();
            if (! $slug) {
                return;
            }

            if (! Schema::hasTable('roles') || ! Schema::hasTable('user_roles')) {
                return;
            }

            if ($user->roles()->exists()) {
                return;
            }

            $roleId = Role::query()->where('slug', $slug)->value('id');
            if (! $roleId) {
                return;
            }

            $user->roles()->attach((int) $roleId);
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function normalizedRoleSlug(): ?string
    {
        $slug = $this->role ? strtolower(trim((string) $this->role)) : null;
        if (! $slug) {
            return null;
        }

        if ($slug === 'assistant') {
            return 'receptionist';
        }

        return $slug;
    }

    public function hasRole(string $roleSlug): bool
    {
        $roleSlug = strtolower(trim($roleSlug));
        if ($roleSlug === '') {
            return false;
        }

        if (Schema::hasTable('roles') && Schema::hasTable('user_roles')) {
            if ($this->roles()->where('slug', $roleSlug)->exists()) {
                return true;
            }
        }

        return $this->normalizedRoleSlug() === $roleSlug;
    }

    public function hasPermission(string $permissionKey): bool
    {
        $permissionKey = strtolower(trim($permissionKey));
        if ($permissionKey === '') {
            return false;
        }

        $roleSlug = $this->normalizedRoleSlug();
        $defaultPermission = (function () use ($roleSlug, $permissionKey): bool {
            if (! $roleSlug) {
                return false;
            }

            $map = [
                'admin' => [
                    'appointments.view',
                    'appointments.checkin',
                    'appointments.update_status',
                    'appointments.convert_to_patient',
                    'patients.view',
                    'patients.create',
                    'clinical.view',
                    'clinical.edit',
                    'inventory.view',
                    'inventory.manage',
                    'billing.view',
                    'billing.create',
                    'billing.lock',
                    'payments.record',
                    'refunds.create',
                    'pricing.manage',
                    'catalog.manage',
                    'dentist.manage_leave_any',
                    'dentist.manage_leave_self',
                    'analytics.view_widgets',
                    'analytics.view_drilldowns',
                    'analytics.choose_dentist',
                ],
                'dentist' => [
                    'appointments.view',
                    'appointments.update_status',
                    'patients.view',
                    'clinical.view',
                    'clinical.edit',
                    'inventory.view',
                    'billing.view',
                    'billing.lock',
                    'analytics.view_widgets',
                    'analytics.view_drilldowns',
                    'analytics.scope_self',
                    'dentist.manage_leave_self',
                ],
                'receptionist' => [
                    'appointments.view',
                    'appointments.checkin',
                    'appointments.update_status',
                    'appointments.convert_to_patient',
                    'patients.view',
                    'patients.create',
                    'inventory.view',
                    'billing.view',
                    'billing.create',
                    'payments.record',
                    'analytics.view_widgets',
                    'analytics.choose_dentist',
                    'dentist.manage_leave_any',
                ],
            ];

            return in_array($permissionKey, $map[$roleSlug] ?? [], true);
        })();

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions') || ! Schema::hasTable('user_roles')) {
            return $defaultPermission;
        }

        static $authzSeeded = null;
        if ($authzSeeded === null) {
            $authzSeeded = Role::query()->exists()
                && Permission::query()->exists()
                && DB::table('role_permissions')->count() > 0;
        }

        if (! $authzSeeded) {
            return $defaultPermission;
        }

        $roleSlugs = $this->roles()->pluck('slug')->all();
        if ($roleSlug) {
            $roleSlugs[] = $roleSlug;
        }
        $roleSlugs = array_values(array_unique($roleSlugs));
        if ($roleSlugs === []) {
            return false;
        }

        return Permission::query()
            ->where('key', $permissionKey)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', $roleSlugs))
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isDentist(): bool
    {
        return $this->hasRole('dentist');
    }

    public function isReceptionist(): bool
    {
        return $this->hasRole('receptionist');
    }
}
