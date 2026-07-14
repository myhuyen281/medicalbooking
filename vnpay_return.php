<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';

$paymentContext = $_GET['payment_context'] ?? '';

// Nếu thanh toán thành công (ResponseCode = 00)
if (isset($_GET['vnp_ResponseCode']) && $_GET['vnp_ResponseCode'] == '00') {

    if ($paymentContext === 'hospital_subscription') {
        $subscriptionPayment = $_SESSION['hospital_subscription_payment'] ?? null;
        if ($subscriptionPayment && !empty($subscriptionPayment['hospital_id'])) {
            $db = new Database();
            try {
                $db->query("ALTER TABLE hospitals ADD COLUMN subscription_started_at DATETIME NULL AFTER subscription_status");
                $db->execute();
            } catch (Exception $e) {
            }
            try {
                $db->query("ALTER TABLE hospitals ADD COLUMN subscription_expires_at DATETIME NULL AFTER subscription_started_at");
                $db->execute();
            } catch (Exception $e) {
            }
            $db->query("UPDATE hospitals SET subscription_status = 'active', subscription_started_at = NOW(), subscription_expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = :id");
            $db->bind(':id', (int)$subscriptionPayment['hospital_id']);
            $db->execute();
            unset($_SESSION['hospital_subscription_payment']);
            ?>
            <!DOCTYPE html>
            <html lang="vi">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta http-equiv="refresh" content="4;url=index.php">
                <title>Thanh toán thành công</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
            </head>
            <body class="min-vh-100 d-flex align-items-center justify-content-center" style="background:linear-gradient(135deg,#023f6d,#00b5f1);">
                <div class="card border-0 shadow-lg text-center" style="max-width:520px;border-radius:24px;">
                    <div class="card-body p-5">
                        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle" style="width:80px;height:80px;background:#e8fff4;color:#16a34a;font-size:42px;">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <h3 class="fw-bold mb-3" style="color:#023f6d;">Thanh toán thành công</h3>
                        <p class="text-muted mb-4">Tài khoản cơ sở y tế đã ghi nhận thanh toán. Gói có hiệu lực 30 ngày kể từ hôm nay. Vui lòng chờ Admin duyệt tài khoản.</p>
                        <p class="small text-muted mb-4">Hệ thống sẽ tự động trở về trang chủ sau vài giây.</p>
                        <a href="index.php" class="btn text-white fw-bold px-4 py-2" style="background:linear-gradient(135deg,#023f6d,#00b5f1);border-radius:12px;">Về trang chủ</a>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }

    // Lấy params từ GET hoặc từ session (tránh mất khi redirect ngrok)
    $params = $_GET;
    if (empty($params['facility']) && !empty($_SESSION['vnpay_booking_params'])) {
        $params = array_merge($_SESSION['vnpay_booking_params'], $params);
    }

    // Lọc bỏ vnp_*
    $bookingParams = [];
    foreach ($params as $key => $value) {
        if (strpos($key, 'vnp_') !== 0) {
            $bookingParams[$key] = $value;
        }
    }

    // Đánh dấu là thanh toán online
    $bookingParams['payment_method'] = 'vnpay';
    $_SESSION['last_booking_payment_method'] = 'vnpay';

    unset($_SESSION['vnpay_booking_params']);
    header("Location: booking_success.php?" . http_build_query($bookingParams));
    exit;

} else {
    echo "<h2>Thanh toán thất bại hoặc bị hủy</h2>";
    echo "<p>Mã lỗi: " . htmlspecialchars($_GET['vnp_ResponseCode'] ?? 'Không xác định') . "</p>";
    echo "<a href='index.php'>Quay về trang chủ</a>";
}
?>