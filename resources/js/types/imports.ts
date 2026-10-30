import type { SelectOption } from '@/types/ui';

export type BatchStatusKey =
    | 'analyzing'
    | 'ready'
    | 'committing'
    | 'committed'
    | 'discarded'
    | 'failed'
    | 'mapping';

export type ImportBatch = {
    id: number;
    filename: string | null;
    source: string | null;
    user: string | null;
    created_at: string | null;
    committed_at: string | null;
    status: { key: BatchStatusKey; label: string };
    is_busy: boolean;
    error: string | null;
    mapping: string | null;
    counts: {
        total: number;
        clean: number;
        warnings: number;
        errors: number;
        new: number;
        merge: number;
        dup_in_batch: number;
        conflict: number;
        skipped: number;
    };
    can: {
        review: boolean;
        map: boolean;
        commit: boolean;
        discard: boolean;
        recheck: boolean;
    };
};

export type RowIssue = {
    field: string;
    code: string;
    severity: number;
    message: string;
    suggestion: string | null;
};

export type LookupValue = { id: number; label: string } | null;

export type RowAction = 'create' | 'merge' | 'skip';

export type StagedRow = {
    id: number;
    row_number: number;
    source_name: string | null;
    type: number | null;
    name_en: string | null;
    name_ar: string | null;
    company_name: string | null;
    mobile_e164: string | null;
    landline_e164: string | null;
    email: string | null;
    gender: 'm' | 'f' | null;
    region: LookupValue;
    area: LookupValue;
    sector: LookupValue;
    nationality: LookupValue;
    address_raw: string | null;
    seen_year: number | null;
    call_outcome: string | null;
    issues: RowIssue[];
    severity: 'clean' | 'warning' | 'error';
    match: {
        status: 'new' | 'matches_contact' | 'dup_in_batch' | 'conflict';
        label: string;
        contact_id: number | null;
        dup_of_row?: number | null;
    };
    action: RowAction;
    action_locked: boolean;
    has_merge_choices: boolean;
    raw: Record<string, string>;
    extra: Record<string, unknown> | null;
};

export type ReviewOptions = {
    regions: SelectOption<number>[];
    areas: (SelectOption<number> & { region_id: number })[];
    sectors: SelectOption<number>[];
    countries: SelectOption<number>[];
    issues: (SelectOption<string> & { severity: string })[];
    matches: SelectOption<string>[];
    actions: SelectOption<string>[];
    severities: SelectOption<string>[];
};

export type BatchSource = {
    name: string;
    rows: number;
    existing: boolean;
    default: boolean;
};

export type NewReferenceItem = {
    kind: 'country' | 'region' | 'area' | 'sector';
    name: string;
    parent: string | null;
    rows: number;
};

export type RowUpdateResponse = { row: StagedRow; batch: ImportBatch };

export type MergeComparison = {
    candidates: { id: number; label: string; matched_by: string }[];
    contact_id: number | null;
    fields: {
        field: string;
        label: string;
        incoming: string | null;
        existing: string | null;
        choice: 'incoming' | 'existing';
    }[];
};
