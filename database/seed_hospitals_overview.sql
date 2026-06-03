-- Bổ sung overview (Tổng quan) + ảnh nội dung cho 2 cơ sở seed
USE medical_booking;

-- Hoàn Mỹ Cửu Long (id 9)
UPDATE hospitals SET
  content_image_url = 'uploads/hospitals/hoanmy_cuulong_logo.webp',
  overview = 'Bệnh viện Đa khoa Hoàn Mỹ Cửu Long được thành lập năm 2007, là một trong những bệnh viện tư nhân lớn và uy tín tại khu vực Đồng bằng sông Cửu Long. Bệnh viện quy tụ đội ngũ bác sĩ, chuyên gia giàu kinh nghiệm cùng hệ thống trang thiết bị y tế hiện đại.

Các thế mạnh chuyên môn: Tim mạch, Tiêu hóa, Sản - Phụ khoa, Nhi khoa, Chẩn đoán hình ảnh và Khám sức khỏe tổng quát. Bệnh viện đạt chứng nhận của Hội đồng Tiêu chuẩn Y tế Úc (ACHSI) về chất lượng điều trị và an toàn người bệnh.

Với phương châm "Chăm sóc bằng cả trái tim", Hoàn Mỹ Cửu Long cam kết mang đến dịch vụ y tế chất lượng cao, chi phí hợp lý cho người dân trong và ngoài khu vực.'
WHERE id = 9;

-- Phương Châu (id 10)
UPDATE hospitals SET
  content_image_url = 'uploads/hospitals/phuongchau_logo.png',
  overview = 'Bệnh viện Quốc tế Phương Châu là bệnh viện chuyên khoa Sản - Nhi tư nhân hàng đầu khu vực Đồng bằng sông Cửu Long, sở hữu hệ sinh thái chăm sóc sức khỏe sinh sản toàn diện.

Hệ sinh thái dịch vụ bao gồm: Sản khoa, Phụ khoa, Nhi - Sơ sinh, Hỗ trợ sinh sản - IVF, Nam khoa và Đa khoa. Đây là một trong những hệ thống y tế đầu tiên tại Đông Nam Á đạt chứng nhận quản lý chất lượng JCI Enterprise.

Phương Châu mang đến trải nghiệm chăm sóc tận tâm, an toàn theo tiêu chuẩn quốc tế, đồng hành cùng sức khỏe của phụ nữ và gia đình qua từng giai đoạn cuộc sống.'
WHERE id = 10;
