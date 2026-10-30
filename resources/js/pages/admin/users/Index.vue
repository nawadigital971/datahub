<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import AppDialog from '@/components/AppDialog.vue';
import AppSelect from '@/components/AppSelect.vue';
import FormField from '@/components/FormField.vue';
import { toast } from '@/composables/useToast';
import { fieldClasses } from '@/lib/fieldClasses';
import { store as storeRole, update as updateRole } from '@/routes/admin/roles';
import {
    deactivate as deactivateUser,
    index as usersIndex,
    reactivate as reactivateUser,
    store as storeUser,
    update as updateUser,
} from '@/routes/admin/users';
import {
    destroy as revokeToken,
    store as storeToken,
} from '@/routes/admin/users/tokens';
import type { SelectOption } from '@/types';

type Token = {
    id: number;
    name: string;
    created_at: string | null;
    expires_at: string | null;
    last_used_at: string | null;
};
type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    is_active: boolean;
    is_self: boolean;
    created_at: string | null;
    tokens: Token[];
};
type RoleRow = {
    id: number;
    name: string;
    permissions: string[];
    locked: boolean;
    users: number;
};

const props = defineProps<{
    users: {
        data: UserRow[];
        meta: { current_page: number; last_page: number; total: number };
        links: { prev: string | null; next: string | null };
    };
    roles: RoleRow[];
    permissions: string[];
    filters: { search: string | null };
}>();

const roleOptions = computed<SelectOption<string>[]>(() =>
    props.roles.map((role) => ({ value: role.name, label: role.name })),
);

/* ---------- search ---------- */
const search = ref(props.filters.search ?? '');
const runSearch = useDebounceFn(() => {
    router.get(
        usersIndex().url,
        search.value ? { filter: { search: search.value } } : {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 300);

/* ---------- add / edit user ---------- */
const userDialog = ref(false);
const editingUser = ref<UserRow | null>(null);
const userForm = useForm({ name: '', email: '', role: null as string | null });

function openUser(user: UserRow | null): void {
    editingUser.value = user;
    userForm.clearErrors();
    userForm.name = user?.name ?? '';
    userForm.email = user?.email ?? '';
    userForm.role = user?.role ?? null;
    userDialog.value = true;
}

function saveUser(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (userDialog.value = false),
    };
    if (editingUser.value) {
        userForm.put(updateUser(editingUser.value.id).url, options);
    } else {
        userForm.post(storeUser().url, options);
    }
}

function setActive(user: UserRow, active: boolean): void {
    router.post(
        (active ? reactivateUser : deactivateUser)(user.id).url,
        {},
        {
            preserveScroll: true,
            onError: (errors) =>
                toast(
                    Object.values(errors)[0] ?? 'Could not change this user.',
                    'error',
                ),
        },
    );
}

/* ---------- MCP tokens ---------- */
const tokenDialog = ref(false);
const tokenUser = ref<UserRow | null>(null);
const tokenForm = useForm({ name: 'Claude', days: 90 });
const newToken = ref<{ user: string; token: string; url: string } | null>(null);

function openTokens(user: UserRow): void {
    tokenUser.value = user;
    newToken.value = null;
    tokenForm.reset();
    tokenDialog.value = true;
}

watch(
    () => props.users.data,
    (rows) => {
        if (tokenUser.value) {
            tokenUser.value =
                rows.find((row) => row.id === tokenUser.value?.id) ??
                tokenUser.value;
        }
    },
);

function createToken(): void {
    if (!tokenUser.value) return;
    tokenForm.post(storeToken(tokenUser.value.id).url, {
        preserveScroll: true,
        onFlash: (flash) => {
            newToken.value =
                (
                    flash as {
                        mcp_token?: {
                            user: string;
                            token: string;
                            url: string;
                        };
                    }
                ).mcp_token ?? null;
        },
    });
}

function removeToken(token: Token): void {
    if (!tokenUser.value) return;
    router.delete(
        revokeToken({ user: tokenUser.value.id, token: token.id }).url,
        { preserveScroll: true },
    );
}

const connectCommand = computed(() =>
    newToken.value
        ? `claude mcp add --transport http nawaa ${newToken.value.url} --header "Authorization: Bearer ${newToken.value.token}"`
        : '',
);

async function copy(text: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(text);
        toast('Copied');
    } catch {
        toast('Select the text and copy it with Ctrl+C', 'info');
    }
}

/* ---------- roles ---------- */
const roleDrafts = ref<Record<number, string[]>>({});
watch(
    () => props.roles,
    (roles) => {
        roleDrafts.value = Object.fromEntries(
            roles.map((role) => [role.id, [...role.permissions]]),
        );
    },
    { immediate: true },
);

const roleChanged = (role: RoleRow): boolean =>
    [...(roleDrafts.value[role.id] ?? [])].sort().join() !==
    [...role.permissions].sort().join();

function togglePermission(role: RoleRow, permission: string): void {
    const current = roleDrafts.value[role.id] ?? [];
    roleDrafts.value[role.id] = current.includes(permission)
        ? current.filter((p) => p !== permission)
        : [...current, permission];
}

function saveRole(role: RoleRow): void {
    router.put(
        updateRole(role.id).url,
        { permissions: roleDrafts.value[role.id] ?? [] },
        { preserveScroll: true },
    );
}

const roleForm = useForm({ name: '', permissions: [] as string[] });
function addRole(): void {
    roleForm.post(storeRole().url, {
        preserveScroll: true,
        onSuccess: () => roleForm.reset(),
    });
}

const PERMISSION_LABELS: Record<string, string> = {
    'contacts.view': 'See contacts and the audience',
    'contacts.view_unmasked': 'See full phones and emails',
    'contacts.update': 'Edit contacts',
    'segments.manage': 'Save and delete segments',
    'exports.create': 'Export audiences',
    'exports.view_log': 'See the export log and download any export',
    'imports.create': 'Upload files and stage data',
    'imports.review': 'Review and fix staged rows',
    'imports.commit': 'Commit imports into the contacts',
    'imports.discard': 'Discard imports',
    'settings.update': 'Change settings and sources',
    'users.manage': 'Manage users, roles and MCP tokens',
};

const dateFormat = new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' });
const formatDate = (value: string | null): string =>
    value ? dateFormat.format(new Date(value)) : '—';
</script>

<template>
    <Head title="Users & roles" />

    <div class="mb-2.5 flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="m-0 font-display text-base font-semibold">
            Users &amp; roles
        </h2>
        <button
            type="button"
            class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg"
            @click="openUser(null)"
        >
            Add user
        </button>
    </div>
    <p class="m-0 mb-4 max-w-[80ch] text-subtle">
        People sign in with their email; new users get a link to choose a
        password. Users are deactivated rather than deleted, so the export and
        import logs keep who did what.
    </p>

    <div
        class="mb-[18px] min-w-0 overflow-hidden rounded-card border border-line bg-surface"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-2.5 border-b border-line px-4 py-3"
        >
            <strong class="num"
                >{{ users.meta.total }}
                {{ users.meta.total === 1 ? 'user' : 'users' }}</strong
            >
            <input
                v-model="search"
                type="search"
                placeholder="Search name or email…"
                aria-label="Search users"
                class="w-64 rounded-lg border border-line bg-surface-2 px-2.5 py-1.5"
                @input="runSearch"
            />
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] border-collapse text-[12.5px]">
                <thead>
                    <tr>
                        <th
                            v-for="heading in [
                                'Name',
                                'Role',
                                'Status',
                                'MCP tokens',
                                'Added',
                                '',
                            ]"
                            :key="heading"
                            class="bg-surface-2 px-3 py-[9px] text-left text-[11px] font-semibold tracking-[.06em] whitespace-nowrap text-subtle uppercase"
                        >
                            {{ heading }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        :class="{ 'opacity-60': !user.is_active }"
                    >
                        <td class="border-t border-line px-3 py-2">
                            <strong>{{ user.name }}</strong>
                            <span
                                v-if="user.is_self"
                                class="ml-1 text-xs text-subtle"
                                >(you)</span
                            >
                            <div class="text-xs text-subtle">
                                {{ user.email }}
                            </div>
                        </td>
                        <td class="border-t border-line px-3 py-2">
                            {{ user.role ?? 'No role' }}
                        </td>
                        <td class="border-t border-line px-3 py-2">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :class="
                                    user.is_active
                                        ? 'bg-ink-soft text-ink'
                                        : 'bg-surface-2 text-subtle'
                                "
                            >
                                {{ user.is_active ? 'Active' : 'Deactivated' }}
                            </span>
                        </td>
                        <td class="border-t border-line px-3 py-2 num">
                            {{ user.tokens.length }}
                        </td>
                        <td class="border-t border-line px-3 py-2">
                            {{ formatDate(user.created_at) }}
                        </td>
                        <td
                            class="border-t border-line px-3 py-2 text-right whitespace-nowrap"
                        >
                            <button
                                type="button"
                                class="mr-3 border-0 bg-transparent p-0 font-medium text-ink"
                                @click="openUser(user)"
                            >
                                Edit
                            </button>
                            <button
                                v-if="user.is_active"
                                type="button"
                                class="mr-3 border-0 bg-transparent p-0 font-medium text-ink"
                                @click="openTokens(user)"
                            >
                                MCP access
                            </button>
                            <button
                                v-if="!user.is_self"
                                type="button"
                                class="border-0 bg-transparent p-0 font-medium"
                                :class="
                                    user.is_active ? 'text-bad' : 'text-ink'
                                "
                                @click="setActive(user, !user.is_active)"
                            >
                                {{
                                    user.is_active ? 'Deactivate' : 'Reactivate'
                                }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            v-if="users.meta.last_page > 1"
            class="flex items-center justify-end gap-2 border-t border-line px-4 py-2.5 text-[12.5px] text-subtle"
        >
            <Link
                v-if="users.links.prev"
                :href="users.links.prev"
                class="rounded-md border border-line bg-surface px-2.5 py-1 text-fg no-underline"
                >Previous</Link
            >
            <Link
                v-if="users.links.next"
                :href="users.links.next"
                class="rounded-md border border-line bg-surface px-2.5 py-1 text-fg no-underline"
                >Next</Link
            >
        </div>
    </div>

    <div class="mb-2.5 flex flex-wrap items-baseline justify-between gap-3">
        <h2 class="m-0 font-display text-base font-semibold">Roles</h2>
        <p class="m-0 text-[12.5px] text-subtle">
            What each role may do. Admin always has everything.
        </p>
    </div>
    <div
        class="min-w-0 overflow-hidden rounded-card border border-line bg-surface"
    >
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[12.5px]">
                <thead>
                    <tr>
                        <th
                            class="bg-surface-2 px-3 py-[9px] text-left text-[11px] font-semibold tracking-[.06em] text-subtle uppercase"
                        >
                            Permission
                        </th>
                        <th
                            v-for="role in roles"
                            :key="role.id"
                            class="bg-surface-2 px-3 py-[9px] text-center text-[11px] font-semibold tracking-[.06em] whitespace-nowrap text-subtle uppercase"
                        >
                            {{ role.name }}
                            <div
                                class="font-normal tracking-normal normal-case"
                            >
                                {{ role.users }} users
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="permission in permissions" :key="permission">
                        <td class="border-t border-line px-3 py-2">
                            {{ PERMISSION_LABELS[permission] ?? permission }}
                            <div class="font-mono text-[11px] text-faint">
                                {{ permission }}
                            </div>
                        </td>
                        <td
                            v-for="role in roles"
                            :key="role.id"
                            class="border-t border-line px-3 py-2 text-center"
                        >
                            <input
                                type="checkbox"
                                class="accent-ink"
                                :checked="
                                    role.locked ||
                                    (roleDrafts[role.id] ?? []).includes(
                                        permission,
                                    )
                                "
                                :disabled="role.locked"
                                :aria-label="`${role.name}: ${PERMISSION_LABELS[permission] ?? permission}`"
                                @change="togglePermission(role, permission)"
                            />
                        </td>
                    </tr>
                    <tr>
                        <td class="border-t border-line px-3 py-2"></td>
                        <td
                            v-for="role in roles"
                            :key="role.id"
                            class="border-t border-line px-3 py-2 text-center"
                        >
                            <button
                                v-if="!role.locked"
                                type="button"
                                class="rounded-md border-0 bg-ink px-2.5 py-1 text-[12px] font-semibold text-ink-fg disabled:opacity-30"
                                :disabled="!roleChanged(role)"
                                @click="saveRole(role)"
                            >
                                Save
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <form
            class="flex flex-wrap items-end gap-2.5 border-t border-line px-4 py-3"
            @submit.prevent="addRole"
        >
            <FormField
                v-slot="field"
                label="New role"
                required
                :error="roleForm.errors.name"
            >
                <input
                    :id="field.id"
                    v-model="roleForm.name"
                    maxlength="60"
                    required
                    aria-required="true"
                    placeholder="e.g. Call centre"
                    :class="[fieldClasses, 'w-56']"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.describedBy"
                />
            </FormField>
            <button
                type="submit"
                class="h-[39px] rounded-lg border border-line bg-transparent px-3.5 text-[13px] font-semibold"
                :disabled="roleForm.processing"
            >
                Add role
            </button>
            <span class="pb-2.5 text-xs text-subtle"
                >It starts with no permissions; tick them above.</span
            >
        </form>
    </div>

    <AppDialog
        v-model:open="userDialog"
        :title="editingUser ? `Edit ${editingUser.name}` : 'Add user'"
        :description="
            editingUser
                ? undefined
                : 'They will get an email with a link to choose their password.'
        "
    >
        <form class="flex flex-col gap-3.5" @submit.prevent="saveUser">
            <FormField
                v-slot="field"
                label="Name"
                required
                :error="userForm.errors.name"
            >
                <input
                    :id="field.id"
                    v-model="userForm.name"
                    :class="fieldClasses"
                    required
                    aria-required="true"
                    maxlength="255"
                    dir="auto"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.describedBy"
                />
            </FormField>
            <FormField
                v-slot="field"
                label="Email"
                required
                :error="userForm.errors.email"
            >
                <input
                    :id="field.id"
                    v-model="userForm.email"
                    type="email"
                    :class="fieldClasses"
                    required
                    aria-required="true"
                    maxlength="255"
                    dir="ltr"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.describedBy"
                />
            </FormField>
            <FormField
                v-slot="field"
                label="Role"
                required
                :error="userForm.errors.role"
            >
                <AppSelect
                    :id="field.id"
                    v-model="userForm.role"
                    :options="roleOptions"
                    :searchable="false"
                    :required="true"
                    :invalid="field.invalid"
                    :described-by="field.describedBy"
                />
            </FormField>
            <div>
                <button
                    type="submit"
                    class="rounded-lg border-0 bg-ink px-3.5 py-2 text-[13px] font-semibold text-ink-fg disabled:opacity-50"
                    :disabled="userForm.processing"
                >
                    {{ editingUser ? 'Save' : 'Add user and send invite' }}
                </button>
            </div>
        </form>
    </AppDialog>

    <AppDialog
        v-model:open="tokenDialog"
        :title="`MCP access · ${tokenUser?.name ?? ''}`"
        description="Claude connects with a token and acts with this user's permissions. It can stage and fix data, never commit."
        width="640px"
    >
        <div
            v-if="newToken"
            class="rounded-[9px] bg-sand-soft px-3 py-2.5 text-[12.5px]"
            role="status"
        >
            <b class="text-sand">Copy this token now.</b> It is shown only once.
            <code
                class="mt-2 block rounded bg-surface px-2 py-1.5 font-mono text-[11.5px] break-all"
                >{{ newToken.token }}</code
            >
            <div class="mt-2 text-subtle">Connect Claude Code:</div>
            <code
                class="mt-1 block rounded bg-surface px-2 py-1.5 font-mono text-[11.5px] break-all"
                >{{ connectCommand }}</code
            >
            <div class="mt-2 flex gap-2">
                <button
                    type="button"
                    class="rounded-md border-0 bg-ink px-2.5 py-1 text-[12px] font-semibold text-ink-fg"
                    @click="copy(newToken.token)"
                >
                    Copy token
                </button>
                <button
                    type="button"
                    class="rounded-md border border-line bg-surface px-2.5 py-1 text-[12px] font-semibold"
                    @click="copy(connectCommand)"
                >
                    Copy command
                </button>
            </div>
        </div>

        <table
            v-if="tokenUser && tokenUser.tokens.length"
            class="w-full border-collapse text-[12.5px]"
        >
            <thead>
                <tr>
                    <th
                        v-for="heading in [
                            'Name',
                            'Created',
                            'Expires',
                            'Last used',
                            '',
                        ]"
                        :key="heading"
                        class="bg-surface-2 px-3 py-[9px] text-left text-[11px] font-semibold tracking-[.06em] text-subtle uppercase"
                    >
                        {{ heading }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="token in tokenUser.tokens" :key="token.id">
                    <td class="border-t border-line px-3 py-2">
                        {{ token.name }}
                    </td>
                    <td class="border-t border-line px-3 py-2">
                        {{ formatDate(token.created_at) }}
                    </td>
                    <td class="border-t border-line px-3 py-2">
                        {{ formatDate(token.expires_at) }}
                    </td>
                    <td class="border-t border-line px-3 py-2">
                        {{ formatDate(token.last_used_at) }}
                    </td>
                    <td class="border-t border-line px-3 py-2 text-right">
                        <button
                            type="button"
                            class="border-0 bg-transparent p-0 font-medium text-bad"
                            @click="removeToken(token)"
                        >
                            Revoke
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-else class="m-0 text-[12.5px] text-subtle">No MCP tokens yet.</p>

        <form
            class="flex flex-wrap items-end gap-2.5"
            @submit.prevent="createToken"
        >
            <FormField
                v-slot="field"
                label="Token name"
                required
                :error="tokenForm.errors.name"
            >
                <input
                    :id="field.id"
                    v-model="tokenForm.name"
                    maxlength="60"
                    required
                    aria-required="true"
                    :class="[fieldClasses, 'w-48']"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.describedBy"
                />
            </FormField>
            <FormField
                v-slot="field"
                label="Valid for (days)"
                required
                :error="tokenForm.errors.days"
            >
                <input
                    :id="field.id"
                    v-model.number="tokenForm.days"
                    type="number"
                    min="1"
                    max="365"
                    required
                    aria-required="true"
                    :class="[fieldClasses, 'w-28']"
                    :aria-invalid="field.invalid"
                    :aria-describedby="field.describedBy"
                />
            </FormField>
            <button
                type="submit"
                class="h-[39px] rounded-lg border-0 bg-ink px-3.5 text-[13px] font-semibold text-ink-fg disabled:opacity-50"
                :disabled="tokenForm.processing"
            >
                Create token
            </button>
        </form>
    </AppDialog>
</template>
