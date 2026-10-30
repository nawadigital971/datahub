Read PROJECT_BRIEF.md, .ai/guidelines/conventions.md and .ai/guidelines/project.md, and open docs/design/prototype.html to study the design.

Then start Phase 1 (Foundation) from the brief:
1. Switch the database to PostgreSQL (DB name nawaa_data_hub) and Redis for cache/queue/session; enable the pg_trgm and unaccent extensions in a migration.
2. Install the approved packages, Laravel Horizon, and Laravel Boost (php artisan boost:install, and include the guidelines in .ai/guidelines so they are merged into CLAUDE.md).
3. Create the Permission enum, app:sync-permissions command, and the four roles.
4. Create GeneralSettings and DataSettings.
5. Build the app shell (top bar, tabs, user menu) and the shared components (AppSelect, FormField/FormLabel, PhoneInput, Toggle, Chip, Toast, lib/http.ts) matching the prototype exactly.

Before writing code, give me a short plan for Phase 1 and wait for my OK. After Phase 1, stop and show me.
