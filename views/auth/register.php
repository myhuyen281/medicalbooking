<?php
require_once '../../config/database.php';
require_once '../../includes/otp_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/MEDICAILBOOKING';
$error = '';
$success = '';
$phone = '';
$step = 'phone';
$testOtpCode = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? 'send_otp';

    if ($action === 'send_otp' || $action === 'resend_otp') {
        $phone = normalizeVietnamPhone($_POST['phone'] ?? '');

        if (empty($phone)) {
            $error = "Vui lòng nhập số điện thoại.";
        } elseif (!isValidVietnamMobile($phone)) {
            $error = "Số điện thoại không hợp lệ.";
        } else {
            $db = new Database();
            $db->query("SELECT id FROM users WHERE phone = :phone");
            $db->bind(':phone', $phone);
            $db->execute();

            if ($db->rowCount() > 0) {
                $error = "Số điện thoại này đã có tài khoản. Vui lòng đăng nhập hoặc nhập số khác.";
            } else {
                $otp = generateOtpCode();
                storeRegistrationOtp($phone, $otp);

                if (sendRegistrationOtp($phone, $otp)) {
                    $step = 'otp';
                    $success = ($action === 'resend_otp')
                        ? "Đã gửi lại mã OTP tới số $phone."
                        : "Mã OTP đã được gửi tới số $phone.";
                } else {
                    clearRegistrationOtp();
                    $error = "Không gửi được SMS OTP: " . ($_SESSION['registration_otp_error'] ?? 'Vui lòng kiểm tra cấu hình Twilio.');
                }
            }
        }
    } elseif ($action === 'verify_otp') {
        $phone = normalizeVietnamPhone($_POST['phone'] ?? '');
        $otpInput = preg_replace('/\D+/', '', $_POST['otp'] ?? '');
        $step = 'otp';

        $result = verifyRegistrationOtp($phone, $otpInput);

        if ($result === true) {
            header("Location: register_full.php?phone=" . urlencode($phone));
            exit();
        }

        $error = $result;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký tài khoản - MedicailBooking</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Google Font Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: #ffffff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow-x: hidden;
        }
        .auth-page {
            min-height: 100vh;
            display: flex;
            position: relative;
        }
        .auth-left {
            width: 52%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            position: relative;
            z-index: 2;
            padding: 40px;
            box-shadow: 10px 0 30px rgba(2, 63, 109, 0.05);
        }
        .auth-right {
            width: 48%;
            min-height: 100vh;
            position: relative;
            background: linear-gradient(135deg, #023f6d 0%, #00b5f1 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            overflow: hidden;
        }
        .auth-right::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.15), transparent 40%), 
                        radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1), transparent 30%);
            z-index: 1;
        }
        .doctor-illustration {
            width: 85%;
            max-width: 480px;
            height: auto;
            object-fit: contain;
            z-index: 2;
            filter: drop-shadow(0 20px 35px rgba(2, 63, 109, 0.3));
            animation: floatImage 4s ease-in-out infinite;
        }
        @keyframes floatImage {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .back-link {
            position: absolute;
            top: 25px;
            left: 25px;
            color: #64748b;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.25s ease;
            background: #f1f5f9;
            padding: 8px 16px;
            border-radius: 30px;
            border: 1px solid #e2e8f0;
        }
        .back-link:hover {
            color: #023f6d;
            background: #e2e8f0;
            transform: translateX(-3px);
        }
        .support-link {
            position: absolute;
            top: 25px;
            right: 25px;
            color: #00b5f1;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 181, 241, 0.1);
            padding: 8px 16px;
            border-radius: 30px;
            transition: all 0.25s ease;
        }
        .support-link:hover {
            color: #023f6d;
            background: rgba(0, 181, 241, 0.2);
        }
        .auth-card {
            width: 100%;
            max-width: 440px;
            text-align: left;
            animation: slideUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }
        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        .brand {
            color: #023f6d;
            font-size: 2.1rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -1.5px;
            text-transform: uppercase;
        }
        .brand span {
            color: #00b5f1;
        }
        .brand-subtitle {
            color: #64748b;
            letter-spacing: 3px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 35px;
            text-transform: uppercase;
        }
        .auth-title {
            font-size: 1.6rem;
            line-height: 1.4;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 25px;
        }
        .phone-row {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
        }
        .country-code {
            width: 110px;
            height: 54px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #475569;
            font-weight: 700;
            font-size: 0.95rem;
            flex-shrink: 0;
        }
        .flag-vn {
            width: 24px;
            height: auto;
            border-radius: 2px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .phone-input-wrap {
            position: relative;
            flex: 1;
        }
        .phone-input,
        .otp-input {
            height: 54px;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            background: #f8fafc;
            padding: 0 46px 0 18px;
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            transition: all 0.25s ease;
            width: 100%;
        }
        .otp-input {
            text-align: center;
            letter-spacing: 8px;
            padding: 0 18px;
            font-size: 1.25rem;
        }
        .phone-input:focus,
        .otp-input:focus {
            border-color: #00b5f1;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(0, 181, 241, 0.12);
            outline: none;
        }
        .clear-phone {
            display: none;
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            width: 22px;
            height: 22px;
            border: none;
            border-radius: 50%;
            background: #cbd5e1;
            color: #ffffff;
            font-size: 0.85rem;
            line-height: 1;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: all 0.2s ease;
            z-index: 5;
        }
        .clear-phone:hover {
            background: #94a3b8;
        }
        .clear-phone.show {
            display: flex;
        }
        .phone-input::placeholder {
            color: #94a3b8;
            font-weight: 500;
        }
        .phone-error {
            display: none;
            color: #dc2626;
            font-size: 0.85rem;
            font-weight: 700;
            text-align: left;
            margin-top: -15px;
            margin-bottom: 20px;
            padding-left: 122px;
        }
        .phone-error.show {
            display: block;
        }
        .continue-btn {
            height: 54px;
            border: none;
            border-radius: 12px;
            background: #cbd5e1;
            color: #ffffff;
            font-weight: 800;
            width: 100%;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            cursor: not-allowed;
        }
        .continue-btn.active {
            background: linear-gradient(135deg, #023f6d 0%, #00b5f1 100%);
            box-shadow: 0 4px 15px rgba(0, 181, 241, 0.3);
            cursor: pointer;
        }
        .continue-btn.active:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(0, 181, 241, 0.4);
        }
        .otp-test-box {
            background: #ecfeff;
            border: 1px dashed #06b6d4;
            color: #023f6d;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 18px;
            text-align: center;
        }
        .resend-btn {
            border: none;
            background: transparent;
            color: #00b5f1;
            font-weight: 800;
            margin-top: 14px;
            padding: 0;
        }
        .right-text-container {
            z-index: 2;
            text-align: center;
            color: #ffffff;
            margin-top: 30px;
        }
        .quote {
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.5;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 10px rgba(2, 63, 109, 0.25);
            max-width: 380px;
            margin: 0 auto;
        }
        .login-link {
            margin-top: 25px;
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 550;
            text-align: center;
        }
        .login-link a {
            color: #00b5f1;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .login-link a:hover {
            color: #023f6d;
            text-decoration: underline;
        }
        @media (max-width: 992px) {
            .auth-page {
                display: block;
            }
            .auth-left {
                width: 100%;
                min-height: 100vh;
                padding: 100px 24px 40px;
            }
            .auth-right {
                display: none;
            }
            .support-link {
                right: 24px;
                left: auto;
                transform: none;
            }
        }
    </style>
</head>
<body>
    <a href="<?php echo $base_url; ?>/index.php" class="back-link"><i class="bi bi-arrow-left"></i> Quay lại</a>
    <a href="#" class="support-link"><i class="bi bi-telephone-fill"></i> Gọi hỗ trợ</a>

    <main class="auth-page">
        <!-- Left Side: Phone Login/Register Form -->
        <section class="auth-left">
            <div class="auth-card">
                <div class="brand">Medi<span>cailBooking</span></div>
                <div class="brand-subtitle">Đặt khám nhanh</div>

                <h1 class="auth-title"><?php echo $step === 'otp' ? 'Nhập mã OTP để<br>xác thực số điện thoại' : 'Nhập số điện thoại để<br>đăng ký và tiếp tục'; ?></h1>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2" style="border-radius: 10px; font-size: 0.9rem; font-weight: 600;"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success py-2" style="border-radius: 10px; font-size: 0.9rem; font-weight: 600;"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if ($step === 'otp'): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="verify_otp">
                        <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                        <input type="tel" name="otp" id="otpInput" class="form-control otp-input mb-3" placeholder="______" maxlength="6" inputmode="numeric" required autofocus autocomplete="off">
                        <button type="submit" id="submitBtn" class="continue-btn">Xác thực OTP</button>
                    </form>
                    <form method="POST" action="" class="text-center">
                        <input type="hidden" name="action" value="resend_otp">
                        <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                        <button type="submit" class="resend-btn">Gửi lại mã OTP</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="send_otp">
                        <div class="phone-row">
                            <div class="country-code"><img src="https://flagcdn.com/w20/vn.png" class="flag-vn" alt="VN"> +84</div>
                            <div class="phone-input-wrap">
                                <input type="tel" name="phone" id="phoneInput" class="form-control phone-input" placeholder="Nhập số điện thoại" value="<?php echo htmlspecialchars($phone); ?>" required autofocus autocomplete="off">
                                <button type="button" id="clearPhone" class="clear-phone">×</button>
                            </div>
                        </div>
                        <div id="phoneError" class="phone-error">Số điện thoại phải có 10 chữ số.</div>
                        <button type="submit" id="submitBtn" class="continue-btn mt-3">Tiếp tục</button>
                    </form>
                <?php endif; ?>

                <div class="login-link">Đã có tài khoản? <a href="login.php">Đăng nhập bằng email</a></div>
                <div class="login-link mt-2"><a href="register_hospital.php" style="color: #023f6d;">Đăng ký tài khoản cơ sở y tế đối tác</a></div>
            </div>
        </section>

        <!-- Right Side: Medical Branding Illustration -->
        <section class="auth-right">
            <img class="doctor-illustration" src="https://media.istockphoto.com/id/1359494953/vi/vec-to/c%C3%A1c-b%C3%A1c-s%C4%A9-t%C6%B0%C6%A1ng-t%C3%A1c-v%E1%BB%9Bi-giao-di%E1%BB%87n-k%E1%BB%B9-thu%E1%BA%ADt-s%E1%BB%91-v%C3%A0-ki%E1%BB%83m-tra-d%E1%BB%AF-li%E1%BB%87u-s%E1%BB%A9c-kh%E1%BB%8Fe.jpg?s=1024x1024&w=is&k=20&c=xmoUgLICa_IczGl1hwkDwnMIrJ-GqXJf9xSIV4GK9JA=" alt="Bác sĩ hỗ trợ đặt lịch" style="border-radius: 20px; object-fit: cover; height: 350px;">
            <div class="right-text-container">
                <div class="quote">“Không còn cảnh xếp hàng dài chờ đợi để lấy số khám bệnh tại cơ sở y tế”</div>
            </div>
        </section>
    </main>

    <script>
        const phoneInput = document.getElementById('phoneInput');
        const phoneError = document.getElementById('phoneError');
        const clearPhone = document.getElementById('clearPhone');
        const submitBtn = document.getElementById('submitBtn');

        function updatePhoneState() {
            if (!phoneInput) {
                submitBtn.classList.add('active');
                return;
            }

            const val = phoneInput.value;
            const isValidLength = val.length === 10;
            const hasStarted = val.length > 0;
            
            phoneError.classList.toggle('show', hasStarted && !isValidLength);
            clearPhone.classList.toggle('show', hasStarted);
            
            if (isValidLength) {
                submitBtn.classList.add('active');
                submitBtn.removeAttribute('disabled');
            } else {
                submitBtn.classList.remove('active');
                submitBtn.setAttribute('disabled', 'true');
            }
        }

        if (phoneInput) {
            phoneInput.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 10);
                updatePhoneState();
            });

            clearPhone.addEventListener('click', function () {
                phoneInput.value = '';
                updatePhoneState();
                phoneInput.focus();
            });
        }

        const otpInput = document.getElementById('otpInput');
        if (otpInput) {
            otpInput.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
            });
        }

        updatePhoneState();
    </script>
</body>
</html>
