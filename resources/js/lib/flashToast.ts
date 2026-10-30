import { router } from '@inertiajs/vue3';
import { toast } from '@/composables/useToast';
import type { FlashToast } from '@/types/ui';

/**
 * Show `Inertia::flash('toast', ['type' => …, 'message' => …])` from the server in the app toast.
 */
export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast(data.message, data.type);
    });
}
