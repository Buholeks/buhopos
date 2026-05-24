function buildStorageKey(empresaId, sucursalId, userId) {
    return `buho_pos_espera_${empresaId}_${sucursalId}_${userId}`;
}

export function getVentasEnEspera(empresaId, sucursalId, userId) {
    try {
        const key = buildStorageKey(empresaId, sucursalId, userId);
        const raw = localStorage.getItem(key);
        const parsed = JSON.parse(raw || "[]");
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

export function saveVentaEnEspera(empresaId, sucursalId, userId, venta) {
    const key = buildStorageKey(empresaId, sucursalId, userId);
    const actuales = getVentasEnEspera(empresaId, sucursalId, userId);

    actuales.unshift(venta);

    localStorage.setItem(key, JSON.stringify(actuales));
    return actuales;
}

export function removeVentaEnEspera(empresaId, sucursalId, userId, ventaId) {
    const key = buildStorageKey(empresaId, sucursalId, userId);
    const actuales = getVentasEnEspera(empresaId, sucursalId, userId);

    const filtradas = actuales.filter((v) => v.id !== ventaId);

    localStorage.setItem(key, JSON.stringify(filtradas));
    return filtradas;
}

export function clearVentasEnEspera(empresaId, sucursalId, userId) {
    const key = buildStorageKey(empresaId, sucursalId, userId);
    localStorage.removeItem(key);
}