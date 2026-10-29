import qz from "qz-tray";
import http from "@/lib/http";
import { printError } from './printing/printErrors.js';

const STORAGE_KEY = (perfilId) => `buhopos_qz_impresora_${perfilId}`;
let seguridadConfigurada = false;
let conexionEnCurso = null;
let impresorasCache = null;
let impresorasConsultadasEn = 0;
let consultaImpresorasEnCurso = null;
const IMPRESORAS_TTL_MS = 60000;

// ── Certificado y firma (sin popup de confianza) ───────────────────────────────

function configurarSeguridad() {
    if (seguridadConfigurada) return;

    qz.security.setCertificatePromise((resolve, reject) => {
        http.get("/api/etiquetas/qztray/cert", { responseType: "text" })
            .then((response) => resolve(response.data))
            .catch(reject);
    });

    qz.security.setSignatureAlgorithm("SHA512");
    // QZ 2.2.6 firma el SHA256 hexadecimal del JSON. El hook permite validar
    // el mensaje completo en servidor, incluso en desarrollo HTTP sin WebCrypto.
    const firmas = new Map();
    qz.api.setSha256Type(async (payload) => {
        const { data } = await http.post('/api/etiquetas/qztray/sign', { request: payload });
        firmas.set(data.hash, data.signature);
        return data.hash;
    });
    qz.security.setSignaturePromise((hash) => (resolve, reject) => {
        const signature = firmas.get(hash);
        firmas.delete(hash);
        if (signature) resolve(signature);
        else reject(new Error('No se autorizó el mensaje QZ.'));
    });

    seguridadConfigurada = true;
}

// ── Conexión ──────────────────────────────────────────────────────────────────

export async function conectar() {
    if (qz.websocket.isActive()) return true;
    if (conexionEnCurso) return conexionEnCurso;

    conexionEnCurso = (async () => {
        try {
            impresorasCache = null;
            configurarSeguridad();
            await qz.websocket.connect({ retries: 2, delay: 1 });
            return true;
        } catch {
            return false;
        } finally {
            conexionEnCurso = null;
        }
    })();

    return conexionEnCurso;
}

async function asegurarConexion() {
    const conectado = await conectar();
    if (!conectado) throw printError('QZ_UNAVAILABLE', 'Inicia QZ Tray y vuelve a conectar.');
}

// Transporte compartido: el contenido y los comandos pertenecen al llamador.
export async function enviarQz(nombreImpresora, opciones, datos) {
    await asegurarConexion();
    if (!nombreImpresora) throw printError('PRINTER_NOT_FOUND', 'Selecciona una impresora.');
    let found;
    try { found = await consultarImpresoras(); }
    catch (error) { throw printError('PRINT_ERROR', `No se pudo consultar la lista de impresoras QZ. No se envió el trabajo. ${error.message ?? error}`, error); }
    const printers = Array.isArray(found) ? found : [found];
    if (!printers.includes(nombreImpresora)) throw printError('PRINTER_NOT_FOUND', `La cola «${nombreImpresora}» no está disponible en este equipo.`);
    try { await qz.print(qz.configs.create(nombreImpresora, opciones), datos); }
    catch (error) {
        impresorasCache = null;
        throw printError('QZ_SEND_FAILED', `${error.message ?? error}. Revisa la cola y el papel antes de reintentar; el trabajo podría haber sido recibido.`, error);
    }
    return { status: 'submitted' }; // no confirma salida física
}

export function isConectado() {
    return qz.websocket.isActive();
}

// ── Impresoras ────────────────────────────────────────────────────────────────

async function consultarImpresoras(forzar = false) {
    if (consultaImpresorasEnCurso) return consultaImpresorasEnCurso;
    if (!forzar && impresorasCache && Date.now() - impresorasConsultadasEn < IMPRESORAS_TTL_MS) {
        return [...impresorasCache];
    }
    consultaImpresorasEnCurso = (async () => {
        try {
            const todas = await qz.printers.find();
            impresorasCache = Array.isArray(todas) ? todas : [todas];
            impresorasConsultadasEn = Date.now();
            return [...impresorasCache];
        } catch (error) {
            impresorasCache = null;
            throw error;
        } finally {
            consultaImpresorasEnCurso = null;
        }
    })();
    return consultaImpresorasEnCurso;
}

export async function listarImpresoras() {
    await asegurarConexion();
    // El selector siempre permite detectar impresoras recién instaladas.
    return consultarImpresoras(true);
}

// ── Preferencia local (por dispositivo) ──────────────────────────────────────

export function obtenerImpresoraLocal(perfilId) {
    return localStorage.getItem(STORAGE_KEY(perfilId)) || null;
}

export function guardarImpresoraLocal(perfilId, nombre) {
    if (nombre) localStorage.setItem(STORAGE_KEY(perfilId), nombre);
    else localStorage.removeItem(STORAGE_KEY(perfilId));
}

// ── Preferencia ticket ────────────────────────────────────────────────────────

const TICKET_KEY = "buhopos_qz_impresora_ticket";
export const obtenerImpresoraTicket = () => localStorage.getItem(TICKET_KEY) || null;
export const guardarImpresoraTicket = (nombre) =>
    nombre ? localStorage.setItem(TICKET_KEY, nombre) : localStorage.removeItem(TICKET_KEY);

// ── Impresión ─────────────────────────────────────────────────────────────────

export async function imprimirTicketHtml(nombreImpresora, html, anchomm = 80) {
    await asegurarConexion();

    const config = qz.configs.create(nombreImpresora, {
        size: { width: anchomm, height: 3000 },
        units: "mm",
        margins: 0,
        colorType: "blackwhite",
        duplex: false,
        copies: 1,
        scaleContent: false,
        rasterize: false,
    });

    await qz.print(config, [{ type: "pixel", format: "html", flavor: "plain", data: html }]);
}

export async function imprimirHtml(nombreImpresora, html, anchomm, altomm, { cintaContinua = false } = {}) {
    await asegurarConexion();

    // Usar pulgadas con DPI evita el redondeo de la resolución métrica en Java.
    const ancho = cintaContinua ? anchomm / 25.4 : anchomm;
    const alto = cintaContinua ? altomm / 25.4 : altomm;
    const config = qz.configs.create(nombreImpresora, {
        size: { width: ancho, height: alto, ...(cintaContinua ? { custom: true } : {}) },
        units: cintaContinua ? "in" : "mm",
        orientation: "portrait",
        margins: { top: 0, right: 0, bottom: 0, left: 0 },
        colorType: "blackwhite",
        duplex: false,
        copies: 1,
        scaleContent: false,
        rasterize: false,
        ...(cintaContinua ? { density: 300, interpolation: 'nearest-neighbor' } : {}),
    });

    if (cintaContinua) {
        const { etiquetaHtmlAPdf } = await import('./printing/etiquetaPdf.js');
        const pdf = await etiquetaHtmlAPdf(html, Number(anchomm), Number(altomm));
        await qz.print(config, [{ type: 'pixel', format: 'pdf', flavor: 'base64', data: pdf }]);
    } else {
        await qz.print(config, [{ type: 'pixel', format: 'html', flavor: 'plain', data: html }]);
    }
}
