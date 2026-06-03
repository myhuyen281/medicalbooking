USE medical_booking;

ALTER TABLE hospitals ADD COLUMN IF NOT EXISTS homepage_priority INT NOT NULL DEFAULT 0;

-- Ưu tiên hiển thị đầu trang: Da Liễu rồi Nhi Đồng (các cơ sở có tài khoản đã duyệt)
UPDATE hospitals SET homepage_priority = 20 WHERE id = 8;
UPDATE hospitals SET homepage_priority = 10 WHERE id = 7;
