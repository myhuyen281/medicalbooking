USE medical_booking;

ALTER TABLE hospitals
ADD COLUMN content_image_url VARCHAR(500) NULL AFTER poster_url;
