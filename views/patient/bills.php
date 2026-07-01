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
$allowedTabs = ['all', 'pending', 'confirmed', 'completed', 'cancel_pending', 'cancelled'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'all';
}
if ($latestBookingTicket && empty($latestBookingTicket['appointment_id']) && !preg_match('/A\d+$/', $latestBookingTicket['ticket_code'] ?? '')) {
    $latestBookingTicket = null;
    unset($_SESSION['latest_booking_ticket']);
}

try {
    $db->query("SELECT full_name FROM users WHERE id = :id");
    $db->bind(':id', $userId);
    $user = $db->single();
} catch (Exception $e) {
    $user = null;
}

try {
    $db->query("SELECT a.id, a.status, a.symptoms, a.created_at,
                       d.id AS doctor_id,
                        u_doc.full_name AS doctor_name,
                        spec.name AS specialty_name,
                        h.name AS hospital_name,
                        s.work_date, s.start_time, s.end_time,
                        d.consultation_fee
                 FROM appointments a
 INNER JOIN schedules s ON a.schedule_id = s.id
 INNER JOIN doctors d ON a.doctor_id = d.id
 LEFT JOIN users u_doc ON d.user_id = u_doc.id
 LEFT JOIN specialties spec ON d.specialty_id = spec.id
 LEFT JOIN hospitals h ON d.hospital_id = h.id
  WHERE a.patient_id = :pid" . ($activeTab !== 'all' ? ($activeTab === 'cancel_pending' ? " AND 1 = 0" : " AND a.status = :st") : "") . "
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
 <a class="bill-tab <?php echo $activeTab === 'pending' ? 'active' : ''; ?>" href="?tab=pending">Chờ duyệt</a>
 <a class="bill-tab <?php echo $activeTab === 'confirmed' ? 'active' : ''; ?>" href="?tab=confirmed">Đã duyệt</a>
 <a class="bill-tab <?php echo $activeTab === 'completed' ? 'active' : ''; ?>" href="?tab=completed">Đã khám</a>
  <a class="bill-tab <?php echo $activeTab === 'cancel_pending' ? 'active' : ''; ?>" href="?tab=cancel_pending">Chờ hủy</a>
  <a class="bill-tab <?php echo $activeTab === 'cancelled' ? 'active' : ''; ?>" href="?tab=cancelled">Đã hủy</a>
  </div>

                <?php if ($latestBookingTicket && (($activeTab === 'cancel_pending' && isset($_COOKIE['cancel_pending'])) || $activeTab === 'all' || ($activeTab === 'pending' && !isset($_COOKIE['cancel_pending'])))): ?>
                    <div class="appointment-card" style="cursor:pointer;" onclick="window.location.href='../../booking_success.php?<?php echo htmlspecialchars(http_build_query($latestBookingTicket)); ?>'">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div style="color:#023f6d;">Mã phiếu: <strong><?php echo htmlspecialchars($latestBookingTicket['ticket_code'] ?? ''); ?></strong></div>
                                <h5 class="fw-bold mt-3 mb-0" style="color:#023f6d;"><?php echo htmlspecialchars($latestBookingTicket['patient_name'] ?? ($user['full_name'] ?? 'Bệnh nhân')); ?></h5>
                            </div>
                            <?php if (isset($_COOKIE['cancel_pending'])): ?>
                                <span class="badge rounded-3 px-4 py-3" style="background:#ffc107;color:#023f6d;">Chờ hủy</span>
                            <?php else: ?>
                                <span class="badge rounded-3 px-4 py-3" style="background:#09c963;">Đặt khám thành công</span>
                            <?php endif; ?>
                        </div>
                        <div class="border-top border-bottom border-dashed py-3">
                            <div class="ticket-title fw-bold mb-3"><i class="bi bi-building text-info me-2"></i><?php echo htmlspecialchars($latestBookingTicket['facility_name'] ?? 'Cơ sở y tế'); ?></div>
                            <div class="ticket-row"><div><i class="bi bi-briefcase-fill"></i>Chuyên khoa:</div><div><?php echo htmlspecialchars($latestBookingTicket['booking_specialty'] ?: ($latestBookingTicket['booking_service'] ?? '')); ?></div></div>
                            <div class="ticket-row"><div><i class="bi bi-heart-pulse-fill"></i>Dịch vụ:</div><div><?php echo htmlspecialchars($latestBookingTicket['booking_service'] ?? 'Khám bệnh'); ?></div></div>
                            <div class="ticket-row"><div><i class="bi bi-calendar-date-fill"></i>Ngày khám:</div><div style="color:#1da1f2;"><?php echo htmlspecialchars($latestBookingTicket['booking_date'] ?? ''); ?></div></div>
                            <div class="ticket-row"><div><i class="bi bi-clock-fill"></i>Giờ khám dự kiến:</div><div style="color:#1da1f2;"><?php echo htmlspecialchars($latestBookingTicket['booking_time'] ?? ''); ?></div></div>
                            <div class="ticket-row"><div><i class="bi bi-wallet-fill"></i>Phí khám:</div><div class="fw-bold"><?php echo htmlspecialchars($latestBookingTicket['booking_price'] ?: '0đ'); ?></div></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (count($appointments) > 0): ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <?php
                        $ticketCode = 'T' . date('ymd', strtotime($appointment['created_at'] ?? 'now')) . strtoupper(substr(md5((string)$appointment['id']), 0, 6));
                        $workDate = !empty($appointment['work_date']) ? date('d/m/Y', strtotime($appointment['work_date'])) : '';
                        $startTime = htmlspecialchars(substr($appointment['start_time'] ?? '', 0, 5));
                        $fee = number_format((float)($appointment['consultation_fee'] ?? 0), 0, ',', '.') . 'đ';
                        ?>
                        <div class="appointment-card" style="cursor:pointer;" onclick="window.location.href='../../booking_success.php?facility=<?php echo urlencode($appointment['hospital_name'] ?: 'Cơ sở y tế'); ?>&booking_service=<?php echo urlencode($appointment['symptoms'] ?: 'Khám bệnh'); ?>&booking_specialty=<?php echo urlencode($appointment['specialty_name'] ?? ''); ?>&booking_date=<?php echo urlencode($workDate); ?>&booking_time=<?php echo urlencode($startTime); ?>&booking_price=<?php echo urlencode($fee); ?>&patient_name=<?php echo urlencode($user['full_name'] ?? 'Bệnh nhân'); ?>'">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <div style="color:#023f6d;">Mã phiếu: <strong><?php echo htmlspecialchars($ticketCode); ?></strong></div>
                                    <h5 class="fw-bold mt-3 mb-0" style="color:#023f6d;"><?php echo htmlspecialchars($user['full_name'] ?? 'Bệnh nhân'); ?></h5>
                                </div>
 <?php
 $statusLabels = [
 'pending' => ['Chờ duyệt', '#ffc107', '#023f6d'],
 'confirmed' => ['Đã duyệt', '#09c963', '#fff'],
  'completed' => ['Đã khám', '#0dcaf0', '#023f6d'],
  'cancel_pending' => ['Chờ hủy', '#ffc107', '#023f6d'],
  'cancelled' => ['Đã hủy', '#dc3545', '#fff'],
 ];
 $statusInfo = $statusLabels[$appointment['status'] ?? 'pending'] ?? $statusLabels['pending'];
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
                        </div>
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

                <?php if (!$latestBookingTicket && count($appointments) === 0): ?>
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
