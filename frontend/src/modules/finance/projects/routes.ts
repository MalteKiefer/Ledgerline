import type { RouteRecordRaw } from 'vue-router';

export const projectRoutes: RouteRecordRaw[] = [
  {
    path: '/finance/projects',
    name: 'finance.projects.index',
    component: () => import('@spa/modules/finance/projects/ProjectListPage.vue'),
  },
  {
    path: '/finance/projects/new',
    name: 'finance.projects.new',
    component: () => import('@spa/modules/finance/projects/ProjectEditPage.vue'),
  },
  {
    path: '/finance/projects/:project',
    name: 'finance.projects.show',
    component: () => import('@spa/modules/finance/projects/ProjectDetailPage.vue'),
  },
  {
    path: '/finance/projects/:project/edit',
    name: 'finance.projects.edit',
    component: () => import('@spa/modules/finance/projects/ProjectEditPage.vue'),
  },
];
