<?php

namespace Database\Seeders;

use App\Models\OfficerUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Purely additive: seeds one permission + one role into the existing
 * admin RBAC tables (admin.permissions, admin.roles, admin.permission_role)
 * so verification officers can be gated without touching any existing
 * role/permission/user row. Does NOT assign the role to anyone — that's a
 * real access-control decision for a human with knowledge of who should
 * actually get it, done separately via admin.role_user.
 */
class TalentOfficerRoleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = DB::connection('admin');

        $permissionId = $admin->table('permissions')->where('name', OfficerUser::VERIFICATION_PERMISSION)->value('id');
        if (!$permissionId) {
            $permissionId = $admin->table('permissions')->insertGetId([
                'name' => OfficerUser::VERIFICATION_PERMISSION,
                'display_name' => 'Talent Verification Officer',
                'description' => 'Review and approve/reject candidate verification submissions on the ShuleSoft Talent Network.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleId = $admin->table('roles')->where('name', 'talent_verification_officer')->value('id');
        if (!$roleId) {
            $roleId = $admin->table('roles')->insertGetId([
                'name' => 'talent_verification_officer',
                'display_name' => 'Talent Verification Officer',
                'description' => 'Can review and approve/reject candidate verification submissions on the ShuleSoft Talent Network.',
                'is_staff' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $linked = $admin->table('permission_role')
            ->where('permission_id', $permissionId)
            ->where('role_id', $roleId)
            ->exists();

        if (!$linked) {
            $admin->table('permission_role')->insert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
