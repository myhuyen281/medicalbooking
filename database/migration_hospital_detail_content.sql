USE medical_booking;

ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS short_description TEXT NULL;
ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS services_info TEXT NULL;
ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS overview TEXT NULL;
