USE medical_booking;

CREATE TABLE IF NOT EXISTS hospitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hospital_specialties (
    hospital_id INT NOT NULL,
    specialty_id INT NOT NULL,
    PRIMARY KEY (hospital_id, specialty_id),
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    FOREIGN KEY (specialty_id) REFERENCES specialties(id) ON DELETE CASCADE
);

ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS address TEXT;
ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS phone VARCHAR(20);
ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS email VARCHAR(100);

ALTER TABLE users ADD COLUMN IF NOT EXISTS hospital_id INT NULL AFTER role;
ALTER TABLE users MODIFY role ENUM('admin', 'hospital', 'patient') DEFAULT 'patient';

ALTER TABLE doctors ADD COLUMN IF NOT EXISTS hospital_id INT NULL AFTER user_id;
ALTER TABLE doctors ADD COLUMN IF NOT EXISTS approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER description;
ALTER TABLE doctors MODIFY user_id INT NULL;

INSERT INTO hospitals (name)
SELECT 'Bệnh viện mặc định'
WHERE NOT EXISTS (SELECT 1 FROM hospitals LIMIT 1);

UPDATE doctors
SET hospital_id = (SELECT id FROM hospitals ORDER BY id ASC LIMIT 1)
WHERE hospital_id IS NULL;

ALTER TABLE doctors MODIFY hospital_id INT NOT NULL;
