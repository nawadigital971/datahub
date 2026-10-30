import { readonly, ref } from 'vue';

export type ToastType = 'success' | 'info' | 'warning' | 'error';

export type ToastMessage = {
    id: number;
    type: ToastType;
    message: string;
};

const DURATION_MS = 2200;

const current = ref<ToastMessage | null>(null);
let counter = 0;
let timer: ReturnType<typeof setTimeout> | undefined;

/**
 * Show a short message in the single toast at the bottom of the screen (as in the prototype).
 * A new toast replaces the one on screen.
 */
export function toast(message: string, type: ToastType = 'success'): void {
    clearTimeout(timer);
    current.value = { id: ++counter, type, message };
    timer = setTimeout(
        dismissToast,
        type === 'error' ? DURATION_MS * 2 : DURATION_MS,
    );
}

export function dismissToast(): void {
    clearTimeout(timer);
    current.value = null;
}

export function useToast() {
    return { current: readonly(current), toast, dismissToast };
}
