<?php
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: ../../views/auth/login.php");
    exit();
}

$latestBookingTicket = $_SESSION['latest_booking_ticket'] ?? null;

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
    .notification-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 44px; }
    .notification-tab { border: 0; border-radius: 999px; padding: 10px 28px; font-weight: 700; color: #023f6d; background: #edf1f4; }
    .notification-tab.active { background: #12bfea; color: #fff; }
    .option-btn { border: 1px solid #e5eaf0; background: #fff; color: #023f6d; border-radius: 8px; padding: 10px 18px; font-weight: 700; }
    .empty-state { text-align: center; padding: 4px 20px 30px; }
    .empty-state img { max-width: 300px; margin-top: 18px; }
    .empty-state h5 { color: #adb5bd; font-size: 1.25rem; line-height: 1.6; margin-bottom: 6px; }
    .notification-item { background:#eaf4ff; border-radius:14px; padding:18px; display:flex; gap:16px; align-items:flex-start; color:#023f6d; }
    .notification-icon { width:52px; height:52px; border-radius:50%; background:#18b8ef; color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.6rem; position:relative; flex:0 0 auto; }
    .notification-icon::after { content:''; position:absolute; top:7px; right:8px; width:9px; height:9px; background:#ff2f4f; border-radius:50%; }
</style>

<div class="patient-layout">
    <nav class="patient-breadcrumb">
        <a href="<?php echo $base_url; ?>/index.php">Trang chủ</a>
        <span class="text-muted">/</span>
        <span class="active">Thông báo</span>
    </nav>

    <div class="row pb-5">
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="sidebar-menu">
                <a href="<?php echo $base_url; ?>/views/patient/profile_create.php" class="btn-add d-flex align-items-center justify-content-center">
                    <i class="bi bi-plus-circle me-2"></i> Thêm hồ sơ
                </a>
                <a href="<?php echo $base_url; ?>/views/patient/records.php"><i class="bi bi-file-medical me-2"></i> Hồ sơ bệnh nhân</a>
                <a href="<?php echo $base_url; ?>/views/patient/bills.php"><i class="bi bi-file-earmark-text me-2"></i> Phiếu khám bệnh</a>
                <a href="<?php echo $base_url; ?>/views/patient/notifications.php" class="active"><i class="bi bi-bell me-2"></i> Thông báo <span class="badge bg-danger ms-1">99+</span></a>
            </div>
        </div>

        <div class="col-md-9">
            <div class="patient-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0" style="color:#023f6d;">Danh sách thông báo</h5>
                    <button class="option-btn" type="button"><i class="bi bi-sliders me-2"></i>Tùy chọn</button>
                </div>
                <div class="notification-tabs">
                    <button class="notification-tab active" type="button">Phiếu khám <span class="badge bg-danger ms-1">1</span></button>
                    <button class="notification-tab" type="button">Tin tức <span class="badge bg-danger ms-1">99+</span></button>
                    <button class="notification-tab" type="button">Thông báo</button>
                </div>

                <?php if ($latestBookingTicket): ?>
                    <div class="notification-item" onclick="window.location.href='<?php echo $base_url; ?>/booking_success.php?<?php echo htmlspecialchars(http_build_query($latestBookingTicket)); ?>'" style="cursor:pointer;">
                        <div class="notification-icon"><i class="bi bi-envelope"></i></div>
                        <div>
                            <div class="fw-bold">Bạn đã đăng ký khám bệnh thành công tại <?php echo htmlspecialchars($latestBookingTicket['facility_name'] ?? 'cơ sở y tế'); ?>. Mã phiếu: <?php echo htmlspecialchars($latestBookingTicket['ticket_code'] ?? ''); ?></div>
                            <div class="small mt-2" style="color:#00a8e8;">Vừa xong</div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="notification-item d-none" id="localBookingNotification" style="cursor:pointer;">
                    <div class="notification-icon"><i class="bi bi-envelope"></i></div>
                    <div>
                        <div class="fw-bold">Bạn đã đăng ký khám bệnh thành công tại <span data-ticket="facility_name"></span>. Mã phiếu: <span data-ticket="ticket_code"></span></div>
                        <div class="small mt-2" style="color:#00a8e8;">Vừa xong</div>
                    </div>
                </div>

                <div class="empty-state" id="emptyNotificationState">
                    <h5>Bạn chưa có thông báo</h5>
                    <img src="https://cdn-icons-png.flaticon.com/512/4076/4076549.png" alt="No notifications">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const latestTicket = localStorage.getItem('latest_booking_ticket');
const hasServerNotification = <?php echo $latestBookingTicket ? 'true' : 'false'; ?>;
if (latestTicket && !hasServerNotification) {
    try {
        const ticket = JSON.parse(latestTicket);
        document.querySelectorAll('[data-ticket="facility_name"]').forEach(element => element.textContent = ticket.facility_name || 'cơ sở y tế');
        document.querySelectorAll('[data-ticket="ticket_code"]').forEach(element => element.textContent = ticket.ticket_code || '');
        const notification = document.getElementById('localBookingNotification');
        notification.classList.remove('d-none');
        notification.onclick = () => {
            window.location.href = '../../booking_success.php?' + new URLSearchParams(ticket).toString();
        };
        const emptyState = document.getElementById('emptyNotificationState');
        if (emptyState) emptyState.classList.add('d-none');
    } catch (error) {}
}
if (hasServerNotification) {
    const emptyState = document.getElementById('emptyNotificationState');
    if (emptyState) emptyState.classList.add('d-none');
}
</script>

<?php include '../../includes/footer.php'; ?>
