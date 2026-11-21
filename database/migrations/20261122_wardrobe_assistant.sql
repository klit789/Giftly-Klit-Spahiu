-- Additive Giftly wardrobe and assistant storage migration.
USE giftly;

CREATE TABLE wardrobe_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(80) NOT NULL,
  size_label VARCHAR(40) NOT NULL,
  season ENUM('All season','Spring','Summer','Autumn','Winter') NOT NULL DEFAULT 'All season',
  quantity INT UNSIGNED NOT NULL,
  condition_label ENUM('New','Like new','Good','Gently used') NOT NULL,
  color VARCHAR(50) NULL,
  description VARCHAR(500) NULL,
  image_path VARCHAR(255) NULL,
  availability ENUM('available','unavailable') NOT NULL DEFAULT 'available',
  deleted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_wardrobe_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT chk_wardrobe_quantity CHECK (quantity >= 0),
  INDEX idx_wardrobe_owner (user_id, availability, created_at),
  INDEX idx_wardrobe_match (user_id, category, size_label, season)
) ENGINE=InnoDB;

CREATE TABLE wardrobe_reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wardrobe_item_id BIGINT UNSIGNED NOT NULL,
  pledge_id BIGINT UNSIGNED NOT NULL,
  donation_item_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  status ENUM('reserved','released','donated') NOT NULL DEFAULT 'reserved',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_wardrobe_reservation_item FOREIGN KEY (wardrobe_item_id) REFERENCES wardrobe_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wardrobe_reservation_pledge FOREIGN KEY (pledge_id) REFERENCES donation_pledges(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wardrobe_reservation_donation_item FOREIGN KEY (donation_item_id) REFERENCES donation_items(id) ON DELETE RESTRICT,
  CONSTRAINT chk_wardrobe_reservation_quantity CHECK (quantity > 0),
  INDEX idx_wardrobe_reservation_item (wardrobe_item_id, status),
  INDEX idx_wardrobe_reservation_pledge (pledge_id, status)
) ENGINE=InnoDB;

CREATE TABLE wardrobe_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wardrobe_item_id BIGINT UNSIGNED NOT NULL,
  reservation_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  action ENUM('added','updated','reserved','released','donated','removed') NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wardrobe_transaction_item FOREIGN KEY (wardrobe_item_id) REFERENCES wardrobe_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wardrobe_transaction_reservation FOREIGN KEY (reservation_id) REFERENCES wardrobe_reservations(id) ON DELETE SET NULL,
  CONSTRAINT fk_wardrobe_transaction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_wardrobe_transaction_owner (user_id, created_at),
  INDEX idx_wardrobe_transaction_item (wardrobe_item_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE assistant_conversations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  session_hash CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_assistant_conversation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_assistant_session (session_hash),
  INDEX idx_assistant_user (user_id, updated_at)
) ENGINE=InnoDB;

CREATE TABLE assistant_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  role ENUM('user','assistant') NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_assistant_message_conversation FOREIGN KEY (conversation_id) REFERENCES assistant_conversations(id) ON DELETE CASCADE,
  INDEX idx_assistant_message_history (conversation_id, created_at)
) ENGINE=InnoDB;
