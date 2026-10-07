<script setup>
const uid = `tp${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
    <svg
        class="pointer-events-none absolute -bottom-1 -right-1 h-[118px] w-[132px]"
        viewBox="0 0 132 118"
        fill="none"
        aria-hidden="true"
    >
        <defs>
            <radialGradient :id="`${uid}-m1`" cx="40%" cy="30%" r="80%">
                <stop offset="0%" stop-color="#F8C48A" />
                <stop offset="100%" stop-color="#E88922" />
            </radialGradient>
            <radialGradient :id="`${uid}-m2`" cx="35%" cy="20%" r="80%">
                <stop offset="0%" stop-color="#F6B56E" />
                <stop offset="100%" stop-color="#E07A18" />
            </radialGradient>
            <radialGradient :id="`${uid}-leaf`" cx="38%" cy="32%" r="72%">
                <stop offset="0%" stop-color="#C6F06A" />
                <stop offset="45%" stop-color="#7ED957" />
                <stop offset="100%" stop-color="#2F9E4A" />
            </radialGradient>
            <filter :id="`${uid}-dust`" x="-50%" y="-50%" width="200%" height="200%">
                <feGaussianBlur stdDeviation="1.1" />
            </filter>
        </defs>

        <ellipse cx="78" cy="112" rx="42" ry="22" :fill="`url(#${uid}-m1)`" />
        <ellipse cx="118" cy="108" rx="46" ry="28" :fill="`url(#${uid}-m2)`" />

        <g :filter="`url(#${uid}-dust)`">
            <circle cx="62" cy="78" r="2.4" fill="#F4A574" opacity="0.55" />
            <circle cx="74" cy="58" r="1.7" fill="#F8C48A" opacity="0.5" />
            <circle cx="96" cy="46" r="2" fill="#E88922" opacity="0.35" />
            <circle cx="118" cy="54" r="1.5" fill="#F4A574" opacity="0.45" />
            <circle cx="108" cy="72" r="1.2" fill="#F8C48A" opacity="0.4" />
            <circle cx="54" cy="96" r="1.8" fill="#E88922" opacity="0.28" />
        </g>

        <!-- Small plant -->
        <g transform="translate(70 78)">
            <path d="M0 28 C0 18 1 8 0 0" stroke="#2F9E4A" stroke-width="2.2" stroke-linecap="round" />
            <g transform="rotate(-48)">
                <ellipse cx="0" cy="-11" rx="5.2" ry="11.5" :fill="`url(#${uid}-leaf)`" />
                <path d="M0 -1 V-20" stroke="#FFFFFF" stroke-opacity="0.55" stroke-width="0.85" stroke-linecap="round" />
            </g>
            <g transform="rotate(46)">
                <ellipse cx="0" cy="-10" rx="4.8" ry="10.5" :fill="`url(#${uid}-leaf)`" />
                <path d="M0 -1 V-18" stroke="#FFFFFF" stroke-opacity="0.5" stroke-width="0.8" stroke-linecap="round" />
            </g>
            <g transform="rotate(-8)">
                <ellipse cx="0" cy="-9" rx="4" ry="9.2" :fill="`url(#${uid}-leaf)`" />
                <path d="M0 0 V-16" stroke="#FFFFFF" stroke-opacity="0.45" stroke-width="0.7" stroke-linecap="round" />
            </g>
        </g>

        <!-- Large plant -->
        <g transform="translate(106 62)">
            <path d="M0 42 C1 26 0 12 0 0" stroke="#2F9E4A" stroke-width="2.6" stroke-linecap="round" />
            <g transform="rotate(-58)">
                <ellipse cx="0" cy="-13" rx="6.2" ry="13.8" :fill="`url(#${uid}-leaf)`" />
                <path d="M0 -1 V-24" stroke="#FFFFFF" stroke-opacity="0.55" stroke-width="0.95" stroke-linecap="round" />
            </g>
            <g transform="rotate(54)">
                <ellipse cx="0" cy="-13" rx="6" ry="13.4" :fill="`url(#${uid}-leaf)`" />
                <path d="M0 -1 V-23" stroke="#FFFFFF" stroke-opacity="0.5" stroke-width="0.9" stroke-linecap="round" />
            </g>
            <g transform="rotate(-22)">
                <ellipse cx="0" cy="-14" rx="5.6" ry="14.2" :fill="`url(#${uid}-leaf)`" />
                <path d="M0 0 V-25" stroke="#FFFFFF" stroke-opacity="0.5" stroke-width="0.9" stroke-linecap="round" />
            </g>
            <g transform="rotate(18)">
                <ellipse cx="0" cy="-13.5" rx="5.4" ry="13.6" :fill="`url(#${uid}-leaf)`" />
                <path d="M0 0 V-24" stroke="#FFFFFF" stroke-opacity="0.48" stroke-width="0.85" stroke-linecap="round" />
            </g>
        </g>
    </svg>
</template>
