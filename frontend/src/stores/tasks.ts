import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api, ApiError } from '@spa/api/client';

// A task list = a calendar collection whose component is VTODO (Apple Reminders /
// Tasks.org). These come from GET /calendar/data alongside event calendars; we
// filter to component === 'VTODO'. Mirrors CalendarCol in stores/calendar.ts.
export interface TaskList {
  id: string;
  name: string;
  color: string | null;
  component: string; // 'VEVENT' | 'VTODO'
  owned: boolean;
}

export type TodoStatus = 'NEEDS-ACTION' | 'IN-PROCESS' | 'COMPLETED' | 'CANCELLED';

// A task row as returned by GET /calendar/todos (the controller's present()).
// `calendar` is the calendar_id; every editable field is denormalised here so the
// editor can open straight from the row without a second fetch.
export interface CalendarTodo {
  id: string;
  calendar: string;
  uid: string | null;
  summary: string | null;
  description: string | null;
  status: TodoStatus;
  priority: number | null; // 0-9 (1-4 high, 5 medium, 6-9 low; 0/null = none)
  percent_complete: number | null; // 0-100
  due: string | null; // ISO-8601 UTC
  dtstart: string | null; // ISO-8601 UTC
  completed_at: string | null;
  all_day: boolean;
  rrule: string | null;
  alarm_minutes_before: number | null;
  categories: string[];
  related_to: string | null; // parent task UID (subtasks)
  sequence: number;
  sort_order: number;
  color: string | null; // echoes the parent list colour
  etag: string;
}

// Create/update payload (CalendarTodoInput). `etag` is sent on update for the
// DAV-native optimistic-concurrency check (409 { error:'etag_conflict', etag }).
export interface CalendarTodoInput extends Record<string, unknown> {
  calendar_id: string;
  summary?: string;
  description?: string | null;
  status?: TodoStatus;
  priority?: number | null;
  percent_complete?: number | null;
  due?: string | null;
  dtstart?: string | null;
  all_day?: boolean;
  rrule?: string | null;
  alarm_minutes_before?: number | null;
  categories?: string[];
  related_to?: string | null;
  etag?: string;
}

export interface TodoImportResult {
  created: number;
  updated: number;
  skipped: number;
}

export type TaskSort = 'due' | 'priority' | 'manual';

export interface TaskFilters {
  listId: string; // '' = all lists
  status: 'all' | TodoStatus;
  hideCompleted: boolean;
  sort: TaskSort;
  dueBefore: string; // '' = no due cap
}

export const useTasksStore = defineStore('tasks', () => {
  const taskLists = ref<TaskList[]>([]);
  const tasks = ref<CalendarTodo[]>([]);
  const openTask = ref<CalendarTodo | null>(null);
  const filters = ref<TaskFilters>({ listId: '', status: 'all', hideCompleted: true, sort: 'manual', dueBefore: '' });

  // Task lists ride on the shared calendar payload — filter to VTODO collections.
  async function loadTaskLists(): Promise<void> {
    const r = await api.get<{ calendars: TaskList[] }>('/api/v1/calendar/data');
    taskLists.value = (r.calendars ?? []).filter((c) => c.component === 'VTODO');
  }

  const createTaskList = (name: string, color?: string) =>
    api.post<{ id: string }>('/api/v1/calendars', { name, color, component: 'VTODO' });

  // Server-side scope by list + optional due cap; status / hide-completed / sort are
  // applied client-side (in the view) for instant toggling — mirrors Calendar.vue.
  async function loadTasks(): Promise<void> {
    const qs = new URLSearchParams();
    if (filters.value.listId) qs.set('calendar_id', filters.value.listId);
    if (filters.value.dueBefore) qs.set('due_before', filters.value.dueBefore);
    const suffix = qs.toString() ? `?${qs}` : '';
    const r = await api.get<{ todos: CalendarTodo[] }>(`/api/v1/calendar/todos${suffix}`);
    tasks.value = r.todos ?? [];
  }

  const show = (id: string) => api.get<Record<string, unknown>>(`/api/v1/calendar/todos/${id}`);
  const createTask = (body: CalendarTodoInput) => api.post<{ id: string }>('/api/v1/calendar/todos', body);

  // etag-optimistic update: on a 409 the server returns the fresh etag; retry once
  // with it (last-writer-wins) so a stale editor still saves rather than dead-ending.
  async function updateTask(id: string, body: CalendarTodoInput): Promise<{ ok: boolean; etag: string }> {
    try {
      return await api.put<{ ok: boolean; etag: string }>(`/api/v1/calendar/todos/${id}`, body);
    } catch (e) {
      if (e instanceof ApiError && e.status === 409) {
        const fresh = (e.body as { etag?: string })?.etag;
        if (fresh) return api.put<{ ok: boolean; etag: string }>(`/api/v1/calendar/todos/${id}`, { ...body, etag: fresh });
      }
      throw e;
    }
  }

  const deleteTask = (id: string) => api.delete(`/api/v1/calendar/todos/${id}`);

  // Toggle helpers return the rolled/updated row; the view swaps it into tasks[].
  const complete = (id: string) =>
    api.post<{ ok: boolean; rolled: boolean; todo: CalendarTodo }>(`/api/v1/calendar/todos/${id}/complete`, {});
  const uncomplete = (id: string) =>
    api.post<{ ok: boolean; todo: CalendarTodo }>(`/api/v1/calendar/todos/${id}/uncomplete`, {});

  const reorder = (order: string[]) => api.post('/api/v1/calendar/todos/reorder', { order });

  function importIcs(file: File, calendarId: string): Promise<TodoImportResult> {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('calendar_id', calendarId);
    return api.upload<TodoImportResult>('/api/v1/calendar/todos/import', fd);
  }

  // Path to the .ics export; the view wraps this with api.streamUrl so the bearer
  // token rides along for an <a>/download (no Authorization header there).
  const exportUrl = (id?: string) =>
    `/api/v1/calendar/todos/export${id ? `?calendar_id=${encodeURIComponent(id)}` : ''}`;

  return {
    taskLists, tasks, openTask, filters,
    loadTaskLists, createTaskList, loadTasks, show,
    createTask, updateTask, deleteTask, complete, uncomplete, reorder, importIcs, exportUrl,
  };
});
