import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';


const terminal = localStorage.getItem("terminal") || "POS-01";
axios.defaults.headers.common["X-Terminal"] = terminal;