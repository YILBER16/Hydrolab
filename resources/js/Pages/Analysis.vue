<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MetricIcon from '@/Components/MetricIcon.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useLiveSnapshot } from '@/composables/useLiveSnapshot';

const props = defineProps({
    device: Object,
    sensors: {
        type: Array,
        default: () => [],
    },
    crops: {
        type: Array,
        default: () => [],
    },
    selectedCrop: {
        type: String,
        default: 'general',
    },
    geminiConfigured: {
        type: Boolean,
        default: false,
    },
    hasLiveData: {
        type: Boolean,
        default: false,
    },
    analysis: {
        type: String,
        default: null,
    },
    analysisSource: {
        type: String,
        default: null,
    },
    error: {
        type: String,
        default: null,
    },
});

const { device, sensors } = useLiveSnapshot(props, {
    intervalMs: 4000,
    includeChart: false,
});

const form = useForm({
    crop: props.selectedCrop,
});

const selectedCropMeta = computed(
    () => props.crops.find((crop) => crop.id === form.crop) ?? props.crops[0],
);

const liveCount = computed(
    () => sensors.value.filter((sensor) => sensor.value !== null && sensor.value !== undefined).length,
);

const statusLabel = {
    ok: 'Óptimo',
    low: 'Bajo',
    high: 'Alto',
    unknown: 'Sin datos',
};

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

const pumpLabel = computed(() => {
    if (device.value?.pump_on === true) {
        return 'Encendida';
    }
    if (device.value?.pump_on === false) {
        return 'Apagada';
    }
    return 'Sin dato';
});

const sourceLabel = computed(() => {
    if (props.analysisSource === 'gemini') {
        return 'Gemini · con tus lecturas reales';
    }
    if (props.analysisSource === 'local') {
        return 'Hydrolab · reglas del cultivo con tus lecturas';
    }
    return null;
});

const submit = () => {
    form.post(route('analysis.run', {}, false), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Análisis IA" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-extrabold tracking-tight text-hl-navy sm:text-3xl">Análisis IA</h1>
            <p class="mt-1 text-sm text-hl-muted">
                Las recomendaciones usan la temperatura y el resto de parámetros que envía el ESP32.
            </p>
        </template>

        <div class="mx-auto max-w-5xl space-y-5">
            <section class="hl-card p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-hl-navy">Parámetros actuales</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ liveCount }} de {{ sensors.length }} sensores con lectura.
                            Bomba: <span class="font-semibold text-hl-navy">{{ pumpLabel }}</span>.
                            ESP32:
                            <span :class="device?.is_online ? 'font-semibold text-emerald-600' : 'font-semibold text-slate-400'">
                                {{ device?.is_online ? 'conectado' : 'desconectado' }}
                            </span>
                        </p>
                    </div>
                    <p
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="geminiConfigured ? 'bg-violet-50 text-violet-700' : 'bg-amber-50 text-amber-700'"
                    >
                        {{ geminiConfigured ? 'Gemini listo' : 'Análisis local (añade GEMINI_API_KEY para Gemini)' }}
                    </p>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <article
                        v-for="sensor in sensors"
                        :key="sensor.id"
                        class="rounded-2xl border border-slate-100 p-4"
                    >
                        <div class="flex items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-50 text-hl-blue">
                                <MetricIcon :type="sensor.type" />
                            </span>
                            <p class="text-sm font-semibold text-hl-navy">{{ sensor.name }}</p>
                        </div>
                        <p class="mt-3 text-2xl font-extrabold text-hl-navy">
                            <template v-if="sensor.value !== null && sensor.value !== undefined">
                                {{ formatValue(sensor.value) }}
                                <span class="text-sm font-semibold text-slate-400">{{ sensor.unit }}</span>
                            </template>
                            <template v-else>--</template>
                        </p>
                        <p
                            class="mt-1 text-xs font-bold"
                            :class="{
                                'text-emerald-600': sensor.status === 'ok',
                                'text-amber-600': sensor.status === 'low' || sensor.status === 'high',
                                'text-slate-400': sensor.status === 'unknown',
                            }"
                        >
                            {{ statusLabel[sensor.status] }}
                        </p>
                    </article>
                </div>
            </section>

            <section class="hl-card p-6">
                <h2 class="text-lg font-bold text-hl-navy">¿Qué estás cultivando?</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Así las recomendaciones se ajustan a temperatura y nutrientes típicos de esa planta.
                </p>

                <form class="mt-5 space-y-5" @submit.prevent="submit">
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="crop in crops"
                            :key="crop.id"
                            class="cursor-pointer rounded-full px-4 py-2 text-sm font-semibold transition"
                            :class="form.crop === crop.id ? 'bg-hl-blue text-white shadow-card' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        >
                            <input v-model="form.crop" type="radio" class="sr-only" :value="crop.id" />
                            {{ crop.label }}
                        </label>
                    </div>
                    <p v-if="selectedCropMeta" class="text-sm leading-6 text-slate-500">
                        {{ selectedCropMeta.notes }}
                    </p>

                    <button
                        type="submit"
                        class="inline-flex items-center rounded-2xl bg-hl-blue px-4 py-2.5 text-sm font-semibold text-white shadow-card hover:bg-blue-600 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Analizando lecturas…' : 'Pedir recomendación' }}
                    </button>
                </form>
            </section>

            <section
                v-if="error"
                class="rounded-[24px] border border-rose-100 bg-rose-50 p-5 text-rose-800"
            >
                {{ error }}
            </section>

            <section
                v-if="analysis"
                class="hl-card p-6"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-bold text-hl-navy">Recomendación del cultivo</h3>
                    <p v-if="sourceLabel" class="text-xs font-semibold text-slate-400">{{ sourceLabel }}</p>
                </div>
                <pre class="mt-4 whitespace-pre-wrap font-sans text-sm leading-7 text-slate-600">{{ analysis }}</pre>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
