import axios from 'axios';
import type { AxiosError, AxiosInstance } from 'axios';
import { toast } from '@/composables/useToast';

declare module 'axios' {
    interface AxiosRequestConfig {
        /** Skip the shared error toast (the caller shows its own message). */
        silent?: boolean;
    }
}

/**
 * The one HTTP client for small JSON calls that must not re-render the page
 * (inline edits, progress polling, async select options). Page visits and forms use Inertia.
 */
export const http: AxiosInstance = axios.create({
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
    withXSRFToken: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
});

/**
 * The message shown to the user for a failed request.
 */
export function errorMessage(error: unknown): string {
    if (!axios.isAxiosError(error)) {
        return 'Something went wrong.';
    }

    if (error.code === 'ERR_CANCELED') {
        return '';
    }

    if (!error.response) {
        return 'Network error — check your connection and try again.';
    }

    const data = error.response.data as { message?: string } | undefined;

    switch (error.response.status) {
        case 401:
            return 'Your session has ended. Please log in again.';
        case 403:
            return 'You don’t have permission to do that.';
        case 404:
            return 'Not found.';
        case 419:
            return 'This page has expired. Reload and try again.';
        case 422:
            return data?.message ?? 'Please check the highlighted fields.';
        case 429:
            return 'Too many requests. Wait a moment and try again.';
        default:
            return data?.message && error.response.status < 500
                ? data.message
                : 'Server error. Please try again.';
    }
}

http.interceptors.response.use(
    (response) => response,
    (error: AxiosError) => {
        const message = errorMessage(error);

        if (message !== '' && !error.config?.silent) {
            toast(message, 'error');
        }

        return Promise.reject(error);
    },
);
