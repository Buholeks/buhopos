// Valores compartidos del módulo de comisiones (deben coincidir con los modelos Comision*).
import { FolderTree, Layers, Package, ShoppingBag, UserRound } from "lucide-vue-next";

export const LISTAS_PRECIO = ["P.Venta", "P1", "P2", "P3", "P4", "P5", "MANUAL"];

export const BENEFICIARIOS = [
    { valor: "vendedor", label: "Vendedor", ayuda: "Quien se registra como vendedor en la venta", icono: UserRound },
    { valor: "cajero", label: "Cajero", ayuda: "Quien cobra la venta en caja", icono: ShoppingBag },
];

export const MODOS_ESQUEMA = [
    { valor: "general", label: "Igual para todo", ayuda: "Una sola comisión para cualquier producto", icono: Layers },
    { valor: "categoria", label: "Por categoría", ayuda: "Cada categoría con su comisión, más lo demás", icono: FolderTree },
    { valor: "producto", label: "Por producto", ayuda: "Cada producto con su comisión, más lo demás", icono: Package },
];

export function modoEsquema(valor) {
    return MODOS_ESQUEMA.find((m) => m.valor === valor) ?? MODOS_ESQUEMA[0];
}

export function beneficiario(valor) {
    return BENEFICIARIOS.find((b) => b.valor === valor) ?? BENEFICIARIOS[0];
}

export function etiquetaLista(lista) {
    return lista === "MANUAL" ? "Manual" : lista;
}

const fmt = new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" });
const fmtCorto = new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN", maximumFractionDigits: 2, minimumFractionDigits: 0 });

export function dinero(valor) {
    return fmt.format(Number(valor ?? 0));
}

/** Formato sin centavos cuando son cero: $1,000 · $999.99 */
export function dineroCorto(valor) {
    return fmtCorto.format(Number(valor ?? 0));
}

/** "5%" o "$10 por pieza" */
export function describirValor(tipo, valor) {
    return tipo === "porcentaje" ? `${Number(valor)}%` : `${dineroCorto(valor)} por pieza`;
}

/** Versión compacta para tablas y etiquetas: "5%" o "$10/pza" */
export function valorCorto(tipo, valor) {
    return tipo === "porcentaje" ? `${Number(valor)}%` : `${dineroCorto(valor)}/pza`;
}

export function describirRango(regla) {
    const { precio_min: min, precio_max: max } = regla;
    if (min == null && max == null) return null;
    if (min != null && max != null) return `${dineroCorto(min)} – ${dineroCorto(max)}`;
    return min != null ? `${dineroCorto(min)} o más` : `Hasta ${dineroCorto(max)}`;
}

export const MOTIVOS_PRUEBA = {
    aplicada: "Se aplicó la comisión",
    precio_apagado: "Ese precio no paga comisión en esta regla",
    fuera_de_rango: "El precio está fuera de los rangos y la regla indica sin comisión",
    sin_regla: "Ninguna regla del esquema aplica a este producto",
};

export function iniciales(nombre) {
    return (nombre ?? "")
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0].toUpperCase())
        .join("");
}
