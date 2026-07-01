<?php
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: ../../views/auth/login.php");
    exit();
}

$db = new Database();
$userId = $_SESSION['user_id'];

$db->query("SELECT full_name, email, phone FROM users WHERE id = :id");
$db->bind(':id', $userId);
$user = $db->single();

try {
    $db->query("SELECT * FROM patient_profiles WHERE user_id = :uid");
    $db->bind(':uid', $userId);
    $profile = $db->single();
} catch (Exception $e) {
    $profile = null;
}

$missingProfileFields = [];
if (empty($profile['date_of_birth'])) {
    $missingProfileFields[] = 'ngày sinh';
}
if (empty($profile['gender'])) {
    $missingProfileFields[] = 'giới tính';
}
if (empty($profile['province'])) {
    $missingProfileFields[] = 'tỉnh/thành';
}
if (empty($profile['district'])) {
    $missingProfileFields[] = 'quận/huyện';
}
if (empty($profile['ward'])) {
    $missingProfileFields[] = 'phường/xã';
}
if (empty($profile['address_detail'])) {
    $missingProfileFields[] = 'số nhà/tên đường';
}

include '../../includes/header.php';
?>

<style>
    .patient-layout { max-width: 1200px; margin: 0 auto; }
    .patient-breadcrumb { font-size: 0.9rem; font-weight: 600; padding: 16px 0; }
    .patient-breadcrumb a { color: #023f6d; text-decoration: none; }
    .patient-breadcrumb .active { color: #00a8e8; }
    .sidebar-menu { background: #fff; border: 1px solid #eef2f7; border-radius: 14px; padding: 18px 14px; box-shadow: 0 2px 10px rgba(2,63,109,0.05); }
    .sidebar-menu a { display: block; padding: 12px 16px; color: #023f6d; text-decoration: none; font-weight: 600; border-radius: 10px; margin-bottom: 6px; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #e7f8ff; color: #00a8e8; }
    .sidebar-menu .btn-add { background: linear-gradient(135deg, #16d5f7 0%, #05b7df 100%); color: #fff; border: none; border-radius: 10px; padding: 13px 16px; font-weight: 700; width: 100%; margin-bottom: 14px; }
    .patient-card { background: #fff; border: 1px solid #eef2f7; border-radius: 14px; padding: 26px; min-height: 420px; box-shadow: 0 2px 10px rgba(2,63,109,0.05); }
    .empty-state { text-align: center; padding: 50px 20px 30px; }
    .empty-state img { max-width: 320px; margin-bottom: 18px; }
    .empty-state h5 { color: #adb5bd; font-size: 1.1rem; line-height: 1.6; margin-bottom: 6px; }
    .empty-state p { color: #adb5bd; font-weight: 700; margin-bottom: 20px; }
    .record-row { border: 1px solid #eef2f7; border-radius: 12px; padding: 26px; box-shadow: 0 2px 12px rgba(2,63,109,0.05); }
    .profile-line { display:flex; align-items:center; gap:12px; margin-bottom:13px; color:#023f6d; }
    .profile-line i { color:#aeb8c2; width:18px; }
    .profile-actions { background:#f8fafc; margin:22px -26px -26px; padding:18px 22px; text-align:right; }
</style>

<div class="patient-layout">
    <nav class="patient-breadcrumb">
        <a href="<?php echo $base_url; ?>/index.php">Trang chủ</a>
        <span class="text-muted">/</span>
        <span class="active">Hồ sơ bệnh nhân</span>
    </nav>

    <div class="row pb-5">
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="sidebar-menu">
                <a href="<?php echo $base_url; ?>/views/patient/profile_create.php" class="btn-add d-flex align-items-center justify-content-center">
                    <i class="bi bi-plus-circle me-2"></i> Thêm hồ sơ
                </a>
                <a href="<?php echo $base_url; ?>/views/patient/records.php" class="active">
                    <i class="bi bi-file-medical me-2"></i> Hồ sơ bệnh nhân
                </a>
                <a href="<?php echo $base_url; ?>/views/patient/bills.php"><i class="bi bi-file-earmark-text me-2"></i> Phiếu khám bệnh</a>
                <a href="<?php echo $base_url; ?>/views/patient/notifications.php"><i class="bi bi-bell me-2"></i> Thông báo <span class="badge bg-danger ms-1">99+</span></a>
            </div>
        </div>

        <div class="col-md-9">
            <div class="patient-card">
                <h5 class="fw-bold mb-4" style="color:#023f6d;">Danh sách hồ sơ bệnh nhân</h5>

                <div class="record-row">
                    <div class="profile-line"><i class="bi bi-person-fill"></i><span>Họ và tên:</span> <strong class="text-uppercase" style="color:#d88920;" id="recordFullName"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></strong></div>
                    <div class="profile-line"><i class="bi bi-calendar3"></i><span>Ngày sinh:</span> <strong id="recordDob"><?php echo htmlspecialchars($profile['date_of_birth'] ?? 'Chưa cập nhật'); ?></strong></div>
                    <div class="profile-line"><i class="bi bi-telephone-fill"></i><span>Số điện thoại:</span> <strong id="recordPhone"><?php echo htmlspecialchars($user['phone'] ?? 'Chưa cập nhật'); ?></strong></div>
                    <div class="profile-line"><i class="bi bi-gender-ambiguous"></i><span>Giới tính:</span> <strong id="recordGender"><?php echo htmlspecialchars($profile['gender'] ?? 'Chưa cập nhật'); ?></strong></div>
                    <div class="profile-line"><i class="bi bi-people-fill"></i><span>Dân tộc:</span> <strong id="recordEthnicity"><?php echo htmlspecialchars($profile['ethnicity'] ?? 'Kinh'); ?></strong></div>
                    <div class="profile-line"><i class="bi bi-geo-alt-fill"></i><span>Địa chỉ mới:</span> <strong id="recordAddress"><?php echo htmlspecialchars($profile['address'] ?? 'Chưa cập nhật'); ?></strong></div>
                    <div class="profile-line"><i class="bi bi-geo-alt-fill"></i><span>Địa chỉ cũ:</span> <strong><?php echo htmlspecialchars($profile['old_address'] ?? ''); ?></strong></div>
                    <div class="profile-actions">
                        <a href="#" class="text-danger text-decoration-none fw-bold me-4"><i class="bi bi-trash me-1"></i>Xóa hồ sơ</a>
                        <a href="#" class="text-warning text-decoration-none fw-bold me-4" data-bs-toggle="modal" data-bs-target="#updateProfileNoticeModal"><i class="bi bi-pencil-square me-1"></i>Bổ sung hồ sơ</a>
                        <a href="<?php echo $base_url; ?>/views/patient/profile_view.php" class="text-dark text-decoration-none fw-bold"><i class="bi bi-info-circle me-1"></i>Chi tiết</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="updateProfileNoticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-2 overflow-hidden">
            <div class="modal-header text-white border-0" style="background:#08bfe8;">
                <h5 class="modal-title fw-bold">Thông báo cập nhật thông tin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-4" style="color:#374151;">
                <?php if (count($missingProfileFields) > 0): ?>
                    <?php foreach ($missingProfileFields as $field): ?>
                        <div>Vui lòng bổ sung thông tin <?php echo htmlspecialchars($field); ?>!</div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div>Hồ sơ của bạn đã được cập nhật đầy đủ.</div>
                <?php endif; ?>
            </div>
            <div class="modal-footer border-top px-4 py-3">
                <button type="button" class="btn btn-link text-dark text-decoration-none px-4" data-bs-dismiss="modal">Đóng</button>
                <a href="<?php echo $base_url; ?>/views/patient/profile.php" class="btn text-white fw-bold px-4" style="background:#08bfe8;">Bổ sung hồ sơ</a>
            </div>
        </div>
    </div>
</div>

<script>
const latestTicket = localStorage.getItem('latest_booking_ticket');
if (latestTicket) {
    try {
        const ticket = JSON.parse(latestTicket);
        if (ticket.patient_name) document.getElementById('recordFullName').textContent = ticket.patient_name;
        if (ticket.patient_dob) document.getElementById('recordDob').textContent = ticket.patient_dob;
        if (ticket.patient_phone) document.getElementById('recordPhone').textContent = ticket.patient_phone;
    } catch (error) {}
}
</script>

<?php include '../../includes/footer.php'; ?>
