-- Safe cleanup script (OTP/contact leftovers only)
-- Keeps all active application schema, including:
--   prescriptions.reason_for_referral  <-- DO NOT DROP

-- Unused OTP tables
DROP TABLE IF EXISTS auth_email_otps;
DROP TABLE IF EXISTS auth_otp_challenges;
DROP TABLE IF EXISTS auth_otp_codes;
DROP TABLE IF EXISTS otp_codes;

-- Unused legacy contact columns
ALTER TABLE users DROP COLUMN IF EXISTS contact;
ALTER TABLE users DROP COLUMN IF EXISTS contact_number;
ALTER TABLE patients DROP COLUMN IF EXISTS contact;
ALTER TABLE patients DROP COLUMN IF EXISTS contact_number;

-- Optional cleanup for old denormalized fields no longer used by current code
ALTER TABLE users DROP COLUMN IF EXISTS lab_request;
ALTER TABLE users DROP COLUMN IF EXISTS lab_request_file_name;
