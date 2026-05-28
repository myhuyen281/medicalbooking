USE medical_booking;

ALTER TABLE hospitals
ADD COLUMN map_embed_url VARCHAR(1000) NULL AFTER content_image_url;
