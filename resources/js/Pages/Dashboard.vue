<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MetricIcon from '@/Components/MetricIcon.vue';
import Sparkline from '@/Components/Sparkline.vue';
import StatusGauge from '@/Components/StatusGauge.vue';
import TipPlants from '@/Components/TipPlants.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';
import { computed } from 'vue';
import { useLiveSnapshot } from '@/composables/useLiveSnapshot';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
);

const props = defineProps({
    device: Object,
    sensors: {
        type: Array,
        default: () => [],
    },
    hours: {
        type: Number,
        default: 24,
    },
    chart: {
        type: Object,
        default: () => ({ labels: [], series: [] }),
    },
    harvest: {
        type: Object,
        default: null,
    },
    dailyAdvice: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const {
    device,
    sensors,
    chart,
    harvest,
    dailyAdvice,
    lastUpdatedAt,
    liveError,
    fetching,
    autoRefresh,
    refresh,
} = useLiveSnapshot(props, { intervalMs: 4000, includeChart: true });

const adviceForm = useForm({});

const ranges = [
    { label: '1H', hours: 1 },
    { label: '6H', hours: 6 },
    { label: '24H', hours: 24 },
    { label: '7D', hours: 168 },
    { label: '30D', hours: 720 },
];

const themes = {
    temperature: {
        color: '#3B82F6',
        soft: 'bg-blue-50 text-blue-500',
        solid: 'bg-[#5BA8FF]',
        iconWrap: 'bg-gradient-to-br from-[#C5E4FF] to-[#5BA8FF] text-white shadow-[0_8px_16px_rgba(91,168,255,0.45)]',
    },
    humidity: {
        color: '#38BDF8',
        soft: 'bg-sky-50 text-sky-500',
        solid: 'bg-[#2F80ED]',
        iconWrap: 'bg-gradient-to-br from-[#CFF4FF] to-[#38BDF8] text-white shadow-[0_8px_16px_rgba(56,189,248,0.4)]',
    },
    ph: {
        color: '#8B5CF6',
        soft: 'bg-violet-50 text-violet-500',
        solid: 'bg-[#8B5CF6]',
        iconWrap: 'bg-gradient-to-br from-[#E9D5FF] to-[#8B5CF6] text-white shadow-[0_8px_16px_rgba(139,92,246,0.4)]',
    },
    ec: {
        color: '#F97316',
        soft: 'bg-orange-50 text-orange-500',
        solid: 'bg-[#F97316]',
        iconWrap: 'bg-gradient-to-br from-[#FFEDD5] to-[#F97316] text-white shadow-[0_8px_16px_rgba(249,115,22,0.35)]',
    },
    water_level: {
        color: '#14B8A6',
        soft: 'bg-teal-50 text-teal-500',
        solid: 'bg-[#14B8A6]',
        iconWrap: 'bg-gradient-to-br from-[#CCFBF1] to-[#14B8A6] text-white shadow-[0_8px_16px_rgba(20,184,166,0.4)]',
    },
};

const summaryName = {
    temperature: 'Temperatura',
    humidity: 'Humedad',
    ph: 'pH',
    ec: 'EC',
    water_level: 'Nivel de agua',
};

const statusLabel = {
    ok: 'Óptimo',
    low: 'Bajo',
    high: 'Alto',
    unknown: 'Sin datos',
};

const firstName = computed(() => page.props.auth.user.name);

const themeFor = (type) => themes[type] ?? themes.temperature;

const formatValue = (value) => {
    if (value === null || value === undefined) {
        return '--';
    }

    const number = Number(value);
    if (Number.isInteger(number)) {
        return String(number);
    }

    return number.toFixed(2).replace(/0$/, '').replace(/\.$/, '');
};

const lastReading = computed(() => {
    const dates = sensors.value
        .map((sensor) => sensor.recorded_at)
        .filter(Boolean)
        .sort()
        .reverse();

    return dates[0] ? new Date(dates[0]) : null;
});

const lastReadingDate = computed(() => {
    if (!lastReading.value) {
        return 'Sin lecturas';
    }

    return new Intl.DateTimeFormat('es-CO', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(lastReading.value);
});

const lastReadingTime = computed(() => {
    if (!lastReading.value) {
        return '--';
    }

    return new Intl.DateTimeFormat('es-CO', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    }).format(lastReading.value);
});

const temperatureSensor = computed(() =>
    sensors.value.find((sensor) => sensor.type === 'temperature') ?? null,
);

const overall = computed(() => {
    if (sensors.value.length === 0) {
        return {
            label: 'Sin datos',
            detail: 'Configura sensores para ver el estado del cultivo.',
            tone: 'unknown',
            percent: 0,
        };
    }

    const known = sensors.value.filter((sensor) => sensor.status !== 'unknown');
    const ok = known.filter((sensor) => sensor.status === 'ok').length;
    const alerts = known.filter((sensor) => sensor.status === 'low' || sensor.status === 'high');

    if (known.length === 0) {
        return {
            label: 'Sin datos',
            detail: 'Aún no llegan lecturas del dispositivo.',
            tone: 'unknown',
            percent: 12,
        };
    }

    const percent = Math.round((ok / sensors.value.length) * 100);

    if (alerts.length > 0) {
        return {
            label: 'Atención',
            detail: `${alerts.length} parámetro${alerts.length > 1 ? 's' : ''} fuera del rango ideal.`,
            tone: 'warn',
            percent: Math.max(percent, 18),
        };
    }

    return {
        label: 'Óptimo',
        detail: 'Todos los parámetros dentro del rango ideal.',
        tone: 'ok',
        percent: Math.max(percent, 86),
    };
});

const tip = computed(() => {
    if (dailyAdvice.value?.text) {
        return dailyAdvice.value.text;
    }

    const temperature = sensors.value.find((sensor) => sensor.type === 'temperature');
    if (temperature?.status === 'high') {
        return `La temperatura del agua está alta (${formatValue(temperature.value)} °C). Evita que suba del máximo del cultivo.`;
    }
    if (temperature?.status === 'ok') {
        return `La temperatura del agua está en ${formatValue(temperature.value)} °C, dentro del rango ideal.`;
    }

    return 'El consejo del día se genera al abrir el Dashboard (1 vez al día). También puedes pedirlo manualmente.';
});

const tipSourceLabel = computed(() => {
    if (dailyAdvice.value?.source === 'gemini') {
        return 'Gemini · consejo del día';
    }
    if (dailyAdvice.value?.source === 'local') {
        return 'Hydrolab · consejo del día';
    }

    return 'Consejo rápido';
});

const requestDailyAdvice = () => {
    adviceForm.post(route('dashboard.daily-advice'), {
        preserveScroll: true,
        onSuccess: () => refresh(),
    });
};

const chartData = computed(() => ({
    labels: chart.value.labels,
    datasets: (chart.value.series ?? [])
        .filter((series) => (series.data ?? []).some((value) => value !== null && value !== undefined))
        .map((series) => ({
        label: series.name,
        data: series.data,
        borderColor: themeFor(series.type).color,
        backgroundColor: themeFor(series.type).color,
        tension: 0.35,
        borderWidth: 2.2,
        pointRadius: (chart.value.labels?.length ?? 0) > 28 ? 0 : 3,
        pointHoverRadius: 5,
        spanGaps: true,
    })),
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                usePointStyle: true,
                pointStyle: 'circle',
                boxWidth: 8,
                padding: 18,
                color: '#6B7C93',
                font: { size: 12, family: 'Plus Jakarta Sans' },
            },
        },
        tooltip: {
            backgroundColor: '#1A2B48',
            padding: 10,
            titleFont: { family: 'Plus Jakarta Sans' },
            bodyFont: { family: 'Plus Jakarta Sans' },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: {
                maxTicksLimit: 8,
                color: '#94A3B8',
                font: { size: 11, family: 'Plus Jakarta Sans' },
            },
            border: { display: false },
        },
        y: {
            grid: {
                color: 'rgba(148, 163, 184, 0.18)',
                drawBorder: false,
            },
            ticks: {
                color: '#94A3B8',
                font: { size: 11, family: 'Plus Jakarta Sans' },
            },
            border: { display: false },
        },
    },
};

const lastUpdatedLabel = computed(() => {
    if (!lastUpdatedAt.value) {
        return 'En vivo';
    }

    return new Intl.DateTimeFormat('es-CO', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    }).format(lastUpdatedAt.value);
});

const pumpLabel = computed(() => {
    if (device.value?.pump_on === true) {
        return 'Encendida';
    }

    if (device.value?.pump_on === false) {
        return 'Apagada';
    }

    return 'Sin dato';
});

const setHours = (hours) => {
    router.get(
        route('dashboard'),
        { hours },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-extrabold tracking-tight text-hl-navy sm:text-3xl">
                ¡Hola, {{ firstName }}! 👋
            </h1>
            <p class="mt-1 text-sm text-hl-muted">
                Aquí tienes el estado actual de tu cultivo hidropónico.
            </p>
        </template>

        <template #actions>
            <div
                class="hl-glass inline-flex items-center gap-2 rounded-2xl border border-white/70 px-3 py-2.5 text-sm font-semibold text-hl-navy shadow-card"
            >
                <span
                    class="h-2.5 w-2.5 rounded-full"
                    :class="autoRefresh && device?.is_online ? 'bg-hl-green hl-live-dot' : device?.is_online ? 'bg-hl-green' : 'bg-slate-300'"
                />
                {{ autoRefresh ? 'En vivo' : 'Pausado' }}
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                    {{ lastUpdatedLabel }}
                </span>
            </div>
            <div
                class="hl-glass inline-flex items-center gap-2 rounded-2xl border border-white/70 px-3 py-2.5 text-sm font-extrabold text-hl-navy shadow-card"
            >
                <span class="text-xs font-semibold text-slate-500">Temp.</span>
                <template v-if="temperatureSensor?.value !== null && temperatureSensor?.value !== undefined">
                    {{ formatValue(temperatureSensor.value) }}
                    <span class="text-xs font-semibold text-slate-500">°C</span>
                </template>
                <template v-else>--</template>
            </div>
            <div
                class="hl-glass inline-flex items-center gap-2 rounded-2xl border border-white/70 px-3 py-2.5 text-sm font-semibold shadow-card"
                :class="device?.pump_on === true ? 'text-emerald-600' : device?.pump_on === false ? 'text-slate-500' : 'text-hl-navy'"
            >
                <span
                    class="h-2.5 w-2.5 rounded-full"
                    :class="device?.pump_on === true ? 'bg-hl-green hl-live-dot' : 'bg-slate-300'"
                />
                Bomba {{ pumpLabel }}
            </div>
            <button
                type="button"
                class="hl-glass inline-flex items-center gap-2 rounded-2xl border border-white/70 px-3 py-2.5 text-sm font-semibold text-hl-navy shadow-card"
                @click="autoRefresh = !autoRefresh"
            >
                <span
                    class="h-2 w-2 rounded-full"
                    :class="autoRefresh ? 'bg-hl-green' : 'bg-slate-300'"
                />
                Auto-actualización
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">4s</span>
            </button>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl bg-hl-blue px-4 py-2.5 text-sm font-semibold text-white shadow-card hover:bg-blue-600 disabled:opacity-70"
                :disabled="fetching"
                @click="refresh"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5 19A8 8 0 0 0 19 8M19 5A8 8 0 0 0 5 16" />
                </svg>
                Actualizar ahora
            </button>
        </template>

        <div class="space-y-5">
            <p v-if="liveError" class="text-sm font-semibold text-amber-600">{{ liveError }}</p>
            <div
                v-if="sensors.length === 0"
                class="hl-card px-6 py-12 text-center text-slate-500"
            >
                No hay sensores configurados. Ejecuta el seeder del dispositivo.
            </div>

            <div
                v-else
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
            >
                <article
                    v-for="sensor in sensors"
                    :key="sensor.id"
                    class="flex flex-col rounded-[24px] bg-white px-5 pt-5 shadow-card"
                    :class="sensor.type === 'temperature' ? 'ring-2 ring-[#5BA8FF]/35' : ''"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                            :class="themeFor(sensor.type).iconWrap"
                        >
                            <MetricIcon :type="sensor.type" />
                        </span>
                        <p class="text-sm font-semibold text-[#0A1F44]">{{ sensor.name }}</p>
                    </div>

                    <div class="mt-5 flex flex-1 flex-col items-center text-center">
                        <p class="text-[32px] font-extrabold leading-none tracking-tight text-[#0A1F44]">
                            <template v-if="sensor.value !== null">
                                {{ formatValue(sensor.value) }}
                                <span class="align-super text-base font-bold">{{ sensor.unit }}</span>
                            </template>
                            <template v-else>--</template>
                        </p>
                        <span
                            class="mt-3 rounded-full px-3.5 py-1 text-xs font-bold"
                            :class="{
                                'bg-[#E9F8EE] text-[#22C55E]': sensor.status === 'ok',
                                'bg-amber-50 text-amber-600': sensor.status === 'low' || sensor.status === 'high',
                                'bg-slate-100 text-slate-500': sensor.status === 'unknown',
                            }"
                        >
                            {{ statusLabel[sensor.status] }}
                        </span>
                        <p class="mt-2 text-xs text-[#6E7C91]">
                            Rango: {{ sensor.min_threshold ?? '—' }} – {{ sensor.max_threshold ?? '—' }} {{ sensor.unit }}
                        </p>
                    </div>

                    <div class="-mx-5 mt-4">
                        <Sparkline
                            :values="sensor.sparkline"
                            :color="themeFor(sensor.type).color"
                            :empty="!sensor.sparkline?.length"
                        />
                    </div>
                </article>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_300px]">
                <section class="hl-card p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-hl-navy">Historial de lecturas</h2>
                            <p class="text-sm text-slate-400">Evolución de los sensores en el periodo seleccionado</p>
                        </div>
                        <div class="flex rounded-full bg-slate-100 p-1">
                            <button
                                v-for="range in ranges"
                                :key="range.hours"
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs font-bold transition"
                                :class="hours === range.hours ? 'bg-white text-hl-blue shadow-sm' : 'text-slate-500 hover:text-hl-navy'"
                                @click="setHours(range.hours)"
                            >
                                {{ range.label }}
                            </button>
                        </div>
                    </div>
                    <div v-if="!chart.labels?.length" class="flex h-72 items-center justify-center text-sm text-slate-400">
                        Esperando lecturas del ESP32. Cuando envíe temperatura, aparecerá aquí.
                    </div>
                    <div v-else class="h-72">
                        <p v-if="chart.fallback" class="mb-3 text-xs font-medium text-amber-600">
                            No hay lecturas en este periodo. Mostrando las últimas temperaturas guardadas.
                        </p>
                        <Line :key="`${hours}-${chart.labels?.length}-${lastReadingTime}`" :data="chartData" :options="chartOptions" />
                    </div>
                </section>

                <div class="grid gap-4 sm:grid-cols-3 xl:grid-cols-1">
                    <section
                        class="rounded-[20px] border border-white/80 p-5 text-center shadow-card"
                        style="background: linear-gradient(180deg, #FFFCFA 0%, #FBF4EC 100%);"
                    >
                        <h3 class="text-left text-sm font-bold text-[#1A2B56]">Estado general</h3>
                        <div class="mt-3 flex justify-center">
                            <StatusGauge :percent="overall.percent" :tone="overall.tone" />
                        </div>
                        <p
                            class="mt-3 text-xl font-extrabold"
                            :class="{
                                'text-[#22C55E]': overall.tone === 'ok',
                                'text-hl-orange': overall.tone === 'warn',
                                'text-rose-500': overall.tone === 'bad',
                                'text-slate-400': overall.tone === 'unknown',
                            }"
                        >
                            {{ overall.label }}
                        </p>
                        <p class="mt-1 text-sm font-medium leading-5 text-[#1A2B56]">
                            {{ overall.detail }}
                        </p>
                    </section>

                    <section class="rounded-[20px] bg-white p-5 shadow-card">
                        <div class="flex items-center gap-4">
                            <span class="relative flex h-12 w-12 shrink-0 items-center justify-center">
                                <span class="absolute inset-[-6px] rounded-full bg-[#8EC5FF] opacity-35 blur-md" />
                                <span class="relative flex h-12 w-12 items-center justify-center rounded-full bg-[#E7F2FF] text-[#2F80ED]">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <rect x="4" y="5" width="16" height="15" rx="2.2" />
                                        <path stroke-linecap="round" d="M8 3.5v3M16 3.5v3M4 10h16" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 13v4M10 15h4" />
                                    </svg>
                                </span>
                            </span>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-[#1A2B56]">Última lectura</h3>
                                <p class="mt-1 text-sm font-semibold capitalize text-[#3CB54A]">{{ lastReadingDate }}</p>
                                <p class="text-sm font-semibold text-[#3CB54A]">{{ lastReadingTime }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[20px] bg-white p-5 shadow-card">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-[#1A2B56]">Proyección de cosecha</h3>
                                <p v-if="harvest" class="mt-1 text-sm font-semibold text-[#1A2B56]">
                                    {{ harvest.crop_label }} · {{ harvest.status_label }}
                                </p>
                                <p v-else class="mt-1 text-sm text-slate-500">
                                    Define la fecha de siembra en Dispositivo.
                                </p>
                            </div>
                            <span
                                v-if="harvest"
                                class="rounded-full px-2.5 py-1 text-[11px] font-bold"
                                :class="{
                                    'bg-emerald-50 text-emerald-600': harvest.status === 'ready' || harvest.status === 'ahead' || harvest.status === 'on_track',
                                    'bg-amber-50 text-amber-700': harvest.status === 'delayed',
                                }"
                            >
                                {{ harvest.progress_percent }}%
                            </span>
                        </div>

                        <template v-if="harvest">
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-[#3CB54A]"
                                    :style="{ width: `${harvest.progress_percent}%` }"
                                />
                            </div>
                            <p class="mt-3 text-sm leading-6 text-[#1A2B56]">{{ harvest.summary }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                Estimada: {{ harvest.harvest_date_label }} · {{ harvest.remaining_days }} día(s) restantes
                            </p>
                        </template>
                        <Link
                            v-else
                            :href="route('device')"
                            class="mt-3 inline-flex text-sm font-bold text-[#2F80ED] hover:underline"
                        >
                            Configurar siembra →
                        </Link>
                    </section>

                    <section class="relative overflow-hidden rounded-[20px] p-5 shadow-card" style="background: #FFFCFA;">
                        <div class="relative z-10 flex items-start gap-4 pr-[4.5rem]">
                            <span class="relative mt-0.5 flex h-12 w-12 shrink-0 items-center justify-center">
                                <span class="absolute inset-[-7px] rounded-full bg-[#FFB067] opacity-45 blur-[10px]" />
                                <span class="relative flex h-12 w-12 items-center justify-center rounded-full bg-[#FFE8D2] text-[#E88922]">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 18h6m-5 3h4M12 3a6 6 0 0 0-3.6 10.8c.6.5 1.1 1.4 1.1 2.2h5c0-.8.5-1.7 1.1-2.2A6 6 0 0 0 12 3Z" />
                                    </svg>
                                </span>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-[#1A2B56]">{{ tipSourceLabel }}</h3>
                                <p class="mt-1.5 text-sm leading-6 text-[#1A2B56]">{{ tip }}</p>
                                <div class="mt-3 flex flex-wrap gap-3">
                                    <button
                                        type="button"
                                        class="text-sm font-bold text-[#E88922] hover:underline disabled:opacity-60"
                                        :disabled="adviceForm.processing"
                                        @click="requestDailyAdvice"
                                    >
                                        {{ adviceForm.processing ? 'Generando…' : 'Pedir consejo IA ahora' }}
                                    </button>
                                    <Link
                                        :href="route('analysis')"
                                        class="text-sm font-bold text-[#2F80ED] hover:underline"
                                    >
                                        Análisis completo →
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <TipPlants />
                    </section>
                </div>
            </div>

            <section
                v-if="sensors.length"
                class="rounded-[24px] px-5 py-5 shadow-card sm:px-7"
                style="background: #FFF9F0;"
            >
                <h3 class="text-base font-extrabold text-[#1A2B56]">Resumen de parámetros</h3>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5">
                    <div
                        v-for="(sensor, index) in sensors"
                        :key="`summary-${sensor.id}`"
                        class="flex items-center gap-3 py-3 lg:px-4"
                        :class="index === 0 ? 'lg:pl-0' : 'lg:border-l lg:border-slate-200/80'"
                    >
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-white shadow-[0_6px_14px_rgba(26,43,72,0.16)]"
                            :class="themeFor(sensor.type).solid"
                        >
                            <MetricIcon :type="sensor.type" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium text-[#1A2B56]">
                                {{ summaryName[sensor.type] ?? sensor.name }}
                            </p>
                            <p class="mt-0.5 text-[15px] font-extrabold leading-5 text-[#1A2B56]">
                                <template v-if="sensor.value !== null">
                                    {{ formatValue(sensor.value) }} {{ sensor.unit }}
                                </template>
                                <template v-else>Sin datos</template>
                            </p>
                            <p
                                class="mt-0.5 text-sm font-semibold"
                                :class="{
                                    'text-[#22C55E]': sensor.status === 'ok',
                                    'text-amber-500': sensor.status === 'low' || sensor.status === 'high',
                                    'text-[#1A2B56]': sensor.status === 'unknown',
                                }"
                            >
                                {{ sensor.status === 'unknown' ? '--' : statusLabel[sensor.status] }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
