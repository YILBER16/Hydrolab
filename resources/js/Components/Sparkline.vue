<script setup>
import { computed } from 'vue';

const props = defineProps({
    values: {
        type: Array,
        default: () => [],
    },
    color: {
        type: String,
        default: '#2F80ED',
    },
    empty: {
        type: Boolean,
        default: false,
    },
});

const uid = `sp${Math.random().toString(36).slice(2, 9)}`;

const path = computed(() => {
    const points = props.values.filter((v) => typeof v === 'number' && !Number.isNaN(v));

    if (points.length < 2) {
        return 'M0 22 C 18 10, 36 30, 54 16 S 90 8, 120 20';
    }

    const min = Math.min(...points);
    const max = Math.max(...points);
    const range = max - min || 1;
    const width = 120;
    const height = 36;
    const pad = 1;

    return points
        .map((value, index) => {
            const x = pad + (index / (points.length - 1)) * (width - pad * 2);
            const y = height - pad - ((value - min) / range) * (height - pad * 2);
            return `${index === 0 ? 'M' : 'L'}${x.toFixed(2)} ${y.toFixed(2)}`;
        })
        .join(' ');
});

const area = computed(() => `${path.value} L120 36 L0 36 Z`);
</script>

<template>
    <svg viewBox="0 0 120 36" class="h-12 w-full overflow-visible" preserveAspectRatio="none">
        <defs>
            <linearGradient :id="`${uid}-fill`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="color" stop-opacity="0.28" />
                <stop offset="100%" :stop-color="color" stop-opacity="0" />
            </linearGradient>
        </defs>
        <path :d="area" :fill="`url(#${uid}-fill)`" />
        <path
            :d="path"
            fill="none"
            :stroke="color"
            stroke-width="2.1"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>
