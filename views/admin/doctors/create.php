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

// Lấy danh sách Users có role='doctor' nhưng CHƯA có trong bảng doctors
$db->query("SELECT id, full_name, email FROM users WHERE role = 'doctor' AND id NOT IN (SELECT user_id FROM doctors)");
$availableUsers = $db->resultSet();

$db->query("SELECT id, name FROM hospitals ORDER BY name ASC");
$hospitals = $db->resultSet();

// Lấy danh sách Chuyên khoa theo bệnh viện
$db->query("SELECT s.id, s.name, hs.hospital_id FROM specialties s INNER JOIN hospital_specialties hs ON s.id = hs.specialty_id ORDER BY s.name ASC");
$specialties = $db->resultSet();

if ($_SERVER["REQUEST_METHOD"] == "POST" && $isHospitalAdmin && !$currentHospitalSubscriptionActive) {
    $error = hospitalSubscriptionExpiredMessage();
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_POST['user_id'];
    $hospitalId = $_POST['hospital_id'];
    $specialtyId = $_POST['specialty_id'];
    $experience = $_POST['experience_years'];
    $fee = str_replace([',', '.'], '', $_POST['consultation_fee']); // Clean format
    $description = trim($_POST['description']);
    $doctorImageUrl = trim($_POST['doctor_image_url'] ?? '');
    $treatmentText = trim($_POST['treatment_text'] ?? '');
    $academicTitle = trim($_POST['academic_title'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $displayScheduleText = trim($_POST['display_schedule_text'] ?? '');
    $displayPriceText = trim($_POST['display_price_text'] ?? '');

    if (empty($userId) || empty($hospitalId) || empty($specialtyId) || empty($fee)) {
        $error = "Vui lòng chọn User, bệnh viện, chuyên khoa và giá khám.";
    } else {
        $plan = getHospitalSubscriptionPlan($db, $hospitalId);
        $doctorLimit = hospitalPlanLimit($plan, 'doctor_limit');
        if ($doctorLimit !== null) {
            $db->query("SELECT COUNT(*) AS total FROM doctors WHERE hospital_id = :hospital_id");
            $db->bind(':hospital_id', $hospitalId);
            $doctorCount = (int)($db->single()['total'] ?? 0);
            if ($doctorCount >= $doctorLimit) {
                $error = hospitalPlanLimitMessage($plan, 'tối đa bác sĩ', $doctorLimit);
            }
        }
    }

    if (empty($error)) {
        $db->query("INSERT INTO doctors (user_id, hospital_id, specialty_id, experience_years, consultation_fee, description, doctor_image_url, treatment_text, academic_title, gender, display_schedule_text, display_price_text, approval_status) VALUES (:uid, :hid, :sid, :exp, :fee, :desc, :doctor_image_url, :treatment_text, :academic_title, :gender, :display_schedule_text, :display_price_text, 'approved')");
        $db->bind(':uid', $userId);
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
        
        if ($db->execute()) {
            $success = "Tạo hồ sơ Bác sĩ thành công!";
            // Làm mới mảng availableUsers để ẩn user vừa thêm
            $db->query("SELECT id, full_name, email FROM users WHERE role = 'doctor' AND id NOT IN (SELECT user_id FROM doctors)");
            $availableUsers = $db->resultSet();
        } else {
            $error = "Có lỗi xảy ra, không thể tạo hồ sơ.";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Thêm Hồ sơ Bác sĩ</h2>
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
                        <label class="form-label fw-bold">Chọn User làm Bác sĩ <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-select" required>
                            <option value="">-- Chọn User (Phải có Role là Bác sĩ) --</option>
                            <?php foreach($availableUsers as $u): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(count($availableUsers) == 0): ?>
                            <small class="text-danger">Không có User nào trống sẵn sàng làm bác sĩ. Hãy vào <a href="../users/create.php">Quản lý người dùng</a> để tạo trước.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Đơn vị bệnh viện <span class="text-danger">*</span></label>
                        <select name="hospital_id" id="hospitalSelect" class="form-select" required>
                            <option value="">-- Chọn bệnh viện --</option>
                            <?php foreach($hospitals as $h): ?>
                                <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Chuyên khoa <span class="text-danger">*</span></label>
                        <select name="specialty_id" id="specialtySelect" class="form-select" required disabled>
                            <option value="">-- Chọn bệnh viện trước --</option>
                            <?php foreach($specialties as $s): ?>
                                <option value="<?php echo $s['id']; ?>" data-hospital="<?php echo $s['hospital_id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Kinh nghiệm (Số năm)</label>
                            <input type="number" name="experience_years" class="form-control" min="0" placeholder="VD: 5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Giá khám (VNĐ) <span class="text-danger">*</span></label>
                            <input type="number" name="consultation_fee" class="form-control" min="0" required placeholder="VD: 300000">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Giới thiệu về Bác sĩ (Kinh nghiệm, học vấn, ...)</label>
                        <textarea name="description" class="form-control" rows="5"></textarea>
                    </div>

                    <div class="border rounded-3 p-3 mb-3 bg-light">
                        <h6 class="fw-bold mb-3">Nội dung hiển thị trong ô chọn bác sĩ</h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Ảnh bác sĩ</label>
                            <input type="text" name="doctor_image_url" class="form-control" placeholder="Dán link ảnh bác sĩ">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Chuyên khoa / Chuyên trị</label>
                            <input type="text" name="treatment_text" class="form-control" placeholder="VD: KHOA KHÁM BỆNH, Nhi khoa...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Học hàm / học vị</label>
                            <input type="text" name="academic_title" class="form-control" placeholder="VD: ThS.BS, BSCKII...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Giới tính</label>
                            <select name="gender" class="form-select">
                                <option value="">Chọn giới tính</option>
                                <option value="Nam">Nam</option>
                                <option value="Nữ">Nữ</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Lịch khám hiển thị</label>
                            <input type="text" name="display_schedule_text" class="form-control" placeholder="VD: Thứ 2, Thứ 4, Thứ 6">
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold">Giá khám hiển thị</label>
                            <input type="text" name="display_price_text" class="form-control" placeholder="VD: 250.000đ">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" <?php echo count($availableUsers) == 0 ? 'disabled' : ''; ?>><i class="bi bi-save me-1"></i> Lưu Hồ Sơ</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const hospitalSelect = document.getElementById('hospitalSelect');
    const specialtySelect = document.getElementById('specialtySelect');

    if (hospitalSelect && specialtySelect) {
        const specialtyOptions = Array.from(specialtySelect.querySelectorAll('option[data-hospital]'));
        hospitalSelect.addEventListener('change', function () {
            const hospitalId = this.value;
            specialtySelect.innerHTML = hospitalId ? '<option value="">-- Chọn chuyên khoa --</option>' : '<option value="">-- Chọn bệnh viện trước --</option>';
            specialtyOptions.forEach(function (option) {
                if (option.dataset.hospital === hospitalId) {
                    specialtySelect.appendChild(option.cloneNode(true));
                }
            });
            specialtySelect.disabled = !hospitalId;
        });
    }
</script>
<?php include '../includes/footer.php'; ?>
