USE medical_booking;

ALTER TABLE hospital_services
ADD COLUMN detail_text TEXT NULL AFTER schedule_text;
