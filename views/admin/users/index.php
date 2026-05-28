<?php
require_once '../../../config/database.php';
include '../includes/header.php';

$db = new Database();
$db->query("SELECT u.id, u.full_name, u.email, u.phone, u.role, u.hospital_approval_status, u.created_at, h.name as hospital_name FROM users u LEFT JOIN hospitals h ON u.hospital_id = h.id ORDER BY u.id DESC");
$users = $db->resultSet();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Quản lý Người dùng</h2>
    <a href="create.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Thêm người dùng</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Họ và Tên</th>
                        <th>Liên hệ (Email / SĐT)</th>
                        <th>Vai trò</th>
                        <th>Ngày tạo</th>
                        <th class="text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td>
                                    <div><i class="bi bi-envelope me-1 text-muted"></i><?php echo htmlspecialchars($user['email']); ?></div>
                                    <div><i class="bi bi-telephone me-1 text-muted"></i><?php echo htmlspecialchars($user['phone']); ?></div>
                                </td>
                                <td>
                                    <?php if($user['role'] == 'admin'): ?>
                                        <span class="badge bg-danger">Quản trị viên</span>
                                    <?php elseif($user['role'] == 'hospital'): ?>
                                        <span class="badge bg-success">Bệnh viện</span>
                                        <?php if (!empty($user['hospital_name'])): ?><div class="small text-muted"><?php echo htmlspecialchars($user['hospital_name']); ?></div><?php endif; ?>
                                        <?php if (($user['hospital_approval_status'] ?? '') === 'pending'): ?><div class="badge bg-warning text-dark">Chờ duyệt</div><?php endif; ?>
                                        <?php if (($user['hospital_approval_status'] ?? '') === 'rejected'): ?><div class="badge bg-danger">Từ chối</div><?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Bệnh nhân</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td class="text-center">
                                    <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-warning text-dark me-1" title="Sửa">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <a href="delete.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa user này? Mọi dữ liệu liên quan sẽ bị xóa.');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary disabled" title="Không thể tự xóa bản thân"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">Chưa có người dùng nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
