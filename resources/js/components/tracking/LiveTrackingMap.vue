<script setup lang="ts">
/**
 * LiveTrackingMap
 *
 * A Grab-style live tracking map for an active incident. Shows the incident
 * pin, the reporter's last known GPS position, and — when a rescuer has been
 * assigned — the rescuer's live position. Draws a polyline between rescuer
 * and incident so both sides can see how close they are.
 *
 * This component is stateless: the parent is responsible for polling and
 * passing the latest coordinates down via props.
 */

import 'leaflet/dist/leaflet.css';
import * as L from 'leaflet';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type LatLng = { latitude: number; longitude: number } | null | undefined;

const props = withDefaults(
    defineProps<{
        incident: { latitude: number; longitude: number; title?: string; type_icon?: string };
        reporter?: LatLng;
        rescuer?: LatLng;
        rescuerName?: string | null;
        rescuerStatus?: string | null;
        height?: string;
        distanceKm?: number | null;
        etaMinutes?: number | null;
        /** When true, removes the rounded/ring styling so the map can sit
         *  full-bleed inside a wrapper (e.g. the rescuer's Grab-style view). */
        flush?: boolean;
    }>(),
    {
        reporter: null,
        rescuer: null,
        rescuerName: null,
        rescuerStatus: null,
        height: '320px',
        distanceKm: null,
        etaMinutes: null,
        flush: false,
    },
);

const mapEl = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let incidentMarker: L.Marker | null = null;
let reporterMarker: L.Marker | null = null;
let rescuerMarker: L.Marker | null = null;
let routeLine: L.Polyline | null = null;

function divIcon(html: string): L.DivIcon {
    return L.divIcon({ className: '', iconSize: [40, 40], iconAnchor: [20, 20], html });
}

const incidentIcon = computed(() =>
    divIcon(`
        <div style="position:relative;width:40px;height:40px;display:flex;align-items:center;justify-content:center;background:#ef444422;border:2px solid #ef4444;border-radius:50%;font-size:18px;box-shadow:0 2px 10px #ef444466;">
            ${props.incident.type_icon ?? '🚨'}
        </div>
    `),
);

const reporterIcon = divIcon(`
    <div style="position:relative;width:34px;height:34px;display:flex;align-items:center;justify-content:center;background:#3b82f622;border:2px solid #3b82f6;border-radius:50%;font-size:14px;box-shadow:0 2px 8px #3b82f666;">
        🧍
    </div>
`);

const rescuerIcon = divIcon(`
    <div style="position:relative;width:40px;height:40px;">
        <span style="position:absolute;inset:0;background:#10b98155;border-radius:50%;animation:resq-ping 1.4s cubic-bezier(0,0,0.2,1) infinite;"></span>
        <div style="position:absolute;inset:3px;display:flex;align-items:center;justify-content:center;background:#10b981;border:2px solid white;border-radius:50%;font-size:18px;box-shadow:0 2px 10px #10b98199;">
            🚑
        </div>
    </div>
    <style>@keyframes resq-ping { 75%, 100% { transform: scale(1.8); opacity: 0; } }</style>
`);

function renderMarkers(): void {
    if (!map) return;

    if (incidentMarker) {
        incidentMarker.setLatLng([props.incident.latitude, props.incident.longitude]);
        incidentMarker.setIcon(incidentIcon.value);
    } else {
        incidentMarker = L.marker([props.incident.latitude, props.incident.longitude], { icon: incidentIcon.value })
            .addTo(map)
            .bindPopup(`<b>${props.incident.title ?? 'Incident'}</b>`);
    }

    if (props.reporter) {
        if (reporterMarker) {
            reporterMarker.setLatLng([props.reporter.latitude, props.reporter.longitude]);
        } else {
            reporterMarker = L.marker([props.reporter.latitude, props.reporter.longitude], { icon: reporterIcon })
                .addTo(map)
                .bindPopup('Reporter');
        }
    } else if (reporterMarker) {
        reporterMarker.remove();
        reporterMarker = null;
    }

    if (props.rescuer) {
        if (rescuerMarker) {
            rescuerMarker.setLatLng([props.rescuer.latitude, props.rescuer.longitude]);
        } else {
            rescuerMarker = L.marker([props.rescuer.latitude, props.rescuer.longitude], { icon: rescuerIcon })
                .addTo(map)
                .bindPopup(`<b>${props.rescuerName ?? 'Rescuer'}</b>${props.rescuerStatus ? `<br><small>${props.rescuerStatus}</small>` : ''}`);
        }

        if (routeLine) {
            routeLine.setLatLngs([
                [props.rescuer.latitude, props.rescuer.longitude],
                [props.incident.latitude, props.incident.longitude],
            ]);
        } else {
            routeLine = L.polyline(
                [
                    [props.rescuer.latitude, props.rescuer.longitude],
                    [props.incident.latitude, props.incident.longitude],
                ],
                {
                    color: '#10b981',
                    weight: 4,
                    opacity: 0.7,
                    dashArray: '8, 8',
                },
            ).addTo(map);
        }
    } else {
        if (rescuerMarker) {
            rescuerMarker.remove();
            rescuerMarker = null;
        }
        if (routeLine) {
            routeLine.remove();
            routeLine = null;
        }
    }

    // Auto-fit the viewport to include every visible marker.
    const points: L.LatLngExpression[] = [[props.incident.latitude, props.incident.longitude]];
    if (props.reporter) points.push([props.reporter.latitude, props.reporter.longitude]);
    if (props.rescuer) points.push([props.rescuer.latitude, props.rescuer.longitude]);

    if (points.length > 1) {
        map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 16 });
    } else {
        map.setView([props.incident.latitude, props.incident.longitude], 15);
    }
}

onMounted(async () => {
    await nextTick();
    if (!mapEl.value) return;

    map = L.map(mapEl.value, {
        zoomControl: true,
        attributionControl: false,
        scrollWheelZoom: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
    }).addTo(map);

    renderMarkers();
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    incidentMarker = null;
    reporterMarker = null;
    rescuerMarker = null;
    routeLine = null;
});

watch(
    () => [props.incident, props.reporter, props.rescuer],
    () => renderMarkers(),
    { deep: true },
);
</script>

<template>
    <div
        :class="[
            'relative overflow-hidden',
            flush ? 'h-full w-full' : 'rounded-2xl ring-1 ring-slate-200 dark:ring-slate-800',
        ]"
        :style="flush ? undefined : { height }"
    >
        <div ref="mapEl" class="absolute inset-0" />

        <div
            v-if="rescuer && (distanceKm !== null || etaMinutes !== null)"
            class="pointer-events-none absolute left-3 top-3 z-[500] flex items-center gap-2 rounded-full bg-white/95 px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-lg ring-1 ring-black/5 dark:bg-slate-900/95 dark:text-slate-100 dark:ring-white/10"
        >
            <span class="flex h-2 w-2">
                <span class="absolute inline-flex h-2 w-2 animate-ping rounded-full bg-emerald-400 opacity-75" />
                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
            </span>
            <span v-if="distanceKm !== null">{{ distanceKm.toFixed(2) }} km away</span>
            <span v-if="distanceKm !== null && etaMinutes !== null" class="text-slate-400">·</span>
            <span v-if="etaMinutes !== null">ETA ~{{ etaMinutes }} min</span>
        </div>

        <div class="pointer-events-none absolute bottom-3 left-3 z-[500] flex flex-col gap-1 text-[11px]">
            <div class="flex items-center gap-1.5 rounded-full bg-white/95 px-2.5 py-1 shadow ring-1 ring-black/5 dark:bg-slate-900/95 dark:ring-white/10">
                <span class="h-2 w-2 rounded-full bg-red-500" />
                <span class="font-medium text-slate-700 dark:text-slate-200">Incident</span>
            </div>
            <div
                v-if="reporter"
                class="flex items-center gap-1.5 rounded-full bg-white/95 px-2.5 py-1 shadow ring-1 ring-black/5 dark:bg-slate-900/95 dark:ring-white/10"
            >
                <span class="h-2 w-2 rounded-full bg-blue-500" />
                <span class="font-medium text-slate-700 dark:text-slate-200">Reporter</span>
            </div>
            <div
                v-if="rescuer"
                class="flex items-center gap-1.5 rounded-full bg-white/95 px-2.5 py-1 shadow ring-1 ring-black/5 dark:bg-slate-900/95 dark:ring-white/10"
            >
                <span class="h-2 w-2 rounded-full bg-emerald-500" />
                <span class="font-medium text-slate-700 dark:text-slate-200">Rescuer</span>
            </div>
        </div>
    </div>
</template>
