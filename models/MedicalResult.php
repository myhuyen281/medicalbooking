<?php
require_once __DIR__ . '/../config/database.php';

class MedicalResult
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
        $this->ensureSchema();
    }

    public function ensureSchema()
    {
        try {
            $this->db->query("ALTER TABLE appointments MODIFY COLUMN status ENUM('pending','confirmed','examining','completed','cancel_pending','cancelled') DEFAULT 'pending'");
            $this->db->execute();
        } catch (Exception $e) {
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS medical_results (
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
            KEY idx_result_doctor (doctor_id)
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->db->execute();
    }

    public function findAppointment($appointmentId, $hospitalId = null, $patientId = null)
    {
        $where = "a.id = :appointment_id";
        if ($hospitalId !== null) {
            $where .= " AND d.hospital_id = :hospital_id";
        }
        if ($patientId !== null) {
            $where .= " AND a.patient_id = :patient_id";
        }

        $this->db->query("SELECT a.*, d.id AS doctor_id, d.hospital_id, h.name AS hospital_name, u_doc.full_name AS doctor_name, u_pat.full_name AS patient_name, s.work_date, s.start_time
            FROM appointments a
            INNER JOIN doctors d ON a.doctor_id = d.id
            INNER JOIN hospitals h ON d.hospital_id = h.id
            LEFT JOIN users u_doc ON d.user_id = u_doc.id
            INNER JOIN users u_pat ON a.patient_id = u_pat.id
            INNER JOIN schedules s ON a.schedule_id = s.id
            WHERE $where
            LIMIT 1");
        $this->db->bind(':appointment_id', $appointmentId);
        if ($hospitalId !== null) {
            $this->db->bind(':hospital_id', $hospitalId);
        }
        if ($patientId !== null) {
            $this->db->bind(':patient_id', $patientId);
        }
        return $this->db->single();
    }

    public function save($data)
    {
        $this->db->query("INSERT INTO medical_results (appointment_id, doctor_id, diagnosis, conclusion, prescription, note, re_exam_date, pdf_file)
            VALUES (:appointment_id, :doctor_id, :diagnosis, :conclusion, :prescription, :note, :re_exam_date, :pdf_file)
            ON DUPLICATE KEY UPDATE diagnosis = VALUES(diagnosis), conclusion = VALUES(conclusion), prescription = VALUES(prescription), note = VALUES(note), re_exam_date = VALUES(re_exam_date), pdf_file = COALESCE(VALUES(pdf_file), pdf_file)");
        $this->db->bind(':appointment_id', $data['appointment_id']);
        $this->db->bind(':doctor_id', $data['doctor_id']);
        $this->db->bind(':diagnosis', $data['diagnosis']);
        $this->db->bind(':conclusion', $data['conclusion']);
        $this->db->bind(':prescription', $data['prescription']);
        $this->db->bind(':note', $data['note']);
        $this->db->bind(':re_exam_date', $data['re_exam_date'] ?: null);
        $this->db->bind(':pdf_file', $data['pdf_file'] ?: null);
        $this->db->execute();

        $this->db->query("UPDATE appointments SET status = 'completed' WHERE id = :appointment_id");
        $this->db->bind(':appointment_id', $data['appointment_id']);
        return $this->db->execute();
    }

    public function getByAppointment($appointmentId, $patientId = null)
    {
        $where = "mr.appointment_id = :appointment_id";
        if ($patientId !== null) {
            $where .= " AND a.patient_id = :patient_id";
        }
        $this->db->query("SELECT mr.*, h.name AS hospital_name, u_doc.full_name AS doctor_name, s.work_date, s.start_time, a.patient_id
            FROM medical_results mr
            INNER JOIN appointments a ON mr.appointment_id = a.id
            INNER JOIN doctors d ON mr.doctor_id = d.id
            INNER JOIN hospitals h ON d.hospital_id = h.id
            LEFT JOIN users u_doc ON d.user_id = u_doc.id
            INNER JOIN schedules s ON a.schedule_id = s.id
            WHERE $where
            LIMIT 1");
        $this->db->bind(':appointment_id', $appointmentId);
        if ($patientId !== null) {
            $this->db->bind(':patient_id', $patientId);
        }
        return $this->db->single();
    }
}
?>
