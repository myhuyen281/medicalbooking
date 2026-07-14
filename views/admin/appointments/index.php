<?php
require_once '../../../config/database.php';
require_once '../../../controllers/RefundController.php';
require_once '../../../controllers/MedicalResultController.php';
include '../includes/header.php';

if (!$isHospitalAdmin) {
    header("Location: $base_url/views/admin/dashboard.php");
    exit();
}

$db = new Database();
$db->query("UPDATE appointments a INNER JOIN doctors d ON a.doctor_id = d.id SET a.status = 'confirmed' WHERE a.status = 'pending' AND d.hospital_id = :hospital_id");
$db->bind(':hospital_id', $currentHospitalId);
$db->execute();

$medicalResultController = new MedicalResultController();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_medical_result') {
    $result = $medicalResultController->completeExam($_POST, $_FILES, $currentHospitalId);
    $msg = $result['message'];
}

// Xử lý Xóa/Hủy Đơn Khám (Chỉ dành cho Admin có quyền force xoá hoặc force huỷ)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $apptId = $_GET['id'];
    $scopeSql = $isHospitalAdmin ? " AND EXISTS (SELECT 1 FROM doctors d WHERE d.id = appointments.doctor_id AND d.hospital_id = :hospital_id)" : "";
 if ($_GET['action'] === 'cancel') {
 $refundController = new RefundController();
 $cancelResult = $refundController->cancelAppointment($apptId, 'hospital', 'Bệnh viện hủy lịch khám', null, $isHospitalAdmin ? $currentHospitalId : null);
 $msg = $cancelResult['message'];
 } elseif ($_GET['action'] === 'start_exam') {
  $result = $medicalResultController->startExam($apptId, $currentHospitalId);
  $msg = $result['message'];
  } elseif ($_GET['action'] === 'confirm') {
 $db->query("UPDATE appointments SET status = 'confirmed' WHERE id = :id" . $scopeSql);
 $db->bind(':id', $apptId);
 if ($isHospitalAdmin) {
 $db->bind(':hospital_id', $currentHospitalId);
 }
 $db->execute();
 $msg = "Đã duyệt đơn khám số #" . $apptId;
 } elseif ($_GET['action'] === 'request_cancel') {
  $db->query("UPDATE appointments SET status = 'cancel_pending' WHERE id = :id" . $scopeSql);
  $db->bind(':id', $apptId);
  if ($isHospitalAdmin) {
  $db->bind(':hospital_id', $currentHospitalId);
  }
  $db->execute();
  $msg = "Đã chuyển đơn #" . $apptId . " sang trạng thái chờ hoàn tiền";
  } elseif ($_GET['action'] === 'complete') {
  $db->query("UPDATE appointments SET status = 'completed' WHERE id = :id" . $scopeSql);
 $db->bind(':id', $apptId);
 if ($isHospitalAdmin) {
 $db->bind(':hospital_id', $currentHospitalId);
 }
 $db->execute();
  $msg = "Đã đánh dấu hoàn thành đơn #" . $apptId;
  } elseif ($_GET['action'] === 'approve_cancel') {
   $refundController = new RefundController();
   $cancelResult = $refundController->cancelAppointment($apptId, 'hospital', 'Bệnh viện xác nhận hủy lịch khám', null, $isHospitalAdmin ? $currentHospitalId : null);
   $msg = $cancelResult['message'];
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
                 d.consultation_fee, mr.diagnosis, mr.conclusion, mr.prescription,
                 mr.note AS result_note, mr.re_exam_date
 FROM appointments a
 INNER JOIN users u_pat ON a.patient_id = u_pat.id
 INNER JOIN doctors d ON a.doctor_id = d.id
 LEFT JOIN users u_doc ON d.user_id = u_doc.id
 LEFT JOIN specialties spec ON d.specialty_id = spec.id
 LEFT JOIN hospitals h ON d.hospital_id = h.id
 INNER JOIN schedules s ON a.schedule_id = s.id
 LEFT JOIN medical_results mr ON mr.appointment_id = a.id
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
<link rel="stylesheet" href="<?php echo $base_url; ?>/public/css/medical-results.css">

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
                <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Đặt khám thành công</option>
                <option value="confirmed" <?php echo $filter_status == 'confirmed' ? 'selected' : ''; ?>>Đã xác nhận</option>
                <option value="examining" <?php echo $filter_status == 'examining' ? 'selected' : ''; ?>>Đang khám</option>
                <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Hoàn thành</option>
                <option value="cancel_pending" <?php echo $filter_status == 'cancel_pending' ? 'selected' : ''; ?>>Chờ hoàn tiền</option>
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
                                    <div class="text-primary fw-bold"><?php echo htmlspecialchars(!empty($appt['doctor_name']) ? 'BS. ' . $appt['doctor_name'] : ($appt['hospital_name'] ?? 'Cơ sở y tế')); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($appt['specialty_name'] ?? 'Dịch vụ khám'); ?></small>
                                </td>
                                <td class="fw-bold text-danger">
                                    <?php echo number_format($appt['consultation_fee'], 0, ',', '.'); ?>₫
                                </td>
                                <td>
                                    <?php 
                                        if($appt['status'] == 'pending') echo '<span class="badge bg-success">Đặt khám thành công</span>';
                                        elseif($appt['status'] == 'confirmed') echo '<span class="badge bg-success">Đã xác nhận</span>';
                                        elseif($appt['status'] == 'examining') echo '<span class="badge bg-primary">Đang khám</span>';
                                        elseif($appt['status'] == 'completed') echo '<span class="badge bg-info text-dark">Hoàn thành</span>';
                                         elseif($appt['status'] == 'cancel_pending') echo '<span class="badge bg-warning text-dark">Chờ hoàn tiền</span>';
                                         elseif($appt['status'] == 'cancelled') echo '<span class="badge bg-danger">Đã hủy</span>';
                                    ?>
                                    <br><small class="text-muted" style="font-size: 10px;">Đặt lúc: <?php echo date('d/m H:i', strtotime($appt['created_at'])); ?></small>
                                </td>
                                <td class="text-center pe-3">
                                    <!-- Options for Admin: For admin we just allow force cancel and delete, not confirm -->
                                    <div class="btn-group">
  <?php if($appt['status'] == 'confirmed'): ?>
  <a href="?action=start_exam&id=<?php echo $appt['id']; ?>" class="btn btn-sm btn-outline-primary" onclick="return confirm('Chuyển lịch này sang trạng thái Đang khám?');" title="Đang khám">
  <i class="bi bi-play-circle"></i>
  </a>
  <?php endif; ?>
  <?php if($appt['status'] == 'examining' || $appt['status'] == 'completed'): ?>
  <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#medicalResultModal" data-result-appointment="<?php echo (int)$appt['id']; ?>" data-result-patient="<?php echo htmlspecialchars($appt['patient_name'], ENT_QUOTES); ?>" data-result-diagnosis="<?php echo htmlspecialchars($appt['diagnosis'] ?? '', ENT_QUOTES); ?>" data-result-conclusion="<?php echo htmlspecialchars($appt['conclusion'] ?? '', ENT_QUOTES); ?>" data-result-prescription="<?php echo htmlspecialchars($appt['prescription'] ?? '', ENT_QUOTES); ?>" data-result-note="<?php echo htmlspecialchars($appt['result_note'] ?? '', ENT_QUOTES); ?>" data-result-re-exam-date="<?php echo htmlspecialchars($appt['re_exam_date'] ?? '', ENT_QUOTES); ?>" data-result-mode="<?php echo $appt['status'] == 'completed' ? 'edit' : 'create'; ?>" title="<?php echo $appt['status'] == 'completed' ? 'Sửa kết quả khám' : 'Hoàn thành khám'; ?>">
  <i class="bi bi-<?php echo $appt['status'] == 'completed' ? 'pencil-square' : 'clipboard-check'; ?>"></i> <?php echo $appt['status'] == 'completed' ? 'Sửa kết quả' : 'Hoàn thành khám'; ?>
  </button>
  <?php endif; ?>
  <?php if($appt['status'] == 'cancel_pending'): ?>
  <a href="?action=approve_cancel&id=<?php echo $appt['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xác nhận hủy đơn và hoàn tiền cho bệnh nhân?');" title="Xác nhận hủy & hoàn tiền">
  <i class="bi bi-cash-coin"></i>
  </a>
  <?php endif; ?>
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

<div class="modal fade medical-result-modal" id="medicalResultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header bg-info text-white rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-clipboard2-pulse me-2"></i><span id="medicalResultModalTitle">Nhập kết quả khám</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="medical-result-form" novalidate>
                <input type="hidden" name="action" value="save_medical_result">
                <input type="hidden" name="appointment_id" id="resultAppointmentId">
                <div class="modal-body p-4">
                    <div class="alert alert-light border"><strong>Bệnh nhân:</strong> <span id="resultPatientName"></span></div>
                    <div class="mb-3">
                        <label class="form-label">Chẩn đoán</label>
                        <textarea name="diagnosis" class="form-control" rows="3" required></textarea>
                        <div class="invalid-feedback">Vui lòng nhập chẩn đoán.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kết luận</label>
                        <textarea name="conclusion" class="form-control" rows="3" required></textarea>
                        <div class="invalid-feedback">Vui lòng nhập kết luận.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Đơn thuốc</label>
                        <textarea name="prescription" class="form-control" rows="4" required></textarea>
                        <div class="invalid-feedback">Vui lòng nhập đơn thuốc.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ghi chú</label>
                        <textarea name="note" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Ngày tái khám</label>
                            <input type="date" name="re_exam_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">File PDF kết quả</label>
                            <input type="file" name="pdf_file" class="form-control" accept="application/pdf">
                            <div class="form-text">Không bắt buộc, tối đa 5MB.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-info text-white fw-bold" id="medicalResultSubmitButton" onclick="return confirm('Xác nhận lưu kết quả khám?');">Xác nhận lưu kết quả</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo $base_url; ?>/public/js/medical-results.js"></script>
<?php include '../includes/footer.php'; ?>