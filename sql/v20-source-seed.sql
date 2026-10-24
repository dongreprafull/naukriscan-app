-- NaukriScan V20 starter official government/recruitment sources
-- Run after sql/v20-migration.sql. Safe to re-run.

INSERT INTO job_sources
(source_name, source_url, source_type, active, created_at, organization, category, state, scan_frequency_minutes, priority, parser_mode, next_scan_at)
SELECT * FROM (
  SELECT 'UPSC Recruitment Notices', 'https://www.upsc.gov.in/recruitment/recruitment-test/notices', 'Recruitment Website', 1, NOW(), 'Union Public Service Commission', 'Central Government', 'All India', 360, 'high', 'auto', NOW()
  UNION ALL SELECT 'SSC Notice Board', 'https://ssc.gov.in/', 'Official Website', 1, NOW(), 'Staff Selection Commission', 'Central Government', 'All India', 360, 'high', 'auto', NOW()
  UNION ALL SELECT 'Employment News - All Jobs', 'https://employmentnews.gov.in/newemp/AllJobs.aspx?k=All', 'Employment Portal', 1, NOW(), 'Employment News / Publications Division', 'Central / State / PSU', 'All India', 360, 'high', 'html', NOW()
  UNION ALL SELECT 'RRB Chandigarh Recruitment Notices', 'https://www.rrbcdg.gov.in/employment-notices.php', 'Recruitment Website', 1, NOW(), 'Railway Recruitment Board Chandigarh', 'Railway', 'All India', 360, 'high', 'auto', NOW()
  UNION ALL SELECT 'SBI Current Openings', 'https://sbi.bank.in/en/web/careers/current-openings', 'Careers Website', 1, NOW(), 'State Bank of India', 'Banking / PSU', 'All India', 720, 'medium', 'auto', NOW()
  UNION ALL SELECT 'IBPS Careers', 'https://www.ibps.in/index.php/careers/', 'Careers Website', 1, NOW(), 'Institute of Banking Personnel Selection', 'Banking / Government-linked', 'All India', 720, 'medium', 'auto', NOW()
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM job_sources j WHERE j.source_url=seed.source_url);
