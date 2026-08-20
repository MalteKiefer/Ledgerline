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
import { setUnauthorizedHandler } from '@spa/api/client';
import { useAuthStore } from '@spa/stores/auth';

const app = createApp(App);

app.use(createPinia());
app.use(i18nVue, {
  lang: initialLocale(),
  resolve: (lang: string) => import(`../../backend/lang/php_${lang}.json`),
});
app.use(router);

// A token can expire while a page is open. The client clears it on any 401, but
// without this the in-memory user stayed set, so the router guard still saw an
// authenticated session and the app sat there half broken until a reload.
setUnauthorizedHandler(() => {
  const auth = useAuthStore();
  if (!auth.user) return;
  auth.user = null;
  const current = router.currentRoute.value;
  if (current.meta.public || current.meta.guest) return;
  void router.replace({ name: 'login', query: { redirect: current.fullPath } });
});

app.mount('#app');
