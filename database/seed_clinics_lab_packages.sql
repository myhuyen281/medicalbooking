-- Seed phòng khám mới + gói xét nghiệm ở Cần Thơ
-- Phòng khám hiển thị ở trang chủ (cần tài khoản hospital approved)
-- Gói xét nghiệm hiển thị ở lab_booking.php
-- Chạy lại được nhiều lần (xóa dữ liệu seed cũ theo email trước khi thêm)
USE medical_booking;

SET @pw := '$2y$10$DlYpuZEHvnBTC5hQ289lmuznIs0RgF1V/JX4hHIPJK3/MX.TilL4a';

-- Dọn dữ liệu seed cũ (nếu chạy lại) dựa trên email tài khoản
DELETE lps FROM lab_package_services lps
  INNER JOIN lab_packages lp ON lp.id = lps.package_id
  INNER JOIN hospitals h ON h.id = lp.hospital_id
  INNER JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
  WHERE u.email IN ('clinic.medlatec@cantho.vn','clinic.diag@cantho.vn','clinic.medic@cantho.vn');
DELETE lp FROM lab_packages lp
  INNER JOIN hospitals h ON h.id = lp.hospital_id
  INNER JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
  WHERE u.email IN ('clinic.medlatec@cantho.vn','clinic.diag@cantho.vn','clinic.medic@cantho.vn');
DELETE u FROM users u WHERE u.email IN ('clinic.medlatec@cantho.vn','clinic.diag@cantho.vn','clinic.medic@cantho.vn');
DELETE h FROM hospitals h WHERE h.email IN ('clinic.medlatec@cantho.vn','clinic.diag@cantho.vn','clinic.medic@cantho.vn');

-- ============================================================
-- Phòng khám 1: MEDLATEC Cần Thơ
-- ============================================================
INSERT INTO hospitals (name, facility_type, address, phone, email, logo_url, working_time, short_description, description, rating)
VALUES (
  'Phòng khám Đa khoa MEDLATEC Cần Thơ', 'clinic',
  '50 Mậu Thân, P. An Nghiệp, Q. Ninh Kiều, TP. Cần Thơ',
  '19001550', 'clinic.medlatec@cantho.vn',
  'uploads/hospitals/medlatec_logo.png',
  'Thứ 2 - Chủ nhật: 06:00 - 18:00',
  'Hệ thống phòng khám và xét nghiệm uy tín, lấy mẫu tận nơi.',
  'Phòng khám Đa khoa MEDLATEC Cần Thơ chuyên cung cấp dịch vụ xét nghiệm, chẩn đoán hình ảnh và khám sức khỏe tổng quát với hệ thống labo đạt chuẩn ISO 15189.',
  4.7
);
SET @c1 := LAST_INSERT_ID();
INSERT INTO users (full_name, email, phone, password, role, hospital_id, hospital_approval_status)
VALUES ('Phòng khám Đa khoa MEDLATEC Cần Thơ', 'clinic.medlatec@cantho.vn', '19001550', @pw, 'hospital', @c1, 'approved');

INSERT INTO lab_packages (hospital_id, name, price, sample_type, turnaround_time, description, is_active)
VALUES (@c1, 'Gói xét nghiệm tổng quát cơ bản', 450000, 'Máu, Nước tiểu', 'Trả kết quả sau 4 - 6 giờ', 'Tầm soát sức khỏe tổng quát cơ bản: công thức máu, đường huyết, chức năng gan thận, mỡ máu.', 1);
SET @c1p1 := LAST_INSERT_ID();
INSERT INTO lab_package_services (package_id, name, service_icon, schedule_text, price, description, sort_order) VALUES
(@c1p1, 'Tổng phân tích tế bào máu', 'bi-droplet-half', 'T2-CN', 80000, 'Đánh giá hồng cầu, bạch cầu, tiểu cầu.', 0),
(@c1p1, 'Đường huyết lúc đói (Glucose)', 'bi-clipboard2-pulse', 'T2-CN', 60000, 'Tầm soát tiểu đường.', 1),
(@c1p1, 'Chức năng gan (AST, ALT)', 'bi-activity', 'T2-CN', 120000, 'Đánh giá men gan.', 2),
(@c1p1, 'Chức năng thận (Ure, Creatinin)', 'bi-activity', 'T2-CN', 110000, 'Đánh giá chức năng thận.', 3),
(@c1p1, 'Mỡ máu (Cholesterol, Triglyceride)', 'bi-heart-pulse', 'T2-CN', 130000, 'Tầm soát rối loạn lipid máu.', 4);

INSERT INTO lab_packages (hospital_id, name, price, sample_type, turnaround_time, description, is_active)
VALUES (@c1, 'Gói tầm soát tiểu đường', 380000, 'Máu', 'Trả kết quả sau 3 giờ', 'Tầm soát và theo dõi bệnh tiểu đường.', 1);
SET @c1p2 := LAST_INSERT_ID();
INSERT INTO lab_package_services (package_id, name, service_icon, schedule_text, price, description, sort_order) VALUES
(@c1p2, 'Đường huyết lúc đói (Glucose)', 'bi-clipboard2-pulse', 'T2-CN', 60000, 'Tầm soát tiểu đường.', 0),
(@c1p2, 'HbA1c', 'bi-graph-up', 'T2-CN', 180000, 'Đánh giá đường huyết trung bình 3 tháng.', 1),
(@c1p2, 'Insulin máu', 'bi-droplet', 'T2-CN', 140000, 'Đánh giá tình trạng kháng insulin.', 2);

-- ============================================================
-- Phòng khám 2: DIAG Cần Thơ
-- ============================================================
INSERT INTO hospitals (name, facility_type, address, phone, email, logo_url, working_time, short_description, description, rating)
VALUES (
  'Trung tâm Xét nghiệm DIAG Cần Thơ', 'lab',
  '152 Trần Hưng Đạo, P. An Nghiệp, Q. Ninh Kiều, TP. Cần Thơ',
  '19001717', 'clinic.diag@cantho.vn',
  'uploads/hospitals/diag_logo.svg',
  'Thứ 2 - Chủ nhật: 05:30 - 17:00',
  'Trung tâm xét nghiệm y khoa, lấy mẫu tận nơi, trả kết quả online.',
  'Trung tâm Xét nghiệm DIAG Cần Thơ cung cấp các gói xét nghiệm máu, tầm soát ung thư, nội tiết tố với độ chính xác cao và dịch vụ lấy mẫu tại nhà.',
  4.6
);
SET @c2 := LAST_INSERT_ID();
INSERT INTO users (full_name, email, phone, password, role, hospital_id, hospital_approval_status)
VALUES ('Trung tâm Xét nghiệm DIAG Cần Thơ', 'clinic.diag@cantho.vn', '19001717', @pw, 'hospital', @c2, 'approved');

INSERT INTO lab_packages (hospital_id, name, price, sample_type, turnaround_time, description, is_active)
VALUES (@c2, 'Gói tầm soát ung thư cơ bản (Nam)', 1500000, 'Máu', 'Trả kết quả sau 24 giờ', 'Tầm soát các dấu ấn ung thư phổ biến ở nam giới.', 1);
SET @c2p1 := LAST_INSERT_ID();
INSERT INTO lab_package_services (package_id, name, service_icon, schedule_text, price, description, sort_order) VALUES
(@c2p1, 'AFP (ung thư gan)', 'bi-shield-plus', 'T2-CN', 250000, 'Dấu ấn ung thư gan.', 0),
(@c2p1, 'CEA (ung thư đường tiêu hóa)', 'bi-shield-plus', 'T2-CN', 280000, 'Dấu ấn ung thư đại trực tràng.', 1),
(@c2p1, 'PSA (ung thư tuyến tiền liệt)', 'bi-shield-plus', 'T2-CN', 320000, 'Dấu ấn ung thư tuyến tiền liệt.', 2);

INSERT INTO lab_packages (hospital_id, name, price, sample_type, turnaround_time, description, is_active)
VALUES (@c2, 'Gói tầm soát ung thư cơ bản (Nữ)', 1650000, 'Máu', 'Trả kết quả sau 24 giờ', 'Tầm soát các dấu ấn ung thư phổ biến ở nữ giới.', 1);
SET @c2p2 := LAST_INSERT_ID();
INSERT INTO lab_package_services (package_id, name, service_icon, schedule_text, price, description, sort_order) VALUES
(@c2p2, 'AFP (ung thư gan)', 'bi-shield-plus', 'T2-CN', 250000, 'Dấu ấn ung thư gan.', 0),
(@c2p2, 'CEA (ung thư đường tiêu hóa)', 'bi-shield-plus', 'T2-CN', 280000, 'Dấu ấn ung thư đại trực tràng.', 1),
(@c2p2, 'CA 125 (ung thư buồng trứng)', 'bi-shield-plus', 'T2-CN', 300000, 'Dấu ấn ung thư buồng trứng.', 2),
(@c2p2, 'CA 15-3 (ung thư vú)', 'bi-shield-plus', 'T2-CN', 320000, 'Dấu ấn ung thư vú.', 3);

INSERT INTO lab_packages (hospital_id, name, price, sample_type, turnaround_time, description, is_active)
VALUES (@c2, 'Gói xét nghiệm nội tiết tố', 850000, 'Máu', 'Trả kết quả sau 6 giờ', 'Đánh giá nội tiết tố tuyến giáp và sinh dục.', 1);
SET @c2p3 := LAST_INSERT_ID();
INSERT INTO lab_package_services (package_id, name, service_icon, schedule_text, price, description, sort_order) VALUES
(@c2p3, 'TSH, FT3, FT4 (tuyến giáp)', 'bi-activity', 'T2-CN', 350000, 'Đánh giá chức năng tuyến giáp.', 0),
(@c2p3, 'Testosterone', 'bi-gender-male', 'T2-CN', 250000, 'Nội tiết tố nam.', 1),
(@c2p3, 'Estradiol (E2)', 'bi-gender-female', 'T2-CN', 250000, 'Nội tiết tố nữ.', 2);

-- ============================================================
-- Phòng khám 3: MEDIC Hòa Hảo Cần Thơ
-- ============================================================
INSERT INTO hospitals (name, facility_type, address, phone, email, logo_url, working_time, short_description, description, rating)
VALUES (
  'Phòng khám Đa khoa MEDIC Cần Thơ', 'clinic',
  '107 Đường 3 Tháng 2, P. Hưng Lợi, Q. Ninh Kiều, TP. Cần Thơ',
  '02923896969', 'clinic.medic@cantho.vn',
  'uploads/hospitals/medic_logo.jpg',
  'Thứ 2 - Thứ 7: 06:30 - 16:30',
  'Phòng khám đa khoa với dịch vụ xét nghiệm và chẩn đoán hình ảnh.',
  'Phòng khám Đa khoa MEDIC Cần Thơ cung cấp các gói khám sức khỏe và xét nghiệm tầm soát chuyên sâu, phục vụ nhu cầu khám định kỳ của người dân.',
  4.5
);
SET @c3 := LAST_INSERT_ID();
INSERT INTO users (full_name, email, phone, password, role, hospital_id, hospital_approval_status)
VALUES ('Phòng khám Đa khoa MEDIC Cần Thơ', 'clinic.medic@cantho.vn', '02923896969', @pw, 'hospital', @c3, 'approved');

INSERT INTO lab_packages (hospital_id, name, price, sample_type, turnaround_time, description, is_active)
VALUES (@c3, 'Gói xét nghiệm tiền hôn nhân', 1200000, 'Máu', 'Trả kết quả sau 24 giờ', 'Tầm soát sức khỏe sinh sản và bệnh di truyền cho cặp đôi.', 1);
SET @c3p1 := LAST_INSERT_ID();
INSERT INTO lab_package_services (package_id, name, service_icon, schedule_text, price, description, sort_order) VALUES
(@c3p1, 'Tổng phân tích tế bào máu', 'bi-droplet-half', 'T2-T7', 80000, 'Đánh giá tế bào máu.', 0),
(@c3p1, 'Viêm gan B (HBsAg)', 'bi-shield-plus', 'T2-T7', 120000, 'Tầm soát viêm gan B.', 1),
(@c3p1, 'HIV', 'bi-shield-plus', 'T2-T7', 130000, 'Tầm soát HIV.', 2),
(@c3p1, 'Giang mai (Syphilis)', 'bi-shield-plus', 'T2-T7', 110000, 'Tầm soát giang mai.', 3),
(@c3p1, 'Định nhóm máu ABO, Rh', 'bi-droplet', 'T2-T7', 90000, 'Xác định nhóm máu.', 4);

INSERT INTO lab_packages (hospital_id, name, price, sample_type, turnaround_time, description, is_active)
VALUES (@c3, 'Gói xét nghiệm chức năng gan thận', 520000, 'Máu', 'Trả kết quả sau 4 giờ', 'Đánh giá chuyên sâu chức năng gan và thận.', 1);
SET @c3p2 := LAST_INSERT_ID();
INSERT INTO lab_package_services (package_id, name, service_icon, schedule_text, price, description, sort_order) VALUES
(@c3p2, 'Chức năng gan (AST, ALT, GGT)', 'bi-activity', 'T2-T7', 180000, 'Đánh giá men gan.', 0),
(@c3p2, 'Bilirubin toàn phần, trực tiếp', 'bi-activity', 'T2-T7', 120000, 'Đánh giá tình trạng vàng da.', 1),
(@c3p2, 'Chức năng thận (Ure, Creatinin, eGFR)', 'bi-activity', 'T2-T7', 160000, 'Đánh giá chức năng thận.', 2),
(@c3p2, 'Acid Uric', 'bi-clipboard2-pulse', 'T2-T7', 80000, 'Tầm soát gout.', 3);
