import { reactive } from 'vue';

export type ToastColor = 'success' | 'error' | 'warning' | 'info';

interface ToastState {
  show: boolean;
  text: string;
  color: ToastColor;
  /** Optional single action, e.g. undoing what the toast just announced. */
  actionLabel: string | null;
  action: (() => void) | null;
}

// Single app-wide snackbar state, rendered once in App.vue.
export const toastState = reactive<ToastState>({ show: false, text: '', color: 'info', actionLabel: null, action: null });

let hideTimer: ReturnType<typeof setTimeout> | undefined;

function show(text: string, color: ToastColor, ms: number, actionLabel: string | null = null, action: (() => void) | null = null) {
  toastState.text = text;
  toastState.color = color;
  toastState.actionLabel = actionLabel;
  toastState.action = action;
  toastState.show = true;
  if (hideTimer) clearTimeout(hideTimer);
  hideTimer = setTimeout(() => { toastState.show = false; toastState.action = null; toastState.actionLabel = null; }, ms);
}

export function useToast() {
  function toast(text: string, color: ToastColor = 'info') { show(text, color, 4000); }

  /**
   * A toast that offers to take the action back.
   *
   * Longer-lived than a plain one (8s): an undo nobody can reach in time is
   * decoration. Reaching for it dismisses the toast, so the action cannot run
   * twice.
   */
  function undoable(text: string, actionLabel: string, undo: () => void, color: ToastColor = 'info') {
    show(text, color, 8000, actionLabel, () => {
      toastState.show = false;
      toastState.action = null;
      toastState.actionLabel = null;
      undo();
    });
  }

  return {
    toast,
    undoable,
    success: (t: string) => toast(t, 'success'),
    error: (t: string) => toast(t, 'error'),
    warning: (t: string) => toast(t, 'warning'),
  };
}
