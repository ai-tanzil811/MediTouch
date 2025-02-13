
CREATE DATABASE IF NOT EXISTS telemedicine;
USE telemedicine;


CREATE TABLE `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'doctor', 'patient') NOT NULL,
  `profile_photo` VARCHAR(255) DEFAULT NULL,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `doctors` (
  `doctor_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `specialization` VARCHAR(100) DEFAULT 'General Practitioner',
  `contact_number` VARCHAR(15) DEFAULT NULL,
  `availability_status` ENUM('available', 'unavailable', 'busy') DEFAULT 'available',
  `consultation_fee` DECIMAL(10,2) DEFAULT NULL,
  `years_of_experience` INT(11) DEFAULT NULL,
  `qualifications` TEXT DEFAULT NULL,
  `consultation_hours` VARCHAR(255) DEFAULT NULL,
  `rating` DECIMAL(3,2) DEFAULT 0.00,
  `total_consultations` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `profile_photo` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`doctor_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `patients` (
  `patient_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `gender` ENUM('Male', 'Female', 'Other') DEFAULT NULL,
  `contact_number` VARCHAR(15) DEFAULT NULL,
  `medical_history` TEXT DEFAULT NULL,
  `allergies` TEXT DEFAULT NULL,
  `emergency_contact_name` VARCHAR(100) DEFAULT NULL,
  `emergency_contact_number` VARCHAR(15) DEFAULT NULL,
  `insurance_provider` VARCHAR(100) DEFAULT NULL,
  `insurance_policy_number` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `profile_photo` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`patient_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `appointments` (
  `appointment_id` INT(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` INT(11) NOT NULL,
  `patient_id` INT(11) NOT NULL,
  `appointment_date` DATETIME NOT NULL,
  `end_time` DATETIME DEFAULT NULL,
  `status` ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'missed') DEFAULT 'scheduled',
  `reason` TEXT DEFAULT NULL,
  `symptoms` TEXT DEFAULT NULL,
  `consultation_mode` ENUM('online', 'offline') DEFAULT 'online',
  `consultation_type` ENUM('regular', 'follow_up', 'emergency') DEFAULT 'regular',
  `payment_status` ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `prescription_file` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`appointment_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `consultation_notes` (
  `notes_id` INT(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` INT(11) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`notes_id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `consultation_notes_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `prescriptions` (
  `prescription_id` INT(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` INT(11) NOT NULL,
  `patient_id` INT(11) NOT NULL,
  `doctor_id` INT(11) NOT NULL,
  `medication` TEXT NOT NULL,
  `dosage` VARCHAR(100) DEFAULT NULL,
  `frequency` VARCHAR(100) DEFAULT NULL,
  `duration` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`prescription_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `password_resets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `phone_number` VARCHAR(15) NOT NULL,
  `otp` VARCHAR(6) NOT NULL,
  `expiry` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `password_reset_attempts` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `otp` VARCHAR(6) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `used` BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `medical_records` (
  `record_id` INT(11) NOT NULL AUTO_INCREMENT,
  `patient_id` INT(11) NOT NULL,
  `doctor_id` INT(11) NOT NULL,
  `record_type` ENUM('consultation', 'test_result', 'prescription', 'referral') NOT NULL,
  `document_path` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`record_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE,
  CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;