import axios from "axios";

const http = axios.create({
  baseURL: window.location.origin,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    "X-Requested-With": "XMLHttpRequest",
    Accept: "application/json",
  },
});

function terminalActual() {
  return localStorage.getItem("terminal") || "POS-01";
}

http.interceptors.request.use((config) => {
  config.headers = config.headers || {};
  config.headers["X-Terminal"] = terminalActual();
  return config;
});

// Rutas que manejan 401 por su cuenta — no redirigir
const RUTAS_TENANT_PROPIAS_401 = ["/api/me", "/api/login"];
const RUTAS_PLATAFORMA_PROPIAS_401 = ["/api/plataforma/me", "/api/plataforma/login"];

http.interceptors.response.use(
  (response) => response,
  async (error) => {
    const status = error?.response?.status;
    const url    = error?.config?.url ?? "";
    const esRutaPlataforma = url.includes("/api/plataforma");

    if (status === 401 && esRutaPlataforma && !RUTAS_PLATAFORMA_PROPIAS_401.some((r) => url.includes(r))) {
      // Importes diferidos para evitar ciclos de dependencia
      const { usePlataformaAuthStore } = await import("@/plataforma/stores/auth");
      const { default: router } = await import("@/router");
      const { toastWarning } = await import("@/lib/alert");

      const plataformaAuth = usePlataformaAuthStore();

      if (plataformaAuth.isAuth) {
        plataformaAuth._clearAdmin();
        toastWarning("Tu sesión ha expirado. Por favor inicia sesión nuevamente.");
        router.push({ name: "plataforma-login" });
      }
    } else if (status === 401 && !esRutaPlataforma && !RUTAS_TENANT_PROPIAS_401.some((r) => url.includes(r))) {
      // Importes diferidos para evitar ciclos de dependencia
      const { useAuthStore } = await import("@/stores/auth");
      const { default: router } = await import("@/router");
      const { toastWarning } = await import("@/lib/alert");

      const auth = useAuthStore();

      if (auth.isAuth) {
        auth._clearUser();
        toastWarning("Tu sesión ha expirado. Por favor inicia sesión nuevamente.");
        router.push({ name: "login" });
      }
    }

    if (status === 402 && !url.includes('/api/facturacion')) {
      const { default: router } = await import("@/router");
      if (router.currentRoute.value.name !== "facturacion") router.push({ name: "facturacion" });
    }

    return Promise.reject(error);
  }
);

export default http;
