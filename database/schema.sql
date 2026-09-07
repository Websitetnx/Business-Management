CREATE DATABASE IF NOT EXISTS permitflow
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE permitflow;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('applicant', 'admin', 'treasurer') NOT NULL DEFAULT 'applicant',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role),
  INDEX idx_users_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auth_otp_challenges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_token_hash CHAR(64) NOT NULL,
  email VARCHAR(190) NOT NULL,
  purpose ENUM('registration', 'password_reset') NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  pending_name VARCHAR(120) NULL,
  pending_password_hash VARCHAR(255) NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  last_sent_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_auth_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_auth_otp_request_token (request_token_hash),
  INDEX idx_auth_otp_email_purpose_sent (email, purpose, last_sent_at),
  INDEX idx_auth_otp_expires (expires_at),
  INDEX idx_auth_otp_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS businesses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  business_name VARCHAR(190) NOT NULL,
  business_type VARCHAR(100) NOT NULL,
  organization_type VARCHAR(100) NOT NULL,
  tin VARCHAR(30) NOT NULL,
  contact VARCHAR(40) NOT NULL,
  email VARCHAR(190) NOT NULL,
  address TEXT NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  location_accuracy_m DECIMAL(10,2) NULL,
  location_captured_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_business_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_business_user (user_id),
  INDEX idx_business_name (business_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  business_id BIGINT UNSIGNED NOT NULL,
  reference VARCHAR(30) NOT NULL UNIQUE,
  permit_number VARCHAR(30) NULL,
  application_type ENUM('New', 'Renewal') NOT NULL DEFAULT 'New',
  status ENUM('Submitted', 'For Review', 'Needs Revision', 'Approved', 'Released', 'Rejected') NOT NULL DEFAULT 'For Review',
  stage TINYINT UNSIGNED NOT NULL DEFAULT 1,
  declared_capital DECIMAL(14,2) NULL,
  gross_sales DECIMAL(14,2) NULL,
  requires_building_inspection TINYINT(1) NOT NULL DEFAULT 0,
  requires_electrical_inspection TINYINT(1) NOT NULL DEFAULT 0,
  requires_plumbing_inspection TINYINT(1) NOT NULL DEFAULT 0,
  applicant_notes TEXT NULL,
  admin_notes TEXT NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at TIMESTAMP NULL,
  approved_at TIMESTAMP NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_application_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_application_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE RESTRICT,
  INDEX idx_application_user (user_id),
  INDEX idx_application_permit (permit_number),
  INDEX idx_application_status (status),
  INDEX idx_application_business (business_id),
  INDEX idx_application_submitted (submitted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permit_fee_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  lgu_name VARCHAR(190) NOT NULL DEFAULT 'Local Government Unit',
  sanitary_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  zoning_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  general_inspection_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  building_inspection_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  electrical_inspection_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  plumbing_inspection_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  barangay_clearance_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  community_tax_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  bfp_rate_percent DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
  bfp_minimum_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_configured TINYINT(1) NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_fee_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO permit_fee_settings (id) VALUES (1)
ON DUPLICATE KEY UPDATE id = VALUES(id);

CREATE TABLE IF NOT EXISTS permit_business_type_rates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_type VARCHAR(100) NOT NULL UNIQUE,
  new_lbt_rate_percent DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
  renewal_lbt_rate_percent DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
  mayors_permit_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO permit_business_type_rates (business_type) VALUES
  ('Retail'), ('Food and Beverage'), ('Professional Services'), ('Manufacturing'), ('Other')
ON DUPLICATE KEY UPDATE business_type = VALUES(business_type);

CREATE TABLE IF NOT EXISTS application_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  document_type VARCHAR(80) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(120) NOT NULL UNIQUE,
  mime_type VARCHAR(100) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_document_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  INDEX idx_document_application (application_id),
  INDEX idx_document_type (document_type)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS application_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(40) NOT NULL,
  notes TEXT NULL,
  changed_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_history_application (application_id),
  INDEX idx_history_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  assessment_breakdown JSON NULL,
  assessed_by BIGINT UNSIGNED NULL,
  assessed_at TIMESTAMP NULL,
  payment_method ENUM('GCash', 'Maya', 'Bank Transfer', 'City Treasurer Counter') NULL,
  payer_name VARCHAR(120) NULL,
  payment_reference VARCHAR(100) NULL,
  status ENUM('Pending', 'Paid', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending',
  proof_original_name VARCHAR(255) NULL,
  proof_stored_name VARCHAR(120) NULL,
  proof_mime_type VARCHAR(100) NULL,
  receipt_number VARCHAR(40) NULL,
  submitted_at TIMESTAMP NULL,
  paid_at TIMESTAMP NULL,
  verified_by BIGINT UNSIGNED NULL,
  verified_at TIMESTAMP NULL,
  admin_notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_assessor FOREIGN KEY (assessed_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_payment_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_payment_application (application_id),
  UNIQUE KEY uq_payment_receipt (receipt_number),
  INDEX idx_payment_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  application_id BIGINT UNSIGNED NULL,
  message VARCHAR(500) NOT NULL,
  read_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notification_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  INDEX idx_notification_user (user_id, read_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_entity (entity_type, entity_id),
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS document_ai_scans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL UNIQUE,
  application_id BIGINT UNSIGNED NOT NULL,
  scan_status ENUM('Completed', 'Failed') NOT NULL,
  detected_document_type VARCHAR(190) NULL,
  matches_expected_type TINYINT(1) NULL,
  quality_score TINYINT UNSIGNED NULL,
  confidence_score TINYINT UNSIGNED NULL,
  extracted_fields JSON NULL,
  issues JSON NULL,
  summary TEXT NULL,
  requires_human_review TINYINT(1) NOT NULL DEFAULT 1,
  model VARCHAR(80) NULL,
  error_message TEXT NULL,
  scanned_by BIGINT UNSIGNED NULL,
  scanned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ai_scan_document FOREIGN KEY (document_id) REFERENCES application_documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_scan_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_scan_user FOREIGN KEY (scanned_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_ai_scan_application (application_id),
  INDEX idx_ai_scan_status (scan_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ai_analytics_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  metrics JSON NOT NULL,
  insights JSON NOT NULL,
  model VARCHAR(80) NOT NULL,
  generated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ai_report_user FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_ai_report_created (created_at)
) ENGINE=InnoDB;

-- Import once after migrations 002 through 007. No existing records are removed.
CREATE TABLE IF NOT EXISTS notification_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_key CHAR(64) NOT NULL UNIQUE,
  application_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  audience ENUM('all','staff') NOT NULL DEFAULT 'all',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS email_outbox (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  locked_at DATETIME NULL,
  sent_at DATETIME NULL,
  last_error VARCHAR(250) NULL,
  UNIQUE KEY uq_event_recipient (event_id,user_id),
  INDEX idx_outbox_due (status,available_at),
  FOREIGN KEY (event_id) REFERENCES notification_events(id) ON DELETE RESTRICT,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS email_delivery_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  outbox_id BIGINT UNSIGNED NOT NULL,
  attempt_number INT UNSIGNED NOT NULL,
  outcome ENUM('sent','failed','uncertain','cancelled') NOT NULL,
  error_message VARCHAR(250) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (outbox_id) REFERENCES email_outbox(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS document_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(120) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  uploaded_at DATETIME NOT NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  archived_at DATETIME NULL,
  UNIQUE KEY uq_document_file (document_id,stored_name),
  FOREIGN KEY (document_id) REFERENCES application_documents(id) ON DELETE RESTRICT,
  FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS document_scan_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version_id BIGINT UNSIGNED NOT NULL,
  snapshot_key CHAR(64) NOT NULL UNIQUE,
  result_json JSON NOT NULL,
  scanned_at DATETIME NOT NULL,
  scanned_by BIGINT UNSIGNED NULL,
  FOREIGN KEY (version_id) REFERENCES document_versions(id) ON DELETE RESTRICT,
  FOREIGN KEY (scanned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS document_review_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version_id BIGINT UNSIGNED NOT NULL,
  reviewer_id BIGINT UNSIGNED NOT NULL,
  note TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (version_id) REFERENCES document_versions(id) ON DELETE RESTRICT,
  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS application_assignments (
  application_id BIGINT UNSIGNED PRIMARY KEY,
  reviewer_id BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  due_at DATETIME NOT NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE RESTRICT,
  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_assignment_due (due_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS assignment_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  reviewer_id BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NOT NULL,
  due_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE RESTRICT,
  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS permit_verification (
  application_id BIGINT UNSIGNED PRIMARY KEY,
  token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  issued_at DATETIME NOT NULL,
  valid_until DATETIME NOT NULL,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
-- Only currently available uploads and scans can be backfilled.
INSERT INTO document_versions (document_id,original_name,stored_name,mime_type,file_size,uploaded_at,uploaded_by)
SELECT d.id,d.original_name,d.stored_name,d.mime_type,d.file_size,d.uploaded_at,a.user_id
FROM application_documents d JOIN applications a ON a.id=d.application_id
ON DUPLICATE KEY UPDATE document_id=VALUES(document_id);
