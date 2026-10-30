export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type SelectOption<T extends string | number = string | number> = {
    value: T;
    label: string;
    /** Small grey text on the right, e.g. a count. */
    hint?: string | number;
    disabled?: boolean;
};
