<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu thanh toán thành công (ResponseCode = 00)
if (isset($_GET['vnp_ResponseCode']) && $_GET['vnp_ResponseCode'] == '00') {

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