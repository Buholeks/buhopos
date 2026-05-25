import axios from "axios";

const http = axios.create({
  baseURL: window.location.origin,
  withCredentials: true,
  headers: {
    "X-Requested-With": "XMLHttpRequest",
    Accept: "application/json",
  },
});

export default http;