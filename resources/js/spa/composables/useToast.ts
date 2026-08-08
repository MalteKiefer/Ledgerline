import { reactive } from 'vue';

export type ToastColor = 'success' | 'error' | 'warning' | 'info';

interface ToastState {
  show: boolean;
  text: string;
  color: ToastColor;
}

// Single app-wide snackbar state, rendered once in App.vue.
export const toastState = reactive<ToastState>({ show: false, text: '', color: 'info' });

export function useToast() {
  function toast(text: string, color: ToastColor = 'info') {
    toastState.text = text;
    toastState.color = color;
    toastState.show = true;
  }
  return {
    toast,
    success: (t: string) => toast(t, 'success'),
    error: (t: string) => toast(t, 'error'),
    warning: (t: string) => toast(t, 'warning'),
  };
}
