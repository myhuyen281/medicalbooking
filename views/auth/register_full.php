<?php
require_once '../../config/database.php';
require_once '../../includes/otp_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/MEDICAILBOOKING';
$error = '';
$phone = normalizeVietnamPhone($_GET['phone'] ?? $_POST['phone'] ?? '');
$verifiedPhone = $_SESSION['registration_verified_phone'] ?? '';

if (!$phone || $verifiedPhone !== $phone) {
    header("Location: register.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($fullName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = "Vui lòng nhập đầy đủ thông tin.";
    } elseif (!isValidEmail($email)) {
        $error = "Email không hợp lệ.";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } elseif ($password !== $confirmPassword) {
        $error = "Mật khẩu xác nhận không khớp.";
    } else {
        $db = new Database();
        $db->query("SELECT id FROM users WHERE email = :email OR phone = :phone");
        $db->bind(':email', $email);
        $db->bind(':phone', $phone);
        $db->execute();

        if ($db->rowCount() > 0) {
            $error = "Email hoặc số điện thoại đã tồn tại.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $db->query("INSERT INTO users (full_name, email, phone, password, role) VALUES (:full_name, :email, :phone, :password, 'patient')");
            $db->bind(':full_name', $fullName);
            $db->bind(':email', $email);
            $db->bind(':phone', $phone);
            $db->bind(':password', $hashedPassword);
            $db->execute();

            $userId = $db->lastInsertId();
            clearRegistrationOtp();
            unset($_SESSION['registration_verified_phone']);

            $_SESSION['user_id'] = $userId;
            $_SESSION['full_name'] = $fullName;
            $_SESSION['role'] = 'patient';
            $_SESSION['hospital_id'] = null;

            header("Location: ../../index.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hoàn tất đăng ký - MedicailBooking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #023f6d 0%, #00b5f1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .register-card {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 24px;
            padding: 36px;
            box-shadow: 0 20px 40px rgba(2, 63, 109, 0.25);
        }
        .brand {
            color: #023f6d;
            font-size: 1.9rem;
            font-weight: 800;
            letter-spacing: -1px;
            text-transform: uppercase;
            text-align: center;
        }
        .brand span {
            color: #00b5f1;
        }
        .form-control {
            height: 52px;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            background: #f8fafc;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #00b5f1;
            box-shadow: 0 0 0 4px rgba(0, 181, 241, 0.12);
        }
        .submit-btn {
            height: 54px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #023f6d 0%, #00b5f1 100%);
            color: #fff;
            font-weight: 800;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="brand">Medi<span>cailBooking</span></div>
        <h1 class="h4 fw-bold text-center mt-3 mb-2">Hoàn tất đăng ký</h1>
        <p class="text-muted text-center mb-4">Số điện thoại đã xác thực: <strong><?php echo htmlspecialchars($phone); ?></strong></p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 fw-semibold"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
            <div class="mb-3">
                <label class="form-label fw-bold">Họ và tên</label>
                <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Email</label>
                <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Mật khẩu</label>
                <input type="password" name="password" class="form-control" required minlength="6">
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">Nhập lại mật khẩu</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="6">
            </div>
            <button type="submit" class="submit-btn">Tạo tài khoản</button>
        </form>
    </div>
</body>
</html>
