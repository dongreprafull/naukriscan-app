-- V22: Manual QR/UPI payment flow.
-- Admin uploads a QR code image + UPI ID once (stored in system_settings).
-- User selects a paid plan, uploads a transaction ID + payment screenshot.
-- Admin reviews and verifies it, which activates the subscription.

ALTER TABLE payments
  ADD COLUMN plan_id BIGINT UNSIGNED NULL AFTER subscription_id,
  ADD COLUMN receipt_file_path VARCHAR(500) DEFAULT '' AFTER invoice_number,
  ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER receipt_file_path,
  ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
  ADD COLUMN admin_note VARCHAR(500) DEFAULT '' AFTER reviewed_at,
  ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER admin_note;

ALTER TABLE payments ADD INDEX idx_user_status (user_id, payment_status);

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
  ('payment_upi_id', ''),
  ('payment_qr_image', ''),
  ('payment_instructions', 'Pay the exact plan amount to the UPI ID or QR code above, then upload your transaction ID and a screenshot/receipt below. Your plan activates once our team verifies it (usually within a few hours).');
