-- Seed gói "Chụp phim & Nội soi" cho các cơ sở có chẩn đoán hình ảnh
-- Chạy lại được (xóa form chụp phim cũ của các cơ sở này trước)
USE medical_booking;

-- Xóa form chụp phim cũ (nếu chạy lại)
DELETE s FROM hospital_services s
  INNER JOIN hospital_booking_forms f ON f.id = s.booking_form_id
  WHERE LOWER(f.name) LIKE '%chụp phim%';
DELETE FROM hospital_booking_forms WHERE LOWER(name) LIKE '%chụp phim%';

-- Danh sách cơ sở có dịch vụ chụp phim/nội soi: Nhi Đồng(7), Hoàn Mỹ(9), Phương Châu(10), MEDLATEC(15), MEDIC(17)
INSERT INTO hospital_booking_forms (hospital_id, name, icon, target, sort_order)
SELECT h.id, 'Đặt lịch chụp phim & nội soi', 'bi-camera', 'specialty', 50
FROM hospitals h WHERE h.id IN (7, 9, 10, 15, 17);

-- Thêm dịch vụ con cho từng form vừa tạo
INSERT INTO hospital_services (hospital_id, booking_form_id, name, service_icon, service_target, specialty_name, schedule_text, detail_text, requires_insurance, price)
SELECT f.hospital_id, f.id, 'Chụp X-quang', 'bi-radioactive', 'specialty', '["Chẩn đoán hình ảnh"]', '1,2,3,4,5,6', 'Chụp X-quang kỹ thuật số, trả kết quả nhanh.', 0, 150000
FROM hospital_booking_forms f WHERE f.name = 'Đặt lịch chụp phim & nội soi';

INSERT INTO hospital_services (hospital_id, booking_form_id, name, service_icon, service_target, specialty_name, schedule_text, detail_text, requires_insurance, price)
SELECT f.hospital_id, f.id, 'Siêu âm tổng quát', 'bi-soundwave', 'specialty', '["Chẩn đoán hình ảnh"]', '1,2,3,4,5,6', 'Siêu âm ổ bụng, tuyến giáp, phần mềm.', 0, 200000
FROM hospital_booking_forms f WHERE f.name = 'Đặt lịch chụp phim & nội soi';

INSERT INTO hospital_services (hospital_id, booking_form_id, name, service_icon, service_target, specialty_name, schedule_text, detail_text, requires_insurance, price)
SELECT f.hospital_id, f.id, 'Chụp CT Scanner', 'bi-printer', 'specialty', '["Chẩn đoán hình ảnh"]', '1,2,3,4,5,6', 'Chụp cắt lớp vi tính (CT) đa lát cắt.', 0, 800000
FROM hospital_booking_forms f WHERE f.name = 'Đặt lịch chụp phim & nội soi';

INSERT INTO hospital_services (hospital_id, booking_form_id, name, service_icon, service_target, specialty_name, schedule_text, detail_text, requires_insurance, price)
SELECT f.hospital_id, f.id, 'Chụp MRI', 'bi-magnet', 'specialty', '["Chẩn đoán hình ảnh"]', '1,2,3,4,5,6', 'Chụp cộng hưởng từ (MRI).', 0, 1800000
FROM hospital_booking_forms f WHERE f.name = 'Đặt lịch chụp phim & nội soi';

INSERT INTO hospital_services (hospital_id, booking_form_id, name, service_icon, service_target, specialty_name, schedule_text, detail_text, requires_insurance, price)
SELECT f.hospital_id, f.id, 'Nội soi tiêu hóa', 'bi-camera-video', 'specialty', '["Nội soi"]', '1,2,3,4,5,6', 'Nội soi dạ dày, đại tràng (có/không gây mê).', 0, 600000
FROM hospital_booking_forms f WHERE f.name = 'Đặt lịch chụp phim & nội soi';
