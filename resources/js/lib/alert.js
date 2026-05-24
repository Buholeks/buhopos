import Swal from "sweetalert2";

/* ======================================================
   BASES
====================================================== */

/* Base para modales */
const modal = Swal.mixin({
  confirmButtonText: "Aceptar",
  cancelButtonText: "Cancelar",
  reverseButtons: true,
});

/* Base para toasts */
const baseToast = Swal.mixin({
  toast: true,
  position: "top-end",
  showConfirmButton: false,
  timer: 2500,
  timerProgressBar: true,
});

/* ======================================================
   MODALES
====================================================== */

/* ❓ Confirmación */
export const confirm = async ({
  title = "¿Estás seguro?",
  text = "Esta acción no se puede deshacer",
  confirmText = "Sí, eliminar",
  cancelText = "Cancelar",
  icon = "warning",
} = {}) => {
  const result = await modal.fire({
    title,
    text,
    icon,
    showCancelButton: true,
    confirmButtonText: confirmText,
    cancelButtonText: cancelText,
  });

  return result.isConfirmed;
};

/* ❌ Error (modal) */
export const error = (title = "Error", text = "Algo salió mal") => {
  return modal.fire({
    icon: "error",
    title,
    text,
  });
};

/* ⏳ Loader */
export const loading = (title = "Procesando...") => {
  Swal.fire({
    title,
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading(),
  });
};

export const close = () => Swal.close();

/* ======================================================
   TOASTS (ÉXITOS Y MENSAJES RÁPIDOS)
====================================================== */

/* ✅ Éxito */
export const toastSuccess = (title) =>
  baseToast.fire({
    icon: "success",
    title,
  });

/* ❌ Error */
export const toastError = (title) =>
  baseToast.fire({
    icon: "error",
    title,
  });

/* ⚠️ Warning */
export const toastWarning = (title) =>
  baseToast.fire({
    icon: "warning",
    title,
  });
