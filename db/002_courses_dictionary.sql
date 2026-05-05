USE korochki_carwash;

-- 1) Справочник курсов
CREATE TABLE IF NOT EXISTS courses (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Три курса по заданию
INSERT IGNORE INTO courses (name) VALUES
  ('Основы алгоритмизации и программирования'),
  ('Основы веб-дизайна'),
  ('Основы проектирования баз данных');

-- 3) Добавить course_id в заявки
ALTER TABLE applications
ADD COLUMN course_id INT NULL AFTER user_id;

-- 4) Перенос значений из старого справочника services
UPDATE applications a
JOIN services s ON s.id = a.service_id
JOIN courses c ON c.name = s.name
SET a.course_id = c.id;

-- 5) Сделать обязательным и добавить FK
ALTER TABLE applications
MODIFY course_id INT NOT NULL,
ADD CONSTRAINT fk_applications_course
FOREIGN KEY (course_id) REFERENCES courses(id)
ON UPDATE CASCADE
ON DELETE RESTRICT;

-- 6) Удалить старую колонку service_id
ALTER TABLE applications
DROP FOREIGN KEY fk_app_service,
DROP COLUMN service_id;

-- 7) Удалить старый справочник
DROP TABLE services;
