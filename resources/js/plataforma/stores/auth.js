import { defineStore } from "pinia";
import http from "@/lib/http";

export const usePlataformaAuthStore = defineStore("plataformaAuth", {
  state: () => ({ admin: null, booted: false, loading: false }),
  getters: { isAuth: (state) => !!state.admin },
  actions: {
    async bootstrap() {
      if (this.booted) return;
      try {
        const { data } = await http.get("/api/plataforma/me");
        this.admin = data;
      } catch { this._clearAdmin(); }
      finally { this.booted = true; }
    },
    async login(payload) {
      this.loading = true;
      try {
        await http.get("/sanctum/csrf-cookie");
        const { data } = await http.post("/api/plataforma/login", payload);
        this.admin = data;
        this.booted = true;
      } finally { this.loading = false; }
    },
    async logout() {
      await http.post("/api/plataforma/logout");
      this._clearAdmin();
      this.booted = true;
    },
    _clearAdmin() {
      this.admin = null;
      this.booted = false;
    },
  },
});
