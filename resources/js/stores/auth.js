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
    /** @type {string[]} Claves de permisos activos. ['*'] = acceso total. */
    permisos: [],
  }),

  getters: {
    isAuth:          (s) => !!s.user,
    empresaId:       (s) => s.user?.empresa_id ?? null,
    sucursalId:      (s) => s.user?.sucursal_id ?? null,
    empresaNombre:   (s) => s.user?.empresa?.nombre ?? "",
    empresaLogoUrl:  (s) => s.user?.empresa?.logo_url ?? null,
    sucursalNombre:  (s) => s.user?.sucursal?.nombre ?? "",
    sucursalActivaId:(s) => s.user?.sucursal_id ?? null,
    esSuperAdmin:    (s) => !!s.user?.es_super_admin,
    rolActual:       (s) => s.user?.rol ?? null,
  },

  actions: {
    /**
     * Devuelve true si el usuario tiene el permiso indicado.
     * - Super admin o sin rol asignado → siempre true (permisos = ['*'])
     * - Con rol → verifica la clave
     */
    can(clave) {
      if (!this.isAuth) return false;
      if (this.permisos.includes("*")) return true;
      return this.permisos.includes(clave);
    },

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
        this._setUser(data);
        toastSuccess(`Sucursal cambiada a ${data.sucursal?.nombre ?? "nueva sucursal"}`);
      } finally {
        this.changingSucursal = false;
      }
    },

    async fetchUser() {
      try {
        const { data } = await http.get("/api/me");
        this._setUser(data);
        await this.fetchSucursales();
      } catch (e) {
        const status = e?.response?.status;

        if (status === 401) {
          this._clearUser();
          return;
        }

        if (status === 403) {
          try { await http.post("/api/logout"); } catch (_) { /* ya cerró */ }
          this._clearUser();
          throw new Error(e?.response?.data?.message || "Usuario sin acceso activo");
        }

        console.error("fetchUser error:", e);
        this._clearUser();
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
        // fetchUser() puede relanzar un Error plano (sin .response) cuando
        // /api/me devuelve 403. En ese caso propagamos el mensaje original.
        if (e instanceof Error && e.message) throw e;
        throw new Error("Error al iniciar sesión");
      } finally {
        this.loading = false;
      }
    },

    async logout() {
      await http.post("/api/logout");
      this._clearUser();
    },

    // ── helpers privados ─────────────────────────────────────────────────────

    _setUser(data) {
      this.permisos = Array.isArray(data.permisos) ? data.permisos : [];
      // Guardar el user sin la clave 'permisos' para no duplicar en el estado
      const { permisos, ...rest } = data;
      this.user = rest;
    },

    _clearUser() {
      this.user = null;
      this.sucursales = [];
      this.permisos = [];
      this.changingSucursal = false;
      this.booted = false;
    },
  },
});
