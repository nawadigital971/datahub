# Project Conventions

These rules apply to every task in this project and override general defaults.

Approved packages — install any that are missing without asking:
`spatie/laravel-query-builder`, `spatie/laravel-permission`, `spatie/laravel-medialibrary`, `spatie/laravel-translatable`, `spatie/laravel-settings`, `vue-tel-input`, `giggsey/libphonenumber-for-php`, `openspout/openspout`, `laravel/horizon`, `laravel/boost`, `laravel/mcp`, `pestphp/pest`.

This project is a **private, login-only dashboard**. Public-site rules (Cloudflare Turnstile, SEO meta/OG output, hreflang) do NOT apply.

## Controllers

- Controllers stay thin. The flow is always: **Form Request → DTO → Action → response**.
- Controllers must NOT contain `private` or `protected` methods. Any logic that would need a helper method belongs in an Action, a DTO, a Form Request, or a Query class.
- No validation, queries with business logic, file handling, or data transformation inside controllers.

```php
public function store(StoreSegmentRequest $request, CreateSegmentAction $action): RedirectResponse
{
    $segment = $action->handle(SegmentData::fromRequest($request));

    return to_route('segments.show', $segment);
}
```

## Validation — Form Requests Always

- Every controller method that receives input uses a dedicated Form Request. Never call `$request->validate()` or `Validator::make()` in controllers.
- Naming: `{Verb}{Model}Request`, e.g. `StoreSegmentRequest`, `UpdateStagedRowRequest`.
- Authorization goes in the Form Request's `authorize()` method, using permissions (see Roles & Permissions).

## Actions & DTOs

- Actions live in `app/Actions/{Domain}/`, e.g. `app/Actions/Imports/CommitBatchAction.php`. Each Action has one public method, `handle()`, and does one thing.
- DTOs live in `app/DTOs/` as `final readonly` classes, built with a static `fromRequest()` constructor (add `fromArray()` when data comes from imports, MCP or other sources).
- Actions receive DTOs, never the raw `Request`.
- Heavy read queries (facet counts, channel counts, chart aggregates) live in `app/Queries/`.

## Filtering, Sorting & Search — Spatie Query Builder

- All index/listing endpoints use `Spatie\QueryBuilder\QueryBuilder`, never manual `if ($request->has(...))` chains.
- Always declare `allowedFilters()`, `allowedSorts()`, and `allowedIncludes()` explicitly, and set a `defaultSort()`.
- **Large tables (`contacts`, `staged_rows`) use `->cursorPaginate()->withQueryString()`**, never `->paginate()` — a full `COUNT(*)` over millions of rows on every request is not allowed. Totals come from cached counts (see CLAUDE.md). Small tables (segments, exports, batches, users) may use `->paginate()->withQueryString()`.
- Pass the active filters back to the page so the UI keeps its state.

## Inertia vs axios

- **Inertia** (`router.get/visit`, `useForm`) for anything that is a page visit or a form: filter changes, pagination, creating segments, starting exports, settings, users, commit/discard of a batch. Filters live in the URL.
- **Inertia v2 deferred props** for expensive data on a page (facet counts, channel counts, charts) so the table renders first.
- **axios** only for small JSON calls that must not re-render the page: inline cell edits in the import preview, progress polling (imports/exports), search-as-you-type options in the Select component, export preview, merge comparison panel.
- One shared axios instance in `resources/js/lib/http.ts` (CSRF, JSON, single error handler/toast). Never create ad-hoc axios instances.
- JSON endpoints follow the same Form Request → DTO → Action flow and return `JsonResource`s.

## Roles & Permissions — Spatie Permission

- All access control uses `spatie/laravel-permission`. Never add `is_admin` columns or hardcode role names in conditionals.
- Check **permissions**, not roles: `$user->can('contacts.export')`, not `$user->hasRole('admin')`.
- Permission names use `{resource}.{action}` format.
- All permissions are defined in one enum, `app/Enums/Permission.php`, and synced by `php artisan app:sync-permissions`.
- Protect routes with `permission:` middleware. Share the current user's permissions to the frontend through `HandleInertiaRequests` so the UI can hide unauthorized buttons. The UI hides; the backend enforces.

## Media — Spatie Media Library

- All uploaded and generated files (import uploads, export CSVs, logos) use `spatie/laravel-medialibrary`. Never store file paths in model columns.
- Every model with images implements `HasMedia`, declares its collections in `registerMediaCollections()`, and registers `thumb` (WebP, resized) and `webp` (full-size WebP) conversions.
- Use `singleFile()` for single-file collections.
- Non-image files (CSV, XLSX) get no conversions. SVG stays SVG. Favicons are never WebP.

## Translations — Spatie Translatable

- Lookup/content fields (countries, regions, areas, sectors, source groups, sources, site name) use `spatie/laravel-translatable` with `json` columns.
- **Exception: contact data.** `name_en`, `name_ar`, `company_name` on contacts and staged rows are plain columns — they are imported data, not translations.
- Supported locales live in `config/app.php` → `locales` (`en`, `ar`). Never hardcode the list.
- Validation uses per-locale keys: `name.en`, `name.ar`. Default locale required.
- Any element showing Arabic text sets `dir="rtl"` (or `dir="auto"` for mixed data). Wrap Latin text/numbers inside Arabic with `<bdi>` so punctuation and brackets don't flip.

## Form Inputs (Frontend)

### Required & optional labels
- Required fields show a red asterisk `*`; optional fields show a muted `(optional)`. Every field has one or the other.
- The marker must match the backend Form Request rules.
- Handled in the shared `FormField`/`FormLabel` component via a `required` prop. The asterisk has `aria-hidden="true"`; the input gets `required` / `aria-required="true"`.

### Text size — no iOS zoom
- Every `input`, `select`, `textarea`, and custom input has a font size of at least **16px** on mobile (`text-base`). Never use `maximum-scale=1` or `user-scalable=no`.

### Select inputs — always enhanced
- Never use the native `<select>`. Always use the shared `AppSelect.vue` (searchable, multi-select, async options via axios).

### Phone inputs — vue-tel-input
- All phone fields use the shared `PhoneInput.vue` wrapping `vue-tel-input`, styled exactly like other inputs (height, border, radius, focus ring, 16px rule).
- Store phones in E.164 (`+971501234567`); validate server-side with libphonenumber in the Form Request.

## Settings — Spatie Settings

- Dashboard-editable config uses `spatie/laravel-settings`, classes in `app/Settings/`, migrations in `database/settings/`. Secrets use `encrypted()`.
- Settings pages follow Form Request → DTO → Action.
- Required: `GeneralSettings` (site name translatable, logo light/dark, favicon, default theme light/dark/system). Project-specific: `DataSettings` (stale-data year threshold, hash-on-export default, export retention days, staged-batch retention days).

## Importing Reference Data — JSON + Command, Not Seeders

- Reference data (countries, UAE regions and areas with Arabic/English aliases, sectors, source groups, sources) lives in JSON under `database/data/`.
- One idempotent Artisan command per dataset (`updateOrCreate` on slug/iso2), grouped under `php artisan app:import-all`.
- Seeders are only for factories and dev/test data (e.g. the 1M demo-contacts seeder).

## Testing

- Pest. Every Action has a feature test. Import normalization rules (phone, email, Arabic normalization, area matching, duplicate detection) have unit tests with real-world messy samples.
