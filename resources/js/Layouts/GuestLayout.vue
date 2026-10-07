<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="hydrolab-shell flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <div class="relative z-10 flex flex-col items-center">
            <Link href="/" class="flex flex-col items-center">
                <ApplicationLogo class="h-[88px] w-[88px]" />
                <p class="mt-3 text-xl font-extrabold tracking-[0.16em] text-[#1A2744]">HYDROLAB</p>
                <p class="text-[12px] font-medium text-[#E88922]">Cultivo inteligente</p>
            </Link>
        </div>

        <div class="relative z-10 hl-glass mt-8 w-full max-w-md rounded-[28px] border border-white/70 p-8 shadow-sidebar">
            <slot />
        </div>
    </div>
</template>
