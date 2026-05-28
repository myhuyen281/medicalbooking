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
$success = '';
try {
    $db->query("CREATE TABLE IF NOT EXISTS lab_packages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hospital_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(12,2) NOT NULL DEFAULT 0,
        sample_type VARCHAR(255) NULL,
        turnaround_time VARCHAR(255) NULL,
        description TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (hospital_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->execute();
} catch (Exception $e) {
}
try {
    $db->query("CREATE TABLE IF NOT EXISTS lab_package_services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        package_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        service_icon VARCHAR(80) NOT NULL DEFAULT 'bi-clipboard2-pulse',
        schedule_text VARCHAR(255) NULL,
        time_slots TEXT NULL,
        price DECIMAL(12,2) NOT NULL DEFAULT 0,
        description TEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (package_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->execute();
} catch (Exception $e) {
}
foreach ([
    "ALTER TABLE lab_package_services ADD COLUMN service_icon VARCHAR(80) NOT NULL DEFAULT 'bi-clipboard2-pulse' AFTER name",
    "ALTER TABLE lab_package_services ADD COLUMN schedule_text VARCHAR(255) NULL AFTER service_icon",
    "ALTER TABLE lab_package_services ADD COLUMN time_slots TEXT NULL AFTER schedule_text"
] as $sql) {
    try { $db->query($sql); $db->execute(); } catch (Exception $e) {}
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

$hospitalId = $isHospitalAdmin ? $currentHospitalId : ($_GET['hospital_id'] ?? null);
if (!$hospitalId && $isSystemAdmin) {
    $db->query("SELECT id FROM hospitals ORDER BY name ASC LIMIT 1");
    $firstHospital = $db->single();
    $hospitalId = $firstHospital['id'] ?? null;
}

if (isset($_GET['delete'], $_GET['id']) && $hospitalId) {
    $db->query("DELETE lps FROM lab_package_services lps INNER JOIN lab_packages lp ON lp.id = lps.package_id WHERE lp.id = :id AND lp.hospital_id = :hospital_id");
    $db->bind(':id', (int)$_GET['id']);
    $db->bind(':hospital_id', $hospitalId);
    $db->execute();
    $db->query("DELETE FROM lab_packages WHERE id = :id AND hospital_id = :hospital_id");
    $db->bind(':id', (int)$_GET['id']);
    $db->bind(':hospital_id', $hospitalId);
    $db->execute();
    $success = 'Đã xóa gói xét nghiệm.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hospitalId = $isHospitalAdmin ? $currentHospitalId : ($_POST['hospital_id'] ?? $hospitalId);
    $name = trim($_POST['name'] ?? '');
    $price = 0;
    $sampleType = '';
    $turnaroundTime = '';
    $description = '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (empty($hospitalId)) {
        $error = 'Vui lòng chọn bệnh viện.';
    } elseif ($name === '') {
        $error = 'Vui lòng nhập tên gói xét nghiệm.';
    } else {
        $db->query("INSERT INTO lab_packages (hospital_id, name, price, sample_type, turnaround_time, description, is_active)
                    VALUES (:hospital_id, :name, :price, :sample_type, :turnaround_time, :description, :is_active)");
        $db->bind(':hospital_id', $hospitalId);
        $db->bind(':name', $name);
        $db->bind(':price', $price);
        $db->bind(':sample_type', $sampleType);
        $db->bind(':turnaround_time', $turnaroundTime);
        $db->bind(':description', $description);
        $db->bind(':is_active', $isActive);
        $db->execute();
        $packageId = $db->lastInsertId();
        foreach ($_POST['service_name'] ?? [] as $index => $serviceName) {
            $serviceName = trim($serviceName);
            if ($serviceName === '') {
                continue;
            }
            $weekdays = $_POST['service_weekdays'][$index] ?? [];
            $scheduleText = implode(',', array_map('intval', $weekdays));
            $timeSlots = [];
            $morningStarts = $_POST['service_morning_start'][$index] ?? [];
            $morningEnds = $_POST['service_morning_end'][$index] ?? [];
            $afternoonStarts = $_POST['service_afternoon_start'][$index] ?? [];
            $afternoonEnds = $_POST['service_afternoon_end'][$index] ?? [];
            if (!is_array($morningStarts)) $morningStarts = [$morningStarts];
            if (!is_array($morningEnds)) $morningEnds = [$morningEnds];
            if (!is_array($afternoonStarts)) $afternoonStarts = [$afternoonStarts];
            if (!is_array($afternoonEnds)) $afternoonEnds = [$afternoonEnds];
            foreach ($morningStarts as $slotIndex => $morningStart) {
                $morningEnd = $morningEnds[$slotIndex] ?? '';
                if ($morningStart !== '' && $morningEnd !== '') {
                    $timeSlots[] = ['period' => 'morning', 'start' => $morningStart, 'end' => $morningEnd];
                }
            }
            foreach ($afternoonStarts as $slotIndex => $afternoonStart) {
                $afternoonEnd = $afternoonEnds[$slotIndex] ?? '';
                if ($afternoonStart !== '' && $afternoonEnd !== '') {
                    $timeSlots[] = ['period' => 'afternoon', 'start' => $afternoonStart, 'end' => $afternoonEnd];
                }
            }
            $db->query("INSERT INTO lab_package_services (package_id, name, service_icon, schedule_text, time_slots, price, description, sort_order)
                        VALUES (:package_id, :name, :service_icon, :schedule_text, :time_slots, :price, :description, :sort_order)");
            $db->bind(':package_id', $packageId);
            $db->bind(':name', $serviceName);
            $db->bind(':service_icon', trim($_POST['service_icon'][$index] ?? 'bi-clipboard2-pulse'));
            $db->bind(':schedule_text', $scheduleText);
            $db->bind(':time_slots', json_encode($timeSlots, JSON_UNESCAPED_UNICODE));
            $db->bind(':price', (float)($_POST['service_price'][$index] ?? 0));
            $db->bind(':description', trim($_POST['service_description'][$index] ?? ''));
            $db->bind(':sort_order', $index + 1);
            $db->execute();
        }
        $success = 'Đã tạo gói xét nghiệm.';
    }
}

$hospitals = [];
if ($isSystemAdmin) {
    $db->query("SELECT id, name FROM hospitals ORDER BY name ASC");
    $hospitals = $db->resultSet();
}

$packages = [];
if ($hospitalId) {
    $db->query("SELECT * FROM lab_packages WHERE hospital_id = :hospital_id ORDER BY id DESC");
    $db->bind(':hospital_id', $hospitalId);
    $packages = $db->resultSet();
    foreach ($packages as $index => $package) {
        $db->query("SELECT * FROM lab_package_services WHERE package_id = :package_id ORDER BY sort_order ASC, id ASC");
        $db->bind(':package_id', (int)$package['id']);
        $packages[$index]['services'] = $db->resultSet();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-extrabold mb-1">Gói xét nghiệm</h2>
        <p class="text-muted mb-0">Tạo và quản lý các gói xét nghiệm của bệnh viện.</p>
    </div>
    <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#labPackageModal">
        + Thêm gói
    </button>
</div>

<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-4">Danh sách gói xét nghiệm</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Tên gói</th>
                        <th>Giá</th>
                        <th>Loại mẫu</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $package): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($package['name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($package['turnaround_time'] ?: 'Chưa cập nhật thời gian'); ?></small>
                                <?php if (!empty($package['services'])): ?>
                                    <div class="small mt-2 text-muted">
                                        <?php foreach ($package['services'] as $service): ?>
                                            <div>• <?php echo htmlspecialchars($service['name']); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-primary"><?php echo number_format((float)$package['price'], 0, ',', '.'); ?>đ</td>
                            <td><?php echo htmlspecialchars($package['sample_type'] ?: 'Đang cập nhật'); ?></td>
                            <td><span class="badge <?php echo !empty($package['is_active']) ? 'bg-success' : 'bg-danger'; ?>"><?php echo !empty($package['is_active']) ? 'Hiển thị' : 'Ẩn'; ?></span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-danger" href="lab_packages.php?id=<?php echo (int)$package['id']; ?>&delete=1<?php echo $isSystemAdmin ? '&hospital_id=' . (int)$hospitalId : ''; ?>" onclick="return confirm('Xóa gói xét nghiệm này?')">Xóa</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($packages) === 0): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Chưa có gói xét nghiệm nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="labPackageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 rounded-4">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tạo gói xét nghiệm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="max-height: calc(100vh - 220px); overflow-y: auto;">
                    <?php if ($isSystemAdmin): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Bệnh viện</label>
                            <select name="hospital_id" class="form-select" required>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?php echo (int)$hospital['id']; ?>" <?php echo (int)$hospitalId === (int)$hospital['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($hospital['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tên gói xét nghiệm <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="VD: Gói xét nghiệm tổng quát">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Dịch vụ trong gói</label>
                        <div id="labPackageServices" class="d-flex flex-column gap-3">
                            <div class="border rounded-3 p-3 bg-light overflow-hidden">
                                <div class="row g-2 mb-2">
                                    <div class="col-md-4"><input type="text" name="service_name[]" class="form-control" placeholder="Tên dịch vụ xét nghiệm"></div>
                                    <div class="col-md-2"><input type="text" name="service_icon[]" class="form-control" value="bi-clipboard2-pulse" placeholder="Icon"></div>
                                    <div class="col-md-3"><input type="number" name="service_price[]" class="form-control" min="0" step="1000" value="0" placeholder="Giá dịch vụ"></div>
                                </div>
                                <div class="border rounded-3 p-2 mb-2 bg-white">
                                    <div class="fw-bold small mb-1">Ngày xét nghiệm</div>
                                    <?php foreach ($weekdayOptions as $dayValue => $dayLabel): ?>
                                        <label class="form-check form-check-inline mb-1">
                                            <input class="form-check-input" type="checkbox" name="service_weekdays[0][]" value="<?php echo (int)$dayValue; ?>">
                                            <span class="form-check-label"><?php echo htmlspecialchars($dayLabel); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="border rounded-3 p-3 mb-2 bg-white">
                                    <label class="form-check-label fw-bold mb-2" style="color:#023f6d;"><input type="checkbox" class="form-check-input lab-period-toggle"> Buổi sáng</label>
                                    <div class="lab-time-slots d-flex flex-column gap-2" data-service-index="0" data-period="morning">
                                        <div class="row g-2 lab-time-row">
                                            <div class="col-md-5"><select name="service_morning_start[0][]" class="form-select"><option value="">Chọn giờ bắt đầu</option><?php foreach ($morningTimeOptions as $time): ?><option value="<?php echo $time; ?>"><?php echo $time; ?></option><?php endforeach; ?></select></div>
                                            <div class="col-md-5"><select name="service_morning_end[0][]" class="form-select"><option value="">Chọn giờ kết thúc</option><?php foreach ($morningTimeOptions as $time): ?><option value="<?php echo $time; ?>"><?php echo $time; ?></option><?php endforeach; ?></select></div>
                                            <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-lab-time">×</button></div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-lab-time" data-period="morning">Thêm giờ xét nghiệm buổi sáng</button>
                                </div>
                                <div class="border rounded-3 p-3 mb-2 bg-white">
                                    <label class="form-check-label fw-bold mb-2" style="color:#023f6d;"><input type="checkbox" class="form-check-input lab-period-toggle"> Buổi chiều</label>
                                    <div class="lab-time-slots d-flex flex-column gap-2" data-service-index="0" data-period="afternoon">
                                        <div class="row g-2 lab-time-row">
                                            <div class="col-md-5"><select name="service_afternoon_start[0][]" class="form-select"><option value="">Chọn giờ bắt đầu</option><?php foreach ($afternoonTimeOptions as $time): ?><option value="<?php echo $time; ?>"><?php echo $time; ?></option><?php endforeach; ?></select></div>
                                            <div class="col-md-5"><select name="service_afternoon_end[0][]" class="form-select"><option value="">Chọn giờ kết thúc</option><?php foreach ($afternoonTimeOptions as $time): ?><option value="<?php echo $time; ?>"><?php echo $time; ?></option><?php endforeach; ?></select></div>
                                            <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-lab-time">×</button></div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm mt-2 add-lab-time" data-period="afternoon">Thêm giờ xét nghiệm buổi chiều</button>
                                </div>
                                <textarea name="service_description[]" class="form-control" rows="3" placeholder="Mô tả dịch vụ"></textarea>
                            </div>
                        </div>
                        <button type="button" id="addLabService" class="btn btn-outline-primary btn-sm mt-2">+ Thêm dịch vụ</button>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                        <label class="form-check-label fw-bold" for="is_active">Đang hiển thị</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                    <button class="btn btn-primary px-4" type="submit">Tạo gói xét nghiệm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const morningStartOptions = <?php echo json_encode('<option value="">Chọn giờ bắt đầu</option>' . implode('', array_map(fn($time) => '<option value="' . $time . '">' . $time . '</option>', array_values($morningTimeOptions)))); ?>;
const morningEndOptions = <?php echo json_encode('<option value="">Chọn giờ kết thúc</option>' . implode('', array_map(fn($time) => '<option value="' . $time . '">' . $time . '</option>', array_values($morningTimeOptions)))); ?>;
const afternoonStartOptions = <?php echo json_encode('<option value="">Chọn giờ bắt đầu</option>' . implode('', array_map(fn($time) => '<option value="' . $time . '">' . $time . '</option>', array_values($afternoonTimeOptions)))); ?>;
const afternoonEndOptions = <?php echo json_encode('<option value="">Chọn giờ kết thúc</option>' . implode('', array_map(fn($time) => '<option value="' . $time . '">' . $time . '</option>', array_values($afternoonTimeOptions)))); ?>;
function labTimeGroup(index, period) {
    const startOptions = period === 'morning' ? morningStartOptions : afternoonStartOptions;
    const endOptions = period === 'morning' ? morningEndOptions : afternoonEndOptions;
    const label = period === 'morning' ? 'Buổi sáng' : 'Buổi chiều';
    const startName = period === 'morning' ? 'service_morning_start' : 'service_afternoon_start';
    const endName = period === 'morning' ? 'service_morning_end' : 'service_afternoon_end';
    return '<div class="border rounded-3 p-3 mb-2 bg-white"><label class="form-check-label fw-bold mb-2" style="color:#023f6d;"><input type="checkbox" class="form-check-input lab-period-toggle"> ' + label + '</label><div class="lab-time-slots d-flex flex-column gap-2" data-service-index="' + index + '" data-period="' + period + '"><div class="row g-2 lab-time-row"><div class="col-md-5"><select name="' + startName + '[' + index + '][]" class="form-select">' + startOptions + '</select></div><div class="col-md-5"><select name="' + endName + '[' + index + '][]" class="form-select">' + endOptions + '</select></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-lab-time">×</button></div></div></div><button type="button" class="btn btn-outline-primary btn-sm mt-2 add-lab-time" data-period="' + period + '">Thêm giờ xét nghiệm ' + label.toLowerCase() + '</button></div>';
}
document.getElementById('addLabService')?.addEventListener('click', function () {
    const wrapper = document.getElementById('labPackageServices');
    const index = wrapper.children.length;
    const item = document.createElement('div');
    item.className = 'border rounded-3 p-3 bg-light overflow-hidden';
    item.innerHTML = '<div class="row g-2 mb-2"><div class="col-md-4"><input type="text" name="service_name[]" class="form-control" placeholder="Tên dịch vụ xét nghiệm"></div><div class="col-md-2"><input type="text" name="service_icon[]" class="form-control" value="bi-clipboard2-pulse" placeholder="Icon"></div><div class="col-md-3"><input type="number" name="service_price[]" class="form-control" min="0" step="1000" value="0" placeholder="Giá dịch vụ"></div><div class="col-md-3"><button type="button" class="btn btn-outline-danger w-100" onclick="this.closest(\'.border\').remove()">Xóa</button></div></div><div class="border rounded-3 p-2 mb-2 bg-white"><div class="fw-bold small mb-1">Ngày xét nghiệm</div><?php foreach ($weekdayOptions as $dayValue => $dayLabel): ?><label class="form-check form-check-inline mb-1"><input class="form-check-input" type="checkbox" name="service_weekdays[' + index + '][]" value="<?php echo (int)$dayValue; ?>"><span class="form-check-label"><?php echo htmlspecialchars($dayLabel); ?></span></label><?php endforeach; ?></div>' + labTimeGroup(index, 'morning') + labTimeGroup(index, 'afternoon') + '<textarea name="service_description[]" class="form-control" rows="3" placeholder="Mô tả dịch vụ"></textarea>';
    wrapper.appendChild(item);
});
document.addEventListener('click', function (event) {
    if (event.target.classList.contains('remove-lab-time')) {
        event.target.closest('.lab-time-row')?.remove();
    }
    if (event.target.classList.contains('add-lab-time')) {
        const group = event.target.closest('.border').querySelector('.lab-time-slots');
        const index = group.dataset.serviceIndex;
        const period = group.dataset.period;
        const startOptions = period === 'morning' ? morningStartOptions : afternoonStartOptions;
        const endOptions = period === 'morning' ? morningEndOptions : afternoonEndOptions;
        const startName = period === 'morning' ? 'service_morning_start' : 'service_afternoon_start';
        const endName = period === 'morning' ? 'service_morning_end' : 'service_afternoon_end';
        const row = document.createElement('div');
        row.className = 'row g-2 lab-time-row';
        row.innerHTML = '<div class="col-md-5"><select name="' + startName + '[' + index + '][]" class="form-select">' + startOptions + '</select></div><div class="col-md-5"><select name="' + endName + '[' + index + '][]" class="form-select">' + endOptions + '</select></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-lab-time">×</button></div>';
        group.appendChild(row);
    }
});
</script>

<?php include '../includes/footer.php'; ?>
