import { onMounted, onUnmounted, ref, toValue, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

export function useLiveSnapshot(props, options = {}) {
    const intervalMs = options.intervalMs ?? 4000;
    const includeChart = options.includeChart ?? true;
    const page = usePage();

    const device = ref(props.device);
    const sensors = ref(props.sensors ?? []);
    const chart = ref(props.chart ?? { labels: [], series: [], fallback: false });
    const lastUpdatedAt = ref(null);
    const liveError = ref(null);
    const fetching = ref(false);
    const autoRefresh = ref(true);

    let timer = null;

    const syncSharedDevice = (payload) => {
        if (!payload) {
            return;
        }

        page.props.hydrolabDevice = {
            name: payload.name,
            is_online: payload.is_online,
            pump_on: payload.pump_on,
        };
    };

    const apply = (payload) => {
        if (Object.prototype.hasOwnProperty.call(payload, 'device')) {
            const previousToken = device.value?.token_preview;
            device.value = payload.device
                ? {
                    ...payload.device,
                    token_preview: payload.device.token_preview ?? previousToken,
                }
                : null;
            syncSharedDevice(payload.device);
        }

        if (payload.sensors) {
            sensors.value = payload.sensors;
        }

        if (includeChart && payload.chart) {
            chart.value = payload.chart;
        }

        lastUpdatedAt.value = new Date();
        liveError.value = null;
    };

    const refresh = async () => {
        if (fetching.value) {
            return;
        }

        fetching.value = true;

        try {
            const { data } = await axios.get(route('dashboard.live', {}, false), {
                params: {
                    hours: toValue(props.hours) ?? 24,
                    chart: includeChart ? 1 : 0,
                },
            });

            apply(data);
        } catch {
            liveError.value = 'No se pudieron actualizar las lecturas.';
        } finally {
            fetching.value = false;
        }
    };

    const stop = () => {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    };

    const start = () => {
        stop();
        timer = setInterval(() => {
            if (autoRefresh.value) {
                refresh();
            }
        }, intervalMs);
    };

    watch(
        () => [props.device, props.sensors, props.chart],
        () => {
            device.value = props.device;
            sensors.value = props.sensors ?? [];
            if (includeChart) {
                chart.value = props.chart ?? { labels: [], series: [], fallback: false };
            }
        },
    );

    watch(
        () => props.hours,
        () => {
            if (includeChart && autoRefresh.value) {
                refresh();
            }
        },
    );

    onMounted(() => {
        refresh();
        start();
    });
    onUnmounted(stop);

    return {
        device,
        sensors,
        chart,
        lastUpdatedAt,
        liveError,
        fetching,
        autoRefresh,
        refresh,
        start,
        stop,
    };
}
