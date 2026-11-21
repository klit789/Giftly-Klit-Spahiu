-- Add confirmed country data for the community country map without changing existing records.
USE giftly;

ALTER TABLE charities
  ADD COLUMN country_code CHAR(3) NULL AFTER address,
  ADD INDEX idx_charities_country_code (country_code);
