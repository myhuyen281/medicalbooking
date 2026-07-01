<?php
require_once 'config/database.php';
include 'includes/header.php';

$facilityName = isset($_GET['facility']) ? trim($_GET['facility']) : 'Bệnh viện tại Cần Thơ';
$facilityAddress = isset($_GET['address']) ? trim($_GET['address']) : 'Thành phố Cần Thơ';
$selectedServiceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
$selectedInsuranceValue = $_GET['insurance'] ?? '';
$bookingFormId = isset($_GET['booking_form_id']) ? (int)$_GET['booking_form_id'] : 0;
$facilityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = new Database();
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
    $db->query("ALTER TABLE hospital_services ADD COLUMN specialty_name VARCHAR(255) NULL AFTER name");
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
try {
    $db->query("ALTER TABLE doctors ADD COLUMN doctor_image_url VARCHAR(500) NULL AFTER description");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE doctors ADD COLUMN treatment_text VARCHAR(255) NULL AFTER doctor_image_url");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE doctors ADD COLUMN academic_title VARCHAR(100) NULL AFTER treatment_text");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE doctors ADD COLUMN gender VARCHAR(20) NULL AFTER academic_title");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE doctors ADD COLUMN display_schedule_text VARCHAR(255) NULL AFTER gender");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("ALTER TABLE doctors ADD COLUMN display_price_text VARCHAR(100) NULL AFTER display_schedule_text");
    $db->execute();
} catch (Exception $e) {
}
if ($facilityId > 0) {
    $db->query("SELECT * FROM hospitals WHERE id = :id LIMIT 1");
    $db->bind(':id', $facilityId);
} else {
    $db->query("SELECT h.*
                FROM hospitals h
                LEFT JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital'
                WHERE h.name = :name OR h.name LIKE :like_name
                ORDER BY CASE WHEN u.id IS NOT NULL THEN 0 ELSE 1 END, h.id DESC
                LIMIT 1");
    $db->bind(':name', $facilityName);
    $db->bind(':like_name', '%' . $facilityName . '%');
}
$hospital = $db->single();
$services = [];
$doctors = [];
$selectedService = null;
$selectedServicePriceDisplay = '';
$selectedServiceWeekdaysForJs = [];
$selectedServiceText = '<i class="bi bi-hand-index-thumb-fill me-2" style="color: #00a8f0;"></i> Chọn dịch vụ';
$bookingAdvanceDays = 30;
$bookingTimeSlots = [];
$bookingFlow = 'service_only';
$serviceSpecialties = [];
$selectedSpecialtyName = '';
function serviceSpecialtyValues($value) {
    $decoded = json_decode((string)$value, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('trim', $decoded), 'strlen'));
    }
    return array_values(array_filter(array_map('trim', explode(',', (string)$value)), 'strlen'));
}
$weekdayLabels = [
    1 => 'Thứ 2',
    2 => 'Thứ 3',
    3 => 'Thứ 4',
    4 => 'Thứ 5',
    5 => 'Thứ 6',
    6 => 'Thứ 7',
    0 => 'CN'
];
if ($hospital) {
    $facilityName = $hospital['name'];
    $facilityAddress = $hospital['address'] ?: $facilityAddress;
    $bookingAdvanceDays = max(1, (int)($hospital['booking_advance_days'] ?? 30));
    $bookingFlow = in_array($hospital['booking_flow'] ?? 'service_only', ['service_only', 'specialty_first', 'service_first', 'doctor_first'], true) ? $hospital['booking_flow'] : 'service_only';
    if ($bookingFormId > 0) {
        $db->query("SELECT target, name FROM hospital_booking_forms WHERE id = :booking_form_id AND hospital_id = :hospital_id LIMIT 1");
        $db->bind(':booking_form_id', $bookingFormId);
        $db->bind(':hospital_id', $hospital['id']);
        $bookingForm = $db->single();
        $bookingFormName = strtolower($bookingForm['name'] ?? '');
        if (($bookingForm['target'] ?? '') === 'doctor' || strpos($bookingFormName, 'chuyên gia') !== false) {
            $bookingFlow = 'doctor_first';
        }
    }
    if (!empty($hospital['booking_time_slots'])) {
        $decodedSlots = json_decode($hospital['booking_time_slots'], true);
        $bookingTimeSlots = is_array($decodedSlots) ? $decodedSlots : [];
    }
    if ($selectedServiceId > 0) {
        $db->query("SELECT * FROM hospital_services WHERE hospital_id = :hospital_id AND id = :service_id ORDER BY id ASC");
        $db->bind(':hospital_id', $hospital['id']);
        $db->bind(':service_id', $selectedServiceId);
        $selectedService = $db->single();
    }
    if ($bookingFormId > 0) {
        $db->query("SELECT * FROM hospital_services WHERE hospital_id = :hospital_id AND booking_form_id = :booking_form_id ORDER BY id ASC");
        $db->bind(':hospital_id', $hospital['id']);
        $db->bind(':booking_form_id', $bookingFormId);
    } else {
        $db->query("SELECT * FROM hospital_services WHERE hospital_id = :hospital_id AND booking_form_id IS NULL ORDER BY id ASC");
        $db->bind(':hospital_id', $hospital['id']);
    }
    $services = $db->resultSet();
    if ($selectedService) {
        $selectedServicePriceDisplay = number_format((float)($selectedService['price'] ?? 0), 0, ',', '.') . 'đ';
    } elseif ($bookingFlow === 'doctor_first' && count($services) > 0) {
        $selectedServicePriceDisplay = number_format((float)($services[0]['price'] ?? 0), 0, ',', '.') . 'đ';
    }
    $selectedServiceWeekdaysForJs = [];
    $selectedServiceText = '<i class="bi bi-hand-index-thumb-fill me-2" style="color: #00a8f0;"></i> Chọn dịch vụ';
    if ($selectedService) {
        $selectedServiceSpecialtyValues = serviceSpecialtyValues($selectedService['specialty_name'] ?? '');
        $selectedSpecialtyName = $selectedServiceSpecialtyValues[0] ?? '';
        $selectedServiceWeekdaysForJs = array_values(array_filter(explode(',', (string)($selectedService['schedule_text'] ?? '')), 'strlen'));
        $selectedServiceText = '<div><i class="bi bi-hand-index-thumb-fill me-2" style="color: #00a8f0;"></i>' . htmlspecialchars($selectedService['name']) . ' - ' . htmlspecialchars($selectedServicePriceDisplay) . '</div>';
        if (!empty($selectedService['requires_insurance']) && in_array($selectedInsuranceValue, ['Có', 'Không'], true)) {
            $selectedServiceText .= '<div class="badge mt-2 px-3 py-2 fw-medium" style="background-color:#e8f4ff; color:#00a8f0;">Khám ' . ($selectedInsuranceValue === 'Có' ? 'có' : 'không') . ' BHYT</div>';
        }
    }
    if ($bookingFlow === 'doctor_first') {
        $db->query("SELECT d.id, u.full_name, s.name AS specialty_name, d.experience_years, d.consultation_fee, d.doctor_image_url, d.treatment_text, d.academic_title, d.gender, d.display_schedule_text, d.display_price_text
                    FROM doctors d
                    INNER JOIN users u ON d.user_id = u.id
                    LEFT JOIN specialties s ON d.specialty_id = s.id
                    WHERE d.hospital_id = :hospital_id AND d.approval_status = 'approved'
                    ORDER BY u.full_name ASC");
        $db->bind(':hospital_id', $hospital['id']);
        $doctors = $db->resultSet();
        $doctorIds = array_map('intval', array_column($doctors, 'id'));
        if (count($doctorIds) > 0) {
            $db->query("SELECT id, doctor_id, work_date, start_time, end_time
                        FROM schedules
                        WHERE doctor_id IN (" . implode(',', $doctorIds) . ")
                          AND status = 'available'
                          AND work_date >= CURDATE()
                        ORDER BY work_date ASC");
            $doctorScheduleRows = $db->resultSet();
            $doctorScheduleMap = [];
            $doctorDateMap = [];
            $doctorSlotMap = [];
            foreach ($doctorScheduleRows as $scheduleRow) {
                $doctorId = (int)$scheduleRow['doctor_id'];
                $weekdayIndex = (int)date('w', strtotime($scheduleRow['work_date']));
                $weekdayLabel = $weekdayLabels[$weekdayIndex] ?? '';
                if ($weekdayLabel !== '') {
                    $doctorScheduleMap[$doctorId][] = $weekdayLabel;
                }
                $doctorDateMap[$doctorId][] = $scheduleRow['work_date'];
                $doctorSlotMap[$doctorId][$scheduleRow['work_date']][] = [
                    'schedule_id' => (int)$scheduleRow['id'],
                    'start' => substr((string)$scheduleRow['start_time'], 0, 5),
                    'end' => substr((string)$scheduleRow['end_time'], 0, 5)
                ];
            }
            foreach ($doctors as $index => $doctor) {
                $labels = array_values(array_unique($doctorScheduleMap[(int)$doctor['id']] ?? []));
                $doctors[$index]['available_dates'] = array_values(array_unique($doctorDateMap[(int)$doctor['id']] ?? []));
                $doctors[$index]['available_slots'] = $doctorSlotMap[(int)$doctor['id']] ?? [];
                $doctors[$index]['schedule_text'] = count($labels) ? implode(', ', array_slice($labels, 0, 3)) : 'Đang cập nhật';
            }
        }
        foreach ($doctors as $index => $doctor) {
            if (!empty($doctor['display_schedule_text'])) {
                $doctors[$index]['schedule_text'] = $doctor['display_schedule_text'];
            }
            $doctors[$index]['treatment_display'] = !empty($doctor['treatment_text']) ? $doctor['treatment_text'] : 'Đang cập nhật';
            $doctors[$index]['price_display'] = $selectedServicePriceDisplay !== '' ? $selectedServicePriceDisplay : 'Chọn dịch vụ để xem giá';
            $doctors[$index]['image_display'] = !empty($doctor['doctor_image_url']) ? $doctor['doctor_image_url'] : 'https://ui-avatars.com/api/?name=' . urlencode($doctor['full_name']) . '&background=eaf7ff&color=00a8f0&size=160';
        }
    }
    foreach ($services as $service) {
        $specialtyNames = serviceSpecialtyValues($service['specialty_name'] ?? '');
        foreach ($specialtyNames as $specialtyName) {
            if (!in_array($specialtyName, $serviceSpecialties, true)) {
                $serviceSpecialties[] = $specialtyName;
            }
        }
    }
    if ($bookingFlow === 'service_only' && count($serviceSpecialties) > 0) {
        $bookingFlow = 'specialty_first';
    }
    if ($bookingFlow === 'specialty_first') {
        $db->query("SELECT DISTINCT COALESCE(NULLIF(d.treatment_text, ''), s.name) AS specialty_name
                    FROM doctors d
                    LEFT JOIN specialties s ON d.specialty_id = s.id
                    WHERE d.hospital_id = :hospital_id AND d.approval_status = 'approved'
                    ORDER BY specialty_name ASC");
        $db->bind(':hospital_id', $hospital['id']);
        foreach ($db->resultSet() as $doctorSpecialty) {
            $specialtyName = trim($doctorSpecialty['specialty_name'] ?? '');
            if ($specialtyName !== '' && !in_array($specialtyName, $serviceSpecialties, true)) {
                $serviceSpecialties[] = $specialtyName;
            }
        }
    }
}
?>

<div class="px-2 px-md-4 pb-4" style="background-color: #eaf7fc; min-height: 560px;">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb fw-semibold mb-0">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none" style="color: #023f6d;">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="facility_booking_options.php?facility=<?php echo urlencode($facilityName); ?>" class="text-decoration-none" style="color: #023f6d;"><?php echo htmlspecialchars($facilityName); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page" style="color: #00b5f1;">Đặt khám theo chuyên khoa</li>
        </ol>
    </nav>

    <div class="row g-3">
        <div class="col-lg-3">
            <div class="bg-white rounded-1 overflow-hidden">
                <div class="text-white fw-bold p-3" style="background-color: #1da1f2;">Thông tin cơ sở y tế</div>
                <div class="p-3">
                    <h6 class="fw-bold mb-2" style="color: #023f6d;"><?php echo htmlspecialchars($facilityName); ?></h6>
                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($facilityAddress); ?></p>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="bg-white">
                <div class="text-white p-3" style="background-color: #1da1f2;">
                    <div class="d-flex align-items-center justify-content-between">
                        <a href="facility_booking_options.php?facility=<?php echo urlencode($facilityName); ?>" class="text-white fs-4 text-decoration-none"><i class="bi bi-arrow-left-short"></i></a>
                        <h5 class="fw-bold mb-0">Chọn thông tin khám</h5>
                        <span style="width: 28px;"></span>
                    </div>
                    <div class="d-flex align-items-center gap-3 mt-3 px-2">
                        <div class="rounded-circle border border-3 border-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="bi bi-stethoscope fs-5"></i></div>
                        <div class="flex-fill border-top border-white"></div>
                        <i class="bi bi-person-fill fs-5 opacity-75"></i>
                        <div class="flex-fill border-top border-white"></div>
                        <i class="bi bi-check-circle-fill fs-5 opacity-75"></i>
                        <div class="flex-fill border-top border-white"></div>
                        <i class="bi bi-wallet2 fs-5 opacity-75"></i>
                    </div>
                </div>

                <div class="p-3 p-md-4">
                    <?php if ($bookingFlow === 'specialty_first' && count($serviceSpecialties) > 0): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Chuyên khoa <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-outline-info w-100 text-start d-flex align-items-center justify-content-between rounded-3 py-2 px-3" onclick="openBookingModal('specialtyModal')" style="border-color: #00a8f0; color: #023f6d;"><div id="selectedSpecialtyText"><i class="bi bi-stethoscope me-2" style="color: #00a8f0;"></i> <?php echo $selectedSpecialtyName !== '' ? htmlspecialchars($selectedSpecialtyName) : 'Chọn chuyên khoa'; ?></div><i class="bi bi-chevron-right"></i></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($bookingFlow === 'doctor_first'): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Bác sĩ <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-outline-info w-100 text-start d-flex align-items-center justify-content-between rounded-3 py-2 px-3" onclick="openBookingModal('doctorModal')" style="border-color: #00a8f0; color: #023f6d;"><div id="selectedDoctorText"><i class="bi bi-person-badge me-2" style="color: #00a8f0;"></i> Chọn bác sĩ</div><i class="bi bi-chevron-right"></i></button>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3 <?php echo ($bookingFlow === 'specialty_first' && count($serviceSpecialties) > 0 && !$selectedService && !$selectedServiceId) ? 'opacity-50' : ''; ?>" id="serviceField">
                        <label class="form-label fw-bold">Dịch vụ <span class="text-danger">*</span></label>
                        <button type="button" id="serviceSelectButton" class="btn btn-outline-info w-100 text-start d-flex align-items-center justify-content-between rounded-3 py-2 px-3" onclick="openBookingModal('serviceModal')" style="border-color: #00a8f0; color: #023f6d;" <?php echo ($bookingFlow === 'doctor_first' || ($bookingFlow === 'specialty_first' && count($serviceSpecialties) > 0 && !$selectedService && !$selectedServiceId)) ? 'disabled' : ''; ?>><div id="selectedServiceText"><?php echo $selectedServiceText; ?></div><i class="bi bi-chevron-right"></i></button>
                    </div>
                    <?php if ($bookingFlow === 'service_first' && count($serviceSpecialties) > 0): ?>
                        <div class="mb-3 opacity-50" id="specialtyField">
                            <label class="form-label fw-bold">Chuyên khoa <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-outline-info w-100 text-start d-flex align-items-center justify-content-between rounded-3 py-2 px-3" onclick="openBookingModal('specialtyModal')" style="border-color: #00a8f0; color: #023f6d;" disabled><div id="selectedSpecialtyText"><i class="bi bi-stethoscope me-2" style="color: #00a8f0;"></i> <?php echo $selectedSpecialtyName !== '' ? htmlspecialchars($selectedSpecialtyName) : 'Chọn chuyên khoa'; ?></div><i class="bi bi-chevron-right"></i></button>
                        </div>
                    <?php endif; ?>

                    <div class="mb-5">
                        <label class="form-label fw-bold">Ngày khám <span class="text-danger">*</span></label>
                        <p id="datePlaceholder" class="text-muted small mb-3">Chọn thông tin trên để hiển thị ngày giờ khám</p>
                        <div id="dateOptions" class="d-none row g-2"></div>
                    </div>

                    <div class="mb-5">
                        <label class="form-label fw-bold">Giờ khám <span class="text-danger">*</span></label>
                        <p id="timePlaceholder" class="text-muted small mb-3">Chọn thông tin trên để hiển thị ngày giờ khám</p>
                        <div id="timeOptions" class="d-none">
                            <?php if (count($bookingTimeSlots) > 0): ?>
                                <?php
                                    $now = new DateTime();
                                    $currentMinutes = $now->format('H') * 60 + $now->format('i');
                                    $isTodaySelection = isset($_GET['booking_date']) && $_GET['booking_date'] === date('d/m/Y');
                                    $morningSlots = array_filter($bookingTimeSlots, function ($slot) use ($currentMinutes, $isTodaySelection) {
                                        $ok = in_array($slot['period'] ?? (($slot['start'] ?? '') >= '12:00' ? 'afternoon' : 'morning'), ['morning', 'both'], true);
                                        if (!$isTodaySelection) return $ok;
                                        [$h, $m] = array_map('intval', explode(':', $slot['start'] ?? '00:00'));
                                        return $ok && ($h * 60 + $m) > $currentMinutes;
                                    });
                                    $afternoonSlots = array_filter($bookingTimeSlots, function ($slot) use ($currentMinutes, $isTodaySelection) {
                                        $ok = in_array($slot['period'] ?? (($slot['start'] ?? '') >= '12:00' ? 'afternoon' : 'morning'), ['afternoon', 'both'], true);
                                        if (!$isTodaySelection) return $ok;
                                        [$h, $m] = array_map('intval', explode(':', $slot['start'] ?? '00:00'));
                                        return $ok && ($h * 60 + $m) > $currentMinutes;
                                    });
                                ?>
                                <?php if (count($morningSlots) > 0): ?>
                                    <div class="fw-bold mb-2" style="color: #023f6d;">Buổi sáng</div>
                                    <div class="row g-2 mb-3">
                                        <?php foreach ($morningSlots as $slot): ?>
                                            <div class="col-md-4"><button type="button" class="btn bg-white w-100 rounded-3 py-3 fw-bold time-card" style="color: #023f6d;"><?php echo htmlspecialchars($slot['start'] . ' - ' . $slot['end']); ?></button></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (count($afternoonSlots) > 0): ?>
                                    <div class="fw-bold mb-2" style="color: #023f6d;">Buổi chiều</div>
                                    <div class="row g-2 mb-3">
                                        <?php foreach ($afternoonSlots as $slot): ?>
                                            <div class="col-md-4"><button type="button" class="btn bg-white w-100 rounded-3 py-3 fw-bold time-card" style="color: #023f6d;"><?php echo htmlspecialchars($slot['start'] . ' - ' . $slot['end']); ?></button></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-muted small">Bệnh viện chưa cập nhật giờ khám.</div>
                            <?php endif; ?>
                            <div class="fw-bold" style="color: #ff9f1c;">Tất cả thời gian theo múi giờ Việt Nam GMT +7</div>
                        </div>
                    </div>

                    <button id="continueButton" class="btn w-100 py-3 text-white fw-bold rounded-3" disabled style="background-color: #d7dce3; opacity: 1;">Tiếp tục</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="specialtyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 justify-content-center position-relative">
                <h5 class="modal-title fw-bold">Chọn chuyên khoa</h5>
                <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-3 px-md-4 pb-4">
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($serviceSpecialties as $specialtyName): ?>
                        <div class="rounded-3 p-3 border specialty-option" role="button" data-specialty="<?php echo htmlspecialchars($specialtyName); ?>">
                            <div class="fw-bold" style="color: #00a8f0;"><i class="bi bi-stethoscope me-2"></i><?php echo htmlspecialchars($specialtyName); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="doctorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 justify-content-center position-relative">
                <h5 class="modal-title fw-bold">Chọn bác sĩ</h5>
                <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-3 px-md-4 pb-4 pt-0">
                <div class="d-flex gap-2 mb-3">
                    <div class="input-group rounded-4 overflow-hidden border bg-white flex-grow-1">
                        <span class="input-group-text border-0 bg-white text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="doctorSearchInput" class="form-control border-0 shadow-none py-3" placeholder="Tìm kiếm bác sĩ...">
                    </div>
                    <button type="button" id="doctorFilterButton" class="btn btn-light border rounded-4 px-3 fw-semibold" style="color:#023f6d;" onclick="openBookingModal('doctorFilterModal')"><i class="bi bi-sliders me-1"></i> Lọc</button>
                </div>
                <div id="doctorList" class="d-flex flex-column gap-3" style="max-height: 448px; overflow-y: auto;">
                    <?php if (count($doctors) > 0): ?>
                        <?php foreach ($doctors as $doctor): ?>
                            <?php $doctorSearchText = trim(($doctor['full_name'] ?? '') . ' ' . ($doctor['treatment_display'] ?? '') . ' ' . ($doctor['schedule_text'] ?? '')); ?>
                            <div class="rounded-3 p-3 border doctor-option bg-white" role="button" data-doctor-id="<?php echo (int)$doctor['id']; ?>" data-doctor="<?php echo htmlspecialchars($doctor['full_name']); ?>" data-specialty="<?php echo htmlspecialchars($doctor['treatment_display'] ?? ''); ?>" data-academic-title="<?php echo htmlspecialchars($doctor['academic_title'] ?? ''); ?>" data-gender="<?php echo htmlspecialchars($doctor['gender'] ?? ''); ?>" data-available-dates="<?php echo htmlspecialchars(json_encode($doctor['available_dates'] ?? [], JSON_UNESCAPED_UNICODE)); ?>" data-available-slots="<?php echo htmlspecialchars(json_encode($doctor['available_slots'] ?? [], JSON_UNESCAPED_UNICODE)); ?>" data-has-schedule="<?php echo (($doctor['schedule_text'] ?? '') !== 'Đang cập nhật') ? '1' : '0'; ?>" data-search="<?php echo htmlspecialchars(strtolower($doctorSearchText)); ?>" style="border-color:#d7e4ec; transition: all .2s ease;">
                                <div class="d-flex align-items-stretch gap-3">
                                    <img src="<?php echo htmlspecialchars($doctor['image_display']); ?>" alt="<?php echo htmlspecialchars($doctor['full_name']); ?>" class="rounded-3 flex-shrink-0" style="width:120px;height:120px;object-fit:cover;">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold mb-2" style="color: #00a8f0; font-size: 1.1rem; line-height: 1.25;"><?php echo htmlspecialchars($doctor['full_name']); ?></div>
                                        <div class="small mb-1" style="color: #023f6d;"><strong>Chuyên trị:</strong> <?php echo htmlspecialchars($doctor['treatment_display'] ?? 'Đa khoa'); ?></div>
                                        <div class="small mb-1" style="color: #023f6d;"><strong>Lịch khám:</strong> <?php echo htmlspecialchars($doctor['schedule_text'] ?? 'Đang cập nhật'); ?></div>
                                        <div class="small" style="color: #023f6d;"><strong>Giá khám:</strong> <?php echo htmlspecialchars($doctor['price_display']); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">Cơ sở y tế chưa cập nhật bác sĩ phù hợp.</div>
                    <?php endif; ?>
                </div>
                <div id="doctorEmptyState" class="d-none text-center text-muted py-4">Không tìm thấy bác sĩ phù hợp.</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="doctorFilterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 560px;">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 justify-content-center position-relative">
                <h5 class="modal-title fw-bold">Tùy chọn</h5>
                <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <div class="fw-bold mb-2" style="color:#00a8f0;">Chuyên khoa</div>
                <div id="filterSpecialtyOptions" class="d-flex flex-wrap gap-2 mb-3"></div>
                <div class="fw-bold mb-2" style="color:#00a8f0;">Học hàm/ học vị</div>
                <div id="filterAcademicOptions" class="d-flex flex-wrap gap-2 mb-3"></div>
                <div class="fw-bold mb-2" style="color:#00a8f0;">Giới tính</div>
                <div id="filterGenderOptions" class="d-flex flex-wrap gap-2 mb-5"></div>
                <div class="row g-2">
                    <div class="col-6"><button type="button" id="resetDoctorFilters" class="btn btn-outline-info w-100 py-3 fw-bold">Làm mới</button></div>
                    <div class="col-6"><button type="button" id="applyDoctorFilters" class="btn btn-info text-white w-100 py-3 fw-bold">Hiển thị kết quả</button></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 justify-content-center position-relative">
                <h5 class="modal-title fw-bold">Chọn dịch vụ</h5>
                <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-3 px-md-4 pb-4">
                <div class="input-group mb-4 rounded-3 overflow-hidden" style="background-color: #f1f1f1;">
                    <span class="input-group-text border-0 bg-transparent text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-0 bg-transparent shadow-none py-3" placeholder="Tìm kiếm dịch vụ...">
                </div>

                <div class="d-flex flex-column gap-3">
                    <?php if (count($services) > 0): ?>
                        <?php foreach ($services as $service): ?>
                            <?php
                                $serviceWeekdays = array_filter(explode(',', (string)($service['schedule_text'] ?? '')), 'strlen');
                                usort($serviceWeekdays, function ($a, $b) use ($weekdayLabels) {
                                    return array_search((int)$a, array_keys($weekdayLabels), true) <=> array_search((int)$b, array_keys($weekdayLabels), true);
                                });
                                $serviceScheduleLabels = array_map(function ($weekday) use ($weekdayLabels) {
                                    return $weekdayLabels[(int)$weekday] ?? null;
                                }, $serviceWeekdays);
                                $serviceScheduleLabels = array_filter($serviceScheduleLabels);
                                $serviceSpecialtyList = serviceSpecialtyValues($service['specialty_name'] ?? '');
                            ?>
                            <a href="specialty_booking.php?id=<?php echo (int)$hospital['id']; ?>&facility=<?php echo urlencode($facilityName); ?><?php echo $bookingFormId > 0 ? '&booking_form_id=' . (int)$bookingFormId : ''; ?>&service_id=<?php echo (int)$service['id']; ?>" class="rounded-3 p-3 border service-option d-block text-decoration-none" role="button" onclick="if (bookingFlow === 'specialty_first' && !selectedSpecialty) { event.preventDefault(); return false; } if (this.dataset.requiresInsurance === '1' && this.querySelector('.insurance-choice')?.classList.contains('d-none')) { event.preventDefault(); this.querySelector('.insurance-choice').classList.remove('d-none'); this.querySelector('.insurance-choice').classList.add('d-flex'); }" data-specialties="<?php echo htmlspecialchars(json_encode($serviceSpecialtyList, JSON_UNESCAPED_UNICODE)); ?>" data-service="<?php echo htmlspecialchars($service['name']); ?>" data-weekdays="<?php echo htmlspecialchars(implode(',', $serviceWeekdays)); ?>" data-detail="<?php echo htmlspecialchars($service['detail_text'] ?? ''); ?>" data-requires-insurance="<?php echo (int)($service['requires_insurance'] ?? 0); ?>" data-price="<?php echo number_format($service['price'], 0, ',', '.'); ?> đ">
                                <div class="fw-bold mb-1" style="color: #00a8f0;"><i class="bi <?php echo htmlspecialchars($service['service_icon'] ?? 'bi-calendar2-check'); ?> me-2"></i><?php echo htmlspecialchars($service['name']); ?></div>
                                <div class="small" style="color: #023f6d;">Lịch khám: <?php echo htmlspecialchars(count($serviceScheduleLabels) ? implode(', ', $serviceScheduleLabels) : 'Đang cập nhật'); ?></div>
                                <div class="fw-bold" style="color: #ff9f1c;">Giá: <?php echo number_format($service['price'], 0, ',', '.'); ?>đ</div>
                                <?php if (!empty($service['requires_insurance'])): ?>
                                    <div class="mt-3 d-none justify-content-end align-items-center gap-3 insurance-choice">
                                        <span style="color:#023f6d;">Bảo hiểm y tế:</span>
                                        <label class="form-check-label"><input type="radio" name="insurance_<?php echo (int)$service['id']; ?>" class="form-check-input insurance-radio" value="Có"> Có</label>
                                        <label class="form-check-label"><input type="radio" name="insurance_<?php echo (int)$service['id']; ?>" class="form-check-input insurance-radio" value="Không" checked> Không</label>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">Bệnh viện chưa cập nhật dịch vụ đặt khám.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="serviceDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 560px;">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 justify-content-center">
                <h5 class="modal-title fw-bold">Thông tin chi tiết</h5>
            </div>
            <div class="modal-body px-4 pb-4">
                <div id="serviceDetailContent" class="lh-lg"></div>
                <button type="button" class="btn text-white fw-bold w-100 mt-3" style="background-color:#00b5f1;" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="calendarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 justify-content-center position-relative">
                <h5 class="modal-title fw-bold">Chọn ngày khám</h5>
                <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-3 pb-3">
                <div class="rounded-3 p-3 mb-3" style="background-color:#e8f4ff; color:#006da8;">
                    <i class="bi bi-info-circle me-1"></i> <strong><?php echo htmlspecialchars($facilityName); ?></strong> hỗ trợ đặt lịch khám bệnh trước từ 1 đến <?php echo (int)$bookingAdvanceDays; ?> ngày.
                </div>
                <div class="rounded-3 overflow-hidden border">
                    <div class="text-white text-center fw-bold py-3 d-flex align-items-center justify-content-between px-3" style="background-color:#1da1f2;">
                        <button type="button" id="prevCalendarMonth" class="btn btn-sm text-white">‹</button>
                        <span id="calendarMonthTitle"></span>
                        <button type="button" id="nextCalendarMonth" class="btn btn-sm text-white">›</button>
                    </div>
                    <div class="calendar-grid fw-bold text-center bg-white">
                        <div>CN</div><div>T2</div><div>T3</div><div>T4</div><div>T5</div><div>T6</div><div>T7</div>
                    </div>
                    <div id="calendarDays" class="calendar-grid text-center bg-white"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.modal { z-index: 2000; }
.modal-backdrop { z-index: 1990; }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
.calendar-grid > div, .calendar-day { min-height: 48px; display: flex; align-items: center; justify-content: center; border: 1px solid #f1f1f1; }
.calendar-day { background: #f8fbff; color: #64748b; }
.calendar-day.available { background: #dff6ff; color: #006da8; cursor: pointer; font-weight: 700; }
.calendar-day.available:hover { background: #1da1f2; color: #fff; }
.calendar-day.selected { background: #1da1f2; color: #fff; }
.calendar-day.disabled { color: #adb5bd; background: #e9ecef; opacity: 1; cursor: not-allowed; }
</style>

<script>
const bookingFlow = '<?php echo $bookingFlow; ?>';
const patientStepUrl = 'booking_patient.php?id=<?php echo (int)($hospital['id'] ?? 0); ?>&facility=<?php echo urlencode($facilityName); ?>&address=<?php echo urlencode($facilityAddress); ?><?php echo $bookingFormId > 0 ? '&booking_form_id=' . (int)$bookingFormId : ''; ?>';
let selectedSpecialty = <?php echo ((in_array($bookingFlow, ['specialty_first', 'service_first'], true) && count($serviceSpecialties) > 0 && $selectedSpecialtyName === '') ? 'false' : 'true'); ?>;
let selectedDoctor = bookingFlow === 'doctor_first' ? false : true;

let selectedDoctorId = '';
let selectedService = <?php echo ($selectedService || $selectedServiceId > 0) ? 'true' : 'false'; ?>;
let selectedInsurance = true;
let pendingServiceDetail = '';
let selectedServiceWeekdays = <?php echo json_encode($selectedServiceWeekdaysForJs); ?>;
let selectedServiceSpecialties = [];
let selectedDoctorDates = [];
let selectedDoctorSlots = {};
let selectedDateKey = '';
let selectedDate = false;
let selectedTime = false;
let selectedDateText = '';
let selectedTimeText = '';
let selectedServiceName = <?php echo json_encode($selectedService['name'] ?? '', JSON_UNESCAPED_UNICODE); ?>;
let selectedServicePrice = <?php echo json_encode($selectedServicePriceDisplay, JSON_UNESCAPED_UNICODE); ?>;
let selectedSpecialtyName = <?php echo json_encode($selectedSpecialtyName, JSON_UNESCAPED_UNICODE); ?>;
let calendarMonth = new Date();
const bookingAdvanceDays = <?php echo (int)$bookingAdvanceDays; ?>;
const specialtySelectButton = document.querySelector('#specialtyField button');
const serviceSelectButton = document.getElementById('serviceSelectButton');
const specialtyField = document.getElementById('specialtyField');
const serviceField = document.getElementById('serviceField');

function updateSelectionAvailability() {
    if (bookingFlow === 'service_first' && specialtySelectButton && specialtyField) {
        specialtySelectButton.disabled = !selectedService;
        specialtyField.classList.toggle('opacity-50', !selectedService);
        specialtySelectButton.style.cursor = selectedService ? 'pointer' : 'not-allowed';
        specialtySelectButton.title = selectedService ? '' : 'Vui lòng chọn dịch vụ trước';
    }
    if (bookingFlow === 'specialty_first' && serviceSelectButton && serviceField) {
        const canSelectService = selectedSpecialty || selectedService;
        serviceSelectButton.disabled = !canSelectService;
        serviceField.classList.toggle('opacity-50', !canSelectService);
        serviceSelectButton.style.cursor = canSelectService ? 'pointer' : 'not-allowed';
        serviceSelectButton.title = canSelectService ? '' : 'Vui lòng chọn chuyên khoa trước';
    }
}

function cleanupModalBackdrop() {
    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
        backdrop.remove();
    });
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
}

function closeBookingModal(modalId) {
    const modalElement = document.getElementById(modalId);
    if (!modalElement) {
        cleanupModalBackdrop();
        return;
    }
    const modal = bootstrap.Modal.getInstance(modalElement);
    if (modal) {
        modal.hide();
    }
    modalElement.classList.remove('show');
    modalElement.style.display = 'none';
    modalElement.setAttribute('aria-hidden', 'true');
    modalElement.removeAttribute('aria-modal');
    modalElement.removeAttribute('role');
    cleanupModalBackdrop();
}

function openBookingModal(modalId) {
    cleanupModalBackdrop();
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
}

function updateContinueButton() {
    const continueButton = document.getElementById('continueButton');
    if (selectedSpecialty && selectedDoctor && selectedService && selectedInsurance && selectedDate && selectedTime) {
        continueButton.disabled = false;
        continueButton.style.backgroundColor = '#00b5f1';
    } else {
        continueButton.disabled = true;
        continueButton.style.backgroundColor = '#d7dce3';
    }
}

function getTodayKey() {
    const now = new Date();
    return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
}

function getCurrentMinutes() {
    const now = new Date();
    return now.getHours() * 60 + now.getMinutes();
}

function isFutureTimeSlot(slotStart, dateKey) {
    if (dateKey !== getTodayKey()) {
        return true;
    }
    const [h, m] = String(slotStart || '00:00').split(':').map(Number);
    return (h * 60 + m) > getCurrentMinutes();
}

function renderStaticTimeOptions(dateKey) {
    const timeOptions = document.getElementById('timeOptions');
    const timePlaceholder = document.getElementById('timePlaceholder');
    const allTimeCards = Array.from(document.querySelectorAll('#timeOptions .time-card'));

    allTimeCards.forEach(function (button) {
        const start = button.textContent.trim().split('-')[0].trim();
        const available = isFutureTimeSlot(start, dateKey);
        button.classList.toggle('d-none', !available);
        button.disabled = !available;
        button.closest('.col-md-4')?.classList.toggle('d-none', !available);
    });

    document.querySelectorAll('#timeOptions .fw-bold.mb-2').forEach(function (title) {
        const row = title.nextElementSibling;
        if (!row || !row.classList.contains('row')) return;
        const hasVisible = Array.from(row.querySelectorAll('.col-md-4')).some(function (col) {
            return !col.classList.contains('d-none');
        });
        title.classList.toggle('d-none', !hasVisible);
        row.classList.toggle('d-none', !hasVisible);
    });

    const hasAnyVisible = Array.from(document.querySelectorAll('#timeOptions .time-card')).some(function (button) {
        return !button.classList.contains('d-none');
    });

    document.getElementById('emptyStaticTimeNotice')?.remove();
    if (!hasAnyVisible) {
        const notice = document.createElement('div');
        notice.id = 'emptyStaticTimeNotice';
        notice.className = 'text-muted small';
        notice.textContent = 'Không còn giờ khám trống trong ngày này.';
        timeOptions.insertBefore(notice, timeOptions.lastElementChild);
    }

    timePlaceholder.classList.add('d-none');
    timeOptions.classList.remove('d-none');
}

function renderDateOptions(weekdays, selectedDate = null) {
    const dateOptions = document.getElementById('dateOptions');
    const weekdaySet = weekdays.length ? new Set(weekdays.map(String)) : null;
    const weekdayLabels = ['CN', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
    dateOptions.innerHTML = '';

    const displayDates = [];
    if (selectedDate) {
        displayDates.push(new Date(selectedDate));
    }
    for (let offset = 0; displayDates.length < 3 && offset < bookingAdvanceDays; offset++) {
        const date = new Date();
        date.setDate(date.getDate() + offset);
        if ((selectedDate && date.toDateString() === selectedDate.toDateString()) || !isDateAvailable(date)) {
            continue;
        }
        displayDates.push(date);
    }

    displayDates.forEach(function (date, index) {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const col = document.createElement('div');
        col.className = 'col-md-3';
        const active = selectedDate && index === 0;
        const dateKey = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        col.innerHTML = '<button type="button" class="btn w-100 rounded-3 py-3 fw-bold date-card" data-date="' + dateKey + '" style="border: 1px solid ' + (active ? '#00a8f0' : '#edf1f5') + '; background-color: ' + (active ? '#e8f8ff' : '#fff') + '; color: #006da8; opacity: 1;"><span>(' + day + '/' + month + ')</span><br><span style="color:#006da8;">' + weekdayLabels[date.getDay()] + '</span></button>';
        dateOptions.appendChild(col);
    });

    const otherCol = document.createElement('div');
    otherCol.className = 'col-md-3';
    otherCol.innerHTML = '<button type="button" id="openCalendarButton" class="btn w-100 rounded-3 py-3 fw-bold date-card bg-white" style="color: #023f6d;"><i class="bi bi-calendar3 d-block mb-1" style="color: #00a8f0;"></i>Ngày khác</button>';
    dateOptions.appendChild(otherCol);
}

function isDateAvailable(date) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const maxDate = new Date(today);
    maxDate.setDate(today.getDate() + bookingAdvanceDays - 1);
    const compareDate = new Date(date);
    compareDate.setHours(0, 0, 0, 0);
    if (bookingFlow === 'doctor_first') {
        const dateKey = compareDate.getFullYear() + '-' + String(compareDate.getMonth() + 1).padStart(2, '0') + '-' + String(compareDate.getDate()).padStart(2, '0');
        return compareDate >= today && compareDate <= maxDate && selectedDoctorDates.includes(dateKey);
    }
    const weekdayAllowed = selectedServiceWeekdays.length ? selectedServiceWeekdays.includes(String(compareDate.getDay())) : true;
    return compareDate >= today && compareDate <= maxDate && weekdayAllowed;
}

function renderDoctorTimeOptions(dateKey) {
    const timeOptions = document.getElementById('timeOptions');
    const timePlaceholder = document.getElementById('timePlaceholder');
    const slots = selectedDoctorSlots[dateKey] || [];
    selectedDateKey = dateKey;
    timeOptions.innerHTML = '';

    const now = new Date();
    const todayKey = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
    const isToday = dateKey === todayKey;
    const currentTime = now.getHours() * 60 + now.getMinutes();

    const filtered = slots.filter(function (slot) {
        if (!isToday) return true;
        const [h, m] = (slot.start || '00:00').split(':').map(Number);
        return (h * 60 + m) > currentTime;
    });

    if (!filtered.length) {
        timeOptions.innerHTML = '<div class="text-muted small">Không còn giờ khám trống trong ngày này.</div>';
    } else {
        const morningSlots = filtered.filter(function (slot) { return (slot.start || '') < '12:00'; });
        const afternoonSlots = filtered.filter(function (slot) { return (slot.start || '') >= '12:00'; });
        [
            { title: 'Buổi sáng', items: morningSlots },
            { title: 'Buổi chiều', items: afternoonSlots }
        ].forEach(function (group) {
            if (!group.items.length) {
                return;
            }
            const title = document.createElement('div');
            title.className = 'fw-bold mb-2';
            title.style.color = '#023f6d';
            title.textContent = group.title;
            timeOptions.appendChild(title);

            const row = document.createElement('div');
            row.className = 'row g-2 mb-3';
            group.items.forEach(function (slot) {
                const col = document.createElement('div');
                col.className = 'col-md-4';
                col.innerHTML = '<button type="button" class="btn bg-white w-100 rounded-3 py-3 fw-bold time-card" data-schedule-id="' + slot.schedule_id + '" style="color: #023f6d;">' + slot.start + ' - ' + slot.end + '</button>';
                row.appendChild(col);
            });
            timeOptions.appendChild(row);
        });
    }

    timePlaceholder.classList.add('d-none');
    timeOptions.classList.remove('d-none');
}

function renderCalendar() {
    const calendarDays = document.getElementById('calendarDays');
    const calendarMonthTitle = document.getElementById('calendarMonthTitle');
    const year = calendarMonth.getFullYear();
    const month = calendarMonth.getMonth();
    const firstDay = new Date(year, month, 1);
    const startOffset = firstDay.getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    calendarMonthTitle.textContent = 'Tháng ' + (month + 1) + ' - ' + year;
    calendarDays.innerHTML = '';

    for (let i = 0; i < startOffset; i++) {
        const empty = document.createElement('div');
        empty.className = 'calendar-day disabled';
        calendarDays.appendChild(empty);
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        const cell = document.createElement('button');
        cell.type = 'button';
        const available = isDateAvailable(date);
        cell.className = 'calendar-day border-0 w-100' + (available ? ' available' : ' disabled');
        cell.disabled = !available;
        cell.textContent = day;
        if (available) {
            cell.addEventListener('click', function () {
                document.querySelectorAll('.calendar-day.selected').forEach(function (item) {
                    item.classList.remove('selected');
                });
                cell.classList.add('selected');
                selectedDate = true;
                selectedTime = false;
                renderDateOptions(bookingFlow === 'doctor_first' ? [] : selectedServiceWeekdays, date);
                if (bookingFlow === 'doctor_first') {
                    const dateKey = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
                    renderDoctorTimeOptions(dateKey);
                }
                closeBookingModal('calendarModal');
                updateContinueButton();
            });
        }
        calendarDays.appendChild(cell);
    }
}

document.querySelectorAll('.insurance-choice, .insurance-choice *').forEach(function (element) {
    element.addEventListener('click', function (event) {
        event.stopPropagation();
    });
});
document.querySelectorAll('.insurance-radio').forEach(function (radio) {
    radio.addEventListener('change', function () {
        const serviceOption = this.closest('.service-option');
        if (serviceOption) {
            window.location.href = serviceOption.href + '&insurance=' + encodeURIComponent(this.value);
        }
    });
});

if (!selectedSpecialty && bookingFlow === 'specialty_first') {
    document.querySelectorAll('.service-option').forEach(function (item) {
        item.classList.add('d-none');
    });
}
if (bookingFlow === 'doctor_first') {
    document.querySelectorAll('.service-option').forEach(function (item) {
        item.classList.add('d-none');
    });
}

function parseServiceSpecialties(value) {
    try {
        const parsed = JSON.parse(value || '[]');
        return Array.isArray(parsed) ? parsed.map(function (name) { return String(name).trim(); }).filter(Boolean) : [];
    } catch (error) {
        return (value || '').split(',').map(function (name) { return name.trim(); }).filter(Boolean);
    }
}

function filterSpecialtiesForSelectedService() {
    if (bookingFlow !== 'service_first') {
        return;
    }
    document.querySelectorAll('.specialty-option').forEach(function (item) {
        const visible = selectedServiceSpecialties.length === 0 || selectedServiceSpecialties.includes(item.dataset.specialty);
        item.classList.toggle('d-none', !visible);
        item.style.borderColor = '';
        item.style.backgroundColor = '';
    });
}

document.getElementById('specialtyModal').addEventListener('show.bs.modal', function () {
    filterSpecialtiesForSelectedService();
});
document.getElementById('specialtyModal').addEventListener('hidden.bs.modal', cleanupModalBackdrop);

document.getElementById('doctorModal').addEventListener('hidden.bs.modal', cleanupModalBackdrop);
document.getElementById('doctorFilterModal').addEventListener('hidden.bs.modal', cleanupModalBackdrop);

const activeDoctorFilters = { specialty: '', academic: '', gender: '' };

function renderDoctorFilterOptions(targetId, values, filterKey) {
    const wrapper = document.getElementById(targetId);
    if (!wrapper) {
        return;
    }
    wrapper.innerHTML = '';
    Array.from(new Set(values.filter(Boolean))).forEach(function (value) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-info rounded-pill doctor-filter-choice';
        button.dataset.filterKey = filterKey;
        button.dataset.value = value;
        button.textContent = value;
        wrapper.appendChild(button);
    });
}

function refreshDoctorFilterChoices() {
    const doctors = Array.from(document.querySelectorAll('.doctor-option'));
    renderDoctorFilterOptions('filterSpecialtyOptions', doctors.map(function (item) { return item.dataset.specialty || ''; }), 'specialty');
    renderDoctorFilterOptions('filterAcademicOptions', doctors.map(function (item) { return item.dataset.academicTitle || ''; }), 'academic');
    renderDoctorFilterOptions('filterGenderOptions', doctors.map(function (item) { return item.dataset.gender || ''; }), 'gender');
}

function normalizeFilterValue(value) {
    return String(value || '').trim().toLowerCase();
}

function filterDoctorOptions() {
    const input = document.getElementById('doctorSearchInput');
    const emptyState = document.getElementById('doctorEmptyState');
    const keyword = normalizeFilterValue(input ? input.value : '');
    const selectedSpecialty = normalizeFilterValue(activeDoctorFilters.specialty);
    const selectedAcademic = normalizeFilterValue(activeDoctorFilters.academic);
    const selectedGender = normalizeFilterValue(activeDoctorFilters.gender);
    let visibleCount = 0;
    document.querySelectorAll('.doctor-option').forEach(function (item) {
        const itemSpecialty = normalizeFilterValue(item.dataset.specialty);
        const itemAcademic = normalizeFilterValue(item.dataset.academicTitle);
        const itemGender = normalizeFilterValue(item.dataset.gender);
        const matchesKeyword = !keyword || normalizeFilterValue(item.dataset.search).includes(keyword);
        const matchesSpecialty = !selectedSpecialty || (itemSpecialty && (itemSpecialty.includes(selectedSpecialty) || selectedSpecialty.includes(itemSpecialty)));
        const matchesAcademic = !selectedAcademic || (itemAcademic && (itemAcademic.includes(selectedAcademic) || selectedAcademic.includes(itemAcademic)));
        const matchesGender = !selectedGender || itemGender === selectedGender;
        const visible = matchesKeyword && matchesSpecialty && matchesAcademic && matchesGender;
        item.classList.toggle('d-none', !visible);
        if (visible) {
            visibleCount++;
        }
    });
    if (emptyState) {
        emptyState.classList.toggle('d-none', visibleCount > 0);
    }
}

refreshDoctorFilterChoices();
document.getElementById('doctorSearchInput')?.addEventListener('input', filterDoctorOptions);
document.addEventListener('click', function (event) {
    const choice = event.target.closest('.doctor-filter-choice');
    if (!choice) {
        return;
    }
    activeDoctorFilters[choice.dataset.filterKey] = activeDoctorFilters[choice.dataset.filterKey] === choice.dataset.value ? '' : choice.dataset.value;
    document.querySelectorAll('.doctor-filter-choice[data-filter-key="' + choice.dataset.filterKey + '"]').forEach(function (item) {
        item.classList.toggle('active', activeDoctorFilters[choice.dataset.filterKey] === item.dataset.value);
    });
});
document.getElementById('resetDoctorFilters')?.addEventListener('click', function () {
    activeDoctorFilters.specialty = '';
    activeDoctorFilters.academic = '';
    activeDoctorFilters.gender = '';
    document.querySelectorAll('.doctor-filter-choice').forEach(function (item) { item.classList.remove('active'); });
    filterDoctorOptions();
});
document.getElementById('applyDoctorFilters')?.addEventListener('click', function () {
    filterDoctorOptions();
    const filterModal = bootstrap.Modal.getInstance(document.getElementById('doctorFilterModal'));
    filterModal?.hide();
    setTimeout(function () {
        cleanupModalBackdrop();
        const doctorModalElement = document.getElementById('doctorModal');
        if (!doctorModalElement.classList.contains('show')) {
            openBookingModal('doctorModal');
        }
    }, 150);
});

document.querySelectorAll('.doctor-option').forEach(function (button) {
    button.addEventListener('click', function () {
        const doctorName = this.dataset.doctor;
        selectedDoctorId = this.dataset.doctorId || '';
        selectedDoctor = true;
        selectedService = false;
        selectedServiceWeekdays = [];
        try {
            selectedDoctorDates = JSON.parse(this.dataset.availableDates || '[]');
        } catch (error) {
            selectedDoctorDates = [];
        }
        try {
            selectedDoctorSlots = JSON.parse(this.dataset.availableSlots || '{}');
        } catch (error) {
            selectedDoctorSlots = {};
        }
        selectedDateKey = '';
        updateSelectionAvailability();
        const doctorDisplayName = doctorName.replace(/\s*\|.*$/, '').replace(/^(BS\.?|ThS\.?|TS\.?|ThS\.BS\.?|ThS\.BS|BS\.ThS\.BS)\s*/i, '').trim();
        document.getElementById('selectedDoctorText').innerHTML = '<i class="bi bi-person-badge me-2" style="color: #00a8f0;"></i> ' + doctorDisplayName;
        document.getElementById('selectedServiceText').innerHTML = '<i class="bi bi-hand-index-thumb-fill me-2" style="color: #00a8f0;"></i> Chọn dịch vụ';
        document.getElementById('serviceSelectButton')?.removeAttribute('disabled');
        document.querySelectorAll('.doctor-option').forEach(function (item) {
            item.style.borderColor = '';
            item.style.backgroundColor = '';
        });
        this.style.borderColor = '#00a8f0';
        this.style.backgroundColor = '#eefcff';
        document.querySelectorAll('.service-option').forEach(function (item) {
            item.classList.remove('d-none', 'active');
            item.style.borderColor = '';
            item.style.backgroundColor = '';
        });
        selectedDate = false;
        selectedTime = false;
        document.getElementById('dateOptions').classList.add('d-none');
        document.getElementById('timeOptions').classList.add('d-none');
        document.getElementById('datePlaceholder').classList.remove('d-none');
        document.getElementById('timePlaceholder').classList.remove('d-none');
        closeBookingModal('doctorModal');
        updateContinueButton();
    });
});

document.querySelectorAll('.specialty-option').forEach(function (button) {
    button.addEventListener('click', function () {
        const specialtyName = this.dataset.specialty;
        selectedSpecialty = true;
        selectedSpecialtyName = specialtyName;
        if (bookingFlow === 'specialty_first') {
            selectedService = false;
        }
        document.getElementById('selectedSpecialtyText').innerHTML = '<i class="bi bi-stethoscope me-2" style="color: #00a8f0;"></i>' + specialtyName;
        if (bookingFlow === 'specialty_first') {
            document.getElementById('selectedServiceText').innerHTML = '<i class="bi bi-hand-index-thumb-fill me-2" style="color: #00a8f0;"></i> Chọn dịch vụ';
        }
        document.querySelectorAll('.specialty-option').forEach(function (item) {
            item.style.borderColor = '';
            item.style.backgroundColor = '';
        });
        this.style.borderColor = '#00a8f0';
        this.style.backgroundColor = '#eefcff';
        if (bookingFlow === 'specialty_first') {
            document.querySelectorAll('.service-option').forEach(function (item) {
                const itemSpecialties = parseServiceSpecialties(item.dataset.specialties);
                const visible = itemSpecialties.length === 0 || itemSpecialties.includes(specialtyName);
                item.classList.toggle('d-none', !visible);
                item.classList.remove('active');
                item.style.borderColor = '';
                item.style.backgroundColor = '';
            });
            document.getElementById('dateOptions').classList.add('d-none');
            document.getElementById('timeOptions').classList.add('d-none');
            document.getElementById('datePlaceholder').classList.remove('d-none');
            document.getElementById('timePlaceholder').classList.remove('d-none');
        }
        closeBookingModal('specialtyModal');
        updateSelectionAvailability();
        updateContinueButton();
    });
});

function selectServiceOption(option) {
    const serviceName = option.dataset.service;
    const serviceWeekdays = (option.dataset.weekdays || '').split(',').filter(Boolean);
    selectedServiceWeekdays = serviceWeekdays;
    if (bookingFlow === 'service_first') {
        const serviceSpecialties = parseServiceSpecialties(option.dataset.specialties);
        selectedServiceSpecialties = serviceSpecialties;
        selectedSpecialty = false;
        document.getElementById('selectedSpecialtyText').innerHTML = '<i class="bi bi-stethoscope me-2" style="color: #00a8f0;"></i> Chọn chuyên khoa';
        document.querySelectorAll('.specialty-option').forEach(function (item) {
            const visible = serviceSpecialties.length === 0 || serviceSpecialties.includes(item.dataset.specialty);
            item.classList.toggle('d-none', !visible);
            item.style.borderColor = '';
            item.style.backgroundColor = '';
        });
    }
    const requiresInsurance = option.dataset.requiresInsurance === '1';
    const insuranceChoice = option.querySelector('.insurance-choice');
    if (requiresInsurance && insuranceChoice && insuranceChoice.classList.contains('d-none')) {
        document.querySelectorAll('.insurance-choice').forEach(function (choice) {
            choice.classList.add('d-none');
            choice.classList.remove('d-flex');
        });
        document.querySelectorAll('.service-option').forEach(function (item) {
            item.style.borderColor = '';
            item.style.backgroundColor = '';
        });
        insuranceChoice.classList.remove('d-none');
        insuranceChoice.classList.add('d-flex');
        option.style.borderColor = '#00a8f0';
        option.style.backgroundColor = '#eefcff';
        return;
    }
    const servicePrice = option.dataset.price || '';
    selectedServiceName = serviceName;
    selectedServicePrice = servicePrice;
    const insuranceValue = requiresInsurance ? (option.querySelector('.insurance-radio:checked')?.value || 'Không') : '';
    document.getElementById('selectedServiceText').innerHTML = '<div><i class="bi bi-hand-index-thumb-fill me-2" style="color: #00a8f0;"></i>' + serviceName + (servicePrice ? ' - ' + servicePrice : '') + '</div>' + (insuranceValue ? '<div class="badge mt-2 px-3 py-2 fw-medium" style="background-color:#e8f4ff; color:#00a8f0;">Khám ' + (insuranceValue === 'Có' ? 'có' : 'không') + ' BHYT</div>' : '');
    pendingServiceDetail = '';
    document.querySelectorAll('.service-option').forEach(function (item) {
        item.classList.remove('active');
        item.style.borderColor = '';
        item.style.backgroundColor = '';
    });
    option.classList.add('active');
    option.style.borderColor = '#00a8f0';
    option.style.backgroundColor = '#eefcff';
    selectedDate = false;
    selectedTime = false;
    document.getElementById('datePlaceholder').classList.add('d-none');
    renderDateOptions(bookingFlow === 'doctor_first' ? [] : serviceWeekdays);
    document.getElementById('dateOptions').classList.remove('d-none');
    if (bookingFlow === 'doctor_first') {
        document.getElementById('timeOptions').classList.add('d-none');
        document.getElementById('timePlaceholder').classList.remove('d-none');
        document.getElementById('timePlaceholder').textContent = selectedDoctor ? 'Chọn ngày khám để hiển thị giờ khám của bác sĩ' : 'Chọn bác sĩ để hiển thị ngày giờ khám';
        if (!selectedDoctor) cleanupModalBackdrop();
    } else {
        document.getElementById('timePlaceholder').classList.add('d-none');
        document.getElementById('timeOptions').classList.remove('d-none');
    }
    selectedService = true;
    updateSelectionAvailability();
    updateContinueButton();
    closeBookingModal('serviceModal');
}


document.getElementById('serviceModal').addEventListener('show.bs.modal', function (event) {
    if (bookingFlow === 'specialty_first' && !selectedSpecialty) {
        event.preventDefault();
        openBookingModal('specialtyModal');
        return;
    }
    if (bookingFlow === 'doctor_first' && !selectedDoctor) {
        event.preventDefault();
        openBookingModal('doctorModal');
    }
});

updateSelectionAvailability();
if (selectedService) {
    document.getElementById('datePlaceholder').classList.add('d-none');
    renderDateOptions(bookingFlow === 'doctor_first' ? [] : selectedServiceWeekdays);
    document.getElementById('dateOptions').classList.remove('d-none');
    if (bookingFlow !== 'doctor_first') {
        document.getElementById('timePlaceholder').classList.add('d-none');
        document.getElementById('timeOptions').classList.remove('d-none');
    }
    updateContinueButton();
}

document.getElementById('serviceModal').addEventListener('hidden.bs.modal', function () {
    cleanupModalBackdrop();
    if (pendingServiceDetail) {
        document.getElementById('serviceDetailContent').innerHTML = pendingServiceDetail.replace(/\n/g, '<br>');
        pendingServiceDetail = '';
        setTimeout(function () {
            openBookingModal('serviceDetailModal');
        }, 150);
    }
});

document.getElementById('serviceDetailModal').addEventListener('hidden.bs.modal', cleanupModalBackdrop);
document.getElementById('calendarModal').addEventListener('hidden.bs.modal', cleanupModalBackdrop);

document.getElementById('continueButton').addEventListener('click', function () {
    if (this.disabled) {
        return;
    }
    const params = new URLSearchParams();
    params.set('id', <?php echo (int)($hospital['id'] ?? 0); ?>);
    params.set('facility', <?php echo json_encode($facilityName, JSON_UNESCAPED_UNICODE); ?>);
    params.set('address', <?php echo json_encode($facilityAddress, JSON_UNESCAPED_UNICODE); ?>);
    <?php if ($bookingFormId > 0): ?>params.set('booking_form_id', <?php echo (int)$bookingFormId; ?>);<?php endif; ?>
    if (selectedDoctorId) params.set('doctor_id', selectedDoctorId);
    params.set('booking_title', 'Thông tin đặt khám');
    if (selectedServiceName) params.set('booking_service', selectedServiceName);
    if (selectedSpecialtyName) params.set('booking_specialty', selectedSpecialtyName);
    if (selectedDateText) params.set('booking_date', selectedDateText);
    if (selectedTimeText) params.set('booking_time', selectedTimeText);
    if (selectedServicePrice) params.set('booking_price', selectedServicePrice);
    window.location.href = 'booking_patient.php?' + params.toString();
});

document.getElementById('prevCalendarMonth').addEventListener('click', function () {
    calendarMonth.setMonth(calendarMonth.getMonth() - 1);
    renderCalendar();
});

document.getElementById('nextCalendarMonth').addEventListener('click', function () {
    calendarMonth.setMonth(calendarMonth.getMonth() + 1);
    renderCalendar();
});

document.addEventListener('click', function (event) {
    if (event.target.closest('#openCalendarButton')) {
        calendarMonth = new Date();
        renderCalendar();
        openBookingModal('calendarModal');
        return;
    }

    const button = event.target.closest('.date-card, .time-card');
    if (!button) {
        return;
    }
    const group = button.classList.contains('date-card') ? '.date-card' : '.time-card';
    document.querySelectorAll(group).forEach(function (item) {
        item.style.border = '';
        item.style.backgroundColor = '';
        item.style.color = '#023f6d';
    });
    button.style.border = '1px solid #00a8f0';
    button.style.backgroundColor = '#eefcff';
    button.style.color = '#00a8f0';
    if (button.classList.contains('date-card') && !button.id) {
        selectedDate = true;
        selectedTime = false;
        selectedTimeText = '';
        selectedDateText = button.textContent.replace(/\s+/g, ' ').trim();
        document.querySelectorAll('.time-card').forEach(function (item) {
            item.style.border = '';
            item.style.backgroundColor = '';
            item.style.color = '#023f6d';
        });
        if (button.dataset.date && bookingFlow !== 'doctor_first') {
            renderStaticTimeOptions(button.dataset.date);
        }
    }
    if (button.classList.contains('time-card')) {
        if (button.disabled || button.classList.contains('d-none')) {
            return;
        }
        selectedTime = true;
        selectedTimeText = button.textContent.replace(/\s+/g, ' ').trim();
    }
    if (bookingFlow === 'doctor_first' && button.classList.contains('date-card') && button.dataset.date) {
        renderDoctorTimeOptions(button.dataset.date);
    }
    updateContinueButton();
});
</script>

<?php include 'includes/footer.php'; ?>
