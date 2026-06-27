CREATE TABLE IF NOT EXISTS `users` (
  `phone_number` VARCHAR(20) NOT NULL,
  `reputation_score` INT DEFAULT 50,
  PRIMARY KEY (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `phone_number` VARCHAR(20) NOT NULL,
  `incident_type` VARCHAR(100) NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `guides` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `assigned_region` VARCHAR(100) NOT NULL,
  `reputation_score` INT DEFAULT 50
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tourist_phone` VARCHAR(20) NOT NULL,
  `guide_id` INT NOT NULL,
  `ticket_token` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`guide_id`) REFERENCES `guides`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pre-seed default guides for the Kaduna Wildlife Sectors
INSERT IGNORE INTO `guides` (`id`, `name`, `assigned_region`, `reputation_score`) VALUES
(1, 'Bello Ibrahim', 'Kamuku National Park', 95),
(2, 'Grace John', 'Kajuru Castle', 92),
(3, 'Shehu Umar', 'Matsirga Waterfalls', 88),
(4, 'Auta Musa', 'Buruku', 85);