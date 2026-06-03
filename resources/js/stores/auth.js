import { defineStore } from "pinia";
import http from "../lib/http";
import { toastSuccess } from "../lib/alert";

export const useAuthStore = defineStore("auth", {
  state: () => ({
    user: null,
    booted: false,
    loading: false,
    sucursales: [],
    changingSucursal: false,
  }),

  getters: {
    isAuth: (s) => !!s.user,
    empresaId: (s) => s.user?.empresa_id ?? null,
    sucursalId: (s) => s.user?.sucursal_id ?? null,
    empresaNombre: (s) => s.user?.empresa?.nombre ?? "",
    sucursalNombre: (s) => s.user?.sucursal?.nombre ?? "",
    sucursalActivaId: (s) => s.user?.sucursal_id ?? null,
  },

  actions: {
    async bootstrap() {
      if (this.booted) return;
      try {
        await this.fetchUser();
      } finally {
        this.booted = true;
      }
    },

    async fetchSucursales() {
      try {
        const { data } = await http.get("/api/mis-sucursales");
        this.sucursales = data;
      } catch {
        this.sucursales = [];
      }
    },

    async cambiarSucursal(sucursal_id) {
      if (+sucursal_id === this.sucursalActivaId) return;

      this.changingSucursal = true;
      try {
        const { data } = await http.post("/api/cambiar-sucursal", { sucursal_id });
        this.user = data;

        toastSuccess(`Sucursal cambiada a ${data.sucursal?.nombre ?? "nueva sucursal"}`);
      } finally {
        this.changingSucursal = false;
      }
    },

    async fetchUser() {
      try {
        const { data } = await http.get("/api/me");
        this.user = data;
        await this.fetchSucursales();
      } catch (e) {
        const status = e?.response?.status;

        if (status === 401) {
          this.user = null;
          this.sucursales = [];
          return;
        }

        if (status === 403) {
          try {
            await http.post("/api/logout");
          } catch (_) {
            // El backend ya puede haber cerrado la sesión.
          }

          this.user = null;
          this.sucursales = [];
          this.changingSucursal = false;
          this.booted = false;

          throw new Error(e?.response?.data?.message || "Usuario sin acceso activo");
        }

        console.error("fetchUser error:", e);
        this.user = null;
        this.sucursales = [];
      }
    },

    async login({ email, password, remember = false }) {
      this.loading = true;
      try {
        await http.get("/sanctum/csrf-cookie");
        await http.post("/api/login", { email, password, remember });
        await this.fetchUser();
      } catch (e) {
        const status = e?.response?.status;

        if (status === 403 || status === 422) throw e;

        throw new Error("Error al iniciar sesión");
      } finally {
        this.loading = false;
      }
    },

    async logout() {
      await http.post("/api/logout");
      this.user = null;
      this.sucursales = [];
      this.changingSucursal = false;
      this.booted = false;
    },
  },
});
