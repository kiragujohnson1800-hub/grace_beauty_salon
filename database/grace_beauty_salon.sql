CREATE DATABASE IF NOT EXISTS grace_beauty_salon
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE grace_beauty_salon;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer', 'salonist', 'admin') NOT NULL DEFAULT 'customer',
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  otp_hash VARCHAR(255) NULL,
  otp_expires_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  image_url VARCHAR(500) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  display_order SMALLINT NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_services_active_order (is_active, display_order),
  CONSTRAINT fk_services_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  salonist_id BIGINT UNSIGNED NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  notes VARCHAR(1000) NULL,
  status ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_appointments_customer (customer_id, appointment_date),
  KEY idx_appointments_salonist (salonist_id, appointment_date),
  KEY idx_appointments_date_status (appointment_date, status),
  CONSTRAINT fk_appointments_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_appointments_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
  CONSTRAINT fk_appointments_salonist FOREIGN KEY (salonist_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO services (name, description, price, duration_minutes, image_url, display_order)
SELECT 'Signature haircut', 'A considered cut, a relaxing wash, and a finish styled just for you.', 55.00, 60,
       'https://images.unsplash.com/photo-1562322140-8baeececf3df?auto=format&fit=crop&w=900&q=85', 1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE name = 'Signature haircut');

INSERT INTO services (name, description, price, duration_minutes, image_url, display_order)
SELECT 'Glow facial', 'A gentle, nourishing facial to leave your skin feeling fresh and luminous.', 75.00, 60,
       'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=900&q=85', 2
WHERE NOT EXISTS (SELECT 1 FROM services WHERE name = 'Glow facial');

INSERT INTO services (name, description, price, duration_minutes, image_url, display_order)
SELECT 'Manicure ritual', 'A neat shape, cuticle care, and your choice of a polished finish.', 38.00, 45,
       'https://images.unsplash.com/photo-1604654894610-df63bc536371?auto=format&fit=crop&w=900&q=85', 3
WHERE NOT EXISTS (SELECT 1 FROM services WHERE name = 'Manicure ritual');

INSERT INTO services (name, description, price, duration_minutes, image_url, display_order)
SELECT 'Soft glam styling', 'A refined makeup look for a celebration, an event, or just because.', 90.00, 75,
       'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&w=900&q=85', 4
WHERE NOT EXISTS (SELECT 1 FROM services WHERE name = 'Soft glam styling');