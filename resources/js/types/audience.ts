export type FacetKey =
    | 'source'
    | 'type'
    | 'country'
    | 'region'
    | 'area'
    | 'sector'
    | 'gender'
    | 'call_outcome';

export type ToggleKey =
    | 'hide_dnc'
    | 'hide_junk'
    | 'has_mobile'
    | 'has_email'
    | 'has_landline'
    | 'has_arabic_name';

export type AudienceFilterState = {
    search: string | null;
    year_from: number | null;
    year_to: number | null;
} & Record<ToggleKey, boolean> &
    Record<FacetKey, number[]>;

/** id => count. PHP sends an empty list ([]) when there are no counts. */
export type Counts = Record<string, number> | [];

export type FacetCounts = {
    facets: Record<FacetKey, Counts>;
    charts: Record<FacetKey, Counts>;
    years: Counts;
};

export type AudienceSummary = {
    total: number;
    with_mobile: number;
    uae_mobile: number;
    usable_email: number;
    with_landline: number;
    channels: Record<ChannelKey, number>;
    completeness: {
        mobile: number;
        email: number;
        arabic_name: number;
        english_name: number;
        gender: number;
        area: number;
    };
    database: { contacts: number; raw_rows: number; sources: number };
};

export type ChannelKey =
    | 'email'
    | 'whatsapp'
    | 'sms'
    | 'meta'
    | 'google'
    | 'calls';

export type FacetOption = { id: number; label: string };

export type AudienceOptions = {
    facets: Record<FacetKey, FacetOption[]>;
    years: { min: number; max: number };
};

export type ContactRow = {
    id: number;
    is_business: boolean;
    name_en: string | null;
    name_ar: string | null;
    /** Other names this contact was seen under. */
    other_names: string[];
    company_name: string | null;
    sector: string | null;
    source: { label: string | null; more: number; year: number | null };
    location: {
        region: string | null;
        area: string | null;
        country: string | null;
    };
    mobile: string | null;
    landline: string | null;
    email: string | null;
    reach: { email: boolean; whatsapp: boolean; sms: boolean };
    flags: { label: string; tone: 'flag' | 'neutral' | 'ok' }[];
};

export type CursorPage<T> = {
    data: T[];
    meta: {
        per_page: number;
        next_cursor: string | null;
        prev_cursor: string | null;
    };
};

/** A saved or built-in segment; `filters` is the URL form of the filters. */
export type AudienceSegment = {
    id?: number;
    key?: string;
    name: string;
    subtitle: string;
    filters: Record<string, string>;
    can_delete?: boolean;
};

export type ActiveFilterChip = {
    key: string;
    label: string;
    value: string;
    remove: () => void;
};

export type ChartBar = { id: number; label: string; value: number };

export type ExportRow = {
    id: number;
    user: string | null;
    created_at: string | null;
    channel: { key: ChannelKey; label: string };
    hashed: boolean;
    audience: string[];
    row_count: number | null;
    purpose: string;
    status: {
        key: 'queued' | 'running' | 'done' | 'failed';
        label: string;
    };
    is_finished: boolean;
    downloaded_at: string | null;
    file: { name: string; size: number } | null;
    download_url: string | null;
};

/** Everything about one contact, for the details panel (phones/emails masked unless allowed). */
export type ContactDetails = {
    id: number;
    is_business: boolean;
    name_en: string | null;
    name_ar: string | null;
    other_names: string[];
    company_name: string | null;
    masked: boolean;
    details: { label: string; value: string }[];
    phones: { value: string; type: string; is_primary: boolean }[];
    emails: { value: string; is_primary: boolean; status: string | null }[];
    status: {
        do_not_contact: boolean;
        dnc_reason: string | null;
        is_junk: boolean;
        check_spelling: boolean;
    };
    extra: { label: string; value: string }[];
    sources: {
        source: string;
        year: number | null;
        file: string | null;
        import_batch_id: number | null;
        row_number: number | null;
        added_at: string | null;
        raw: { column: string; value: string | null }[];
    }[];
    history: {
        type: string;
        at: string;
        by: string | null;
        text: string;
    }[];
    history_more: boolean;
    created_at: string | null;
    updated_at: string | null;
};
