import { createApp } from "vue";
import axios from "axios";
import App from "@/App.vue";

// Initialize CSRF cookie for Sanctum SPA authentication (runs in parallel with app setup)
const csrfPromise = axios.get('/sanctum/csrf-cookie', { withCredentials: true }).catch(() => {
    // Silent fail - will be retried on 419 errors
});

const app = createApp(App);

// pinia store
import { createPinia } from "pinia";
const pinia = createPinia();
app.use(pinia);

import router from "@/router";
app.use(router);

// main app css
import "@/assets/css/app.css";

// perfect scrollbar
import { PerfectScrollbarPlugin } from "vue3-perfect-scrollbar";
app.use(PerfectScrollbarPlugin);

//vue-meta
import { createHead } from "@unhead/vue/client";
const head = createHead();
app.use(head);

// set default settings
import appSetting from "@/app-setting";
appSetting.init();

//vue-i18n
import i18n from "@/i18n";
app.use(i18n);

//markdown editor
import VueEasymde from "vue3-easymde";
import "easymde/dist/easymde.min.css";
app.use(VueEasymde);

// popper
import Popper from "vue3-popper";
app.component("Popper", Popper);

// json to excel
import vue3JsonExcel from "vue3-json-excel";
app.use(vue3JsonExcel);

// Custom directives (v-can, v-role)
import { registerDirectives } from "@/directives";
registerDirectives(app);

app.mount("#app");
