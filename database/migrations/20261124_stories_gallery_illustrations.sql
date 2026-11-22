-- Add explicit labels for Giftly-supplied illustrative images.
USE giftly;

ALTER TABLE change_stories
  ADD featured_image_is_illustrative TINYINT(1) NOT NULL DEFAULT 0 AFTER featured_alt;

ALTER TABLE happy_gallery_entries
  ADD is_illustrative TINYINT(1) NOT NULL DEFAULT 0 AFTER guardian_consent;
