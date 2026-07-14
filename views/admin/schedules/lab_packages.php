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

$categoryConfig = [
    'lab' => ['title' => 'Gói xét nghiệm', 'desc' => 'Tạo và quản lý các gói xét nghiệm của bệnh viện.', 'name_ph' => 'VD: Gói xét nghiệm tổng quát', 'service_ph' => 'Tên dịch vụ xét nghiệm', 'day_label' => 'Ngày xét nghiệm', 'icon' => 'bi-clipboard2-pulse'],
    'imaging' => ['title' => 'Gói chụp phim & nội soi', 'desc' => 'Tạo và quản lý các gói chụp X-quang, siêu âm, CT, MRI và nội soi.', 'name_ph' => 'VD: Gói chụp X-quang tổng quát', 'service_ph' => 'Tên dịch vụ chụp phim/nội soi', 'day_label' => 'Ngày thực hiện', 'icon' => 'bi-camera'],
    'vaccination' => ['title' => 'Gói tiêm chủng', 'desc' => 'Tạo và quản lý các gói tiêm chủng, vắc-xin của bệnh viện.', 'name_ph' => 'VD: Gói vắc-xin 6 trong 1', 'service_ph' => 'Tên mũi tiêm/vắc-xin', 'day_label' => 'Ngày tiêm', 'icon' => 'bi-eyedropper'],
    'health' => ['title' => 'Gói khám sức khỏe', 'desc' => 'Tạo và quản lý các gói khám sức khỏe tổng quát, định kỳ của bệnh viện.', 'name_ph' => 'VD: Gói khám sức khỏe tổng quát', 'service_ph' => 'Tên hạng mục khám', 'day_label' => 'Ngày khám', 'icon' => 'bi-heart-pulse'],
    'circular' => ['title' => 'Khám sức khỏe thông tư', 'desc' => 'Tạo và quản lý các gói khám sức khỏe theo thông tư, giấy khám sức khỏe.', 'name_ph' => 'VD: Gói khám sức khỏe thông tư', 'service_ph' => 'Tên hạng mục khám', 'day_label' => 'Ngày khám', 'icon' => 'bi-file-medical'],
    'homecare' => ['title' => 'Gói Y tế tại nhà', 'desc' => 'Tạo và quản lý các gói dịch vụ y tế tại nhà của bệnh viện.', 'name_ph' => 'VD: Gói chăm sóc y tế tại nhà', 'service_ph' => 'Tên dịch vụ tại nhà', 'day_label' => 'Ngày thực hiện', 'icon' => 'bi-house-heart'],
];
$category = $_GET['category'] ?? ($_POST['category'] ?? 'lab');
if (!isset($categoryConfig[$category])) { $category = 'lab'; }
$cfg = $categoryConfig[$category];
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
    "ALTER TABLE lab_packages ADD COLUMN category VARCHAR(20) NOT NULL DEFAULT 'lab' AFTER hospital_id",
    "ALTER TABLE lab_packages ADD COLUMN icon_path VARCHAR(255) NULL AFTER category",
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

function uploadLabPackageIcon($file) {
    if (empty($file['name']) || empty($file['tmp_name'])) {
        return '';
    }
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    if (!in_array($file['type'] ?? '', $allowedTypes, true)) {
        return '';
    }
    $uploadDir = __DIR__ . '/../../../uploads/lab_packages/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'lab_package_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
        return '';
    }
    return 'uploads/lab_packages/' . $fileName;
}

$hospitalId = $isHospitalAdmin ? $currentHospitalId : ($_GET['hospital_id'] ?? null);
if (!$hospitalId && $isSystemAdmin) {
    $db->query("SELECT id FROM hospitals ORDER BY name ASC LIMIT 1");
    $firstHospital = $db->single();
    $hospitalId = $firstHospital['id'] ?? null;
}

if (isset($_GET['delete'], $_GET['id']) && $hospitalId && $isHospitalAdmin && !$currentHospitalSubscriptionActive) {
    $error = hospitalSubscriptionExpiredMessage();
} elseif (isset($_GET['delete'], $_GET['id']) && $hospitalId) {
    $db->query("DELETE lps FROM lab_package_services lps INNER JOIN lab_packages lp ON lp.id = lps.package_id WHERE lp.id = :id AND lp.hospital_id = :hospital_id");
    $db->bind(':id', (int)$_GET['id']);
    $db->bind(':hospital_id', $hospitalId);
    $db->execute();
    $db->query("DELETE FROM lab_packages WHERE id = :id AND hospital_id = :hospital_id");
    $db->bind(':id', (int)$_GET['id']);
    $db->bind(':hospital_id', $hospitalId);
    $db->execute();
    $success = 'Đã xóa ' . mb_strtolower($cfg['title']) . '.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isHospitalAdmin && !$currentHospitalSubscriptionActive) {
    $error = hospitalSubscriptionExpiredMessage();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hospitalId = $isHospitalAdmin ? $currentHospitalId : ($_POST['hospital_id'] ?? $hospitalId);
    $name = trim($_POST['name'] ?? '');
    $price = 0;
    $sampleType = '';
    $turnaroundTime = '';
    $description = '';
    $iconPath = uploadLabPackageIcon($_FILES['package_icon'] ?? []);
    $isActive = isset($_POST['is_active_select']) ? (int)$_POST['is_active_select'] : (isset($_POST['is_active']) ? 1 : 0);
    $updatedExistingIcon = false;
    if (!empty($_FILES['saved_package_icon']['name']) && is_array($_FILES['saved_package_icon']['name'])) {
        foreach ($_FILES['saved_package_icon']['name'] as $packageIconId => $fileName) {
            if (empty($fileName)) {
                continue;
            }
            $file = [
                'name' => $_FILES['saved_package_icon']['name'][$packageIconId],
                'type' => $_FILES['saved_package_icon']['type'][$packageIconId] ?? '',
                'tmp_name' => $_FILES['saved_package_icon']['tmp_name'][$packageIconId] ?? '',
                'error' => $_FILES['saved_package_icon']['error'][$packageIconId] ?? 0,
                'size' => $_FILES['saved_package_icon']['size'][$packageIconId] ?? 0,
            ];
            $savedIconPath = uploadLabPackageIcon($file);
            if ($savedIconPath !== '') {
                $db->query("UPDATE lab_packages SET icon_path = :icon_path WHERE id = :id AND hospital_id = :hospital_id");
                $db->bind(':icon_path', $savedIconPath);
                $db->bind(':id', (int)$packageIconId);
                $db->bind(':hospital_id', $hospitalId);
                $db->execute();
                $updatedExistingIcon = true;
            }
        }
    }

    if (empty($hospitalId)) {
        $error = 'Vui lòng chọn bệnh viện.';
    } elseif ($isHospitalAdmin && !hospitalPlanAllows(getHospitalSubscriptionPlan($db, $hospitalId), 'lab_packages')) {
        $error = 'Gói hiện tại chưa hỗ trợ quản lý gói xét nghiệm. Vui lòng nâng cấp lên Gói Nâng Cao hoặc Premium.';
    } elseif ($name === '' && !$updatedExistingIcon) {
        $error = 'Vui lòng nhập tên gói.';
    } elseif ($name !== '') {
        $db->query("INSERT INTO lab_packages (hospital_id, category, icon_path, name, price, sample_type, turnaround_time, description, is_active)
                    VALUES (:hospital_id, :category, :icon_path, :name, :price, :sample_type, :turnaround_time, :description, :is_active)");
        $db->bind(':hospital_id', $hospitalId);
        $db->bind(':category', $category);
        $db->bind(':icon_path', $iconPath);
        $db->bind(':name', $name);
        $db->bind(':price', $price);
        $db->bind(':sample_type', $sampleType);
        $db->bind(':turnaround_time', $turnaroundTime);
        $db->bind(':description', $description);
        $db->bind(':is_active', $isActive);
        $db->execute();
        $packageId = $db->lastInsertId();
        $postedServiceNames = $_POST['service_name'] ?? [];
        if (count(array_filter(array_map('trim', $postedServiceNames))) === 0) {
            $postedServiceNames = [$name];
        }
        foreach ($postedServiceNames as $index => $serviceName) {
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
        $success = 'Đã tạo ' . mb_strtolower($cfg['title']) . '.';
    } elseif ($updatedExistingIcon) {
        $success = 'Đã cập nhật logo gói.';
    }
}

$hospitals = [];
if ($isSystemAdmin) {
    $db->query("SELECT id, name FROM hospitals ORDER BY name ASC");
    $hospitals = $db->resultSet();
}

$packages = [];
if ($hospitalId) {
    $db->query("SELECT * FROM lab_packages WHERE hospital_id = :hospital_id AND category = :category ORDER BY id DESC");
    $db->bind(':hospital_id', $hospitalId);
    $db->bind(':category', $category);
    $packages = $db->resultSet();
    foreach ($packages as $index => $package) {
        $db->query("SELECT * FROM lab_package_services WHERE package_id = :package_id ORDER BY sort_order ASC, id ASC");
        $db->bind(':package_id', (int)$package['id']);
        $packages[$index]['services'] = $db->resultSet();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><?php echo $category === 'imaging' ? 'Gói chụp phim nội soi' : htmlspecialchars($cfg['title']); ?></h2>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Quay lại lịch khám</a>
</div>

<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
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

            <div class="alert alert-info border-0">
                Mỗi gói là một ô hiển thị ngoài website. Nhập tên gói, thêm dịch vụ, lịch thực hiện, giá và trạng thái hiển thị cho bệnh nhân.
            </div>

            <div id="packageRows" class="d-flex flex-column gap-2 mb-3">
                <?php $visiblePackages = count($packages) ? $packages : [['id' => 0, 'name' => '', 'is_active' => 1]]; ?>
                <?php foreach ($visiblePackages as $package): ?>
                    <div class="row g-2 package-row border rounded-3 p-2 align-items-center">
                        <div class="col-md-5"><input type="text" name="<?php echo empty($package['id']) ? 'name' : 'saved_name[]'; ?>" class="form-control" <?php echo empty($package['id']) ? 'required' : 'readonly'; ?> placeholder="<?php echo htmlspecialchars($cfg['name_ph']); ?>" value="<?php echo htmlspecialchars($package['name'] ?? ''); ?>"></div>
                        <div class="col-md-2"><input type="file" name="<?php echo empty($package['id']) ? 'package_icon' : 'saved_package_icon[' . (int)$package['id'] . ']'; ?>" class="form-control" accept="image/*"></div>
                        <div class="col-md-4 d-flex gap-2">
                            <select class="form-select" name="is_active_select"><option value="1" <?php echo !empty($package['is_active']) ? 'selected' : ''; ?>>Hiển thị</option><option value="0" <?php echo empty($package['is_active']) ? 'selected' : ''; ?>>Ẩn</option></select>
                            <?php if (!empty($package['id'])): ?>
                                <a href="lab_package_services.php?package_id=<?php echo (int)$package['id']; ?>&category=<?php echo urlencode($category); ?><?php echo $isSystemAdmin ? '&hospital_id=' . (int)$hospitalId : ''; ?>" class="btn btn-outline-primary flex-shrink-0">Thêm dịch vụ</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-primary flex-shrink-0 save-package-first">Thêm dịch vụ</button>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-1">
                            <?php if (!empty($package['id'])): ?>
                                <a class="btn btn-outline-danger w-100" href="lab_packages.php?category=<?php echo urlencode($category); ?>&id=<?php echo (int)$package['id']; ?>&delete=1<?php echo $isSystemAdmin ? '&hospital_id=' . (int)$hospitalId : ''; ?>" onclick="return confirm('Xóa mục này?')">×</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-danger w-100 clear-package">×</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="is_active" value="1">
            <input type="hidden" name="service_name[]" value="">
            <input type="hidden" name="service_icon[]" value="<?php echo htmlspecialchars($cfg['icon']); ?>">
            <input type="hidden" name="service_price[]" value="0">
            <input type="hidden" name="service_description[]" value="">

            <button type="button" id="addPackageRow" class="btn btn-outline-primary me-2">Thêm gói</button>
            <button type="submit" class="btn btn-primary">Lưu gói</button>
        </form>


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
document.getElementById('addPackageRow')?.addEventListener('click', function () {
    const row = document.createElement('div');
    row.className = 'row g-2 package-row border rounded-3 p-2 align-items-center';
    row.innerHTML = '<div class="col-md-5"><input type="text" name="name" class="form-control" required placeholder="<?php echo htmlspecialchars($cfg['name_ph']); ?>"></div><div class="col-md-2"><input type="file" name="package_icon" class="form-control" accept="image/*"></div><div class="col-md-4 d-flex gap-2"><select class="form-select" name="is_active_select"><option value="1">Hiển thị</option><option value="0">Ẩn</option></select><button type="button" class="btn btn-outline-primary flex-shrink-0 save-package-first">Thêm dịch vụ</button></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 clear-package">×</button></div>';
    document.getElementById('packageRows').appendChild(row);
});

document.addEventListener('click', function (event) {
    if (event.target.classList.contains('save-package-first')) {
        alert('Vui lòng bấm Lưu gói trước, sau đó thêm chi tiết dịch vụ cho gói này.');
        return;
    }
    if (event.target.classList.contains('clear-package')) {
        const row = event.target.closest('.package-row');
        if (document.querySelectorAll('.package-row').length > 1) {
            row.remove();
        } else {
            row.querySelector('input[type="text"]').value = '';
        }
        return;
    }
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
