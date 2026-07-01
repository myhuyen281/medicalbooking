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
    ];
    
    // Insert new profile
    $sql = "INSERT INTO patient_profiles 
            (user_id, date_of_birth, gender, identity_card, insurance_number, 
             province, district, ward, address_detail, 
             emergency_contact_name, emergency_contact_phone, emergency_contact_relationship,
             blood_type, allergies, chronic_diseases, medications, medical_history,
             smoking, drinking_alcohol, exercise_frequency)
            VALUES (:user_id, :date_of_birth, :gender, :identity_card, :insurance_number,
                    :province, :district, :ward, :address_detail,
                    :emergency_contact_name, :emergency_contact_phone, :emergency_contact_relationship,
                    :blood_type, :allergies, :chronic_diseases, :medications, :medical_history,
                    :smoking, :drinking_alcohol, :exercise_frequency)";
    $data['user_id'] = $userId;
    
    $db->query($sql);
    foreach ($data as $key => $value) {
        $db->bind(':' . $key, $value);
    }
    $db->execute();
    
    header("Location: $base_url/views/patient/records.php");
    exit();
}

// Fetch user info
$db->query("SELECT full_name, email, phone FROM users WHERE id = :id");
$db->bind(':id', $userId);
$user = $db->single();

include '../../includes/header.php';
?>
<style>
    .patient-layout { max-width: 1200px; margin: 0 auto; }
    .patient-breadcrumb { font-size: 0.9rem; font-weight: 600; padding: 16px 0; }
    .patient-breadcrumb a { color: #023f6d; text-decoration: none; }
    .patient-breadcrumb .active { color: #00a8e8; }
    .sidebar-menu { background: #fff; border: 1px solid #eef2f7; border-radius: 14px; padding: 18px 14px; box-shadow: 0 2px 10px rgba(2,63,109,0.05); }
    .sidebar-menu a { display: block; padding: 12px 16px; color: #023f6d; text-decoration: none; font-weight: 600; border-radius: 10px; margin-bottom: 6px; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #e7f8ff; color: #00a8e8; }
    .main-content { background: #fff; border: 1px solid #eef2f7; border-radius: 14px; padding: 26px; box-shadow: 0 2px 10px rgba(2,63,109,0.05); }
    .form-section { margin-bottom: 30px; }
    .form-section h6 { color: #00a8e8; font-weight: bold; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f1f3f5; }
    .form-label { font-weight: 600; font-size: 13px; color: #495057; }
    .form-control, .form-select { border-radius: 8px; padding: 10px 14px; font-size: 14px; }
    .btn-submit { background: linear-gradient(135deg, #00d4ff 0%, #00a8e8 100%); border: none; border-radius: 8px; padding: 14px 40px; font-weight: 600; }
</style>

<div class="patient-layout">
    <nav class="patient-breadcrumb">
        <a href="<?php echo $base_url; ?>/index.php">Trang chủ</a>
        <span class="text-muted">/</span>
        <a href="<?php echo $base_url; ?>/views/patient/records.php">Hồ sơ bệnh nhân</a>
        <span class="text-muted">/</span>
        <span class="active">Thêm hồ sơ mới</span>
    </nav>

    <div class="row pb-5">
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="sidebar-menu">
                <a href="<?php echo $base_url; ?>/views/patient/profile_create.php" class="active"><i class="bi bi-file-medical me-2"></i> Hồ sơ bệnh nhân</a>
                <a href="<?php echo $base_url; ?>/views/patient/bills.php"><i class="bi bi-file-earmark-text me-2"></i> Phiếu khám bệnh</a>
                <a href="<?php echo $base_url; ?>/views/patient/notifications.php"><i class="bi bi-bell me-2"></i> Thông báo <span class="badge bg-danger ms-1">99+</span></a>
            </div>
        </div>

        <div class="col-md-9">
            <div class="main-content">
                <h5 class="fw-bold mb-4" style="color:#023f6d;">Tạo hồ sơ bệnh nhân mới</h5>

                    <form method="POST" action="">
                        <!-- Thông tin cơ bản -->
                        <div class="form-section">
                            <h6><i class="bi bi-person me-2"></i>Thông tin cơ bản</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Họ và tên</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Ngày sinh</label>
                                    <input type="date" name="date_of_birth" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Giới tính</label>
                                    <select name="gender" class="form-select" required>
                                        <option value="">-- Chọn --</option>
                                        <option value="male">Nam</option>
                                        <option value="female">Nữ</option>
                                        <option value="other">Khác</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Số điện thoại</label>
                                    <input type="tel" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">CMND/CCCD</label>
                                    <input type="text" name="identity_card" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Số thẻ BHYT</label>
                                    <input type="text" name="insurance_number" class="form-control">
                                </div>
                            </div>
                        </div>

                        <!-- Địa chỉ -->
                        <div class="form-section">
                            <h6><i class="bi bi-geo-alt me-2"></i>Địa chỉ liên hệ</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Tỉnh/Thành phố</label>
                                    <input type="text" name="province" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Quận/Huyện</label>
                                    <input type="text" name="district" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Phường/Xã</label>
                                    <input type="text" name="ward" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Địa chỉ chi tiết</label>
                                    <textarea name="address_detail" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Người liên hệ khẩn cấp -->
                        <div class="form-section">
                            <h6><i class="bi bi-telephone me-2"></i>Người liên hệ khẩn cấp</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Họ tên</label>
                                    <input type="text" name="emergency_contact_name" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Số điện thoại</label>
                                    <input type="tel" name="emergency_contact_phone" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Mối quan hệ</label>
                                    <input type="text" name="emergency_contact_relationship" class="form-control" placeholder="Vợ/Chồng, Con...">
                                </div>
                            </div>
                        </div>

                        <!-- Hồ sơ y tế -->
                        <div class="form-section">
                            <h6><i class="bi bi-heart-pulse me-2"></i>Hồ sơ y tế</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Nhóm máu</label>
                                    <select name="blood_type" class="form-select">
                                        <option value="">-- Chọn --</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                    </select>
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label">Dị ứng (thuốc, thực phẩm...)</label>
                                    <textarea name="allergies" class="form-control" rows="2" placeholder="Liệt kê các dị ứng nếu có..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Bệnh mãn tính</label>
                                    <textarea name="chronic_diseases" class="form-control" rows="2" placeholder="Tiểu đường, Cao huyết áp..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Thuốc đang sử dụng</label>
                                    <textarea name="medications" class="form-control" rows="2" placeholder="Liệt kê thuốc đang dùng..."></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Tiền sử bệnh án</label>
                                    <textarea name="medical_history" class="form-control" rows="3" placeholder="Các bệnh đã mắc, phẫu thuật..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Thói quen -->
                        <div class="form-section">
                            <h6><i class="bi bi-activity me-2"></i>Thói quen sinh hoạt</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="smoking" id="smoking">
                                        <label class="form-check-label" for="smoking">Hút thuốc lá</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="drinking_alcohol" id="drinking">
                                        <label class="form-check-label" for="drinking">Uống rượu/bia</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tần suất vận động</label>
                                    <select name="exercise_frequency" class="form-select">
                                        <option value="">-- Chọn --</option>
                                        <option value="none">Không vận động</option>
                                        <option value="rare">Hiếm khi</option>
                                        <option value="weekly">1-2 lần/tuần</option>
                                        <option value="frequent">3-4 lần/tuần</option>
                                        <option value="daily">Hàng ngày</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-submit">
                                <i class="bi bi-check-circle me-2"></i>Tạo hồ sơ
                            </button>
                            <a href="<?php echo $base_url; ?>/views/patient/records.php" class="btn btn-outline-secondary ms-2">Quay lại</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>