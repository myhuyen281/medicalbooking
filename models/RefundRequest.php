<?php
require_once __DIR__ . '/../config/database.php';

class RefundRequest
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
        $this->ensureTable();
    }

    public function ensureTable()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS refund_requests (
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
            KEY idx_refund_hospital (hospital_id)
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->db->execute();

        foreach ([
            "payment_method VARCHAR(50) DEFAULT 'vnpay'",
            "bank_account_name VARCHAR(150) NULL",
            "bank_account_number VARCHAR(50) NULL",
            "bank_name VARCHAR(120) NULL"
        ] as $columnSql) {
            try {
                $this->db->query("ALTER TABLE refund_requests ADD COLUMN " . $columnSql);
                $this->db->execute();
            } catch (Exception $e) {
            }
        }
    }

    public function calculateRate($workDate, $startTime, $cancelledBy = 'patient')
    {
        if ($cancelledBy === 'hospital') {
            return 100;
        }

        $appointmentTime = strtotime($workDate . ' ' . $startTime);
        $hours = ($appointmentTime - time()) / 3600;

        if ($hours >= 24) {
            return 100;
        }

        if ($hours >= 12) {
            return 50;
        }

        return 0;
    }

    public function findAppointmentForRefund($appointmentId, $patientId = null, $hospitalId = null)
    {
        $where = "a.id = :id";
        if ($patientId !== null) {
            $where .= " AND a.patient_id = :patient_id";
        }
        if ($hospitalId !== null) {
            $where .= " AND d.hospital_id = :hospital_id";
        }

        $this->db->query("SELECT a.id, a.patient_id, a.status, a.schedule_id, s.work_date, s.start_time, d.consultation_fee, d.hospital_id, u.full_name AS patient_name, u.phone AS patient_phone
            FROM appointments a
            INNER JOIN schedules s ON a.schedule_id = s.id
            INNER JOIN doctors d ON a.doctor_id = d.id
            INNER JOIN users u ON a.patient_id = u.id
            WHERE $where
            LIMIT 1");
        $this->db->bind(':id', $appointmentId);
        if ($patientId !== null) {
            $this->db->bind(':patient_id', $patientId);
        }
        if ($hospitalId !== null) {
            $this->db->bind(':hospital_id', $hospitalId);
        }
        return $this->db->single();
    }

    public function createForAppointment($appointment, $cancelledBy, $reason)
    {
        $paidAmount = (float)($appointment['consultation_fee'] ?? 0);
        $rate = $this->calculateRate($appointment['work_date'], $appointment['start_time'], $cancelledBy);
        if ($paidAmount > 0 && $rate <= 0) {
            $rate = 100;
        }
        $refundAmount = round($paidAmount * $rate / 100, 2);

        $accountName = $appointment['patient_name'] ?? 'Khách hàng';
        $accountNumber = '9704' . str_pad((string)(int)$appointment['patient_id'], 8, '0', STR_PAD_LEFT);
        $bankName = 'Ngân hàng NCB';

        $this->db->query("INSERT INTO refund_requests (appointment_id, patient_id, hospital_id, paid_amount, refund_rate, refund_amount, payment_method, bank_account_name, bank_account_number, bank_name, reason, cancelled_by, status)
            VALUES (:appointment_id, :patient_id, :hospital_id, :paid_amount, :refund_rate, :refund_amount, :payment_method, :bank_account_name, :bank_account_number, :bank_name, :reason, :cancelled_by, 'pending')
            ON DUPLICATE KEY UPDATE reason = VALUES(reason), cancelled_by = VALUES(cancelled_by), paid_amount = VALUES(paid_amount), refund_rate = VALUES(refund_rate), refund_amount = VALUES(refund_amount), payment_method = VALUES(payment_method), bank_account_name = VALUES(bank_account_name), bank_account_number = VALUES(bank_account_number), bank_name = VALUES(bank_name)");
        $this->db->bind(':appointment_id', (int)$appointment['id']);
        $this->db->bind(':patient_id', (int)$appointment['patient_id']);
        $this->db->bind(':hospital_id', (int)$appointment['hospital_id']);
        $this->db->bind(':paid_amount', $paidAmount);
        $this->db->bind(':refund_rate', $rate);
        $this->db->bind(':refund_amount', $refundAmount);
        $this->db->bind(':payment_method', 'VNPAY');
        $this->db->bind(':bank_account_name', $accountName);
        $this->db->bind(':bank_account_number', $accountNumber);
        $this->db->bind(':bank_name', $bankName);
        $this->db->bind(':reason', $reason);
        $this->db->bind(':cancelled_by', $cancelledBy);
        $this->db->execute();

        return ['created' => true, 'rate' => $rate, 'amount' => $refundAmount];
    }

    public function all($status = '', $hospitalId = null)
    {
        $where = [];
        if ($status !== '') {
            $where[] = "rr.status = :status";
        }
        if ($hospitalId !== null) {
            $where[] = "rr.hospital_id = :hospital_id";
        }
        $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $this->db->query("SELECT rr.*, u.full_name AS patient_name, h.name AS hospital_name
            FROM refund_requests rr
            INNER JOIN users u ON rr.patient_id = u.id
            INNER JOIN hospitals h ON rr.hospital_id = h.id
            $whereSql
            ORDER BY rr.created_at DESC");
        if ($status !== '') {
            $this->db->bind(':status', $status);
        }
        if ($hospitalId !== null) {
            $this->db->bind(':hospital_id', $hospitalId);
        }
        return $this->db->resultSet();
    }

    public function updateStatus($id, $status, $adminId)
    {
        $this->db->query("UPDATE refund_requests SET status = :status, processed_by = :processed_by, processed_at = NOW() WHERE id = :id");
        $this->db->bind(':status', $status);
        $this->db->bind(':processed_by', $adminId);
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }
}
?>
