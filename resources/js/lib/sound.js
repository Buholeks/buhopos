const STORAGE_ENABLED = "buhopos.sonidos.habilitados";
const STORAGE_VOLUME = "buhopos.sonidos.volumen";
const VOLUMEN_PREDETERMINADO = 0.72;

let audioCtx = null;
let masterGain = null;
const ultimosSonidos = new Map();

function storageDisponible() {
    return typeof window !== "undefined" && "localStorage" in window;
}

function leerPreferencia(clave, valorPredeterminado) {
    if (!storageDisponible()) return valorPredeterminado;

    try {
        return window.localStorage.getItem(clave) ?? valorPredeterminado;
    } catch {
        return valorPredeterminado;
    }
}

function sonidosHabilitados() {
    return leerPreferencia(STORAGE_ENABLED, "1") !== "0";
}

function volumenPreferido() {
    const volumen = Number(
        leerPreferencia(STORAGE_VOLUME, String(VOLUMEN_PREDETERMINADO)),
    );
    return Number.isFinite(volumen)
        ? Math.min(1, Math.max(0, volumen))
        : VOLUMEN_PREDETERMINADO;
}

async function getContext() {
    if (typeof window === "undefined" || !sonidosHabilitados()) return null;

    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) return null;

    if (!audioCtx || audioCtx.state === "closed") {
        audioCtx = new AudioContextClass();
        masterGain = audioCtx.createGain();
        masterGain.gain.value = volumenPreferido();
        masterGain.connect(audioCtx.destination);
    }

    if (audioCtx.state === "suspended") {
        await audioCtx.resume();
    }

    return audioCtx.state === "running" ? audioCtx : null;
}

function programarTono(ctx, {
    frequency,
    endFrequency = frequency,
    duration,
    type = "sine",
    volume = 0.7,
    delay = 0,
}) {
    const inicio = ctx.currentTime + Math.max(0, delay);
    const fin = inicio + Math.max(0.025, duration);
    const frecuenciaInicial = Math.max(40, frequency);
    const frecuenciaFinal = Math.max(40, endFrequency);
    const nivel = Math.min(1, Math.max(0.001, volume));

    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();

    oscillator.type = type;
    oscillator.frequency.setValueAtTime(frecuenciaInicial, inicio);
    oscillator.frequency.exponentialRampToValueAtTime(frecuenciaFinal, fin);

    // Una envolvente breve evita chasquidos al iniciar y terminar el tono.
    gain.gain.setValueAtTime(0.001, inicio);
    gain.gain.exponentialRampToValueAtTime(nivel, inicio + 0.008);
    gain.gain.setValueAtTime(nivel, Math.max(inicio + 0.008, fin - 0.025));
    gain.gain.exponentialRampToValueAtTime(0.001, fin);

    oscillator.connect(gain);
    gain.connect(masterGain);

    oscillator.addEventListener("ended", () => {
        oscillator.disconnect();
        gain.disconnect();
    }, { once: true });

    oscillator.start(inicio);
    oscillator.stop(fin + 0.01);
}

async function reproducir(tonos, { intervaloMinimo = 45, canal = "general" } = {}) {
    const ahora = Date.now();
    const ultimoSonido = ultimosSonidos.get(canal) ?? 0;

    // Evita saturación si un lector dispara el mismo evento varias veces seguidas.
    if (ahora - ultimoSonido < intervaloMinimo) return;
    ultimosSonidos.set(canal, ahora);

    try {
        const ctx = await getContext();
        if (!ctx) return;

        tonos.forEach((tono) => programarTono(ctx, tono));
    } catch {
        // El sonido es una ayuda; nunca debe interrumpir la operación del POS.
    }
}

/** Producto agregado o acción correcta: corto, suave y fácil de reconocer. */
export function playSonidoExito() {
    void reproducir([
        {
            frequency: 720,
            endFrequency: 940,
            duration: 0.085,
            type: "triangle",
            volume: 0.88,
        },
    ], { canal: "exito" });
}

/** Código no encontrado o error: dos tonos graves claramente distinguibles. */
export function playSonidoError() {
    void reproducir([
        {
            frequency: 320,
            endFrequency: 235,
            duration: 0.12,
            type: "triangle",
            volume: 0.95,
        },
        {
            frequency: 235,
            endFrequency: 175,
            duration: 0.14,
            type: "triangle",
            volume: 0.9,
            delay: 0.1,
        },
    ], { intervaloMinimo: 100, canal: "error" });
}

/** Advertencia no crítica. */
export function playSonidoAdvertencia() {
    void reproducir([
        {
            frequency: 470,
            endFrequency: 470,
            duration: 0.09,
            type: "triangle",
            volume: 0.86,
        },
        {
            frequency: 470,
            endFrequency: 470,
            duration: 0.09,
            type: "triangle",
            volume: 0.82,
            delay: 0.13,
        },
    ], { intervaloMinimo: 100, canal: "advertencia" });
}

/** Operación completa, por ejemplo una compra o venta guardada. */
export function playSonidoCompletado() {
    void reproducir([
        {
            frequency: 590,
            endFrequency: 740,
            duration: 0.11,
            type: "triangle",
            volume: 0.84,
        },
        {
            frequency: 740,
            endFrequency: 990,
            duration: 0.14,
            type: "triangle",
            volume: 0.9,
            delay: 0.1,
        },
    ], { intervaloMinimo: 120, canal: "completado" });
}

export function setSonidosHabilitados(habilitados) {
    if (!storageDisponible()) return;

    try {
        window.localStorage.setItem(STORAGE_ENABLED, habilitados ? "1" : "0");
    } catch {
        // La preferencia simplemente no se conservará si el almacenamiento está bloqueado.
    }
}

export function setVolumenSonidos(volumen) {
    const valor = Math.min(1, Math.max(0, Number(volumen) || 0));

    if (storageDisponible()) {
        try {
            window.localStorage.setItem(STORAGE_VOLUME, String(valor));
        } catch {
            // La preferencia simplemente no se conservará si el almacenamiento está bloqueado.
        }
    }

    if (audioCtx && masterGain) {
        masterGain.gain.setTargetAtTime(valor, audioCtx.currentTime, 0.015);
    }
}
