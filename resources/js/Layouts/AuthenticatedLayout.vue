<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const sidebarOpen = ref(false);

const user = computed(() => page.props.auth.user);
const device = computed(() => page.props.hydrolabDevice);

const initials = computed(() => {
    const name = user.value?.name ?? '';
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase() || 'HL';
});

const navItems = [
    {
        label: 'Dashboard',
        href: 'dashboard',
        current: 'dashboard',
        icon: 'dashboard',
    },
    {
        label: 'Historial',
        href: 'history',
        current: 'history',
        icon: 'history',
    },
    {
        label: 'Análisis IA',
        href: 'analysis',
        current: 'analysis',
        icon: 'analysis',
    },
    {
        label: 'Dispositivo',
        href: 'device',
        current: 'device',
        icon: 'device',
    },
    {
        label: 'Configuración',
        href: 'profile.edit',
        current: 'profile.edit',
        icon: 'settings',
    },
];
</script>

<template>
    <div class="hydrolab-shell min-h-screen">
        <div class="relative z-10 mx-auto flex min-h-screen max-w-[1600px] gap-5 p-4 lg:p-5">
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-40 bg-hl-navy/30 backdrop-blur-sm lg:hidden"
                @click="sidebarOpen = false"
            />

            <aside
                class="z-50 flex w-[270px] shrink-0 flex-col overflow-y-auto rounded-[28px] p-5 shadow-sidebar transition-transform duration-200 max-lg:fixed max-lg:inset-y-4 max-lg:left-4 lg:sticky lg:top-5 lg:h-[calc(100vh-2.5rem)]"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-[120%] lg:translate-x-0'"
                style="background: linear-gradient(180deg, #FFFFFF 0%, #FFF6EE 100%);"
            >
                <Link :href="route('dashboard')" class="flex flex-col items-center px-1 text-center">
                    <ApplicationLogo class="h-[72px] w-[72px] shrink-0" />
                    <p class="mt-2 text-[18px] font-extrabold tracking-[0.16em] text-[#1A2744]">HYDROLAB</p>
                    <p class="mt-0.5 text-[12px] font-medium text-[#E88922]">Cultivo inteligente</p>
                </Link>

                <nav class="mt-8 flex-1 space-y-2.5">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="route(item.href)"
                        class="flex items-center gap-3 rounded-2xl px-3.5 py-3 text-[15px] font-semibold transition"
                        :class="
                            route().current(item.current)
                                ? 'bg-[#E8F1FF] text-[#2F80ED]'
                                : 'text-[#1A2B56] hover:bg-white/70'
                        "
                        @click="sidebarOpen = false"
                    >
                        <svg v-if="item.icon === 'dashboard'" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" :fill="route().current(item.current) ? 'currentColor' : 'none'" :stroke="route().current(item.current) ? 'none' : 'currentColor'" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.2 10.6 12 4.2l7.8 6.4V20a1 1 0 0 1-1 1h-4.6v-6.2H9.8V21H5.2a1 1 0 0 1-1-1v-9.4Z" />
                        </svg>
                        <svg v-else-if="item.icon === 'history'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <rect x="4" y="4.5" width="16" height="15" rx="2.2" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 15.2 10.2 12l2.6 2.2 3.7-5" />
                        </svg>
                        <svg v-else-if="item.icon === 'analysis'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.2 14.2c-1.7-.8-2.7-2.3-2.7-4.2A5.5 5.5 0 0 1 12 4.6a5.5 5.5 0 0 1 6.5 5.4c0 1.9-1 3.4-2.7 4.2" />
                            <path stroke-linecap="round" d="M12 4.6v5.2M9.4 8.4h5.2" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.2 14.8 8 20.2M14.8 14.8 16 20.2" />
                            <path stroke-linecap="round" d="M10.2 10.8c.4 1.5 1.8 2.4 1.8 2.4s1.4-.9 1.8-2.4" />
                        </svg>
                        <svg v-else-if="item.icon === 'device'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <rect x="7.2" y="7.2" width="9.6" height="9.6" rx="1.6" />
                            <path stroke-linecap="round" d="M9.4 7.2V4.6M12 7.2V4.6M14.6 7.2V4.6M9.4 19.4v-2.6M12 19.4v-2.6M14.6 19.4v-2.6M7.2 9.4H4.6M7.2 12H4.6M7.2 14.6H4.6M19.4 9.4h-2.6M19.4 12h-2.6M19.4 14.6h-2.6" />
                        </svg>
                        <svg v-else class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.3 9.8 7.1a7.1 7.1 0 0 0-1.6.9L5.5 7.1 3.7 10.3l2.2 1.6a7 7 0 0 0 0 1.8L3.7 15.3 5.5 18.5l2.7-.9a7.1 7.1 0 0 0 1.6.9l.5 2.8h3.6l.5-2.8a7.1 7.1 0 0 0 1.6-.9l2.7.9 1.8-3.2-2.2-1.6a7 7 0 0 0 0-1.8l2.2-1.6-1.8-3.2-2.7.9a7.1 7.1 0 0 0-1.6-.9l-.5-2.8h-3.6Z" />
                            <circle cx="12" cy="13" r="2.3" />
                        </svg>
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="mt-4 space-y-3">
                    <div class="rounded-2xl bg-[#FFF3E8] p-3.5">
                        <p class="text-[11px] font-medium text-[#1A2B56]">Dispositivo</p>
                        <p class="mt-0.5 text-sm font-bold text-[#1A2B56]">
                            {{ device?.name ?? 'Sin dispositivo' }}
                        </p>
                        <div class="mt-2.5 flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold" :class="device?.is_online ? 'text-[#22C55E]' : 'text-slate-400'">
                                <span class="h-2 w-2 rounded-full" :class="device?.is_online ? 'bg-[#22C55E] hl-live-dot' : 'bg-slate-300'" />
                                {{ device?.is_online ? 'Conectado' : 'Desconectado' }}
                            </span>
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full"
                                :class="device?.is_online ? 'bg-[#E9F9EE] text-[#22C55E]' : 'bg-slate-100 text-slate-400'"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5a9 9 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19.5h.01" />
                                </svg>
                            </span>
                        </div>
                    </div>

                    <Dropdown align="left" width="48">
                        <template #trigger>
                            <button type="button" class="flex w-full items-center gap-3 rounded-2xl bg-white px-3 py-2.5 text-left shadow-sm">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-[#6B7CFF] to-[#A855F7] text-sm font-bold text-white">
                                    {{ initials }}
                                </span>
                                <span class="min-w-0 flex-1 truncate text-sm font-semibold text-[#1A2B56]">{{ user.name }}</span>
                                <svg class="h-4 w-4 text-[#1A2B56]" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Configuración</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Cerrar sesión</DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="mb-5 flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <button
                            type="button"
                            class="mt-1 inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-white shadow-card lg:hidden"
                            @click="sidebarOpen = true"
                        >
                            <svg class="h-5 w-5 text-hl-navy" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                            </svg>
                        </button>
                        <div>
                            <slot name="header" />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                        <slot name="actions" />

                        <button
                            type="button"
                            class="relative flex h-11 w-11 items-center justify-center rounded-2xl border border-white/70 text-slate-500 shadow-card hl-glass"
                            aria-label="Notificaciones"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0" />
                            </svg>
                            <span class="absolute right-2.5 top-2.5 h-2 w-2 rounded-full bg-rose-500" />
                        </button>

                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button type="button" class="hl-glass hidden items-center gap-2 rounded-2xl border border-white/70 py-1.5 pl-1.5 pr-3 shadow-card sm:flex">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-hl-blue to-sky-400 text-xs font-bold text-white">
                                        {{ initials }}
                                    </span>
                                    <span class="max-w-[120px] truncate text-sm font-semibold text-hl-navy">{{ user.name }}</span>
                                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </template>
                            <template #content>
                                <DropdownLink :href="route('profile.edit')">Configuración</DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">Cerrar sesión</DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </header>

                <main class="flex-1 pb-4">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
