<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hospital'])) {
    header("Location: ../../index.php");
    exit();
}

$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/MEDICAILBOOKING';
$isSystemAdmin = $_SESSION['role'] === 'admin';
$isHospitalAdmin = $_SESSION['role'] === 'hospital';
$currentHospitalId = $_SESSION['hospital_id'] ?? null;
require_once __DIR__ . '/../../../includes/hospital_subscription.php';
$headerSubscriptionDb = new Database();
$currentHospitalPlan = $isHospitalAdmin ? getHospitalSubscriptionPlan($headerSubscriptionDb, $currentHospitalId) : null;
$currentHospitalSubscriptionActive = $isHospitalAdmin ? isHospitalSubscriptionActive($headerSubscriptionDb, $currentHospitalId) : true;
$hospitalAllowedPaths = [
    '/views/admin/dashboard.php',
    '/views/admin/schedules/index.php',
    '/views/admin/schedules/create.php',
    '/views/admin/schedules/services.php',
    '/views/admin/schedules/booking_forms.php',
    '/views/admin/schedules/lab_packages.php',
    '/views/admin/schedules/lab_package_services.php',
    '/views/admin/appointments/index.php',
    '/views/admin/refunds/index.php',
    '/views/admin/hospital_profile.php'
];
$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$currentPath = preg_replace('#^/MEDICAILBOOKING#', '', $currentPath);
if ($isHospitalAdmin && !in_array($currentPath, $hospitalAllowedPaths)) {
    header("Location: $base_url/views/admin/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Medical Booking</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Google Fonts Import */
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #f8fafc;
            color: #1e293b;
        }

        /* Modern Scrollbars */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Glassmorphic Translucent Header Navbar */
        .navbar-custom {
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.6);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.01);
            padding: 0.85rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1020;
        }
        .navbar-custom .navbar-brand {
            font-weight: 800;
            font-size: 1.35rem;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #023f6d 0%, #00b5f1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .navbar-custom .navbar-brand i {
            -webkit-text-fill-color: #00b5f1;
            margin-right: 0.5rem;
            display: inline-block;
            transition: transform 0.3s ease;
        }
        .navbar-custom .navbar-brand:hover i {
            transform: rotate(-10deg) scale(1.1);
        }
        .navbar-custom .btn-logout {
            border-radius: 9999px;
            padding: 0.45rem 1.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            color: #64748b;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .navbar-custom .btn-logout:hover {
            background-color: #fff1f2;
            color: #ef4444;
            border-color: #fee2e2;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.08);
        }

        /* Premium Sidebar Layout - aligned with main deep corporate navy (#023f6d) */
        .sidebar {
            min-height: calc(100vh - 65px);
            background: #023f6d;
            width: 265px;
            padding: 1.75rem 0;
            box-shadow: 4px 0 24px rgba(2, 63, 109, 0.08);
            z-index: 1010;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }
        .sidebar .sidebar-heading {
            padding: 0 1.75rem 1.25rem 1.75rem;
            color: rgba(255, 255, 255, 0.45);
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .sidebar a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            font-weight: 600;
            font-size: 0.9rem;
            margin: 0.25rem 0.85rem;
            border-radius: 0.625rem;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
        }
        .sidebar a i {
            font-size: 1.2rem;
            margin-right: 0.85rem;
            color: rgba(255, 255, 255, 0.65);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.3s ease;
        }
        .sidebar a:hover {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.08);
            transform: translateX(4px);
        }
        .sidebar a:hover i {
            transform: scale(1.15);
            color: #00b5f1;
        }
        .sidebar a.active {
            background: linear-gradient(135deg, rgba(0, 181, 241, 0.25) 0%, rgba(0, 181, 241, 0.1) 100%);
            color: #ffffff;
            box-shadow: inset 0 0 0 1px rgba(0, 181, 241, 0.15);
            font-weight: 700;
        }
        .sidebar a.active i {
            color: #00b5f1;
        }
        .sidebar a.active::before {
            content: '';
            position: absolute;
            left: -0.85rem;
            top: 20%;
            height: 60%;
            width: 4px;
            background-color: #00b5f1;
            border-radius: 0 4px 4px 0;
            box-shadow: 0 0 8px rgba(0, 181, 241, 0.6);
        }
        .sidebar hr {
            border-color: rgba(255, 255, 255, 0.08);
            margin: 1.5rem 1.75rem;
        }

        /* Main Content Panel */
        .main-content {
            flex: 1;
            padding: 2.5rem 3rem;
            background-color: #f8fafc;
            min-height: calc(100vh - 65px);
        }

        /* Premium Card Styles with Light-sweep shining reflection */
        .card {
            background-color: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.7);
            border-radius: 1.25rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.02), 0 1px 2px -1px rgba(0, 0, 0, 0.02);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -150%;
            width: 50%;
            height: 100%;
            background: linear-gradient(to right, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.25) 100%);
            transform: skewX(-25deg);
            transition: 0.85s;
        }
        .card:hover {
            box-shadow: 0 12px 20px -3px rgba(0, 0, 0, 0.03), 0 4px 12px -4px rgba(0, 0, 0, 0.02);
            transform: translateY(-2px);
        }
        .card:hover::after {
            left: 150%;
        }
        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            padding: 1.5rem 1.75rem;
            font-weight: 750;
            color: #023f6d;
            border-left: 4px solid #00b5f1;
        }

        /* Metric / Stats Cards - corporate brand colors */
        .card.bg-primary {
            background: linear-gradient(135deg, #023f6d 0%, #00b5f1 100%) !important;
            border: none;
        }
        .card.bg-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            border: none;
        }
        .card.bg-warning {
            background: linear-gradient(135deg, #ffb02e 0%, #f7941d 100%) !important;
            color: #ffffff !important;
            border: none;
        }
        .card.bg-warning .text-dark, .card.bg-warning h2, .card.bg-warning h6 {
            color: #ffffff !important;
        }
        .card.bg-danger {
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%) !important;
            border: none;
        }
        .card.bg-primary .text-white-50, .card.bg-success .text-white-50, .card.bg-warning .text-white-50, .card.bg-danger .text-white-50 {
            opacity: 0.8 !important;
            font-weight: 700;
        }

        /* Modern Tables styling */
        .table {
            margin-bottom: 0;
            color: #334155;
            font-size: 0.9rem;
        }
        .table thead th {
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.25rem;
        }
        .table tbody td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.2s ease;
        }
        .table-hover tbody tr:hover td {
            background-color: #f8fafc;
        }

        /* Form Styling overrides */
        .form-control, .form-select {
            border-radius: 0.625rem;
            border: 1px solid #cbd5e1;
            padding: 0.75rem 1.15rem;
            font-size: 0.9rem;
            color: #1e293b;
            transition: all 0.25s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        .form-control:focus, .form-select:focus {
            border-color: #00b5f1;
            box-shadow: 0 0 0 4px rgba(0, 181, 241, 0.12);
        }
        .form-label {
            font-weight: 700;
            color: #334155;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }

        /* Soft Pastels Badge System */
        .badge {
            padding: 0.45em 0.9em;
            font-size: 0.725rem;
            font-weight: 700;
            border-radius: 9999px;
            letter-spacing: 0.03em;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        .badge.bg-warning {
            background-color: #fffbeb !important;
            color: #d97706 !important;
            border: 1px solid #fef3c7 !important;
        }
        .badge.bg-success {
            background-color: #ecfdf5 !important;
            color: #059669 !important;
            border: 1px solid #d1fae5 !important;
        }
        .badge.bg-info {
            background-color: #f0f9ff !important;
            color: #00b5f1 !important;
            border: 1px solid #e0f2fe !important;
        }
        .badge.bg-danger {
            background-color: #fef2f2 !important;
            color: #dc2626 !important;
            border: 1px solid #fee2e2 !important;
        }
        .badge.bg-primary {
            background-color: #f0f9ff !important;
            color: #00b5f1 !important;
            border: 1px solid #e0f2fe !important;
        }

        /* Buttons styling */
        .btn {
            border-radius: 0.625rem;
            padding: 0.6rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 700;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .btn:hover {
            transform: translateY(-1px);
        }
        .btn-primary {
            background-color: #00b5f1;
            border-color: #00b5f1;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 181, 241, 0.18);
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: #00a8f0 !important;
            border-color: #00a8f0 !important;
            box-shadow: 0 6px 20px rgba(0, 181, 241, 0.3) !important;
            color: #ffffff !important;
        }
        .btn-outline-primary {
            color: #00b5f1;
            border-color: #00b5f1;
            background-color: transparent;
        }
        .btn-outline-primary:hover {
            background-color: #00b5f1;
            border-color: #00b5f1;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 181, 241, 0.2);
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $base_url; ?>/views/admin/dashboard.php">
                <i class="bi bi-hospital"></i>MedicalBooking Admin
            </a>
            <div class="d-flex align-items-center">
                <span class="me-3 fw-semibold text-secondary"><i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="<?php echo $base_url; ?>/views/auth/logout.php" class="btn-logout text-decoration-none">Đăng xuất</a>
            </div>
        </div>
    </nav>
    <?php if ($isHospitalAdmin && !$currentHospitalSubscriptionActive): ?>
        <div class="alert alert-warning rounded-0 mb-0 text-center fw-semibold"><?php echo hospitalSubscriptionExpiredMessage(); ?></div>
    <?php endif; ?>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar flex-shrink-0">
            <div class="sidebar-heading">Menu quản trị</div>
            <a href="<?php echo $base_url; ?>/views/admin/dashboard.php" class="<?php echo (strpos($currentPath, '/dashboard.php') !== false) ? 'active' : ''; ?>"><i class="bi bi-speedometer2"></i> Tổng quan</a>
            
            <?php if ($isSystemAdmin): ?>
            <a href="<?php echo $base_url; ?>/views/admin/users/index.php" class="<?php echo (strpos($currentPath, '/users/') !== false) ? 'active' : ''; ?>"><i class="bi bi-people"></i> Quản lý Người dùng</a>
            <a href="<?php echo $base_url; ?>/views/admin/hospitals/index.php" class="<?php echo (strpos($currentPath, '/hospitals/index.php') !== false) ? 'active' : ''; ?>"><i class="bi bi-building-check"></i> Duyệt Bệnh viện</a>
            <a href="<?php echo $base_url; ?>/views/admin/hospitals/manage.php" class="<?php echo (strpos($currentPath, '/hospitals/manage.php') !== false) ? 'active' : ''; ?>"><i class="bi bi-hospital"></i> Quản lý Bệnh viện</a>
            <a href="<?php echo $base_url; ?>/views/admin/homepage_banners.php" class="<?php echo (strpos($currentPath, '/homepage_banners.php') !== false) ? 'active' : ''; ?>"><i class="bi bi-images"></i> Quản lý Banner</a>
            <a href="<?php echo $base_url; ?>/views/admin/news.php" class="<?php echo (strpos($currentPath, '/news.php') !== false) ? 'active' : ''; ?>"><i class="bi bi-newspaper"></i> Quản lý Tin tức</a>
            <?php endif; ?>
            
            <?php if ($isHospitalAdmin): ?>
            <a href="<?php echo $base_url; ?>/views/admin/hospital_profile.php" class="<?php echo (strpos($currentPath, '/hospital_profile.php') !== false) ? 'active' : ''; ?>"><i class="bi bi-building"></i> Hồ sơ Bệnh viện</a>
            <?php endif; ?>
            
            <?php if ($isHospitalAdmin): ?>
            <a href="<?php echo $base_url; ?>/views/admin/schedules/index.php" class="<?php echo (strpos($currentPath, '/schedules/index.php') !== false || strpos($currentPath, '/schedules/create.php') !== false) ? 'active' : ''; ?>"><i class="bi bi-calendar-plus"></i> Quản lý Lịch khám</a>
            <?php if (hospitalPlanAllows($currentHospitalPlan, 'booking_forms')): ?>
            <a href="<?php echo $base_url; ?>/views/admin/schedules/booking_forms.php" class="<?php echo (strpos($currentPath, '/schedules/booking_forms.php') !== false || strpos($currentPath, '/schedules/services.php') !== false) ? 'active' : ''; ?>"><i class="bi bi-grid-3x3-gap"></i> Các hình thức đặt khám</a>
            <?php endif; ?>
            <?php if (hospitalPlanAllows($currentHospitalPlan, 'lab_packages')): ?>
            <?php $packageCategory = $_GET['category'] ?? 'lab'; ?>
            <a href="<?php echo $base_url; ?>/views/admin/schedules/lab_packages.php?category=lab" class="<?php echo (strpos($currentPath, '/schedules/lab_packages.php') !== false && $packageCategory === 'lab') ? 'active' : ''; ?>"><i class="bi bi-clipboard2-pulse"></i> Gói xét nghiệm</a>
            <a href="<?php echo $base_url; ?>/views/admin/schedules/lab_packages.php?category=imaging" class="<?php echo (strpos($currentPath, '/schedules/lab_packages.php') !== false && $packageCategory === 'imaging') ? 'active' : ''; ?>"><i class="bi bi-camera"></i> Gói chụp phim nội soi</a>
            <a href="<?php echo $base_url; ?>/views/admin/schedules/lab_packages.php?category=vaccination" class="<?php echo (strpos($currentPath, '/schedules/lab_packages.php') !== false && $packageCategory === 'vaccination') ? 'active' : ''; ?>"><i class="bi bi-eyedropper"></i> Gói tiêm chủng</a>
            <a href="<?php echo $base_url; ?>/views/admin/schedules/lab_packages.php?category=health" class="<?php echo (strpos($currentPath, '/schedules/lab_packages.php') !== false && $packageCategory === 'health') ? 'active' : ''; ?>"><i class="bi bi-heart-pulse"></i> Thêm gói khám sức khỏe</a>
            <a href="<?php echo $base_url; ?>/views/admin/schedules/lab_packages.php?category=circular" class="<?php echo (strpos($currentPath, '/schedules/lab_packages.php') !== false && $packageCategory === 'circular') ? 'active' : ''; ?>"><i class="bi bi-file-medical"></i> Thêm khám sức khỏe thông tư</a>
            <a href="<?php echo $base_url; ?>/views/admin/schedules/lab_packages.php?category=homecare" class="<?php echo (strpos($currentPath, '/schedules/lab_packages.php') !== false && $packageCategory === 'homecare') ? 'active' : ''; ?>"><i class="bi bi-house-heart"></i> Gói Y tế tại nhà</a>
            <?php endif; ?>
            <a href="<?php echo $base_url; ?>/views/admin/appointments/index.php" class="<?php echo (strpos($currentPath, '/appointments/') !== false) ? 'active' : ''; ?>"><i class="bi bi-calendar-check"></i> Quản lý Đơn khám</a>
            <a href="<?php echo $base_url; ?>/views/admin/refunds/index.php" class="<?php echo (strpos($currentPath, '/refunds/') !== false) ? 'active' : ''; ?>"><i class="bi bi-cash-coin"></i> Quản lý hoàn tiền</a>
            <?php endif; ?>
            <hr>
            <a href="<?php echo $base_url; ?>/index.php" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Xem Website</a>
        </div>
        <!-- Main Content -->
        <div class="main-content">
