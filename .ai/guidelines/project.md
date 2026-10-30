# NAWAA Data Hub

- This project is specified in `PROJECT_BRIEF.md` at the repo root. Read it before any task: stack, performance rules, full DB schema, screens, import pipeline, build phases.
- The UI must match `docs/design/prototype.html` exactly (open it in a browser). Reuse its tokens, fonts, spacing and copy.
- Work phase by phase as listed in the brief. At the end of each phase: run `php artisan test`, run Pint, and summarize what was built and what is next.
- Contacts can number in the millions: never `->paginate()` or `COUNT(*)` on `contacts`/`staged_rows`, never per-row Eloquent loops in imports, always set-based SQL and queued jobs for heavy work.
