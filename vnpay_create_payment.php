<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');
$paymentContext = $_GET['payment_context'] ?? 'booking';
if ($paymentContext === 'hospital_subscription') {
    $subscriptionPayment = $_SESSION['hospital_subscription_payment'] ?? null;
    if (!$subscriptionPayment || empty($subscriptionPayment['hospital_id']) || empty($subscriptionPayment['amount'])) {
        header('Location: views/auth/register_hospital.php');
        exit();
    }
} else {
    $_SESSION['vnpay_booking_params'] = $_GET;
}

$vnp_TmnCode = "TBJWSERF";
$vnp_HashSecret = "VYU4YZ8IFAO9NVL9KMUV3RK8SQF41NQO";
$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host   = $_SERVER['HTTP_HOST'];
$vnp_Returnurl = $scheme . "://" . $host . "/Medicailbooking/vnpay_return.php" . ($paymentContext === 'hospital_subscription' ? '?payment_context=hospital_subscription' : '');

$vnp_TxnRef = rand(100000,999999);
$vnp_OrderType = "other";
if ($paymentContext === 'hospital_subscription') {
    $vnp_OrderInfo = "Thanh toan goi " . ($subscriptionPayment['name'] ?? 'dang ky co so y te');
    $vnp_Amount = ((int)$subscriptionPayment['amount']) * 100;
} else {
    $vnp_OrderInfo = "Thanh toan dat lich";
    $vnp_Amount = 150000 * 100;
}

$inputData = array(
    "vnp_Version" => "2.1.0",
    "vnp_TmnCode" => $vnp_TmnCode,
    "vnp_Amount" => $vnp_Amount,
    "vnp_Command" => "pay",
    "vnp_CreateDate" => date('YmdHis'),
    "vnp_CurrCode" => "VND",
    "vnp_IpAddr" => $_SERVER['REMOTE_ADDR'],
    "vnp_Locale" => "vn",
    "vnp_OrderInfo" => $vnp_OrderInfo,
    "vnp_OrderType" => $vnp_OrderType,
    "vnp_ReturnUrl" => $vnp_Returnurl,
    "vnp_TxnRef" => $vnp_TxnRef
);

ksort($inputData);

$query = "";
$i = 0;
$hashdata = "";
foreach ($inputData as $key => $value) {
    if ($i === 1) {
        $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashdata .= urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
    $query .= urlencode($key) . "=" . urlencode($value) . '&';
}

$vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);

$vnp_Url = $vnp_Url . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;

header('Location: ' . $vnp_Url);
exit();