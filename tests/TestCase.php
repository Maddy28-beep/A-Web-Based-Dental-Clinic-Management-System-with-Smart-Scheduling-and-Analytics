<?php

namespace Tests;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsStaff(User $user): static
    {
        $role = $user->normalizedRoleSlug() ?? $user->role ?? null;

        return $this->actingAs($user)->withSession([
            'auth_role' => $role,
            'auth_interface' => 'staff',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthPermissions();
    }

    protected function seedAuthPermissions(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $roles = collect([
            ['slug' => 'admin', 'name' => 'Admin', 'is_active' => true],
            ['slug' => 'dentist', 'name' => 'Dentist', 'is_active' => true],
            ['slug' => 'receptionist', 'name' => 'Receptionist', 'is_active' => true],
            ['slug' => 'patient', 'name' => 'Patient', 'is_active' => true],
        ])->map(fn (array $r) => Role::updateOrCreate(['slug' => $r['slug']], $r))->keyBy('slug');

        $permissions = collect([
            ['key' => 'appointments.view', 'name' => 'View appointments'],
            ['key' => 'appointments.checkin', 'name' => 'Check in appointments'],
            ['key' => 'appointments.update_status', 'name' => 'Update appointment statuses'],
            ['key' => 'appointments.convert_to_patient', 'name' => 'Convert appointment to patient'],
            ['key' => 'patients.view', 'name' => 'View patients'],
            ['key' => 'patients.create', 'name' => 'Create patients'],
            ['key' => 'clinical.view', 'name' => 'View clinical records'],
            ['key' => 'clinical.edit', 'name' => 'Edit clinical records'],
            ['key' => 'inventory.view', 'name' => 'View inventory'],
            ['key' => 'inventory.manage', 'name' => 'Manage inventory'],
            ['key' => 'billing.view', 'name' => 'View billing'],
            ['key' => 'billing.create', 'name' => 'Create bills'],
            ['key' => 'billing.lock', 'name' => 'Lock bills'],
            ['key' => 'payments.record', 'name' => 'Record payments'],
            ['key' => 'refunds.create', 'name' => 'Create refunds'],
            ['key' => 'pricing.manage', 'name' => 'Manage pricing'],
            ['key' => 'catalog.manage', 'name' => 'Manage services and dentists'],
            ['key' => 'dentist.manage_leave_any', 'name' => 'Manage dentist leave (any)'],
            ['key' => 'dentist.manage_leave_self', 'name' => 'Manage own leave'],
            ['key' => 'analytics.view_widgets', 'name' => 'View analytics widgets'],
            ['key' => 'analytics.view_drilldowns', 'name' => 'View analytics drilldowns'],
            ['key' => 'analytics.choose_dentist', 'name' => 'Choose dentist filters'],
            ['key' => 'analytics.scope_self', 'name' => 'Scope analytics to own dentist'],
        ])->map(fn (array $p) => Permission::updateOrCreate(['key' => $p['key']], $p))->keyBy('key');

        $syncRolePerms = function (string $roleSlug, array $permissionKeys) use ($roles, $permissions): void {
            $role = $roles[$roleSlug] ?? null;
            if (! $role) {
                return;
            }

            $permIds = collect($permissionKeys)
                ->map(fn (string $k) => $permissions[$k]->id ?? null)
                ->filter()
                ->values()
                ->all();

            $role->permissions()->sync($permIds);
        };

        $allPermissionKeys = $permissions->keys()->values()->all();
        $adminKeys = array_values(array_diff($allPermissionKeys, ['analytics.scope_self']));
        $syncRolePerms('admin', $adminKeys);
        $syncRolePerms('dentist', [
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
        ]);
        $syncRolePerms('receptionist', [
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
        ]);
        $syncRolePerms('patient', []);
    }
}
