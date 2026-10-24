-- NaukriScan V20: AI Government Source Scanner + modern dashboard support
-- Run after the existing database dump. Safe to re-run on MariaDB 10.6+ / 11.x.

ALTER TABLE job_sources
  ADD COLUMN IF NOT EXISTS organization varchar(200) DEFAULT '',
  ADD COLUMN IF NOT EXISTS category varchar(80) DEFAULT 'Government',
  ADD COLUMN IF NOT EXISTS state varchar(100) DEFAULT '',
  ADD COLUMN IF NOT EXISTS scan_frequency_minutes int unsigned NOT NULL DEFAULT 360,
  ADD COLUMN IF NOT EXISTS priority varchar(20) NOT NULL DEFAULT 'medium',
  ADD COLUMN IF NOT EXISTS parser_mode varchar(30) NOT NULL DEFAULT 'auto',
  ADD COLUMN IF NOT EXISTS last_scan_at datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS next_scan_at datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS last_status varchar(30) DEFAULT 'never',
  ADD COLUMN IF NOT EXISTS last_error text DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS last_found_count int unsigned NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS last_new_count int unsigned NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS created_by bigint(20) unsigned DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS updated_at datetime DEFAULT current_timestamp() ON UPDATE current_timestamp();

CREATE TABLE IF NOT EXISTS source_scan_logs (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  source_id int(10) unsigned NOT NULL,
  started_at datetime NOT NULL DEFAULT current_timestamp(),
  finished_at datetime DEFAULT NULL,
  status varchar(30) NOT NULL DEFAULT 'running',
  found_links int unsigned NOT NULL DEFAULT 0,
  new_documents int unsigned NOT NULL DEFAULT 0,
  extracted_count int unsigned NOT NULL DEFAULT 0,
  created_jobs int unsigned NOT NULL DEFAULT 0,
  duplicate_count int unsigned NOT NULL DEFAULT 0,
  failed_count int unsigned NOT NULL DEFAULT 0,
  error_message text DEFAULT NULL,
  details_json longtext DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_source_scan_source_time (source_id, started_at),
  KEY idx_source_scan_status_time (status, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS source_documents (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  source_id int(10) unsigned NOT NULL,
  source_url varchar(1000) NOT NULL,
  canonical_url varchar(1000) NOT NULL,
  sha256 char(64) NOT NULL,
  mime_type varchar(100) DEFAULT '',
  http_status smallint unsigned DEFAULT NULL,
  content_title varchar(500) DEFAULT '',
  content_path varchar(700) DEFAULT NULL,
  extraction_status varchar(30) NOT NULL DEFAULT 'pending',
  extraction_log_id bigint(20) unsigned DEFAULT NULL,
  job_id bigint(20) unsigned DEFAULT NULL,
  first_seen_at datetime NOT NULL DEFAULT current_timestamp(),
  last_seen_at datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_source_document_hash (sha256),
  KEY idx_source_documents_source (source_id, last_seen_at),
  KEY idx_source_documents_status (extraction_status, last_seen_at),
  KEY idx_source_documents_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT IGNORE INTO permissions (id, permission_key, description) VALUES
(16, 'sources.view', 'View government source registry and scanner status'),
(17, 'sources.manage', 'Add, edit, enable and disable government sources'),
(18, 'sources.scan', 'Run government source scans');

INSERT IGNORE INTO admin_role_permissions (role_id, permission_id) VALUES
(1,16),(1,17),(1,18),(2,16),(8,16),(8,18),(7,16),(7,17),(7,18);

-- Seed the existing BEL record with sensible scanner defaults.
UPDATE job_sources
SET organization=CASE WHEN organization='' THEN source_name ELSE organization END,
    category=CASE WHEN category='' THEN 'Government / PSU' ELSE category END,
    priority=CASE WHEN priority='' THEN 'medium' ELSE priority END,
    parser_mode=CASE WHEN parser_mode='' THEN 'auto' ELSE parser_mode END
WHERE id=1;

-- V20 starter registry: verified official recruitment sources.
-- Safe to re-run: each source is inserted only when the source URL is not already present.
INSERT INTO job_sources
(source_name, source_url, source_type, active, created_at, organization, category, state, scan_frequency_minutes, priority, parser_mode, next_scan_at)
SELECT * FROM (
  SELECT 'UPSC Recruitment Notices', 'https://www.upsc.gov.in/recruitment/recruitment-test/notices', 'Recruitment Website', 1, NOW(), 'Union Public Service Commission', 'Central Government', 'All India', 360, 'high', 'auto', NOW()
  UNION ALL
  SELECT 'SSC Notice Board', 'https://ssc.gov.in/', 'Official Website', 1, NOW(), 'Staff Selection Commission', 'Central Government', 'All India', 360, 'high', 'auto', NOW()
  UNION ALL
  SELECT 'Employment News - All Jobs', 'https://employmentnews.gov.in/newemp/AllJobs.aspx?k=All', 'Employment Portal', 1, NOW(), 'Employment News / Publications Division', 'Central / State / PSU', 'All India', 360, 'high', 'html', NOW()
  UNION ALL
  SELECT 'RRB Chandigarh Recruitment Notices', 'https://www.rrbcdg.gov.in/employment-notices.php', 'Recruitment Website', 1, NOW(), 'Railway Recruitment Board Chandigarh', 'Railway', 'All India', 360, 'high', 'auto', NOW()
  UNION ALL
  SELECT 'SBI Current Openings', 'https://sbi.bank.in/en/web/careers/current-openings', 'Careers Website', 1, NOW(), 'State Bank of India', 'Banking / PSU', 'All India', 720, 'medium', 'auto', NOW()
  UNION ALL
  SELECT 'IBPS Careers', 'https://www.ibps.in/index.php/careers/', 'Careers Website', 1, NOW(), 'Institute of Banking Personnel Selection', 'Banking / Government-linked', 'All India', 720, 'medium', 'auto', NOW()
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM job_sources j WHERE j.source_url=seed.source_url);

