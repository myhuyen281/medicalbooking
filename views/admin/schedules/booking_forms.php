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
    $db->query("CREATE TABLE IF NOT EXISTS hospital_booking_forms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hospital_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        icon VARCHAR(255) NULL,
        target VARCHAR(30) NOT NULL DEFAULT 'specialty',
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (hospital_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->execute();
} catch (Exception $e) {
    $error = 'Không thể khởi tạo bảng hình thức đặt khám.';
}

try {
    $db->query("ALTER TABLE hospital_booking_forms MODIFY icon VARCHAR(255) NULL");
    $db->execute();
} catch (Exception $e) {
}

function uploadBookingFormIcon($file, $index) {
    if (empty($file['name'][$index]) || empty($file['tmp_name'][$index])) {
        return '';
    }
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    if (!in_array($file['type'][$index] ?? '', $allowedTypes, true)) {
        return '';
    }
    $uploadDir = __DIR__ . '/../../../uploads/booking_forms/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $extension = pathinfo($file['name'][$index], PATHINFO_EXTENSION);
    $fileName = 'booking_form_' . time() . '_' . $index . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $uploadDir . $fileName;
    if (!move_uploaded_file($file['tmp_name'][$index], $targetPath)) {
        return '';
    }
    return 'uploads/booking_forms/' . $fileName;
}

$hospitalId = $isHospitalAdmin ? $currentHospitalId : ($_GET['hospital_id'] ?? null);
if (!$hospitalId && $isSystemAdmin) {
    $db->query("SELECT id FROM hospitals ORDER BY name ASC LIMIT 1");
    $firstHospital = $db->single();
    $hospitalId = $firstHospital['id'] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isHospitalAdmin && !$currentHospitalSubscriptionActive) {
    $error = hospitalSubscriptionExpiredMessage();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hospitalId = $isHospitalAdmin ? $currentHospitalId : ($_POST['hospital_id'] ?? $hospitalId);
    $formIds = $_POST['form_id'] ?? [];
    $formNames = $_POST['form_name'] ?? [];
    $existingIcons = $_POST['existing_icon'] ?? [];
    $formTargets = $_POST['form_target'] ?? [];

    if (empty($hospitalId)) {
        $error = 'Vui lòng chọn bệnh viện.';
    } elseif ($isHospitalAdmin && !hospitalPlanAllows(getHospitalSubscriptionPlan($db, $hospitalId), 'booking_forms')) {
        $error = 'Gói hiện tại chưa hỗ trợ quản lý biểu mẫu đặt lịch. Vui lòng nâng cấp lên Gói Nâng Cao hoặc Premium.';
    } else {
        $db->query("SELECT id FROM hospital_booking_forms WHERE hospital_id = :hospital_id");
        $db->bind(':hospital_id', $hospitalId);
        $existingFormIdsBeforeSave = array_map('intval', array_column($db->resultSet(), 'id'));
        $keptFormIds = [];
        foreach ($formNames as $index => $formName) {
            $formName = trim($formName);
            $formId = (int)($formIds[$index] ?? 0);
            if ($formName === '') {
                continue;
            }
            $uploadedIcon = uploadBookingFormIcon($_FILES['form_icon'] ?? [], $index);
            $icon = $uploadedIcon !== '' ? $uploadedIcon : trim($existingIcons[$index] ?? '');
            $target = in_array($formTargets[$index] ?? 'specialty', ['specialty', 'doctor'], true) ? $formTargets[$index] : 'specialty';
            if ($formId > 0) {
                $db->query("UPDATE hospital_booking_forms SET name = :name, icon = :icon, target = :target, sort_order = :sort_order WHERE id = :id AND hospital_id = :hospital_id");
                $db->bind(':id', $formId);
                $db->bind(':hospital_id', $hospitalId);
                $db->bind(':name', $formName);
                $db->bind(':icon', $icon);
                $db->bind(':target', $target);
                $db->bind(':sort_order', $index);
                $db->execute();
                $keptFormIds[] = $formId;
            } else {
                $db->query("INSERT INTO hospital_booking_forms (hospital_id, name, icon, target, sort_order) VALUES (:hospital_id, :name, :icon, :target, :sort_order)");
                $db->bind(':hospital_id', $hospitalId);
                $db->bind(':name', $formName);
                $db->bind(':icon', $icon);
                $db->bind(':target', $target);
                $db->bind(':sort_order', $index);
                $db->execute();
            }
        }
        $deletedFormIds = array_values(array_diff($existingFormIdsBeforeSave, $keptFormIds));
        if (count($deletedFormIds) > 0) {
            $db->query("DELETE FROM hospital_services WHERE hospital_id = :hospital_id AND booking_form_id IN (" . implode(',', array_map('intval', $deletedFormIds)) . ")");
            $db->bind(':hospital_id', $hospitalId);
            $db->execute();

            $db->query("DELETE FROM hospital_booking_forms WHERE hospital_id = :hospital_id AND id IN (" . implode(',', array_map('intval', $deletedFormIds)) . ")");
            $db->bind(':hospital_id', $hospitalId);
            $db->execute();
        }
        $success = 'Cập nhật hình thức đặt khám thành công.';
    }
}

$db->query("SELECT id, name FROM hospitals ORDER BY name ASC");
$hospitals = $db->resultSet();

$bookingForms = [];
if ($hospitalId) {
    $db->query("SELECT * FROM hospital_booking_forms WHERE hospital_id = :hospital_id ORDER BY sort_order ASC, id ASC");
    $db->bind(':hospital_id', $hospitalId);
    $bookingForms = $db->resultSet();
}
if (!count($bookingForms)) {
    $bookingForms = [
        ['name' => '', 'icon' => '', 'target' => 'specialty'],
    ];
}
$servicesUrl = 'services.php' . ($isSystemAdmin && $hospitalId ? '?hospital_id=' . (int)$hospitalId : '');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Các hình thức đặt khám</h2>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Quay lại lịch khám</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form method="POST" enctype="multipart/form-data">
            <?php if ($isSystemAdmin): ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Bệnh viện</label>
                    <select name="hospital_id" class="form-select" onchange="window.location.href='booking_forms.php?hospital_id=' + this.value">
                        <?php foreach ($hospitals as $hospital): ?>
                            <option value="<?php echo $hospital['id']; ?>" <?php echo (int)$hospitalId === (int)$hospital['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($hospital['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="alert alert-info border-0">
                Mỗi hình thức đặt khám là một ô hiển thị ngoài website. Bấm <strong>Thêm dịch vụ khám</strong> để nhập nội dung dịch vụ, lịch khám, giá và chi tiết hiển thị cho bệnh nhân.
            </div>


            <div id="formRows" class="d-flex flex-column gap-2 mb-3">
                <?php foreach ($bookingForms as $form): ?>
                    <?php $formServicesUrl = !empty($form['id']) ? 'services.php' . ($isSystemAdmin && $hospitalId ? '?hospital_id=' . (int)$hospitalId . '&booking_form_id=' . (int)$form['id'] : '?booking_form_id=' . (int)$form['id']) : $servicesUrl; ?>
                    <div class="row g-2 form-row border rounded-3 p-2 align-items-center">
                        <input type="hidden" name="form_id[]" value="<?php echo (int)($form['id'] ?? 0); ?>">
                        <div class="col-md-5"><input type="text" name="form_name[]" class="form-control" placeholder="Tên hình thức, ví dụ: Đặt khám theo chuyên khoa" value="<?php echo htmlspecialchars($form['name'] ?? ''); ?>"></div>
                        <div class="col-md-2">
                            <input type="hidden" name="existing_icon[]" value="<?php echo htmlspecialchars($form['icon'] ?? ''); ?>">
                            <input type="file" name="form_icon[]" class="form-control" accept="image/*">
                            <?php if (!empty($form['icon'])): ?>
                                <img src="<?php echo htmlspecialchars($base_url . '/' . $form['icon']); ?>" alt="Icon" class="mt-2" style="width:32px;height:32px;object-fit:contain;">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <select name="form_target[]" class="form-select">
                                <option value="specialty" <?php echo ($form['target'] ?? 'specialty') === 'specialty' ? 'selected' : ''; ?>>Trang dịch vụ khám</option>
                                <option value="doctor" <?php echo ($form['target'] ?? '') === 'doctor' ? 'selected' : ''; ?>>Trang bác sĩ</option>
                            </select>
                            <?php if (!empty($form['id'])): ?>
                                <a href="<?php echo htmlspecialchars($formServicesUrl); ?>" class="btn btn-outline-primary flex-shrink-0">Thêm dịch vụ khám</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-primary flex-shrink-0 save-form-first">Thêm dịch vụ khám</button>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-form">×</button></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" id="addForm" class="btn btn-outline-primary me-2">Thêm hình thức</button>
            <button type="submit" class="btn btn-primary">Lưu hình thức</button>
        </form>
    </div>
</div>

<script>
document.getElementById('addForm').addEventListener('click', function () {
    const row = document.createElement('div');
    row.className = 'row g-2 form-row border rounded-3 p-2 align-items-center';
    row.innerHTML = '<input type="hidden" name="form_id[]" value="0"><div class="col-md-5"><input type="text" name="form_name[]" class="form-control" placeholder="Tên hình thức, ví dụ: Đặt khám theo chuyên khoa"></div><div class="col-md-2"><input type="hidden" name="existing_icon[]" value=""><input type="file" name="form_icon[]" class="form-control" accept="image/*"></div><div class="col-md-4 d-flex gap-2"><select name="form_target[]" class="form-select"><option value="specialty">Trang dịch vụ khám</option><option value="doctor">Trang bác sĩ</option></select><button type="button" class="btn btn-outline-primary flex-shrink-0 save-form-first">Thêm dịch vụ khám</button></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-form">×</button></div>';
    document.getElementById('formRows').appendChild(row);
});

document.addEventListener('click', function (event) {
    if (event.target.classList.contains('save-form-first')) {
        alert('Vui lòng bấm Lưu hình thức trước, sau đó bấm Thêm dịch vụ khám để lưu riêng cho hình thức này.');
        return;
    }
    if (event.target.classList.contains('remove-form')) {
        const rows = document.querySelectorAll('.form-row');
        if (rows.length > 1) {
            event.target.closest('.form-row').remove();
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
