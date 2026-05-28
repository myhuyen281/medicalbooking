<?php
require_once '../../../config/database.php';
include '../includes/header.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$doctorId = $_GET['id'];
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

// Lấy thông tin hiện tại
$db->query("SELECT d.*, u.full_name FROM doctors d INNER JOIN users u ON d.user_id = u.id WHERE d.id = :id");
$db->bind(':id', $doctorId);
$doctor = $db->single();

if (!$doctor) {
    echo "<h3>Không tìm thấy hồ sơ!</h3>";
    exit();
}

$db->query("SELECT id, name FROM hospitals ORDER BY name ASC");
$hospitals = $db->resultSet();

// Lấy danh sách Chuyên khoa theo bệnh viện
$db->query("SELECT s.id, s.name, hs.hospital_id FROM specialties s INNER JOIN hospital_specialties hs ON s.id = hs.specialty_id ORDER BY s.name ASC");
$specialties = $db->resultSet();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $hospitalId = $_POST['hospital_id'];
    $specialtyId = $_POST['specialty_id'];
    $experience = $_POST['experience_years'];
    $fee = str_replace([',', '.'], '', $_POST['consultation_fee']);
    $description = trim($_POST['description']);
    $doctorImageUrl = trim($_POST['doctor_image_url'] ?? '');
    $treatmentText = trim($_POST['treatment_text'] ?? '');
    $academicTitle = trim($_POST['academic_title'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $displayScheduleText = trim($_POST['display_schedule_text'] ?? '');
    $displayPriceText = trim($_POST['display_price_text'] ?? '');

    if (empty($hospitalId) || empty($specialtyId) || empty($fee)) {
        $error = "Vui lòng chọn bệnh viện, chuyên khoa và giá khám.";
    } else {
        $db->query("UPDATE doctors SET hospital_id = :hid, specialty_id = :sid, experience_years = :exp, consultation_fee = :fee, description = :desc, doctor_image_url = :doctor_image_url, treatment_text = :treatment_text, academic_title = :academic_title, gender = :gender, display_schedule_text = :display_schedule_text, display_price_text = :display_price_text WHERE id = :id");
        $db->bind(':hid', $hospitalId);
        $db->bind(':sid', $specialtyId);
        $db->bind(':exp', $experience);
        $db->bind(':fee', $fee);
        $db->bind(':desc', $description);
        $db->bind(':doctor_image_url', $doctorImageUrl);
        $db->bind(':treatment_text', $treatmentText);
        $db->bind(':academic_title', $academicTitle);
        $db->bind(':gender', $gender);
        $db->bind(':display_schedule_text', $displayScheduleText);
        $db->bind(':display_price_text', $displayPriceText);
        $db->bind(':id', $doctorId);
        
        if ($db->execute()) {
            $success = "Cập nhật hồ sơ Bác sĩ thành công!";
            // Tải lại
            $doctor['hospital_id'] = $hospitalId;
            $doctor['specialty_id'] = $specialtyId;
            $doctor['experience_years'] = $experience;
            $doctor['consultation_fee'] = $fee;
            $doctor['description'] = $description;
            $doctor['doctor_image_url'] = $doctorImageUrl;
            $doctor['treatment_text'] = $treatmentText;
            $doctor['academic_title'] = $academicTitle;
            $doctor['gender'] = $gender;
            $doctor['display_schedule_text'] = $displayScheduleText;
            $doctor['display_price_text'] = $displayPriceText;
        } else {
            $error = "Có lỗi xảy ra khi cập nhật.";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Sửa Hồ sơ Bác sĩ</h2>
    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Quay lại</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tên Bác sĩ</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($doctor['full_name']); ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Đơn vị bệnh viện <span class="text-danger">*</span></label>
                        <select name="hospital_id" id="hospitalSelect" class="form-select" required>
                            <option value="">-- Chọn bệnh viện --</option>
                            <?php foreach($hospitals as $h): ?>
                                <option value="<?php echo $h['id']; ?>" <?php echo ($doctor['hospital_id'] ?? '') == $h['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($h['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Chuyên khoa <span class="text-danger">*</span></label>
                        <select name="specialty_id" id="specialtySelect" class="form-select" required>
                            <option value="">-- Chọn Chuyên khoa --</option>
                            <?php foreach($specialties as $s): ?>
                                <option value="<?php echo $s['id']; ?>" data-hospital="<?php echo $s['hospital_id']; ?>" <?php echo $doctor['specialty_id'] == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Kinh nghiệm (Số năm)</label>
                            <input type="number" name="experience_years" class="form-control" min="0" value="<?php echo $doctor['experience_years']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Giá khám (VNĐ) <span class="text-danger">*</span></label>
                            <input type="number" name="consultation_fee" class="form-control" min="0" required value="<?php echo round($doctor['consultation_fee']); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Giới thiệu chi tiết</label>
                        <textarea name="description" class="form-control" rows="5"><?php echo htmlspecialchars($doctor['description']); ?></textarea>
                    </div>

                    <div class="border rounded-3 p-3 mb-3 bg-light">
                        <h6 class="fw-bold mb-3">Nội dung hiển thị trong ô chọn bác sĩ</h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Ảnh bác sĩ</label>
                            <input type="text" name="doctor_image_url" class="form-control" placeholder="Dán link ảnh bác sĩ" value="<?php echo htmlspecialchars($doctor['doctor_image_url'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Chuyên khoa / Chuyên trị</label>
                            <input type="text" name="treatment_text" class="form-control" placeholder="VD: KHOA KHÁM BỆNH, Nhi khoa..." value="<?php echo htmlspecialchars($doctor['treatment_text'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Học hàm / học vị</label>
                            <input type="text" name="academic_title" class="form-control" placeholder="VD: ThS.BS, BSCKII..." value="<?php echo htmlspecialchars($doctor['academic_title'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Giới tính</label>
                            <select name="gender" class="form-select">
                                <option value="">Chọn giới tính</option>
                                <option value="Nam" <?php echo ($doctor['gender'] ?? '') === 'Nam' ? 'selected' : ''; ?>>Nam</option>
                                <option value="Nữ" <?php echo ($doctor['gender'] ?? '') === 'Nữ' ? 'selected' : ''; ?>>Nữ</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Lịch khám hiển thị</label>
                            <input type="text" name="display_schedule_text" class="form-control" placeholder="VD: Thứ 2, Thứ 4, Thứ 6" value="<?php echo htmlspecialchars($doctor['display_schedule_text'] ?? ''); ?>">
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold">Giá khám hiển thị</label>
                            <input type="text" name="display_price_text" class="form-control" placeholder="VD: 250.000đ" value="<?php echo htmlspecialchars($doctor['display_price_text'] ?? ''); ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning"><i class="bi bi-pencil-square me-1"></i> Cập nhật Hồ Sơ</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const hospitalSelect = document.getElementById('hospitalSelect');
    const specialtySelect = document.getElementById('specialtySelect');

    if (hospitalSelect && specialtySelect) {
        const selectedSpecialty = '<?php echo $doctor['specialty_id']; ?>';
        const specialtyOptions = Array.from(specialtySelect.querySelectorAll('option[data-hospital]'));

        function filterSpecialties() {
            const hospitalId = hospitalSelect.value;
            specialtySelect.innerHTML = hospitalId ? '<option value="">-- Chọn chuyên khoa --</option>' : '<option value="">-- Chọn bệnh viện trước --</option>';
            specialtyOptions.forEach(function (option) {
                if (option.dataset.hospital === hospitalId) {
                    const cloned = option.cloneNode(true);
                    cloned.selected = cloned.value === selectedSpecialty;
                    specialtySelect.appendChild(cloned);
                }
            });
            specialtySelect.disabled = !hospitalId;
        }

        hospitalSelect.addEventListener('change', filterSpecialties);
        filterSpecialties();
    }
</script>
<?php include '../includes/footer.php'; ?>
