CREATE DATABASE IF NOT EXISTS ai_govt_jobs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ai_govt_jobs;

CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_uid VARCHAR(30) NOT NULL UNIQUE,
 mobile VARCHAR(30) NOT NULL UNIQUE,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 mobile_verified TINYINT(1) NOT NULL DEFAULT 0,
 email_verified TINYINT(1) NOT NULL DEFAULT 0,
 account_status ENUM('active','inactive','locked','pending','deleted') NOT NULL DEFAULT 'active',
 reset_token VARCHAR(64) NULL,
 reset_expires DATETIME NULL,
 last_login DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE user_profiles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL UNIQUE,
 first_name VARCHAR(100) NOT NULL DEFAULT '',
 middle_name VARCHAR(100) NOT NULL DEFAULT '',
 last_name VARCHAR(100) NOT NULL DEFAULT '',
 father_name VARCHAR(150) NOT NULL DEFAULT '',
 mother_name VARCHAR(150) NOT NULL DEFAULT '',
 date_of_birth DATE NULL,
 gender VARCHAR(30) NOT NULL DEFAULT '',
 nationality VARCHAR(80) NOT NULL DEFAULT 'Indian',
 category VARCHAR(30) NOT NULL DEFAULT '',
 sub_category VARCHAR(100) NOT NULL DEFAULT '',
 pwd_status TINYINT(1) NOT NULL DEFAULT 0,
 pwd_percentage DECIMAL(5,2) NULL,
 ex_serviceman TINYINT(1) NOT NULL DEFAULT 0,
 domicile_state VARCHAR(100) NOT NULL DEFAULT '',
 profile_photo VARCHAR(255) NULL,
 profile_completion INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_profile_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_addresses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 address_type ENUM('current','permanent') NOT NULL,
 address_line_1 VARCHAR(255) DEFAULT '',
 address_line_2 VARCHAR(255) DEFAULT '',
 village VARCHAR(120) DEFAULT '',
 city VARCHAR(120) DEFAULT '',
 district VARCHAR(120) DEFAULT '',
 state VARCHAR(100) DEFAULT '',
 pincode VARCHAR(10) DEFAULT '',
 country VARCHAR(80) DEFAULT 'India',
 UNIQUE KEY uq_user_address_type(user_id,address_type),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_education (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 qualification_type VARCHAR(80) NOT NULL,
 course_name VARCHAR(150) DEFAULT '',
 stream VARCHAR(150) DEFAULT '',
 board_university VARCHAR(200) DEFAULT '',
 institution VARCHAR(200) DEFAULT '',
 passing_year SMALLINT NULL,
 marks VARCHAR(50) DEFAULT '',
 percentage DECIMAL(6,2) NULL,
 cgpa VARCHAR(20) DEFAULT '',
 roll_number VARCHAR(100) DEFAULT '',
 registration_number VARCHAR(100) DEFAULT '',
 document_id BIGINT UNSIGNED NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX(user_id)
) ENGINE=InnoDB;

CREATE TABLE user_experience (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 organization VARCHAR(200) DEFAULT '',
 designation VARCHAR(150) DEFAULT '',
 employment_type VARCHAR(80) DEFAULT '',
 government_private VARCHAR(30) DEFAULT '',
 start_date DATE NULL,
 end_date DATE NULL,
 currently_working TINYINT(1) DEFAULT 0,
 total_experience VARCHAR(80) DEFAULT '',
 description TEXT,
 document_id BIGINT UNSIGNED NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_qualifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 qualification_type VARCHAR(100) DEFAULT '',
 qualification_name VARCHAR(200) DEFAULT '',
 issuing_authority VARCHAR(200) DEFAULT '',
 certificate_number VARCHAR(120) DEFAULT '',
 issue_date DATE NULL,
 expiry_date DATE NULL,
 document_id BIGINT UNSIGNED NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 document_type VARCHAR(80) NOT NULL,
 file_name VARCHAR(255) NOT NULL,
 file_path VARCHAR(500) NOT NULL,
 file_size BIGINT UNSIGNED DEFAULT 0,
 mime_type VARCHAR(100) DEFAULT '',
 document_number VARCHAR(150) DEFAULT '',
 issue_date DATE NULL,
 expiry_date DATE NULL,
 verification_status ENUM('pending','verified','rejected','expired') DEFAULT 'pending',
 uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 verified_at DATETIME NULL,
 verified_by BIGINT UNSIGNED NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX(user_id,document_type)
) ENGINE=InnoDB;

CREATE TABLE jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_uid VARCHAR(30) NOT NULL UNIQUE,
 organization VARCHAR(200) NOT NULL,
 department VARCHAR(200) DEFAULT '',
 job_title VARCHAR(250) NOT NULL,
 short_description TEXT,
 job_type VARCHAR(80) DEFAULT '',
 location VARCHAR(200) DEFAULT '',
 state VARCHAR(100) DEFAULT '',
 district VARCHAR(120) DEFAULT '',
 vacancy_count INT DEFAULT 0,
 status ENUM('draft','pending_verification','published','expired','archived') DEFAULT 'draft',
 source_url VARCHAR(500) DEFAULT '',
 application_url VARCHAR(500) DEFAULT '',
 notification_pdf VARCHAR(500) DEFAULT '',
 created_by BIGINT UNSIGNED NULL,
 verified_by BIGINT UNSIGNED NULL,
 published_at DATETIME NULL,
 closing_date DATE NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX(status,closing_date)
) ENGINE=InnoDB;

CREATE TABLE job_eligibility (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL,
 min_age INT NULL,
 max_age INT NULL,
 education_required TEXT,
 experience_required TEXT,
 gender_requirement VARCHAR(100) DEFAULT '',
 nationality_requirement VARCHAR(100) DEFAULT 'Indian',
 domicile_requirement TEXT,
 category_rules TEXT,
 pwd_rules TEXT,
 other_conditions TEXT,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE job_dates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL,
 notification_date DATE NULL,
 application_start DATE NULL,
 application_end DATE NULL,
 fee_last_date DATE NULL,
 exam_date DATE NULL,
 admit_card_date DATE NULL,
 result_date DATE NULL,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE job_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL,
 document_type VARCHAR(80) DEFAULT '',
 file_name VARCHAR(255) DEFAULT '',
 file_path VARCHAR(500) DEFAULT '',
 source_url VARCHAR(500) DEFAULT '',
 uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE job_matches (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 eligibility_status ENUM('eligible','possible','not_eligible','needs_review') DEFAULT 'needs_review',
 match_score DECIMAL(5,2) DEFAULT 0,
 match_reasons TEXT,
 mismatch_reasons TEXT,
 matched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 engine_version VARCHAR(50) DEFAULT 'v1.0',
 UNIQUE KEY uq_job_user(job_id,user_id),
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE saved_jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 job_id BIGINT UNSIGNED NOT NULL,
 saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_saved(user_id,job_id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE applications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 job_id BIGINT UNSIGNED NOT NULL,
 application_status ENUM('not_started','started','assisted','submitted','cancelled') DEFAULT 'not_started',
 application_reference VARCHAR(150) DEFAULT '',
 applied_at DATETIME NULL,
 official_application_url VARCHAR(500) DEFAULT '',
 notes TEXT,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 notification_type VARCHAR(50) DEFAULT 'system',
 title VARCHAR(255) NOT NULL,
 message TEXT NOT NULL,
 job_id BIGINT UNSIGNED NULL,
 channel VARCHAR(30) DEFAULT 'in_app',
 status VARCHAR(30) DEFAULT 'pending',
 sent_at DATETIME NULL,
 read_at DATETIME NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notification_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 notification_id BIGINT UNSIGNED NOT NULL,
 provider VARCHAR(80) DEFAULT '',
 provider_message_id VARCHAR(150) DEFAULT '',
 delivery_status VARCHAR(50) DEFAULT '',
 error_message TEXT,
 sent_at DATETIME NULL,
 delivered_at DATETIME NULL,
 FOREIGN KEY(notification_id) REFERENCES notifications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE subscription_plans (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 plan_name VARCHAR(80) NOT NULL,
 duration_days INT NOT NULL,
 price DECIMAL(10,2) NOT NULL DEFAULT 0,
 features TEXT,
 status ENUM('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE subscriptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 plan_id BIGINT UNSIGNED NOT NULL,
 start_date DATE NOT NULL,
 end_date DATE NOT NULL,
 status VARCHAR(30) DEFAULT 'active',
 auto_renew TINYINT(1) DEFAULT 0,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(plan_id) REFERENCES subscription_plans(id)
) ENGINE=InnoDB;

CREATE TABLE payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 subscription_id BIGINT UNSIGNED NULL,
 transaction_id VARCHAR(150) DEFAULT '',
 amount DECIMAL(10,2) DEFAULT 0,
 currency VARCHAR(10) DEFAULT 'INR',
 payment_gateway VARCHAR(80) DEFAULT '',
 payment_status VARCHAR(40) DEFAULT 'pending',
 payment_date DATETIME NULL,
 invoice_number VARCHAR(100) DEFAULT '',
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE admin_users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 admin_uid VARCHAR(30) NOT NULL UNIQUE,
 name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 mobile VARCHAR(30) DEFAULT '',
 password_hash VARCHAR(255) NOT NULL,
 role VARCHAR(50) DEFAULT 'super_admin',
 status ENUM('active','inactive','locked') DEFAULT 'active',
 last_login DATETIME NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admin_roles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 role_name VARCHAR(80) NOT NULL UNIQUE,
 description TEXT
) ENGINE=InnoDB;

CREATE TABLE permissions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 permission_key VARCHAR(100) NOT NULL UNIQUE,
 description TEXT
) ENGINE=InnoDB;

CREATE TABLE admin_role_permissions (
 role_id BIGINT UNSIGNED NOT NULL,
 permission_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(role_id,permission_id),
 FOREIGN KEY(role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
 FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE support_tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 subject VARCHAR(255) NOT NULL,
 category VARCHAR(100) DEFAULT '',
 message TEXT NOT NULL,
 priority VARCHAR(30) DEFAULT 'normal',
 status VARCHAR(30) DEFAULT 'open',
 assigned_to BIGINT UNSIGNED NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 resolved_at DATETIME NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 admin_id BIGINT UNSIGNED NULL,
 action VARCHAR(100) NOT NULL,
 module VARCHAR(100) DEFAULT '',
 record_id BIGINT UNSIGNED NULL,
 old_value LONGTEXT NULL,
 new_value LONGTEXT NULL,
 ip_address VARCHAR(45) DEFAULT '',
 user_agent VARCHAR(500) DEFAULT '',
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX(admin_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE system_settings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 setting_key VARCHAR(100) NOT NULL UNIQUE,
 setting_value TEXT,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO subscription_plans(plan_name,duration_days,price,features) VALUES
('Free',0,0,'Basic profile and job discovery'),
('Trial',7,0,'Premium features during trial'),
('Monthly',30,99,'Premium matching and alerts'),
('Quarterly',90,249,'Premium matching and alerts'),
('Yearly',365,799,'Premium matching and alerts');

INSERT INTO admin_roles(role_name,description) VALUES
('super_admin','Full system access'),
('job_manager','Manage government jobs'),
('verification_staff','Verify job data'),
('support_staff','Handle candidate support'),
('marketing','Manage notifications and campaigns'),
('accounts','View subscription and payment reports');

-- IMPORTANT: replace the password hash below before production.
-- Generate one using password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT) in PHP.
INSERT INTO admin_users(admin_uid,name,email,password_hash,role,status)
VALUES('ADM-0001','Super Admin','admin@yourdomain.com','$2y$10$REPLACE_THIS_WITH_A_REAL_PASSWORD_HASH','super_admin','active');
