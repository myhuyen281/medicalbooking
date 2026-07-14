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

try {
    $db->query("CREATE TABLE IF NOT EXISTS patient_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        date_of_birth DATE NULL,
        gender VARCHAR(20) NULL,
        identity_card VARCHAR(50) DEFAULT '',
        insurance_number VARCHAR(50) DEFAULT '',
        province VARCHAR(100) DEFAULT '',
        district VARCHAR(100) DEFAULT '',
        ward VARCHAR(100) DEFAULT '',
        address_detail VARCHAR(255) DEFAULT '',
        emergency_contact_name VARCHAR(150) DEFAULT '',
        emergency_contact_phone VARCHAR(30) DEFAULT '',
        emergency_contact_relationship VARCHAR(50) DEFAULT '',
        blood_type VARCHAR(10) NULL,
        allergies TEXT NULL,
        chronic_diseases TEXT NULL,
        medications TEXT NULL,
        medical_history TEXT NULL,
        smoking TINYINT(1) DEFAULT 0,
        drinking_alcohol TINYINT(1) DEFAULT 0,
        exercise_frequency VARCHAR(50) NULL,
        avatar_url VARCHAR(500) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $db->execute();
    $db->query("SHOW COLUMNS FROM patient_profiles LIKE 'avatar_url'");
    if (!$db->single()) {
        $db->query("ALTER TABLE patient_profiles ADD COLUMN avatar_url VARCHAR(500) NULL AFTER exercise_frequency");
        $db->execute();
    }
} catch (Exception $e) {
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = [
        'date_of_birth' => $_POST['date_of_birth'] ?: null,
        'gender' => $_POST['gender'] ?: null,
        'identity_card' => trim($_POST['identity_card'] ?: ''),
        'insurance_number' => trim($_POST['insurance_number'] ?: ''),
        'province' => trim($_POST['province'] ?: ''),
        'district' => trim($_POST['district'] ?: ''),
        'ward' => trim($_POST['ward'] ?: ''),
        'address_detail' => trim($_POST['address_detail'] ?: ''),
        'emergency_contact_name' => trim($_POST['emergency_contact_name'] ?: ''),
        'emergency_contact_phone' => trim($_POST['emergency_contact_phone'] ?: ''),
        'emergency_contact_relationship' => trim($_POST['emergency_contact_relationship'] ?: ''),
        'blood_type' => $_POST['blood_type'] ?: null,
        'allergies' => trim($_POST['allergies'] ?: ''),
        'chronic_diseases' => trim($_POST['chronic_diseases'] ?: ''),
        'medications' => trim($_POST['medications'] ?: ''),
        'medical_history' => trim($_POST['medical_history'] ?: ''),
        'smoking' => isset($_POST['smoking']) ? 1 : 0,
        'drinking_alcohol' => isset($_POST['drinking_alcohol']) ? 1 : 0,
        'exercise_frequency' => $_POST['exercise_frequency'] ?: null,
        'user_id' => $userId,
    ];
    
    // Check if profile exists
    $db->query("SELECT id, avatar_url FROM patient_profiles WHERE user_id = :uid");
    $db->bind(':uid', $userId);
    $existing = $db->single();
    $data['avatar_url'] = $existing['avatar_url'] ?? null;

    if (!empty($_FILES['avatar_file']['name'])) {
        $file = $_FILES['avatar_file'];
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mimeType = is_uploaded_file($file['tmp_name'] ?? '') ? mime_content_type($file['tmp_name']) : '';

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > 2 * 1024 * 1024 || !isset($allowedTypes[$mimeType])) {
            $error = "Ảnh đại diện phải là JPG, PNG hoặc WebP và không vượt quá 2MB.";
        } else {
            $uploadDir = __DIR__ . '/../../uploads/patients/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = 'patient_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mimeType];
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                $data['avatar_url'] = 'uploads/patients/' . $fileName;
            } else {
                $error = "Không thể lưu ảnh đại diện.";
            }
        }
    }
    
    if (!isset($error) && $existing) {
        // Update
        $sql = "UPDATE patient_profiles SET 
                date_of_birth = :date_of_birth,
                gender = :gender,
                identity_card = :identity_card,
                insurance_number = :insurance_number,
                province = :province,
                district = :district,
                ward = :ward,
                address_detail = :address_detail,
                emergency_contact_name = :emergency_contact_name,
                emergency_contact_phone = :emergency_contact_phone,
                emergency_contact_relationship = :emergency_contact_relationship,
                blood_type = :blood_type,
                allergies = :allergies,
                chronic_diseases = :chronic_diseases,
                medications = :medications,
                medical_history = :medical_history,
                smoking = :smoking,
                drinking_alcohol = :drinking_alcohol,
                exercise_frequency = :exercise_frequency,
                avatar_url = :avatar_url
                WHERE user_id = :user_id";
    } elseif (!isset($error)) {
        // Insert
        $sql = "INSERT INTO patient_profiles 
                (user_id, date_of_birth, gender, identity_card, insurance_number, 
                 province, district, ward, address_detail, 
                 emergency_contact_name, emergency_contact_phone, emergency_contact_relationship,
                 blood_type, allergies, chronic_diseases, medications, medical_history,
                  smoking, drinking_alcohol, exercise_frequency, avatar_url)
                VALUES (:user_id, :date_of_birth, :gender, :identity_card, :insurance_number,
                        :province, :district, :ward, :address_detail,
                        :emergency_contact_name, :emergency_contact_phone, :emergency_contact_relationship,
                        :blood_type, :allergies, :chronic_diseases, :medications, :medical_history,
                        :smoking, :drinking_alcohol, :exercise_frequency, :avatar_url)";
    }
    
    if (!isset($error)) {
        $db->query($sql);
        foreach ($data as $key => $value) {
            $db->bind(':' . $key, $value);
        }
        $db->execute();
        $success = "Hồ sơ đã được lưu thành công!";
    }
}

// Fetch user info
$db->query("SELECT full_name, email, phone FROM users WHERE id = :id");
$db->bind(':id', $userId);
$user = $db->single();

// Fetch patient profile
$db->query("SELECT * FROM patient_profiles WHERE user_id = :uid");
$db->bind(':uid', $userId);
$profile = $db->single() ?: [];
include '../../includes/header.php';
?>
<style>
        .profile-page {
            width: 100%;
            max-width: 805px;
            margin: 0 auto;
            padding-bottom: 24px;
        }
        
        /* Header */
        .profile-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            padding: 30px 20px 50px;
            text-align: center;
            position: relative;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            color: #4facfe;
            margin: 0 auto 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .profile-name {
            font-size: 24px;
            font-weight: bold;
            color: white;
            margin-bottom: 5px;
        }
        
        .profile-info {
            font-size: 14px;
            color: rgba(255,255,255,0.9);
        }
        
        .edit-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            cursor: pointer;
        }
        
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.2);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            cursor: pointer;
        }
        
        /* Stats Cards */
        .stats-container {
            display: flex;
            gap: 15px;
            padding: 0 20px;
            margin-top: -30px;
        }
        
        .stat-card {
            flex: 1;
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #4facfe;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 13px;
            color: #666;
        }
        
        /* Menu Section */
        .menu-section {
            display: none;
        }
        
        .menu-title {
            font-size: 18px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 15px;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            padding: 18px 20px;
            background: white;
            border-radius: 12px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-decoration: none;
            color: #1a1a1a;
        }
        
        .menu-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .menu-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 15px;
        }
        
        .menu-icon.blue { background: #e3f2fd; color: #2196f3; }
        .menu-icon.green { background: #e8f5e9; color: #4caf50; }
        .menu-icon.orange { background: #fff3e0; color: #ff9800; }
        .menu-icon.purple { background: #f3e5f5; color: #9c27b0; }
        .menu-icon.red { background: #ffebee; color: #f44336; }
        
        .menu-content {
            flex: 1;
        }
        
        .menu-label {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .menu-desc {
            font-size: 13px;
            color: #888;
        }
        
        .menu-arrow {
            color: #ccc;
            font-size: 20px;
        }
        
        /* Form Modal */
        .form-section {
            background: transparent;
            border-radius: 0;
            padding: 0;
            margin-bottom: 24px;
            box-shadow: none;
        }
        
        .form-section h6 {
            font-size: 24px;
            font-weight: 800;
            color: #023f6d;
            margin: 24px 0 18px;
            padding-bottom: 0;
            border-bottom: 1px solid #cfdce5;
        }
        
        .form-label {
            font-size: 20px;
            font-weight: 800;
            color: #023f6d;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            font-size: 16px;
            border-radius: 10px;
            padding: 14px 16px;
            border: 1px solid #d7dce3;
            min-height: 50px;
        }
        
        .form-control:focus {
            border-color: #4facfe;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.15);
        }
        
        .btn-save {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
            border-radius: 12px;
            padding: 16px 40px;
            font-weight: bold;
            color: white;
            font-size: 16px;
            width: 100%;
            margin-top: 10px;
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 24px;
        }
        #profileForm {
            width: 100%;
        }
        .profile-header, .stats-container, .menu-section {
            display: none !important;
        }
        .btn-save {
            width: 120px;
            border-radius: 8px;
            padding: 12px 24px;
            margin-top: 0;
        }
</style>
<div class="profile-page">

            <!-- Profile Header -->
            <div class="profile-header">
                <a href="<?php echo $base_url; ?>/views/patient/dashboard.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <button class="edit-btn">
                    <i class="bi bi-pencil"></i>
                </button>
                <div class="profile-avatar" style="overflow: hidden;">
                    <?php if (!empty($profile['avatar_url'])): ?>
                        <img src="<?php echo htmlspecialchars($base_url . '/' . ltrim($profile['avatar_url'], '/')); ?>" alt="<?php echo htmlspecialchars($user['full_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <?php echo htmlspecialchars(strtoupper(substr($user['full_name'], 0, 1))); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                <div class="profile-info">
                    <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($user['email']); ?>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-value">
                        <?php 
                        $db->query("SELECT COUNT(*) as total FROM appointments WHERE patient_id = :pid AND status IN ('confirmed', 'completed')");
                        $db->bind(':pid', $userId);
                        $stat = $db->single();
                        echo $stat['total'] ?? 0;
                        ?>
                    </div>
                    <div class="stat-label">Lịch khám</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">Toa thuốc</div>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Menu Section -->
            <div class="menu-section">
                <div class="menu-title">Thông tin cá nhân</div>
                
                <a href="#info-form" class="menu-item" data-bs-toggle="collapse">
                    <div class="menu-icon blue">
                        <i class="bi bi-person"></i>
                    </div>
                    <div class="menu-content">
                        <div class="menu-label">Thông tin cơ bản</div>
                        <div class="menu-desc">Cập nhật thông tin cá nhân</div>
                    </div>
                    <i class="bi bi-chevron-down menu-arrow"></i>
                </a>

                <a href="#address-form" class="menu-item" data-bs-toggle="collapse">
                    <div class="menu-icon green">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <div class="menu-content">
                        <div class="menu-label">Địa chỉ liên hệ</div>
                        <div class="menu-desc">Cập nhật địa chỉ của bạn</div>
                    </div>
                    <i class="bi bi-chevron-down menu-arrow"></i>
                </a>

                <a href="#emergency-form" class="menu-item" data-bs-toggle="collapse">
                    <div class="menu-icon orange">
                        <i class="bi bi-telephone"></i>
                    </div>
                    <div class="menu-content">
                        <div class="menu-label">Người liên hệ khẩn cấp</div>
                        <div class="menu-desc">Cập nhật thông tin người thân</div>
                    </div>
                    <i class="bi bi-chevron-down menu-arrow"></i>
                </a>

                <div class="menu-title mt-4">Hồ sơ y tế</div>

                <a href="#medical-form" class="menu-item" data-bs-toggle="collapse">
                    <div class="menu-icon purple">
                        <i class="bi bi-heart-pulse"></i>
                    </div>
                    <div class="menu-content">
                        <div class="menu-label">Thông tin y tế</div>
                        <div class="menu-desc">Nhóm máu, dị ứng, bệnh mãn tính</div>
                    </div>
                    <i class="bi bi-chevron-down menu-arrow"></i>
                </a>

                <a href="#habit-form" class="menu-item" data-bs-toggle="collapse">
                    <div class="menu-icon red">
                        <i class="bi bi-activity"></i>
                    </div>
                    <div class="menu-content">
                        <div class="menu-label">Thói quen sinh hoạt</div>
                        <div class="menu-desc">Vận động, hút thuốc, uống rượu</div>
                    </div>
                    <i class="bi bi-chevron-down menu-arrow"></i>
                </a>
            </div>

            <form method="POST" action="" id="profileForm" enctype="multipart/form-data">
                    <!-- Thông tin cơ bản -->
                    <div class="form-section collapse show" id="info-form">
                        <h6><i class="bi bi-person me-2"></i>Thông tin cơ bản</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Ảnh đại diện</label>
                                <input type="file" name="avatar_file" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">JPG, PNG hoặc WebP, tối đa 2MB.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Họ và tên</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Ngày sinh</label>
                                <input type="date" name="date_of_birth" class="form-control" value="<?php echo $profile['date_of_birth'] ?? ''; ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Giới tính</label>
                                <select name="gender" class="form-select">
                                    <option value="">-- Chọn --</option>
                                    <option value="male" <?php echo ($profile['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Nam</option>
                                    <option value="female" <?php echo ($profile['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Nữ</option>
                                    <option value="other" <?php echo ($profile['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Khác</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Số điện thoại</label>
                                <input type="tel" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label">CMND/CCCD</label>
                                <input type="text" name="identity_card" class="form-control" value="<?php echo htmlspecialchars($profile['identity_card'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Số thẻ BHYT</label>
                                <input type="text" name="insurance_number" class="form-control" value="<?php echo htmlspecialchars($profile['insurance_number'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Địa chỉ -->
                    <div class="form-section collapse show" id="address-form">
                        <h6><i class="bi bi-geo-alt me-2"></i>Địa chỉ liên hệ</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Tỉnh/Thành phố</label>
                                <input type="text" name="province" class="form-control" value="<?php echo htmlspecialchars($profile['province'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Quận/Huyện</label>
                                <input type="text" name="district" class="form-control" value="<?php echo htmlspecialchars($profile['district'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Phường/Xã</label>
                                <input type="text" name="ward" class="form-control" value="<?php echo htmlspecialchars($profile['ward'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Địa chỉ chi tiết</label>
                                <textarea name="address_detail" class="form-control" rows="2"><?php echo htmlspecialchars($profile['address_detail'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Người liên hệ khẩn cấp -->
                    <div class="form-section collapse show d-none" id="emergency-form">
                        <h6><i class="bi bi-telephone me-2"></i>Người liên hệ khẩn cấp</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Họ tên</label>
                                <input type="text" name="emergency_contact_name" class="form-control" value="<?php echo htmlspecialchars($profile['emergency_contact_name'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Số điện thoại</label>
                                <input type="tel" name="emergency_contact_phone" class="form-control" value="<?php echo htmlspecialchars($profile['emergency_contact_phone'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Mối quan hệ</label>
                                <input type="text" name="emergency_contact_relationship" class="form-control" placeholder="Vợ/Chồng, Con, Cha/Mẹ..." value="<?php echo htmlspecialchars($profile['emergency_contact_relationship'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Hồ sơ y tế -->
                    <div class="form-section collapse" id="medical-form">
                        <h6><i class="bi bi-heart-pulse me-2"></i>Hồ sơ y tế</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nhóm máu</label>
                                <select name="blood_type" class="form-select">
                                    <option value="">-- Chọn --</option>
                                    <option value="A+" <?php echo ($profile['blood_type'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo ($profile['blood_type'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo ($profile['blood_type'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo ($profile['blood_type'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                                    <option value="AB+" <?php echo ($profile['blood_type'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo ($profile['blood_type'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                    <option value="O+" <?php echo ($profile['blood_type'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo ($profile['blood_type'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Dị ứng (thuốc, thực phẩm...)</label>
                                <textarea name="allergies" class="form-control" rows="2" placeholder="Liệt kê các dị ứng nếu có..."><?php echo htmlspecialchars($profile['allergies'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Bệnh mãn tính</label>
                                <textarea name="chronic_diseases" class="form-control" rows="2" placeholder="Tiểu đường, Cao huyết áp..."><?php echo htmlspecialchars($profile['chronic_diseases'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Thuốc đang sử dụng</label>
                                <textarea name="medications" class="form-control" rows="2" placeholder="Liệt kê thuốc đang dùng..."><?php echo htmlspecialchars($profile['medications'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Tiền sử bệnh án</label>
                                <textarea name="medical_history" class="form-control" rows="3" placeholder="Các bệnh đã mắc, phẫu thuật..."><?php echo htmlspecialchars($profile['medical_history'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Thói quen sinh hoạt -->
                    <div class="form-section collapse" id="habit-form">
                        <h6><i class="bi bi-activity me-2"></i>Thói quen sinh hoạt</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="smoking" id="smoking" <?php echo ($profile['smoking'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="smoking">Hút thuốc lá</label>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="drinking_alcohol" id="drinking" <?php echo ($profile['drinking_alcohol'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="drinking">Uống rượu/bia</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Tần suất vận động</label>
                                <select name="exercise_frequency" class="form-select">
                                    <option value="">-- Chọn --</option>
                                    <option value="none" <?php echo ($profile['exercise_frequency'] ?? '') === 'none' ? 'selected' : ''; ?>>Không vận động</option>
                                    <option value="rare" <?php echo ($profile['exercise_frequency'] ?? '') === 'rare' ? 'selected' : ''; ?>>Hiếm khi</option>
                                    <option value="weekly" <?php echo ($profile['exercise_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>1-2 lần/tuần</option>
                                    <option value="frequent" <?php echo ($profile['exercise_frequency'] ?? '') === 'frequent' ? 'selected' : ''; ?>>3-4 lần/tuần</option>
                                    <option value="daily" <?php echo ($profile['exercise_frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Hàng ngày</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4 mb-5">
                        <button type="submit" class="btn btn-save">
                            <i class="bi bi-check-circle me-2"></i>Lưu thông tin
                        </button>
                    </div>
                </form>
</div>

<script>
        // Auto close other sections when opening one
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    // Close all other sections first
                    document.querySelectorAll('.form-section').forEach(section => {
                        if (section !== target && section.classList.contains('show')) {
                            section.classList.remove('show');
                        }
                    });
                }
            });
        });
</script>
<?php include '../../includes/footer.php'; ?>