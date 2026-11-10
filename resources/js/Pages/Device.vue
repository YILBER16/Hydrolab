<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MetricIcon from '@/Components/MetricIcon.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
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
});

const page = usePage();
const { device, sensors, lastUpdatedAt, liveError, fetching, autoRefresh, refresh } = useLiveSnapshot(props, {
    intervalMs: 4000,
    includeChart: false,
});

const cropForm = useForm({
    crop: props.device?.active_crop ?? 'general',
});

const selectedCrop = computed(
    () => props.crops.find((crop) => crop.id === cropForm.crop) ?? props.crops[0],
);

const flashSuccess = computed(() => page.props.flash?.success ?? null);

const saveCrop = () => {
    cropForm.patch(route('device.crop'), {
        preserveScroll: true,
        onSuccess: () => refresh(),
    });
};

watch(
    () => device.value?.active_crop,
    (crop) => {
        if (crop && !cropForm.processing) {
            cropForm.crop = crop;
        }
    },
);

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

const lastSeen = (value) => {
    if (!value) {
        return 'Sin registro';
    }

    return new Intl.DateTimeFormat('es-CO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const themes = {
    temperature: 'bg-blue-50 text-blue-500',
    humidity: 'bg-sky-50 text-sky-500',
    ph: 'bg-violet-50 text-violet-500',
    ec: 'bg-orange-50 text-orange-500',
    water_level: 'bg-teal-50 text-teal-500',
};
</script>

<template>
    <Head title="Dispositivo" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-extrabold tracking-tight text-hl-navy sm:text-3xl">Dispositivo</h1>
            <p class="mt-1 text-sm text-hl-muted">Información de conexión y sensores del ESP32.</p>
        </template>

        <template #actions>
            <div
                class="hl-glass inline-flex items-center gap-2 rounded-2xl border border-white/70 px-3 py-2.5 text-sm font-semibold text-hl-navy shadow-card"
            >
                <span
                    class="h-2.5 w-2.5 rounded-full"
                    :class="autoRefresh && device?.is_online ? 'bg-hl-green hl-live-dot' : 'bg-slate-300'"
                />
                {{ autoRefresh ? 'En vivo' : 'Pausado' }}
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                    {{ lastUpdatedLabel }}
                </span>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl bg-hl-blue px-4 py-2.5 text-sm font-semibold text-white shadow-card hover:bg-blue-600 disabled:opacity-70"
                :disabled="fetching"
                @click="refresh"
            >
                Actualizar
            </button>
        </template>

        <div v-if="!device" class="hl-card px-6 py-12 text-center text-slate-500">
            No hay dispositivo configurado. Ejecuta el seeder de Hydrolab.
        </div>

        <div v-else class="space-y-5">
            <p v-if="liveError" class="text-sm font-semibold text-amber-600">{{ liveError }}</p>
            <p v-if="flashSuccess" class="text-sm font-semibold text-emerald-600">{{ flashSuccess }}</p>

            <section class="hl-card p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-400">Nombre</p>
                        <h2 class="mt-1 text-2xl font-extrabold text-hl-navy">{{ device.name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ device.location ?? 'Sin ubicación' }}</p>
                    </div>
                    <span
                        class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold"
                        :class="device.is_online ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'"
                    >
                        <span class="h-2 w-2 rounded-full" :class="device.is_online ? 'bg-hl-green hl-live-dot' : 'bg-slate-400'" />
                        {{ device.is_online ? 'Conectado' : 'Desconectado' }}
                    </span>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-medium text-slate-400">Última conexión</p>
                        <p class="mt-1 text-sm font-semibold text-hl-navy">{{ lastSeen(device.last_seen_at) }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-medium text-slate-400">Bomba</p>
                        <p
                            class="mt-1 text-sm font-semibold"
                            :class="device.pump_on === true ? 'text-emerald-600' : 'text-hl-navy'"
                        >
                            {{ pumpLabel }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-medium text-slate-400">Estado</p>
                        <p class="mt-1 text-sm font-semibold text-hl-navy">
                            {{ device.is_active ? 'Activo' : 'Inactivo' }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-medium text-slate-400">Token API</p>
                        <p class="mt-1 truncate font-mono text-sm font-semibold text-hl-navy">{{ device.token_preview }}</p>
                    </div>
                </div>
            </section>

            <section class="hl-card p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-hl-navy">Cultivo activo</h3>
                        <p class="mt-1 text-sm text-slate-400">
                            Elige qué estás cultivando. Se ajustan los rangos de temperatura y pH; Gemini solo se consulta si salen de rango.
                        </p>
                    </div>
                    <span class="rounded-full bg-hl-blue/10 px-3 py-1 text-xs font-semibold text-hl-blue">
                        {{ device.active_crop_label || 'Sin cultivo' }}
                    </span>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <label
                        v-for="crop in crops"
                        :key="crop.id"
                        class="cursor-pointer rounded-2xl border p-4 transition"
                        :class="cropForm.crop === crop.id ? 'border-hl-blue bg-blue-50/70 shadow-card' : 'border-slate-100 hover:border-slate-200'"
                    >
                        <input v-model="cropForm.crop" type="radio" class="sr-only" :value="crop.id" />
                        <p class="font-bold text-hl-navy">{{ crop.label }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ crop.notes }}</p>
                        <p class="mt-3 text-xs font-semibold text-slate-600">
                            Temp {{ crop.temperature[0] }}–{{ crop.temperature[1] }} °C · pH {{ crop.ph[0] }}–{{ crop.ph[1] }}
                        </p>
                    </label>
                </div>

                <p v-if="selectedCrop" class="mt-4 text-sm text-slate-500">{{ selectedCrop.notes }}</p>

                <button
                    type="button"
                    class="mt-5 inline-flex items-center rounded-2xl bg-hl-blue px-4 py-2.5 text-sm font-semibold text-white shadow-card hover:bg-blue-600 disabled:opacity-70"
                    :disabled="cropForm.processing || cropForm.crop === device.active_crop"
                    @click="saveCrop"
                >
                    {{ cropForm.processing ? 'Guardando…' : 'Guardar cultivo' }}
                </button>
            </section>

            <section v-if="device.last_ai_advice" class="hl-card p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-lg font-bold text-hl-navy">Última alerta IA</h3>
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        {{ device.last_ai_source || 'ia' }} · {{ lastSeen(device.last_ai_alert_at) }}
                    </span>
                </div>
                <pre class="mt-4 whitespace-pre-wrap font-sans text-sm leading-relaxed text-slate-700">{{ device.last_ai_advice }}</pre>
            </section>

            <section class="hl-card p-6">
                <h3 class="text-lg font-bold text-hl-navy">Sensores</h3>
                <p class="mt-1 text-sm text-slate-400">Rangos ideales y última lectura de cada parámetro.</p>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <article
                        v-for="sensor in sensors"
                        :key="sensor.id"
                        class="flex items-start gap-3 rounded-2xl border border-slate-100 p-4"
                    >
                        <span
                            class="flex h-11 w-11 items-center justify-center rounded-2xl"
                            :class="themes[sensor.type] ?? themes.temperature"
                        >
                            <MetricIcon :type="sensor.type" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-semibold text-hl-navy">{{ sensor.name }}</p>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="sensor.is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ sensor.is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            <p class="mt-1 text-xl font-extrabold text-hl-navy">
                                {{ formatValue(sensor.value) }}
                                <span v-if="sensor.value !== null" class="text-sm font-medium text-slate-400">{{ sensor.unit }}</span>
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                Rango {{ sensor.min_threshold ?? '—' }} - {{ sensor.max_threshold ?? '—' }} {{ sensor.unit }}
                            </p>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
