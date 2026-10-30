# Setup

1. Create the project (Laravel installer):
   laravel new nawaa-data-hub --vue --pest
   (or: composer create-project laravel/vue-starter-kit nawaa-data-hub)
2. Copy everything from this package into the project root
   (PROJECT_BRIEF.md, START_PROMPT.md, .ai/, docs/).
3. Make sure PostgreSQL 16 and Redis are running, and create the database:
   createdb nawaa_data_hub
4. cd nawaa-data-hub && claude
5. Paste the contents of START_PROMPT.md.
