<?php
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: ../../views/auth/login.php");
    exit();
}

$db = new Database();
$userId = $_SESSION['user_id'];
$appointments = [];
$user = null;
$latestBookingTicket = $_SESSION['latest_booking_ticket'] ?? null;
$activeTab = $_GET['tab'] ?? 'all';
$allowedTabs = ['all', 'confirmed', 'completed', 'cancel_pending', 'cancelled'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    $db->query("SELECT a.doctor_id FROM appointments a WHERE a.id = :appointment_id AND a.patient_id = :patient_id AND a.status = 'completed'");
    $db->bind(':appointment_id', $appointmentId);
    $db->bind(':patient_id', $userId);
    $reviewAppointment = $db->single();

    if (!$reviewAppointment || $rating < 1 || $rating > 5 || $comment === '') {
        $_SESSION['review_message'] = 'Vui lòng chọn số sao và nhập nội dung đánh giá.';
    } else {
        $db->query("SELECT id FROM reviews WHERE patient_id = :patient_id AND doctor_id = :doctor_id LIMIT 1");
        $db->bind(':patient_id', $userId);
        $db->bind(':doctor_id', $reviewAppointment['doctor_id']);
        $existingReview = $db->single();

        if ($existingReview) {
            $db->query("UPDATE reviews SET rating = :rating, comment = :comment WHERE id = :id");
            $db->bind(':id', $existingReview['id']);
        } else {
            $db->query("INSERT INTO reviews (patient_id, doctor_id, rating, comment) VALUES (:patient_id, :doctor_id, :rating, :comment)");
            $db->bind(':patient_id', $userId);
            $db->bind(':doctor_id', $reviewAppointment['doctor_id']);
        }
        $db->bind(':rating', $rating);
        $db->bind(':comment', $comment);
        $db->execute();
        $_SESSION['review_message'] = 'Cảm ơn bạn đã đánh giá bệnh viện.';
    }

    header('Location: bills.php?tab=completed');
    exit();
}
if ($latestBookingTicket && empty($latestBookingTicket['appointment_id']) && !preg_match('/A\d+$/', $latestBookingTicket['ticket_code'] ?? '')) {
    $latestBookingTicket = null;
    unset($_SESSION['latest_booking_ticket']);
}

try {
    $db->query("UPDATE appointments SET status = 'confirmed' WHERE patient_id = :pid AND status = 'pending'");
    $db->bind(':pid', $userId);
    $db->execute();
} catch (Exception $e) {
}

try {
    $db->query("SELECT full_name FROM users WHERE id = :id");
    $db->bind(':id', $userId);
    $user = $db->single();
} catch (Exception $e) {
    $user = null;
}

try {
    $db->query("SHOW TABLES LIKE 'refund_requests'");
    $hasRefundTable = (bool)$db->single();

    $statusWhere = '';
    $refundSelect = $hasRefundTable ? ", rr.status AS refund_status" : ", NULL AS refund_status";
    $refundJoin = $hasRefundTable ? " LEFT JOIN refund_requests rr ON rr.appointment_id = a.id" : "";

    if ($activeTab === 'cancel_pending') {
        $statusWhere = $hasRefundTable ? " AND ((rr.status = 'pending') OR (a.status IN ('cancel_pending', 'cancelled') AND (rr.status IS NULL OR rr.status <> 'refunded')))" : " AND a.status IN ('cancel_pending', 'cancelled')";
    } elseif ($activeTab === 'cancelled') {
        $statusWhere = $hasRefundTable ? " AND a.status = 'cancelled' AND rr.status = 'refunded'" : " AND 1 = 0";
    } elseif ($activeTab !== 'all') {
        $statusWhere = $hasRefundTable ? " AND a.status = :st AND (rr.status IS NULL OR rr.status <> 'pending')" : " AND a.status = :st";
    }

    $db->query("SELECT a.id, a.status, a.symptoms, a.created_at,
                       d.id AS doctor_id,
                        u_doc.full_name AS doctor_name,
                        spec.name AS specialty_name,
                        h.name AS hospital_name,
                        s.work_date, s.start_time, s.end_time,
                        d.consultation_fee,
                         mr.id AS result_id, mr.diagnosis, mr.conclusion, mr.prescription,
                         mr.note, mr.re_exam_date, mr.pdf_file,
                         rv.id AS review_id, rv.rating AS review_rating, rv.comment AS review_comment" . $refundSelect . "
                 FROM appointments a
 INNER JOIN schedules s ON a.schedule_id = s.id
 INNER JOIN doctors d ON a.doctor_id = d.id
 LEFT JOIN users u_doc ON d.user_id = u_doc.id
 LEFT JOIN specialties spec ON d.specialty_id = spec.id
 LEFT JOIN hospitals h ON d.hospital_id = h.id
 LEFT JOIN medical_results mr ON mr.appointment_id = a.id
 LEFT JOIN reviews rv ON rv.patient_id = a.patient_id AND rv.doctor_id = a.doctor_id" . $refundJoin . "
  WHERE a.patient_id = :pid" . $statusWhere . "
 ORDER BY s.work_date DESC, s.start_time DESC");
 $db->bind(':pid', $userId);
  if ($activeTab !== 'all' && $activeTab !== 'cancel_pending') {
  $db->bind(':st', $activeTab);
  }
 $appointments = $db->resultSet();
} catch (Exception $e) {
    $appointments = [];
}

include '../../includes/header.php';
?>

<style>
    .patient-layout { max-width: 1200px; margin: 0 auto; }
    .patient-breadcrumb { font-size: 0.9rem; font-weight: 600; padding: 16px 0; }
    .patient-breadcrumb a { color: #023f6d; text-decoration: none; }
    .patient-breadcrumb .active { color: #00a8e8; }
    .sidebar-menu { background: #fff; border: 1px solid #eef2f7; border-radius: 14px; padding: 18px 14px; box-shadow: 0 2px 10px rgba(2,63,109,0.05); }
    .sidebar-menu a { display: block; padding: 12px 16px; color: #023f6d; text-decoration: none; font-weight: 600; border-radius: 10px; margin-bottom: 6px; }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: #e7f8ff; color: #00a8e8; }
    .sidebar-menu .btn-add { background: linear-gradient(135deg, #16d5f7 0%, #05b7df 100%); color: #fff; border: none; border-radius: 10px; padding: 13px 16px; font-weight: 700; width: 100%; margin-bottom: 14px; }
    .patient-card { background: #fff; border: 1px solid #eef2f7; border-radius: 14px; padding: 26px; min-height: 520px; box-shadow: 0 2px 10px rgba(2,63,109,0.05); }
    .bill-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
    .bill-tab { border: 0; border-radius: 999px; padding: 10px 28px; font-weight: 700; color: #023f6d; background: #edf1f4; text-decoration: none; }
    .bill-tab.active { background: #12bfea; color: #fff; }
    .empty-state { text-align: center; padding: 4px 20px 30px; }
    .empty-state img { max-width: 300px; margin-top: 18px; }
    .empty-state h5 { color: #adb5bd; font-size: 1.25rem; line-height: 1.6; margin-bottom: 6px; }
    .appointment-card { border: 1px solid #eef2f7; border-radius: 12px; padding: 18px 20px; margin-bottom: 12px; }
    .care-banner { background: linear-gradient(100deg, #ff7a1a 0%, #ff9e23 58%, #fff 58%, #fff 100%); color: #fff; border-radius: 8px; padding: 14px 18px; margin: 12px 0 16px; overflow: hidden; }
    .ticket-title { color:#00a8e8; font-size: 1.25rem; text-transform: uppercase; }
    .ticket-row { display: grid; grid-template-columns: 150px 1fr; gap: 24px; margin: 14px 0; color:#023f6d; }
    .ticket-row i { color:#f7a627; margin-right: 8px; }
    .medical-result-section { background: #f8fbff; border-radius: 12px; padding: 16px; margin-bottom: 14px; }
    .medical-result-section h6 { color: #00a8e8; font-weight: 800; }
</style>

<div class="patient-layout">
    <nav class="patient-breadcrumb">
        <a href="<?php echo $base_url; ?>/index.php">Trang chủ</a>
        <span class="text-muted">/</span>
        <span class="active">Phiếu khám bệnh</span>
    </nav>

    <div class="row pb-5">
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="sidebar-menu">
                <a href="<?php echo $base_url; ?>/views/patient/profile_create.php" class="btn-add d-flex align-items-center justify-content-center">
                    <i class="bi bi-plus-circle me-2"></i> Thêm hồ sơ
                </a>
                <a href="<?php echo $base_url; ?>/views/patient/records.php"><i class="bi bi-file-medical me-2"></i> Hồ sơ bệnh nhân</a>
                <a href="<?php echo $base_url; ?>/views/patient/bills.php" class="active"><i class="bi bi-file-earmark-text me-2"></i> Phiếu khám bệnh</a>
                <a href="<?php echo $base_url; ?>/views/patient/notifications.php"><i class="bi bi-bell me-2"></i> Thông báo <span class="badge bg-danger ms-1">99+</span></a>
            </div>
        </div>

        <div class="col-md-9">
            <div class="patient-card">
 <h5 class="fw-bold mb-3" style="color:#023f6d;">Danh sách phiếu khám bệnh</h5>
                <div class="bill-tabs">
 <a class="bill-tab <?php echo $activeTab === 'all' ? 'active' : ''; ?>" href="?tab=all">Tất cả</a>
 <a class="bill-tab <?php echo $activeTab === 'confirmed' ? 'active' : ''; ?>" href="?tab=confirmed">Đặt khám thành công</a>
 <a class="bill-tab <?php echo $activeTab === 'completed' ? 'active' : ''; ?>" href="?tab=completed">Đã khám</a>
  <a class="bill-tab <?php echo $activeTab === 'cancel_pending' ? 'active' : ''; ?>" href="?tab=cancel_pending">Chờ hoàn tiền</a>
  <a class="bill-tab <?php echo $activeTab === 'cancelled' ? 'active' : ''; ?>" href="?tab=cancelled">Đã hoàn tiền</a>
  </div>

                <?php if (!empty($_SESSION['review_message'])): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['review_message']); unset($_SESSION['review_message']); ?></div>
                <?php endif; ?>

                <?php if (count($appointments) > 0): ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <?php
                        $ticketCode = 'T' . date('ymd', strtotime($appointment['created_at'] ?? 'now')) . strtoupper(substr(md5((string)$appointment['id']), 0, 6));
                        $workDate = !empty($appointment['work_date']) ? date('d/m/Y', strtotime($appointment['work_date'])) : '';
                        $startTime = htmlspecialchars(substr($appointment['start_time'] ?? '', 0, 5));
                        $fee = number_format((float)($appointment['consultation_fee'] ?? 0), 0, ',', '.') . 'đ';
                        ?>
                        <div class="appointment-card" style="cursor:pointer;" onclick="window.location.href='../../booking_success.php?appointment_id=<?php echo (int)$appointment['id']; ?>&facility=<?php echo urlencode($appointment['hospital_name'] ?: 'Cơ sở y tế'); ?>&booking_service=<?php echo urlencode($appointment['symptoms'] ?: 'Khám bệnh'); ?>&booking_specialty=<?php echo urlencode($appointment['specialty_name'] ?? ''); ?>&booking_date=<?php echo urlencode($workDate); ?>&booking_time=<?php echo urlencode($startTime); ?>&booking_price=<?php echo urlencode($fee); ?>&patient_name=<?php echo urlencode($user['full_name'] ?? 'Bệnh nhân'); ?>'">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <div style="color:#023f6d;">Mã phiếu: <strong><?php echo htmlspecialchars($ticketCode); ?></strong></div>
                                    <h5 class="fw-bold mt-3 mb-0" style="color:#023f6d;"><?php echo htmlspecialchars($user['full_name'] ?? 'Bệnh nhân'); ?></h5>
                                </div>
 <?php
 $statusLabels = [
 'pending' => ['Đặt khám thành công', '#09c963', '#fff'],
 'confirmed' => ['Đặt khám thành công', '#09c963', '#fff'],
  'completed' => ['Đã khám', '#0dcaf0', '#023f6d'],
  'cancel_pending' => ['Chờ hoàn tiền', '#ffc107', '#023f6d'],
  'cancelled' => ['Đã hoàn tiền', '#09c963', '#fff'],
 ];
 $statusInfo = ((($appointment['refund_status'] ?? '') === 'pending') || (($appointment['status'] ?? '') === 'cancelled' && ($appointment['refund_status'] ?? '') !== 'refunded')) ? $statusLabels['cancel_pending'] : ($statusLabels[$appointment['status'] ?? 'pending'] ?? $statusLabels['pending']);
 ?>
 <span class="badge rounded-3 px-4 py-3" style="background:<?php echo $statusInfo[1]; ?>; color:<?php echo $statusInfo[2]; ?>;"><?php echo $statusInfo[0]; ?></span>
                            </div>
                            <div class="border-top border-bottom border-dashed py-3">
                                <div class="ticket-title fw-bold mb-3"><i class="bi bi-building text-info me-2"></i><?php echo htmlspecialchars($appointment['hospital_name'] ?: 'Cơ sở y tế'); ?></div>
                                <div class="ticket-row"><div><i class="bi bi-briefcase-fill"></i>Chuyên khoa:</div><div><?php echo htmlspecialchars($appointment['specialty_name'] ?? ''); ?></div></div>
                                <div class="ticket-row"><div><i class="bi bi-heart-pulse-fill"></i>Dịch vụ:</div><div><?php echo htmlspecialchars($appointment['symptoms'] ?: 'Khám bệnh'); ?></div></div>
                                <div class="ticket-row"><div><i class="bi bi-calendar-date-fill"></i>Ngày khám:</div><div style="color:#1da1f2;"><?php echo htmlspecialchars($workDate); ?></div></div>
                                <div class="ticket-row"><div><i class="bi bi-clock-fill"></i>Giờ khám dự kiến:</div><div style="color:#1da1f2;"><?php echo $startTime; ?></div></div>
                                <div class="ticket-row"><div><i class="bi bi-wallet-fill"></i>Phí khám:</div><div class="fw-bold"><?php echo htmlspecialchars($fee); ?></div></div>
                            </div>
                            <?php if (($appointment['status'] ?? '') === 'completed'): ?>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-info text-white fw-bold" data-bs-toggle="modal" data-bs-target="#resultModal<?php echo (int)$appointment['id']; ?>" onclick="event.stopPropagation();">
                                        <i class="bi bi-file-earmark-medical me-1"></i>Xem kết quả khám
                                    </button>
                                    <button type="button" class="btn btn-warning text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo (int)$appointment['id']; ?>" onclick="event.stopPropagation();">
                                        <i class="bi bi-star-fill me-1"></i><?php echo !empty($appointment['review_id']) ? 'Sửa đánh giá' : 'Đánh giá bệnh viện'; ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (($appointment['status'] ?? '') === 'completed'): ?>
                            <div class="modal fade" id="resultModal<?php echo (int)$appointment['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content text-start border-0 rounded-4">
                                        <div class="modal-header bg-info text-white rounded-top-4">
                                            <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-medical me-2"></i>Kết quả khám bệnh</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6"><strong>Bệnh viện:</strong><br><?php echo htmlspecialchars($appointment['hospital_name'] ?? 'Cơ sở y tế'); ?></div>
                                                <div class="col-md-6"><strong>Bác sĩ:</strong><br><?php echo htmlspecialchars($appointment['doctor_name'] ?? ''); ?></div>
                                                <div class="col-md-6"><strong>Ngày khám:</strong><br><?php echo htmlspecialchars($workDate); ?></div>
                                            </div>
                                            <?php if (!empty($appointment['result_id'])): ?>
                                                <div class="medical-result-section"><h6>Chẩn đoán</h6><div><?php echo nl2br(htmlspecialchars($appointment['diagnosis'])); ?></div></div>
                                                <div class="medical-result-section"><h6>Kết luận</h6><div><?php echo nl2br(htmlspecialchars($appointment['conclusion'])); ?></div></div>
                                                <div class="medical-result-section"><h6>Đơn thuốc</h6><div><?php echo nl2br(htmlspecialchars($appointment['prescription'])); ?></div></div>
                                                <div class="medical-result-section"><h6>Ghi chú</h6><div><?php echo nl2br(htmlspecialchars($appointment['note'] ?: 'Không có')); ?></div></div>
                                                <div class="medical-result-section"><h6>Ngày tái khám</h6><div><?php echo !empty($appointment['re_exam_date']) ? date('d/m/Y', strtotime($appointment['re_exam_date'])) : 'Không có'; ?></div></div>
                                                <?php if (!empty($appointment['pdf_file'])): ?>
                                                    <a href="<?php echo $base_url . '/' . htmlspecialchars($appointment['pdf_file']); ?>" target="_blank" class="btn btn-primary"><i class="bi bi-download me-1"></i>Tải kết quả</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="alert alert-warning mb-0">Bệnh viện chưa cập nhật kết quả khám.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal fade" id="reviewModal<?php echo (int)$appointment['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4">
                                        <form method="POST">
                                            <div class="modal-header bg-warning rounded-top-4">
                                                <h5 class="modal-title fw-bold">Đánh giá bệnh viện</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <input type="hidden" name="action" value="review">
                                                <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Bệnh viện</label>
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($appointment['hospital_name'] ?? 'Cơ sở y tế'); ?>" disabled>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Mức đánh giá</label>
                                                    <select name="rating" class="form-select" required>
                                                        <?php for ($star = 5; $star >= 1; $star--): ?>
                                                            <option value="<?php echo $star; ?>" <?php echo (int)($appointment['review_rating'] ?? 5) === $star ? 'selected' : ''; ?>><?php echo str_repeat('★', $star) . ' (' . $star . '/5)'; ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="form-label fw-bold">Bình luận</label>
                                                    <textarea name="comment" class="form-control" rows="4" maxlength="1000" required><?php echo htmlspecialchars($appointment['review_comment'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                                                <button type="submit" class="btn btn-warning fw-bold">Gửi đánh giá</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div id="localBookingTicket" class="appointment-card d-none">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <div style="color:#023f6d;">Mã phiếu: <strong data-ticket="ticket_code"></strong></div>
                            <h5 class="fw-bold mt-3 mb-0" style="color:#023f6d;" data-ticket="patient_name"></h5>
                        </div>
                        <span class="badge rounded-3 px-4 py-3" style="background:#09c963;">Đặt khám thành công</span>
                    </div>
                    <div class="border-top border-bottom border-dashed py-3">
                        <div class="ticket-title fw-bold mb-3"><i class="bi bi-building text-info me-2"></i><span data-ticket="facility_name"></span></div>
                        <div class="ticket-row"><div><i class="bi bi-briefcase-fill"></i>Chuyên khoa:</div><div data-ticket="booking_specialty"></div></div>
                        <div class="ticket-row"><div><i class="bi bi-heart-pulse-fill"></i>Dịch vụ:</div><div data-ticket="booking_service"></div></div>
                        <div class="ticket-row"><div><i class="bi bi-calendar-date-fill"></i>Ngày khám:</div><div style="color:#1da1f2;" data-ticket="booking_date"></div></div>
                        <div class="ticket-row"><div><i class="bi bi-clock-fill"></i>Giờ khám dự kiến:</div><div style="color:#1da1f2;" data-ticket="booking_time"></div></div>
                        <div class="ticket-row"><div><i class="bi bi-wallet-fill"></i>Phí khám:</div><div class="fw-bold" data-ticket="booking_price"></div></div>
                    </div>
                </div>

                <?php if (count($appointments) === 0): ?>
                    <div class="empty-state" id="emptyBillsState">
                        <h5>Bạn chưa có phiếu khám nào</h5>
                        <img src="https://cdn-icons-png.flaticon.com/512/4076/4076549.png" alt="No bills">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
localStorage.removeItem('latest_booking_ticket');
</script>

<?php include '../../includes/footer.php'; ?>
