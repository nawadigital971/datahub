/**
 * Shared look for text-like fields, copied from the prototype's `.search` input
 * (16px text on phones so iOS never zooms).
 */
export const fieldClasses =
    'w-full rounded-lg border border-line bg-surface-2 px-2.5 py-2 text-base text-fg placeholder:text-faint sm:text-sm disabled:cursor-not-allowed disabled:opacity-50 aria-[invalid=true]:border-bad';

/** The prototype's compact rail `<select>` look (`.range select`). */
export const compactFieldClasses =
    'w-full rounded-[7px] border border-line bg-surface-2 p-1.5 text-base text-fg sm:text-sm disabled:cursor-not-allowed disabled:opacity-50 aria-[invalid=true]:border-bad';
