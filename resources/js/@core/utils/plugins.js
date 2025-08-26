import fontawesome from '@/plugins/fontawesome.js';
import iconify from '@/plugins/iconify/index.js';
import layouts from '@/plugins/layouts.js';
import webfontloader from '@/plugins/webfontloader.js';
import setupRouter from '../../plugins/1.router/index.js';
import setupPinia from '../../plugins/2.pinia.js';
import setupVuetify from '../../plugins/vuetify/index.js';

export const registerPlugins = app => {

  setupPinia(app);
  setupVuetify(app);
  setupRouter(app);
  iconify();
  fontawesome(app);
  layouts(app);
  webfontloader();
}
