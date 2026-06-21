import qz from "qz-tray";
import http from "@/lib/http";

const STORAGE_KEY = (perfilId) => `buhopos_qz_impresora_${perfilId}`;
let seguridadConfigurada = false;

// ── Certificado y firma (sin popup de confianza) ───────────────────────────────

function configurarSeguridad() {
    if (seguridadConfigurada) return;

    qz.security.setCertificatePromise((resolve, reject) => {
        http.get("/api/etiquetas/qztray/cert", { responseType: "text" })
            .then((response) => resolve(response.data))
            .catch(reject);
    });

    qz.security.setSignatureAlgorithm("SHA512");

    qz.security.setSignaturePromise((toSign) => (resolve, reject) => {
        http.post("/api/etiquetas/qztray/sign", { request: toSign })
            .then((response) => resolve(response.data.signature))
            .catch(reject);
    });

    seguridadConfigurada = true;
}

// ── Conexión ──────────────────────────────────────────────────────────────────

export async function conectar() {
    if (qz.websocket.isActive()) return true;
    try {
        configurarSeguridad();
        await qz.websocket.connect({ retries: 2, delay: 1 });
        return true;
    } catch {
        return false;
    }
}

export function isConectado() {
    return qz.websocket.isActive();
}

// ── Impresoras ────────────────────────────────────────────────────────────────

export async function listarImpresoras() {
    if (!qz.websocket.isActive()) throw new Error("QZ Tray no está conectado.");
    const todas = await qz.printers.find();
    return Array.isArray(todas) ? todas : [todas];
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
    if (!qz.websocket.isActive()) throw new Error("QZ Tray no está conectado.");

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

    await qz.print(config, [{ type: "html", format: "plain", data: html }]);
}

export async function imprimirHtml(nombreImpresora, html, anchomm, altomm) {
    if (!qz.websocket.isActive()) throw new Error("QZ Tray no está conectado.");

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

    await qz.print(config, [{ type: "html", format: "plain", data: html }]);
}
