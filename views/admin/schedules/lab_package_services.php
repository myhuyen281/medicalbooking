<?php
require_once '../../../config/database.php';
include '../includes/header.php';

$db = new Database();
$error = '';
$success = '';
$packageId = (int)($_GET['package_id'] ?? $_POST['package_id'] ?? 0);
$category = $_GET['category'] ?? ($_POST['category'] ?? 'lab');
foreach ([
    "ALTER TABLE lab_packages ADD COLUMN booking_flow VARCHAR(30) NOT NULL DEFAULT 'service_first' AFTER icon_path",
    "ALTER TABLE lab_packages ADD COLUMN booking_specialties TEXT NULL AFTER booking_flow",
    "ALTER TABLE lab_package_services ADD COLUMN specialty_name TEXT NULL AFTER service_icon",
    "ALTER TABLE lab_package_services ADD COLUMN service_icon VARCHAR(80) NOT NULL DEFAULT 'bi-clipboard2-pulse' AFTER name",
    "ALTER TABLE lab_package_services ADD COLUMN specialty_name TEXT NULL AFTER service_icon",
    "ALTER TABLE lab_package_services ADD COLUMN schedule_text VARCHAR(255) NULL AFTER service_icon",
    "ALTER TABLE lab_package_services ADD COLUMN time_slots TEXT NULL AFTER schedule_text",
    "ALTER TABLE lab_package_services ADD COLUMN description TEXT NULL AFTER price"
] as $sql) {
    try { $db->query($sql); $db->execute(); } catch (Exception $e) {}
}
$weekdayOptions = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 0 => 'CN'];
$morningTimeOptions = [];
for ($hour = 7; $hour <= 11; $hour++) { foreach ([0, 30] as $minute) { $time = sprintf('%02d:%02d', $hour, $minute); $morningTimeOptions[$time] = $time; } }
$morningTimeOptions['12:00'] = '12:00';
$afternoonTimeOptions = [];
for ($hour = 13; $hour <= 17; $hour++) { foreach ([0, 30] as $minute) { $time = sprintf('%02d:%02d', $hour, $minute); $afternoonTimeOptions[$time] = $time; } }
$afternoonTimeOptions['18:00'] = '18:00';

$db->query("SELECT lp.*, h.name AS hospital_name FROM lab_packages lp INNER JOIN hospitals h ON h.id = lp.hospital_id WHERE lp.id = :id LIMIT 1");
$db->bind(':id', $packageId);
$package = $db->single();
if (!$package || ($isHospitalAdmin && (int)$package['hospital_id'] !== (int)$currentHospitalId)) {
    echo "<div class='alert alert-danger'>Không tìm thấy gói.</div>";
    include '../includes/footer.php';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingFlow = in_array($_POST['booking_flow'] ?? 'service_first', ['specialty_first', 'service_first', 'service_only'], true) ? $_POST['booking_flow'] : 'service_first';
    $specialtyNames = array_values(array_filter(array_map('trim', $_POST['specialty_name'] ?? []), 'strlen'));
    $db->query("UPDATE lab_packages SET booking_flow = :booking_flow, booking_specialties = :booking_specialties WHERE id = :id");
    $db->bind(':booking_flow', $bookingFlow);
    $db->bind(':booking_specialties', json_encode($specialtyNames, JSON_UNESCAPED_UNICODE));
    $db->bind(':id', $packageId);
    $db->execute();

    $serviceIds = $_POST['service_id'] ?? [];
    $serviceNames = $_POST['service_name'] ?? [];
    $servicePrices = $_POST['service_price'] ?? [];
    $serviceIcons = $_POST['service_icon'] ?? [];
    $serviceSpecialties = $_POST['service_specialty'] ?? [];
    $serviceDescriptions = $_POST['service_description'] ?? [];
    foreach ($serviceNames as $index => $serviceName) {
        $serviceName = trim($serviceName);
        $serviceId = (int)($serviceIds[$index] ?? 0);
        if ($serviceName === '') {
            continue;
        }
        $price = (float)str_replace(',', '', $servicePrices[$index] ?? 0);
        $icon = trim($serviceIcons[$index] ?? 'bi-clipboard2-pulse');
        $specialty = trim($serviceSpecialties[$index] ?? '');
        $description = trim($serviceDescriptions[$index] ?? '');
        $weekdays = $_POST['service_weekdays'][$index] ?? [];
        $scheduleText = implode(',', array_map('intval', $weekdays));
        $timeSlots = [];
        foreach (['morning' => 'service_morning', 'afternoon' => 'service_afternoon'] as $period => $prefix) {
            $starts = $_POST[$prefix . '_start'][$index] ?? [];
            $ends = $_POST[$prefix . '_end'][$index] ?? [];
            if (!is_array($starts)) $starts = [$starts];
            if (!is_array($ends)) $ends = [$ends];
            foreach ($starts as $slotIndex => $start) {
                $end = $ends[$slotIndex] ?? '';
                if ($start !== '' && $end !== '') {
                    $timeSlots[] = ['period' => $period, 'start' => $start, 'end' => $end];
                }
            }
        }
        if ($serviceId > 0) {
            $db->query("UPDATE lab_package_services SET name = :name, service_icon = :service_icon, specialty_name = :specialty_name, schedule_text = :schedule_text, time_slots = :time_slots, price = :price, description = :description WHERE id = :id AND package_id = :package_id");
            $db->bind(':id', $serviceId);
        } else {
            $db->query("INSERT INTO lab_package_services (package_id, name, service_icon, specialty_name, schedule_text, time_slots, price, description, sort_order) VALUES (:package_id, :name, :service_icon, :specialty_name, :schedule_text, :time_slots, :price, :description, :sort_order)");
            $db->bind(':sort_order', $index + 1);
        }
        $db->bind(':package_id', $packageId);
        $db->bind(':name', $serviceName);
        $db->bind(':service_icon', $icon);
        $db->bind(':specialty_name', $specialty);
        $db->bind(':schedule_text', $scheduleText);
        $db->bind(':time_slots', json_encode($timeSlots, JSON_UNESCAPED_UNICODE));
        $db->bind(':price', $price);
        $db->bind(':description', $description);
        $db->execute();
    }
    $db->query("SELECT lp.*, h.name AS hospital_name FROM lab_packages lp INNER JOIN hospitals h ON h.id = lp.hospital_id WHERE lp.id = :id LIMIT 1");
    $db->bind(':id', $packageId);
    $package = $db->single();
    $success = 'Đã lưu dịch vụ gói.';
}

if (isset($_GET['delete'])) {
    $db->query("DELETE FROM lab_package_services WHERE id = :id AND package_id = :package_id");
    $db->bind(':id', (int)$_GET['delete']);
    $db->bind(':package_id', $packageId);
    $db->execute();
    $success = 'Đã xóa dịch vụ gói.';
}

$db->query("SELECT * FROM lab_package_services WHERE package_id = :package_id ORDER BY sort_order ASC, id ASC");
$db->bind(':package_id', $packageId);
$services = $db->resultSet();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Dịch vụ gói - <?php echo htmlspecialchars($package['name']); ?></h2>
        <p class="text-muted mb-0"><?php echo htmlspecialchars($package['hospital_name']); ?></p>
    </div>
    <a href="lab_packages.php?category=<?php echo urlencode($category); ?>" class="btn btn-secondary">Quay lại</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <div class="card-body p-4">
        <div class="alert alert-info border-0 mb-4">Nhập dịch vụ trong gói tương tự phần Dịch vụ khám: tên dịch vụ, icon, giá, ngày thực hiện, giờ buổi sáng/chiều và mô tả.</div>
        <form method="post">
            <input type="hidden" name="package_id" value="<?php echo (int)$packageId; ?>">
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
            <?php $currentFlow = in_array($package['booking_flow'] ?? '', ['specialty_first', 'service_first', 'service_only'], true) ? $package['booking_flow'] : 'service_first'; $currentSpecialties = json_decode($package['booking_specialties'] ?? '[]', true); if (!is_array($currentSpecialties)) $currentSpecialties = []; ?>
            <div class="border rounded-3 p-3 mb-3" style="background:#eefcff; border-color:#00a8f0 !important;">
                <label class="form-label fw-bold">Kiểu đặt lịch</label>
                <select name="booking_flow" id="bookingFlow" class="form-select mb-3" style="max-width: 440px;">
                    <option value="specialty_first" <?php echo $currentFlow === 'specialty_first' ? 'selected' : ''; ?>>Chọn chuyên khoa trước, rồi chọn dịch vụ</option>
                    <option value="service_first" <?php echo $currentFlow === 'service_first' ? 'selected' : ''; ?>>Chọn dịch vụ trước, rồi chọn chuyên khoa</option>
                    <option value="service_only" <?php echo $currentFlow === 'service_only' ? 'selected' : ''; ?>>Chỉ chọn dịch vụ</option>
                </select>
                <label class="form-label fw-bold">Danh sách chuyên khoa</label>
                <div id="specialtyRows" class="d-flex flex-column gap-2 mb-2">
                    <?php $renderSpecialties = count($currentSpecialties) ? $currentSpecialties : ['']; ?>
                    <?php foreach ($renderSpecialties as $specialty): ?>
                        <div class="d-flex gap-2 specialty-row"><input type="text" name="specialty_name[]" class="form-control" value="<?php echo htmlspecialchars($specialty); ?>" placeholder="Tên chuyên khoa"><button type="button" class="btn btn-outline-danger remove-specialty-row">×</button></div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="addSpecialtyRow" class="btn btn-outline-primary btn-sm">Thêm chuyên khoa</button>
            </div>
            <div id="serviceRows" class="d-flex flex-column gap-3">
                <?php $renderServices = count($services) ? $services : [['id' => 0, 'name' => '', 'service_icon' => 'bi-clipboard2-pulse', 'price' => 0, 'schedule_text' => '', 'time_slots' => '[]', 'description' => '']]; ?>
                <?php foreach ($renderServices as $index => $service): ?>
                    <?php $selectedDays = array_filter(explode(',', (string)($service['schedule_text'] ?? '')), 'strlen'); $slots = json_decode($service['time_slots'] ?? '[]', true) ?: []; ?>
                    <div class="service-row border rounded-3 p-3 bg-light overflow-hidden">
                        <input type="hidden" name="service_id[]" value="<?php echo (int)$service['id']; ?>">
                        <div class="row g-2 mb-2">
                            <div class="col-md-3"><input type="text" name="service_name[]" class="form-control" value="<?php echo htmlspecialchars($service['name']); ?>" placeholder="Tên dịch vụ trong gói"></div>
                            <div class="col-md-2"><input type="text" name="service_icon[]" class="form-control" value="<?php echo htmlspecialchars($service['service_icon'] ?: 'bi-clipboard2-pulse'); ?>" placeholder="Icon"></div>
                            <div class="col-md-3">
                                <select name="service_specialty[]" class="form-select service-specialty-select">
                                    <option value="">Chọn chuyên khoa</option>
                                    <?php foreach ($currentSpecialties as $specialtyOption): ?>
                                        <option value="<?php echo htmlspecialchars($specialtyOption); ?>" <?php echo (($service['specialty_name'] ?? '') === $specialtyOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars($specialtyOption); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3"><input type="number" name="service_price[]" class="form-control" min="0" step="1000" value="<?php echo (float)$service['price']; ?>" placeholder="Giá"></div>
                            <div class="col-md-3"><?php if (!empty($service['id'])): ?><a class="btn btn-outline-danger w-100" href="lab_package_services.php?package_id=<?php echo (int)$packageId; ?>&category=<?php echo urlencode($category); ?>&delete=<?php echo (int)$service['id']; ?>" onclick="return confirm('Xóa dịch vụ này?')">Xóa dịch vụ</a><?php else: ?><button type="button" class="btn btn-outline-danger w-100 remove-service-row">Xóa dịch vụ</button><?php endif; ?></div>
                        </div>
                        <div class="border rounded-3 p-2 mb-2 bg-white">
                            <div class="fw-bold small mb-1">Ngày thực hiện</div>
                            <?php foreach ($weekdayOptions as $dayValue => $dayLabel): ?>
                                <label class="form-check form-check-inline mb-1"><input class="form-check-input" type="checkbox" name="service_weekdays[<?php echo $index; ?>][]" value="<?php echo (int)$dayValue; ?>" <?php echo in_array((string)$dayValue, $selectedDays, true) ? 'checked' : ''; ?>> <span class="form-check-label"><?php echo htmlspecialchars($dayLabel); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <?php foreach (['morning' => ['Buổi sáng', $morningTimeOptions], 'afternoon' => ['Buổi chiều', $afternoonTimeOptions]] as $period => $periodData): ?>
                            <?php $periodSlots = array_values(array_filter($slots, function ($slot) use ($period) { return ($slot['period'] ?? '') === $period; })); if (count($periodSlots) === 0) $periodSlots = [['start' => '', 'end' => '']]; ?>
                            <div class="border rounded-3 p-3 mb-2 bg-white">
                                <label class="form-check-label fw-bold mb-2" style="color:#023f6d;"><input type="checkbox" class="form-check-input" <?php echo count(array_filter($periodSlots, fn($slot) => !empty($slot['start']) && !empty($slot['end']))) ? 'checked' : ''; ?>> <?php echo $periodData[0]; ?></label>
                                <div class="package-time-slots d-flex flex-column gap-2" data-service-index="<?php echo $index; ?>" data-period="<?php echo $period; ?>">
                                    <?php foreach ($periodSlots as $slot): ?>
                                        <div class="row g-2 package-time-row">
                                            <div class="col-md-5"><select name="service_<?php echo $period; ?>_start[<?php echo $index; ?>][]" class="form-select"><option value="">Chọn giờ bắt đầu</option><?php foreach ($periodData[1] as $time): ?><option value="<?php echo $time; ?>" <?php echo (($slot['start'] ?? '') === $time) ? 'selected' : ''; ?>><?php echo $time; ?></option><?php endforeach; ?></select></div>
                                            <div class="col-md-5"><select name="service_<?php echo $period; ?>_end[<?php echo $index; ?>][]" class="form-select"><option value="">Chọn giờ kết thúc</option><?php foreach ($periodData[1] as $time): ?><option value="<?php echo $time; ?>" <?php echo (($slot['end'] ?? '') === $time) ? 'selected' : ''; ?>><?php echo $time; ?></option><?php endforeach; ?></select></div>
                                            <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-package-time">×</button></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-package-time" data-period="<?php echo $period; ?>">Thêm giờ <?php echo mb_strtolower($periodData[0]); ?></button>
                            </div>
                        <?php endforeach; ?>
                        <textarea name="service_description[]" class="form-control" rows="3" placeholder="Mô tả dịch vụ"><?php echo htmlspecialchars($service['description'] ?? ''); ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="button" id="addServiceRow" class="btn btn-outline-primary">Thêm dịch vụ</button>
                <button type="submit" class="btn btn-primary px-4">Lưu dịch vụ gói</button>
            </div>
        </form>
    </div>
</div>

<script>
const morningOptions = <?php echo json_encode('<option value="">Chọn giờ bắt đầu</option>' . implode('', array_map(fn($time) => '<option value="' . $time . '">' . $time . '</option>', array_values($morningTimeOptions)))); ?>;
const afternoonOptions = <?php echo json_encode('<option value="">Chọn giờ bắt đầu</option>' . implode('', array_map(fn($time) => '<option value="' . $time . '">' . $time . '</option>', array_values($afternoonTimeOptions)))); ?>;
const dayCheckboxes = `<?php foreach ($weekdayOptions as $dayValue => $dayLabel): ?><label class="form-check form-check-inline mb-1"><input class="form-check-input" type="checkbox" data-day-value="<?php echo (int)$dayValue; ?>"> <span class="form-check-label"><?php echo htmlspecialchars($dayLabel); ?></span></label><?php endforeach; ?>`;
function specialtySelectHtml() {
    const values = Array.from(document.querySelectorAll('input[name="specialty_name[]"]')).map(function (input) { return input.value.trim(); }).filter(Boolean);
    return '<select name="service_specialty[]" class="form-select service-specialty-select"><option value="">Chọn chuyên khoa</option>' + values.map(function (value) { return '<option value="' + value.replace(/"/g, '&quot;') + '">' + value + '</option>'; }).join('') + '</select>';
}
function refreshServiceSpecialtySelects() {
    const values = Array.from(document.querySelectorAll('input[name="specialty_name[]"]')).map(function (input) { return input.value.trim(); }).filter(Boolean);
    document.querySelectorAll('.service-specialty-select').forEach(function (select) {
        const selected = select.value;
        select.innerHTML = '<option value="">Chọn chuyên khoa</option>' + values.map(function (value) { return '<option value="' + value.replace(/"/g, '&quot;') + '">' + value + '</option>'; }).join('');
        select.value = selected;
    });
}
function timeGroup(index, period) {
    const label = period === 'morning' ? 'Buổi sáng' : 'Buổi chiều';
    const options = period === 'morning' ? morningOptions : afternoonOptions;
    return '<div class="border rounded-3 p-3 mb-2 bg-white"><label class="form-check-label fw-bold mb-2" style="color:#023f6d;"><input type="checkbox" class="form-check-input"> ' + label + '</label><div class="package-time-slots d-flex flex-column gap-2" data-service-index="' + index + '" data-period="' + period + '"><div class="row g-2 package-time-row"><div class="col-md-5"><select name="service_' + period + '_start[' + index + '][]" class="form-select">' + options + '</select></div><div class="col-md-5"><select name="service_' + period + '_end[' + index + '][]" class="form-select">' + options.replace('Chọn giờ bắt đầu', 'Chọn giờ kết thúc') + '</select></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-package-time">×</button></div></div></div><button type="button" class="btn btn-outline-primary btn-sm mt-2 add-package-time" data-period="' + period + '">Thêm giờ ' + label.toLowerCase() + '</button></div>';
}
document.getElementById('addSpecialtyRow')?.addEventListener('click', function () {
    const wrapper = document.getElementById('specialtyRows');
    const row = document.createElement('div');
    row.className = 'd-flex gap-2 specialty-row';
    row.innerHTML = '<input type="text" name="specialty_name[]" class="form-control" placeholder="Tên chuyên khoa"><button type="button" class="btn btn-outline-danger remove-specialty-row">×</button>';
    wrapper.appendChild(row);
    refreshServiceSpecialtySelects();
});
document.getElementById('specialtyRows')?.addEventListener('input', refreshServiceSpecialtySelects);

document.getElementById('addServiceRow')?.addEventListener('click', function () {
    const wrapper = document.getElementById('serviceRows');
    const index = wrapper.querySelectorAll('.service-row').length;
    const row = document.createElement('div');
    row.className = 'service-row border rounded-3 p-3 bg-light overflow-hidden';
    const days = dayCheckboxes.replaceAll('data-day-value=', 'name="service_weekdays[' + index + '][]" value=');
    row.innerHTML = '<input type="hidden" name="service_id[]" value="0"><div class="row g-2 mb-2"><div class="col-md-3"><input type="text" name="service_name[]" class="form-control" placeholder="Tên dịch vụ trong gói"></div><div class="col-md-2"><input type="text" name="service_icon[]" class="form-control" value="bi-clipboard2-pulse" placeholder="Icon"></div><div class="col-md-3">' + specialtySelectHtml() + '</div><div class="col-md-3"><input type="number" name="service_price[]" class="form-control" min="0" step="1000" value="0" placeholder="Giá"></div><div class="col-md-3"><button type="button" class="btn btn-outline-danger w-100 remove-service-row">Xóa dịch vụ</button></div></div><div class="border rounded-3 p-2 mb-2 bg-white"><div class="fw-bold small mb-1">Ngày thực hiện</div>' + days + '</div>' + timeGroup(index, 'morning') + timeGroup(index, 'afternoon') + '<textarea name="service_description[]" class="form-control" rows="3" placeholder="Mô tả dịch vụ"></textarea>';
    wrapper.appendChild(row);
});
document.addEventListener('click', function (event) {
    if (event.target.classList.contains('remove-specialty-row') && document.querySelectorAll('.specialty-row').length > 1) {
        event.target.closest('.specialty-row')?.remove();
        refreshServiceSpecialtySelects();
    }
    if (event.target.classList.contains('remove-service-row') && document.querySelectorAll('.service-row').length > 1) {
        event.target.closest('.service-row')?.remove();
    }
    if (event.target.classList.contains('remove-package-time')) {
        event.target.closest('.package-time-row')?.remove();
    }
    if (event.target.classList.contains('add-package-time')) {
        const group = event.target.closest('.border').querySelector('.package-time-slots');
        const index = group.dataset.serviceIndex;
        const period = group.dataset.period;
        const options = period === 'morning' ? morningOptions : afternoonOptions;
        const row = document.createElement('div');
        row.className = 'row g-2 package-time-row';
        row.innerHTML = '<div class="col-md-5"><select name="service_' + period + '_start[' + index + '][]" class="form-select">' + options + '</select></div><div class="col-md-5"><select name="service_' + period + '_end[' + index + '][]" class="form-select">' + options.replace('Chọn giờ bắt đầu', 'Chọn giờ kết thúc') + '</select></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-package-time">×</button></div>';
        group.appendChild(row);
    }
});
</script>

<?php include '../includes/footer.php'; ?>
