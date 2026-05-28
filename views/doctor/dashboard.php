<?php
require_once '../../config/database.php';
include 'includes/header.php';

$db = new Database();
$error = '';
$success = '';

$db->query("SELECT id, name FROM hospitals ORDER BY name ASC");
$hospitals = $db->resultSet();

$db->query("SELECT s.id, s.name, hs.hospital_id FROM specialties s INNER JOIN hospital_specialties hs ON s.id = hs.specialty_id ORDER BY s.name ASC");
$specialties = $db->resultSet();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_profile'])) {
    $hospitalId = $_POST['hospital_id'];
    $specialtyId = $_POST['specialty_id'];
    $experience = $_POST['experience_years'];
    $fee = str_replace([',', '.'], '', $_POST['consultation_fee']);
    $description = trim($_POST['description']);

    if (empty($hospitalId) || empty($specialtyId) || $fee === '') {
        $error = "Vui lòng chọn bệnh viện, chuyên khoa và nhập giá khám.";
    } else {
        $db->query("INSERT INTO doctors (user_id, hospital_id, specialty_id, experience_years, consultation_fee, description, approval_status) VALUES (:uid, :hid, :sid, :exp, :fee, :desc, 'pending')");
        $db->bind(':uid', $_SESSION['user_id']);
        $db->bind(':hid', $hospitalId);
        $db->bind(':sid', $specialtyId);
        $db->bind(':exp', $experience);
        $db->bind(':fee', $fee);
        $db->bind(':desc', $description);

        if ($db->execute()) {
            $success = "Đã hoàn thiện hồ sơ bác sĩ.";
        } else {
            $error = "Không thể lưu hồ sơ bác sĩ.";
        }
    }
}

// Tìm thông tin hồ sơ bác sĩ dựa vào user_id đang đăng nhập
$db->query("SELECT d.*, s.name as specialty_name, h.name as hospital_name FROM doctors d LEFT JOIN specialties s ON d.specialty_id = s.id LEFT JOIN hospitals h ON d.hospital_id = h.id WHERE d.user_id = :uid");
$db->bind(':uid', $_SESSION['user_id']);
$doctorProfile = $db->single();

if (!$doctorProfile): ?>
    <div class="alert alert-warning shadow-sm">
        <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Hồ sơ chưa hoàn thiện</h4>
        <p>Tài khoản của bạn đã được cấp quyền Bác sĩ. Vui lòng hoàn thiện hồ sơ để có thể nhận lịch khám.</p>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Hoàn thiện hồ sơ bác sĩ</h5>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="">
                <input type="hidden" name="create_profile" value="1">
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
                        <label class="form-label fw-bold">Kinh nghiệm (số năm)</label>
                        <input type="number" name="experience_years" class="form-control" min="0" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Giá khám (VNĐ) <span class="text-danger">*</span></label>
                        <input type="number" name="consultation_fee" class="form-control" min="0" required placeholder="VD: 300000">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Giới thiệu / kinh nghiệm</label>
                    <textarea name="description" class="form-control" rows="5" placeholder="Nhập giới thiệu ngắn về chuyên môn, kinh nghiệm..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Lưu hồ sơ</button>
            </form>
        </div>
    </div>
<?php elseif (($doctorProfile['approval_status'] ?? 'approved') !== 'approved'): ?>
    <div class="alert alert-info shadow-sm">
        <h4 class="alert-heading"><i class="bi bi-hourglass-split me-2"></i>Hồ sơ đang chờ duyệt</h4>
        <p>Bạn đã tạo hồ sơ bác sĩ. Vui lòng chờ Admin duyệt trước khi nhận lịch khám.</p>
        <hr>
        <p class="mb-0"><strong>Bệnh viện:</strong> <?php echo htmlspecialchars($doctorProfile['hospital_name'] ?? 'Chưa rõ'); ?> - <strong>Chuyên khoa:</strong> <?php echo htmlspecialchars($doctorProfile['specialty_name'] ?? 'Chưa rõ'); ?> - <strong>Giá khám:</strong> <?php echo number_format($doctorProfile['consultation_fee'], 0, ',', '.'); ?> VNĐ</p>
    </div>
<?php else:
    $doctorId = $doctorProfile['id'];
    
    // Thống kê số lịch hẹn hôm nay
    $today = date('Y-m-d');
    $db->query("SELECT COUNT(*) as count FROM appointments a INNER JOIN schedules s ON a.schedule_id = s.id WHERE a.doctor_id = :did AND s.work_date = :today");
    $db->bind(':did', $doctorId);
    $db->bind(':today', $today);
    $appointmentsToday = $db->single()['count'];

    // Thống kê lịch hẹn chờ xác nhận (Pending)
    $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = :did AND status = 'pending'");
    $db->bind(':did', $doctorId);
    $appointmentsPending = $db->single()['count'];

    // Tổng số lịch hẹn đã hoàn thành
    $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = :did AND status = 'completed'");
    $db->bind(':did', $doctorId);
    $appointmentsCompleted = $db->single()['count'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Trang Tổng Quan Của Bác Sĩ</h2>
    <div>
        <span class="badge bg-info text-dark fs-6"><i class="bi bi-tag-fill me-1"></i> <?php echo htmlspecialchars($doctorProfile['specialty_name'] ?? 'Chưa rỗ'); ?></span>
    </div>
</div>

<div class="row mb-4">
    <!-- Block 1 -->
    <div class="col-md-4">
        <div class="card bg-primary text-white shadow-sm h-100 border-0">
            <div class="card-body d-flex align-items-center">
                <i class="bi bi-calendar-event fs-1 opacity-50 me-3"></i>
                <div>
                    <h6 class="card-title mb-1">Lịch khám hôm nay</h6>
                    <h2 class="mb-0 fw-bold"><?php echo $appointmentsToday; ?></h2>
                </div>
            </div>
        </div>
    </div>
    <!-- Block 2 -->
    <div class="col-md-4">
        <div class="card bg-warning text-dark shadow-sm h-100 border-0">
            <div class="card-body d-flex align-items-center">
                <i class="bi bi-hourglass-split fs-1 opacity-50 me-3"></i>
                <div>
                    <h6 class="card-title mb-1">Yêu cầu chờ duyệt</h6>
                    <h2 class="mb-0 fw-bold"><?php echo $appointmentsPending; ?></h2>
                </div>
            </div>
        </div>
    </div>
    <!-- Block 3 -->
    <div class="col-md-4">
        <div class="card bg-success text-white shadow-sm h-100 border-0">
            <div class="card-body d-flex align-items-center">
                <i class="bi bi-check-circle fs-1 opacity-50 me-3"></i>
                <div>
                    <h6 class="card-title mb-1">Đã hoàn thành khám</h6>
                    <h2 class="mb-0 fw-bold"><?php echo $appointmentsCompleted; ?></h2>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Danh sách cần khám hôm nay (<?php echo date('d/m/Y'); ?>)</h5>
                <a href="#" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <div class="text-center p-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                    (Chúng tôi sẽ lập trình truy xuất danh sách bệnh nhân đặt lịch ở đây trong bước sau)
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Thông tin cá nhân</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Kinh nghiệm:</span>
                        <strong><?php echo $doctorProfile['experience_years']; ?> Năm</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Phí tư vấn:</span>
                        <strong class="text-danger"><?php echo number_format($doctorProfile['consultation_fee'], 0, ',', '.'); ?> VNĐ</strong>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
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
<?php include 'includes/footer.php'; ?>
