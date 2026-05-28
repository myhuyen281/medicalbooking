USE medical_booking;

CREATE TABLE IF NOT EXISTS homepage_banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    link_url VARCHAR(500),
    sort_order INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
