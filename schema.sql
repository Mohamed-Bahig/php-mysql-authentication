CREATE DATABASE IF NOT EXISTS registration_lab4
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE registration_lab4;

CREATE TABLE IF NOT EXISTS `user` (
  user_id INT NOT NULL AUTO_INCREMENT,
  email VARCHAR(225) NOT NULL,
  name VARCHAR(225) NOT NULL,
  password VARCHAR(255) NOT NULL,
  registration_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY unique_user_email (email)
);

