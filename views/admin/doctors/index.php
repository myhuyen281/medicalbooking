<?php
require_once '../../../config/database.php';
include '../includes/header.php';

$db = new Database();
// Lấy danh sách các Bác sĩ (INNER JOIN với bảng users và specialties)
$query = "SELECT d.id as doctor_id, u.full_name as user_name, u.email, u.phone, 
                 s.name as specialty_name, h.name as hospital_name, d.experience_years, d.consultation_fee, d.approval_status 
          FROM doctors d 
          INNER JOIN users u ON d.user_id = u.id 
          LEFT JOIN hospitals h ON d.hospital_id = h.id
          LEFT JOIN specialties s ON d.specialty_id = s.id
          ORDER BY d.id DESC";
$db->query($query);
$doctors = $db->resultSet();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Quản lý Hồ sơ Bác sĩ</h2>
    <a href="create.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Thêm Hồ sơ Bác sĩ</a>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <p class="text-muted"><i class="bi bi-info-circle me-1"></i> <strong>Lưu ý:</strong> Để thêm Bác sĩ mới, người đó phải có sẵn tài khoản ở phần <a href="../users/index.php">Quản lý người dùng</a> với vai trò (Role = Bác sĩ).</p>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID Bác sĩ</th>
                        <th>Họ và Tên</th>
                        <th>Liên hệ</th>
                        <th>Bệnh viện</th>
                        <th>Chuyên khoa</th>
                        <th>Kinh nghiệm (Năm)</th>
                        <th>Giá khám (VNĐ)</th>
                        <th>Trạng thái</th>
                        <th class="text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($doctors) > 0): ?>
                        <?php foreach ($doctors as $doc): ?>
                            <tr>
                                <td><?php echo $doc['doctor_id']; ?></td>
                                <td class="fw-bold">BS. <?php echo htmlspecialchars($doc['user_name']); ?></td>
                                <td>
                                    <div><i class="bi bi-envelope me-1 text-muted"></i><?php echo htmlspecialchars($doc['email']); ?></div>
                                    <div><i class="bi bi-telephone me-1 text-muted"></i><?php echo htmlspecialchars($doc['phone']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($doc['hospital_name'] ?? 'Chưa chọn'); ?></td>
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($doc['specialty_name'] ?? 'Chưa rõ'); ?></span></td>
                                <td><?php echo htmlspecialchars($doc['experience_years']); ?> năm</td>
                                <td class="text-danger fw-bold"><?php echo number_format($doc['consultation_fee'], 0, ',', '.'); ?>đ</td>
                                <td>
                                    <?php if (($doc['approval_status'] ?? 'approved') === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Chờ duyệt</span>
                                    <?php elseif (($doc['approval_status'] ?? 'approved') === 'rejected'): ?>
                                        <span class="badge bg-danger">Từ chối</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Đã duyệt</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (($doc['approval_status'] ?? 'approved') !== 'approved'): ?>
                                        <a href="approve.php?id=<?php echo $doc['doctor_id']; ?>" class="btn btn-sm btn-outline-success me-1" title="Duyệt" onclick="return confirm('Duyệt hồ sơ bác sĩ này?');">
                                            <i class="bi bi-check-lg"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="edit.php?id=<?php echo $doc['doctor_id']; ?>" class="btn btn-sm btn-outline-warning text-dark me-1" title="Sửa">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a href="delete.php?id=<?php echo $doc['doctor_id']; ?>" class="btn btn-sm btn-outline-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa hồ sơ bác sĩ này? Tài khoản User của bác sĩ vẫn sẽ tồn tại.');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-3">Chưa có hồ sơ bác sĩ nào được tạo.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
