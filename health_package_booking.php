<?php
$facilityName = isset($_GET['facility']) ? trim($_GET['facility']) : 'Bệnh viện tại Cần Thơ';
$facilityAddress = isset($_GET['address']) ? trim($_GET['address']) : '';
$targetUrl = 'specialty_booking.php?facility=' . urlencode($facilityName);
if ($facilityAddress !== '') {
    $targetUrl .= '&address=' . urlencode($facilityAddress);
}
header('Location: ' . $targetUrl);
exit();
