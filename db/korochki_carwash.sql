DROP DATABASE IF EXISTS korochki_carwash;
CREATE DATABASE korochki_carwash CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE korochki_carwash;

-- 1) Roles
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 2) Users
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(200) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  role_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 3) Courses dictionary
CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 4) Payment methods
CREATE TABLE payment_methods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 5) Application statuses
CREATE TABLE application_statuses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 6) Applications (course requests)
CREATE TABLE applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  course_id INT NOT NULL,
  car_model VARCHAR(120) NOT NULL,
  visit_date DATE NOT NULL,
  visit_time TIME NOT NULL,
  payment_method_id INT NOT NULL,
  status_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_app_course
    FOREIGN KEY (course_id) REFERENCES courses(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_app_payment
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_app_status
    FOREIGN KEY (status_id) REFERENCES application_statuses(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 7) Reviews
CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  text TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed dictionaries
INSERT INTO roles (name) VALUES
  ('client'),
  ('admin');

INSERT INTO courses (name) VALUES
  ('Основы алгоритмизации и программирования'),
  ('Основы веб-дизайна'),
  ('Основы проектирования баз данных');

INSERT INTO payment_methods (name) VALUES
  ('Наличными'),
  ('Переводом по номеру телефона');

INSERT INTO application_statuses (name) VALUES
  ('Новая'),
  ('Идёт обучение'),
  ('Обучение завершено');

-- Test users
-- Note: in production, store only hashed passwords.
INSERT INTO users (login, password_hash, full_name, phone, email, role_id) VALUES
  (
    'Admin',
    'KorokNET',
    'Администратор',
    '8(000)000-00-00',
    'admin@carwash.local',
    (SELECT id FROM roles WHERE name = 'admin')
  ),
  (
    'client01',
    'qazqaz123',
    'Иванов Иван Иванович',
    '8(999)123-45-67',
    'ivanov@example.com',
    (SELECT id FROM roles WHERE name = 'client')
  );

-- Test applications
INSERT INTO applications (
  user_id, course_id, car_model, visit_date, visit_time, payment_method_id, status_id
) VALUES
  (
    (SELECT id FROM users WHERE login = 'client01'),
    (SELECT id FROM courses WHERE name = 'Основы веб-дизайна'),
    'Kia Rio',
    CURDATE(),
    '10:30:00',
    (SELECT id FROM payment_methods WHERE name = 'Наличными'),
    (SELECT id FROM application_statuses WHERE name = 'Новая')
  );

-- Test review
INSERT INTO reviews (user_id, text) VALUES
  (
    (SELECT id FROM users WHERE login = 'client01'),
    'Быстро и качественно, рекомендую.'
  );
