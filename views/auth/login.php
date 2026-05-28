<?php
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu đã đăng nhập thì chuyển về trang chủ
if (isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Vui lòng nhập Email và Mật khẩu.";
    } else {
        $db = new Database();
        $db->query("SELECT * FROM users WHERE email = :email");
        $db->bind(':email', $email);
        $user = $db->single();

        if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
            if ($user['role'] === 'hospital' && ($user['hospital_approval_status'] ?? 'pending') !== 'approved') {
                $error = "Tài khoản bệnh viện đang chờ Admin duyệt.";
            } else {
            if ($password === $user['password']) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $db->query("UPDATE users SET password = :password WHERE id = :id");
                $db->bind(':password', $hashedPassword);
                $db->bind(':id', $user['id']);
                $db->execute();
            }

            // Set sessions
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['hospital_id'] = $user['hospital_id'] ?? null;
            
            if ($user['role'] == 'admin' || $user['role'] == 'hospital') {
                header("Location: ../admin/dashboard.php");
            } else {
                header("Location: ../../index.php");
            }
            exit();
            }
        } else {
            $error = "Email hoặc mật khẩu không chính xác.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - MedicailBooking</title>
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
            padding: 20px;
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
            top: -200px;
            right: -100px;
        }
        .bg-circle-2 {
            width: 600px;
            height: 600px;
            bottom: -250px;
            left: -150px;
        }

        /* Glassmorphic container wrapper */
        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 20px 40px rgba(2, 63, 109, 0.25);
            padding: 40px 35px;
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

        .login-subtitle {
            color: #64748b;
            font-size: 0.925rem;
            font-weight: 500;
        }

        /* Custom Form Controls */
        .form-label {
            font-weight: 700;
            color: #334155;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        
        .input-group-custom {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group-custom .form-control {
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            padding: 12px 16px 12px 46px;
            font-size: 0.95rem;
            color: #1e293b;
            transition: all 0.25s ease;
            background-color: #f8fafc;
            width: 100%;
        }

        .input-group-custom .form-control:focus {
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

        .input-group-custom .form-control:focus ~ .input-icon {
            color: #00b5f1;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.25s ease;
            font-size: 1.15rem;
            z-index: 5;
            user-select: none;
        }
        .password-toggle:hover {
            color: #64748b;
        }

        /* Action Buttons */
        .btn-submit {
            background: linear-gradient(135deg, #023f6d 0%, #00b5f1 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
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

        /* Alert tweaks */
        .alert-custom {
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid #fee2e2;
            background-color: #fef2f2;
            color: #dc2626;
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

    <!-- Main Card Container -->
    <div class="login-container">
        <!-- Logo / Title -->
        <div class="text-center mb-4">
            <a href="../../index.php" class="brand-logo mb-2">
                Medi<span>cailBooking</span>
            </a>
            <div class="login-subtitle">Hệ thống đặt lịch khám bệnh trực tuyến</div>
        </div>

        <!-- Alert messages -->
        <?php if ($error): ?>
            <div class="alert alert-custom mb-4 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>

        <!-- Form elements -->
        <form method="POST" action="">
            <!-- Email Input -->
            <div class="mb-3">
                <label class="form-label">Email đăng nhập</label>
                <div class="input-group-custom">
                    <input type="email" name="email" class="form-control" placeholder="example@gmail.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <i class="bi bi-envelope input-icon"></i>
                </div>
            </div>

            <!-- Password Input -->
            <div class="mb-4">
                <label class="form-label">Mật khẩu</label>
                <div class="input-group-custom">
                    <input type="password" name="password" id="passwordField" class="form-control" placeholder="••••••••" required>
                    <i class="bi bi-shield-lock input-icon"></i>
                    <i class="bi bi-eye password-toggle" id="passwordToggleBtn"></i>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-submit w-100 mb-3">Đăng Nhập</button>
        </form>

        <!-- Redirect links -->
        <div class="text-center auth-footer mt-4">
            <div>Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></div>
            <div class="mt-2 text-muted" style="font-size: 0.8rem;">
                Bạn là đối tác bệnh viện? <a href="register_hospital.php" style="color: #023f6d;">Đăng ký tại đây</a>
            </div>
        </div>
    </div>

    <!-- Toggle password visibility logic -->
    <script>
        const passwordField = document.getElementById('passwordField');
        const toggleBtn = document.getElementById('passwordToggleBtn');

        toggleBtn.addEventListener('click', function () {
            const isPassword = passwordField.getAttribute('type') === 'password';
            passwordField.setAttribute('type', isPassword ? 'text' : 'password');
            
            // Toggle eye icon class
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });
    </script>
</body>
</html>
