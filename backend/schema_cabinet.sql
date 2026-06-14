CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(32) NOT NULL,
    email VARCHAR(255) NULL,
    name VARCHAR(160) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_users_phone (phone),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    ts INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    phone VARCHAR(32) NOT NULL,
    email VARCHAR(255) NOT NULL DEFAULT '',
    service VARCHAR(64) NOT NULL,
    comment TEXT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'new',
    status_note TEXT NULL,
    status_updated_at INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_requests_user (user_id),
    KEY idx_requests_phone (phone),
    KEY idx_requests_ts (ts),
    CONSTRAINT fk_requests_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
