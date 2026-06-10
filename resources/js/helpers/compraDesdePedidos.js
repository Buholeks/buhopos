const KEY = "buho_compra_desde_pedidos";

export function guardarCompraDesdePedidos(detalles) {
    sessionStorage.setItem(KEY, JSON.stringify(detalles));
}

export function consumirCompraDesdePedidos() {
    const raw = sessionStorage.getItem(KEY);
    sessionStorage.removeItem(KEY);
    if (!raw) return [];

    try {
        const detalles = JSON.parse(raw);
        return Array.isArray(detalles) ? detalles : [];
    } catch {
        return [];
    }
}
