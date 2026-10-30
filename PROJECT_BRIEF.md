# NAWAA Data Hub — Project Brief

> Read this whole file and `.ai/guidelines/conventions.md` before writing code.
> The visual design reference is `docs/design/prototype.html` — open it in a browser. The Vue UI must match it exactly (layout, spacing, colors, fonts, components, copy).

## 1. What this is

A private dashboard for NAWAA Digital Technology (Dubai) that stores **millions of contact records** (names, mobiles, landlines, emails) collected over years from many Excel files, and lets the team:

1. **Filter** contacts in every useful way (source, country, emirate, area, sector, gender, data year, call outcome, has mobile/email/landline, Arabic name, quality flags, free-text search).
2. **See channel readiness** — how many of the filtered audience each channel can reach (email, WhatsApp, SMS, Meta audience, Google Customer Match, call centre).
3. **Save segments** and **export** them per channel in the right format (with SHA-256 hashing for Meta/Google), with every export logged.
4. **Import** new data through a **staging + preview** step: see problems and duplicates, fix inline, choose merges, then commit. Data arrives by file upload or later via an MCP server (Claude stages data; a human commits).

Users: a small internal team. UI language: **English**, with Arabic data displayed RTL-correct (`dir="auto"`, `<bdi>`).

## 2. Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 12, PHP 8.3+ |
| Frontend | Inertia v2 + Vue 3 (`<script setup lang="ts">`), Tailwind v4, Vite (Laravel Vue starter kit) |
| DB | PostgreSQL 16 with `pg_trgm` and `unaccent` |
| Queue/cache | Redis + Laravel Horizon |
| Dev tooling | Laravel Boost (MCP for development); project rules in `.ai/guidelines/` |
| Packages | spatie query-builder, permission, medialibrary, translatable, settings; vue-tel-input; giggsey/libphonenumber-for-php; openspout/openspout; laravel/mcp (phase 5); Pest |
| File conversion | LibreOffice headless (`soffice --headless --convert-to csv`) for `.xls`/`.docx` on the server |
| Charts | None — CSS bars as in the prototype |
| Fonts | Readex Pro (display), IBM Plex Sans Arabic (body), IBM Plex Mono (data) |

## 3. Performance rules (millions of rows)

1. **One row per real person** in `contacts`. Duplicates are merged at commit time, never stored twice. Every source is kept in `contact_sources`.
2. All filter dimensions are small-int FKs or booleans **directly on `contacts`**, each B-tree indexed. Postgres combines them with bitmap scans.
3. Source filter uses `contacts.source_ids smallint[]` with a **GIN index** (`source_ids && ARRAY[...]`), not a join.
4. Search: `contacts.search_text` (Arabic-normalized names + company + email + phone digits) with a **trigram GIN index**. Arabic normalization: أ/إ/آ→ا, ة→ه, ى→ي, strip tashkeel and tatweel.
5. Listing uses **cursor pagination**. The total shown comes from the facet-count query (cached), not `COUNT(*)` per page.
6. **Facet counts** (each facet counted with all *other* filters applied) are delivered as **Inertia deferred props**, computed in `app/Queries/FacetCountsQuery.php`, cached in Redis keyed by a hash of the normalized filters (TTL 10 min, busted on commit). With no filters, read from the `facet_counts` materialized view (refreshed concurrently after each commit).
7. Partial indexes `WHERE do_not_contact = false` on the hottest filter columns (region_id, area_id, country_id, sector_id).
8. Import commit: staged rows → set-based SQL upsert in chunks of 5–10k inside a queued job. No per-row Eloquent saves.
9. Exports: queued job, `lazyById(5000)` streaming to a CSV on disk, attached via Media Library. Never build the file in memory.
10. Seed a **1,000,000-row demo dataset** (factory + `COPY`) and keep every Audience request under ~500 ms for the table and ~1.5 s for deferred facets on it.

## 4. Database schema

### Lookups (translatable `name` json)
```
countries      id smallint pk, iso2 char(2) unique, dial_code varchar(5), name json
regions        id smallint pk, country_id fk, slug unique, name json            -- emirates / states
areas          id int pk, region_id fk, slug, name json, aliases jsonb           -- Arabic+English spellings for matching
               unique(region_id, slug)
sectors        id smallint pk, slug unique, name json
source_groups  id smallint pk, slug unique, name json                            -- Magazines, Waseet, Photography, Retail…
sources        id smallint pk, source_group_id fk, slug unique, name json, collected_year smallint null,
               lawful_basis smallint (consent|contract|legitimate_interest|unknown), notes text null, timestamps
```

### Contacts
```
contacts
  id bigint pk
  type smallint                        -- 1 person, 2 business
  name_en varchar(200) null, name_ar varchar(200) null, company_name varchar(200) null
  name_en_verified bool default true   -- false for machine transliterations
  gender char(1) null, gender_trusted bool default false
  nationality_country_id smallint null fk
  country_id smallint null fk          -- derived from mobile dialling code
  region_id smallint null fk, area_id int null fk, sector_id smallint null fk
  address_raw text null
  mobile_e164 varchar(16) null         -- primary mobile (denormalized from contact_phones)
  landline_e164 varchar(16) null
  email varchar(254) null              -- primary email, lowercased
  email_status smallint default 0      -- 0 unknown, 1 valid, 2 invalid, 3 typo_fixed
  has_mobile bool GENERATED ALWAYS AS (mobile_e164 IS NOT NULL) STORED
  has_uae_mobile bool GENERATED ALWAYS AS (mobile_e164 LIKE '+9715%') STORED
  has_email bool GENERATED ALWAYS AS (email IS NOT NULL AND email_status <> 2) STORED
  has_landline bool GENERATED ALWAYS AS (landline_e164 IS NOT NULL) STORED
  has_arabic_name bool GENERATED ALWAYS AS (name_ar IS NOT NULL) STORED
  source_ids smallint[] not null default '{}'   -- GIN
  source_count smallint default 0
  first_seen_year smallint null, last_seen_year smallint null
  last_call_outcome smallint null      -- 1 not_interested, 2 call_back, 3 switched_off, 4 wrong_number, 5 subscribed
  last_called_at timestamptz null
  do_not_contact bool default false, dnc_reason smallint null
  is_junk bool default false
  search_text text not null default '' -- trigram GIN
  extra jsonb null                     -- contract no., agent, union member, original notes…
  timestamps
indexes: btree(type), btree(country_id), btree(region_id, area_id), btree(sector_id), btree(gender),
         btree(last_seen_year), btree(last_call_outcome), GIN(source_ids), GIN(search_text gin_trgm_ops),
         partial btree on region_id/area_id/country_id/sector_id WHERE do_not_contact = false

contact_phones   id bigint, contact_id fk cascade, e164 varchar(16) unique, type smallint (mobile|landline),
                 country_id smallint, is_primary bool, created_at
contact_emails   id bigint, contact_id fk cascade, email varchar(254) unique, is_primary bool, created_at
contact_sources  id bigint, contact_id fk cascade, source_id fk, import_batch_id fk null, row_number int null,
                 seen_year smallint null, raw jsonb (original row, never modified), created_at
                 unique(contact_id, source_id)
contact_events   id bigint, contact_id fk cascade, type smallint (call|opt_out|merge|export|edit),
                 outcome smallint null, agent varchar null, occurred_at timestamptz, meta jsonb null, user_id null
suppressions     id, kind smallint (phone|email), value varchar unique, reason smallint, source_id null,
                 user_id null, created_at      -- global do-not-contact; commits must never resurrect these
```
**Dedup key at commit:** match `contact_phones.e164` first, then `contact_emails.email`. Phone match wins over email match. If a row matches two different contacts → `conflict` (human decides).

### Import staging & preview
```
mapping_templates  id, name, layout smallint (master|form_export|business_directory|custom),
                   column_map jsonb, timestamps
import_batches     id, source_id fk, origin smallint (upload|mcp), user_id fk (creator / MCP token owner),
                   mapping_template_id null, status smallint (analyzing|ready|committing|committed|discarded|failed),
                   original_filename null,
                   total_rows, clean_rows, warning_rows, error_rows, new_rows, merge_rows, dup_in_batch_rows,
                   conflict_rows, skipped_rows   (all int default 0)
                   committed_by null, committed_at null, discarded_at null, error text null, timestamps
                   + media collection "file"
staged_rows        id bigint, import_batch_id fk cascade, row_number int,
                   raw jsonb                     -- original, never modified
                   type, name_en, name_ar, company_name, gender, nationality_country_id,
                   mobile_e164, landline_e164, email, country_id, region_id, area_id, sector_id,
                   address_raw, seen_year, call_outcome, extra jsonb      -- parsed & editable
                   issue_codes text[] default '{}'   -- GIN
                   issues jsonb default '[]'         -- [{field, code, severity, message, suggestion}]
                   severity smallint (0 clean, 1 warning, 2 error)
                   match_status smallint (new|matches_contact|dup_in_batch|conflict)
                   matched_contact_id bigint null, dup_of_row_id bigint null
                   action smallint (create|merge|skip)
                   merge_choices jsonb null          -- {field: "incoming"|"existing"}
                   edited_by null, edited_at null
                   index(import_batch_id, severity), index(import_batch_id, match_status), index(import_batch_id, action)
staged_row_edits   id, staged_row_id fk cascade, field, old_value text null, new_value text null, user_id, created_at
```
Retention: committed batches delete their staged rows after commit (the raw row lives on in `contact_sources.raw`). Discarded batches are purged after `DataSettings::staged_retention_days` (default 30) by a scheduled command.

**Issue codes** (start with these): `invalid_phone`, `landline_in_mobile`, `phone_in_notes`, `missing_contact_method`, `email_invalid`, `email_typo` (gamil.com → gmail.com, suggestion), `junk_name`, `unverified_transliteration`, `gender_suspect`, `unknown_area`, `unknown_region`, `suppressed` (on do-not-contact list → default action skip), `stale_data` (seen_year older than threshold).

### Segments & exports
```
segments   id, name, filters jsonb, user_id fk, is_shared bool, cached_count int null, counted_at null, timestamps
exports    id, user_id fk, segment_id null, filters jsonb, channel smallint (email|whatsapp|sms|meta|google|calls),
           hashed bool, row_count int null, status smallint (queued|running|done|failed), purpose text,
           downloaded_at null, timestamps   + media collection "file"
facet_counts  MATERIALIZED VIEW (dimension text, value_id int, total bigint) WHERE do_not_contact = false
```

### Framework
users, Spatie permission tables, media, settings, jobs/failed_jobs (Horizon uses Redis).

## 5. Permissions (`app/Enums/Permission.php`)
`contacts.view`, `contacts.view_unmasked` (otherwise phones/emails are masked like `+971 50 ••• ••12`), `contacts.update`, `segments.manage`, `exports.create`, `exports.view_log`, `imports.create`, `imports.review`, `imports.commit`, `imports.discard`, `settings.update`, `users.manage`.
Roles (seeded, editable): Admin (all), Marketer (view, segments, exports), Data Editor (view, imports.create/review), Viewer (view).

## 6. Screens (match `docs/design/prototype.html`)

Top bar: brand "NAWAA Data Hub" + subtitle, tabs, user menu. White/near-white palette, charcoal accent (see the `:root` tokens in the prototype). Light theme only for now, but keep everything on CSS variables.

1. **Audience** (`/audience`) — left filter rail (search, compliance toggles, must-have toggles, data-year range via AppSelect, facet groups with live counts, clear per group), main column: summary card (big count + 4 stats with meters + removable filter chips), quick segments (saved segments + built-ins), channel readiness cards (6), charts grid (by source, emirate, country, data age columns, top areas, field completeness), records table with masked phones/emails, reach dots (@ / W / S), status tags, cursor pager, "Export this audience". Clicking a chart bar toggles that filter. Filter rail becomes a drawer under 900px.
2. **Export dialog** — format cards per channel with row counts, compliance note per channel, preview of first 40 rows, "Start export" (queued) → appears in Exports.
3. **Imports** (`/imports`) — batch list with status pills and counts; "New import" (upload file, pick source, pick/auto-detect mapping template, map columns).
4. **Import preview** (`/imports/{batch}`) — summary bar (total / clean / warnings / errors / new / merge / dup-in-batch / conflict), filter by issue code / match status / action, table with **inline editable cells** (axios PATCH → server re-normalizes and re-checks the row → returns updated row), row drawer with raw original vs parsed, **merge panel** (incoming vs existing contact side by side, choose per field), bulk actions on selection or on all rows matching the current filter (set action, set region/area/sector, accept all suggestions), Commit (confirm step inside the page) and Discard.
5. **Exports log** (`/exports`) — who, when, channel, filters summary, rows, purpose, download.
6. **Data health** (`/health`) — live counts of each issue across contacts (junk, unverified transliteration, stale, missing contact method…), as cards like the prototype.
7. **Sources** (`/sources`) — sources with contact counts, group, year, lawful basis.
8. **Settings** and **Users & roles**.

## 7. Import pipeline (Actions)

1. `CreateImportBatchAction` — store upload via Media Library, create batch (status analyzing), dispatch `AnalyzeBatchJob`.
2. `AnalyzeBatchJob`:
   - convert to CSV (OpenSpout for xlsx/csv; LibreOffice for xls/docx/txt),
   - `COPY` into `staged_rows.raw` via a temp table,
   - apply mapping template → parsed columns,
   - normalize (Actions in `app/Actions/Normalization/`): `NormalizePhoneAction` (libphonenumber, default region AE, split mobile/landline, derive country), `NormalizeEmailAction` (lowercase, trim, typo map), `NormalizeArabicTextAction`, `MatchAreaAction` (aliases dictionary, Arabic+English), `ParseNotesAction` (CB/NI/SW/D call outcomes, "Landline: …", contract/agent),
   - detect issues → `issue_codes`, `issues`, `severity`,
   - detect duplicates in batch (same phone/email) and against `contact_phones` / `contact_emails` / `suppressions` (set-based SQL joins),
   - set default `action` (create / merge / skip), update batch counts, status ready.
3. `UpdateStagedRowAction` (inline edit) — logs to `staged_row_edits`, re-runs normalization and checks for that row only, recomputes batch counts incrementally.
4. `BulkUpdateStagedRowsAction` — set-based by ids or by filter.
5. `CommitBatchAction` → `CommitBatchJob` — chunked SQL: insert new contacts, merge into existing using `merge_choices`, upsert phones/emails/sources, append `source_ids`, recompute `search_text`, write `contact_events`, refresh `facet_counts` concurrently, bust Redis facet cache, delete staged rows.
6. `DiscardBatchAction`.

## 8. Reference data (database/data/*.json + commands)
- `countries.json` (at least all Arab + GCC + common expat countries with dial codes, en/ar names)
- `regions.json` (7 emirates, en/ar)
- `areas.json` (Dubai, Sharjah, Abu Dhabi, Ajman, RAK, Fujairah, UAQ districts with Arabic/English aliases — e.g. International City / انترناشيونال سيتي / المدينة العالمية; Deira / ديرة / Deirah)
- `sectors.json`, `source_groups.json`, `sources.json` (Layalina subscribers, Top Gear/Gala subscribers, Call centre lists 2013, Photographers – award entrants, Photographers – UAE community, Real estate agents, Waseet business directory, Designer Outlet customers, SoClean customers, RE email list 2024)
- `mapping_templates.json` — the three known layouts:
  - **master**: Date Added, Record Type, Source, English Full Name, Arabic Full Name, Gender, Company, Title, Mobile AE, Mobile AE 2, Mobile Intl 1, Mobile Intl 2, Phone, Email, Email 2, Location, Notes
  - **form_export** (photographers): Timestamp, Full Name in Arabic, Full Name in English, AE Number, Intl Number, Sex, Email, Nationality, Member of Union Of Arab Photographers?, Source, Duplicate Flag
  - **business_directory** (Waseet): Company Name, Client Name, Address, Mobile, Landline, Email/ Website

## 9. Build phases (do them in order, run tests and show me at the end of each)
1. **Foundation** — install packages + Boost, Postgres config, permissions enum + sync command, roles, settings classes, layout shell matching the prototype top bar, shared components (AppSelect, FormField, PhoneInput, Toggle, Chip, Toast, http.ts).
2. **Schema + reference data** — all migrations, models, enums, JSON import commands, 1M demo contacts seeder (via `COPY`), materialized view.
3. **Audience** — QueryBuilder filters, cursor pagination, deferred facets/channels/charts, segments, the full Audience UI pixel-matched to the prototype. Measure query times on 1M rows.
4. **Exports** — dialog, job, hashing, log page.
5. **Imports + preview** — upload, analysis, preview screen with inline edits, merge panel, bulk actions, commit/discard.
6. **MCP server** (`laravel/mcp`, Sanctum token auth, permission-checked): `stage_contacts`, `get_batch_summary`, `list_batch_issues`, `update_staged_rows`, `query_audience_counts`. **No commit tool** — commit is human-only in the dashboard.
7. Data health, sources, settings, users pages; polish.

## 10. Compliance
- Keep source + year on every contact; global suppression list; `do_not_contact` respected by every channel count and export.
- Every export requires a purpose and is logged.
- Phones/emails masked unless `contacts.view_unmasked`.
- The file "Waseet Stuff Info for Medical Insurance Final.xlsx" is employee data and must never be imported.
