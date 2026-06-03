-- Cập nhật logo (thật) + thông tin cho 2 cơ sở seed
USE medical_booking;

-- Bệnh viện Đa khoa Hoàn Mỹ Cửu Long (id 9) - logo & thông tin chính thức
UPDATE hospitals SET
  facility_type = 'private',
  address = 'Số 20 đường Võ Nguyên Giáp (Quang Trung), P. Phú Thứ, Q. Cái Răng, TP. Cần Thơ',
  phone = '02923917355',
  email = 'info.hmcl@hoanmy.com',
  logo_url = 'uploads/hospitals/hoanmy_cuulong_logo.webp',
  poster_url = 'https://hoanmy.com/wp-content/uploads/2025/11/hoanmycuulong-banner-en.jpg',
  working_time = 'Mở cửa 24/24 giờ tất cả các ngày trong tuần',
  short_description = 'Bệnh viện tư nhân lớn tại khu vực Đồng bằng sông Cửu Long, đạt chứng nhận ACHSI.',
  description = 'Thành lập năm 2007, Bệnh viện Hoàn Mỹ Cửu Long phục vụ nhu cầu chăm sóc sức khỏe cho người dân khu vực Đồng bằng sông Cửu Long và các tỉnh lân cận. Bệnh viện cung cấp dịch vụ y tế chất lượng cao với chi phí hợp lý theo phương châm "Chăm sóc bằng cả trái tim", đạt chứng nhận của Hội đồng Tiêu chuẩn Y tế Úc (ACHSI).',
  rating = 4.7
WHERE id = 9;

-- Bệnh viện Quốc tế Phương Châu (id 10) - logo & thông tin chính thức
UPDATE hospitals SET
  facility_type = 'private',
  address = '300 Nguyễn Văn Cừ nối dài, P. An Bình, Q. Ninh Kiều, TP. Cần Thơ',
  phone = '1900545466',
  email = 'cskh@phuongchau.com',
  logo_url = 'uploads/hospitals/phuongchau_logo.png',
  poster_url = 'https://phuongchau.com/Data/Sites/1/News/2526/8.jpg',
  working_time = 'Thứ 2 - Chủ nhật: 07:00 - 19:00 (Cấp cứu 24/24)',
  short_description = 'Tập đoàn Bệnh viện Sản - Nhi tư nhân hàng đầu, đạt chứng nhận JCI Enterprise.',
  description = 'Bệnh viện Quốc tế Phương Châu sở hữu hệ sinh thái chăm sóc sức khỏe sinh sản toàn diện gồm Sản - Phụ khoa, Nhi - Sơ sinh, Hiếm muộn - IVF, Nam khoa và Đa khoa. Phương Châu là một trong những hệ thống y tế đầu tiên tại Đông Nam Á đạt chứng nhận quản lý chất lượng JCI Enterprise.',
  rating = 4.8
WHERE id = 10;
