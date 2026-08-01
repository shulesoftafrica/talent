<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Verification officers are existing admin.users — no parallel user table.
 * Access is gated by the additive "Talent Verification Officer" RBAC role/
 * permission seeded by Database\Seeders\TalentOfficerRoleSeeder; nothing
 * about the existing admin auth/RBAC system is modified.
 */
class OfficerUser extends Authenticatable
{
    public const VERIFICATION_PERMISSION = 'talent.verify_candidates';

    protected $connection = 'admin';
    protected $table = 'users';

    protected $hidden = ['password', 'remember_token'];

    public function hasVerificationAccess(): bool
    {
        return DB::connection('admin')->table('role_user as ru')
            ->join('permission_role as pr', 'pr.role_id', '=', 'ru.role_id')
            ->join('permissions as p', 'p.id', '=', 'pr.permission_id')
            ->where('ru.user_id', $this->id)
            ->where('p.name', self::VERIFICATION_PERMISSION)
            ->exists();
    }
}
