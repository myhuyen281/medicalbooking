<?php
require_once '../../config/database.php';
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/MEDICAILBOOKING';

session_start();

// Ensure user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: $base_url/views/auth/login.php");
    exit();
}

$db = new Database();
$userId = $_SESSION['user_id'];

// Handle Patient Cancellation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $apptId = $_POST['appointment_id'];

    // Verify this appointment belongs to the logged-in patient and is cancelable (pending or confirmed)
    $db->query("SELECT status FROM appointments WHERE id = :id AND patient_id = :pid");
    $db->bind(':id', $apptId);
    $db->bind(':pid', $userId);
    $currentStore = $db->single();

    if ($currentStore && ($currentStore['status'] == 'pending' || $currentStore['status'] == 'confirmed')) {
        $db->query("UPDATE appointments SET status = 'cancelled' WHERE id = :id");
        $db->bind(':id', $apptId);
        $db->execute();

        // Release the schedule
        $db->query("UPDATE schedules s 
                    INNER JOIN appointments a ON s.id = a.schedule_id 
                    SET s.status = 'available' 
                    WHERE a.id = :aid");
        $db->bind(':aid', $apptId);
        $db->execute();

        $success = "Bạn đã hủy lịch khám thành công.";
    }
}

// Handle Patient Review
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'review') {
    $docId = $_POST['doctor_id'];
    $rating = (int)$_POST['rating'];
    $comment = $_POST['comment'];
    
    if ($rating >= 1 && $rating <= 5) {
        $db->query("INSERT INTO reviews (patient_id, doctor_id, rating, comment) VALUES (:pid, :did, :rating, :comment)");
        $db->bind(':pid', $userId);
        $db->bind(':did', $docId);
        $db->bind(':rating', $rating);
        $db->bind(':comment', $comment);
        $db->execute();
        $success = "Cảm ơn bạn đã để lại đánh giá!";
    }
}

// Fetch user info
$db->query("SELECT full_name, email, phone FROM users WHERE id = :id");
$db->bind(':id', $userId);
$user = $db->single();

// Fetch appointments for this patient
$db->query("
    SELECT a.id, a.status, a.symptoms, a.created_at,
           d.id as doctor_id,
           u_doc.full_name as doctor_name, 
           spec.name as specialty_name,
           s.work_date, s.start_time, s.end_time,
           d.consultation_fee
    FROM appointments a
    INNER JOIN schedules s ON a.schedule_id = s.id
    INNER JOIN doctors d ON a.doctor_id = d.id
    INNER JOIN users u_doc ON d.user_id = u_doc.id
    INNER JOIN specialties spec ON d.specialty_id = spec.id
    WHERE a.patient_id = :pid
    ORDER BY s.work_date DESC, s.start_time DESC
");
$db->bind(':pid', $userId);
$appointments = $db->resultSet();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Điều Khiển Patient - Đặt Lịch Khám</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .sidebar { background-color: #2b3a4a; min-height: 100vh; color: white; }
        .sidebar a { color: #cbd5e1; text-decoration: none; display: block; padding: 12px 20px; border-radius: 5px; margin: 5px 15px; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background-color: #3b5066; color: white; }
        .main-content { padding: 30px; }
        .navbar { background-color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .status-badge { font-size: 13px; padding: 5px 10px; border-radius: 20px; font-weight: 500;}
        .pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;}
        .confirmed { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .completed { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb;}
        .cancelled { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar flex-shrink-0" style="width: 250px;">
            <div class="text-center py-4 border-bottom border-secondary mb-3">
                <h4 class="m-0"><i class="bi bi-heart-pulse-fill text-danger me-2"></i>Medical Patient</h4>
            </div>
            <a href="<?php echo $base_url; ?>/views/patient/dashboard.php" class="active"><i class="bi bi-person-lines-fill me-2"></i> Lịch khám</a>
            <a href="<?php echo $base_url; ?>/views/patient/profile.php"><i class="bi bi-file-medical me-2"></i> Hồ sơ bệnh nhân</a>
            <a href="<?php echo $base_url; ?>/doctors.php"><i class="bi bi-search me-2"></i> Đặt lịch hẹn mới</a>
            <hr class="text-white mx-3 border-secondary">
            <a href="<?php echo $base_url; ?>/views/auth/logout.php" class="text-danger mt-5"><i class="bi bi-box-arrow-right me-2"></i> Đăng xuất</a>
        </div>

        <!-- Main content -->
        <div class="flex-grow-1">
            <!-- Navbar -->
            <nav class="navbar navbar-expand-lg px-4 py-3">
                <div class="container-fluid">
                    <h5 class="mb-0 text-muted">Bảng điều khiển Bệnh nhân</h5>
                    <div class="d-flex align-items-center">
                        <span class="me-3 fw-medium text-dark"><i class="bi bi-person-circle"></i> Xin chào, <?php echo htmlspecialchars($user['full_name']); ?></span>
                    </div>
                </div>
            </nav>

            <div class="main-content">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <!-- User Profile Card -->
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body text-center pt-5">
                                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 32px;">
                                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                </div>
                                <h4 class="card-title"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                <p class="text-muted mb-1"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                                <p class="text-muted"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($user['phone']); ?></p>
                                <button class="btn btn-outline-primary btn-sm mt-3">Sửa thông tin cá nhân</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="col-md-8">
                        <div class="row h-100">
                            <div class="col-sm-6 mb-3 mb-sm-0">
                                <div class="card shadow-sm border-0 bg-primary text-white h-100">
                                    <div class="card-body d-flex flex-column justify-content-center align-items-center">
                                        <i class="bi bi-calendar-check" style="font-size: 2.5rem;"></i>
                                        <h2 class="mt-2 text-white">
                                            <?php echo count(array_filter($appointments, fn($a) => $a['status'] === 'confirmed' || $a['status'] === 'pending')); ?>
                                        </h2>
                                        <span>Lịch Khám Sắp Tới</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="card shadow-sm border-0 bg-info text-white h-100">
                                    <div class="card-body d-flex flex-column justify-content-center align-items-center">
                                        <i class="bi bi-clipboard2-pulse" style="font-size: 2.5rem;"></i>
                                        <h2 class="mt-2 text-white">
                                            <?php echo count(array_filter($appointments, fn($a) => $a['status'] === 'completed')); ?>
                                        </h2>
                                        <span>Tổng lần đã khám</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appointments List -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-list-task me-2"></i>Lịch sử đặt khám của bạn</h5>
                        <a href="<?php echo $base_url; ?>/doctors.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Đặt khám mới</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Ngày hẹn</th>
                                        <th>Giờ hẹn</th>
                                        <th>Bác sĩ</th>
                                        <th>Chuyên khoa</th>
                                        <th>Trạng thái</th>
                                        <th class="text-center pe-4">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($appointments) > 0): ?>
                                        <?php foreach($appointments as $appt): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-dark"><?php echo date('d/m/Y', strtotime($appt['work_date'])); ?></td>
                                                <td><span class="badge bg-light text-dark border"><i class="bi bi-clock"></i> <?php echo date('H:i', strtotime($appt['start_time'])); ?> - <?php echo date('H:i', strtotime($appt['end_time'])); ?></span></td>
                                                <td>
                                                    <div class="fw-bold text-primary">BS. <?php echo htmlspecialchars($appt['doctor_name']); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($appt['specialty_name']); ?></td>
                                                <td>
                                                    <?php 
                                                        $statusClass = '';
                                                        $statusText = '';
                                                        switch($appt['status']) {
                                                            case 'pending': $statusClass = 'pending'; $statusText = 'Chờ BS duyệt'; break;
                                                            case 'confirmed': $statusClass = 'confirmed'; $statusText = 'Đã chốt lịch'; break;
                                                            case 'completed': $statusClass = 'completed'; $statusText = 'Đã khám xong'; break;
                                                            case 'cancelled': $statusClass = 'cancelled'; $statusText = 'Đã hủy'; break;
                                                        }
                                                    ?>
                                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                                </td>
                                                <td class="text-center pe-4">
                                                    <?php if($appt['status'] == 'pending' || $appt['status'] == 'confirmed'): ?>
                                                        <!-- Chỉ cho phép tự hủy nếu lịch chưa hoàn thành -->
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                                            <button type="submit" name="action" value="cancel" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bạn thực sự muốn hủy lịch khám này? Các bác sĩ sẽ nhận được thông báo.');">Hủy lịch</button>
                                                        </form>
                                                    <?php elseif($appt['status'] == 'completed'): ?>
                                                        <!-- Đã khám xong thì có thể Review -->
                                                        <button type="button" class="btn btn-sm btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $appt['id']; ?>"><i class="bi bi-star"></i> Đánh giá</button>

                                                        <!-- Modal Đánh giá -->
                                                        <div class="modal fade" id="reviewModal<?php echo $appt['id']; ?>" tabindex="-1" aria-labelledby="reviewLabel" aria-hidden="true">
                                                          <div class="modal-dialog">
                                                            <div class="modal-content text-start">
                                                              <div class="modal-header">
                                                                <h5 class="modal-title">Đánh giá Bác sĩ</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                              </div>
                                                              <form method="POST" action="">
                                                                  <div class="modal-body">
                                                                    <input type="hidden" name="action" value="review">
                                                                    <input type="hidden" name="doctor_id" value="<?php echo $appt['doctor_id']; ?>">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Điểm đánh giá (1-5 sao)</label>
                                                                        <select name="rating" class="form-select" required>
                                                                            <option value="5">⭐⭐⭐⭐⭐ (5) Tuyệt vời</option>
                                                                            <option value="4">⭐⭐⭐⭐ (4) Tốt</option>
                                                                            <option value="3">⭐⭐⭐ (3) Bình thường</option>
                                                                            <option value="2">⭐⭐ (2) Không hài lòng</option>
                                                                            <option value="1">⭐ (1) Tệ</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Nhận xét của bạn</label>
                                                                        <textarea name="comment" class="form-control" rows="3" placeholder="Chia sẻ trải nghiệm khám bệnh của bạn..." required></textarea>
                                                                    </div>
                                                                  </div>
                                                                  <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                                    <button type="submit" class="btn btn-primary">Gửi đánh giá</button>
                                                                  </div>
                                                              </form>
                                                            </div>
                                                          </div>
                                                        </div>

                                                    <?php else: ?>
                                                        <span class="text-muted"><i class="bi bi-dash"></i></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <div class="text-muted mb-3"><i class="bi bi-journal-x" style="font-size: 3rem;"></i></div>
                                                <h6>Bạn chưa có lịch hẹn khám nào.</h6>
                                                <a href="<?php echo $base_url; ?>/doctors.php" class="btn btn-primary mt-2">Tìm bác sĩ ngay</a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>