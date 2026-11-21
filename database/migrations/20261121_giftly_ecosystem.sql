-- Giftly ecosystem upgrade. This migration is additive: it does not drop or recreate existing data.
USE giftly;

ALTER TABLE charities
  ADD COLUMN latitude DECIMAL(10,7) NULL AFTER address,
  ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude,
  ADD COLUMN website VARCHAR(255) NULL AFTER longitude;

ALTER TABLE appointments
  MODIFY status ENUM('booked','checked_in','completed','cancelled') NOT NULL DEFAULT 'booked',
  ADD COLUMN checked_in_at DATETIME NULL AFTER status;

CREATE TABLE achievements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL,
  icon VARCHAR(24) NOT NULL DEFAULT '✦',
  metric ENUM('completed_donations','items_donated','winter_items') NOT NULL,
  target_value INT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_achievements_active (is_active, metric)
) ENGINE=InnoDB;

CREATE TABLE user_achievements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  achievement_id BIGINT UNSIGNED NOT NULL,
  earned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_achievement (user_id, achievement_id),
  CONSTRAINT fk_user_achievements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_achievements_achievement FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
  INDEX idx_user_achievements_user (user_id, earned_at)
) ENGINE=InnoDB;

CREATE TABLE points_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  pledge_id BIGINT UNSIGNED NULL,
  points INT NOT NULL,
  reason_code VARCHAR(80) NOT NULL,
  description VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_points_pledge_reason (pledge_id, reason_code),
  CONSTRAINT fk_points_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_points_pledge FOREIGN KEY (pledge_id) REFERENCES donation_pledges(id) ON DELETE SET NULL,
  INDEX idx_points_leaderboard (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE community_challenges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  description TEXT NOT NULL,
  category VARCHAR(80) NULL,
  target_items INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('draft','active','closed') NOT NULL DEFAULT 'draft',
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_challenges_admin FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_challenges_public (status, start_date, end_date),
  INDEX idx_challenges_category (category)
) ENGINE=InnoDB;

CREATE TABLE challenge_participations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  challenge_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  items_contributed INT UNSIGNED NOT NULL DEFAULT 0,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_challenge_participant (challenge_id, user_id),
  CONSTRAINT fk_participation_challenge FOREIGN KEY (challenge_id) REFERENCES community_challenges(id) ON DELETE CASCADE,
  CONSTRAINT fk_participation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_challenge_participation (challenge_id, items_contributed)
) ENGINE=InnoDB;

CREATE TABLE challenge_contributions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  challenge_id BIGINT UNSIGNED NOT NULL,
  pledge_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_challenge_pledge (challenge_id, pledge_id),
  CONSTRAINT fk_contribution_challenge FOREIGN KEY (challenge_id) REFERENCES community_challenges(id) ON DELETE CASCADE,
  CONSTRAINT fk_contribution_pledge FOREIGN KEY (pledge_id) REFERENCES donation_pledges(id) ON DELETE CASCADE,
  CONSTRAINT fk_contribution_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL DEFAULT 'system',
  title VARCHAR(150) NOT NULL,
  message VARCHAR(500) NOT NULL,
  link_path VARCHAR(255) NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notifications_inbox (user_id, read_at, created_at)
) ENGINE=InnoDB;

CREATE TABLE donation_qr_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id BIGINT UNSIGNED NOT NULL,
  token VARCHAR(96) NOT NULL UNIQUE,
  redeemed_at DATETIME NULL,
  redeemed_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_qr_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  CONSTRAINT fk_qr_redeemer FOREIGN KEY (redeemed_by) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_qr_appointment (appointment_id),
  INDEX idx_qr_token (token)
) ENGINE=InnoDB;

CREATE TABLE donation_certificates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pledge_id BIGINT UNSIGNED NOT NULL,
  certificate_number VARCHAR(40) NOT NULL UNIQUE,
  issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_certificates_pledge FOREIGN KEY (pledge_id) REFERENCES donation_pledges(id) ON DELETE CASCADE,
  UNIQUE KEY uq_certificate_pledge (pledge_id)
) ENGINE=InnoDB;

CREATE TABLE user_preferences (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  theme ENUM('light','dark','system') NOT NULL DEFAULT 'system',
  appointment_reminders TINYINT(1) NOT NULL DEFAULT 1,
  challenge_updates TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE donor_inventory (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(80) NOT NULL,
  size_label VARCHAR(40) NOT NULL,
  season ENUM('All season','Spring','Summer','Autumn','Winter') NOT NULL DEFAULT 'All season',
  quantity INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inventory_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_inventory_match (user_id, category, size_label, season)
) ENGINE=InnoDB;

CREATE TABLE contact_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_contact_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_contact_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE platform_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO achievements (slug,name,description,icon,metric,target_value) VALUES
  ('first-donation','First Donation','Completed your first confirmed donation.','♥','completed_donations',1),
  ('community-hero','Community Hero','Completed ten confirmed donations.','★','completed_donations',10),
  ('winter-helper','Winter Helper','Donated five items to winter clothing needs.','❄','winter_items',5),
  ('hundred-items','100 Items Donated','Put one hundred confirmed items back into circulation.','100','items_donated',100);

INSERT IGNORE INTO community_challenges (title,slug,description,category,target_items,start_date,end_date,status,is_demo) VALUES
  ('Winter Warmth — Demo','winter-warmth-demo','A clearly labelled demo challenge for warm coats and layers.','Coats & jackets',100,CURDATE()-INTERVAL 15 DAY,CURDATE()+INTERVAL 45 DAY,'active',1),
  ('Back to School — Demo','back-to-school-demo','A clearly labelled demo challenge for school clothing essentials.','School uniforms',120,CURDATE()-INTERVAL 15 DAY,CURDATE()+INTERVAL 45 DAY,'active',1);

INSERT IGNORE INTO platform_settings (setting_key,setting_value) VALUES
  ('points_per_confirmed_item','10'),
  ('support_email','hello@giftly.local');
