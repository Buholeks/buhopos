import Swal from "sweetalert2";

export const alertColors = Object.freeze({
  primary: "#059669",
  danger: "#dc2626",
  warning: "#d97706",
  info: "#0284c7",
  neutral: "#64748b",
});

/*
 * Instancia compartida para alertas simples y flujos avanzados.
 * Los módulos que necesiten preConfirm, inputs o HTML personalizado deben
 * importar `swal` en lugar de importar SweetAlert2 directamente.
 */
export const swal = Swal.mixin({
  confirmButtonText: "Aceptar",
  cancelButtonText: "Cancelar",
  confirmButtonColor: alertColors.primary,
  cancelButtonColor: alertColors.neutral,
  denyButtonColor: alertColors.info,
  reverseButtons: true,
});

const baseToast = swal.mixin({
  toast: true,
  position: "top-end",
  showConfirmButton: false,
  timer: 2500,
  timerProgressBar: true,
});

export const confirm = async ({
  title = "¿Estás seguro?",
  text = "Esta acción no se puede deshacer",
  confirmText = "Sí, eliminar",
  cancelText = "Cancelar",
  icon = "warning",
  tone = "danger",
  html,
} = {}) => {
  const result = await swal.fire({
    title,
    text,
    html,
    icon,
    showCancelButton: true,
    confirmButtonColor: alertColors[tone] ?? alertColors.primary,
    confirmButtonText: confirmText,
    cancelButtonText: cancelText,
  });

  return result.isConfirmed;
};

export const error = (title = "Error", text = "Algo salió mal") => {
  return swal.fire({
    icon: "error",
    title,
    text,
    confirmButtonColor: alertColors.danger,
  });
};

export const loading = (title = "Procesando...") => {
  return swal.fire({
    title,
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => swal.showLoading(),
  });
};

export const close = () => swal.close();

export const toastSuccess = (title) =>
  baseToast.fire({
    icon: "success",
    title,
  });

export const toastError = (title) =>
  baseToast.fire({
    icon: "error",
    title,
  });

export const toastWarning = (title) =>
  baseToast.fire({
    icon: "warning",
    title,
  });

export const toastInfo = (title) =>
  baseToast.fire({
    icon: "info",
    title,
  });
