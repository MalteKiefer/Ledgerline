import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { i18nVue } from 'laravel-vue-i18n';
import App from '@spa/App.vue';
import { vuetify } from '@spa/plugins/vuetify';
import { router } from '@spa/router';
import { initialLocale } from '@spa/plugins/i18n';

const app = createApp(App);

app.use(createPinia());
app.use(vuetify);
app.use(i18nVue, {
  lang: initialLocale(),
  resolve: (lang: string) => import(`../../../lang/php_${lang}.json`),
});
app.use(router);

app.mount('#app');
