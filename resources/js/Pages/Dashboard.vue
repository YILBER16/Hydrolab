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
import { computed, watch } from 'vue';
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
    crops: {
        type: Array,
        default: () => [],
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
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const sensorRange = (type, fallback) => {
    const sensor = (props.sensors ?? []).find((item) => item.type === type);
    if (sensor?.min_threshold != null && sensor?.max_threshold != null) {
        return [Number(sensor.min_threshold), Number(sensor.max_threshold)];
    }

    return fallback;
};

const initialCrop = props.device?.active_crop ?? 'general';
const initialProfile = props.crops.find((crop) => crop.id === initialCrop) ?? props.crops[0];
const [initialTempMin, initialTempMax] = sensorRange('temperature', initialProfile?.temperature ?? [18, 28]);
const [initialPhMin, initialPhMax] = sensorRange('ph', initialProfile?.ph ?? [5.5, 6.5]);

const cropForm = useForm({
    crop: initialCrop,
    temperature_min: initialTempMin,
    temperature_max: initialTempMax,
    ph_min: initialPhMin,
    ph_max: initialPhMax,
    planted_at: props.device?.planted_at ?? '',
    harvest_days: props.device?.harvest_days ?? initialProfile?.harvest_days ?? 40,
});

const selectedCrop = computed(
    () => props.crops.find((crop) => crop.id === cropForm.crop) ?? props.crops[0],
);

const applyCropDefaults = (cropId) => {
    const crop = props.crops.find((item) => item.id === cropId);
    if (!crop) {
        return;
    }

    cropForm.temperature_min = crop.temperature[0];
    cropForm.temperature_max = crop.temperature[1];
    cropForm.ph_min = crop.ph[0];
    cropForm.ph_max = crop.ph[1];
    cropForm.harvest_days = crop.harvest_days;
};

watch(
    () => cropForm.crop,
    (cropId, previous) => {
        if (previous !== undefined && cropId !== previous) {
            applyCropDefaults(cropId);
        }
    },
);

watch(
    () => [
        device.value?.active_crop,
        device.value?.planted_at,
        device.value?.harvest_days,
        sensors.value?.find((s) => s.type === 'temperature')?.min_threshold,
        sensors.value?.find((s) => s.type === 'temperature')?.max_threshold,
        sensors.value?.find((s) => s.type === 'ph')?.min_threshold,
        sensors.value?.find((s) => s.type === 'ph')?.max_threshold,
    ],
    () => {
        if (cropForm.processing || cropForm.isDirty) {
            return;
        }

        if (device.value?.active_crop) {
            cropForm.crop = device.value.active_crop;
        }
        if (device.value?.planted_at !== undefined) {
            cropForm.planted_at = device.value.planted_at ?? '';
        }
        if (device.value?.harvest_days) {
            cropForm.harvest_days = device.value.harvest_days;
        }

        const liveSensors = sensors.value ?? props.sensors ?? [];
        const tempSensor = liveSensors.find((item) => item.type === 'temperature');
        const phSensor = liveSensors.find((item) => item.type === 'ph');
        const tempFallback = selectedCrop.value?.temperature ?? [18, 28];
        const phFallback = selectedCrop.value?.ph ?? [5.5, 6.5];

        cropForm.temperature_min = tempSensor?.min_threshold != null ? Number(tempSensor.min_threshold) : tempFallback[0];
        cropForm.temperature_max = tempSensor?.max_threshold != null ? Number(tempSensor.max_threshold) : tempFallback[1];
        cropForm.ph_min = phSensor?.min_threshold != null ? Number(phSensor.min_threshold) : phFallback[0];
        cropForm.ph_max = phSensor?.max_threshold != null ? Number(phSensor.max_threshold) : phFallback[1];

        cropForm.defaults({
            crop: cropForm.crop,
            temperature_min: cropForm.temperature_min,
            temperature_max: cropForm.temperature_max,
            ph_min: cropForm.ph_min,
            ph_max: cropForm.ph_max,
            planted_at: cropForm.planted_at,
            harvest_days: cropForm.harvest_days,
        });
    },
);

const saveCrop = () => {
    cropForm.patch(route('device.crop'), {
        preserveScroll: true,
        onSuccess: () => {
            cropForm.defaults({
                crop: cropForm.crop,
                temperature_min: cropForm.temperature_min,
                temperature_max: cropForm.temperature_max,
                ph_min: cropForm.ph_min,
                ph_max: cropForm.ph_max,
                planted_at: cropForm.planted_at,
                harvest_days: cropForm.harvest_days,
            });
            refresh();
        },
    });
};

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
            <h1 class="text-xl font-extrabold tracking-tight text-hl-navy sm:text-2xl lg:text-3xl">
                ¡Hola, {{ firstName }}! 👋
            </h1>
            <p class="mt-1 text-sm text-hl-muted">
                Estado actual de tu cultivo hidropónico.
            </p>
        </template>

        <template #actions>
            <div
                class="hl-glass inline-flex max-w-full items-center gap-2 rounded-2xl border border-white/70 px-2.5 py-2 text-xs font-semibold text-hl-navy shadow-card sm:px-3 sm:py-2.5 sm:text-sm"
            >
                <span
                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                    :class="autoRefresh && device?.is_online ? 'bg-hl-green hl-live-dot' : device?.is_online ? 'bg-hl-green' : 'bg-slate-300'"
                />
                <span>{{ autoRefresh ? 'En vivo' : 'Pausado' }}</span>
                <span class="hidden rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 sm:inline">
                    {{ lastUpdatedLabel }}
                </span>
            </div>
            <div
                class="hl-glass hidden items-center gap-2 rounded-2xl border border-white/70 px-3 py-2.5 text-sm font-semibold shadow-card lg:inline-flex"
                :class="device?.pump_on === true ? 'text-emerald-600' : 'text-hl-navy'"
            >
                <span
                    class="h-2.5 w-2.5 rounded-full"
                    :class="device?.pump_on === true ? 'bg-hl-green hl-live-dot' : 'bg-slate-300'"
                />
                Bomba {{ pumpLabel }}
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl bg-hl-blue px-3 py-2 text-xs font-semibold text-white shadow-card hover:bg-blue-600 disabled:opacity-70 sm:px-4 sm:py-2.5 sm:text-sm"
                :disabled="fetching"
                @click="refresh"
            >
                Actualizar
            </button>
        </template>

        <div class="space-y-4 sm:space-y-5">
            <p v-if="liveError" class="text-sm font-semibold text-amber-600">{{ liveError }}</p>
            <p v-if="flashSuccess" class="text-sm font-semibold text-emerald-600">{{ flashSuccess }}</p>

            <div
                v-if="sensors.length === 0"
                class="hl-card px-6 py-12 text-center text-slate-500"
            >
                No hay sensores configurados. Ejecuta el seeder del dispositivo.
            </div>

            <div
                v-else
                class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-5"
            >
                <article
                    v-for="sensor in sensors"
                    :key="sensor.id"
                    class="flex flex-col rounded-[20px] bg-white px-3 pt-4 shadow-card sm:rounded-[24px] sm:px-5 sm:pt-5"
                    :class="sensor.type === 'temperature' ? 'ring-2 ring-[#5BA8FF]/35' : ''"
                >
                    <div class="flex items-center gap-2 sm:gap-3">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full sm:h-11 sm:w-11"
                            :class="themeFor(sensor.type).iconWrap"
                        >
                            <MetricIcon :type="sensor.type" />
                        </span>
                        <p class="truncate text-xs font-semibold text-[#0A1F44] sm:text-sm">{{ sensor.name }}</p>
                    </div>

                    <div class="mt-3 flex flex-1 flex-col items-center text-center sm:mt-5">
                        <p class="text-[26px] font-extrabold leading-none tracking-tight text-[#0A1F44] sm:text-[32px]">
                            <template v-if="sensor.value !== null">
                                {{ formatValue(sensor.value) }}
                                <span class="align-super text-sm font-bold sm:text-base">{{ sensor.unit }}</span>
                            </template>
                            <template v-else>--</template>
                        </p>
                        <span
                            class="mt-2 rounded-full px-2.5 py-1 text-[11px] font-bold sm:mt-3 sm:px-3.5 sm:text-xs"
                            :class="{
                                'bg-[#E9F8EE] text-[#22C55E]': sensor.status === 'ok',
                                'bg-amber-50 text-amber-600': sensor.status === 'low' || sensor.status === 'high',
                                'bg-slate-100 text-slate-500': sensor.status === 'unknown',
                            }"
                        >
                            {{ statusLabel[sensor.status] }}
                        </span>
                        <p class="mt-2 text-[10px] leading-4 text-[#6E7C91] sm:text-xs">
                            Rango: {{ sensor.min_threshold ?? '—' }} – {{ sensor.max_threshold ?? '—' }} {{ sensor.unit }}
                        </p>
                    </div>

                    <div class="-mx-3 mt-3 sm:-mx-5 sm:mt-4">
                        <Sparkline
                            :values="sensor.sparkline"
                            :color="themeFor(sensor.type).color"
                            :empty="!sensor.sparkline?.length"
                        />
                    </div>
                </article>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.9fr)]">
                <div class="space-y-4">
                    <section class="hl-card p-4 sm:p-5">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <h2 class="text-base font-bold text-hl-navy sm:text-lg">Historial de lecturas</h2>
                                <p class="text-xs text-slate-400 sm:text-sm">
                                    Última lectura:
                                    <span class="font-semibold text-hl-navy">{{ lastReadingDate }} {{ lastReadingTime }}</span>
                                </p>
                            </div>
                            <div class="-mx-1 overflow-x-auto px-1">
                                <div class="inline-flex min-w-max rounded-full bg-slate-100 p-1">
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
                        </div>
                        <div v-if="!chart.labels?.length" class="flex h-56 items-center justify-center px-2 text-center text-sm text-slate-400 sm:h-72">
                            Esperando lecturas del ESP32. Cuando envíe temperatura, aparecerá aquí.
                        </div>
                        <div v-else class="h-56 sm:h-72">
                            <p v-if="chart.fallback" class="mb-3 text-xs font-medium text-amber-600">
                                No hay lecturas en este periodo. Mostrando las últimas temperaturas guardadas.
                            </p>
                            <Line :key="`${hours}-${chart.labels?.length}-${lastReadingTime}`" :data="chartData" :options="chartOptions" />
                        </div>
                    </section>

                    <section class="relative overflow-hidden rounded-[24px] p-4 shadow-card sm:p-5" style="background: #FFFCFA;">
                        <div class="relative z-10 flex items-start gap-3 pr-10 sm:gap-4 sm:pr-16">
                            <span class="relative mt-0.5 hidden h-12 w-12 shrink-0 items-center justify-center sm:flex">
                                <span class="absolute inset-[-7px] rounded-full bg-[#FFB067] opacity-45 blur-[10px]" />
                                <span class="relative flex h-12 w-12 items-center justify-center rounded-full bg-[#FFE8D2] text-[#E88922]">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 18h6m-5 3h4M12 3a6 6 0 0 0-3.6 10.8c.6.5 1.1 1.4 1.1 2.2h5c0-.8.5-1.7 1.1-2.2A6 6 0 0 0 12 3Z" />
                                    </svg>
                                </span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="text-sm font-bold text-[#1A2B56]">{{ tipSourceLabel }}</h3>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ overall.label }} · {{ overall.detail }}</p>
                                    </div>
                                    <StatusGauge :percent="overall.percent" :tone="overall.tone" :size="56" class="shrink-0 sm:hidden" />
                                    <StatusGauge :percent="overall.percent" :tone="overall.tone" :size="72" class="hidden shrink-0 sm:block" />
                                </div>
                                <p class="mt-2 text-sm leading-6 text-[#1A2B56]">{{ tip }}</p>
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
                        <TipPlants class="max-sm:scale-75 max-sm:origin-bottom-right" />
                    </section>
                </div>

                <aside class="space-y-4">
                    <section class="hl-card p-4 sm:p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                            <div class="min-w-0">
                                <h2 class="text-base font-bold text-hl-navy sm:text-lg">Cultivo activo</h2>
                                <p class="mt-1 text-sm text-slate-400">Edita rangos sin salir del Dashboard.</p>
                            </div>
                            <span class="w-fit shrink-0 rounded-full bg-hl-blue/10 px-2.5 py-1 text-[11px] font-bold text-hl-blue">
                                {{ device?.active_crop_label || 'Sin cultivo' }}
                            </span>
                        </div>

                        <label class="mt-4 block text-xs font-medium text-slate-500">
                            Tipo de cultivo
                            <select
                                v-model="cropForm.crop"
                                class="mt-1 w-full rounded-xl border-slate-200 text-base font-semibold text-hl-navy shadow-sm focus:border-hl-blue focus:ring-hl-blue sm:text-sm"
                            >
                                <option v-for="crop in crops" :key="crop.id" :value="crop.id">
                                    {{ crop.label }}
                                </option>
                            </select>
                        </label>
                        <p v-if="selectedCrop" class="mt-2 text-xs text-slate-500">{{ selectedCrop.notes }}</p>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <label class="text-xs font-medium text-slate-500">
                                Temp mín
                                <input v-model.number="cropForm.temperature_min" type="number" step="0.1" inputmode="decimal" class="mt-1 w-full rounded-xl border-slate-200 text-base shadow-sm focus:border-hl-blue focus:ring-hl-blue sm:text-sm" />
                            </label>
                            <label class="text-xs font-medium text-slate-500">
                                Temp máx
                                <input v-model.number="cropForm.temperature_max" type="number" step="0.1" inputmode="decimal" class="mt-1 w-full rounded-xl border-slate-200 text-base shadow-sm focus:border-hl-blue focus:ring-hl-blue sm:text-sm" />
                            </label>
                            <label class="text-xs font-medium text-slate-500">
                                pH mín
                                <input v-model.number="cropForm.ph_min" type="number" step="0.1" inputmode="decimal" class="mt-1 w-full rounded-xl border-slate-200 text-base shadow-sm focus:border-hl-blue focus:ring-hl-blue sm:text-sm" />
                            </label>
                            <label class="text-xs font-medium text-slate-500">
                                pH máx
                                <input v-model.number="cropForm.ph_max" type="number" step="0.1" inputmode="decimal" class="mt-1 w-full rounded-xl border-slate-200 text-base shadow-sm focus:border-hl-blue focus:ring-hl-blue sm:text-sm" />
                            </label>
                            <label class="text-xs font-medium text-slate-500">
                                Siembra
                                <input v-model="cropForm.planted_at" type="date" class="mt-1 w-full rounded-xl border-slate-200 text-base shadow-sm focus:border-hl-blue focus:ring-hl-blue sm:text-sm" />
                            </label>
                            <label class="text-xs font-medium text-slate-500">
                                Días cosecha
                                <input v-model.number="cropForm.harvest_days" type="number" min="7" max="180" inputmode="numeric" class="mt-1 w-full rounded-xl border-slate-200 text-base shadow-sm focus:border-hl-blue focus:ring-hl-blue sm:text-sm" />
                            </label>
                        </div>

                        <button
                            type="button"
                            class="mt-4 inline-flex w-full items-center justify-center rounded-2xl bg-hl-blue px-4 py-2.5 text-sm font-semibold text-white shadow-card hover:bg-blue-600 disabled:opacity-70"
                            :disabled="cropForm.processing || !device"
                            @click="saveCrop"
                        >
                            {{ cropForm.processing ? 'Guardando…' : 'Guardar cultivo y rangos' }}
                        </button>
                        <p class="mt-2 text-center text-[11px] text-slate-400">
                            Para crear nuevos tipos de cultivo ve a
                            <Link :href="route('device')" class="font-semibold text-hl-blue hover:underline">Dispositivo</Link>.
                        </p>
                    </section>

                    <section class="rounded-[20px] bg-white p-5 shadow-card">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-[#1A2B56]">Proyección de cosecha</h3>
                                <p v-if="harvest" class="mt-1 text-sm font-semibold text-[#1A2B56]">
                                    {{ harvest.crop_label }} · {{ harvest.status_label }}
                                </p>
                                <p v-else class="mt-1 text-sm text-slate-500">
                                    Indica la fecha de siembra arriba y guarda.
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
                                Estimada: {{ harvest.harvest_date_label }} · {{ harvest.remaining_days }} día(s)
                            </p>
                        </template>
                    </section>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
