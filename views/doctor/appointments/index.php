<?php
require_once '../../../config/database.php';
include '../includes/header.php';

$db = new Database();

// Retrieve doctor ID
$db->query("SELECT id FROM doctors WHERE user_id = :uid AND approval_status = 'approved'");
$db->bind(':uid', $_SESSION['user_id']);
$doctor = $db->single();

if (!$doctor) {
    echo "<div class='alert alert-danger mt-4'>Tài khoản của bạn chưa được liên kết với hồ sơ Bác sĩ.</div>";
    include '../includes/footer.php';
    exit();
}

$doctorId = $doctor['id'];

// Handling appointment status update (Approve, Complete, Cancel)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['appointment_id'])) {
    $apptId = $_POST['appointment_id'];
    $action = $_POST['action'];
    $newStatus = '';

    if ($action === 'confirm') $newStatus = 'confirmed';
    elseif ($action === 'complete') $newStatus = 'completed';
    elseif ($action === 'cancel') $newStatus = 'cancelled';

    if ($newStatus) {
        $db->query("UPDATE appointments SET status = :status WHERE id = :id AND doctor_id = :did");
        $db->bind(':status', $newStatus);
        $db->bind(':id', $apptId);
        $db->bind(':did', $doctorId);
        $db->execute();

        // If cancelled, we should ideally free up the schedule again
        if ($newStatus === 'cancelled') {
            $db->query("UPDATE schedules s 
                        INNER JOIN appointments a ON s.id = a.schedule_id 
                        SET s.status = 'available' 
                        WHERE a.id = :aid");
            $db->bind(':aid', $apptId);
            $db->execute();
        }
        
        $success = "Đã cập nhật trạng thái đơn khám thành công.";
    }
}

// Fetch all appointments for this doctor (Join with patient info & schedules)
$db->query("SELECT a.id, a.status, a.symptoms, a.created_at, 
                   u.full_name as patient_name, u.phone as patient_phone, u.date_of_birth,
                   s.work_date, s.start_time, s.end_time 
            FROM appointments a 
            INNER JOIN users u ON a.patient_id = u.id 
            INNER JOIN schedules s ON a.schedule_id = s.id 
            WHERE a.doctor_id = :did 
            ORDER BY s.work_date ASC, s.start_time ASC");
$db->bind(':did', $doctorId);
$appointments = $db->resultSet();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Quản lý Cuộc hẹn Khám bệnh</h2>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <!-- Tabs for status filtering -->
        <ul class="nav nav-tabs mb-4" id="appointmentTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold text-warning" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">Chờ Xác Nhận</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-success" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab">Sắp Khám (Đã duyệt)</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-secondary" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">Lịch sử (Hoàn thành/Hủy)</button>
            </li>
        </ul>

        <div class="tab-content" id="appointmentTabsContent">
            
            <!-- PENDING TAB -->
            <div class="tab-pane fade show active" id="pending" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Mã Lịch</th>
                                <th>Thời gian khám</th>
                                <th>Bệnh nhân</th>
                                <th>Triệu chứng</th>
                                <th class="text-center">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $hasPending = false;
                            foreach ($appointments as $appt): 
                                if($appt['status'] === 'pending'): 
                                    $hasPending = true;
                            ?>
                                <tr>
                                    <td>#<?php echo $appt['id']; ?></td>
                                    <td>
                                        <div class="fw-bold text-primary"><?php echo date('d/m/Y', strtotime($appt['work_date'])); ?></div>
                                        <div class="small"><i class="bi bi-clock"></i> <?php echo date('H:i', strtotime($appt['start_time'])); ?> - <?php echo date('H:i', strtotime($appt['end_time'])); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($appt['patient_name']); ?></div>
                                        <div class="small text-muted"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($appt['patient_phone']); ?></div>
                                    </td>
                                    <td><button class="btn btn-sm btn-light" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($appt['symptoms']); ?>"><?php echo mb_strimwidth($appt['symptoms'], 0, 30, "..."); ?></button></td>
                                    <td class="text-center">
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                            <button type="submit" name="action" value="confirm" class="btn btn-sm btn-success me-1" title="Chấp nhận đơn"><i class="bi bi-check-circle"></i> Duyệt</button>
                                            <button type="submit" name="action" value="cancel" class="btn btn-sm btn-danger" title="Từ chối/Hủy" onclick="return confirm('Xác nhận từ chối cuộc khám này?');"><i class="bi bi-x-circle"></i> Từ chối</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php 
                                endif; 
                            endforeach; 
                            if (!$hasPending):
                            ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Không có yêu cầu đặt lịch nào đang chờ xác nhận.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- UPCOMING TAB (Confirmed) -->
            <div class="tab-pane fade" id="upcoming" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Thời gian khám</th>
                                <th>Bệnh nhân</th>
                                <th>Triệu chứng (Notes)</th>
                                <th>Trạng thái</th>
                                <th class="text-center">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $hasUpcoming = false;
                            foreach ($appointments as $appt): 
                                if($appt['status'] === 'confirmed'): 
                                    $hasUpcoming = true;
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-success"><?php echo date('d/m/Y', strtotime($appt['work_date'])); ?></div>
                                        <div><span class="badge bg-light text-dark border"><i class="bi bi-clock text-primary"></i> <?php echo date('H:i', strtotime($appt['start_time'])); ?></span></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($appt['patient_name']); ?></div>
                                        <div class="small"><a href="tel:<?php echo $appt['patient_phone']; ?>"><?php echo htmlspecialchars($appt['patient_phone']); ?></a></div>
                                    </td>
                                    <td><?php echo nl2br(htmlspecialchars($appt['symptoms'])); ?></td>
                                    <td><span class="badge bg-success">Đã duyệt</span></td>
                                    <td class="text-center">
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                            <button type="submit" name="action" value="complete" class="btn btn-sm btn-primary mb-1 w-100" onclick="return confirm('Cuộc khám này đã diễn ra xong?');"><i class="bi bi-check2-all"></i> Đã khám xong</button>
                                            <button type="submit" name="action" value="cancel" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Bạn bận đột xuất? Xác nhận hủy lịch khám này? Bệnh nhân sẽ được thông báo.');"><i class="bi bi-calendar-x"></i> Hủy lịch</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php 
                                endif; 
                            endforeach; 
                            if (!$hasUpcoming):
                            ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Chưa có lịch khám nào sắp diễn ra.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- HISTORY TAB (Completed or Cancelled) -->
            <div class="tab-pane fade" id="history" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Ngày khám</th>
                                <th>Bệnh nhân</th>
                                <th>Giờ khám</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $hasHistory = false;
                            foreach ($appointments as $appt): 
                                if($appt['status'] === 'completed' || $appt['status'] === 'cancelled'): 
                                    $hasHistory = true;
                            ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($appt['work_date'])); ?></td>
                                    <td class="fw-bold text-secondary"><?php echo htmlspecialchars($appt['patient_name']); ?></td>
                                    <td><?php echo date('H:i', strtotime($appt['start_time'])); ?> - <?php echo date('H:i', strtotime($appt['end_time'])); ?></td>
                                    <td>
                                        <?php if ($appt['status'] === 'completed'): ?>
                                            <span class="badge bg-info text-dark">Đã hoàn thành</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Khách hủy / BS Hủy</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php 
                                endif; 
                            endforeach; 
                            if (!$hasHistory):
                            ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">Lịch sử trống.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div> <!-- End Tab Content -->
    </div>
</div>

<script>
// Enable Tooltips
document.addEventListener("DOMContentLoaded", function(){
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../includes/footer.php'; ?>
