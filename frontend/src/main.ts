import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { i18nVue } from 'laravel-vue-i18n';
import '@spa/app.css';
import App from '@spa/App.vue';

// Apply the stored theme to the <html> class (Tailwind dark) on boot.
const storedDark = localStorage.getItem('ll_theme') === 'dark';
document.documentElement.classList.toggle('dark', storedDark);
import { router } from '@spa/router';
import { initialLocale } from '@spa/plugins/i18n';

const app = createApp(App);

app.use(createPinia());
app.use(i18nVue, {
  lang: initialLocale(),
  resolve: (lang: string) => import(`../../backend/lang/php_${lang}.json`),
});
app.use(router);

app.mount('#app');
