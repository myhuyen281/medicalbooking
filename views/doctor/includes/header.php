<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Kiểm tra quyền: Chỉ dành cho Bác sĩ
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../../index.php");
    exit();
}

$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/MEDICAILBOOKING';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Medical Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; flex-direction: column; }
        .sidebar { min-height: calc(100vh - 56px); background-color: #2c3e50; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 12px 20px; display: block; border-left: 3px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background-color: #34495e; border-left: 3px solid #3498db; }
        .main-content { flex: 1; padding: 20px; background-color: #f8f9fa; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark shadow-sm" style="background-color: #1a252f;">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="<?php echo $base_url; ?>/views/doctor/dashboard.php">
                <i class="bi bi-heart-pulse me-2"></i>Bác Sĩ - MedicalBooking
            </a>
            <div class="d-flex text-white align-items-center">
                <span class="me-3"><i class="bi bi-person-circle me-1"></i> BS. <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="<?php echo $base_url; ?>/views/auth/logout.php" class="btn btn-sm btn-outline-light">Đăng xuất</a>
            </div>
        </div>
    </nav>
    <div class="d-flex">
        <div class="sidebar flex-shrink-0" style="width: 250px;">
            <a href="<?php echo $base_url; ?>/views/doctor/dashboard.php"><i class="bi bi-house-door me-2"></i> Tổng quan</a>
            <a href="<?php echo $base_url; ?>/views/doctor/schedules/index.php"><i class="bi bi-calendar-plus me-2"></i> Lịch làm việc của tôi</a>
            <a href="<?php echo $base_url; ?>/views/doctor/appointments/index.php"><i class="bi bi-journal-medical me-2"></i> Cuộc hẹn khám bệnh</a>
            <a href="#"><i class="bi bi-star me-2"></i> Đánh giá từ bệnh nhân</a>
            <hr class="text-white mx-3">
            <a href="<?php echo $base_url; ?>/index.php" target="_blank"><i class="bi bi-box-arrow-up-right me-2"></i> Về trang chủ</a>
        </div>
        <div class="main-content">
