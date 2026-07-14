<?php
function hospitalSubscriptionPlans() {
    return [
        'basic' => [
            'name' => 'Gói Cơ Bản',
            'doctor_limit' => 5,
            'daily_schedule_limit' => 10,
            'service_limit' => 10,
            'specialty_limit' => 2,
            'lab_packages' => false,
            'booking_forms' => false,
            'advanced_stats' => false,
            'banner' => false,
            'premium_priority' => false,
        ],
        'advanced' => [
            'name' => 'Gói Nâng Cao',
            'doctor_limit' => 20,
            'daily_schedule_limit' => null,
            'service_limit' => null,
            'specialty_limit' => 10,
            'lab_packages' => true,
            'booking_forms' => true,
            'advanced_stats' => false,
            'banner' => true,
            'premium_priority' => false,
        ],
        'premium' => [
            'name' => 'Gói Premium',
            'doctor_limit' => null,
            'daily_schedule_limit' => null,
            'service_limit' => null,
            'specialty_limit' => null,
            'lab_packages' => true,
            'booking_forms' => true,
            'advanced_stats' => true,
            'banner' => true,
            'premium_priority' => true,
        ],
    ];
}

function getHospitalSubscriptionPlan($db, $hospitalId) {
    $plans = hospitalSubscriptionPlans();
    if (!$hospitalId) {
        return $plans['basic'];
    }
    try {
        syncHospitalSubscriptionStatus($db, $hospitalId);
        $db->query("SELECT subscription_plan FROM hospitals WHERE id = :id LIMIT 1");
        $db->bind(':id', $hospitalId);
        $hospital = $db->single();
        $planKey = $hospital['subscription_plan'] ?? 'basic';
    } catch (Exception $e) {
        $planKey = 'basic';
    }
    return $plans[$planKey] ?? $plans['basic'];
}

function hospitalPlanAllows($plan, $feature) {
    return !empty($plan[$feature]);
}

function hospitalPlanLimit($plan, $limitKey) {
    return $plan[$limitKey] ?? null;
}

function hospitalPlanLimitMessage($plan, $label, $limit) {
    return $plan['name'] . ' chỉ cho phép ' . $label . ' ' . $limit . '. Vui lòng nâng cấp gói để sử dụng thêm.';
}

function ensureHospitalSubscriptionColumns($db) {
    $queries = [
        "ALTER TABLE hospitals ADD COLUMN subscription_started_at DATETIME NULL AFTER subscription_status",
        "ALTER TABLE hospitals ADD COLUMN subscription_expires_at DATETIME NULL AFTER subscription_started_at"
    ];
    foreach ($queries as $query) {
        try {
            $db->query($query);
            $db->execute();
        } catch (Exception $e) {
        }
    }
}

function syncHospitalSubscriptionStatus($db, $hospitalId) {
    ensureHospitalSubscriptionColumns($db);
    $db->query("UPDATE hospitals SET subscription_status = 'expired' WHERE id = :id AND subscription_status = 'active' AND subscription_expires_at IS NOT NULL AND subscription_expires_at < NOW()");
    $db->bind(':id', $hospitalId);
    $db->execute();
}

function isHospitalSubscriptionActive($db, $hospitalId) {
    if (!$hospitalId) {
        return false;
    }
    syncHospitalSubscriptionStatus($db, $hospitalId);
    $db->query("SELECT subscription_status FROM hospitals WHERE id = :id LIMIT 1");
    $db->bind(':id', $hospitalId);
    $hospital = $db->single();
    return ($hospital['subscription_status'] ?? '') === 'active';
}

function hospitalSubscriptionExpiredMessage() {
    return 'Gói dịch vụ đã hết hạn. Cơ sở y tế chỉ được xem dữ liệu đã có, không thể thêm hoặc chỉnh sửa cho đến khi gia hạn thành công.';
}
?>
