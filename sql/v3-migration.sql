-- Run ONCE in the existing u609460025_jobportal database.
-- Prevents duplicate application records for the same candidate/job.
ALTER TABLE applications ADD UNIQUE KEY uq_application_user_job (user_id, job_id);
