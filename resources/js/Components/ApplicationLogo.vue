<script setup>
const uid = `hl${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 80 80"
        fill="none"
        aria-hidden="true"
    >
        <defs>
            <linearGradient
                :id="`${uid}-ring`"
                x1="10"
                y1="6"
                x2="72"
                y2="76"
                gradientUnits="userSpaceOnUse"
            >
                <stop offset="0%" stop-color="#6EC8FF" />
                <stop offset="18%" stop-color="#4BA7FF" />
                <stop offset="42%" stop-color="#2F7FF0" />
                <stop offset="62%" stop-color="#2A6DE4" />
                <stop offset="78%" stop-color="#F0B13A" />
                <stop offset="100%" stop-color="#E88922" />
            </linearGradient>
            <linearGradient
                :id="`${uid}-leaf-l`"
                x1="22"
                y1="30"
                x2="40"
                y2="56"
                gradientUnits="userSpaceOnUse"
            >
                <stop offset="0%" stop-color="#1F8A3C" />
                <stop offset="55%" stop-color="#3CB85A" />
                <stop offset="100%" stop-color="#2E9A48" />
            </linearGradient>
            <linearGradient
                :id="`${uid}-leaf-r`"
                x1="40"
                y1="54"
                x2="58"
                y2="28"
                gradientUnits="userSpaceOnUse"
            >
                <stop offset="0%" stop-color="#2FA046" />
                <stop offset="55%" stop-color="#7ED957" />
                <stop offset="100%" stop-color="#C6EE5A" />
            </linearGradient>
        </defs>

        <path
            d="M40 8C40 8 14.5 31.5 14.5 48.2C14.5 62.4 25.8 73.5 40 73.5C54.2 73.5 65.5 62.4 65.5 48.2C65.5 31.5 40 8 40 8Z"
            fill="none"
            :stroke="`url(#${uid}-ring)`"
            stroke-width="6.8"
            stroke-linejoin="round"
            stroke-linecap="round"
        />

        <path
            d="M40 61.5C40 61.5 39.6 50.5 40 47"
            stroke="#2E9A48"
            stroke-width="2.1"
            stroke-linecap="round"
        />

        <path
            d="M40 55.2C29.2 52.6 21.8 43.4 23.6 32.6C30.4 35.8 35.6 45.2 40 55.2Z"
            :fill="`url(#${uid}-leaf-l)`"
        />
        <path
            d="M40 55.2C50.8 51.4 57.4 41.6 55.4 30.6C49 34.4 43.8 44.6 40 55.2Z"
            :fill="`url(#${uid}-leaf-r)`"
        />
    </svg>
</template>
