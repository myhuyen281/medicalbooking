USE medical_booking;

ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS facility_type VARCHAR(30) NOT NULL DEFAULT 'public' AFTER facility_code;

UPDATE hospitals
SET facility_type = CASE
    WHEN LOWER(CONCAT_WS(' ', name, description, services_info)) LIKE '%phòng khám%' THEN 'clinic'
    WHEN LOWER(CONCAT_WS(' ', name, description, services_info)) LIKE '%phòng mạch%' THEN 'office'
    WHEN LOWER(CONCAT_WS(' ', name, description, services_info)) LIKE '%xét nghiệm%' THEN 'lab'
    WHEN LOWER(CONCAT_WS(' ', name, description, services_info)) LIKE '%tại nhà%' THEN 'home'
    WHEN LOWER(CONCAT_WS(' ', name, description, services_info)) LIKE '%tiêm chủng%' THEN 'vaccination'
    WHEN LOWER(CONCAT_WS(' ', name, description, services_info)) LIKE '%tư%' THEN 'private'
    ELSE 'public'
END
WHERE facility_type IS NULL OR facility_type = '';
