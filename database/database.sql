-- Giftly · PHP 8 / MySQL 8 schema and local demo data
CREATE DATABASE IF NOT EXISTS giftly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE giftly;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS donation_items;
DROP TABLE IF EXISTS donation_pledges;
DROP TABLE IF EXISTS request_requirements;
DROP TABLE IF EXISTS clothing_requests;
DROP TABLE IF EXISTS charities;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('donor','charity','admin') NOT NULL DEFAULT 'donor',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_active (role,is_active)
) ENGINE=InnoDB;

CREATE TABLE charities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  organization_name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  contact_phone VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_charities_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_charities_verified (verified)
) ENGINE=InnoDB;

CREATE TABLE clothing_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  charity_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  season ENUM('All season','Spring','Summer','Autumn','Winter') NOT NULL DEFAULT 'All season',
  urgency ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  location VARCHAR(150) NOT NULL,
  collection_location VARCHAR(255) NOT NULL,
  collection_notes VARCHAR(1000) NULL,
  image_path VARCHAR(255) NULL,
  status ENUM('active','closed','fulfilled') NOT NULL DEFAULT 'active',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_requests_charity FOREIGN KEY (charity_id) REFERENCES charities(id) ON DELETE CASCADE,
  INDEX idx_requests_browse (status,season,urgency,created_at),
  INDEX idx_requests_charity (charity_id,status)
) ENGINE=InnoDB;

CREATE TABLE request_requirements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(80) NOT NULL,
  size_label VARCHAR(40) NOT NULL,
  quantity_needed INT UNSIGNED NOT NULL,
  condition_note VARCHAR(255) NULL,
  CONSTRAINT fk_requirements_request FOREIGN KEY (request_id) REFERENCES clothing_requests(id) ON DELETE CASCADE,
  INDEX idx_requirements_request (request_id),
  INDEX idx_requirements_filter (category,size_label)
) ENGINE=InnoDB;

CREATE TABLE donation_pledges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donor_id BIGINT UNSIGNED NOT NULL,
  request_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','approved','scheduled','received','cancelled') NOT NULL DEFAULT 'pending',
  donor_notes VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pledges_donor FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pledges_request FOREIGN KEY (request_id) REFERENCES clothing_requests(id) ON DELETE RESTRICT,
  INDEX idx_pledges_donor (donor_id,status,created_at),
  INDEX idx_pledges_request (request_id,status,created_at)
) ENGINE=InnoDB;

CREATE TABLE donation_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pledge_id BIGINT UNSIGNED NOT NULL,
  request_requirement_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(80) NOT NULL,
  size_label VARCHAR(40) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  CONSTRAINT fk_items_pledge FOREIGN KEY (pledge_id) REFERENCES donation_pledges(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_requirement FOREIGN KEY (request_requirement_id) REFERENCES request_requirements(id) ON DELETE RESTRICT,
  INDEX idx_items_pledge (pledge_id),
  INDEX idx_items_requirement (request_requirement_id)
) ENGINE=InnoDB;

CREATE TABLE appointments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pledge_id BIGINT UNSIGNED NOT NULL,
  appointment_date DATE NOT NULL,
  time_slot TIME NOT NULL,
  status ENUM('booked','completed','cancelled') NOT NULL DEFAULT 'booked',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_appointments_pledge FOREIGN KEY (pledge_id) REFERENCES donation_pledges(id) ON DELETE CASCADE,
  INDEX idx_appointments_date (appointment_date,time_slot,status),
  INDEX idx_appointments_pledge (pledge_id,status)
) ENGINE=InnoDB;

CREATE TABLE activity_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(255) NOT NULL,
  entity_type VARCHAR(60) NULL,
  entity_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_activity_recent (created_at),
  INDEX idx_activity_entity (entity_type,entity_id)
) ENGINE=InnoDB;

-- All seeded accounts use the demo password: password
INSERT INTO users (id,name,email,password_hash,role,is_active,created_at) VALUES
(1,'Giftly Administrator','admin@giftly.local','$2y$10$S.mW5YXbmiukiEbwbR/eEOygs8jfn2SeT0a0/c1.sVRbL6kwh38hi','admin',1,NOW()-INTERVAL 60 DAY),
(2,'Amelia Jones','amelia@giftly.local','$2y$10$S.mW5YXbmiukiEbwbR/eEOygs8jfn2SeT0a0/c1.sVRbL6kwh38hi','donor',1,NOW()-INTERVAL 28 DAY),
(3,'Marcus Green','marcus@giftly.local','$2y$10$S.mW5YXbmiukiEbwbR/eEOygs8jfn2SeT0a0/c1.sVRbL6kwh38hi','donor',1,NOW()-INTERVAL 18 DAY),
(4,'Harbour House Team','hello@harbourhouse.local','$2y$10$S.mW5YXbmiukiEbwbR/eEOygs8jfn2SeT0a0/c1.sVRbL6kwh38hi','charity',1,NOW()-INTERVAL 90 DAY),
(5,'New Start Collective','team@newstart.local','$2y$10$S.mW5YXbmiukiEbwbR/eEOygs8jfn2SeT0a0/c1.sVRbL6kwh38hi','charity',1,NOW()-INTERVAL 75 DAY),
(6,'Sofia Bennett','sofia@giftly.local','$2y$10$S.mW5YXbmiukiEbwbR/eEOygs8jfn2SeT0a0/c1.sVRbL6kwh38hi','donor',1,NOW()-INTERVAL 8 DAY);

INSERT INTO charities (id,user_id,organization_name,description,contact_phone,address,verified) VALUES
(1,4,'Harbour House','Supporting families moving from temporary accommodation into secure homes.','0113 555 0101','21 Market Street, Leeds',1),
(2,5,'New Start Collective','A neighbourhood support network for people rebuilding after hardship.','0113 555 0102','8 Rose Lane, Leeds',1);

INSERT INTO clothing_requests (id,charity_id,title,description,season,urgency,location,collection_location,collection_notes,image_path,status,is_featured,created_at) VALUES
(1,1,'Winter layers for families finding home','We are welcoming families into new homes this month and need warm, durable layers for adults. Coats should be clean, wearable and ready for the cold walk to school, work and appointments.','Winter','urgent','Leeds','21 Market Street, Leeds','Use the side entrance. Free 15-minute parking is available.','🧥','active',1,NOW()-INTERVAL 8 DAY),
(2,2,'School uniforms for a confident September','Help children begin the term feeling like they belong. We need clean school basics in common primary-age sizes.','Autumn','high','Leeds','8 Rose Lane, Leeds','Please bag uniform items by size where possible.','🎒','active',1,NOW()-INTERVAL 5 DAY),
(3,1,'Everyday essentials for women','Soft, practical everyday clothes help women joining our employment and wellbeing programme feel prepared for their next step.','All season','normal','Leeds','21 Market Street, Leeds','Reception is open Tuesday to Saturday.','👚','active',0,NOW()-INTERVAL 2 DAY);

INSERT INTO request_requirements (id,request_id,category,size_label,quantity_needed,condition_note) VALUES
(1,1,'Coats & jackets','M',30,'Warm, clean and fully wearable'),
(2,1,'Coats & jackets','L',25,'Warm, clean and fully wearable'),
(3,1,'Tops','XL',20,'Long-sleeved layers preferred'),
(4,2,'School uniforms','Children',35,'Grey trousers or skirts'),
(5,2,'Tops','Children',40,'White polo shirts or button shirts'),
(6,2,'Shoes','Children',25,'Sturdy school shoes'),
(7,3,'Tops','M',20,'Smart-casual and clean'),
(8,3,'Trousers & jeans','M',18,'Smart-casual and clean'),
(9,3,'Dresses & skirts','L',15,'Everyday, seasonally appropriate');

INSERT INTO donation_pledges (id,donor_id,request_id,status,donor_notes,created_at) VALUES
(1,2,1,'received','I have washed winter coats ready to bring.',NOW()-INTERVAL 6 DAY),
(2,3,2,'pending','I can bring these after work next week.',NOW()-INTERVAL 1 DAY),
(3,6,3,'approved','Everything is clean and in great condition.',NOW()-INTERVAL 1 DAY),
(4,2,1,'scheduled','Two more coats are ready for drop-off.',NOW()-INTERVAL 3 HOUR);

INSERT INTO donation_items (pledge_id,request_requirement_id,category,size_label,quantity) VALUES
(1,1,'Coats & jackets','M',8),(1,2,'Coats & jackets','L',5),(2,4,'School uniforms','Children',4),(2,5,'Tops','Children',6),(3,7,'Tops','M',3),(3,8,'Trousers & jeans','M',2),(4,1,'Coats & jackets','M',2);

INSERT INTO appointments (pledge_id,appointment_date,time_slot,status) VALUES
(1,CURDATE()-INTERVAL 5 DAY,'10:00:00','completed'),
(4,CURDATE()+INTERVAL 2 DAY,'14:00:00','booked');

INSERT INTO activity_log (user_id,action,entity_type,entity_id,created_at) VALUES
(2,'donated clothing that was received','pledge',1,NOW()-INTERVAL 5 DAY),
(5,'posted a clothing request','request',2,NOW()-INTERVAL 5 DAY),
(3,'pledged clothing for School uniforms for a confident September','pledge',2,NOW()-INTERVAL 1 DAY),
(6,'pledged clothing for Everyday essentials for women','pledge',3,NOW()-INTERVAL 1 DAY);
