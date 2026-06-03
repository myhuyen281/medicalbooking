-- Seed hình thức đặt khám + dịch vụ (gói khám) cho 2 cơ sở seed (id 9, 10)
-- Chạy lại được nhiều lần (xóa dữ liệu cũ trước khi thêm)
USE medical_booking;

DELETE FROM hospital_services WHERE hospital_id IN (9, 10);
DELETE FROM hospital_booking_forms WHERE hospital_id IN (9, 10);

-- ============================================================
-- Bệnh viện Đa khoa Hoàn Mỹ Cửu Long (id 9)
-- ============================================================

-- Form 1: Đặt khám theo chuyên khoa
INSERT INTO hospital_booking_forms (hospital_id, name, icon, target, sort_order)
VALUES (9, 'Đặt khám theo chuyên khoa', NULL, 'specialty', 0);
SET @hm_f1 := LAST_INSERT_ID();

INSERT INTO hospital_services (hospital_id, booking_form_id, name, service_icon, service_target, specialty_name, schedule_text, detail_text, requires_insurance, price) VALUES
(9, @hm_f1, 'Khám Nội tổng quát', 'bi-clipboard2-pulse', 'specialty',
 '["Khoa Nội tổng quát","Khoa Tim mạch","Khoa Tiêu hóa - Gan mật"]', '1,2,3,4,5,6',
 'Khám và tư vấn các bệnh lý nội khoa, tim mạch, tiêu hóa. Bao gồm khám lâm sàng và chỉ định cận lâm sàng nếu cần.', 0, 200000),
(9, @hm_f1, 'Khám Sản - Phụ khoa', 'bi-gender-female', 'specialty',
 '["Khoa Sản","Khoa Phụ khoa"]', '1,2,3,4,5,6',
 'Khám thai, theo dõi thai kỳ, khám và điều trị các bệnh lý phụ khoa.', 0, 250000),
(9, @hm_f1, 'Khám Nhi khoa', 'bi-emoji-smile', 'specialty',
 '["Khoa Nhi"]', '1,2,3,4,5,6,0',
 'Khám và điều trị các bệnh lý hô hấp, tiêu hóa, dinh dưỡng cho trẻ em.', 0, 180000);

-- Form 2: Đặt khám chuyên gia (chọn bác sĩ)
INSERT INTO hospital_booking_forms (hospital_id, name, icon, target, sort_order)
VALUES (9, 'Đặt khám chuyên gia', NULL, 'doctor', 1);
SET @hm_f2 := LAST_INSERT_ID();

INSERT INTO hospital_services (hospital_id, booking_form_id, name, service_icon, service_target, specialty_name, schedule_text, detail_text, requires_insurance, price) VALUES
(9, @hm_f2, 'Khám theo yêu cầu chuyên gia (chọn Bác sĩ)', 'bi-person-badge', 'doctor',
 '[]', '1,2,3,4,5', 'Khám trực tiếp với bác sĩ chuyên gia, chuyên khoa sâu theo lựa chọn của bệnh nhân.', 0, 350000);

-- Form 3: Đặt khám ngoài giờ
INSERT INTO hospital_booking_forms (hospital_id, name, icon, target, sort_order)
VALUES (9, 'Đặt khám ngoài giờ', NULL, 'specialty', 2);
SET @hm_f3 := LAST_INSERT_ID();

INSERT INTO hospital_services (hospital_id, booking_form_id, name, service_icon, service_target, specialty_name, schedule_text, detail_text, requires_insurance, price) VALUES
(9, @hm_f3, 'Khám Nội tổng quát ngoài giờ', 'bi-clock-history', 'specialty',
 '["Khoa Nội tổng quát","Khoa Tim mạch"]', '1,2,3,4,5,6,0',
 'Khám nội tổng quát ngoài giờ hành chính, buổi tối và cuối tuần.', 0, 300000),
(9, @hm_f3, 'Khám Nhi ngoài giờ', 'bi-clock-history', 'specialty',
 '["Khoa Nhi"]', '6,0', 'Khám nhi ngoài giờ vào cuối tuần.', 0, 280000);

-- Gói khám tổng quát (không gắn form - hiển thị mặc định)
INSERT INTO hospital_services (hospital_id, booking_form_id, name, service_icon, service_target, specialty_name, schedule_text, detail_text, requires_insurance, price) VALUES
(9, NULL, 'Gói khám sức khỏe tổng quát cơ bản', 'bi-heart-pulse', 'specialty',
 '["Khám sức khỏe tổng quát"]', '1,2,3,4,5,6',
 'Gói khám sức khỏe tổng quát: khám lâm sàng, xét nghiệm máu, nước tiểu, chụp X-quang ngực, siêu âm bụng.', 0, 1200000),
(9, NULL, 'Gói khám sức khỏe tổng quát nâng cao', 'bi-heart-pulse', 'specialty',
 '["Khám sức khỏe tổng quát"]', '1,2,3,4,5,6',
 'Gói khám nâng cao: bao gồm gói cơ bản và tầm soát ung thư, điện tim, đo loãng xương.', 0, 2500000);

-- ============================================================
-- Bệnh viện Quốc tế Phương Châu (id 10) - Sản Nhi
-- ============================================================

-- Form 1: Đặt khám theo chuyên khoa
INSERT INTO hospital_booking_forms (hospital_id, name, icon, target, sort_order)
VALUES (10, 'Đặt khám theo chuyên khoa', NULL, 'specialty', 0);
SET @pc_f1 := LAST_INSERT_ID();

INSERT INTO hospital_services (hospital_id, booking_form_id, name, service_icon, service_target, specialty_name, schedule_text, detail_text, requires_insurance, price) VALUES
(10, @pc_f1, 'Khám Sản khoa', 'bi-gender-female', 'specialty',
 '["Khoa Sản","Theo dõi thai kỳ"]', '1,2,3,4,5,6',
 'Khám thai, quản lý và theo dõi thai kỳ toàn diện theo tiêu chuẩn quốc tế.', 0, 300000),
(10, @pc_f1, 'Khám Phụ khoa', 'bi-flower1', 'specialty',
 '["Khoa Phụ khoa"]', '1,2,3,4,5,6',
 'Khám và điều trị các bệnh lý phụ khoa, tầm soát ung thư cổ tử cung.', 0, 280000),
(10, @pc_f1, 'Khám Nhi - Sơ sinh', 'bi-emoji-smile', 'specialty',
 '["Khoa Nhi","Khoa Sơ sinh"]', '1,2,3,4,5,6,0',
 'Khám và chăm sóc sức khỏe trẻ từ sơ sinh đến 15 tuổi.', 0, 250000),
(10, @pc_f1, 'Khám Hiếm muộn - IVF', 'bi-droplet-half', 'specialty',
 '["Khoa Hiếm muộn - IVF","Nam khoa"]', '2,4,6',
 'Tư vấn và điều trị hiếm muộn, hỗ trợ sinh sản IVF cho cả nam và nữ.', 0, 500000);

-- Form 2: Đặt khám ngoài giờ
INSERT INTO hospital_booking_forms (hospital_id, name, icon, target, sort_order)
VALUES (10, 'Đặt khám ngoài giờ', NULL, 'specialty', 1);
SET @pc_f2 := LAST_INSERT_ID();

INSERT INTO hospital_services (hospital_id, booking_form_id, name, service_icon, service_target, specialty_name, schedule_text, detail_text, requires_insurance, price) VALUES
(10, @pc_f2, 'Khám Sản ngoài giờ', 'bi-clock-history', 'specialty',
 '["Khoa Sản"]', '1,2,3,4,5,6,0', 'Khám thai và sản khoa ngoài giờ hành chính.', 0, 400000),
(10, @pc_f2, 'Khám Nhi ngoài giờ', 'bi-clock-history', 'specialty',
 '["Khoa Nhi"]', '6,0', 'Khám nhi ngoài giờ vào buổi tối và cuối tuần.', 0, 350000);

-- Gói khám (không gắn form)
INSERT INTO hospital_services (hospital_id, booking_form_id, name, service_icon, service_target, specialty_name, schedule_text, detail_text, requires_insurance, price) VALUES
(10, NULL, 'Gói khám thai theo quý', 'bi-clipboard2-heart', 'specialty',
 '["Theo dõi thai kỳ"]', '1,2,3,4,5,6',
 'Gói theo dõi thai kỳ trọn gói theo từng quý: siêu âm, xét nghiệm, khám định kỳ.', 0, 3500000),
(10, NULL, 'Gói khám sức khỏe tiền hôn nhân', 'bi-people', 'specialty',
 '["Khám sức khỏe tổng quát"]', '1,2,3,4,5,6',
 'Gói khám sức khỏe tiền hôn nhân dành cho cặp đôi: xét nghiệm, tầm soát di truyền, tư vấn sức khỏe sinh sản.', 0, 2800000);
