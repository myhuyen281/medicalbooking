<?php
require_once '../../../config/database.php';
include '../includes/header.php';

$db = new Database();

// Xử lý Xóa/Hủy Đơn Khám (Chỉ dành cho Admin có quyền force xoá hoặc force huỷ)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $apptId = $_GET['id'];
    $scopeSql = $isHospitalAdmin ? " AND EXISTS (SELECT 1 FROM doctors d WHERE d.id = appointments.doctor_id AND d.hospital_id = :hospital_id)" : "";
    if ($_GET['action'] === 'cancel') {
        $db->query("UPDATE appointments SET status = 'cancelled' WHERE id = :id" . $scopeSql);
        $db->bind(':id', $apptId);
        if ($isHospitalAdmin) {
            $db->bind(':hospital_id', $currentHospitalId);
        }
        $db->execute();
        
        // Giải phóng lịch
        $db->query("UPDATE schedules s 
                    INNER JOIN appointments a ON s.id = a.schedule_id 
                    SET s.status = 'available' 
                    WHERE a.id = :aid");
        $db->bind(':aid', $apptId);
        $db->execute();

        $msg = "Đã hủy đơn khám số #" . $apptId;
    } elseif ($_GET['action'] === 'delete' && $isSystemAdmin) {
        $db->query("DELETE FROM appointments WHERE id = :id");
        $db->bind(':id', $apptId);
        $db->execute();
        $msg = "Đã xóa vĩnh viễn đơn khám số #" . $apptId;
    }
}

$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$whereParts = [];
if ($filter_status !== '') {
    $whereParts[] = "a.status = :status";
}
if ($isHospitalAdmin) {
    $whereParts[] = "d.hospital_id = :hospital_id";
}
$whereClause = count($whereParts) ? "WHERE " . implode(" AND ", $whereParts) : "";

$query = "SELECT a.id, a.status, a.symptoms, a.created_at,
                 u_pat.full_name as patient_name, u_pat.phone as patient_phone,
                 u_doc.full_name as doctor_name, spec.name as specialty_name, h.name as hospital_name,
                  s.work_date, s.start_time, s.end_time,
                 d.consultation_fee
          FROM appointments a
          INNER JOIN users u_pat ON a.patient_id = u_pat.id
          INNER JOIN doctors d ON a.doctor_id = d.id
          LEFT JOIN users u_doc ON d.user_id = u_doc.id
          INNER JOIN specialties spec ON d.specialty_id = spec.id
          LEFT JOIN hospitals h ON d.hospital_id = h.id
          INNER JOIN schedules s ON a.schedule_id = s.id
          $whereClause
          ORDER BY a.created_at DESC";

$db->query($query);
if ($filter_status !== '') {
    $db->bind(':status', $filter_status);
}
if ($isHospitalAdmin) {
    $db->bind(':hospital_id', $currentHospitalId);
}
$appointments = $db->resultSet();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Quản lý toàn bộ Đơn Đặt Khám</h2>
</div>

<?php if(isset($msg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> <?php echo $msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <label class="fw-bold">Lọc theo trạng thái:</label>
            <select name="status" class="form-select w-auto" onchange="this.form.submit()">
                <option value="">Tất cả</option>
                <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Chờ xác nhận</option>
                <option value="confirmed" <?php echo $filter_status == 'confirmed' ? 'selected' : ''; ?>>Đã xác nhận</option>
                <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Đã hoàn thành</option>
                <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
            </select>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Mã</th>
                        <th>Thời gian khám</th>
                        <th>Bệnh nhân</th>
                        <th>Bác sĩ & Chuyên khoa</th>
                        <th>Tiền khám</th>
                        <th>Trạng thái</th>
                        <th class="text-center pe-3">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($appointments) > 0): ?>
                        <?php foreach($appointments as $appt): ?>
                            <tr>
                                <td class="ps-3 fw-bold">#<?php echo $appt['id']; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo date('d/m/Y', strtotime($appt['work_date'])); ?></div>
                                    <small class="text-muted"><i class="bi bi-clock"></i> <?php echo date('H:i', strtotime($appt['start_time'])); ?> - <?php echo date('H:i', strtotime($appt['end_time'])); ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($appt['patient_name']); ?></div>
                                    <small><?php echo htmlspecialchars($appt['patient_phone']); ?></small>
                                </td>
                                <td>
                                    <div class="text-primary fw-bold">BS. <?php echo htmlspecialchars($appt['doctor_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($appt['specialty_name']); ?></small>
                                </td>
                                <td class="fw-bold text-danger">
                                    <?php echo number_format($appt['consultation_fee'], 0, ',', '.'); ?>₫
                                </td>
                                <td>
                                    <?php 
                                        if($appt['status'] == 'pending') echo '<span class="badge bg-warning text-dark">Chờ duyệt</span>';
                                        elseif($appt['status'] == 'confirmed') echo '<span class="badge bg-success">Đã duyệt</span>';
                                        elseif($appt['status'] == 'completed') echo '<span class="badge bg-info text-dark">Hoàn thành</span>';
                                        elseif($appt['status'] == 'cancelled') echo '<span class="badge bg-danger">Đã hủy</span>';
                                    ?>
                                    <br><small class="text-muted" style="font-size: 10px;">Đặt lúc: <?php echo date('d/m H:i', strtotime($appt['created_at'])); ?></small>
                                </td>
                                <td class="text-center pe-3">
                                    <!-- Options for Admin: For admin we just allow force cancel and delete, not confirm -->
                                    <div class="btn-group">
                                        <?php if($appt['status'] == 'pending' || $appt['status'] == 'confirmed'): ?>
                                            <a href="?action=cancel&id=<?php echo $appt['id']; ?>" class="btn btn-sm btn-outline-warning" onclick="return confirm('Bạn có chắc muốn hủy đơn này?');" title="Hủy">
                                                <i class="bi bi-x-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($isSystemAdmin): ?>
                                            <a href="?action=delete&id=<?php echo $appt['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Cảnh báo: Hành động này sẽ xóa vĩnh viễn đơn khám khỏi DB. Bạn chắc chắn chứ?');" title="Xóa">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4">Chưa có đơn khám nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>