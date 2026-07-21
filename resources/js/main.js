import App from '@/App.vue';
import { registerPlugins } from '@core/utils/plugins';
import { createApp } from 'vue';

import axiosInstance from '@/plugins/axios';

import { Ability } from '@casl/ability';
import { abilitiesPlugin } from '@casl/vue';
import { buildAbilityForRules } from '@/plugins/ability.js';

import '@core-scss/template/index.scss';
import '@styles/print.scss';
import '@styles/styles.scss';
import '@styles/sweetalert-styles.scss';
import 'cropperjs/dist/cropper.css';

async function startApp() {
  try {
    await axiosInstance.get('/sanctum/csrf-cookie', { baseURL: '' });
    console.log("✅ CSRF cookie obtained successfully.");

    const app = createApp(App)

    const initialRules = buildAbilityForRules(null);
    app.use(abilitiesPlugin, new Ability(initialRules), {
      use$Can: true, 
    });

    registerPlugins(app)

    app.config.warnHandler = (msg, instance, trace) => {
      if (msg.includes('<Suspense>') || msg.includes('Suspense is an experimental feature')) return;
      console.warn(`[Vue warn]: ${msg}`, trace);
    };

    app.config.errorHandler = (err, instance, info) => {
      console.error("¡ERROR CAPTURADO POR VUE GLOBAL!", err);
      console.log("Instancia donde ocurrió:", instance);
      console.log("Información adicional de Vue:", info);
    }

    app.mount('#app')

  } catch (error) {
    console.error("❌ CRITICAL: Could not get CSRF cookie. The app will not start.", error);
    document.body.innerHTML = '<div style="text-align: center; padding: 50px; font-family: sans-serif;"><h1>Error de Conexión</h1><p>No se pudo conectar con el servidor. Por favor, intente más tarde.</p></div>';
  }
}

startApp();

