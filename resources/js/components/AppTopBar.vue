<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import { ChevronDown } from "@lucide/vue";
import { computed } from "vue";
import AppLogoIcon from "@/components/AppLogoIcon.vue";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import UserMenuContent from "@/components/UserMenuContent.vue";
import { useCurrentUrl } from "@/composables/useCurrentUrl";
import { getInitials } from "@/composables/useInitials";
import { usePermissions } from "@/composables/usePermissions";
import { toUrl } from "@/lib/utils";
import { edit as adminSettings } from "@/routes/admin/settings";
import { index as audience } from "@/routes/audience";
import { index as exportsLog } from "@/routes/exports";
import { index as health } from "@/routes/health";
import { index as imports } from "@/routes/imports";
import { index as sources } from "@/routes/sources";
import type { NavItem, Permission } from "@/types";

type Tab = NavItem & { permissions: Permission[] };

const page = usePage();
const user = computed(() => page.props.auth.user);
const { canAny } = usePermissions();
const { isCurrentOrParentUrl } = useCurrentUrl();

const allTabs: Tab[] = [
    { title: "Audience", href: audience(), permissions: ["contacts.view"] },
    {
        title: "Imports",
        href: imports(),
        permissions: ["imports.create", "imports.review"],
    },
    {
        title: "Exports",
        href: exportsLog(),
        permissions: ["exports.view_log"],
    },
    { title: "Data health", href: health(), permissions: ["contacts.view"] },
    { title: "Sources", href: sources(), permissions: ["contacts.view"] },
    {
        title: "Settings",
        href: adminSettings(),
        permissions: ["settings.update"],
    },
];

const tabs = computed(() => allTabs.filter((tab) => canAny(tab.permissions)));
</script>

<template>
    <header
        class="sticky top-[env(safe-area-inset-top,0px)] z-[5] border-b border-line bg-surface py-2.5 pr-[max(16px,env(safe-area-inset-right,0px))] pl-[max(16px,env(safe-area-inset-left,0px))]"
    >
        <div class="mx-auto flex max-w-shell flex-wrap items-center gap-3.5">
            <Link
                :href="audience()"
                class="flex min-w-0 items-center gap-2.5 text-fg no-underline"
            >
                <img
                    v-if="page.props.brand?.logo_light"
                    :src="page.props.brand.logo_light"
                    alt=""
                    class="h-7 max-w-24 flex-none object-contain"
                />
                <AppLogoIcon v-else class="size-7 flex-none" />
                <h1
                    class="m-0 font-display text-[17px] font-semibold tracking-[.01em] whitespace-nowrap"
                >
                    {{ page.props.name }}
                    <small
                        class="block text-[11.5px] font-normal tracking-[.02em] text-subtle"
                        >Contacts · Segments · Campaign exports</small
                    >
                </h1>
            </Link>

            <nav
                v-if="tabs.length"
                class="flex max-w-full gap-1 overflow-x-auto rounded-[9px] bg-surface-2 p-[3px]"
                aria-label="Views"
            >
                <Link
                    v-for="tab in tabs"
                    :key="toUrl(tab.href)"
                    :href="tab.href"
                    :aria-current="
                        isCurrentOrParentUrl(tab.href) ? 'page' : undefined
                    "
                    class="rounded-[7px] px-3 py-1.5 text-[13px] font-medium whitespace-nowrap text-subtle no-underline aria-[current=page]:bg-surface aria-[current=page]:text-fg aria-[current=page]:shadow-[0_1px_2px_rgba(0,0,0,.08)]"
                    prefetch
                >
                    {{ tab.title }}
                </Link>
            </nav>

            <DropdownMenu>
                <DropdownMenuTrigger
                    class="ml-auto flex items-center gap-2 rounded-lg py-1 pr-1.5 pl-1 text-[13px] font-medium hover:bg-surface-2 data-[state=open]:bg-surface-2 max-[900px]:ml-0"
                    data-test="user-menu-trigger"
                >
                    <span
                        class="grid size-7 place-items-center rounded-full bg-ink-soft font-mono text-[11px] font-semibold text-ink"
                        aria-hidden="true"
                        >{{ getInitials(user.name) }}</span
                    >
                    <span class="max-w-40 truncate max-[1100px]:sr-only">{{
                        user.name
                    }}</span>
                    <ChevronDown class="size-3.5 text-subtle" />
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-60" align="end">
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
