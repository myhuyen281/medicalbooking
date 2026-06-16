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

// Fetch patient profile
$db->query("SELECT * FROM patient_profiles WHERE user_id = :uid");
$db->bind(':uid', $userId);
$profile = $db->single();

if (!$profile) {
    header("Location: $base_url/views/patient/records.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ bệnh nhân | Medpro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .top-bar { background: #fff; border-bottom: 1px solid #e9ecef; padding: 8px 0; font-size: 13px; }
        .main-nav { background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .main-nav .navbar-brand { font-weight: bold; font-size: 28px; color: #00a8e8; }
        .breadcrumb { background: transparent; padding: 15px 0; }
        .sidebar-menu { background: #fff; border-radius: 12px; padding: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .sidebar-menu a { display: block; padding: 12px 24px; color: #495057; text-decoration: none; font-weight: 500; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: #e7f5ff; color: #00a8e8; border-left: 3px solid #00a8e8; }
        .main-content { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .profile-header { background: linear-gradient(135deg, #00d4ff 0%, #00a8e8 100%); border-radius: 16px; padding: 40px; color: white; margin-bottom: 30px; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; font-size: 42px; font-weight: bold; color: #00a8e8; margin: 0 auto 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .profile-name { font-size: 28px; font-weight: bold; text-align: center; margin-bottom: 8px; }
        .profile-info { text-align: center; opacity: 0.9; font-size: 15px; }
        .info-section { margin-bottom: 30px; }
        .info-section h6 { color: #00a8e8; font-weight: bold; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #f1f3f5; font-size: 18px; }
        .info-row { display: flex; margin-bottom: 16px; }
        .info-label { min-width: 180px; font-weight: 600; color: #495057; }
        .info-value { color: #212529; }
        .empty-field { color: #adb5bd; font-style: italic; }
        .tag { display: inline-block; padding: 8px 16px; background: #e7f5ff; color: #00a8e8; border-radius: 20px; font-size: 14px; font-weight: 600; margin-right: 10px; margin-bottom: 10px; }
        .badge-custom { padding: 6px 14px; border-radius: 16px; font-size: 13px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
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
                <a href="#" class="btn btn-sm btn-warning rounded-pill px-3 py-1 me-2">Tải ứng dụng</a>
                <a href="#" class="text-dark"><i class="bi bi-telephone"></i> +84939837176</a>
                <span class="ms-2">🇻🇳</span>
            </div>
        </div>
    </div>

    <!-- Main Navigation -->
    <nav class="navbar navbar-expand-lg main-nav">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $base_url; ?>/index.php">medpro</a>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/index.php">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/views/patient/records.php">Hồ sơ bệnh nhân</a></li>
                <li class="breadcrumb-item active">Chi tiết hồ sơ</li>
            </ol>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="container pb-5">
        <div class="row">
            <div class="col-md-3">
                <div class="sidebar-menu">
                    <a href="<?php echo $base_url; ?>/views/patient/records.php" class="active"><i class="bi bi-file-medical me-2"></i> Hồ sơ bệnh nhân</a>
                    <a href="#"><i class="bi bi-file-earmark-text me-2"></i> Phiếu khám bệnh</a>
                    <a href="#"><i class="bi bi-bell me-2"></i> Thông báo <span class="badge bg-danger ms-1">99+</span></a>
                </div>
            </div>

            <div class="col-md-9">
                <div class="main-content">
                    <!-- Profile Header -->
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        <div class="profile-info">
                            <i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?>
                            <span class="mx-2">|</span>
                            <i class="bi bi-telephone me-2"></i><?php echo htmlspecialchars($user['phone']); ?>
                        </div>
                    </div>

                    <!-- Thông tin cơ bản -->
                    <div class="info-section">
                        <h6><i class="bi bi-person me-2"></i>Thông tin cơ bản</h6>
                        <div class="info-row">
                            <span class="info-label">Họ và tên:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Ngày sinh:</span>
                            <span class="info-value">
                                <?php if ($profile['date_of_birth']): ?>
                                    <?php echo date('d/m/Y', strtotime($profile['date_of_birth'])); ?>
                                <?php else: ?>
                                    <span class="empty-field">Chưa cập nhật</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Giới tính:</span>
                            <span class="info-value">
                                <?php 
                                $genders = ['male' => 'Nam', 'female' => 'Nữ', 'other' => 'Khác'];
                                echo $genders[$profile['gender']] ?? '<span class="empty-field">Chưa cập nhật</span>';
                                ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">CMND/CCCD:</span>
                            <span class="info-value"><?php echo $profile['identity_card'] ?: '<span class="empty-field">Chưa cập nhật</span>'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Số thẻ BHYT:</span>
                            <span class="info-value"><?php echo $profile['insurance_number'] ?: '<span class="empty-field">Chưa cập nhật</span>'; ?></span>
                        </div>
                    </div>

                    <!-- Địa chỉ -->
                    <div class="info-section">
                        <h6><i class="bi bi-geo-alt me-2"></i>Địa chỉ liên hệ</h6>
                        <div class="info-row">
                            <span class="info-label">Tỉnh/Thành phố:</span>
                            <span class="info-value"><?php echo $profile['province'] ?: '<span class="empty-field">Chưa cập nhật</span>'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Quận/Huyện:</span>
                            <span class="info-value"><?php echo $profile['district'] ?: '<span class="empty-field">Chưa cập nhật</span>'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phường/Xã:</span>
                            <span class="info-value"><?php echo $profile['ward'] ?: '<span class="empty-field">Chưa cập nhật</span>'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Địa chỉ chi tiết:</span>
                            <span class="info-value"><?php echo $profile['address_detail'] ?: '<span class="empty-field">Chưa cập nhật</span>'; ?></span>
                        </div>
                    </div>

                    <!-- Người liên hệ khẩn cấp -->
                    <div class="info-section">
                        <h6><i class="bi bi-telephone me-2"></i>Người liên hệ khẩn cấp</h6>
                        <div class="info-row">
                            <span class="info-label">Họ tên:</span>
                            <span class="info-value"><?php echo $profile['emergency_contact_name'] ?: '<span class="empty-field">Chưa cập nhật</span>'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Số điện thoại:</span>
                            <span class="info-value"><?php echo $profile['emergency_contact_phone'] ?: '<span class="empty-field">Chưa cập nhật</span>'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Mối quan hệ:</span>
                            <span class="info-value"><?php echo $profile['emergency_contact_relationship'] ?: '<span class="empty-field">Chưa cập nhật</span>'; ?></span>
                        </div>
                    </div>

                    <!-- Hồ sơ y tế -->
                    <div class="info-section">
                        <h6><i class="bi bi-heart-pulse me-2"></i>Hồ sơ y tế</h6>
                        <div class="info-row">
                            <span class="info-label">Nhóm máu:</span>
                            <span class="info-value">
                                <?php if ($profile['blood_type']): ?>
                                    <span class="tag bg-danger text-white" style="background: #dc3545 !important;"><?php echo $profile['blood_type']; ?></span>
                                <?php else: ?>
                                    <span class="empty-field">Chưa cập nhật</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Dị ứng:</span>
                            <span class="info-value"><?php echo $profile['allergies'] ?: '<span class="empty-field">Không có</span>'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Bệnh mãn tính:</span>
                            <span class="info-value"><?php echo $profile['chronic_diseases'] ?: '<span class="empty-field">Không có</span>'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Thuốc đang dùng:</span>
                            <span class="info-value"><?php echo $profile['medications'] ?: '<span class="empty-field">Không có</span>'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tiền sử bệnh án:</span>
                            <span class="info-value"><?php echo $profile['medical_history'] ?: '<span class="empty-field">Không có</span>'; ?></span>
                        </div>
                    </div>

                    <!-- Thói quen -->
                    <div class="info-section">
                        <h6><i class="bi bi-activity me-2"></i>Thói quen sinh hoạt</h6>
                        <div class="info-row">
                            <span class="info-label">Hút thuốc:</span>
                            <span class="info-value">
                                <?php if ($profile['smoking']): ?>
                                    <span class="badge-custom badge-danger">Có</span>
                                <?php else: ?>
                                    <span class="badge-custom badge-success">Không</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Uống rượu/bia:</span>
                            <span class="info-value">
                                <?php if ($profile['drinking_alcohol']): ?>
                                    <span class="badge-custom badge-danger">Có</span>
                                <?php else: ?>
                                    <span class="badge-custom badge-success">Không</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tần suất vận động:</span>
                            <span class="info-value">
                                <?php 
                                $exercise = [
                                    'none' => 'Không vận động',
                                    'rare' => 'Hiếm khi',
                                    'weekly' => '1-2 lần/tuần',
                                    'frequent' => '3-4 lần/tuần',
                                    'daily' => 'Hàng ngày'
                                ];
                                echo $exercise[$profile['exercise_frequency']] ?? '<span class="empty-field">Chưa cập nhật</span>';
                                ?>
                            </span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="text-center pt-3">
                        <a href="<?php echo $base_url; ?>/views/patient/profile.php" class="btn btn-outline-primary btn-lg px-4 me-2">
                            <i class="bi bi-pencil me-2"></i>Chỉnh sửa
                        </a>
                        <a href="<?php echo $base_url; ?>/doctors.php" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-calendar-plus me-2"></i>Đặt khám ngay
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>