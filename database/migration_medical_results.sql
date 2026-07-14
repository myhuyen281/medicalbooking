ALTER TABLE appointments
MODIFY COLUMN status ENUM('pending','confirmed','examining','completed','cancel_pending','cancelled') DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS medical_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    doctor_id INT NOT NULL,
    diagnosis TEXT NOT NULL,
    conclusion TEXT NOT NULL,
    prescription TEXT NOT NULL,
    note TEXT NULL,
    re_exam_date DATE NULL,
    pdf_file VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_result_appointment (appointment_id),
    KEY idx_result_doctor (doctor_id),
    CONSTRAINT fk_medical_result_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    CONSTRAINT fk_medical_result_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);
