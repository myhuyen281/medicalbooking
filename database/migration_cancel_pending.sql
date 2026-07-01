-- Thêm trạng thái cancel_pending cho lịch hẹn chờ hủy (hoàn tiền)
ALTER TABLE appointments
MODIFY COLUMN status ENUM('pending','confirmed','completed','cancel_pending','cancelled') DEFAULT 'pending';
