<?php
require_once '../../config/database.php';
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/MEDICAILBOOKING';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: $base_url/views/auth/login.php");
    exit();
}

$db = new Database();
$userId = $_SESSION['user_id'];

// Fetch user info
$db->query("SELECT full_name, email, phone FROM users WHERE id = :id");
$db->bind(':id', $userId);
$user = $db->single();

// Fetch patient profile if exists
$db->query("SELECT * FROM patient_profiles WHERE user_id = :uid");
$db->bind(':uid', $userId);
$profile = $db->single();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ bệnh nhân | Medpro - Đặt lịch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .top-bar { background: #fff; border-bottom: 1px solid #e9ecef; padding: 8px 0; font-size: 13px; }
        .top-bar a { color: #666; text-decoration: none; margin-right: 15px; }
        .top-bar .social-icons i { margin-right: 12px; font-size: 16px; }
        .main-nav { background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .main-nav .navbar-brand { font-weight: bold; font-size: 28px; color: #00a8e8; }
        .search-bar { background: #f1f3f5; border-radius: 50px; padding: 8px 20px; border: none; width: 300px; }
        .nav-menu a { color: #333; font-weight: 500; padding: 8px 16px; }
        .nav-menu a:hover { color: #00a8e8; }
        .breadcrumb { background: transparent; padding: 15px 0; font-size: 14px; }
        .sidebar-menu { background: #fff; border-radius: 12px; padding: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .sidebar-menu a { display: block; padding: 12px 24px; color: #495057; text-decoration: none; font-weight: 500; transition: 0.2s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: #e7f5ff; color: #00a8e8; border-left: 3px solid #00a8e8; }
        .sidebar-menu .btn-add { background: linear-gradient(135deg, #00d4ff 0%, #00a8e8 100%); color: white; border: none; border-radius: 8px; padding: 12px 20px; font-weight: 600; width: 100%; margin: 15px 0; }
        .main-content { background: #fff; border-radius: 12px; padding: 30px; min-height: 500px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state img { max-width: 280px; margin-bottom: 30px; }
        .empty-state h5 { color: #495057; margin-bottom: 10px; }
        .empty-state p { color: #6c757d; }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="social-icons">
                <a href="#"><i class="bi bi-tiktok"></i></a>
                <a href="#"><i class="bi bi-facebook"></i></a>
                <a href="#"><i class="bi bi-chat-dots"></i> Zalo</a>
                <a href="#"><i class="bi bi-youtube"></i></a>
            </div>
            <div>
                <a href="#" class="btn btn-sm btn-warning rounded-pill px-3 py-1 me-2" style="font-size:12px; font-weight:600;">Tải ứng dụng</a>
                <a href="#" class="text-dark"><i class="bi bi-telephone"></i> +84939837176</a>
                <span class="ms-2">🇻🇳</span>
            </div>
        </div>
    </div>

    <!-- Main Navigation -->
    <nav class="navbar navbar-expand-lg main-nav">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $base_url; ?>/index.php">medpro</a>
            <div class="d-flex align-items-center">
                <input type="text" class="search-bar me-4" placeholder="Tìm kiếm chuyên khoa">
                <div class="nav-menu d-flex">
                    <a href="#">Cơ sở y tế <i class="bi bi-chevron-down ms-1"></i></a>
                    <a href="#">Dịch vụ y tế <i class="bi bi-chevron-down ms-1"></i></a>
                    <a href="#">Khám sức khỏe doanh nghiệp</a>
                    <a href="#">Tin tức</a>
                    <a href="#">Hướng dẫn</a>
                    <a href="#">Liên hệ hợp tác</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/index.php">Trang chủ</a></li>
                <li class="breadcrumb-item active">Hồ sơ bệnh nhân</li>
            </ol>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="container pb-5">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="sidebar-menu">
                    <a href="<?php echo $base_url; ?>/views/patient/profile_create.php" class="btn-add d-flex align-items-center justify-content-center">
                        <i class="bi bi-plus-circle me-2"></i> Thêm hồ sơ
                    </a>
                    <a href="<?php echo $base_url; ?>/views/patient/records.php" class="active">
                        <i class="bi bi-file-medical me-2"></i> Hồ sơ bệnh nhân
                    </a>
                    <a href="#">
                        <i class="bi bi-file-earmark-text me-2"></i> Phiếu khám bệnh
                    </a>
                    <a href="#">
                        <i class="bi bi-bell me-2"></i> Thông báo <span class="badge bg-danger ms-1">99+</span>
                    </a>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9">
                <div class="main-content">
                    <h5 class="fw-bold mb-4">Danh sách hồ sơ bệnh nhân</h5>

                    <?php if ($profile): ?>
                        <!-- Has profile -->
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:50px;height:50px;font-size:20px;font-weight:bold;">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($user['phone']); ?> • <?php echo htmlspecialchars($user['email']); ?></small>
                                    </div>
                                </div>
                                <div>
                                    <a href="<?php echo $base_url; ?>/views/patient/profile.php" class="btn btn-outline-primary btn-sm me-2">Chỉnh sửa</a>
                                    <a href="<?php echo $base_url; ?>/doctors.php" class="btn btn-primary btn-sm">Đặt khám</a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="empty-state">
                            <img src="https://cdn-icons-png.flaticon.com/512/4076/4076549.png" alt="No records">
                            <h5>Bạn chưa có hồ sơ bệnh nhân</h5>
                            <p>Vui lòng tạo mới hồ sơ để được đặt khám.</p>
                            <a href="<?php echo $base_url; ?>/views/patient/profile_create.php" class="btn btn-primary mt-3 px-4 py-2">
                                <i class="bi bi-plus-circle me-2"></i>Tạo hồ sơ mới
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>