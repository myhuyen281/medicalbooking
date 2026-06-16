-- Bảng Hồ sơ Bệnh nhân (Patient Profiles)
-- Chứa thông tin chi tiết về hồ sơ y tế của bệnh nhân

CREATE TABLE IF NOT EXISTS patient_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    
    -- Thông tin cá nhân mở rộng
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    identity_card VARCHAR(20),
    insurance_number VARCHAR(30),
    
    -- Địa chỉ liên hệ
    province VARCHAR(100),
    district VARCHAR(100),
    ward VARCHAR(100),
    address_detail TEXT,
    
    -- Thông tin người thân/người liên hệ khẩn cấp
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relationship VARCHAR(50),
    
    -- Hồ sơ y tế
    blood_type VARCHAR(5),
    allergies TEXT,
    chronic_diseases TEXT,
    medications TEXT,
    medical_history TEXT,
    
    -- Thói quen sinh hoạt
    smoking TINYINT(1) DEFAULT 0,
    drinking_alcohol TINYINT(1) DEFAULT 0,
    exercise_frequency VARCHAR(50),
    
    -- Meta thông tin
    avatar_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;