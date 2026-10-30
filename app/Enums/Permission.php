<?php

namespace App\Enums;

/**
 * Every permission in the application. Synced to the database by `php artisan app:sync-permissions`.
 */
enum Permission: string
{
    case ContactsView = 'contacts.view';
    case ContactsViewUnmasked = 'contacts.view_unmasked';
    case ContactsUpdate = 'contacts.update';
    case SegmentsManage = 'segments.manage';
    case ExportsCreate = 'exports.create';
    case ExportsViewLog = 'exports.view_log';
    case ImportsCreate = 'imports.create';
    case ImportsReview = 'imports.review';
    case ImportsCommit = 'imports.commit';
    case ImportsDiscard = 'imports.discard';
    case SettingsUpdate = 'settings.update';
    case UsersManage = 'users.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
