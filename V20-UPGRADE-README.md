# NaukriScan V20 Upgrade

## What changed
- Manual Government Source Registry with active/paused sources, category/state, priority, parser and scan frequency.
- AI Discovery Queue: AI Ready, Review Required, Failed/Manual Feed.
- Source scan history and document hashing for duplicate detection.
- Cron scanner for official HTTP/HTTPS recruitment sources.
- High-confidence discoveries are inserted as `pending_verification`; nothing is auto-published.
- Modern responsive Admin and Candidate navigation/dashboard shell.
- V20 database migration: `sql/v20-migration.sql`.

## Database
1. Backup the live database first.
2. Import `sql/v20-migration.sql` into the existing NaukriScan database.
3. Verify the new tables: `source_scan_logs`, `source_documents`.

## Cron
Configure Hostinger cron to run:
`php /home/USER/domains/DOMAIN/public_html/cron/scan-sources.php`

Recommended schedule: every 15 minutes. The database controls each source's own scan frequency, so the cron can run frequently without scanning every source each time.

## AI
The scanner uses the existing OpenAI configuration. If `OPENAI_API_KEY` is not configured, documents will move to the failed/manual workflow rather than being published.

## Safety
- Only HTTP/HTTPS sources are accepted.
- The scanner does not bypass CAPTCHA, OTP or payment flows.
- Official source remains the final authority.
- AI discoveries remain pending verification before publication.
