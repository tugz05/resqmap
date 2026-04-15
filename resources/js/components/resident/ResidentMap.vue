<script setup lang="ts">
import 'leaflet/dist/leaflet.css';
import * as L from 'leaflet';
import { Loader2, RefreshCw } from 'lucide-vue-next';
import { nextTick, onMounted, onUnmounted, ref } from 'vue';

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps<{
    incidents: Array<{
        id: string;
        type_icon: string;
        type_label: string;
        severity: string;
        status: string;
        status_label: string;
        is_active: boolean;
        title: string;
        location: { latitude: number; longitude: number; address?: string; city?: string };
    }>;
}>();

// ─── State ────────────────────────────────────────────────────────────────────
const mapEl = ref<HTMLElement | null>(null);
const loading = ref(true);
const lastRefresh = ref(new Date());

let map: L.Map | null = null;
let rescuerLayer: L.LayerGroup | null = null;
let incidentLayer: L.LayerGroup | null = null;
let userMarker: L.CircleMarker | null = null;
let refreshTimer: ReturnType<typeof setInterval> | null = null;
let resizeHandler: (() => void) | null = null;

// ─── Marker factories ─────────────────────────────────────────────────────────
function makeIncidentIcon(emoji: string, severity: string): L.DivIcon {
    const colors: Record<string, string> = {
        low: '#22c55e',
        medium: '#eab308',
        high: '#f97316',
        critical: '#ef4444',
    };
    const ring = colors[severity] ?? '#ef4444';
    return L.divIcon({
        className: '',
        iconSize: [36, 36],
        iconAnchor: [18, 18],
        html: `
            <div style="
                position:relative;width:36px;height:36px;
                display:flex;align-items:center;justify-content:center;
                background:${ring}22;
                border:2px solid ${ring};
                border-radius:50%;
                font-size:16px;
                box-shadow: 0 2px 8px ${ring}66;
            ">
                ${emoji}
            </div>
        `,
    });
}

function makeRescuerIcon(): L.DivIcon {
    return L.divIcon({
        className: '',
        iconSize: [36, 36],
        iconAnchor: [18, 18],
        html: `
            <div style="
                position:relative;width:36px;height:36px;
                display:flex;align-items:center;justify-content:center;
                background:#3b82f6;
                border:2px solid #1d4ed8;
                border-radius:50%;
                box-shadow: 0 2px 8px #3b82f666;
            ">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/>
                </svg>
            </div>
        `,
    });
}

// ─── Plot incidents ───────────────────────────────────────────────────────────
function plotIncidents(): void {
    if (!incidentLayer) return;
    incidentLayer.clearLayers();

    for (const inc of props.incidents) {
        if (!inc.is_active) continue;
        const { latitude, longitude } = inc.location;
        if (!latitude || !longitude) continue;

        const marker = L.marker([latitude, longitude], {
            icon: makeIncidentIcon(inc.type_icon, inc.severity),
        });

        marker.bindPopup(`
            <div style="min-width:180px;font-family:system-ui,sans-serif">
                <div style="font-size:13px;font-weight:700;margin-bottom:4px">${inc.type_icon} ${inc.title}</div>
                <div style="font-size:11px;color:#64748b;margin-bottom:6px">${inc.location.address ?? inc.location.city ?? ''}</div>
                <span style="
                    display:inline-block;
                    padding:2px 8px;border-radius:99px;font-size:10px;font-weight:600;
                    background:#fef3c7;color:#92400e;
                ">${inc.status_label}</span>
            </div>
        `, { maxWidth: 220 });

        incidentLayer.addLayer(marker);
    }
}

// ─── Fetch and plot rescuers ──────────────────────────────────────────────────
async function fetchRescuers(): Promise<void> {
    if (!rescuerLayer || !map) return;
    try {
        const res = await fetch('/api/v1/location/rescuers', {
            headers: { Accept: 'application/json' },
            credentials: 'include',
        });
        if (!res.ok) return;
        const data = await res.json();
        const rescuers: Array<{ latitude: number; longitude: number; user?: { name: string } }> = data.rescuers ?? [];

        rescuerLayer.clearLayers();
        for (const r of rescuers) {
            const marker = L.marker([r.latitude, r.longitude], { icon: makeRescuerIcon() });
            marker.bindPopup(`
                <div style="font-family:system-ui,sans-serif">
                    <div style="font-size:12px;font-weight:700;color:#1d4ed8">🚑 Rescuer</div>
                    <div style="font-size:11px;color:#334155">${r.user?.name ?? 'Active Rescuer'}</div>
                </div>
            `);
            rescuerLayer.addLayer(marker);
        }

        lastRefresh.value = new Date();
    } catch {
        /* silently ignore network errors */
    }
}

// ─── Locate user ──────────────────────────────────────────────────────────────
function locateUser(): void {
    if (!map) return;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const { latitude, longitude } = pos.coords;
            if (userMarker) {
                userMarker.setLatLng([latitude, longitude]);
            } else {
                userMarker = L.circleMarker([latitude, longitude], {
                    radius: 8,
                    color: '#1d4ed8',
                    fillColor: '#3b82f6',
                    fillOpacity: 1,
                    weight: 3,
                }).addTo(map!).bindPopup('📍 You are here');
            }
            map!.setView([latitude, longitude], 15);
        },
        () => {
            // Default to Metro Manila if denied
            map?.setView([14.5995, 120.9842], 11);
        },
        { enableHighAccuracy: true, timeout: 8000 },
    );
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
    // Fix Leaflet default icon path in Vite environment
    delete (L.Icon.Default.prototype as Record<string, unknown>)._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });

    map = L.map(mapEl.value!, {
        zoomControl: false,
        attributionControl: false,
    }).setView([14.5995, 120.9842], 12);

    // Important for mobile/tab switches: force Leaflet to re-measure container.
    await nextTick();
    setTimeout(() => map?.invalidateSize(), 0);
    setTimeout(() => map?.invalidateSize(), 180);

    // Tile layer (dark style for ResQMap)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap © CARTO',
    }).addTo(map);

    L.control.zoom({ position: 'bottomright' }).addTo(map);
    L.control.attribution({ position: 'bottomright', prefix: false }).addTo(map);

    incidentLayer = L.layerGroup().addTo(map);
    rescuerLayer = L.layerGroup().addTo(map);

    plotIncidents();
    await fetchRescuers();
    locateUser();

    loading.value = false;

    // Auto-refresh rescuer positions every 30 seconds
    refreshTimer = setInterval(fetchRescuers, 30_000);

    // Keep map dimensions correct on viewport changes / orientation changes.
    resizeHandler = () => map?.invalidateSize();
    window.addEventListener('resize', resizeHandler);
});

onUnmounted(() => {
    if (refreshTimer) clearInterval(refreshTimer);
    if (resizeHandler) window.removeEventListener('resize', resizeHandler);
    map?.remove();
});

function manualRefresh(): void {
    fetchRescuers();
    plotIncidents();
}
</script>

<template>
    <div class="relative h-full w-full">

        <!-- Loading spinner -->
        <div
            v-if="loading"
            class="absolute inset-0 z-10 flex items-center justify-center bg-slate-900"
        >
            <div class="flex flex-col items-center gap-3 text-slate-400">
                <Loader2 class="h-8 w-8 animate-spin text-blue-400" />
                <p class="text-sm">Loading map…</p>
            </div>
        </div>

        <!-- Leaflet map container -->
        <div ref="mapEl" class="h-full w-full" />

        <!-- Legend overlay -->
        <div class="absolute left-3 top-3 z-[400] space-y-1.5 rounded-xl bg-black/70 px-3 py-2.5 text-xs text-white backdrop-blur-sm">
            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Legend</p>
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-red-500" /> Active Incident</div>
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-blue-500" /> Rescuer</div>
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-blue-400 ring-2 ring-blue-600" /> Your Location</div>
        </div>

        <!-- Refresh button -->
        <button
            class="absolute right-3 top-3 z-[400] flex items-center gap-1.5 rounded-xl bg-black/70 px-3 py-2 text-xs font-medium text-white backdrop-blur-sm hover:bg-black/90"
            @click="manualRefresh"
        >
            <RefreshCw class="h-3.5 w-3.5" />
            Refresh
        </button>

        <!-- Last updated -->
        <div class="absolute bottom-3 right-3 z-[400] rounded-xl bg-black/60 px-3 py-1.5 text-[10px] text-slate-400 backdrop-blur-sm">
            Updated {{ lastRefresh.toLocaleTimeString() }}
        </div>
    </div>
</template>
