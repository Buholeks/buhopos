import http from '@/lib/http';

let empresa = null;
let version = 0;
let consulta = null;
let ultimoExito = 0;
let memoria = {};
const key = id => `buhopos_ticket_config_empresa_${id}`;
const valido = data => data && typeof data === 'object' && !Array.isArray(data);

export function establecerEmpresaTicket(id) {
    const siguiente = id == null ? null : String(id);
    if (empresa === siguiente) return;
    empresa = siguiente;
    version++;
    consulta = null;
    ultimoExito = 0;
    memoria = {};
    // El caché histórico no identifica a su empresa: no se reutiliza.
    try { memoria = JSON.parse(localStorage.getItem(key(empresa)) || '{}'); } catch { /* sin caché */ }
    if (!valido(memoria)) memoria = {};
}

export function obtenerConfigTicket() { return structuredClone(memoria); }

if (typeof window !== 'undefined') window.addEventListener('storage', event => {
    if (empresa && event.key === key(empresa)) {
        try {
            const data = JSON.parse(event.newValue || '{}');
            if (valido(data)) { memoria = data; ultimoExito = Date.now(); version++; }
        } catch { /* no cargar caché corrupto */ }
    }
});

function conservar(data, id) {
    memoria = valido(data) ? data : {};
    try { localStorage.setItem(key(id), JSON.stringify(memoria)); } catch { /* servidor ya confirmó */ }
    ultimoExito = Date.now();
}

export async function cargarConfigTicketDesdeServidor({ forzar = true } = {}) {
    if (!empresa) return obtenerConfigTicket();
    if (!forzar && ultimoExito && Date.now() - ultimoExito < 30000) return obtenerConfigTicket();
    if (consulta) return consulta;
    const id = empresa, revision = version;
    const peticion = (async () => {
        const { data } = await http.get('/api/ticket-config');
        if (revision === version && id === empresa) conservar(data, id);
        return obtenerConfigTicket();
    })();
    consulta = peticion;
    try { return await peticion; }
    finally { if (consulta === peticion) consulta = null; }
}

export async function guardarConfigTicket(config) {
    if (!empresa) throw new Error('No hay una empresa activa.');
    const id = empresa, revision = ++version;
    consulta = null;
    await http.put('/api/ticket-config', { config });
    if (id === empresa && revision === version) conservar(config, id);
}
