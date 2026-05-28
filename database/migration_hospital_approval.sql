USE medical_booking;

ALTER TABLE users ADD COLUMN IF NOT EXISTS hospital_approval_status ENUM('pending', 'approved', 'rejected') DEFAULT NULL AFTER hospital_id;

UPDATE users
SET hospital_approval_status = 'approved'
WHERE role = 'hospital' AND hospital_approval_status IS NULL;
