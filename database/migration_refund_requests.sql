-- Module quản lý hoàn tiền mô phỏng, không gọi API VNPAY
ALTER TABLE appointments
MODIFY COLUMN status ENUM('pending','confirmed','examining','completed','cancel_pending','cancelled') DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS refund_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    patient_id INT NOT NULL,
    hospital_id INT NOT NULL,
    paid_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    refund_rate TINYINT NOT NULL DEFAULT 0,
    refund_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(50) DEFAULT 'vnpay',
    bank_account_name VARCHAR(150) NULL,
    bank_account_number VARCHAR(50) NULL,
    bank_name VARCHAR(120) NULL,
    reason TEXT,
    cancelled_by ENUM('patient','hospital') NOT NULL DEFAULT 'patient',
    status ENUM('pending','refunded','rejected') NOT NULL DEFAULT 'pending',
    processed_by INT NULL,
    processed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_refund_appointment (appointment_id),
    KEY idx_refund_status (status),
    KEY idx_refund_patient (patient_id),
    KEY idx_refund_hospital (hospital_id),
    CONSTRAINT fk_refund_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    CONSTRAINT fk_refund_patient FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_refund_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    CONSTRAINT fk_refund_processed_by FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);
