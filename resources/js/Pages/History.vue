<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
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
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';

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
    sensors: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        required: true,
    },
    selectedSensor: Object,
    readings: {
        type: Array,
        default: () => [],
    },
    fallback: {
        type: Boolean,
        default: false,
    },
});

const sensorId = ref(props.filters.sensor_id);
const hours = ref(props.filters.hours);
const liveReadings = ref(props.readings);
const liveFallback = ref(props.fallback);

let timer = null;

watch(
    () => [props.readings, props.fallback],
    () => {
        liveReadings.value = props.readings;
        liveFallback.value = props.fallback;
    },
);

watch(
    () => props.filters,
    (value) => {
        sensorId.value = value.sensor_id;
        hours.value = value.hours;
    },
);

const refreshLive = async () => {
    try {
        const { data } = await axios.get(route('history.live', {}, false), {
            params: {
                sensor_id: sensorId.value,
                hours: hours.value,
            },
        });

        liveReadings.value = data.readings ?? [];
        liveFallback.value = Boolean(data.fallback);
    } catch {
        // Keep the last known readings if the live poll fails.
    }
};

onMounted(() => {
    timer = setInterval(refreshLive, 4000);
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }
});

const ranges = [
    { label: '1H', hours: 1 },
    { label: '6H', hours: 6 },
    { label: '24H', hours: 24 },
    { label: '7D', hours: 168 },
    { label: '30D', hours: 720 },
];

const colors = {
    temperature: '#3B82F6',
    humidity: '#38BDF8',
    ph: '#8B5CF6',
    ec: '#F97316',
    water_level: '#14B8A6',
};

const applyFilters = () => {
    router.get(
        route('history'),
        {
            sensor_id: sensorId.value,
            hours: hours.value,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const setHours = (value) => {
    hours.value = value;
    applyFilters();
};

const chartColor = computed(() => colors[props.selectedSensor?.type] ?? '#2F80ED');

const chartData = computed(() => ({
    labels: liveReadings.value.map((reading) =>
        new Date(reading.recorded_at).toLocaleString([], {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }),
    ),
    datasets: [
        {
            label: props.selectedSensor
                ? `${props.selectedSensor.name} (${props.selectedSensor.unit})`
                : 'Lecturas',
            data: liveReadings.value.map((reading) => reading.value),
            borderColor: chartColor.value,
            backgroundColor: `${chartColor.value}22`,
            fill: true,
            tension: 0.35,
            pointRadius: liveReadings.value.length > 40 ? 0 : 3,
            borderWidth: 2.2,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                usePointStyle: true,
                color: '#6B7C93',
                font: { family: 'Plus Jakarta Sans' },
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { maxTicksLimit: 8, color: '#94A3B8' },
            border: { display: false },
        },
        y: {
            grid: { color: 'rgba(148, 163, 184, 0.18)' },
            ticks: { color: '#94A3B8' },
            border: { display: false },
        },
    },
};
</script>

<template>
    <Head title="Historial" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-extrabold tracking-tight text-hl-navy sm:text-3xl">Historial</h1>
            <p class="mt-1 text-sm text-hl-muted">Consulta la evolución de cada sensor en el tiempo.</p>
        </template>

        <div class="space-y-5">
            <section class="hl-card p-5">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-hl-navy">Sensor</label>
                        <select
                            v-model="sensorId"
                            class="mt-1 rounded-2xl border-slate-200 bg-slate-50 shadow-none focus:border-hl-blue focus:ring-hl-blue"
                            @change="applyFilters"
                        >
                            <option
                                v-for="sensor in sensors"
                                :key="sensor.id"
                                :value="sensor.id"
                            >
                                {{ sensor.name }}
                            </option>
                        </select>
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
                    <p class="text-sm font-medium text-slate-400">
                        {{ liveReadings.length }} lecturas
                    </p>
                </div>
            </section>

            <section class="hl-card p-5">
                <p v-if="liveFallback && liveReadings.length" class="mb-3 text-xs font-medium text-amber-600">
                    No hay lecturas en este periodo. Mostrando las últimas guardadas.
                </p>
                <div v-if="liveReadings.length === 0" class="py-16 text-center text-slate-400">
                    Esperando lecturas del ESP32. Cuando envíe temperatura, aparecerá aquí.
                </div>
                <div v-else class="h-80">
                    <Line :key="`${hours}-${liveReadings.length}`" :data="chartData" :options="chartOptions" />
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
