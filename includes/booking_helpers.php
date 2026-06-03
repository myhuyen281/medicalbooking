<?php
function bookingPatientStepUrl($facilityName = '', $facilityAddress = '', array $extraParams = []) {
    $params = [];
    if (trim((string)$facilityName) !== '') {
        $params['facility'] = trim((string)$facilityName);
    }
    if (trim((string)$facilityAddress) !== '') {
        $params['address'] = trim((string)$facilityAddress);
    }
    foreach ($extraParams as $key => $value) {
        if ($value !== null && $value !== '') {
            $params[$key] = $value;
        }
    }
    return 'booking_patient.php' . (count($params) ? '?' . http_build_query($params) : '');
}
?>
