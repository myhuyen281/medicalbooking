<?php
require_once '../../config/database.php';
include 'includes/header.php';

$db = new Database();

// Lấy tổng số bệnh nhân
$db->query("SELECT COUNT(*) as count FROM users WHERE role = 'patient'");
$patientsCount = $db->single()['count'];

$doctorScope = $isHospitalAdmin ? " WHERE hospital_id = :hospital_id" : "";
$db->query("SELECT COUNT(*) as count FROM doctors" . $doctorScope);
if ($isHospitalAdmin) {
    $db->bind(':hospital_id', $currentHospitalId);
}
$doctorsCount = $db->single()['count'];

// Lấy tổng số chuyên khoa
$db->query("SELECT COUNT(*) as count FROM specialties");
$specialtiesCount = $db->single()['count'];

$appointmentJoin = $isHospitalAdmin ? " INNER JOIN doctors d ON a.doctor_id = d.id" : "";
$appointmentWhere = $isHospitalAdmin ? " WHERE d.hospital_id = :hospital_id" : "";
$db->query("SELECT COUNT(*) as count FROM appointments a" . $appointmentJoin . $appointmentWhere);
if ($isHospitalAdmin) {
    $db->bind(':hospital_id', $currentHospitalId);
}
$appointmentsCount = $db->single()['count'];

// Lấy tổng doanh thu (chỉ tính lịch đã hoàn thành)
$revenueWhere = $isHospitalAdmin ? "WHERE a.status = 'completed' AND d.hospital_id = :hospital_id" : "WHERE a.status = 'completed'";
$db->query("SELECT SUM(d.consultation_fee) as total_revenue
            FROM appointments a
            INNER JOIN doctors d ON a.doctor_id = d.id
            $revenueWhere");
if ($isHospitalAdmin) {
    $db->bind(':hospital_id', $currentHospitalId);
}
$totalRevenue = $db->single()['total_revenue'] ?? 0;
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <div>
        <h2 class="fw-extrabold text-dark mb-1" style="font-size: 1.75rem; letter-spacing: -0.5px;">Tổng quan hệ thống</h2>
        <p class="text-secondary mb-0 small">Báo cáo trực quan và số liệu thống kê thời gian thực của hệ thống.</p>
    </div>
    <div class="d-flex align-items-center gap-2 px-3 py-2 bg-white border rounded-4 shadow-sm">
        <i class="bi bi-calendar3 text-primary fs-5"></i>
        <div>
            <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Hôm nay</div>
            <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?php echo date('d/m/Y'); ?></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Card 1: Bệnh nhân -->
    <div class="col-xl-3 col-sm-6">
        <div class="card bg-primary text-white border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-radius: 1.25rem;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-white-50 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Bệnh nhân</span>
                        <h2 class="mb-0 fw-extrabold mt-1 text-white" style="font-size: 2.25rem;"><?php echo $patientsCount; ?></h2>
                    </div>
                    <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-20 rounded-circle" style="width: 56px; height: 56px;">
                        <i class="bi bi-people text-white fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Card 2: Bác sĩ -->
    <div class="col-xl-3 col-sm-6">
        <div class="card bg-success text-white border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-radius: 1.25rem;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-white-50 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Bác sĩ</span>
                        <h2 class="mb-0 fw-extrabold mt-1 text-white" style="font-size: 2.25rem;"><?php echo $doctorsCount; ?></h2>
                    </div>
                    <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-20 rounded-circle" style="width: 56px; height: 56px;">
                        <i class="bi bi-person-badge text-white fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Card 3: Cuộc hẹn -->
    <div class="col-xl-3 col-sm-6">
        <div class="card bg-warning text-white border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-radius: 1.25rem;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-white-50 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Cuộc hẹn</span>
                        <h2 class="mb-0 fw-extrabold mt-1 text-white" style="font-size: 2.25rem;"><?php echo $appointmentsCount; ?></h2>
                    </div>
                    <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-20 rounded-circle" style="width: 56px; height: 56px;">
                        <i class="bi bi-calendar-check text-white fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Card 4: Doanh thu -->
    <div class="col-xl-3 col-sm-6">
        <div class="card bg-danger text-white border-0 shadow-sm h-100 position-relative overflow-hidden" style="border-radius: 1.25rem;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-white-50 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.05em;">Doanh thu</span>
                        <h2 class="mb-0 fw-extrabold mt-1 text-white" style="font-size: 1.85rem;"><?php echo number_format($totalRevenue, 0, ',', '.'); ?>₫</h2>
                    </div>
                    <div class="d-flex align-items-center justify-content-center bg-white bg-opacity-20 rounded-circle" style="width: 56px; height: 56px;">
                        <i class="bi bi-wallet2 text-white fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Cột bên trái: Danh sách cuộc hẹn mới nhất -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100" style="border-radius: 1.25rem;">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-4 px-4 border-0">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-list-task text-primary me-2 fs-5"></i>Lịch hẹn khám mới nhận</h5>
                <a href="<?php echo $base_url; ?>/views/admin/appointments/index.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 fs-7"><i class="bi bi-eye me-1"></i> Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4" style="background-color: #f8fafc;">Bệnh nhân</th>
                                <th style="background-color: #f8fafc;">Bác sĩ</th>
                                <th style="background-color: #f8fafc;">Thời gian khám</th>
                                <th class="pe-4 text-center" style="background-color: #f8fafc; width: 140px;">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $db->query("SELECT a.status, u_pat.full_name as patient_name, u_doc.full_name as doctor_name, s.work_date, s.start_time
                                        FROM appointments a
                                        INNER JOIN users u_pat ON a.patient_id = u_pat.id
                                        INNER JOIN doctors d ON a.doctor_id = d.id
                                        LEFT JOIN users u_doc ON d.user_id = u_doc.id
                                        INNER JOIN schedules s ON a.schedule_id = s.id
                                        " . ($isHospitalAdmin ? "WHERE d.hospital_id = :hospital_id " : "") . "
                                        ORDER BY a.created_at DESC LIMIT 5");
                            if ($isHospitalAdmin) {
                                $db->bind(':hospital_id', $currentHospitalId);
                            }
                            $recentAppts = $db->resultSet();
                            if (count($recentAppts) > 0):
                                foreach ($recentAppts as $appt):
                            ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($appt['patient_name']); ?></td>
                                    <td>
                                        <div class="fw-semibold text-secondary">BS. <?php echo htmlspecialchars($appt['doctor_name']); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo date('d/m/Y', strtotime($appt['work_date'])); ?></div>
                                        <div class="text-secondary small"><i class="bi bi-clock me-1"></i><?php echo date('H:i', strtotime($appt['start_time'])); ?></div>
                                    </td>
                                    <td class="pe-4 text-center">
                                        <?php 
                                            if($appt['status'] == 'pending') echo '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Chờ duyệt</span>';
                                            elseif($appt['status'] == 'confirmed') echo '<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Đã chốt</span>';
                                            elseif($appt['status'] == 'completed') echo '<span class="badge bg-info text-dark"><i class="bi bi-heart-pulse-fill me-1"></i>Đã khám</span>';
                                            elseif($appt['status'] == 'cancelled') echo '<span class="badge bg-danger"><i class="bi bi-x-circle-fill me-1"></i>Đã hủy</span>';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-5">Chưa có dữ liệu lịch hẹn mới nào.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Cột bên phải: Thống kê nhanh  -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100" style="border-radius: 1.25rem;">
            <div class="card-header bg-white py-4 px-4 border-0">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-pie-chart text-success me-2 fs-5"></i>Trạng thái Hệ thống</h5>
            </div>
            <div class="card-body px-4 pt-0 pb-4">
                <ul class="list-group list-group-flush border-0">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3 border-0 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 8px; height: 8px; background-color: #0284c7;"></span>
                            <span class="text-secondary fw-semibold">Số Chuyên Khoa</span>
                        </div>
                        <span class="badge bg-primary rounded-pill"><?php echo $specialtiesCount; ?></span>
                    </li>
                    <?php
                    $db->query("SELECT a.status, COUNT(*) as cnt FROM appointments a" . ($isHospitalAdmin ? " INNER JOIN doctors d ON a.doctor_id = d.id WHERE d.hospital_id = :hospital_id" : "") . " GROUP BY a.status");
                    if ($isHospitalAdmin) {
                        $db->bind(':hospital_id', $currentHospitalId);
                    }
                    $statusCounts = $db->resultSet();
                    $sMap = ['pending'=>0, 'confirmed'=>0, 'completed'=>0, 'cancelled'=>0];
                    foreach($statusCounts as $sc) { $sMap[$sc['status']] = $sc['cnt']; }
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3 border-0 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 8px; height: 8px; background-color: #f59e0b;"></span>
                            <span class="text-secondary fw-semibold">Đơn Chờ Duyệt</span>
                        </div>
                        <span class="badge bg-warning text-dark rounded-pill"><?php echo $sMap['pending']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3 border-0 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 8px; height: 8px; background-color: #10b981;"></span>
                            <span class="text-secondary fw-semibold">Đơn Đã Chốt</span>
                        </div>
                        <span class="badge bg-success rounded-pill"><?php echo $sMap['confirmed']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3 border-0">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 8px; height: 8px; background-color: #ef4444;"></span>
                            <span class="text-secondary fw-semibold">Đơn Bị Hủy</span>
                        </div>
                        <span class="badge bg-danger rounded-pill"><?php echo $sMap['cancelled']; ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
