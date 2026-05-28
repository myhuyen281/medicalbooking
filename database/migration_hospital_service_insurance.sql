USE medical_booking;

ALTER TABLE hospital_services
ADD COLUMN requires_insurance TINYINT(1) NOT NULL DEFAULT 0 AFTER detail_text;
