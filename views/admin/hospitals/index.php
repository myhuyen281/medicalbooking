<?php
require_once '../../../config/database.php';
include '../includes/header.php';

if (!$isSystemAdmin) {
    echo "<div class='alert alert-danger'>Bạn không có quyền truy cập trang này.</div>";
    include '../includes/footer.php';
    exit();
}

$db = new Database();
try {
    $db->query("ALTER TABLE hospitals ADD COLUMN facility_type VARCHAR(30) NOT NULL DEFAULT 'public' AFTER facility_code");
    $db->execute();
} catch (Exception $e) {
}
$facilityTypeLabels = [
    'public' => 'Bệnh viện công',
    'private' => 'Bệnh viện tư',
    'clinic' => 'Phòng khám',
    'office' => 'Phòng mạch',
    'lab' => 'Xét nghiệm',
    'home' => 'Y tế tại nhà',
    'vaccination' => 'Tiêm chủng'
];

if (isset($_GET['action'], $_GET['id']) && in_array($_GET['action'], ['approved', 'rejected'])) {
    $db->query("UPDATE users SET hospital_approval_status = :status WHERE id = :id AND role = 'hospital'");
    $db->bind(':status', $_GET['action']);
    $db->bind(':id', $_GET['id']);
    $db->execute();
    $msg = $_GET['action'] === 'approved' ? 'Đã duyệt tài khoản bệnh viện.' : 'Đã từ chối tài khoản bệnh viện.';
}

$db->query("SELECT u.id, u.full_name, u.email, u.phone, u.hospital_approval_status, u.created_at, h.name as hospital_name, h.facility_type, h.address, h.subscription_plan, h.subscription_status, h.subscription_expires_at
            FROM users u
            LEFT JOIN hospitals h ON u.hospital_id = h.id
            WHERE u.role = 'hospital'
            ORDER BY FIELD(u.hospital_approval_status, 'pending', 'approved', 'rejected'), u.created_at DESC");
$hospitals = $db->resultSet();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Duyệt tài khoản Bệnh viện</h2>
</div>

<?php if (isset($msg)): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Bệnh viện</th>
                        <th>Liên hệ</th>
                        <th>Loại cơ sở</th>
                        <th>Gói dịch vụ</th>
                        <th>Trạng thái</th>
                        <th>Ngày đăng ký</th>
                        <th class="text-center pe-3">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($hospitals) > 0): ?>
                        <?php foreach ($hospitals as $hospital): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold"><?php echo htmlspecialchars($hospital['hospital_name'] ?? $hospital['full_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($hospital['address'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($hospital['email']); ?></div>
                                    <small><?php echo htmlspecialchars($hospital['phone']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($facilityTypeLabels[$hospital['facility_type'] ?? ''] ?? 'Chưa phân loại'); ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($hospital['subscription_plan'] ?? 'basic'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($hospital['subscription_status'] ?? 'pending_payment'); ?><?php echo !empty($hospital['subscription_expires_at']) ? ' - hết hạn ' . date('d/m/Y', strtotime($hospital['subscription_expires_at'])) : ''; ?></small>
                                </td>
                                <td>
                                    <?php if ($hospital['hospital_approval_status'] === 'approved'): ?>
                                        <span class="badge bg-success">Đã duyệt</span>
                                    <?php elseif ($hospital['hospital_approval_status'] === 'rejected'): ?>
                                        <span class="badge bg-danger">Từ chối</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Chờ duyệt</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($hospital['created_at'])); ?></td>
                                <td class="text-center pe-3">
                                    <?php if ($hospital['hospital_approval_status'] !== 'approved'): ?>
                                        <a href="?action=approved&id=<?php echo $hospital['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Duyệt tài khoản bệnh viện này?');">Duyệt</a>
                                    <?php endif; ?>
                                    <?php if ($hospital['hospital_approval_status'] !== 'rejected'): ?>
                                        <a href="?action=rejected&id=<?php echo $hospital['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Từ chối tài khoản bệnh viện này?');">Từ chối</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Chưa có tài khoản bệnh viện nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
