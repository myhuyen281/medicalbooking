<?php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    include 'includes/header.php';
    echo '<div class="container mt-5 text-center">
            <div class="alert alert-warning border-0 shadow-sm d-inline-block p-4">
                <h4><i class="bi bi-shield-lock text-warning"></i> Bạn Cần Đăng Nhập</h4>
                <p>Vui lòng đăng nhập với tư cách Bệnh Nhân để thực hiện đặt lịch khám.</p>
                <a href="views/auth/login.php" class="btn btn-primary me-2">Đăng Nhập</a>
                <a href="views/auth/register.php" class="btn btn-outline-primary">Đăng Ký</a>
            </div>
          </div>';
    include 'includes/footer.php';
    exit();
}

if (!isset($_GET['schedule_id'])) {
    header("Location: doctors.php");
    exit();
}

include 'includes/header.php';

$scheduleId = $_GET['schedule_id'];
$patientId = $_SESSION['user_id']; // Tài khoản đang đăng nhập là bệnh nhân
$db = new Database();

// 1. Kiểm tra trạng thái của lịch (chỉ cho phép đặt nếu status='available')
$db->query("SELECT s.*, d.id as doctor_id, d.consultation_fee, u.full_name as doctor_name, spec.name as specialty 
            FROM schedules s 
            INNER JOIN doctors d ON s.doctor_id = d.id 
            INNER JOIN users u ON d.user_id = u.id 
            LEFT JOIN specialties spec ON d.specialty_id = spec.id
            WHERE s.id = :sid");
$db->bind(':sid', $scheduleId);
$schedule = $db->single();

if (!$schedule || $schedule['status'] !== 'available') {
    $error = "Rất tiếc! Lịch khám này không tồn tại hoặc đã được người khác đặt.";
}

// 2. Xử lý Form đặt lịch
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($schedule) && $schedule['status'] === 'available') {
    $symptoms = trim($_POST['symptoms']);
    
    try {
        // Begin Transaction (Để đảm bảo tính toàn vẹn dữ liệu)
        $db->dbh->beginTransaction();

        // Thêm bản ghi vào Appointments (Lịch hẹn)
        $db->query("INSERT INTO appointments (patient_id, doctor_id, schedule_id, appointment_date, symptoms, status) 
                    VALUES (:pid, :did, :sid, :adate, :symptoms, 'pending')");
        $db->bind(':pid', $patientId);
        $db->bind(':did', $schedule['doctor_id']);
        $db->bind(':sid', $scheduleId);
        $db->bind(':adate', $schedule['work_date']);
        $db->bind(':symptoms', $symptoms);
        $db->execute();

        // Cập nhật thẻ trạng thái của Schedule từ 'available' -> 'booked'
        $db->query("UPDATE schedules SET status = 'booked' WHERE id = :sid");
        $db->bind(':sid', $scheduleId);
        $db->execute();

        // Commit Transaction
        $db->dbh->commit();

        $success = "Xác nhận đặt lịch hẹn thành công! Vui lòng chờ bác sĩ/phòng khám liên hệ xác nhận.";
        // Vô hiệu hóa form bằng cách giả lập status
        $schedule['status'] = 'booked';

    } catch (Exception $e) {
        $db->dbh->rollBack();
        $error = "Lỗi hệ thống trong quá trình đặt lịch: " . $e->getMessage();
    }
}
?>

<div class="row justify-content-center mt-4">
    <div class="col-md-8">
        <?php if (isset($success)): ?>
            <div class="card shadow-sm border-0 border-top border-success border-4">
                <div class="card-body text-center p-5">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                    <h2 class="mt-3 text-success">Đặt Lịch Thành Công!</h2>
                    <p class="lead">Bạn đã yêu cầu khám bệnh với BS. <?php echo htmlspecialchars($schedule['doctor_name']); ?>.</p>
                    <div class="bg-light p-3 rounded mb-4 text-start d-inline-block">
                        <ul class="list-unstyled mb-0 px-3">
                            <li class="mb-2"><strong>Ngày khám:</strong> <?php echo date('d/m/Y', strtotime($schedule['work_date'])); ?></li>
                            <li class="mb-2"><strong>Giờ khám:</strong> <span class="text-danger fw-bold"><?php echo date('H:i', strtotime($schedule['start_time'])); ?> - <?php echo date('H:i', strtotime($schedule['end_time'])); ?></span></li>
                            <li><strong>Phí dự kiến:</strong> <?php echo number_format($schedule['consultation_fee'], 0, ',', '.'); ?> VNĐ</li>
                        </ul>
                    </div>
                    <div>
                        <a href="views/patient/dashboard.php" class="btn btn-primary me-2">Quản lý Lịch Hẹn Của Tôi</a>
                        <a href="index.php" class="btn btn-outline-secondary">Về Trang Chủ</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
        
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3">
                <h4 class="mb-0"><i class="bi bi-clipboard2-check me-2"></i> Xác Nhận Đặt Lịch Khám</h4>
            </div>
            
            <div class="card-body p-4">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                        <br><br><a href="doctors.php" class="btn btn-sm btn-outline-danger">Quản lại trang Danh sách BS</a>
                    </div>
                <?php else: ?>
                    <div class="row border-bottom pb-4 mb-4">
                        <div class="col-sm-3 text-center">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($schedule['doctor_name']); ?>&background=random&size=100" class="rounded-circle shadow-sm" alt="Doctor">
                        </div>
                        <div class="col-sm-9 d-flex flex-column justify-content-center">
                            <h4 class="mb-1 text-primary fw-bold">BS. <?php echo htmlspecialchars($schedule['doctor_name']); ?></h4>
                            <div class="text-muted"><i class="bi bi-tag-fill me-1"></i> Chuyên khoa: <?php echo htmlspecialchars($schedule['specialty'] ?? 'Đa khoa'); ?></div>
                            <div class="fw-bold mt-2 text-danger">Giá khám: <?php echo number_format($schedule['consultation_fee'], 0, ',', '.'); ?> đ</div>
                        </div>
                    </div>

                    <div class="alert alert-info border-0">
                        <h5 class="alert-heading"><i class="bi bi-clock-history me-1"></i> Thời gian đăng ký</h5>
                        <p class="mb-0">
                            Ngày khám: <strong class="fs-5"><?php echo date('d/m/Y', strtotime($schedule['work_date'])); ?></strong><br>
                            Ca khám: <strong class="fs-5 text-primary"><?php echo date('H:i', strtotime($schedule['start_time'])); ?> - <?php echo date('H:i', strtotime($schedule['end_time'])); ?></strong>
                        </p>
                    </div>

                    <form method="POST" action="">
                        <div class="mb-4 mt-4">
                            <label class="form-label fw-bold">Mô tả chi tiết triệu chứng bệnh <span class="text-danger">*</span></label>
                            <textarea name="symptoms" class="form-control" rows="4" placeholder="Ví dụ: Đau đầu, sốt nhẹ, chóng mặt từ 2 ngày trước..." required></textarea>
                            <small class="text-muted">Cung cấp triệu chứng giúp Bác sĩ chuẩn bị tốt hơn trước khi bạn đến khám.</small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold"><i class="bi bi-send-check me-2"></i> Xác Nhận Đặt Lịch Ngay</button>
                            <a href="doctor_detail.php?id=<?php echo $schedule['doctor_id']; ?>" class="btn btn-outline-secondary">Hủy & Đổi giờ khác</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
