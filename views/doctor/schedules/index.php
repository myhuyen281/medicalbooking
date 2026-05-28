<?php
require_once '../../../config/database.php';
include '../includes/header.php';

$db = new Database();

// Lấy id bác sĩ dựa trên user đang đăng nhập
$db->query("SELECT id FROM doctors WHERE user_id = :uid AND approval_status = 'approved'");
$db->bind(':uid', $_SESSION['user_id']);
$doctor = $db->single();

if (!$doctor) {
    echo "<div class='alert alert-danger mt-4'>Bạn chưa có hồ sơ Bác sĩ. Vui lòng liên hệ Admin.</div>";
    include '../includes/footer.php';
    exit();
}

$doctorId = $doctor['id'];

// Lấy danh sách lịch làm việc của bác sĩ này
$db->query("SELECT * FROM schedules WHERE doctor_id = :did ORDER BY work_date DESC, start_time ASC");
$db->bind(':did', $doctorId);
$schedules = $db->resultSet();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Quản lý Lịch làm việc</h2>
    <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Thêm Khung Giờ Mới</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Ngày làm việc</th>
                        <th>Giờ bắt đầu</th>
                        <th>Giờ kết thúc</th>
                        <th>Trạng thái</th>
                        <th class="text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($schedules) > 0): ?>
                        <?php foreach ($schedules as $sched): ?>
                            <tr>
                                <td class="fw-bold"><?php echo date('d/m/Y', strtotime($sched['work_date'])); ?></td>
                                <td><span class="badge bg-secondary"><i class="bi bi-clock me-1"></i><?php echo date('H:i', strtotime($sched['start_time'])); ?></span></td>
                                <td><span class="badge bg-secondary"><i class="bi bi-clock me-1"></i><?php echo date('H:i', strtotime($sched['end_time'])); ?></span></td>
                                <td>
                                    <?php if ($sched['status'] == 'available'): ?>
                                        <span class="badge bg-success">Còn trống</span>
                                    <?php elseif ($sched['status'] == 'booked'): ?>
                                        <span class="badge bg-danger">Đã có người đặt</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Đã hủy</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($sched['status'] == 'available'): ?>
                                        <a href="delete.php?id=<?php echo $sched['id']; ?>" class="btn btn-sm btn-outline-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa không?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary disabled" title="Không thể xóa lịch đã đặt"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">Bạn chưa cấu hình ngày làm việc nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
