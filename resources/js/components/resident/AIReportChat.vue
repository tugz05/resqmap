<script setup lang="ts">
import {
    Camera,
    Loader2,
    Mic,
    MicOff,
    Send,
    Volume2,
    VolumeX,
    X,
} from 'lucide-vue-next';
import { nextTick, onMounted, onUnmounted, ref } from 'vue';

// ─── Props & Emits ────────────────────────────────────────────────────────────
const props = defineProps<{ user: { id: number; name: string } }>();
const emit = defineEmits<{ close: [] }>();

// ─── Types ────────────────────────────────────────────────────────────────────
type Stage =
    | 'intro' | 'type' | 'description' | 'severity'
    | 'location' | 'photo' | 'confirm' | 'submitting' | 'done' | 'error';

type OAIMessage = { role: 'user' | 'assistant'; content: string };

interface ChatMessage {
    id: string;
    from: 'ai' | 'user';
    text: string;
    isAudio?: boolean;
}

interface IncidentOption { value: string; label: string; icon: string; bisaya: string }

// ─── Report data ──────────────────────────────────────────────────────────────
const report = ref({
    type: '', type_label: '', type_bisaya: '',
    severity: '', severity_label: '',
    title: '', description: '',
    latitude: 0, longitude: 0,
    address: '', barangay: '', city: '', province: '',
    photos: [] as File[],
});

// ─── Chat & UI state ──────────────────────────────────────────────────────────
const stage = ref<Stage>('intro');
const messages = ref<ChatMessage[]>([]);
const oaiHistory = ref<OAIMessage[]>([]); // sent to OpenAI
const userInput = ref('');
const isTyping = ref(false);           // AI is "thinking"
const locationLoading = ref(false);
const submittedId = ref('');
const chatEl = ref<HTMLElement | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);

// ─── Voice state ──────────────────────────────────────────────────────────────
const voiceEnabled = ref(true);      // TTS on/off
const isListening = ref(false);      // STT active
const isSpeaking = ref(false);       // TTS playing
const voiceSupported = ref(false);   // browser supports STT
const interimTranscript = ref('');   // live STT preview
let recognition: InstanceType<typeof window.SpeechRecognition> | null = null;
let currentAudio: HTMLAudioElement | null = null;

// ─── Option sets ─────────────────────────────────────────────────────────────
const incidentTypes: IncidentOption[] = [
    { value: 'fire',       label: 'Fire',          icon: '🔥', bisaya: 'Sunog'    },
    { value: 'flood',      label: 'Flood',         icon: '🌊', bisaya: 'Baha'     },
    { value: 'medical',    label: 'Medical',       icon: '🚑', bisaya: 'Medikal'  },
    { value: 'earthquake', label: 'Earthquake',    icon: '🌍', bisaya: 'Linog'    },
    { value: 'landslide',  label: 'Landslide',     icon: '⛰️', bisaya: 'Landslide'},
    { value: 'accident',   label: 'Accident',      icon: '🚗', bisaya: 'Aksidente'},
    { value: 'missing',    label: 'Missing Person', icon: '👤', bisaya: 'Nawala'  },
    { value: 'other',      label: 'Other',         icon: '⚠️', bisaya: 'Uban'    },
];

const severityOptions: IncidentOption[] = [
    { value: 'low',      label: 'Low',      icon: '🟢', bisaya: 'Gamay'   },
    { value: 'medium',   label: 'Medium',   icon: '🟡', bisaya: 'Tunga'   },
    { value: 'high',     label: 'High',     icon: '🟠', bisaya: 'Grabe'   },
    { value: 'critical', label: 'Critical', icon: '🔴', bisaya: 'Kritikal'},
];

// ─── Helpers ──────────────────────────────────────────────────────────────────
function uid(): string { return Math.random().toString(36).slice(2); }

function getCsrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

const firstName = props.user.name.split(' ')[0];

function addUser(text: string): void {
    messages.value.push({ id: uid(), from: 'user', text });
    oaiHistory.value.push({ role: 'user', content: text });
    nextTick().then(scrollToBottom);
}

function addAIMessage(text: string): void {
    messages.value.push({ id: uid(), from: 'ai', text });
    oaiHistory.value.push({ role: 'assistant', content: text });
    nextTick().then(scrollToBottom);
}

function scrollToBottom(): void {
    if (chatEl.value) chatEl.value.scrollTop = chatEl.value.scrollHeight;
}

// ─── OpenAI API call ──────────────────────────────────────────────────────────
async function askAI(userMessage?: string): Promise<string> {
    isTyping.value = true;

    // Build history — optionally append the new user message before calling
    const messages: OAIMessage[] = userMessage
        ? [...oaiHistory.value, { role: 'user', content: userMessage }]
        : [...oaiHistory.value];

    try {
        const res = await fetch('/api/v1/ai/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({ messages }),
            credentials: 'include',
        });

        if (!res.ok) {
            const payload = await res.json().catch(() => ({}));
            const backendMessage = (payload as { message?: string }).message;

            if (res.status === 401) {
                throw new Error('Wala na ang imong session. Palihog i-login pag-usab.');
            }

            throw new Error(backendMessage ?? `API error (${res.status})`);
        }

        const data = await res.json();
        isTyping.value = false;
        return data.message as string;
    } catch (error) {
        isTyping.value = false;
        console.error('AI chat failed:', error);
        return error instanceof Error
            ? error.message
            : 'Pasensya, adunay problema sa koneksyon. Sulayi pag-usab.';
    }
}

// ─── OpenAI TTS ───────────────────────────────────────────────────────────────
async function speakWithOpenAI(text: string): Promise<void> {
    if (!voiceEnabled.value) return;

    // Cancel any active speech
    stopSpeaking();
    const plainText = stripMarkdown(text);
    let browserFallbackUsed = false;

    const startBrowserFallback = (): void => {
        if (browserFallbackUsed) return;
        browserFallbackUsed = true;
        speakBrowser(plainText);
    };

    // If OpenAI TTS takes too long, start speaking immediately via browser TTS.
    const fallbackTimer = setTimeout(() => {
        if (!isSpeaking.value) startBrowserFallback();
    }, 700);

    // Hard timeout for slow network/API responses.
    const controller = new AbortController();
    const requestTimeout = setTimeout(() => controller.abort(), 4500);

    try {
        const res = await fetch('/api/v1/ai/tts', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'audio/mpeg',
            },
            body: JSON.stringify({ text: plainText }),
            credentials: 'include',
            signal: controller.signal,
        });

        if (!res.ok) throw new Error('TTS error');

        // Browser fallback already started; avoid double-speaking.
        if (browserFallbackUsed) return;

        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        currentAudio = new Audio(url);
        currentAudio.onended = () => {
            isSpeaking.value = false;
            URL.revokeObjectURL(url);
        };
        currentAudio.onerror = () => {
            isSpeaking.value = false;
            if (!browserFallbackUsed) startBrowserFallback();
        };
        isSpeaking.value = true;
        await currentAudio.play();
    } catch {
        if (!browserFallbackUsed) startBrowserFallback();
    } finally {
        clearTimeout(fallbackTimer);
        clearTimeout(requestTimeout);
    }
}

function speakBrowser(text: string): void {
    if (!voiceEnabled.value || !window.speechSynthesis) return;
    window.speechSynthesis.cancel();
    const utt = new SpeechSynthesisUtterance(stripMarkdown(text));
    utt.lang = 'fil-PH';
    utt.rate = 0.88;
    utt.pitch = 1.0;
    // Prefer a local Filipino voice if available
    const voices = window.speechSynthesis.getVoices();
    const ph = voices.find((v) => v.lang.startsWith('fil') || v.lang === 'tl-PH');
    if (ph) utt.voice = ph;
    utt.onstart = () => { isSpeaking.value = true; };
    utt.onend = () => { isSpeaking.value = false; };
    utt.onerror = () => { isSpeaking.value = false; };
    window.speechSynthesis.speak(utt);
}

function stopSpeaking(): void {
    currentAudio?.pause();
    currentAudio = null;
    window.speechSynthesis?.cancel();
    isSpeaking.value = false;
}

function stripMarkdown(text: string): string {
    return text
        .replace(/\*\*(.*?)\*\*/g, '$1')
        .replace(/`(.*?)`/g, '$1')
        .replace(/\n/g, '. ');
}

// Speak and record into OAI history
async function aiSpeak(text: string): Promise<void> {
    addAIMessage(text);
    await speakWithOpenAI(text);
}

// ─── Speech Recognition (STT) ─────────────────────────────────────────────────
function initSpeechRecognition(): boolean {
    const SR = (window as any).SpeechRecognition ?? (window as any).webkitSpeechRecognition;
    if (!SR) return false;

    recognition = new SR() as InstanceType<typeof window.SpeechRecognition>;
    recognition.continuous = false;
    recognition.interimResults = true;
    recognition.maxAlternatives = 1;
    recognition.lang = 'fil-PH'; // closest supported to Cebuano

    recognition.onresult = (event: SpeechRecognitionEvent) => {
        const results = Array.from(event.results);
        const transcript = results.map((r) => r[0].transcript).join('');
        if (event.results[event.results.length - 1].isFinal) {
            userInput.value = transcript;
            interimTranscript.value = '';
            isListening.value = false;
            // Auto-send if we're in a text-input stage
            if (stage.value === 'description' || (stage.value === 'location' && report.value.latitude === 0)) {
                handleSend();
            }
        } else {
            interimTranscript.value = transcript;
        }
    };

    recognition.onend = () => { isListening.value = false; interimTranscript.value = ''; };
    recognition.onerror = () => { isListening.value = false; interimTranscript.value = ''; };
    return true;
}

function toggleListening(): void {
    if (!recognition) {
        if (!initSpeechRecognition()) return;
    }
    if (isListening.value) {
        recognition!.stop();
    } else {
        stopSpeaking();           // stop TTS before recording
        userInput.value = '';
        interimTranscript.value = '';
        isListening.value = true;
        recognition!.start();
    }
}

// ─── Conversation flow ────────────────────────────────────────────────────────
async function startIntro(): Promise<void> {
    // Seed the OAI history with the opening user request
    oaiHistory.value.push({
        role: 'user',
        content: `Kumusta! Ako si ${firstName}. Gusto ko mag-report og emergency.`,
    });

    const reply = await askAI();
    stage.value = 'type';
    await aiSpeak(reply);
}

async function selectType(opt: IncidentOption): Promise<void> {
    const userText = `${opt.icon} ${opt.bisaya} (${opt.label})`;
    addUser(userText);
    report.value.type = opt.value;
    report.value.type_label = opt.label;
    report.value.type_bisaya = opt.bisaya;

    stage.value = 'description';
    const reply = await askAI();
    await aiSpeak(reply);
}

async function submitDescription(): Promise<void> {
    const text = userInput.value.trim();
    if (!text) return;
    addUser(text);
    report.value.description = text;
    report.value.title = `${report.value.type_bisaya} — ${text.slice(0, 60)}${text.length > 60 ? '…' : ''}`;
    userInput.value = '';

    stage.value = 'severity';
    const reply = await askAI();
    await aiSpeak(reply);
}

async function selectSeverity(opt: IncidentOption): Promise<void> {
    addUser(`${opt.icon} ${opt.bisaya} (${opt.label})`);
    report.value.severity = opt.value;
    report.value.severity_label = opt.label;

    stage.value = 'location';
    const reply = await askAI();
    await aiSpeak(reply);
    await detectLocation();
}

async function detectLocation(): Promise<void> {
    locationLoading.value = true;
    try {
        const pos = await new Promise<GeolocationPosition>((resolve, reject) =>
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true, timeout: 10000,
            }),
        );
        const { latitude, longitude } = pos.coords;
        report.value.latitude = latitude;
        report.value.longitude = longitude;

        // Reverse-geocode
        try {
            const geo = await fetch(
                `https://nominatim.openstreetmap.org/reverse?lat=${latitude}&lon=${longitude}&format=json`,
            );
            const d = await geo.json();
            const a = d.address ?? {};
            report.value.address   = d.display_name ?? '';
            report.value.barangay  = a.neighbourhood ?? a.suburb ?? '';
            report.value.city      = a.city ?? a.town ?? a.municipality ?? '';
            report.value.province  = a.state ?? '';
        } catch { /* ignore geocode failure */ }

        locationLoading.value = false;
        const display = report.value.city
            ? `${report.value.barangay ? report.value.barangay + ', ' : ''}${report.value.city}`
            : `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;

        const userText = `Ang akong lokasyon mao ang: ${display}`;
        oaiHistory.value.push({ role: 'user', content: userText });
        messages.value.push({ id: uid(), from: 'user', text: `📍 ${display}` });
        scrollToBottom();

        const reply = await askAI();
        await aiSpeak(reply);
    } catch {
        locationLoading.value = false;
        report.value.latitude = 0;
        const reply = await askAI('Dili makuha ang akong GPS. Sulaton ko na lang ang akong lokasyon.');
        await aiSpeak(reply);
    }
}

async function confirmLocation(confirmed: boolean): Promise<void> {
    const userText = confirmed ? 'Oo, husto ang lokasyon.' : 'Dili, sulayi pag-usab o sulaton ko.';
    addUser(confirmed ? '✅ Oo, husto' : '❌ Dili, sulaton ko');

    if (confirmed) {
        stage.value = 'photo';
        const reply = await askAI(userText);
        await aiSpeak(reply);
    } else {
        report.value.latitude = 0;
        const reply = await askAI(userText);
        await aiSpeak(reply);
    }
}

async function handleManualLocation(): Promise<void> {
    const text = userInput.value.trim();
    if (!text) return;
    addUser(text);
    report.value.address = text;
    report.value.city = text;
    userInput.value = '';
    stage.value = 'photo';
    const reply = await askAI(`Ang akong lokasyon: ${text}`);
    await aiSpeak(reply);
}

async function handlePhotos(skip: boolean): Promise<void> {
    if (skip) addUser('⏩ Preskyu ang mga litrato');
    stage.value = 'confirm';

    const loc = report.value.city
        ? `${report.value.barangay ? report.value.barangay + ', ' : ''}${report.value.city}`
        : report.value.address || 'hindi makuha ang lokasyon';

    const summaryCtx = `
Nakolekta na ang tanan nga impormasyon:
- Klase: ${report.value.type_bisaya} (${report.value.type_label})
- Grabidad: ${report.value.severity_label}
- Deskripsyon: ${report.value.description}
- Lokasyon: ${loc}
- Mga litrato: ${report.value.photos.length > 0 ? report.value.photos.length + ' ka litrato' : 'wala'}
Ipakita ang summary sa user ug pangutana kung i-submit na.`.trim();

    const reply = await askAI(summaryCtx);
    await aiSpeak(reply);
}

function handlePhotoSelect(event: Event): void {
    const files = (event.target as HTMLInputElement).files;
    if (!files) return;
    report.value.photos = Array.from(files).slice(0, 5);
    const count = report.value.photos.length;
    addUser(`📷 ${count} ka litrato ang gipili`);
    handlePhotos(false);
}

async function submitReport(): Promise<void> {
    addUser('🚨 I-submit na ang report');
    stage.value = 'submitting';

    const submittingReply = await askAI('Okay, i-submit na ang emergency report karon.');
    await aiSpeak(submittingReply);

    try {
        const fd = new FormData();
        fd.append('type',        report.value.type);
        fd.append('severity',    report.value.severity);
        fd.append('title',       report.value.title);
        fd.append('description', report.value.description);
        fd.append('latitude',    String(report.value.latitude || 14.5995));
        fd.append('longitude',   String(report.value.longitude || 120.9842));
        if (report.value.address)  fd.append('address',  report.value.address);
        if (report.value.barangay) fd.append('barangay', report.value.barangay);
        if (report.value.city)     fd.append('city',     report.value.city);
        if (report.value.province) fd.append('province', report.value.province);
        report.value.photos.forEach((f, i) => fd.append(`photos[${i}]`, f));

        const res = await fetch('/api/v1/incidents', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
            body: fd,
            credentials: 'include',
        });

        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error((err as { message?: string }).message ?? 'Submission failed');
        }

        const data = await res.json();
        submittedId.value = (data as { incident?: { id?: string } }).incident?.id ?? '';
        stage.value = 'done';

        const doneReply = await askAI(
            `Gi-submit na ang emergency report. Ang reference ID mao ang: ${submittedId.value}. Pasalamati ang user ug sultihi sila nga magpabilin nga luwas.`,
        );
        await aiSpeak(doneReply);
    } catch (err) {
        stage.value = 'error';
        const errReply = await askAI(
            `Adunay problema sa pag-submit: ${err instanceof Error ? err.message : 'Unknown error'}. Pangayoa ang user nga sulayi pag-usab.`,
        );
        await aiSpeak(errReply);
    }
}

async function handleSend(): Promise<void> {
    if (!userInput.value.trim()) return;
    if (stage.value === 'description') await submitDescription();
    else if (stage.value === 'location' && report.value.latitude === 0) await handleManualLocation();
}

// ─── Markdown renderer ────────────────────────────────────────────────────────
function renderMarkdown(text: string): string {
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/`(.*?)`/g, '<code class="rounded bg-slate-700 px-1 py-0.5 text-xs font-mono text-emerald-300">$1</code>')
        .replace(/\n/g, '<br />');
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
    voiceSupported.value = initSpeechRecognition();
    // Pre-load voices (Chrome requires a gesture first, but this triggers the list)
    if (window.speechSynthesis) window.speechSynthesis.getVoices();
    startIntro();
});

onUnmounted(() => {
    recognition?.stop();
    stopSpeaking();
});
</script>

<template>
    <Transition name="slide-up">
        <div class="fixed inset-0 z-[100] flex flex-col bg-[#0d1117]">

            <!-- ═══ HEADER ══════════════════════════════════════════════════ -->
            <div class="flex shrink-0 items-center justify-between border-b border-white/8 px-4 py-3">
                <div class="flex items-center gap-3">
                    <!-- Bot avatar + speaking indicator -->
                    <div class="relative flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-slate-900/70 ring-1 ring-white/20 shadow-lg">
                        <img
                            src="/images/logo/resqmap.png"
                            alt="ResQMap"
                            class="h-8 w-8 rounded-full object-contain"
                        />
                        <!-- Pulse when speaking -->
                        <span
                            v-if="isSpeaking"
                            class="absolute -bottom-0.5 -right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-green-500 ring-2 ring-[#0d1117]"
                        >
                            <span class="absolute h-full w-full animate-ping rounded-full bg-green-400" />
                            <span class="h-2 w-2 rounded-full bg-white" />
                        </span>
                        <!-- Pulse when listening -->
                        <span
                            v-if="isListening"
                            class="absolute -bottom-0.5 -right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-red-500 ring-2 ring-[#0d1117]"
                        >
                            <span class="absolute h-full w-full animate-ping rounded-full bg-red-400" />
                            <span class="h-2 w-2 rounded-full bg-white" />
                        </span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white">ResQBot</p>
                        <p class="text-[11px] text-slate-400">
                            <span v-if="isListening" class="text-red-400">Nagpaminaw…</span>
                            <span v-else-if="isSpeaking" class="text-green-400">Nagsulti…</span>
                            <span v-else-if="isTyping" class="text-yellow-400">Naghunahuna…</span>
                            <span v-else>AI Emergency Assistant · Cebuano</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-1.5">
                    <!-- Voice on/off toggle -->
                    <button
                        class="flex h-9 w-9 items-center justify-center rounded-xl transition-colors"
                        :class="voiceEnabled ? 'bg-blue-600/30 text-blue-400 ring-1 ring-blue-600/50' : 'bg-white/8 text-slate-500'"
                        :title="voiceEnabled ? 'I-off ang boses' : 'I-on ang boses'"
                        @click="voiceEnabled = !voiceEnabled; stopSpeaking()"
                    >
                        <Volume2 v-if="voiceEnabled" class="h-4 w-4" />
                        <VolumeX v-else class="h-4 w-4" />
                    </button>

                    <button
                        class="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-white/8 hover:text-white"
                        @click="emit('close')"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- ═══ MESSAGES ════════════════════════════════════════════════ -->
            <div ref="chatEl" class="flex-1 space-y-3 overflow-y-auto px-4 py-4">

                <div
                    v-for="msg in messages"
                    :key="msg.id"
                    :class="['flex gap-2.5', msg.from === 'user' ? 'justify-end' : 'justify-start']"
                >
                    <div
                        v-if="msg.from === 'ai'"
                        class="mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-rose-500 to-orange-500 text-sm"
                    >
                        🤖
                    </div>
                    <div
                        :class="[
                            'max-w-[80%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed',
                            msg.from === 'ai'
                                ? 'rounded-tl-sm bg-[#1c2333] text-slate-200'
                                : 'rounded-tr-sm bg-gradient-to-br from-rose-600 to-orange-500 text-white',
                        ]"
                        v-html="msg.from === 'ai' ? renderMarkdown(msg.text) : msg.text"
                    />
                </div>

                <!-- Thinking indicator -->
                <div v-if="isTyping" class="flex items-center gap-2.5">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-rose-500 to-orange-500 text-sm">🤖</div>
                    <div class="flex items-center gap-1.5 rounded-2xl rounded-tl-sm bg-[#1c2333] px-4 py-3">
                        <span class="typing-dot h-2 w-2 rounded-full bg-slate-400" />
                        <span class="typing-dot h-2 w-2 rounded-full bg-slate-400 [animation-delay:0.18s]" />
                        <span class="typing-dot h-2 w-2 rounded-full bg-slate-400 [animation-delay:0.36s]" />
                    </div>
                </div>

                <!-- GPS loading -->
                <div v-if="locationLoading" class="flex items-center gap-2 rounded-2xl bg-[#1c2333] px-4 py-3 text-sm text-slate-400">
                    <Loader2 class="h-4 w-4 animate-spin text-blue-400" />
                    Gipangita ang imong GPS…
                </div>

                <!-- Live STT transcript -->
                <div v-if="interimTranscript" class="flex justify-end">
                    <div class="max-w-[80%] rounded-2xl rounded-tr-sm border border-rose-500/30 bg-rose-900/20 px-4 py-2.5 text-sm italic text-rose-300">
                        {{ interimTranscript }}…
                    </div>
                </div>
            </div>

            <!-- ═══ QUICK REPLY OPTIONS ══════════════════════════════════════ -->
            <div class="shrink-0 border-t border-white/8">

                <!-- ── INCIDENT TYPE ── -->
                <div v-if="stage === 'type' && !isTyping" class="p-3">
                    <div class="grid grid-cols-4 gap-2">
                        <button
                            v-for="opt in incidentTypes"
                            :key="opt.value"
                            class="flex flex-col items-center gap-1 rounded-xl bg-[#1c2333] py-3 text-center transition-all hover:bg-rose-900/40 hover:ring-1 hover:ring-rose-500/60 active:scale-95"
                            @click="selectType(opt)"
                        >
                            <span class="text-2xl">{{ opt.icon }}</span>
                            <span class="text-[10px] font-medium leading-tight text-slate-300">{{ opt.bisaya }}</span>
                        </button>
                    </div>
                </div>

                <!-- ── SEVERITY ── -->
                <div v-else-if="stage === 'severity' && !isTyping" class="flex gap-2 p-3">
                    <button
                        v-for="opt in severityOptions"
                        :key="opt.value"
                        class="flex flex-1 flex-col items-center gap-1.5 rounded-xl bg-[#1c2333] py-3 transition-all hover:bg-rose-900/40 hover:ring-1 hover:ring-rose-500/60 active:scale-95"
                        @click="selectSeverity(opt)"
                    >
                        <span class="text-2xl">{{ opt.icon }}</span>
                        <span class="text-[10px] font-semibold text-slate-300">{{ opt.bisaya }}</span>
                    </button>
                </div>

                <!-- ── LOCATION CONFIRM ── -->
                <div v-else-if="stage === 'location' && report.latitude !== 0 && !isTyping" class="flex gap-2 p-3">
                    <button
                        class="flex-1 rounded-xl bg-green-800/40 py-3 text-sm font-semibold text-green-300 ring-1 ring-green-700 hover:bg-green-800/60 active:scale-95"
                        @click="confirmLocation(true)"
                    >
                        ✅ Oo, husto
                    </button>
                    <button
                        class="flex-1 rounded-xl bg-[#1c2333] py-3 text-sm font-semibold text-slate-300 hover:bg-white/8 active:scale-95"
                        @click="confirmLocation(false)"
                    >
                        ✏️ Sulaton ko
                    </button>
                </div>

                <!-- ── PHOTO ── -->
                <div v-else-if="stage === 'photo' && !isTyping" class="flex gap-2 p-3">
                    <input ref="photoInput" type="file" accept="image/*" multiple class="hidden" @change="handlePhotoSelect" />
                    <button
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-[#1c2333] py-3 text-sm font-semibold text-slate-300 hover:bg-white/8 active:scale-95"
                        @click="photoInput?.click()"
                    >
                        <Camera class="h-4 w-4 text-blue-400" />
                        Magdugang og Litrato
                    </button>
                    <button
                        class="flex-1 rounded-xl bg-[#1c2333] py-3 text-sm text-slate-400 hover:bg-white/8 active:scale-95"
                        @click="handlePhotos(true)"
                    >
                        Preskyu →
                    </button>
                </div>

                <!-- ── CONFIRM SUBMIT ── -->
                <div v-else-if="stage === 'confirm' && !isTyping" class="p-3">
                    <button
                        class="relative w-full overflow-hidden rounded-xl py-4 text-sm font-bold text-white shadow-xl shadow-rose-600/30 active:scale-[0.98]"
                        @click="submitReport"
                    >
                        <span class="absolute inset-0 bg-gradient-to-r from-rose-600 via-red-500 to-orange-500" />
                        <span class="relative flex items-center justify-center gap-2">
                            🚨 I-submit ang Emergency Report
                        </span>
                    </button>
                </div>

                <!-- ── ERROR RETRY ── -->
                <div v-else-if="stage === 'error' && !isTyping" class="flex gap-2 p-3">
                    <button class="flex-1 rounded-xl bg-gradient-to-r from-rose-600 to-orange-500 py-3 text-sm font-bold text-white active:scale-95" @click="submitReport">
                        🔁 Sulayi Pag-usab
                    </button>
                    <button class="flex-1 rounded-xl bg-[#1c2333] py-3 text-sm text-slate-400 active:scale-95" @click="emit('close')">
                        Kanselahon
                    </button>
                </div>

                <!-- ── DONE ── -->
                <div v-else-if="stage === 'done' && !isTyping" class="p-3">
                    <button
                        class="w-full rounded-xl bg-green-800/40 py-4 text-sm font-semibold text-green-300 ring-1 ring-green-700 active:scale-95"
                        @click="emit('close')"
                    >
                        ✅ Tapos na — Isira
                    </button>
                </div>

                <!-- ── TEXT INPUT (description / manual location) ── -->
                <div
                    v-else-if="(stage === 'description' || (stage === 'location' && report.latitude === 0)) && !isTyping"
                    class="flex items-end gap-2 p-3"
                >
                    <!-- Voice toggle -->
                    <button
                        v-if="voiceSupported"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl transition-all"
                        :class="isListening
                            ? 'animate-pulse bg-red-600 shadow-lg shadow-red-500/40 text-white'
                            : 'bg-[#1c2333] text-slate-400 hover:bg-white/8 hover:text-white'"
                        @click="toggleListening"
                    >
                        <Mic v-if="!isListening" class="h-4 w-4" />
                        <MicOff v-else class="h-5 w-5" />
                    </button>

                    <!-- Text area -->
                    <textarea
                        v-model="userInput"
                        :placeholder="stage === 'description'
                            ? 'Isulat o isulti ang nahitabo…'
                            : 'Isulat ang imong barangay ug lungsod…'"
                        rows="2"
                        class="flex-1 resize-none rounded-2xl bg-[#1c2333] px-4 py-3 text-sm text-white placeholder-slate-500 outline-none ring-1 ring-white/10 focus:ring-rose-500/60"
                        @keydown.enter.exact.prevent="handleSend"
                    />

                    <!-- Send -->
                    <button
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-600 to-orange-500 text-white shadow-lg disabled:opacity-40"
                        :disabled="!userInput.trim() && !isListening"
                        @click="handleSend"
                    >
                        <Send class="h-4 w-4" />
                    </button>
                </div>

                <!-- Spacer while AI is typing -->
                <div v-else-if="isTyping" class="flex items-center justify-center py-4 text-xs text-slate-500">
                    <Loader2 class="mr-2 h-3.5 w-3.5 animate-spin" />
                    ResQBot naghunahuna…
                </div>

            </div>
        </div>
    </Transition>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1), opacity 0.25s;
}
.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}
.typing-dot {
    animation: typing-bounce 1s ease-in-out infinite;
}
@keyframes typing-bounce {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
    30%            { transform: translateY(-6px); opacity: 1; }
}
</style>
