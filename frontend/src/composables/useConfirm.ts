import { reactive } from 'vue';

// App-wide confirm/prompt dialogs (replace vanilla window.confirm/prompt with a
// styled Modal). One dialog at a time; each call returns a Promise resolved when
// the user confirms/cancels. Rendered once by ConfirmDialog.vue in App.vue.

type Mode = 'confirm' | 'prompt';

interface DialogState {
  open: boolean;
  mode: Mode;
  title: string;
  message: string;
  confirmLabel: string;
  cancelLabel: string;
  danger: boolean;
  value: string;
  placeholder: string;
  resolve: ((v: boolean | string | null) => void) | null;
}

export const confirmState = reactive<DialogState>({
  open: false, mode: 'confirm', title: '', message: '', confirmLabel: '', cancelLabel: '',
  danger: false, value: '', placeholder: '', resolve: null,
});

export interface ConfirmOpts { title?: string; confirmLabel?: string; cancelLabel?: string; danger?: boolean }
export interface PromptOpts extends ConfirmOpts { value?: string; placeholder?: string }

function settle(v: boolean | string | null): void {
  const r = confirmState.resolve;
  confirmState.resolve = null;
  confirmState.open = false;
  if (r) r(v);
}

/** Ask the user to confirm; resolves true (confirmed) or false (cancelled). */
export function confirmAsk(message: string, opts: ConfirmOpts = {}): Promise<boolean> {
  return new Promise((resolve) => {
    Object.assign(confirmState, {
      open: true, mode: 'confirm', message, title: opts.title ?? '',
      confirmLabel: opts.confirmLabel ?? '', cancelLabel: opts.cancelLabel ?? '',
      danger: opts.danger ?? false, value: '', placeholder: '',
      resolve: (v: boolean | string | null) => resolve(v === true),
    });
  });
}

/** Ask the user for a string; resolves the trimmed value or null if cancelled/empty. */
export function promptAsk(message: string, opts: PromptOpts = {}): Promise<string | null> {
  return new Promise((resolve) => {
    Object.assign(confirmState, {
      open: true, mode: 'prompt', message, title: opts.title ?? '',
      confirmLabel: opts.confirmLabel ?? '', cancelLabel: opts.cancelLabel ?? '',
      danger: false, value: opts.value ?? '', placeholder: opts.placeholder ?? '',
      resolve: (v: boolean | string | null) => resolve(typeof v === 'string' && v.trim() !== '' ? v.trim() : null),
    });
  });
}

export function confirmResolve(): void {
  settle(confirmState.mode === 'prompt' ? confirmState.value : true);
}
export function confirmCancel(): void {
  settle(confirmState.mode === 'prompt' ? null : false);
}

export function useConfirm() {
  return { confirm: confirmAsk, prompt: promptAsk };
}
