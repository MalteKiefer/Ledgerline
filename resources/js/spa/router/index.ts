import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@spa/stores/auth';

const routes: RouteRecordRaw[] = [
  { path: '/login', name: 'login', component: () => import('@spa/views/auth/Login.vue'), meta: { guest: true } },
  // Public auth pages — reachable WITHOUT a token. `guest` bounces an already
  // signed-in user to home (same as login); `public` pages never bounce.
  { path: '/forgot-password', name: 'forgot-password', component: () => import('@spa/views/auth/ForgotPassword.vue'), meta: { guest: true } },
  { path: '/reset-password', name: 'reset-password', component: () => import('@spa/views/auth/ResetPassword.vue'), meta: { guest: true } },
  { path: '/register', name: 'register', component: () => import('@spa/views/auth/Register.vue'), meta: { guest: true } },
  { path: '/invite/:invite/:token', name: 'invite', component: () => import('@spa/views/auth/Invite.vue'), meta: { public: true } },
  { path: '/share/:token', name: 'public-share', component: () => import('@spa/views/PublicShare.vue'), meta: { public: true } },
  { path: '/u/:token', name: 'upload-link', component: () => import('@spa/views/UploadLink.vue'), meta: { public: true } },
  {
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
          { path: 'calendar', name: 'profile.calendar', component: () => import('@spa/views/profile/Calendar.vue') },
          { path: 'mail-keys', name: 'profile.mail-keys', component: () => import('@spa/views/profile/MailKeys.vue') },
          { path: 'data', name: 'profile.data', component: () => import('@spa/views/profile/Data.vue') },
        ],
      },
      { path: 'files', name: 'files', component: () => import('@spa/views/Files.vue') },
      { path: 'shared-with-me', name: 'shared-with-me', component: () => import('@spa/views/SharedWithMe.vue') },
      { path: 'contacts', name: 'contacts', component: () => import('@spa/views/Contacts.vue') },
      { path: 'notes', name: 'notes', component: () => import('@spa/views/Notes.vue') },
      { path: 'gallery', name: 'gallery', component: () => import('@spa/views/Gallery.vue') },
      { path: 'calendar', name: 'calendar', component: () => import('@spa/views/Calendar.vue') },
      { path: 'tasks', name: 'tasks', component: () => import('@spa/views/Tasks.vue') },
      { path: 'mail', name: 'mail', component: () => import('@spa/views/Mail.vue') },
      // Settings is a hub layout (left submenu + RouterView), like Profile —
      // its submenu lives in the page, not in the sidebar rail.
      {
        path: 'settings',
        component: () => import('@spa/views/settings/Settings.vue'),
        children: [
          { path: '', name: 'settings.users', component: () => import('@spa/views/settings/Users.vue') },
          { path: 'groups', name: 'settings.groups', component: () => import('@spa/views/settings/Groups.vue') },
          { path: 'company', name: 'settings.company', component: () => import('@spa/views/settings/Company.vue') },
          { path: 'notifications-config', name: 'settings.notifications-config', component: () => import('@spa/views/settings/NotificationsConfig.vue') },
          { path: 'security', name: 'settings.security', component: () => import('@spa/views/settings/Security.vue') },
          { path: 'files-limits', name: 'settings.files-limits', component: () => import('@spa/views/settings/FilesLimits.vue') },
          { path: 'gallery', name: 'settings.gallery', component: () => import('@spa/views/settings/Gallery.vue') },
          { path: 'backup', name: 'settings.backup', component: () => import('@spa/views/settings/Backup.vue') },
          { path: 'paperless', name: 'settings.paperless', component: () => import('@spa/views/settings/Paperless.vue') },
          { path: 'system', name: 'settings.system', component: () => import('@spa/views/settings/System.vue') },
          { path: 'security-log', name: 'settings.security-log', component: () => import('@spa/views/settings/SecurityLog.vue') },
          { path: 'request-log', name: 'settings.request-log', component: () => import('@spa/views/settings/RequestLog.vue') },
          { path: 'blocks', name: 'settings.blocks', component: () => import('@spa/views/settings/Blocks.vue') },
          { path: 'sessions', name: 'settings.sessions', component: () => import('@spa/views/settings/SessionsOverview.vue') },
          { path: 'notifications', name: 'settings.notifications', component: () => import('@spa/views/settings/Notifications.vue') },
        ],
      },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@spa/views/NotFound.vue') },
];

export const router = createRouter({
  history: createWebHistory('/'),
  routes,
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (!auth.ready) await auth.bootstrap();
  // Fully public pages (invite/share links) load regardless of auth state.
  if (to.meta.public) return true;
  if (!to.meta.guest && !auth.user) return { name: 'login', query: { redirect: to.fullPath } };
  if (to.meta.guest && auth.user) return { name: 'home' };
  return true;
});
