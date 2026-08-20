ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_code VARCHAR(6) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_expires DATETIME NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_code VARCHAR(6) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_expires DATETIME NULL;

-- Grandfather in everyone who signed up before this feature existed,
-- so they aren't suddenly locked out at login.
UPDATE users SET email_verified = 1 WHERE email_verified = 0;
