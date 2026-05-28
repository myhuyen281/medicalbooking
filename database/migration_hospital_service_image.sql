USE medical_booking;

ALTER TABLE hospitals
ADD COLUMN service_image_url VARCHAR(500) NULL AFTER map_embed_url;
