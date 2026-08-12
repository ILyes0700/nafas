<?php
declare(strict_types=1);
/**
 * Role-Based Access Control for Gabes Tatenafas.
 *
 * Role keys (kept backward-compatible with existing modules & role_allowed_routes):
 *   citizen      -> Normal User (public self-registration)
 *   health       -> Doctor (medical authority)  [granted by Admin]
 *   school       -> School                        [granted by Admin]
 *   admin        -> Admin                         [created by Super Admin]
 *   super_admin  -> Super Admin (single, seeded manually)
 */

const ROLE_CITIZEN     = 'citizen';
const ROLE_DOCTOR      = 'health';   // "Doctor" maps to the existing health role
const ROLE_SCHOOL      = 'school';
const ROLE_ADMIN       = 'admin';
const ROLE_SUPER_ADMIN = 'super_admin';

function rbac_all_roles(): array {
    return [ROLE_CITIZEN, ROLE_DOCTOR, ROLE_SCHOOL, ROLE_ADMIN, ROLE_SUPER_ADMIN];
}

/** Roles an Admin may assign to a normal user (never admin/super_admin). */
function rbac_admin_assignable_roles(): array {
    return [ROLE_CITIZEN, ROLE_DOCTOR, ROLE_SCHOOL];
}

/** Human label for a role key. */
function rbac_label(string $role): string {
    switch ($role) {
        case ROLE_SUPER_ADMIN: return 'Super Admin';
        case ROLE_ADMIN:       return 'Admin';
        case ROLE_DOCTOR:      return 'Doctor';
        case ROLE_SCHOOL:      return 'School';
        case ROLE_CITIZEN:     return 'Normal User';
        default:               return $role;
    }
}

function rbac_rank(string $role): int {
    switch ($role) {
        case ROLE_SUPER_ADMIN: return 100;
        case ROLE_ADMIN:       return 80;
        case ROLE_DOCTOR:      return 40;
        case ROLE_SCHOOL:      return 40;
        case ROLE_CITIZEN:     return 10;
        default:               return 0;
    }
}

function rbac_is_admin(string $role): bool {
    return $role === ROLE_ADMIN || $role === ROLE_SUPER_ADMIN;
}

/**
 * Capability check. $actorRole is the current user's role string.
 * Returns true if that role is allowed to perform $capability.
 */
function rbac_can(string $actorRole, string $capability): bool {
    $super = ($actorRole === ROLE_SUPER_ADMIN);
    $admin = ($actorRole === ROLE_ADMIN);
    switch ($capability) {
        /* Super-Admin only */
        case 'admin.create':
        case 'admin.delete':
        case 'admin.suspend':
        case 'admin.restore':
            return $super;
        /* Admin or Super-Admin */
        case 'users.view':
        case 'users.suspend':
        case 'users.restore':
        case 'users.set_role':
            return $super || $admin;
        default:
            return false;
    }
}
