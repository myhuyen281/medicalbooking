<?php
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/MEDICAILBOOKING';
$error = '';
$success = '';

function uploadHospitalRegisterImage($fieldName, $hospitalId, &$error) {
    if (empty($_FILES[$fieldName]['name'])) {
        return '';
    }

    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $tmpPath = $_FILES[$fieldName]['tmp_name'];
    $mimeType = mime_content_type($tmpPath);

    if (!isset($allowedTypes[$mimeType])) {
        $error = "Chỉ cho phép upload ảnh JPG, PNG, WEBP hoặc GIF.";
        return false;
    }

    if ($_FILES[$fieldName]['size'] > 5 * 1024 * 1024) {
        $error = "Dung lượng ảnh không được vượt quá 5MB.";
        return false;
    }

    $uploadDir = __DIR__ . '/../../uploads/hospitals/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = $hospitalId . '_' . $fieldName . '_' . time() . '.' . $allowedTypes[$mimeType];
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        $error = "Không thể upload ảnh.";
        return false;
    }

    return 'uploads/hospitals/' . $fileName;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $facilityCode = trim($_POST['facility_code']);
    $hospitalName = trim($_POST['hospital_name']);
    $facilityType = trim($_POST['facility_type']);
    $allowedFacilityTypes = ['public', 'private', 'clinic', 'office', 'lab', 'home', 'vaccination'];
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $workingTime = '';
    $description = '';
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($facilityCode) || empty($hospitalName) || empty($facilityType) || empty($email) || empty($phone) || empty($password) || empty($confirmPassword)) {
        $error = "Vui lòng nhập đầy đủ thông tin bắt buộc.";
    } elseif (!in_array($facilityType, $allowedFacilityTypes, true)) {
        $error = "Loại cơ sở y tế không hợp lệ.";
    } elseif ($password !== $confirmPassword) {
        $error = "Mật khẩu nhập lại không khớp.";
    } else {
        $db = new Database();
        try {
            $db->query("ALTER TABLE hospitals ADD COLUMN facility_type VARCHAR(30) NOT NULL DEFAULT 'public' AFTER facility_code");
            $db->execute();
        } catch (Exception $e) {
        }
        $db->query("SELECT id FROM users WHERE email = :email OR phone = :phone");
        $db->bind(':email', $email);
        $db->bind(':phone', $phone);
        $db->execute();

        if ($db->rowCount() > 0) {
            $error = "Email hoặc số điện thoại đã tồn tại.";
        } else {
            $db->query("SELECT id FROM hospitals WHERE facility_code = :facility_code");
            $db->bind(':facility_code', $facilityCode);
            $db->execute();

            if ($db->rowCount() > 0) {
                $error = "Mã cơ sở khám chữa bệnh đã tồn tại.";
            } else {
            $db->query("INSERT INTO hospitals (facility_code, name, facility_type, address, phone, email, working_time, description) VALUES (:facility_code, :name, :facility_type, :address, :phone, :email, :working_time, :description)");
            $db->bind(':facility_code', $facilityCode);
            $db->bind(':name', $hospitalName);
            $db->bind(':facility_type', $facilityType);
            $db->bind(':address', $address);
            $db->bind(':phone', $phone);
            $db->bind(':email', $email);
            $db->bind(':working_time', $workingTime);
            $db->bind(':description', $description);
            $db->execute();

            $db->query("SELECT id FROM hospitals WHERE email = :email ORDER BY id DESC LIMIT 1");
            $db->bind(':email', $email);
            $hospital = $db->single();

            $db->query("INSERT INTO users (full_name, email, phone, password, role, hospital_id, hospital_approval_status, address) VALUES (:name, :email, :phone, :password, 'hospital', :hospital_id, 'pending', :address)");
            $db->bind(':name', $hospitalName);
            $db->bind(':email', $email);
            $db->bind(':phone', $phone);
            $db->bind(':password', password_hash($password, PASSWORD_DEFAULT));
            $db->bind(':hospital_id', $hospital['id']);
            $db->bind(':address', $address);
            $success = $db->execute() ? "Đăng ký bệnh viện thành công. Vui lòng chờ Admin duyệt tài khoản. Hệ thống sẽ chuyển về trang chủ sau vài giây." : "Không thể tạo tài khoản bệnh viện.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký đối tác cơ sở y tế - MedicailBooking</title>
    <?php if ($success): ?>
        <meta http-equiv="refresh" content="3;url=<?php echo $base_url; ?>/index.php">
    <?php endif; ?>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Google Font Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #023f6d 0%, #00b5f1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            overflow-x: hidden;
            position: relative;
        }

        /* Decorative background elements */
        .bg-circle {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
            z-index: 0;
            pointer-events: none;
        }
        .bg-circle-1 {
            width: 500px;
            height: 500px;
            top: -150px;
            right: -100px;
        }
        .bg-circle-2 {
            width: 600px;
            height: 600px;
            bottom: -200px;
            left: -150px;
        }

        /* Glassmorphic container wrapper */
        .register-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 780px;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 20px 40px rgba(2, 63, 109, 0.25);
            padding: 45px 40px;
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.65s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        @keyframes slideUp {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .brand-logo {
            font-size: 1.85rem;
            font-weight: 800;
            color: #023f6d;
            letter-spacing: -1px;
            text-transform: uppercase;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.3s ease;
        }
        .brand-logo:hover {
            transform: scale(1.03);
            color: #023f6d;
        }
        .brand-logo span {
            color: #00b5f1;
        }

        .register-subtitle {
            color: #64748b;
            font-size: 0.925rem;
            font-weight: 500;
        }

        /* Custom Form Controls */
        .form-label {
            font-weight: 750;
            color: #334155;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        
        .input-group-custom {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group-custom .form-control,
        .input-group-custom .form-select {
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            padding: 12px 16px 12px 46px;
            font-size: 0.95rem;
            color: #1e293b;
            transition: all 0.25s ease;
            background-color: #f8fafc;
            width: 100%;
        }

        .input-group-custom .form-control:focus,
        .input-group-custom .form-select:focus {
            border-color: #00b5f1;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(0, 181, 241, 0.12);
            outline: none;
        }

        .input-group-custom .input-icon {
            position: absolute;
            left: 16px;
            color: #94a3b8;
            font-size: 1.2rem;
            transition: color 0.25s ease;
            pointer-events: none;
            z-index: 5;
        }

        .input-group-custom .form-control:focus ~ .input-icon,
        .input-group-custom .form-select:focus ~ .input-icon {
            color: #00b5f1;
        }

        /* Action Buttons */
        .btn-submit {
            background: linear-gradient(135deg, #023f6d 0%, #00b5f1 100%);
            border: none;
            border-radius: 12px;
            padding: 14px 28px;
            font-size: 1rem;
            font-weight: 700;
            color: #ffffff;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(2, 63, 109, 0.2);
            position: relative;
            overflow: hidden;
        }
        .btn-submit::after {
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
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 181, 241, 0.35);
        }
        .btn-submit:hover::after {
            left: 150%;
        }
        .btn-submit:active {
            transform: translateY(0);
        }

        /* Custom Alert styling */
        .alert-custom-error {
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid #fee2e2;
            background-color: #fef2f2;
            color: #dc2626;
            padding: 12px 16px;
        }
        .alert-custom-success {
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid #d1fae5;
            background-color: #ecfdf5;
            color: #059669;
            padding: 12px 16px;
        }

        /* Footer styling */
        .auth-footer {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
        }
        .auth-footer a {
            color: #00b5f1;
            text-decoration: none;
            font-weight: 700;
            transition: color 0.2s ease;
        }
        .auth-footer a:hover {
            color: #023f6d;
            text-decoration: underline;
        }

        .back-to-home {
            position: absolute;
            top: 25px;
            left: 25px;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.25s ease;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .back-to-home:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-3px);
        }
    </style>
</head>
<body>

    <!-- Back to home button -->
    <a href="../../index.php" class="back-to-home">
        <i class="bi bi-arrow-left"></i> Quay lại trang chủ
    </a>

    <!-- Decorative shapes -->
    <div class="bg-circle bg-circle-1"></div>
    <div class="bg-circle bg-circle-2"></div>

    <!-- Main Container -->
    <div class="register-container">
        <!-- Logo / Title -->
        <div class="text-center mb-5">
            <a href="../../index.php" class="brand-logo mb-2">
                Medi<span>cailBooking</span>
            </a>
            <div class="register-subtitle">Hệ thống liên kết và hợp tác cơ sở y tế toàn diện</div>
            <h4 class="mt-4 fw-bold text-dark" style="color: #023f6d !important;">Đăng ký tài khoản Cơ sở y tế</h4>
        </div>

        <!-- Alert messages -->
        <?php if ($error): ?>
            <div class="alert alert-custom-error mb-4 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-custom-success mb-4 d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div><?php echo htmlspecialchars($success); ?></div>
            </div>
        <?php endif; ?>

        <!-- Form elements -->
        <form method="POST" action="">
            <div class="row g-4">
                <!-- Mã Cơ Sở -->
                <div class="col-md-6">
                    <label class="form-label">Mã cơ sở khám chữa bệnh <span class="text-danger">*</span></label>
                    <div class="input-group-custom">
                        <input type="text" name="facility_code" class="form-control" placeholder="Ví dụ: BV-10023" required value="<?php echo htmlspecialchars($_POST['facility_code'] ?? ''); ?>">
                        <i class="bi bi-patch-check input-icon"></i>
                    </div>
                </div>

                <!-- Tên Cơ Sở -->
                <div class="col-md-6">
                    <label class="form-label">Tên cơ sở y tế <span class="text-danger">*</span></label>
                    <div class="input-group-custom">
                        <input type="text" name="hospital_name" class="form-control" placeholder="Ví dụ: Bệnh viện Đa khoa Cần Thơ" required value="<?php echo htmlspecialchars($_POST['hospital_name'] ?? ''); ?>">
                        <i class="bi bi-building input-icon"></i>
                    </div>
                </div>

                <!-- Loại Hình Cơ Sở -->
                <div class="col-md-6">
                    <label class="form-label">Loại hình cơ sở <span class="text-danger">*</span></label>
                    <div class="input-group-custom">
                        <select name="facility_type" class="form-select" required>
                            <option value="">-- Chọn loại hình --</option>
                            <option value="public" <?php echo (($_POST['facility_type'] ?? '') === 'public') ? 'selected' : ''; ?>>Bệnh viện công</option>
                            <option value="private" <?php echo (($_POST['facility_type'] ?? '') === 'private') ? 'selected' : ''; ?>>Bệnh viện tư</option>
                            <option value="clinic" <?php echo (($_POST['facility_type'] ?? '') === 'clinic') ? 'selected' : ''; ?>>Phòng khám</option>
                            <option value="office" <?php echo (($_POST['facility_type'] ?? '') === 'office') ? 'selected' : ''; ?>>Phòng mạch</option>
                            <option value="lab" <?php echo (($_POST['facility_type'] ?? '') === 'lab') ? 'selected' : ''; ?>>Xét nghiệm</option>
                            <option value="home" <?php echo (($_POST['facility_type'] ?? '') === 'home') ? 'selected' : ''; ?>>Y tế tại nhà</option>
                            <option value="vaccination" <?php echo (($_POST['facility_type'] ?? '') === 'vaccination') ? 'selected' : ''; ?>>Tiêm chủng</option>
                        </select>
                        <i class="bi bi-hospital input-icon"></i>
                    </div>
                </div>

                <!-- Địa Chỉ -->
                <div class="col-md-6">
                    <label class="form-label">Địa chỉ hiển thị</label>
                    <div class="input-group-custom">
                        <input type="text" name="address" class="form-control" placeholder="Số, đường, phường/xã, quận/huyện..." value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                        <i class="bi bi-geo-alt input-icon"></i>
                    </div>
                </div>

                <!-- Email -->
                <div class="col-md-6">
                    <label class="form-label">Email liên hệ <span class="text-danger">*</span></label>
                    <div class="input-group-custom">
                        <input type="email" name="email" class="form-control" placeholder="hospital@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        <i class="bi bi-envelope input-icon"></i>
                    </div>
                </div>

                <!-- Số Điện Thoại -->
                <div class="col-md-6">
                    <label class="form-label">Số điện thoại liên hệ <span class="text-danger">*</span></label>
                    <div class="input-group-custom">
                        <input type="text" name="phone" class="form-control" placeholder="Số điện thoại bàn hoặc di động" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        <i class="bi bi-telephone input-icon"></i>
                    </div>
                </div>

                <!-- Mật Khẩu -->
                <div class="col-md-6">
                    <label class="form-label">Mật khẩu tài khoản <span class="text-danger">*</span></label>
                    <div class="input-group-custom">
                        <input type="password" name="password" class="form-control" placeholder="Mật khẩu tối thiểu 6 ký tự" required>
                        <i class="bi bi-shield-lock input-icon"></i>
                    </div>
                </div>

                <!-- Xác Nhận Mật Khẩu -->
                <div class="col-md-6">
                    <label class="form-label">Nhập lại mật khẩu <span class="text-danger">*</span></label>
                    <div class="input-group-custom">
                        <input type="password" name="confirm_password" class="form-control" placeholder="Xác nhận lại mật khẩu" required>
                        <i class="bi bi-shield-check input-icon"></i>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-submit w-100 mt-5 mb-3">Gửi thông tin đăng ký đối tác</button>
        </form>

        <!-- Redirect links -->
        <div class="text-center auth-footer mt-4">
            <div>Đã có tài khoản đối tác? <a href="login.php" style="color: #023f6d;">Đăng nhập tại đây</a></div>
            <div class="mt-2 text-muted" style="font-size: 0.8rem;">
                Bạn muốn đặt lịch khám? <a href="register.php">Đăng ký tài khoản bệnh nhân</a>
            </div>
        </div>
    </div>

</body>
</html>
