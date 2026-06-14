CREATE TABLE IF NOT EXISTS part_models (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL,
    brand_slug VARCHAR(80) NOT NULL,
    name VARCHAR(160) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_part_models_slug (slug),
    KEY idx_part_models_brand (brand_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS catalog_parts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    meta VARCHAR(255) NOT NULL DEFAULT '',
    price VARCHAR(64) NOT NULL,
    image_url VARCHAR(512) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at INT UNSIGNED NOT NULL,
    updated_at INT UNSIGNED NOT NULL,
    KEY idx_catalog_parts_model (model_id, is_active),
    CONSTRAINT fk_catalog_parts_model
        FOREIGN KEY (model_id) REFERENCES part_models(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
