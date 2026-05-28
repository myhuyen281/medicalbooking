<?php
require_once '../../../config/database.php';
include '../includes/header.php';

$db = new Database();
$db->query("SELECT * FROM specialties ORDER BY id DESC");
$specialties = $db->resultSet();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Quản lý Chuyên khoa</h2>
    <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Thêm mới</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Tên chuyên khoa</th>
                        <th>Mô tả</th>
                        <th class="text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($specialties) > 0): ?>
                        <?php foreach ($specialties as $spec): ?>
                            <tr>
                                <td><?php echo $spec['id']; ?></td>
                                <td class="fw-bold text-primary"><?php echo htmlspecialchars($spec['name']); ?></td>
                                <td><?php echo htmlspecialchars(mb_strimwidth($spec['description'], 0, 50, '...')); ?></td>
                                <td class="text-center">
                                    <a href="edit.php?id=<?php echo $spec['id']; ?>" class="btn btn-sm btn-outline-warning text-dark me-1" title="Sửa">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a href="delete.php?id=<?php echo $spec['id']; ?>" class="btn btn-sm btn-outline-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa chuyên khoa này? Lịch khám liên quan có thể bị ảnh hưởng.');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3">Chưa có chuyên khoa nào trên hệ thống.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
