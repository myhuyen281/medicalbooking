-- Seed dữ liệu ảo: Bệnh viện & Phòng khám ở Cần Thơ
-- Mật khẩu mọi tài khoản hospital: hospital123
USE medical_booking;

SET @pw := '$2y$10$DlYpuZEHvnBTC5hQ289lmuznIs0RgF1V/JX4hHIPJK3/MX.TilL4a';

-- 1) Bệnh viện Đa khoa Hoàn Mỹ Cửu Long
INSERT INTO hospitals (name, facility_type, address, phone, email, logo_url, working_time, short_description, description, rating)
VALUES (
  'Bệnh viện Đa khoa Hoàn Mỹ Cửu Long', 'public',
  '20 Đường 3 Tháng 2, Hưng Lợi, Ninh Kiều, Cần Thơ',
  '02923917045', 'contact@hoanmycuulong.vn',
  'https://img.icons8.com/color/96/hospital.png',
  'Thứ 2 - Chủ nhật: 06:30 - 20:00',
  'Bệnh viện đa khoa tư nhân hàng đầu khu vực Đồng bằng sông Cửu Long.',
  'Bệnh viện Đa khoa Hoàn Mỹ Cửu Long cung cấp dịch vụ khám chữa bệnh đa chuyên khoa với hệ thống trang thiết bị hiện đại và đội ngũ bác sĩ giàu kinh nghiệm.',
  4.7
);
SET @h1 := LAST_INSERT_ID();
INSERT INTO users (full_name, email, phone, password, role, hospital_id, hospital_approval_status)
VALUES ('Bệnh viện Đa khoa Hoàn Mỹ Cửu Long', 'hospital.hoanmy@cantho.vn', '02923917045', @pw, 'hospital', @h1, 'approved');

-- 2) Bệnh viện Quốc tế Phương Châu
INSERT INTO hospitals (name, facility_type, address, phone, email, logo_url, working_time, short_description, description, rating)
VALUES (
  'Bệnh viện Quốc tế Phương Châu', 'private',
  '300 Nguyễn Văn Cừ nối dài, An Bình, Ninh Kiều, Cần Thơ',
  '02923760888', 'cskh@phuongchau.com',
  'https://img.icons8.com/color/96/maternity.png',
  'Thứ 2 - Chủ nhật: 07:00 - 19:00',
  'Bệnh viện chuyên sản - nhi tiêu chuẩn quốc tế tại Cần Thơ.',
  'Bệnh viện Quốc tế Phương Châu nổi tiếng với dịch vụ Sản - Phụ khoa, Nhi khoa và chăm sóc sức khỏe sinh sản chất lượng cao.',
  4.8
);
SET @h2 := LAST_INSERT_ID();
INSERT INTO users (full_name, email, phone, password, role, hospital_id, hospital_approval_status)
VALUES ('Bệnh viện Quốc tế Phương Châu', 'hospital.phuongchau@cantho.vn', '02923760888', @pw, 'hospital', @h2, 'approved');

-- 3) Phòng khám Đa khoa Quốc tế Hồng Đức
INSERT INTO hospitals (name, facility_type, address, phone, email, logo_url, working_time, short_description, description, rating)
VALUES (
  'Phòng khám Đa khoa Quốc tế Hồng Đức', 'clinic',
  '86 Đường 30 Tháng 4, Xuân Khánh, Ninh Kiều, Cần Thơ',
  '02923730777', 'lienhe@hongduc-cantho.vn',
  'https://img.icons8.com/color/96/clinic.png',
  'Thứ 2 - Thứ 7: 07:00 - 17:00',
  'Phòng khám đa khoa hiện đại, khám nhanh, ít chờ đợi.',
  'Phòng khám Đa khoa Quốc tế Hồng Đức cung cấp dịch vụ khám tổng quát, nội khoa, da liễu và xét nghiệm với quy trình nhanh gọn.',
  4.5
);
SET @h3 := LAST_INSERT_ID();
INSERT INTO users (full_name, email, phone, password, role, hospital_id, hospital_approval_status)
VALUES ('Phòng khám Đa khoa Quốc tế Hồng Đức', 'hospital.hongduc@cantho.vn', '02923730777', @pw, 'hospital', @h3, 'approved');

-- 4) Phòng khám Đa khoa Tây Đô
INSERT INTO hospitals (name, facility_type, address, phone, email, logo_url, working_time, short_description, description, rating)
VALUES (
  'Phòng khám Đa khoa Tây Đô', 'clinic',
  '99 Mậu Thân, An Hòa, Ninh Kiều, Cần Thơ',
  '02923828383', 'info@taydo-clinic.vn',
  'https://img.icons8.com/color/96/medical-doctor.png',
  'Thứ 2 - Chủ nhật: 06:30 - 18:00',
  'Phòng khám đa khoa phục vụ người dân khu vực trung tâm Cần Thơ.',
  'Phòng khám Đa khoa Tây Đô chuyên khám nội tổng quát, tai mũi họng, răng hàm mặt và tư vấn dinh dưỡng.',
  4.4
);
SET @h4 := LAST_INSERT_ID();
INSERT INTO users (full_name, email, phone, password, role, hospital_id, hospital_approval_status)
VALUES ('Phòng khám Đa khoa Tây Đô', 'hospital.taydo@cantho.vn', '02923828383', @pw, 'hospital', @h4, 'approved');

-- 5) Trung tâm Xét nghiệm Y khoa Diag Cần Thơ
INSERT INTO hospitals (name, facility_type, address, phone, email, logo_url, working_time, short_description, description, rating)
VALUES (
  'Trung tâm Xét nghiệm Y khoa Diag Cần Thơ', 'lab',
  '152 Trần Hưng Đạo, An Nghiệp, Ninh Kiều, Cần Thơ',
  '02871023456', 'cantho@diag.vn',
  'https://img.icons8.com/color/96/test-tube.png',
  'Thứ 2 - Chủ nhật: 05:30 - 17:00',
  'Trung tâm xét nghiệm y khoa lấy mẫu tận nơi, trả kết quả nhanh.',
  'Trung tâm Xét nghiệm Y khoa Diag Cần Thơ cung cấp gói xét nghiệm máu, tầm soát ung thư, sinh hóa với độ chính xác cao.',
  4.6
);
SET @h5 := LAST_INSERT_ID();
INSERT INTO users (full_name, email, phone, password, role, hospital_id, hospital_approval_status)
VALUES ('Trung tâm Xét nghiệm Y khoa Diag Cần Thơ', 'hospital.diag@cantho.vn', '02871023456', @pw, 'hospital', @h5, 'approved');

-- 6) Phòng khám Nhi khoa Bình Minh
INSERT INTO hospitals (name, facility_type, address, phone, email, logo_url, working_time, short_description, description, rating)
VALUES (
  'Phòng khám Nhi khoa Bình Minh', 'clinic',
  '45 Trần Văn Hoài, Xuân Khánh, Ninh Kiều, Cần Thơ',
  '02923899555', 'binhminh.nhikhoa@cantho.vn',
  'https://img.icons8.com/color/96/baby.png',
  'Thứ 2 - Chủ nhật: 07:30 - 20:30',
  'Phòng khám nhi khoa thân thiện, chuyên chăm sóc sức khỏe trẻ em.',
  'Phòng khám Nhi khoa Bình Minh chuyên khám và tư vấn các bệnh lý hô hấp, tiêu hóa, dinh dưỡng và tiêm chủng cho trẻ.',
  4.7
);
SET @h6 := LAST_INSERT_ID();
INSERT INTO users (full_name, email, phone, password, role, hospital_id, hospital_approval_status)
VALUES ('Phòng khám Nhi khoa Bình Minh', 'hospital.binhminh@cantho.vn', '02923899555', @pw, 'hospital', @h6, 'approved');

-- Gắn chuyên khoa sẵn có cho các cơ sở mới (nếu chưa có)
INSERT IGNORE INTO hospital_specialties (hospital_id, specialty_id)
SELECT h.id, s.id
FROM hospitals h
CROSS JOIN specialties s
WHERE h.id IN (@h1, @h2, @h3, @h4, @h5, @h6)
  AND s.id IN (1, 2, 3, 4);
