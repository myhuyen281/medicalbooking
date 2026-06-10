<?php
require_once '../../../config/database.php';
include '../includes/header.php';

if (!$isSystemAdmin) {
    echo "<div class='alert alert-danger'>Bạn không có quyền truy cập trang này.</div>";
    include '../includes/footer.php';
    exit();
}

$db = new Database();
$error = '';
$success = '';
$facilityTypeLabels = [
    'public' => 'Bệnh viện công',
    'private' => 'Bệnh viện tư',
    'clinic' => 'Phòng khám',
    'office' => 'Phòng mạch',
    'lab' => 'Xét nghiệm',
    'home' => 'Y tế tại nhà',
    'vaccination' => 'Tiêm chủng'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $hospitalId = (int)($_POST['hospital_id'] ?? 0);
        $db->query('SELECT id FROM users WHERE hospital_id = :hospital_id AND role = \'hospital\' AND hospital_approval_status = \'approved\' LIMIT 1');
        $db->bind(':hospital_id', $hospitalId);
        $hospitalUser = $db->single();

        if (!$hospitalUser) {
            $error = 'Chỉ được xóa bệnh viện đã duyệt.';
        } else {
            $db->query('DELETE FROM users WHERE hospital_id = :hospital_id AND role = \'hospital\'');
            $db->bind(':hospital_id', $hospitalId);
            $db->execute();
            $db->query('DELETE FROM hospitals WHERE id = :id');
            $db->bind(':id', $hospitalId);
            $success = $db->execute() ? 'Đã xóa bệnh viện.' : 'Không thể xóa bệnh viện.';
        }
    } elseif ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $facilityType = $_POST['facility_type'] ?? 'public';
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $logoUrl = trim($_POST['logo_url'] ?? '');
        $posterUrl = trim($_POST['poster_url'] ?? '');
        $workingTime = trim($_POST['working_time'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $error = 'Vui lòng nhập tên bệnh viện.';
        } else {
            $db->query('INSERT INTO hospitals (name, facility_type, address, phone, email, logo_url, poster_url, working_time, description) VALUES (:name, :facility_type, :address, :phone, :email, :logo_url, :poster_url, :working_time, :description)');
            $db->bind(':name', $name);
            $db->bind(':facility_type', $facilityType);
            $db->bind(':address', $address);
            $db->bind(':phone', $phone);
            $db->bind(':email', $email);
            $db->bind(':logo_url', $logoUrl);
            $db->bind(':poster_url', $posterUrl);
            $db->bind(':working_time', $workingTime);
            $db->bind(':description', $description);
            if ($db->execute()) {
                $hospitalId = $db->lastInsertId();
                $accountEmail = $email !== '' ? $email : 'hospital_' . $hospitalId . '@medicalbooking.local';
                $accountPhone = $phone !== '' ? $phone : 'hospital_' . $hospitalId;
                $password = password_hash('123456', PASSWORD_DEFAULT);
                $db->query('INSERT INTO users (full_name, email, phone, password, role, hospital_id, hospital_approval_status, address) VALUES (:full_name, :email, :phone, :password, \'hospital\', :hospital_id, \'approved\', :address)');
                $db->bind(':full_name', $name);
                $db->bind(':email', $accountEmail);
                $db->bind(':phone', $accountPhone);
                $db->bind(':password', $password);
                $db->bind(':hospital_id', $hospitalId);
                $db->bind(':address', $address);
                $success = $db->execute() ? 'Đã thêm bệnh viện đã duyệt.' : 'Đã thêm bệnh viện nhưng không tạo được tài khoản.';
            } else {
                $error = 'Không thể thêm bệnh viện.';
            }
        }
    }
}

$db->query("SELECT h.*, u.id AS user_id, u.email AS account_email, u.phone AS account_phone, u.created_at AS approved_at
            FROM hospitals h
            INNER JOIN users u ON u.hospital_id = h.id AND u.role = 'hospital' AND u.hospital_approval_status = 'approved'
            ORDER BY h.id DESC");
$hospitals = $db->resultSet();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Quản lý Bệnh viện</h2>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h5 class="fw-bold mb-3">Thêm bệnh viện mới</h5>
        <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="create">
            <div class="col-md-4">
                <label class="form-label fw-bold">Tên bệnh viện</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Loại cơ sở</label>
                <select name="facility_type" class="form-select">
                    <?php foreach ($facilityTypeLabels as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-bold">Địa chỉ</label>
                <input type="text" name="address" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Điện thoại</label>
                <input type="text" name="phone" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Logo URL</label>
                <input type="text" name="logo_url" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Poster URL</label>
                <input type="text" name="poster_url" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Thời gian làm việc</label>
                <input type="text" name="working_time" class="form-control">
            </div>
            <div class="col-md-8">
                <label class="form-label fw-bold">Mô tả</label>
                <input type="text" name="description" class="form-control">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Thêm bệnh viện</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Bệnh viện</th>
                        <th>Liên hệ</th>
                        <th>Loại cơ sở</th>
                        <th>Ảnh</th>
                        <th class="text-center pe-3">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($hospitals) > 0): ?>
                        <?php foreach ($hospitals as $hospital): ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold"><?php echo htmlspecialchars($hospital['name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($hospital['address'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($hospital['email'] ?: $hospital['account_email']); ?></div>
                                    <small><?php echo htmlspecialchars($hospital['phone'] ?: $hospital['account_phone']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($facilityTypeLabels[$hospital['facility_type'] ?? ''] ?? 'Chưa phân loại'); ?></td>
                                <td>
                                    <?php if (!empty($hospital['logo_url'])): ?><span class="badge bg-info me-1">Logo</span><?php endif; ?>
                                    <?php if (!empty($hospital['poster_url'])): ?><span class="badge bg-primary">Poster</span><?php endif; ?>
                                </td>
                                <td class="text-center pe-3">
                                    <a href="../../../facility_detail.php?id=<?php echo (int)$hospital['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">Xem</a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa bệnh viện này?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="hospital_id" value="<?php echo (int)$hospital['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Chưa có bệnh viện đã duyệt.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
