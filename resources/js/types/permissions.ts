/**
 * Mirrors app/Enums/Permission.php. Keep both lists in sync.
 */
export type Permission =
    | 'contacts.view'
    | 'contacts.view_unmasked'
    | 'contacts.update'
    | 'segments.manage'
    | 'exports.create'
    | 'exports.view_log'
    | 'imports.create'
    | 'imports.review'
    | 'imports.commit'
    | 'imports.discard'
    | 'settings.update'
    | 'users.manage';
