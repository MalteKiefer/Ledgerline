import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@spa/stores/auth';

const routes: RouteRecordRaw[] = [
  { path: '/login', name: 'login', component: () => import('@spa/views/auth/Login.vue'), meta: { guest: true } },  {
    path: '/',
    component: () => import('@spa/views/Shell.vue'),
    children: [
      { path: '', name: 'home', component: () => import('@spa/views/Home.vue') },
      // Finance sections drive the in-page tab via the route param (nav submenu).
      { path: 'finance/:section?', name: 'finance', component: () => import('@spa/views/Finance.vue') },
      {
        path: 'profile',
        component: () => import('@spa/views/Profile.vue'),
        children: [
          { path: '', name: 'profile.account', component: () => import('@spa/views/profile/Account.vue') },
          { path: 'appearance', name: 'profile.appearance', component: () => import('@spa/views/profile/Appearance.vue') },
          { path: 'security', name: 'profile.security', component: () => import('@spa/views/profile/Security.vue') },
          { path: 'devices', name: 'profile.devices', component: () => import('@spa/views/profile/Devices.vue') },
          { path: 'data', name: 'profile.data', component: () => import('@spa/views/profile/Data.vue') },
        ],
      },
      { path: 'files', name: 'files', component: () => import('@spa/views/Files.vue') },
      { path: 'contacts', name: 'contacts', component: () => import('@spa/views/Contacts.vue') },
      { path: 'calendar', name: 'calendar', component: () => import('@spa/views/Calendar.vue') },
      // Settings sub-pages render directly in the main area (the Shell nav is the menu).
      { path: 'settings', redirect: '/settings/users' },
      { path: 'settings/users', name: 'settings.users', component: () => import('@spa/views/settings/Users.vue') },
      { path: 'settings/groups', name: 'settings.groups', component: () => import('@spa/views/settings/Groups.vue') },
      { path: 'settings/company', name: 'settings.company', component: () => import('@spa/views/settings/Company.vue') },
      { path: 'settings/backup', name: 'settings.backup', component: () => import('@spa/views/settings/Backup.vue') },
      { path: 'settings/security-log', name: 'settings.security-log', component: () => import('@spa/views/settings/SecurityLog.vue') },
      { path: 'settings/notifications', name: 'settings.notifications', component: () => import('@spa/views/settings/Notifications.vue') },
      { path: 'settings/notifications-config', name: 'settings.notifications-config', component: () => import('@spa/views/settings/NotificationsConfig.vue') },
      { path: 'settings/security', name: 'settings.security', component: () => import('@spa/views/settings/Security.vue') },
      { path: 'settings/files-limits', name: 'settings.files-limits', component: () => import('@spa/views/settings/FilesLimits.vue') },
      { path: 'settings/system', name: 'settings.system', component: () => import('@spa/views/settings/System.vue') },
      { path: 'settings/paperless', name: 'settings.paperless', component: () => import('@spa/views/settings/Paperless.vue') },
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
