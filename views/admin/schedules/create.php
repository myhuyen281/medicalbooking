<?php
require_once '../../../config/database.php';
include '../includes/header.php';

$db = new Database();
$error = '';
$success = '';

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

function uploadDoctorImage($fieldName, &$error) {
    if (empty($_FILES[$fieldName]['name'])) {
        return null;
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        $error = "Không thể upload ảnh bác sĩ.";
        return false;
    }

    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $tmpPath = $_FILES[$fieldName]['tmp_name'];
    $mimeType = mime_content_type($tmpPath);

    if (!isset($allowedTypes[$mimeType])) {
        $error = "Chỉ cho phép upload ảnh JPG, PNG, WEBP hoặc GIF.";
        return false;
    }

    if ($_FILES[$fieldName]['size'] > 5 * 1024 * 1024) {
        $error = "Dung lượng ảnh không được vượt quá 5MB.";
        return false;
    }

    $uploadDir = __DIR__ . '/../../../uploads/doctors/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = 'doctor_' . time() . '_' . mt_rand(1000, 9999) . '.' . $allowedTypes[$mimeType];
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        $error = "Không thể lưu ảnh bác sĩ.";
        return false;
    }

    return 'uploads/doctors/' . $fileName;
}

$hospitalId = $isHospitalAdmin ? $currentHospitalId : ($_GET['hospital_id'] ?? null);
if (!$hospitalId && $isSystemAdmin) {
    $db->query("SELECT id FROM hospitals ORDER BY name ASC LIMIT 1");
    $firstHospital = $db->single();
    $hospitalId = $firstHospital['id'] ?? null;
}

$doctorWhere = $hospitalId ? 'WHERE d.hospital_id = :hospital_id AND d.approval_status = \'approved\'' : 'WHERE d.approval_status = \'approved\'';
$selectedDoctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

$db->query("SELECT d.id, u.full_name, h.name as hospital_name, sp.name as specialty_name, d.doctor_image_url, d.treatment_text, d.academic_title, d.gender, d.display_schedule_text, d.display_price_text
            FROM doctors d
            LEFT JOIN users u ON d.user_id = u.id
            LEFT JOIN hospitals h ON d.hospital_id = h.id
            LEFT JOIN specialties sp ON d.specialty_id = sp.id
            $doctorWhere
            ORDER BY h.name ASC, u.full_name ASC");
if ($hospitalId) {
    $db->bind(':hospital_id', (int)$hospitalId);
}
$doctors = $db->resultSet();

$currentHospital = null;
$bookingAdvanceDays = 30;
if ($hospitalId) {
    $db->query("SELECT booking_advance_days FROM hospitals WHERE id = :id");
    $db->bind(':id', (int)$hospitalId);
    $currentHospital = $db->single();
    $bookingAdvanceDays = max(1, (int)($currentHospital['booking_advance_days'] ?? 30));
}

$editingSchedules = [];
$editingDates = [];
$editingSlots = [];
if ($selectedDoctorId > 0) {
    $maxBookingDate = date('Y-m-d', strtotime('+' . $bookingAdvanceDays . ' days'));
    $db->query("SELECT work_date, start_time, end_time FROM schedules WHERE doctor_id = :doctor_id AND work_date >= CURDATE() AND work_date <= :max_date ORDER BY work_date ASC, start_time ASC");
    $db->bind(':doctor_id', $selectedDoctorId);
    $db->bind(':max_date', $maxBookingDate);
    $editingSchedules = $db->resultSet();
    foreach ($editingSchedules as $editingSchedule) {
        $editingDates[$editingSchedule['work_date']] = $editingSchedule['work_date'];
        $slotKey = date('H:i', strtotime($editingSchedule['start_time'])) . '|' . date('H:i', strtotime($editingSchedule['end_time']));
        $editingSlots[$slotKey] = [
            'start' => date('H:i', strtotime($editingSchedule['start_time'])),
            'end' => date('H:i', strtotime($editingSchedule['end_time']))
        ];
    }
}

function formatServicePrice($price) {
    return number_format((float)$price, 0, ',', '.') . 'đ';
}

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
if ($_SERVER["REQUEST_METHOD"] == "POST" && $isHospitalAdmin && !$currentHospitalSubscriptionActive) {
    $error = hospitalSubscriptionExpiredMessage();
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $hospitalId = $isHospitalAdmin ? $currentHospitalId : ($_POST['hospital_id'] ?? $hospitalId);
    $doctorName = trim($_POST['doctor_name'] ?? '');
    $selectedDates = [];
    foreach ($_POST['work_dates'] ?? [] as $dateValue) {
        $dateValue = trim($dateValue);
        $dateObj = DateTime::createFromFormat('Y-m-d', $dateValue);
        if ($dateObj && $dateObj->format('Y-m-d') === $dateValue && $dateValue >= date('Y-m-d') && !in_array($dateValue, $selectedDates, true)) {
            $selectedDates[] = $dateValue;
        }
    }
    sort($selectedDates);
    $enabledPeriods = $_POST['enabled_periods'] ?? [];
    $slotPeriods = $_POST['slot_period'] ?? [];
    $slotStarts = $_POST['slot_start'] ?? [];
    $slotEnds = $_POST['slot_end'] ?? [];
    $timeSlots = [];
    $timeSlotKeys = [];
    $hasDuplicateTimeSlot = false;
    foreach ($slotStarts as $slotIndex => $slotStart) {
        $slotStart = trim($slotStart);
        $slotEnd = trim($slotEnds[$slotIndex] ?? '');
        $slotPeriod = in_array($slotPeriods[$slotIndex] ?? 'morning', ['morning', 'afternoon'], true) ? $slotPeriods[$slotIndex] : 'morning';
        if (!in_array($slotPeriod, $enabledPeriods, true) || $slotStart === '' || $slotEnd === '' || $slotStart >= $slotEnd) {
            continue;
        }
        if ($slotPeriod === 'morning' && ($slotStart >= '12:00' || $slotEnd > '12:00')) {
            continue;
        }
        if ($slotPeriod === 'afternoon' && $slotStart < '13:00') {
            continue;
        }
        $slotKey = $slotStart . '|' . $slotEnd;
        if (isset($timeSlotKeys[$slotKey])) {
            $hasDuplicateTimeSlot = true;
            continue;
        }
        $timeSlotKeys[$slotKey] = true;
        $timeSlots[] = ['start' => $slotStart, 'end' => $slotEnd];
    }
    $doctorImageUrl = trim($_POST['current_doctor_image_url'] ?? '');
    $uploadedDoctorImage = uploadDoctorImage('doctor_image_file', $error);
    if ($uploadedDoctorImage) {
        $doctorImageUrl = $uploadedDoctorImage;
    }
    $treatmentText = trim($_POST['treatment_text'] ?? '');
    $academicTitle = trim($_POST['academic_title'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $displayScheduleText = '';
    $displayPriceText = '';
    $consultationFee = 0;

    if ($uploadedDoctorImage === false) {
    } elseif ($hasDuplicateTimeSlot) {
        $error = "Không được chọn 2 khung giờ khám giống nhau.";
    } elseif (empty($doctorName) || empty($hospitalId) || empty($selectedDates) || empty($timeSlots)) {
        $error = "Vui lòng nhập đầy đủ thông tin.";
    } else {
        $plan = getHospitalSubscriptionPlan($db, $hospitalId);
        $dailyScheduleLimit = hospitalPlanLimit($plan, 'daily_schedule_limit');
        if ($dailyScheduleLimit !== null) {
            $requestedByDate = [];
            foreach ($selectedDates as $selectedDate) {
                $requestedByDate[$selectedDate] = ($requestedByDate[$selectedDate] ?? 0) + count($timeSlots);
            }
            foreach ($requestedByDate as $date => $requestedCount) {
                if ($requestedCount > $dailyScheduleLimit) {
                    $error = hospitalPlanLimitMessage($plan, 'tối đa lịch khám/ngày', $dailyScheduleLimit);
                    break;
                }
            }
        }
    }

    if (empty($error)) {
        $db->query("SELECT d.id FROM doctors d INNER JOIN users u ON d.user_id = u.id WHERE d.hospital_id = :hospital_id AND u.full_name = :doctor_name LIMIT 1");
        $db->bind(':hospital_id', (int)$hospitalId);
        $db->bind(':doctor_name', $doctorName);
        $doctor = $db->single();

        if (!$doctor) {
            $doctorLimit = hospitalPlanLimit($plan, 'doctor_limit');
            if ($doctorLimit !== null) {
                $db->query("SELECT COUNT(*) AS total FROM doctors WHERE hospital_id = :hospital_id");
                $db->bind(':hospital_id', (int)$hospitalId);
                $doctorCount = (int)($db->single()['total'] ?? 0);
                if ($doctorCount >= $doctorLimit) {
                    $error = hospitalPlanLimitMessage($plan, 'tối đa bác sĩ', $doctorLimit);
                }
            }
        }

        if (!$doctor && empty($error)) {
            $db->query("SELECT hs.specialty_id
                        FROM hospital_specialties hs
                        LEFT JOIN specialties sp ON sp.id = hs.specialty_id
                        WHERE hs.hospital_id = :hospital_id
                        ORDER BY CASE WHEN LOWER(sp.name) LIKE '%khám bệnh%' THEN 0 ELSE 1 END, hs.specialty_id ASC
                        LIMIT 1");
            $db->bind(':hospital_id', (int)$hospitalId);
            $defaultSpecialty = $db->single();
            if (!$defaultSpecialty) {
                $db->query("SELECT id AS specialty_id FROM specialties ORDER BY id ASC LIMIT 1");
                $defaultSpecialty = $db->single();
            }

            if (!$defaultSpecialty) {
                $error = "Vui lòng thêm chuyên khoa trước khi tạo lịch khám.";
            } else {
                $uniqueSuffix = time() . rand(1000, 9999);
                $email = 'doctor_' . $uniqueSuffix . '@medicalbooking.local';
                $phone = '09' . substr((string)$uniqueSuffix, -8);
                $db->query("INSERT INTO users (full_name, email, phone, password, role, hospital_id) VALUES (:name, :email, :phone, :password, 'doctor', :hospital_id)");
                $db->bind(':name', $doctorName);
                $db->bind(':email', $email);
                $db->bind(':phone', $phone);
                $db->bind(':password', password_hash('123456', PASSWORD_DEFAULT));
                $db->bind(':hospital_id', (int)$hospitalId);
                $db->execute();

                $db->query("SELECT id FROM users WHERE email = :email LIMIT 1");
                $db->bind(':email', $email);
                $newUser = $db->single();

                $db->query("INSERT INTO doctors (user_id, hospital_id, specialty_id, experience_years, consultation_fee, description, doctor_image_url, treatment_text, academic_title, gender, display_schedule_text, display_price_text, approval_status) VALUES (:user_id, :hospital_id, :specialty_id, 0, :consultation_fee, '', :doctor_image_url, :treatment_text, :academic_title, :gender, :display_schedule_text, :display_price_text, 'approved')");
                $db->bind(':user_id', (int)$newUser['id']);
                $db->bind(':hospital_id', (int)$hospitalId);
                $db->bind(':specialty_id', (int)$defaultSpecialty['specialty_id']);
                $db->bind(':consultation_fee', $consultationFee);
                $db->bind(':doctor_image_url', $doctorImageUrl);
$db->bind(':treatment_text', $treatmentText);
            $db->bind(':academic_title', $academicTitle);
            $db->bind(':gender', $gender);
            $db->bind(':display_schedule_text', $displayScheduleText);
                $db->bind(':display_price_text', $displayPriceText);
                $db->execute();

                $db->query("SELECT id FROM doctors WHERE user_id = :user_id LIMIT 1");
                $db->bind(':user_id', (int)$newUser['id']);
                $doctor = $db->single();
            }
            }
        }

        if (!$doctor) {
            $error = "Bác sĩ không thuộc bệnh viện được phân quyền.";
        } else {
            $doctorId = (int)$doctor['id'];
            $db->query("UPDATE doctors SET consultation_fee = CASE WHEN :consultation_fee > 0 THEN :consultation_fee ELSE consultation_fee END, doctor_image_url = :doctor_image_url, treatment_text = :treatment_text, academic_title = :academic_title, gender = :gender, display_schedule_text = :display_schedule_text, display_price_text = :display_price_text WHERE id = :did");
            $db->bind(':consultation_fee', $consultationFee);
            $db->bind(':doctor_image_url', $doctorImageUrl);
                $db->bind(':treatment_text', $treatmentText);
                $db->bind(':academic_title', $academicTitle);
                $db->bind(':gender', $gender);
                $db->bind(':display_schedule_text', $displayScheduleText);
            $db->bind(':display_price_text', $displayPriceText);
            $db->bind(':did', $doctorId);
            $db->execute();

            if ($selectedDoctorId > 0 && $selectedDoctorId === $doctorId) {
                $maxBookingDate = date('Y-m-d', strtotime('+' . $bookingAdvanceDays . ' days'));
                $db->query("DELETE FROM schedules WHERE doctor_id = :did AND status = 'available' AND work_date >= CURDATE() AND work_date <= :max_date");
                $db->bind(':did', $doctorId);
                $db->bind(':max_date', $maxBookingDate);
                $db->execute();
            }
            $insertedCount = 0;
            $skippedCount = 0;
            foreach ($selectedDates as $workDateValue) {
                foreach ($timeSlots as $slot) {
                    $db->query("SELECT id FROM schedules WHERE doctor_id = :did AND work_date = :wdate AND ((start_time <= :st AND end_time > :st) OR (start_time < :et AND end_time >= :et) OR (start_time >= :st AND end_time <= :et))");
                    $db->bind(':did', $doctorId);
                    $db->bind(':wdate', $workDateValue);
                    $db->bind(':st', $slot['start']);
                    $db->bind(':et', $slot['end']);
                    $db->execute();
                    if ($db->rowCount() > 0) {
                        $skippedCount++;
                        continue;
                    }
                    $db->query("INSERT INTO schedules (doctor_id, work_date, start_time, end_time, status) VALUES (:did, :wdate, :st, :et, 'available')");
                    $db->bind(':did', $doctorId);
                    $db->bind(':wdate', $workDateValue);
                    $db->bind(':st', $slot['start']);
                    $db->bind(':et', $slot['end']);
                    if ($db->execute()) {
                        $insertedCount++;
                    }
                }
            }
            if ($insertedCount > 0) {
                $success = "Thêm $insertedCount lịch khám thành công." . ($skippedCount > 0 ? " Bỏ qua $skippedCount khung giờ đã tồn tại." : "");
            } elseif ($skippedCount > 0) {
                $success = "Các khung giờ đã tồn tại, không cần tạo thêm.";
            } else {
                $error = "Không thể thêm lịch khám. Vui lòng chọn ngày và giờ khám hợp lệ.";
            }
            if (false) {
            $db->query("SELECT id FROM schedules WHERE doctor_id = :did AND work_date = :wdate AND ((start_time <= :st AND end_time > :st) OR (start_time < :et AND end_time >= :et) OR (start_time >= :st AND end_time <= :et))");
            $db->bind(':did', $doctorId);
            $db->bind(':wdate', $workDate);
            $db->bind(':st', $startTime);
            $db->bind(':et', $endTime);
            $db->execute();

            if ($db->rowCount() > 0) {
                $error = "Khung giờ này bị trùng với lịch đã có.";
            } else {
                $db->query("UPDATE doctors SET consultation_fee = CASE WHEN :consultation_fee > 0 THEN :consultation_fee ELSE consultation_fee END, doctor_image_url = :doctor_image_url, treatment_text = :treatment_text, academic_title = :academic_title, gender = :gender, display_schedule_text = :display_schedule_text, display_price_text = :display_price_text WHERE id = :did");
                $db->bind(':consultation_fee', $consultationFee);
                $db->bind(':doctor_image_url', $doctorImageUrl);
$db->bind(':treatment_text', $treatmentText);
            $db->bind(':academic_title', $academicTitle);
            $db->bind(':gender', $gender);
            $db->bind(':display_schedule_text', $displayScheduleText);
                $db->bind(':display_price_text', $displayPriceText);
                $db->bind(':did', $doctorId);
                $db->execute();

                $db->query("INSERT INTO schedules (doctor_id, work_date, start_time, end_time, status) VALUES (:did, :wdate, :st, :et, 'available')");
                $db->bind(':did', $doctorId);
                $db->bind(':wdate', $workDate);
                $db->bind(':st', $startTime);
                $db->bind(':et', $endTime);
                $success = $db->execute() ? "Thêm lịch khám thành công." : "Không thể thêm lịch khám.";
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Thêm Lịch khám</h2>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Quay lại</a>
</div>

<div class="row">
    <div class="col-md-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Bác sĩ <span class="text-danger">*</span></label>
                        <input type="text" name="doctor_name" id="doctorNameInput" class="form-control mb-2" list="doctorOptions" required placeholder="Nhập tên bác sĩ">
                        <datalist id="doctorOptions">
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo htmlspecialchars($doctor['full_name'] ?? ''); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <select name="doctor_id" id="doctorSelect" class="form-select d-none">
                            <option value="">-- Chọn bác sĩ --</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" <?php echo (int)$doctor['id'] === $selectedDoctorId ? 'selected' : ''; ?>
                                    data-name="<?php echo htmlspecialchars($doctor['full_name'] ?? ''); ?>"
                                    data-image="<?php echo htmlspecialchars($doctor['doctor_image_url'] ?? ''); ?>"
                                    data-treatment="<?php echo htmlspecialchars($doctor['treatment_text'] ?? ''); ?>"
                                    data-academic-title="<?php echo htmlspecialchars($doctor['academic_title'] ?? ''); ?>"
                                    data-gender="<?php echo htmlspecialchars($doctor['gender'] ?? ''); ?>">
                                    <?php echo htmlspecialchars(($doctor['full_name'] ?? 'Chưa gán tài khoản') . ' - ' . ($doctor['specialty_name'] ?? '') . ' - ' . ($doctor['hospital_name'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="border rounded-3 p-3 mb-3 bg-light">
                        <h6 class="fw-bold mb-3">Nội dung hiển thị trong ô chọn bác sĩ</h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Ảnh bác sĩ</label>
                            <input type="hidden" name="current_doctor_image_url" id="doctorImageUrl">
                            <div id="doctorImagePreviewWrap" class="mb-2 d-none">
                                <img id="doctorImagePreview" src="" class="img-thumbnail" style="width: 110px; height: 110px; object-fit: cover;" alt="Anh bac si">
                            </div>
                            <input type="file" name="doctor_image_file" id="doctorImageFile" class="form-control" accept="image/*">
                            <small class="text-muted">Chọn ảnh từ máy tính. Nếu không chọn ảnh mới, hệ thống sẽ giữ ảnh hiện tại của bác sĩ.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Chuyên khoa / Chuyên trị</label>
                            <input type="text" name="treatment_text" id="treatmentText" class="form-control" placeholder="VD: KHOA KHÁM BỆNH, Nhi khoa...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Học hàm / học vị</label>
                            <input type="text" name="academic_title" id="academicTitle" class="form-control" placeholder="VD: ThS.BS, BSCKII...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Giới tính</label>
                            <select name="gender" id="doctorGender" class="form-select">
                                <option value="">Chọn giới tính</option>
                                <option value="Nam">Nam</option>
                                <option value="Nữ">Nữ</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ngày khám <span class="text-danger">*</span></label>
                        <div id="workDateRows" class="d-flex flex-column gap-2 mb-2" style="max-width: 320px;">
                            <?php $dateRows = count($editingDates) ? array_values($editingDates) : ['']; ?>
                            <?php foreach ($dateRows as $dateValue): ?>
                                <div class="input-group work-date-row">
                                    <input type="date" name="work_dates[]" class="form-control" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+' . (int)$bookingAdvanceDays . ' days')); ?>" value="<?php echo htmlspecialchars($dateValue); ?>" required>
                                    <button type="button" class="btn btn-outline-danger remove-work-date">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="addWorkDate" class="btn btn-outline-primary btn-sm mb-2">Thêm ngày khám</button>
                        <div class="border rounded-3 p-2 bg-white">
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($weekdayOptions as $weekdayValue => $weekdayLabel): ?>
                                    <label class="form-check-label small me-2"><input type="checkbox" name="work_weekdays[]" value="<?php echo $weekdayValue; ?>" class="form-check-input" <?php echo in_array((int)$weekdayValue, array_map(function ($date) { return (int)date('w', strtotime($date)); }, array_values($editingDates)), true) ? 'checked' : ''; ?>> <?php echo $weekdayLabel; ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <small class="text-muted">Hệ thống sẽ tạo lịch cho các ngày đã chọn trong <?php echo (int)$bookingAdvanceDays; ?> ngày tới.</small>
                        <input type="date" name="work_date" class="form-control d-none legacy-single-date" min="<?php echo date('Y-m-d'); ?>" disabled>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Giờ khám <span class="text-danger">*</span></label>
                        <div class="border rounded-3 p-3 mb-3">
                            <label class="form-check-label fw-bold mb-2" style="color:#023f6d;"><input type="checkbox" name="enabled_periods[]" value="morning" class="form-check-input period-toggle" data-target="morningSlotRows" checked> Buổi sáng</label>
                            <div id="morningSlotRows" class="d-flex flex-column gap-2 time-slot-group" data-period="morning">
                                <?php $morningRows = array_values(array_filter($editingSlots, function ($slot) { return $slot['start'] < '12:00'; })); if (!count($morningRows)) { $morningRows = [['start' => '', 'end' => '']]; } ?>
                                <?php foreach ($morningRows as $slot): ?>
                                    <div class="row g-2 time-slot-row align-items-center">
                                        <input type="hidden" name="slot_period[]" value="morning">
                                        <div class="col-md-5"><select name="slot_start[]" class="form-select"><option value="">Chọn giờ bắt đầu</option><?php foreach ($morningTimeOptions as $timeValue => $timeLabel): ?><option value="<?php echo $timeValue; ?>" <?php echo $slot['start'] === $timeValue ? 'selected' : ''; ?>><?php echo $timeLabel; ?></option><?php endforeach; ?></select></div>
                                        <div class="col-md-5"><select name="slot_end[]" class="form-select"><option value="">Chọn giờ kết thúc</option><?php foreach ($morningTimeOptions as $timeValue => $timeLabel): ?><option value="<?php echo $timeValue; ?>" <?php echo $slot['end'] === $timeValue ? 'selected' : ''; ?>><?php echo $timeLabel; ?></option><?php endforeach; ?></select></div>
                                        <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-time-slot">×</button></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-time-slot" data-target="morningSlotRows" data-period="morning">Thêm giờ khám buổi sáng</button>
                        </div>
                        <div class="border rounded-3 p-3">
                            <?php $afternoonRows = array_values(array_filter($editingSlots, function ($slot) { return $slot['start'] >= '12:00'; })); ?>
                            <label class="form-check-label fw-bold mb-2" style="color:#023f6d;"><input type="checkbox" name="enabled_periods[]" value="afternoon" class="form-check-input period-toggle" data-target="afternoonSlotRows" <?php echo count($afternoonRows) ? 'checked' : ''; ?>> Buổi chiều</label>
                            <div id="afternoonSlotRows" class="d-flex flex-column gap-2 time-slot-group" data-period="afternoon">
                                <?php if (!count($afternoonRows)) { $afternoonRows = [['start' => '', 'end' => '']]; } ?>
                                <?php foreach ($afternoonRows as $slot): ?>
                                    <div class="row g-2 time-slot-row align-items-center">
                                        <input type="hidden" name="slot_period[]" value="afternoon">
                                        <div class="col-md-5"><select name="slot_start[]" class="form-select"><option value="">Chọn giờ bắt đầu</option><?php foreach ($afternoonTimeOptions as $timeValue => $timeLabel): ?><option value="<?php echo $timeValue; ?>" <?php echo $slot['start'] === $timeValue ? 'selected' : ''; ?>><?php echo $timeLabel; ?></option><?php endforeach; ?></select></div>
                                        <div class="col-md-5"><select name="slot_end[]" class="form-select"><option value="">Chọn giờ kết thúc</option><?php foreach ($afternoonTimeOptions as $timeValue => $timeLabel): ?><option value="<?php echo $timeValue; ?>" <?php echo $slot['end'] === $timeValue ? 'selected' : ''; ?>><?php echo $timeLabel; ?></option><?php endforeach; ?></select></div>
                                        <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-time-slot">×</button></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-time-slot" data-target="afternoonSlotRows" data-period="afternoon">Thêm giờ khám buổi chiều</button>
                        </div>
                        <div class="small text-muted mt-1">Buổi sáng chọn trong khoảng 07:00 - 12:00, buổi chiều chọn trong khoảng 13:00 - 18:00.</div>
                    </div>
                    <div class="row mb-3 d-none">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Giờ bắt đầu <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Giờ kết thúc <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>
                    <div id="duplicateTimeWarning" class="alert alert-danger d-none">Không được chọn 2 khung giờ khám giống nhau.</div>
                    <button type="submit" id="saveScheduleButton" class="btn btn-primary"><i class="bi bi-save me-1"></i> Lưu lịch khám</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const doctorSelect = document.getElementById('doctorSelect');
const doctorNameInput = document.getElementById('doctorNameInput');
const doctorImageUrl = document.getElementById('doctorImageUrl');
const doctorImageFile = document.getElementById('doctorImageFile');
const doctorImagePreviewWrap = document.getElementById('doctorImagePreviewWrap');
const doctorImagePreview = document.getElementById('doctorImagePreview');
const treatmentText = document.getElementById('treatmentText');
const academicTitle = document.getElementById('academicTitle');
const doctorGender = document.getElementById('doctorGender');
const baseUrl = <?php echo json_encode($base_url); ?>;
const morningTimeOptions = <?php echo json_encode(array_values($morningTimeOptions)); ?>;
const afternoonTimeOptions = <?php echo json_encode(array_values($afternoonTimeOptions)); ?>;

if (doctorSelect && doctorSelect.value) {
    const selectedOption = doctorSelect.options[doctorSelect.selectedIndex];
    doctorNameInput.value = selectedOption.dataset.name || '';
    setTimeout(syncDoctorDisplayFields, 0);
}

document.querySelectorAll('input[name="start_time"], input[name="end_time"]').forEach(function (input) {
    input.required = false;
    input.disabled = true;
});

function buildTimeSelect(name, placeholder, period) {
    const times = period === 'morning' ? morningTimeOptions : afternoonTimeOptions;
    let html = '<select name="' + name + '" class="form-select"><option value="">' + placeholder + '</option>';
    times.forEach(function (time) {
        html += '<option value="' + time + '">' + time + '</option>';
    });
    return html + '</select>';
}

function applyPeriodToggle(toggle) {
    const target = document.getElementById(toggle.dataset.target);
    const disabled = !toggle.checked;
    target.querySelectorAll('select, button').forEach(function (input) {
        input.disabled = disabled;
    });
    const addButton = document.querySelector('.add-time-slot[data-target="' + toggle.dataset.target + '"]');
    if (addButton) {
        addButton.disabled = disabled;
    }
}

document.querySelectorAll('.period-toggle').forEach(function (toggle) {
    applyPeriodToggle(toggle);
    toggle.addEventListener('change', function () {
        applyPeriodToggle(this);
    });
});

document.getElementById('addWorkDate')?.addEventListener('click', function () {
    const wrapper = document.getElementById('workDateRows');
    const row = document.createElement('div');
    row.className = 'input-group work-date-row';
    row.innerHTML = '<input type="date" name="work_dates[]" class="form-control" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+' . (int)$bookingAdvanceDays . ' days')); ?>" required><button type="button" class="btn btn-outline-danger remove-work-date">×</button>';
    wrapper.appendChild(row);
});

document.addEventListener('click', function (event) {
    if (!event.target.classList.contains('remove-work-date')) {
        return;
    }
    const rows = document.querySelectorAll('.work-date-row');
    if (rows.length > 1) {
        event.target.closest('.work-date-row').remove();
    }
});

document.querySelectorAll('.add-time-slot').forEach(function (button) {
    button.addEventListener('click', function () {
        const wrapper = document.getElementById(this.dataset.target);
        const period = this.dataset.period;
        const row = document.createElement('div');
        row.className = 'row g-2 time-slot-row align-items-center';
        row.innerHTML = '<input type="hidden" name="slot_period[]" value="' + period + '"><div class="col-md-5">' + buildTimeSelect('slot_start[]', 'Chọn giờ bắt đầu', period) + '</div><div class="col-md-5">' + buildTimeSelect('slot_end[]', 'Chọn giờ kết thúc', period) + '</div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-time-slot">×</button></div>';
        wrapper.appendChild(row);
    });
});

document.addEventListener('click', function (event) {
    if (!event.target.classList.contains('remove-time-slot')) {
        return;
    }
    const row = event.target.closest('.time-slot-row');
    const rows = row.closest('.time-slot-group').querySelectorAll('.time-slot-row');
    if (rows.length > 1) {
        row.remove();
    } else {
        row.querySelectorAll('select').forEach(function (select) {
            select.value = '';
        });
    }
});

function validateDuplicateTimeSlots() {
    const seen = new Set();
    let duplicated = false;
    document.querySelectorAll('.time-slot-row').forEach(function (row) {
        const group = row.closest('.time-slot-group');
        const periodToggle = group ? document.querySelector('.period-toggle[data-target="' + group.id + '"]') : null;
        if (periodToggle && !periodToggle.checked) {
            row.classList.remove('border', 'border-danger', 'rounded-3');
            return;
        }
        const start = row.querySelector('select[name="slot_start[]"]')?.value || '';
        const end = row.querySelector('select[name="slot_end[]"]')?.value || '';
        const key = start && end ? start + '|' + end : '';
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

document.addEventListener('change', function (event) {
    if (event.target.matches('select[name="slot_start[]"], select[name="slot_end[]"], .period-toggle')) {
        validateDuplicateTimeSlots();
    }
});

document.querySelector('form')?.addEventListener('submit', function (event) {
    if (!validateDuplicateTimeSlots()) {
        event.preventDefault();
    }
});

function imageSource(path) {
    if (!path) {
        return '';
    }
    return /^https?:\/\//i.test(path) ? path : baseUrl + '/' + path;
}

function setDoctorImage(path) {
    doctorImageUrl.value = path || '';
    if (path) {
        doctorImagePreview.src = imageSource(path);
        doctorImagePreviewWrap.classList.remove('d-none');
    } else {
        doctorImagePreview.src = '';
        doctorImagePreviewWrap.classList.add('d-none');
    }
}

if (doctorSelect) {
    doctorSelect.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        setDoctorImage(option.dataset.image || '');
        treatmentText.value = option.dataset.treatment || '';
        academicTitle.value = option.dataset.academicTitle || '';
        doctorGender.value = option.dataset.gender || '';
        if (doctorImageFile) {
            doctorImageFile.value = '';
        }
    });
}

function syncDoctorDisplayFields() {
    if (!doctorSelect || !doctorNameInput) {
        return;
    }
    const matched = Array.from(doctorSelect.options).find(function (option) {
        return (option.dataset.name || '').trim().toLowerCase() === doctorNameInput.value.trim().toLowerCase();
    });
    if (!matched) {
        doctorSelect.value = '';
        setDoctorImage('');
        treatmentText.value = '';
        academicTitle.value = '';
        doctorGender.value = '';
        return;
    }
    doctorSelect.value = matched.value;
    setDoctorImage(matched.dataset.image || '');
    treatmentText.value = matched.dataset.treatment || '';
    academicTitle.value = matched.dataset.academicTitle || '';
    doctorGender.value = matched.dataset.gender || '';
    if (doctorImageFile) {
        doctorImageFile.value = '';
    }
}

doctorNameInput?.addEventListener('change', syncDoctorDisplayFields);
doctorNameInput?.addEventListener('blur', syncDoctorDisplayFields);

doctorImageFile?.addEventListener('change', function () {
    const file = this.files && this.files[0];
    if (!file) {
        setDoctorImage(doctorImageUrl.value);
        return;
    }
    doctorImagePreview.src = URL.createObjectURL(file);
    doctorImagePreviewWrap.classList.remove('d-none');
});
</script>

<?php include '../includes/footer.php'; ?>
