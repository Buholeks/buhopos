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

        // 401 = no autenticado (normal cuando estás en /login)
        if (status === 401) {
          this.user = null;
          this.sucursales = [];
          return;
        }

        // 403 = autenticado pero sin empresa/sucursal -> cerrar sesión y limpiar
        if (status === 403) {
          // intenta cerrar sesión en backend (si existe cookie/token vigente)
          try {
            await http.post("/api/logout");
          } catch (_) {
            // si falla, igual limpiamos en frontend
          }

          this.user = null;
          this.sucursales = [];
          this.changingSucursal = false;
          this.booted = false;

          // opcional: lanzar error para que el router/guard decida a dónde ir
          throw new Error(e?.response?.data?.message || "Usuario sin empresa o sucursal asignada");
        }

        // otros errores
        console.error("fetchUser error:", e);
        this.user = null;
        this.sucursales = [];
      }
    },


    async login({ email, password }) {
      this.loading = true;
      try {
        await http.get("/sanctum/csrf-cookie");
        await http.post("/api/login", { email, password });
        await this.fetchUser();
      } catch (e) {
        const status = e?.response?.status;

        if (status === 403) throw new Error(e?.response?.data?.message || "Usuario sin empresa o sucursal asignada");
        if (status === 422) throw new Error("Credenciales inválidas");

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
