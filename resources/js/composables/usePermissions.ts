import { usePage } from '@inertiajs/vue3';
import type { Permission } from '@/types';

export type UsePermissionsReturn = {
    can: (permission: Permission) => boolean;
    canAny: (permissions: Permission[]) => boolean;
};

/**
 * Hide UI the current user is not allowed to use. The backend still enforces every permission.
 */
export function usePermissions(): UsePermissionsReturn {
    const page = usePage();

    const can = (permission: Permission): boolean =>
        page.props.auth.permissions.includes(permission);

    const canAny = (permissions: Permission[]): boolean =>
        permissions.some(can);

    return { can, canAny };
}
