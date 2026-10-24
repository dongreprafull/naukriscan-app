# NaukriScan V20 — Hostinger + Starter Sources

1. Import `sql/v20-migration.sql` if V20 migration has not been imported yet.
2. If V20 migration is already imported, import `sql/v20-source-seed.sql` only.
3. Upload the V20 codebase.
4. Configure the three cron jobs in `cron/HOSTINGER-CRON-SETUP.md`.
5. Run `php cron/scan-sources.php` once manually.
6. Open Admin → Government Sources and confirm the starter sources show `Never` initially, then update after the first run.
7. Open Admin → Discovery to review AI-ready/review/failed results.
