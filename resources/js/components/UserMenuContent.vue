<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Database, LogOut, User as UserIcon, Users } from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { usePermissions } from '@/composables/usePermissions';
import { logout } from '@/routes';
import { index as referenceData } from '@/routes/admin/reference';
import { index as adminUsers } from '@/routes/admin/users';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const { can, canAny } = usePermissions();

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <UserIcon class="mr-2 h-4 w-4" />
                Profile
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem v-if="can('users.manage')" :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="adminUsers()"
                prefetch
            >
                <Users class="mr-2 h-4 w-4" />
                Users &amp; roles
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem
            v-if="canAny(['settings.update', 'contacts.update'])"
            :as-child="true"
        >
            <Link
                class="block w-full cursor-pointer"
                :href="referenceData()"
                prefetch
            >
                <Database class="mr-2 h-4 w-4" />
                Reference data
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
