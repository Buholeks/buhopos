import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import { createPinia } from "pinia";
import BaseButton from "./components/ui/buttons/BaseButton.vue";
import BaseIconButton from "./components/ui/buttons/BaseIconButton.vue";
import BtnAceptar from "./components/ui/buttons/BtnAceptar.vue";
import BtnAceptarIcon from "./components/ui/buttons/BtnAceptarIcon.vue";
import BtnActualizar from "./components/ui/buttons/BtnActualizar.vue";
import BtnAgregar from "./components/ui/buttons/BtnAgregar.vue";
import BtnAgregarIcon from "./components/ui/buttons/BtnAgregarIcon.vue";
import BtnAnterior from "./components/ui/buttons/BtnAnterior.vue";
import BtnBuscar from "./components/ui/buttons/BtnBuscar.vue";
import BtnCancelar from "./components/ui/buttons/BtnCancelar.vue";
import BtnCerrar from "./components/ui/buttons/BtnCerrar.vue";
import BtnEditar from "./components/ui/buttons/BtnEditar.vue";
import BtnEditarIcon from "./components/ui/buttons/BtnEditarIcon.vue";
import BtnEliminar from "./components/ui/buttons/BtnEliminar.vue";
import BtnEliminarIcon from "./components/ui/buttons/BtnEliminarIcon.vue";
import BtnGuardar from "./components/ui/buttons/BtnGuardar.vue";
import BtnLimpiar from "./components/ui/buttons/BtnLimpiar.vue";
import BtnSiguiente from "./components/ui/buttons/BtnSiguiente.vue";
import BtnVolver from "./components/ui/buttons/BtnVolver.vue";

const app = createApp(App);

Object.entries({
    BaseButton,
    BaseIconButton,
    BtnAceptar,
    BtnAceptarIcon,
    BtnActualizar,
    BtnAgregar,
    BtnAgregarIcon,
    BtnAnterior,
    BtnBuscar,
    BtnCancelar,
    BtnCerrar,
    BtnEditar,
    BtnEditarIcon,
    BtnEliminar,
    BtnEliminarIcon,
    BtnGuardar,
    BtnLimpiar,
    BtnSiguiente,
    BtnVolver,
}).forEach(([name, component]) => {
    app.component(name, component);
});

app.use(createPinia()).use(router).mount("#app");
