USE korochki_carwash;

-- 1) users.login: обязательный
ALTER TABLE users
MODIFY login VARCHAR(50) NOT NULL;

-- 2) users.email: уникальный
ALTER TABLE users
ADD UNIQUE KEY uq_users_email (email);

-- 3) reviews.created_at: корректный timestamp
ALTER TABLE reviews
MODIFY created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 4) reviews.text: расширяем до TEXT
ALTER TABLE reviews
MODIFY text TEXT NOT NULL;

-- 5) payment_methods.name: уникальный справочник
ALTER TABLE payment_methods
ADD UNIQUE KEY uq_payment_methods_name (name);
