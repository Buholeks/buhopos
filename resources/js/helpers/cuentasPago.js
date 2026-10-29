import { ref } from "vue";
import http from "@/lib/http";

// Terminales (tarjeta) y cuentas bancarias (transferencia) para el filtro
// secundario de forma de pago. Se cargan una sola vez y se comparten entre pantallas.
const opciones = ref({ terminales: [], cuentas: [] });
let carga = null;

export function useCuentasPago() {
    if (!carga) {
        carga = http
            .get("/api/reportes/cuentas-pago")
            .then(({ data }) => {
                opciones.value = {
                    terminales: data.terminales ?? [],
                    cuentas: data.cuentas ?? [],
                };
            })
            .catch(() => {
                carga = null; // reintenta en la siguiente pantalla
            });
    }

    /**
     * Qué campo extra mostrar para la forma de pago elegida:
     * { campo, label, opciones } o null si no aplica (efectivo, todas…).
     */
    function filtroPara(formaPago) {
        if (formaPago === "tarjeta") {
            return { campo: "terminal_pago_id", label: "Terminal", opciones: opciones.value.terminales };
        }
        if (formaPago === "transferencia") {
            return { campo: "cuenta_bancaria_id", label: "Cuenta", opciones: opciones.value.cuentas };
        }
        return null;
    }

    return { filtroPara };
}
