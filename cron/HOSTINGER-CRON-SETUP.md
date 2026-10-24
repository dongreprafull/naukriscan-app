# NaukriScan V20 — Hostinger Cron Setup

## Recommended cron
Run the scanner every 15 minutes. The database controls each source's actual frequency (`scan_frequency_minutes`), so the cron only wakes the scanner; it does not scan every source every 15 minutes.

### Command
```bash
/usr/bin/php /home/USERNAME/domains/naukriscan.in/public_html/cron/scan-sources.php >> /home/USERNAME/logs/naukriscan-scanner.log 2>&1
```

If `/usr/bin/php` is not the PHP CLI binary on the account, use the PHP CLI path shown by Hostinger's Cron Jobs page. `php` is also acceptable when Hostinger resolves it correctly.

### hPanel
1. Open Hostinger hPanel → Websites → Manage → Advanced → Cron Jobs.
2. Create a cron job.
3. Select **Every 15 minutes** (or `*/15 * * * *`).
4. Paste the command above and replace `USERNAME` with the hosting account username.
5. Save.

### First manual test
Before waiting for cron, run once from Hostinger Terminal:
```bash
cd /home/USERNAME/domains/naukriscan.in/public_html
php cron/scan-sources.php
```
Expected output is JSON containing `ok` and a `summary` object.

### Notification worker
Run the existing notification worker every 5 minutes:
```bash
/usr/bin/php /home/USERNAME/domains/naukriscan.in/public_html/cron/process-notifications.php >> /home/USERNAME/logs/naukriscan-notifications.log 2>&1
```

### Maintenance
Run maintenance once daily, e.g. at 03:15:
```bash
/usr/bin/php /home/USERNAME/domains/naukriscan.in/public_html/cron/maintenance.php >> /home/USERNAME/logs/naukriscan-maintenance.log 2>&1
```

## Required server checks
The scanner needs:
- PHP CLI
- PHP cURL extension
- PDO MySQL
- DOM/XML extension for HTML parsing
- `pdftotext` for text-based PDF extraction
- outbound HTTPS access to official sources and `api.openai.com`

If `pdftotext` is unavailable, PDF documents will be routed to the failed/review queue rather than silently published.

## AI configuration
The server must have the existing NaukriScan OpenAI configuration populated. Do not put API keys in the cron command or database.

## Security
Use CLI cron execution; do not expose `cron/scan-sources.php` as a public URL. The script has a non-CLI guard for accidental web execution.

## Initial sources
The V20 source seed adds UPSC Recruitment Notices, SSC, Employment News All Jobs, RRB Chandigarh Recruitment Notices, SBI Current Openings and IBPS Careers. Admin can add more official sources from Admin → Government Sources.
