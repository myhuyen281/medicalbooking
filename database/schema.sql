-- Giai đoạn 2: Thiết kế Cơ sở dữ liệu cho Hệ thống Đặt lịch Khám bệnh

CREATE DATABASE IF NOT EXISTS medical_booking_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medical_booking_db;

-- Bảng Người dùng (dung chung hoặc tách riêng)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'hospital', 'patient') DEFAULT 'patient',
    hospital_id INT NULL,
    hospital_approval_status ENUM('pending', 'approved', 'rejected') DEFAULT NULL,
    date_of_birth DATE,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Bảng Chuyên khoa
CREATE TABLE specialties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bảng Bệnh viện
CREATE TABLE hospitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    facility_type VARCHAR(30) NOT NULL DEFAULT 'public',
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    logo_url VARCHAR(500),
    poster_url VARCHAR(500),
    content_image_url VARCHAR(500),
    map_embed_url VARCHAR(1000),
    service_image_url VARCHAR(500),
    working_time VARCHAR(255),
    description TEXT,
    short_description TEXT,
    services_info TEXT,
    overview TEXT,
    rating DECIMAL(2, 1) DEFAULT 4.5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hospital_specialties (
    hospital_id INT NOT NULL,
    specialty_id INT NOT NULL,
    PRIMARY KEY (hospital_id, specialty_id),
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    FOREIGN KEY (specialty_id) REFERENCES specialties(id) ON DELETE CASCADE
);

CREATE TABLE hospital_banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

CREATE TABLE homepage_banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    link_url VARCHAR(500),
    sort_order INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hospital_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    schedule_text VARCHAR(255),
    detail_text TEXT,
    requires_insurance TINYINT(1) NOT NULL DEFAULT 0,
    price DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

-- Bảng Bác sĩ (liên kết với bệnh viện)
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    hospital_id INT NOT NULL,
    specialty_id INT NOT NULL,
    experience_years INT,
    consultation_fee DECIMAL(10, 2) NOT NULL,
    description TEXT,
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    FOREIGN KEY (specialty_id) REFERENCES specialties(id) ON DELETE CASCADE
);

-- Bảng Lịch làm việc
CREATE TABLE schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    work_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('available', 'booked', 'cancelled') DEFAULT 'available',
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);

-- Bảng Lịch hẹn
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    schedule_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    symptoms TEXT,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
);

-- Bảng Đánh giá
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);
