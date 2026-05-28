<?php
require_once '../../../config/database.php';
include '../includes/header.php';

$db = new Database();
$error = '';
$success = '';

// Lấy id bác sĩ
$db->query("SELECT id FROM doctors WHERE user_id = :uid AND approval_status = 'approved'");
$db->bind(':uid', $_SESSION['user_id']);
$doctor = $db->single();

if (!$doctor) {
    echo "<div class='alert alert-danger mt-4'>Bạn chưa có hồ sơ Bác sĩ. Vui lòng liên hệ Admin.</div>";
    include '../includes/footer.php';
    exit();
}
$doctorId = $doctor['id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $workDate = $_POST['work_date'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];

    // Validate logic thời gian
    if (empty($workDate) || empty($startTime) || empty($endTime)) {
        $error = "Vui lòng nhập đầy đủ Ngày, Giờ Bắt đầu và Giờ Kết thúc.";
    } elseif (strtotime($startTime) >= strtotime($endTime)) {
        $error = "Giờ xuất phát phải diễn ra trước Giờ kết thúc.";
    } elseif (strtotime($workDate) < strtotime(date('Y-m-d'))) {
        $error = "Không thể thêm lịch làm việc trong quá khứ.";
    } else {
        // Kiểm tra xem khung giờ này đã bị trùng chưa
        $db->query("SELECT id FROM schedules WHERE doctor_id = :did AND work_date = :wdate AND 
                    ((start_time <= :st AND end_time > :st) OR (start_time < :et AND end_time >= :et) OR (start_time >= :st AND end_time <= :et))");
        $db->bind(':did', $doctorId);
        $db->bind(':wdate', $workDate);
        $db->bind(':st', $startTime);
        $db->bind(':et', $endTime);
        $db->execute();

        if ($db->rowCount() > 0) {
            $error = "Khung giờ này bị trùng lẻ với một thời gian bạn đã đăng ký trong ngày " . date('d/m/Y', strtotime($workDate));
        } else {
            $db->query("INSERT INTO schedules (doctor_id, work_date, start_time, end_time, status) VALUES (:did, :wdate, :st, :et, 'available')");
            $db->bind(':did', $doctorId);
            $db->bind(':wdate', $workDate);
            $db->bind(':st', $startTime);
            $db->bind(':et', $endTime);

            if ($db->execute()) {
                $success = "Thêm lịch làm việc thành công!";
            } else {
                $error = "Lỗi tạo lịch.";
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Thêm Khung giờ làm việc</h2>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Quay lại</a>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ngày làm việc <span class="text-danger">*</span></label>
                        <input type="date" name="work_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        <small class="text-muted">Chỉ có thể tạo lịch từ ngày hôm nay trở đi.</small>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Giờ bắt đầu <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Giờ kết thúc <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i> Lưu Lịch Làm Việc</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="alert alert-info border-0 shadow-sm">
            <h5 class="alert-heading"><i class="bi bi-lightbulb me-1"></i> Gợi ý chia khung giờ</h5>
            <p>Để tối ưu quá trình khám bệnh, bạn nên chia ca thành các Block nhỏ để người bệnh dễ dàng đặt, ví dụ:</p>
            <ul>
                <li>Sáng: 08:00 - 08:30</li>
                <li>Sáng: 08:30 - 09:00</li>
                <li>Chiều: 14:00 - 14:30</li>
                <li>Chiều: 14:30 - 15:00</li>
            </ul>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
