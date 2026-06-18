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
const RUTAS_PROPIAS_401 = ["/api/me", "/api/login"];

http.interceptors.response.use(
  (response) => response,
  async (error) => {
    const status = error?.response?.status;
    const url    = error?.config?.url ?? "";

    if (status === 401 && !RUTAS_PROPIAS_401.some((r) => url.includes(r))) {
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

    return Promise.reject(error);
  }
);

export default http;
