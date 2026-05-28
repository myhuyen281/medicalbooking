USE medical_booking;

ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS facility_code VARCHAR(50) NULL AFTER id;
ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS facility_type VARCHAR(100) NULL AFTER name;
