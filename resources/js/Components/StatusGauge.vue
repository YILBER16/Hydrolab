<script setup>
const props = defineProps({
    percent: {
        type: Number,
        default: 100,
    },
    tone: {
        type: String,
        default: 'ok',
    },
    size: {
        type: Number,
        default: 138,
    },
});

const uid = `st${Math.random().toString(36).slice(2, 9)}`;

const ring = {
    ok: 'conic-gradient(from -30deg, #C8E85A 0%, #4ADE80 22%, #22C55E 48%, #14B8A6 72%, #86EFAC 88%, #C8E85A 100%)',
    warn: 'conic-gradient(from -30deg, #FDE68A 0%, #FBBF24 40%, #F97316 75%, #FDE68A 100%)',
    bad: 'conic-gradient(from -30deg, #FDA4AF 0%, #F43F5E 50%, #E11D48 80%, #FDA4AF 100%)',
    unknown: 'conic-gradient(from -30deg, #E2E8F0 0%, #94A3B8 50%, #CBD5E1 100%)',
};

const iconSize = Math.round(props.size * 0.42);
const inset = Math.max(6, Math.round(props.size * 0.072));
</script>

<template>
    <div class="relative" :style="{ width: `${size}px`, height: `${size}px` }">
        <div
            class="absolute inset-0 rounded-full"
            :style="{ background: ring[props.tone] ?? ring.ok }"
        />
        <div
            class="absolute rounded-full bg-[#FFFCFA]"
            :style="{ inset: `${inset}px` }"
        />

        <svg
            class="absolute inset-0 m-auto"
            :style="{ width: `${iconSize}px`, height: `${iconSize}px` }"
            viewBox="0 0 64 64"
            fill="none"
            aria-hidden="true"
        >
            <defs>
                <linearGradient :id="`${uid}-l`" x1="8" y1="10" x2="32" y2="52" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="#A3E635" />
                    <stop offset="100%" stop-color="#16A34A" />
                </linearGradient>
                <linearGradient :id="`${uid}-r`" x1="32" y1="50" x2="56" y2="8" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="#15803D" />
                    <stop offset="55%" stop-color="#4ADE80" />
                    <stop offset="100%" stop-color="#D9F99D" />
                </linearGradient>
            </defs>
            <path
                d="M32 54C32 54 31.5 38 32 34"
                stroke="#15803D"
                stroke-width="2.4"
                stroke-linecap="round"
            />
            <path
                d="M32 46C18 42 10 30 13 16C22 20 28 34 32 46Z"
                :fill="`url(#${uid}-l)`"
            />
            <path
                d="M32 46C46 40 54 27 51 13C43 18 36 32 32 46Z"
                :fill="`url(#${uid}-r)`"
            />
        </svg>
    </div>
</template>
