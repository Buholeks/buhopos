import Swal from "sweetalert2";
import http from "@/lib/http";

/**
 * Detecta respuesta 409 recoverable del backend y ofrece restaurar el registro.
 * @param {Error} err        - El error capturado en el catch
 * @param {string} apiBase   - URL base del recurso, ej: "/api/marcas"
 * @param {Function} onRestored - Callback con el data restaurado
 * @returns {boolean} true si era recoverable (se manejó aquí), false si no
 */
export async function ofrecerRecuperacion(err, apiBase, onRestored) {
    const res = err.response;
    if (res?.status !== 409 || !res.data?.recoverable) return false;

    const { id, nombre, message } = res.data;

    const result = await Swal.fire({
        icon: "question",
        title: "Registro eliminado encontrado",
        text: message ?? `"${nombre}" fue eliminado anteriormente. ¿Deseas recuperarlo?`,
        showCancelButton: true,
        confirmButtonText: "Recuperar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#059669",
    });

    if (!result.isConfirmed) return true;

    try {
        const { data } = await http.post(`${apiBase}/${id}/restore`);
        await onRestored(data);
    } catch {
        Swal.fire({ icon: "error", title: "No se pudo recuperar el registro." });
    }

    return true;
}
