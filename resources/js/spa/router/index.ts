import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@spa/stores/auth';

const routes: RouteRecordRaw[] = [
  { path: '/login', name: 'login', component: () => import('@spa/views/auth/Login.vue'), meta: { guest: true } },
  { path: '/two-factor-challenge', name: 'two-factor', component: () => import('@spa/views/auth/TwoFactor.vue'), meta: { guest: true } },
  {
    path: '/',
    component: () => import('@spa/views/Shell.vue'),
    children: [
      { path: '', name: 'home', component: () => import('@spa/views/Home.vue') },
      { path: 'profile', name: 'profile', component: () => import('@spa/views/Profile.vue') },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@spa/views/NotFound.vue') },
];

export const router = createRouter({
  history: createWebHistory('/spa/'),
  routes,
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (!auth.ready) await auth.bootstrap();
  if (!to.meta.guest && !auth.user) return { name: 'login', query: { redirect: to.fullPath } };
  if (to.meta.guest && auth.user) return { name: 'home' };
  return true;
});
