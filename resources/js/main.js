import App from '@/App.vue';
import { registerPlugins } from '@core/utils/plugins';
import { createApp } from 'vue';

import axiosInstance from '@/plugins/axios';

import '@core-scss/template/index.scss';
import '@styles/print.scss';
import '@styles/styles.scss';
import '@styles/sweetalert-styles.scss';

async function startApp() {
  try {
    await axiosInstance.get('/sanctum/csrf-cookie');
    console.log("✅ CSRF cookie obtained successfully.");

    const app = createApp(App)

    registerPlugins(app)

    app.mount('#app')

  } catch (error) {
    console.error("❌ CRITICAL: Could not get CSRF cookie. The app will not start.", error);
    document.body.innerHTML = '<div style="text-align: center; padding: 50px; font-family: sans-serif;"><h1>Error de Conexión</h1><p>No se pudo conectar con el servidor. Por favor, intente más tarde.</p></div>';
  }
}

startApp();

