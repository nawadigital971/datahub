<?php

namespace App\Enums;

/**
 * The roles seeded on install. Permissions of existing roles are editable in the dashboard,
 * so these defaults are applied only when a role is first created (Admin always gets everything).
 */
enum Role: string
{
    case Admin = 'Admin';
    case Marketer = 'Marketer';
    case DataEditor = 'Data Editor';
    case Viewer = 'Viewer';

    /**
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Marketer => [
                Permission::ContactsView,
                Permission::SegmentsManage,
                Permission::ExportsCreate,
                Permission::ExportsViewLog,
            ],
            self::DataEditor => [
                Permission::ContactsView,
                Permission::ImportsCreate,
                Permission::ImportsReview,
            ],
            self::Viewer => [
                Permission::ContactsView,
            ],
        };
    }
}
