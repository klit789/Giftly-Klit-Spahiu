-- Additive Stories of Change and Happy People Gallery content storage.
USE giftly;

CREATE TABLE story_categories (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  slug VARCHAR(90) NOT NULL UNIQUE,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO story_categories (name, slug, sort_order) VALUES
  ('Families Supported', 'families-supported', 1),
  ('Children and Education', 'children-education', 2),
  ('Winter Support', 'winter-support', 3),
  ('Community Projects', 'community-projects', 4),
  ('Emergency Assistance', 'emergency-assistance', 5),
  ('Clothing Donations', 'clothing-donations', 6),
  ('Other', 'other', 7);

CREATE TABLE gallery_categories (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  slug VARCHAR(90) NOT NULL UNIQUE,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO gallery_categories (name, slug, sort_order) VALUES
  ('Families', 'families', 1),
  ('Children', 'children', 2),
  ('Winter Donations', 'winter-donations', 3),
  ('School Support', 'school-support', 4),
  ('Community', 'community', 5),
  ('General Donations', 'general-donations', 6);

CREATE TABLE change_stories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  charity_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  campaign_id BIGINT UNSIGNED NULL,
  category_id TINYINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  excerpt VARCHAR(400) NOT NULL,
  description MEDIUMTEXT NOT NULL,
  featured_image_path VARCHAR(255) NOT NULL,
  featured_alt VARCHAR(180) NULL,
  quote VARCHAR(500) NULL,
  impact_stats_json TEXT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_change_stories_charity FOREIGN KEY (charity_id) REFERENCES charities(id) ON DELETE CASCADE,
  CONSTRAINT fk_change_stories_author FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_change_stories_campaign FOREIGN KEY (campaign_id) REFERENCES clothing_requests(id) ON DELETE SET NULL,
  CONSTRAINT fk_change_stories_category FOREIGN KEY (category_id) REFERENCES story_categories(id) ON DELETE RESTRICT,
  INDEX idx_change_stories_public (status, published_at),
  INDEX idx_change_stories_charity (charity_id, status, updated_at),
  INDEX idx_change_stories_category (category_id, status)
) ENGINE=InnoDB;

CREATE TABLE change_story_images (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  story_id BIGINT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  alt_text VARCHAR(180) NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_change_story_images_story FOREIGN KEY (story_id) REFERENCES change_stories(id) ON DELETE CASCADE,
  INDEX idx_change_story_images_order (story_id, sort_order, id)
) ENGINE=InnoDB;

CREATE TABLE happy_gallery_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  charity_id BIGINT UNSIGNED NOT NULL,
  submitted_by BIGINT UNSIGNED NULL,
  campaign_id BIGINT UNSIGNED NULL,
  category_id TINYINT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  caption VARCHAR(500) NOT NULL,
  location_safe VARCHAR(100) NULL,
  consent_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  guardian_consent TINYINT(1) NOT NULL DEFAULT 0,
  moderation_status ENUM('pending','approved','rejected','removed') NOT NULL DEFAULT 'pending',
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  rejection_reason VARCHAR(500) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  removal_requested_at DATETIME NULL,
  published_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_happy_gallery_charity FOREIGN KEY (charity_id) REFERENCES charities(id) ON DELETE CASCADE,
  CONSTRAINT fk_happy_gallery_submitter FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_happy_gallery_campaign FOREIGN KEY (campaign_id) REFERENCES clothing_requests(id) ON DELETE SET NULL,
  CONSTRAINT fk_happy_gallery_category FOREIGN KEY (category_id) REFERENCES gallery_categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_happy_gallery_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_happy_gallery_consent CHECK (consent_confirmed = 1),
  INDEX idx_happy_gallery_public (moderation_status, is_published, published_at),
  INDEX idx_happy_gallery_charity (charity_id, created_at),
  INDEX idx_happy_gallery_category (category_id, moderation_status, is_published),
  INDEX idx_happy_gallery_removal (removal_requested_at)
) ENGINE=InnoDB;

CREATE TABLE happy_gallery_consents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gallery_entry_id BIGINT UNSIGNED NOT NULL,
  confirmed_by BIGINT UNSIGNED NULL,
  consent_type ENUM('pictured_people','guardian_for_child','child_safety_attestation') NOT NULL,
  statement_version VARCHAR(30) NOT NULL,
  confirmed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_happy_gallery_consent_entry FOREIGN KEY (gallery_entry_id) REFERENCES happy_gallery_entries(id) ON DELETE CASCADE,
  CONSTRAINT fk_happy_gallery_consent_user FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_happy_gallery_consent_entry (gallery_entry_id, confirmed_at)
) ENGINE=InnoDB;

CREATE TABLE content_moderation_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gallery_entry_id BIGINT UNSIGNED NOT NULL,
  moderator_id BIGINT UNSIGNED NULL,
  action ENUM('approved','rejected','unpublished','removed','removal_requested') NOT NULL,
  note VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_content_moderation_entry FOREIGN KEY (gallery_entry_id) REFERENCES happy_gallery_entries(id) ON DELETE CASCADE,
  CONSTRAINT fk_content_moderation_moderator FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_content_moderation_entry (gallery_entry_id, created_at),
  INDEX idx_content_moderation_action (action, created_at)
) ENGINE=InnoDB;

CREATE TABLE content_removal_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gallery_entry_id BIGINT UNSIGNED NULL,
  requester_email VARCHAR(190) NULL,
  reason VARCHAR(1000) NOT NULL,
  was_published TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('open','resolved','declined') NOT NULL DEFAULT 'open',
  resolved_by BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  resolution_note VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_content_removal_entry FOREIGN KEY (gallery_entry_id) REFERENCES happy_gallery_entries(id) ON DELETE SET NULL,
  CONSTRAINT fk_content_removal_admin FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_content_removal_status (status, created_at)
) ENGINE=InnoDB;
