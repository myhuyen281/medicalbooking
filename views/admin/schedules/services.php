<?php
require_once '../../../config/database.php';
include '../includes/header.php';

if ($isHospitalAdmin && empty($currentHospitalId)) {
    echo "<div class='alert alert-danger'>Tài khoản chưa được gán bệnh viện.</div>";
    include '../includes/footer.php';
    exit();
}

$db = new Database();
$error = '';
try {
    $db->query("ALTER TABLE hospital_services ADD COLUMN booking_form_id INT NULL AFTER hospital_id");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospital_services ADD INDEX idx_booking_form_id (booking_form_id)");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospital_services ADD COLUMN specialty_name TEXT NULL AFTER name");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospital_services MODIFY specialty_name TEXT NULL");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospital_services ADD COLUMN detail_text TEXT NULL AFTER schedule_text");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospital_services ADD COLUMN requires_insurance TINYINT(1) NOT NULL DEFAULT 0 AFTER detail_text");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospital_services ADD COLUMN service_icon VARCHAR(80) NOT NULL DEFAULT 'bi-calendar2-check' AFTER name");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospital_services ADD COLUMN service_target VARCHAR(30) NOT NULL DEFAULT 'specialty' AFTER service_icon");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospitals ADD COLUMN booking_advance_days INT NOT NULL DEFAULT 30 AFTER working_time");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospitals ADD COLUMN booking_time_slots TEXT NULL AFTER booking_advance_days");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospitals ADD COLUMN booking_specialties TEXT NULL AFTER booking_time_slots");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE hospitals ADD COLUMN booking_flow VARCHAR(30) NOT NULL DEFAULT 'service_only' AFTER booking_specialties");
    $db->execute();
} catch (Exception $e) {
}
$success = '';
$weekdayOptions = [
    1 => 'Thứ 2',
    2 => 'Thứ 3',
    3 => 'Thứ 4',
    4 => 'Thứ 5',
    5 => 'Thứ 6',
    6 => 'Thứ 7',
    0 => 'CN'
];
$morningTimeOptions = [];
for ($hour = 7; $hour <= 11; $hour++) {
    foreach ([0, 30] as $minute) {
        $time = sprintf('%02d:%02d', $hour, $minute);
        $morningTimeOptions[$time] = $time;
    }
}
$morningTimeOptions['12:00'] = '12:00';
$afternoonTimeOptions = [];
for ($hour = 13; $hour <= 17; $hour++) {
    foreach ([0, 30] as $minute) {
        $time = sprintf('%02d:%02d', $hour, $minute);
        $afternoonTimeOptions[$time] = $time;
    }
}
$afternoonTimeOptions['18:00'] = '18:00';
function serviceSpecialtyValues($value) {
    $decoded = json_decode((string)$value, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('trim', $decoded), 'strlen'));
    }
    return array_values(array_filter(array_map('trim', explode(',', (string)$value)), 'strlen'));
}

function to12HourTime($time) {
    if (empty($time)) {
        return ['', 'AM'];
    }
    [$hour, $minute] = array_pad(explode(':', $time), 2, '00');
    $hour = (int)$hour;
    $meridiem = $hour >= 12 ? 'PM' : 'AM';
    $hour = $hour % 12;
    if ($hour === 0) {
        $hour = 12;
    }
    return [sprintf('%02d:%s', $hour, $minute), $meridiem];
}

function to24HourTime($time, $meridiem) {
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
        return '';
    }
    $hour = (int)$matches[1];
    $minute = $matches[2];
    if ($hour < 1 || $hour > 12) {
        return '';
    }
    if ($meridiem === 'PM' && $hour < 12) {
        $hour += 12;
    }
    if ($meridiem === 'AM' && $hour === 12) {
        $hour = 0;
    }
    return sprintf('%02d:%s', $hour, $minute);
}

$hospitalId = $isHospitalAdmin ? $currentHospitalId : ($_GET['hospital_id'] ?? null);
$bookingFormId = isset($_GET['booking_form_id']) ? (int)$_GET['booking_form_id'] : 0;
$singleServiceMode = false;

if (!$hospitalId && $isSystemAdmin) {
    $db->query("SELECT id FROM hospitals ORDER BY name ASC LIMIT 1");
    $firstHospital = $db->single();
    $hospitalId = $firstHospital['id'] ?? null;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $isHospitalAdmin && !$currentHospitalSubscriptionActive) {
    $error = hospitalSubscriptionExpiredMessage();
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $hospitalId = $isHospitalAdmin ? $currentHospitalId : ($_POST['hospital_id'] ?? $hospitalId);
    $serviceNames = $_POST['service_name'] ?? [];
    $serviceSchedules = $_POST['service_schedule'] ?? [];
    $serviceSpecialties = $_POST['service_specialty'] ?? [];
    $serviceDetails = $_POST['service_detail'] ?? [];
    $serviceIcons = $_POST['service_icon'] ?? [];
    $serviceTargets = $_POST['service_target'] ?? [];
    $serviceInsurance = $_POST['requires_insurance'] ?? [];
    $servicePrices = $_POST['service_price'] ?? [];
    $bookingFormId = isset($_POST['booking_form_id']) ? (int)$_POST['booking_form_id'] : 0;
    $singleServiceMode = false;

    if (empty($hospitalId)) {
        $error = "Vui lòng chọn bệnh viện.";
    } else {
        $db->query("SELECT * FROM hospitals WHERE id = :id");
        $db->bind(':id', $hospitalId);
        $currentHospital = $db->single();
        $bookingAdvanceDays = max(1, min(365, (int)($_POST['booking_advance_days'] ?? 30)));
        $bookingFlow = in_array($_POST['booking_flow'] ?? 'service_only', ['service_only', 'specialty_first', 'service_first', 'doctor_first'], true) ? $_POST['booking_flow'] : 'service_only';
        if ($bookingFormId > 0) {
            $db->query("SELECT target FROM hospital_booking_forms WHERE id = :id AND hospital_id = :hospital_id LIMIT 1");
            $db->bind(':id', $bookingFormId);
            $db->bind(':hospital_id', $hospitalId);
            $bookingFormTarget = $db->single();
            if (($bookingFormTarget['target'] ?? '') === 'doctor') {
                $bookingFlow = 'doctor_first';
            }
        }
        $useSpecialties = in_array($bookingFlow, ['specialty_first', 'service_first'], true);
        $specialtyNames = [];
        if ($useSpecialties) {
            foreach ($_POST['specialty_names'] ?? [] as $specialtyName) {
                $specialtyName = trim($specialtyName);
                if ($specialtyName !== '' && !in_array($specialtyName, $specialtyNames, true)) {
                    $specialtyNames[] = $specialtyName;
                }
            }
        }
        $bookingTimeSlotsJson = $currentHospital['booking_time_slots'] ?? null;
        if ($bookingFlow !== 'doctor_first') {
            $enabledPeriods = $_POST['enabled_periods'] ?? [];
            $slotPeriods = $_POST['slot_period'] ?? [];
            $slotStarts = $_POST['slot_start'] ?? [];
            $slotEnds = $_POST['slot_end'] ?? [];
            $bookingTimeSlots = [];
            $timeSlotKeys = [];
            $hasDuplicateTimeSlot = false;
            foreach ($slotStarts as $slotIndex => $slotStart) {
                $slotStart = trim($slotStart);
                $slotEnd = trim($slotEnds[$slotIndex] ?? '');
                $slotPeriod = in_array($slotPeriods[$slotIndex] ?? 'morning', ['morning', 'afternoon', 'both'], true) ? $slotPeriods[$slotIndex] : 'morning';
                if (!in_array($slotPeriod, $enabledPeriods, true) || $slotStart === '' || $slotEnd === '' || $slotStart >= $slotEnd) {
                    continue;
                }
                if ($slotPeriod === 'morning' && ($slotStart >= '12:00' || $slotEnd > '12:00')) {
                    continue;
                }
                if ($slotPeriod === 'afternoon' && $slotStart < '13:00') {
                    continue;
                }
                $slotKey = $slotPeriod . '|' . $slotStart . '|' . $slotEnd;
                if (isset($timeSlotKeys[$slotKey])) {
                    $hasDuplicateTimeSlot = true;
                    continue;
                }
                $timeSlotKeys[$slotKey] = true;
                $bookingTimeSlots[] = ['period' => $slotPeriod, 'start' => $slotStart, 'end' => $slotEnd];
            }
            if ($hasDuplicateTimeSlot) {
                $error = 'Không được chọn 2 khung giờ khám giống nhau.';
            }
            $bookingTimeSlotsJson = json_encode($bookingTimeSlots, JSON_UNESCAPED_UNICODE);
        }
        $plan = getHospitalSubscriptionPlan($db, $hospitalId);
        $serviceLimit = hospitalPlanLimit($plan, 'service_limit');
        $specialtyLimit = hospitalPlanLimit($plan, 'specialty_limit');
        $newServiceCount = count(array_filter(array_map('trim', $serviceNames)));
        if ($serviceLimit !== null && $newServiceCount > $serviceLimit) {
            $error = hospitalPlanLimitMessage($plan, 'tối đa dịch vụ', $serviceLimit);
        } elseif ($specialtyLimit !== null && count($specialtyNames) > $specialtyLimit) {
            $error = hospitalPlanLimitMessage($plan, 'tối đa chuyên khoa', $specialtyLimit);
        }
        if (empty($error)) {
        $db->query("UPDATE hospitals SET booking_advance_days = :booking_advance_days, booking_time_slots = :booking_time_slots, booking_specialties = :booking_specialties, booking_flow = :booking_flow WHERE id = :hospital_id");
        $db->bind(':booking_advance_days', $bookingAdvanceDays);
        $db->bind(':booking_time_slots', $bookingTimeSlotsJson);
        $db->bind(':booking_specialties', json_encode($specialtyNames, JSON_UNESCAPED_UNICODE));
        $db->bind(':booking_flow', $bookingFlow);
        $db->bind(':hospital_id', $hospitalId);
        $db->execute();

        if ($bookingFormId > 0) {
            $db->query("DELETE FROM hospital_services WHERE hospital_id = :hospital_id AND booking_form_id = :booking_form_id");
            $db->bind(':hospital_id', $hospitalId);
            $db->bind(':booking_form_id', $bookingFormId);
        } else {
            $db->query("DELETE FROM hospital_services WHERE hospital_id = :hospital_id AND booking_form_id IS NULL");
            $db->bind(':hospital_id', $hospitalId);
        }
        $db->execute();

        foreach ($serviceNames as $index => $serviceName) {
            $serviceName = trim($serviceName);
            if ($serviceName === '') {
                continue;
            }
            $selectedWeekdays = $bookingFlow === 'doctor_first' ? [] : array_values(array_intersect(array_keys($weekdayOptions), array_map('intval', $serviceSchedules[$index] ?? [])));
            usort($selectedWeekdays, function ($a, $b) use ($weekdayOptions) {
                return array_search($a, array_keys($weekdayOptions), true) <=> array_search($b, array_keys($weekdayOptions), true);
            });
            $scheduleText = implode(',', $selectedWeekdays);
            $selectedSpecialties = [];
            if ($useSpecialties) {
                foreach ($serviceSpecialties[$index] ?? [] as $specialtyName) {
                    $specialtyName = trim($specialtyName);
                    if ($specialtyName !== '' && in_array($specialtyName, $specialtyNames, true) && !in_array($specialtyName, $selectedSpecialties, true)) {
                        $selectedSpecialties[] = $specialtyName;
                    }
                }
            }
            $specialtyName = json_encode($selectedSpecialties, JSON_UNESCAPED_UNICODE);
            $serviceIcon = preg_match('/^bi-[a-z0-9-]+$/', $serviceIcons[$index] ?? '') ? $serviceIcons[$index] : 'bi-calendar2-check';
            $rawServiceTarget = $serviceTargets[$index] ?? 'specialty';
            $serviceTarget = in_array($rawServiceTarget, ['specialty', 'doctor'], true) ? $rawServiceTarget : 'specialty';
            $db->query("INSERT INTO hospital_services (hospital_id, booking_form_id, specialty_name, service_icon, service_target, name, schedule_text, detail_text, requires_insurance, price) VALUES (:hospital_id, :booking_form_id, :specialty_name, :service_icon, :service_target, :name, :schedule_text, :detail_text, :requires_insurance, :price)");
            $db->bind(':hospital_id', $hospitalId);
            $db->bind(':booking_form_id', $bookingFormId > 0 ? $bookingFormId : null);
            $db->bind(':specialty_name', $specialtyName);
            $db->bind(':service_icon', $serviceIcon);
            $db->bind(':service_target', $serviceTarget);
            $db->bind(':name', $serviceName);
            $db->bind(':schedule_text', $scheduleText);
            $db->bind(':detail_text', trim($serviceDetails[$index] ?? ''));
            $db->bind(':requires_insurance', isset($serviceInsurance[$index]) ? 1 : 0);
            $db->bind(':price', (float)str_replace(',', '', $servicePrices[$index] ?? 0));
            $db->execute();
        }
        $success = "Cập nhật dịch vụ đặt khám thành công.";
    }
}
}

$db->query("SELECT id, name FROM hospitals ORDER BY name ASC");
$hospitals = $db->resultSet();

$currentHospital = null;
if ($hospitalId) {
    $db->query("SELECT * FROM hospitals WHERE id = :id");
    $db->bind(':id', $hospitalId);
    $currentHospital = $db->single();
}

$bookingForm = null;
if ($hospitalId && $bookingFormId > 0) {
    $db->query("SELECT * FROM hospital_booking_forms WHERE id = :id AND hospital_id = :hospital_id LIMIT 1");
    $db->bind(':id', $bookingFormId);
    $db->bind(':hospital_id', $hospitalId);
    $bookingForm = $db->single();
    if (!$bookingForm) {
        $bookingFormId = 0;
    }
}
if ($hospitalId && $bookingFormId > 0) {
    $db->query("SELECT * FROM hospital_services WHERE hospital_id = :hospital_id AND booking_form_id = :booking_form_id ORDER BY id ASC");
    $db->bind(':hospital_id', $hospitalId);
    $db->bind(':booking_form_id', $bookingFormId);
} else {
    $db->query("SELECT * FROM hospital_services WHERE hospital_id = :hospital_id AND booking_form_id IS NULL ORDER BY id ASC");
    $db->bind(':hospital_id', $hospitalId);
}
$hospitalServices = $hospitalId ? $db->resultSet() : [];
$bookingSpecialties = [];
if (!empty($currentHospital['booking_specialties'])) {
    $decodedSpecialties = json_decode($currentHospital['booking_specialties'], true);
    $bookingSpecialties = is_array($decodedSpecialties) ? array_values(array_filter($decodedSpecialties, 'strlen')) : [];
}
if (count($bookingSpecialties) === 0) {
    foreach ($hospitalServices as $service) {
        $specialtyName = trim($service['specialty_name'] ?? '');
        if ($specialtyName !== '' && !in_array($specialtyName, $bookingSpecialties, true)) {
            $bookingSpecialties[] = $specialtyName;
        }
    }
}
$bookingFlow = in_array($currentHospital['booking_flow'] ?? '', ['service_only', 'specialty_first', 'service_first', 'doctor_first'], true) ? $currentHospital['booking_flow'] : (count($bookingSpecialties) > 0 ? 'specialty_first' : 'service_only');
if (($bookingForm['target'] ?? '') === 'doctor') {
    $bookingFlow = 'doctor_first';
}
$useSpecialties = in_array($bookingFlow, ['specialty_first', 'service_first'], true);
if (count($bookingSpecialties) === 0) {
    $bookingSpecialties = [''];
}
$bookingTimeSlots = [];
if (!empty($currentHospital['booking_time_slots'])) {
    $decodedSlots = json_decode($currentHospital['booking_time_slots'], true);
    $bookingTimeSlots = is_array($decodedSlots) ? $decodedSlots : [];
}
$morningTimeSlots = array_filter($bookingTimeSlots, function ($slot) {
    $period = $slot['period'] ?? (($slot['start'] ?? '') >= '12:00' ? 'afternoon' : 'morning');
    return in_array($period, ['morning', 'both'], true) && (($slot['start'] ?? '') === '' || ($slot['start'] ?? '') < '12:00');
});
$afternoonTimeSlots = array_filter($bookingTimeSlots, function ($slot) {
    $period = $slot['period'] ?? (($slot['start'] ?? '') >= '12:00' ? 'afternoon' : 'morning');
    return in_array($period, ['afternoon', 'both'], true);
});
if (count($morningTimeSlots) === 0) {
    $morningTimeSlots = [['period' => 'morning', 'start' => '', 'end' => '']];
}
$morningEnabled = count(array_filter($morningTimeSlots, function ($slot) { return !empty($slot['start']) && !empty($slot['end']); })) > 0 || count($bookingTimeSlots) === 0;
$afternoonEnabled = count(array_filter($afternoonTimeSlots, function ($slot) { return !empty($slot['start']) && !empty($slot['end']); })) > 0 || count($bookingTimeSlots) === 0;
if (count($afternoonTimeSlots) === 0) {
    $afternoonTimeSlots = [['period' => 'afternoon', 'start' => '', 'end' => '']];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Dịch vụ khám<?php echo $bookingForm ? ' - ' . htmlspecialchars($bookingForm['name']) : ''; ?></h2>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Quay lại lịch khám</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form method="POST" action="">
            <input type="hidden" name="booking_form_id" value="<?php echo (int)$bookingFormId; ?>">
            <?php if ($bookingForm): ?>
                <div class="alert alert-info border-0">Mỗi hình thức đặt khám tương ứng với 1 dịch vụ riêng. Bạn đang cấu hình dịch vụ cho: <strong><?php echo htmlspecialchars($bookingForm['name']); ?></strong></div>
            <?php endif; ?>
            <?php if ($isSystemAdmin): ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Bệnh viện</label>
                    <select name="hospital_id" class="form-select" onchange="window.location='?hospital_id=' + this.value<?php echo $bookingFormId > 0 ? ' + \'&booking_form_id=' . (int)$bookingFormId . '\'' : ''; ?>">
                        <?php foreach ($hospitals as $hospital): ?>
                            <option value="<?php echo $hospital['id']; ?>" <?php echo $hospitalId == $hospital['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($hospital['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="border rounded-3 p-3 mb-3" style="background-color:#eefcff; border-color:#00a8f0 !important;">
                <label class="form-label fw-bold" style="color:#023f6d;">Kiểu đặt khám</label>
                <select name="booking_flow" id="bookingFlow" class="form-select mb-3" style="max-width: 420px;" <?php echo ($bookingForm['target'] ?? '') === 'doctor' ? 'disabled' : ''; ?>>
                    <option value="service_only" <?php echo $bookingFlow === 'service_only' ? 'selected' : ''; ?>>Chỉ chọn dịch vụ</option>
                    <option value="specialty_first" <?php echo $bookingFlow === 'specialty_first' ? 'selected' : ''; ?>>Chọn chuyên khoa trước, rồi chọn dịch vụ</option>
                    <option value="service_first" <?php echo $bookingFlow === 'service_first' ? 'selected' : ''; ?>>Chọn dịch vụ trước, rồi chọn chuyên khoa</option>
                    <option value="doctor_first" <?php echo $bookingFlow === 'doctor_first' ? 'selected' : ''; ?>>Chọn bác sĩ trước, rồi chọn dịch vụ</option>
                </select>
                <?php if (($bookingForm['target'] ?? '') === 'doctor'): ?>
                    <input type="hidden" name="booking_flow" value="doctor_first">
                    <div class="small text-muted mb-2">Hình thức này đang trỏ tới trang bác sĩ nên luồng đặt khám được cố định là chọn bác sĩ trước, rồi chọn dịch vụ.</div>
                <?php endif; ?>
                <div id="specialtyRows" class="d-flex flex-column gap-2 <?php echo $useSpecialties ? '' : 'd-none'; ?>" style="max-width: 520px;">
                    <?php foreach ($bookingSpecialties as $specialtyName): ?>
                        <div class="row g-2 specialty-row">
                            <div class="col-10"><input type="text" name="specialty_names[]" class="form-control specialty-name-input" placeholder="Tên chuyên khoa" value="<?php echo htmlspecialchars($specialtyName); ?>"></div>
                            <div class="col-2"><button type="button" class="btn btn-outline-danger w-100 remove-specialty">×</button></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="addSpecialty" class="btn btn-outline-primary btn-sm mt-2 <?php echo $useSpecialties ? '' : 'd-none'; ?>">Thêm chuyên khoa</button>
                <div class="small text-muted mt-1">Nếu bật chuyên khoa, dịch vụ của hình thức này có thể gắn với chuyên khoa tương ứng.</div>
            </div>

            <div class="mb-3">
                <button type="button" id="addService" class="btn btn-outline-primary">Thêm dịch vụ</button>
            </div>

            <div id="serviceRows" class="d-flex flex-column gap-2 mb-4">
                <?php $serviceRows = count($hospitalServices) ? $hospitalServices : [['name' => '', 'schedule_text' => '', 'price' => '']]; ?>
                <?php foreach ($serviceRows as $index => $service): ?>
                    <div class="row g-2 service-row border rounded-3 p-2">
                        <input type="hidden" name="service_target[]" value="<?php echo htmlspecialchars($service['service_target'] ?? 'specialty'); ?>">
                        <div class="col-md-4 service-specialty-select <?php echo $useSpecialties ? '' : 'd-none'; ?>">
                            <div class="border rounded-3 p-2 bg-white">
                                <div class="fw-semibold small mb-2">Chuyên khoa</div>
                                <div class="d-flex flex-column gap-1">
                                    <?php $selectedSpecialties = serviceSpecialtyValues($service['specialty_name'] ?? ''); ?>
                                    <?php foreach ($bookingSpecialties as $specialtyName): ?>
                                        <?php if ($specialtyName !== ''): ?>
                                            <label class="form-check-label small"><input type="checkbox" name="service_specialty[<?php echo $index; ?>][]" value="<?php echo htmlspecialchars($specialtyName); ?>" class="form-check-input" <?php echo in_array($specialtyName, $selectedSpecialties, true) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($specialtyName); ?></label>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3"><input type="text" name="service_name[]" class="form-control" placeholder="Tên dịch vụ" value="<?php echo htmlspecialchars($service['name'] ?? ''); ?>"></div>
                        <div class="col-md-1"><input type="text" name="service_icon[]" class="form-control" placeholder="Icon" value="<?php echo htmlspecialchars($service['service_icon'] ?? 'bi-calendar2-check'); ?>" title="Bootstrap icon, ví dụ: bi-calendar2-check"></div>
                        <div class="col-md-4 service-schedule-select <?php echo $bookingFlow === 'doctor_first' ? 'd-none' : ''; ?>">
                            <div class="border rounded-3 p-2 bg-white">
                                <div class="fw-semibold small mb-2">Ngày khám</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php $selectedWeekdays = array_filter(explode(',', (string)($service['schedule_text'] ?? '')), 'strlen'); ?>
                                    <?php foreach ($weekdayOptions as $weekdayValue => $weekdayLabel): ?>
                                        <label class="form-check-label small me-2"><input type="checkbox" name="service_schedule[<?php echo $index; ?>][]" value="<?php echo $weekdayValue; ?>" class="form-check-input" <?php echo in_array((string)$weekdayValue, $selectedWeekdays, true) ? 'checked' : ''; ?>> <?php echo $weekdayLabel; ?></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3"><input type="number" step="1" min="0" name="service_price[]" class="form-control" placeholder="Giá" value="<?php echo htmlspecialchars($service['price'] ?? ''); ?>"></div>
                        <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-service">×</button></div>
                        <div class="col-12"><textarea name="service_detail[]" class="form-control" rows="3" placeholder="Nội dung chi tiết hiện khi bệnh nhân chọn dịch vụ"><?php echo htmlspecialchars($service['detail_text'] ?? ''); ?></textarea></div>
                        <div class="col-12">
                            <label class="form-check-label">
                                <input type="checkbox" name="requires_insurance[<?php echo $index; ?>]" class="form-check-input" <?php echo !empty($service['requires_insurance']) ? 'checked' : ''; ?>> Dịch vụ này yêu cầu bệnh nhân chọn Có/Không BHYT
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Cho phép đặt khám trước</label>
                <div class="input-group" style="max-width: 320px;">
                    <input type="number" name="booking_advance_days" class="form-control" min="1" max="365" value="<?php echo htmlspecialchars($currentHospital['booking_advance_days'] ?? 30); ?>">
                    <span class="input-group-text">ngày</span>
                </div>
                <small class="text-muted">Trang đặt khám chỉ cho bệnh nhân chọn ngày trong thời hạn này.</small>
            </div>

            <div id="hospitalTimeSettings" class="mb-4 <?php echo $bookingFlow === 'doctor_first' ? 'd-none' : ''; ?>">
                <label class="form-label fw-bold">Giờ khám bệnh viện cho phép đặt</label>
                <div class="border rounded-3 p-3 mb-3" style="max-width: 620px;">
                    <label class="form-check-label fw-bold mb-2" style="color:#023f6d;"><input type="checkbox" name="enabled_periods[]" value="morning" class="form-check-input period-toggle" data-target="morningSlotRows" <?php echo $morningEnabled ? 'checked' : ''; ?>> Buổi sáng</label>
                    <div id="morningSlotRows" class="d-flex flex-column gap-2 time-slot-group" data-period="morning">
                        <?php foreach ($morningTimeSlots as $slot): ?>
                            <div class="row g-2 time-slot-row align-items-center">
                                <input type="hidden" name="slot_period[]" value="morning" class="slot-period-value">
                                <div class="col-md-5">
                                    <select name="slot_start[]" class="form-select">
                                        <option value="">Chọn giờ bắt đầu</option>
                                        <?php foreach ($morningTimeOptions as $timeValue => $timeLabel): ?>
                                            <option value="<?php echo $timeValue; ?>" <?php echo ($slot['start'] ?? '') === $timeValue ? 'selected' : ''; ?>><?php echo $timeLabel; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <select name="slot_end[]" class="form-select">
                                        <option value="">Chọn giờ kết thúc</option>
                                        <?php foreach ($morningTimeOptions as $timeValue => $timeLabel): ?>
                                            <option value="<?php echo $timeValue; ?>" <?php echo ($slot['end'] ?? '') === $timeValue ? 'selected' : ''; ?>><?php echo $timeLabel; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-time-slot">×</button></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-time-slot" data-target="morningSlotRows" data-period="morning">Thêm giờ khám buổi sáng</button>
                </div>
                <div class="border rounded-3 p-3" style="max-width: 620px;">
                    <label class="form-check-label fw-bold mb-2" style="color:#023f6d;"><input type="checkbox" name="enabled_periods[]" value="afternoon" class="form-check-input period-toggle" data-target="afternoonSlotRows" <?php echo $afternoonEnabled ? 'checked' : ''; ?>> Buổi chiều</label>
                    <div id="afternoonSlotRows" class="d-flex flex-column gap-2 time-slot-group" data-period="afternoon">
                        <?php foreach ($afternoonTimeSlots as $slot): ?>
                            <div class="row g-2 time-slot-row align-items-center">
                                <input type="hidden" name="slot_period[]" value="afternoon" class="slot-period-value">
                                <div class="col-md-5">
                                    <select name="slot_start[]" class="form-select">
                                        <option value="">Chọn giờ bắt đầu</option>
                                        <?php foreach ($afternoonTimeOptions as $timeValue => $timeLabel): ?>
                                            <option value="<?php echo $timeValue; ?>" <?php echo ($slot['start'] ?? '') === $timeValue ? 'selected' : ''; ?>><?php echo $timeLabel; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <select name="slot_end[]" class="form-select">
                                        <option value="">Chọn giờ kết thúc</option>
                                        <?php foreach ($afternoonTimeOptions as $timeValue => $timeLabel): ?>
                                            <option value="<?php echo $timeValue; ?>" <?php echo ($slot['end'] ?? '') === $timeValue ? 'selected' : ''; ?>><?php echo $timeLabel; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-time-slot">×</button></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-time-slot" data-target="afternoonSlotRows" data-period="afternoon">Thêm giờ khám buổi chiều</button>
                </div>
                <div class="small text-muted mt-1">Buổi sáng chọn trong khoảng 07:00 - 12:00, buổi chiều chọn trong khoảng 13:00 - 18:00.</div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <div id="duplicateTimeWarning" class="alert alert-danger d-none">Không được chọn 2 khung giờ khám giống nhau.</div>
                <button type="submit" class="btn btn-primary">Lưu dịch vụ</button>
            </div>
        </form>
    </div>
</div>

<script>
const servicesForm = document.querySelector('form[method="POST"]');
const servicesDraftKey = 'hospital_services_draft_v4_<?php echo (int)$hospitalId; ?>_<?php echo (int)$bookingFormId; ?>';
const singleServiceMode = <?php echo $singleServiceMode ? 'true' : 'false'; ?>;
const fixedDoctorFirstFlow = <?php echo $bookingFlow === 'doctor_first' ? 'true' : 'false'; ?>;

function captureFormDraft() {
    const fields = [];
    servicesForm.querySelectorAll('input, select, textarea').forEach(function (field) {
        fields.push({ name: field.name, type: field.type, value: field.value, checked: field.checked });
    });
    localStorage.setItem(servicesDraftKey, JSON.stringify({ html: servicesForm.innerHTML, fields: fields }));
}

function restoreFormDraft() {
    const draft = localStorage.getItem(servicesDraftKey);
    if (!draft) {
        return;
    }
    try {
        const data = JSON.parse(draft);
        if (!data.html || !Array.isArray(data.fields)) {
            return;
        }
        servicesForm.innerHTML = data.html;
        const fields = servicesForm.querySelectorAll('input, select, textarea');
        data.fields.forEach(function (saved, index) {
            const field = fields[index];
            if (!field || field.name !== saved.name) {
                return;
            }
            field.value = saved.value;
            if (field.type === 'checkbox' || field.type === 'radio') {
                field.checked = saved.checked;
            }
        });
    } catch (error) {
        localStorage.removeItem(servicesDraftKey);
    }
}

restoreFormDraft();
servicesForm.addEventListener('input', captureFormDraft);
servicesForm.addEventListener('change', captureFormDraft);
function validateDuplicateTimeSlots() {
    const seen = new Set();
    let duplicated = false;
    document.querySelectorAll('.time-slot-row').forEach(function (row) {
        const group = row.closest('.time-slot-group');
        const period = row.querySelector('.slot-period-value')?.value || group?.dataset.period || '';
        const periodToggle = group ? document.querySelector('.period-toggle[data-target="' + group.id + '"]') : null;
        if (periodToggle && !periodToggle.checked) {
            row.classList.remove('border', 'border-danger', 'rounded-3');
            return;
        }
        const start = row.querySelector('select[name="slot_start[]"]')?.value || '';
        const end = row.querySelector('select[name="slot_end[]"]')?.value || '';
        const key = period && start && end ? period + '|' + start + '|' + end : '';
        const isDuplicate = key && seen.has(key);
        row.classList.toggle('border', !!isDuplicate);
        row.classList.toggle('border-danger', !!isDuplicate);
        row.classList.toggle('rounded-3', !!isDuplicate);
        if (isDuplicate) {
            duplicated = true;
        }
        if (key) {
            seen.add(key);
        }
    });
    document.getElementById('duplicateTimeWarning')?.classList.toggle('d-none', !duplicated);
    return !duplicated;
}

servicesForm.addEventListener('submit', function (event) {
    if (!validateDuplicateTimeSlots()) {
        event.preventDefault();
        return;
    }
    captureFormDraft();
});
servicesForm.addEventListener('change', function (event) {
    if (event.target.matches('select[name="slot_start[]"], select[name="slot_end[]"], .period-toggle')) {
        validateDuplicateTimeSlots();
    }
});
window.addEventListener('beforeunload', captureFormDraft);

function applyPeriodToggle(toggle) {
    const target = document.getElementById(toggle.dataset.target);
    const disabled = !toggle.checked;
    target.querySelectorAll('input, select, button').forEach(function (input) {
        input.disabled = disabled;
    });
    const addButton = document.querySelector('.add-time-slot[data-target="' + toggle.dataset.target + '"]');
    if (addButton) {
        addButton.disabled = disabled;
    }
}

function buildTimeSelect(name, placeholder, period) {
    const times = period === 'morning' ? <?php echo json_encode(array_values($morningTimeOptions)); ?> : <?php echo json_encode(array_values($afternoonTimeOptions)); ?>;
    let html = '<select name="' + name + '" class="form-select"><option value="">' + placeholder + '</option>';
    times.forEach(function (time) {
        html += '<option value="' + time + '">' + time + '</option>';
    });
    return html + '</select>';
}

document.querySelectorAll('.period-toggle').forEach(function (toggle) {
    applyPeriodToggle(toggle);
    toggle.addEventListener('change', function () {
        applyPeriodToggle(this);
    });
});

document.querySelectorAll('.add-time-slot').forEach(function (button) {
    button.addEventListener('click', function () {
        const wrapper = document.getElementById(this.dataset.target);
        const period = this.dataset.period;
        const row = document.createElement('div');
        row.className = 'row g-2 time-slot-row align-items-center';
        row.innerHTML = '<input type="hidden" name="slot_period[]" value="' + period + '" class="slot-period-value"><div class="col-md-5">' + buildTimeSelect('slot_start[]', 'Chọn giờ bắt đầu', period) + '</div><div class="col-md-5">' + buildTimeSelect('slot_end[]', 'Chọn giờ kết thúc', period) + '</div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-time-slot">×</button></div>';
        wrapper.appendChild(row);
        captureFormDraft();
    });
});

function currentSpecialtyOptions() {
    return Array.from(document.querySelectorAll('.specialty-name-input')).map(function (input) {
        return input.value.trim();
    }).filter(Boolean);
}

function buildSpecialtySelect(index, selected = []) {
    let html = '<div class="border rounded-3 p-2 bg-white"><div class="fw-semibold small mb-2">Chuyên khoa</div><div class="d-flex flex-column gap-1">';
    currentSpecialtyOptions().forEach(function (name) {
        const safeName = name.replace(/"/g, '&quot;');
        const checked = selected.includes(name) ? ' checked' : '';
        html += '<label class="form-check-label small"><input type="checkbox" name="service_specialty[' + index + '][]" value="' + safeName + '" class="form-check-input"' + checked + '> ' + name + '</label>';
    });
    return html + '</div></div>';
}

function syncSpecialtyMode() {
    const bookingFlowValue = document.getElementById('bookingFlow').value;
    const enabled = ['specialty_first', 'service_first'].includes(bookingFlowValue);
    const showServiceSchedule = bookingFlowValue !== 'doctor_first';
    document.getElementById('specialtyRows').classList.toggle('d-none', !enabled);
    document.getElementById('addSpecialty').classList.toggle('d-none', !enabled);
    document.querySelectorAll('.service-specialty-select').forEach(function (element) {
        element.classList.toggle('d-none', !enabled);
        if (enabled && element.innerHTML.trim() === '') {
            const index = Array.from(document.querySelectorAll('.service-row')).indexOf(element.closest('.service-row'));
            element.innerHTML = buildSpecialtySelect(index);
        }
    });
    document.querySelectorAll('.service-schedule-select').forEach(function (element) {
        element.classList.toggle('d-none', !showServiceSchedule);
        if (!showServiceSchedule) {
            element.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
                checkbox.checked = false;
            });
        }
    });
    const hospitalTimeSettings = document.getElementById('hospitalTimeSettings');
    if (hospitalTimeSettings) {
        hospitalTimeSettings.classList.toggle('d-none', !showServiceSchedule);
    }
}

document.getElementById('bookingFlow').addEventListener('change', syncSpecialtyMode);
syncSpecialtyMode();
document.getElementById('addSpecialty').addEventListener('click', function () {
    const row = document.createElement('div');
    row.className = 'row g-2 specialty-row';
    row.innerHTML = '<div class="col-10"><input type="text" name="specialty_names[]" class="form-control specialty-name-input" placeholder="Tên chuyên khoa"></div><div class="col-2"><button type="button" class="btn btn-outline-danger w-100 remove-specialty">×</button></div>';
    document.getElementById('specialtyRows').appendChild(row);
    captureFormDraft();
});

document.addEventListener('input', function (event) {
    if (event.target.classList.contains('specialty-name-input')) {
        document.querySelectorAll('.service-specialty-select').forEach(function (wrapper) {
            const selected = Array.from(wrapper.querySelectorAll('input[type="checkbox"]:checked')).map(function (input) { return input.value; });
            const index = Array.from(document.querySelectorAll('.service-row')).indexOf(wrapper.closest('.service-row'));
            wrapper.innerHTML = buildSpecialtySelect(index, selected);
        });
    }
});

const addServiceButton = document.getElementById('addService');
if (addServiceButton) {
addServiceButton.addEventListener('click', function () {
    const wrapper = document.getElementById('serviceRows');
    const row = document.createElement('div');
    const index = document.querySelectorAll('.service-row').length;
    row.className = 'row g-2 service-row border rounded-3 p-2';
    row.innerHTML = '<input type="hidden" name="service_target[]" value="specialty"><div class="col-md-4 service-specialty-select ' + (['specialty_first', 'service_first'].includes(document.getElementById('bookingFlow').value) ? '' : 'd-none') + '">' + buildSpecialtySelect(index) + '</div><div class="col-md-3"><input type="text" name="service_name[]" class="form-control" placeholder="Tên dịch vụ"></div><div class="col-md-1"><input type="text" name="service_icon[]" class="form-control" placeholder="Icon" value="bi-calendar2-check" title="Bootstrap icon, ví dụ: bi-calendar2-check"></div><div class="col-md-4 service-schedule-select ' + ((document.getElementById('bookingFlow').value === 'doctor_first') ? 'd-none' : '') + '"><div class="border rounded-3 p-2 bg-white"><div class="fw-semibold small mb-2">Ngày khám</div><div class="d-flex flex-wrap gap-2"><label class="form-check-label small me-2"><input type="checkbox" name="service_schedule[' + index + '][]" value="1" class="form-check-input"> Thứ 2</label><label class="form-check-label small me-2"><input type="checkbox" name="service_schedule[' + index + '][]" value="2" class="form-check-input"> Thứ 3</label><label class="form-check-label small me-2"><input type="checkbox" name="service_schedule[' + index + '][]" value="3" class="form-check-input"> Thứ 4</label><label class="form-check-label small me-2"><input type="checkbox" name="service_schedule[' + index + '][]" value="4" class="form-check-input"> Thứ 5</label><label class="form-check-label small me-2"><input type="checkbox" name="service_schedule[' + index + '][]" value="5" class="form-check-input"> Thứ 6</label><label class="form-check-label small me-2"><input type="checkbox" name="service_schedule[' + index + '][]" value="6" class="form-check-input"> Thứ 7</label><label class="form-check-label small me-2"><input type="checkbox" name="service_schedule[' + index + '][]" value="0" class="form-check-input"> CN</label></div></div></div><div class="col-md-3"><input type="number" step="1" min="0" name="service_price[]" class="form-control" placeholder="Giá"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-service">×</button></div><div class="col-12"><textarea name="service_detail[]" class="form-control" rows="3" placeholder="Nội dung chi tiết hiện khi bệnh nhân chọn dịch vụ"></textarea></div><div class="col-12"><label class="form-check-label"><input type="checkbox" name="requires_insurance[' + index + ']" class="form-check-input"> Dịch vụ này yêu cầu bệnh nhân chọn Có/Không BHYT</label></div>';
    wrapper.appendChild(row);
    captureFormDraft();
});
}

document.addEventListener('change', function (event) {
    if (event.target.classList.contains('specialty-toggle')) {
        const input = event.target.closest('.service-row').querySelector('.specialty-input');
        input.classList.toggle('d-none', !event.target.checked);
        if (!event.target.checked) {
            input.value = '';
        }
    }
});

document.addEventListener('click', function (event) {
    if (event.target.classList.contains('remove-specialty')) {
        const rows = document.querySelectorAll('.specialty-row');
        if (rows.length > 1) {
            event.target.closest('.specialty-row').remove();
            captureFormDraft();
        }
    }
    if (event.target.classList.contains('remove-time-slot')) {
        const row = event.target.closest('.time-slot-row');
        const rows = row.closest('.time-slot-group').querySelectorAll('.time-slot-row');
        if (rows.length > 1) {
            row.remove();
            captureFormDraft();
        } else {
            row.querySelectorAll('select').forEach(function (select) {
                select.value = '';
            });
            captureFormDraft();
        }
    }
    if (event.target.classList.contains('remove-service')) {
        const rows = document.querySelectorAll('.service-row');
        if (rows.length > 1) {
            event.target.closest('.service-row').remove();
            captureFormDraft();
        } else {
            const row = event.target.closest('.service-row');
            row.querySelectorAll('input, textarea, select').forEach(function (field) {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = false;
                } else if (field.name === 'service_icon[]') {
                    field.value = 'bi-calendar2-check';
                } else if (field.name === 'service_target[]') {
                    field.value = 'specialty';
                } else {
                    field.value = '';
                }
            });
            captureFormDraft();
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
