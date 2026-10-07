<script setup>
const props = defineProps({
    type: {
        type: String,
        default: 'temperature',
    },
});
</script>

<template>
    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <template v-if="props.type === 'temperature'">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14.5V6.2a2.2 2.2 0 1 1 4 0v8.3a3.5 3.5 0 1 1-4 0Z" />
            <path stroke-linecap="round" d="M12 8.5v5" />
        </template>
        <template v-else-if="props.type === 'humidity'">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5C12 3.5 6 10.2 6 14.4A6 6 0 0 0 18 14.4C18 10.2 12 3.5 12 3.5Z" />
        </template>
        <template v-else-if="props.type === 'ph'">
            <text
                x="12"
                y="16.2"
                text-anchor="middle"
                fill="currentColor"
                stroke="none"
                font-size="11"
                font-weight="700"
                font-family="Plus Jakarta Sans, sans-serif"
            >pH</text>
        </template>
        <template v-else-if="props.type === 'ec'">
            <path stroke-linecap="round" d="M3.5 12c1.8-3 3.6-3 5.4 0s3.6 3 5.4 0 3.6-3 5.4 0" />
        </template>
        <template v-else>
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 20V9.2A1.7 1.7 0 0 1 9.2 7.5h5.6A1.7 1.7 0 0 1 16.5 9.2V20" />
            <path stroke-linecap="round" d="M6.5 20h11" />
            <path stroke-linecap="round" d="M8.8 16.2c1.4-1.4 2.6-1.4 3.7 0s2.4 1.4 3.7 0" />
        </template>
    </svg>
</template>
