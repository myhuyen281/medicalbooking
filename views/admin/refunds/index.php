<?php
require_once '../../../controllers/RefundController.php';
include '../includes/header.php';

if (!$isHospitalAdmin) {
    header("Location: $base_url/views/admin/dashboard.php");
    exit();
}

$controller = new RefundController();
$msg = '';

try {
    $syncDb = new Database();
    $syncDb->query("SELECT a.id
        FROM appointments a
        INNER JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN refund_requests rr ON rr.appointment_id = a.id
        WHERE d.hospital_id = :hospital_id
          AND a.status IN ('cancel_pending', 'cancelled')
          AND rr.id IS NULL");
    $syncDb->bind(':hospital_id', $currentHospitalId);
    $missingRefunds = $syncDb->resultSet();
    foreach ($missingRefunds as $missingRefund) {
        $controller->cancelAppointment((int)$missingRefund['id'], 'patient', 'Khách hàng hủy phiếu khám', null, $currentHospitalId);
    }
    $syncDb->query("UPDATE refund_requests rr
        INNER JOIN users u ON rr.patient_id = u.id
        SET rr.payment_method = COALESCE(NULLIF(rr.payment_method, ''), 'VNPAY'),
            rr.bank_account_name = COALESCE(NULLIF(rr.bank_account_name, ''), u.full_name),
            rr.bank_account_number = COALESCE(NULLIF(rr.bank_account_number, ''), CONCAT('9704', LPAD(rr.patient_id, 8, '0'))),
            rr.bank_name = CASE WHEN rr.bank_name IS NULL OR rr.bank_name = '' OR rr.bank_name = 'Ngân hàng mô phỏng' THEN 'Ngân hàng NCB' ELSE rr.bank_name END,
            rr.refund_rate = CASE WHEN rr.paid_amount > 0 AND rr.refund_rate = 0 THEN 100 ELSE rr.refund_rate END,
            rr.refund_amount = CASE WHEN rr.paid_amount > 0 AND rr.refund_amount = 0 THEN rr.paid_amount ELSE rr.refund_amount END
        WHERE rr.hospital_id = :hospital_id");
    $syncDb->bind(':hospital_id', $currentHospitalId);
    $syncDb->execute();
} catch (Exception $e) {
}
$filterStatus = $_GET['status'] ?? '';
$allowedStatuses = ['', 'pending', 'refunded', 'rejected'];
if (!in_array($filterStatus, $allowedStatuses)) {
    $filterStatus = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_id'], $_POST['status'])) {
    $refundId = (int)$_POST['refund_id'];
    if ($_POST['status'] === 'refunded') {
        $controller->approve($refundId, (int)$_SESSION['user_id']);
        $msg = 'Đã cập nhật trạng thái Đã hoàn tiền.';
    } elseif ($_POST['status'] === 'rejected') {
        $controller->reject($refundId, (int)$_SESSION['user_id']);
        $msg = 'Đã cập nhật trạng thái Từ chối hoàn tiền.';
    }
}

$refunds = $controller->index($filterStatus, $isHospitalAdmin ? $currentHospitalId : null);
$statusLabels = [
    'pending' => ['Chờ xử lý', 'bg-warning'],
    'refunded' => ['Đã hoàn tiền', 'bg-success'],
    'rejected' => ['Từ chối hoàn tiền', 'bg-danger']
];
?>
<link rel="stylesheet" href="<?php echo $base_url; ?>/public/css/refunds.css">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">Quản lý hoàn tiền</h2>
        <div class="text-muted">Quản lý yêu cầu hoàn tiền của khách hàng.</div>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4 refund-status-card">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
            <label class="fw-bold">Lọc trạng thái:</label>
            <select name="status" class="form-select w-auto" onchange="this.form.submit()">
                <option value="" <?php echo $filterStatus === '' ? 'selected' : ''; ?>>Tất cả</option>
                <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Chờ xử lý</option>
                <option value="refunded" <?php echo $filterStatus === 'refunded' ? 'selected' : ''; ?>>Đã hoàn tiền</option>
                <option value="rejected" <?php echo $filterStatus === 'rejected' ? 'selected' : ''; ?>>Từ chối hoàn tiền</option>
            </select>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover refund-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Mã đơn</th>
                        <th>Tên bệnh nhân</th>
                        <th>Bệnh viện</th>
                        <th>Số tiền thanh toán</th>
                        <th>Tỷ lệ hoàn</th>
                        <th>Số tiền hoàn</th>
                        <th>Thanh toán</th>
                        <th>Tài khoản nhận</th>
                        <th>Lý do</th>
                        <th>Trạng thái</th>
                        <th class="text-center pe-3">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($refunds) > 0): ?>
                        <?php foreach ($refunds as $refund): ?>
                            <?php $status = $statusLabels[$refund['status']] ?? ['Không rõ', 'bg-secondary']; ?>
                            <tr>
                                <td class="ps-3 fw-bold">#<?php echo (int)$refund['appointment_id']; ?></td>
                                <td><?php echo htmlspecialchars($refund['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($refund['hospital_name']); ?></td>
                                <td><?php echo number_format((float)$refund['paid_amount'], 0, ',', '.'); ?>₫</td>
                                <td><span class="badge bg-info refund-rate"><?php echo (int)$refund['refund_rate']; ?>%</span></td>
                                <td class="refund-money"><?php echo number_format((float)$refund['refund_amount'], 0, ',', '.'); ?>₫</td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($refund['payment_method'] ?? 'VNPAY'); ?></div>
                                    <small class="text-muted">Đã thanh toán</small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($refund['bank_account_name'] ?? $refund['patient_name']); ?></div>
                                    <div>STK: <?php echo htmlspecialchars($refund['bank_account_number'] ?? 'Chưa cập nhật'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($refund['bank_name'] ?? 'Ngân hàng NCB'); ?></small>
                                </td>
                                <td class="refund-reason"><?php echo nl2br(htmlspecialchars($refund['reason'] ?? '')); ?></td>
                                <td><span class="badge <?php echo $status[1]; ?>"><?php echo $status[0]; ?></span></td>
                                <td class="text-center pe-3">
                                    <form method="POST" class="refund-action-form">
                                        <input type="hidden" name="refund_id" value="<?php echo (int)$refund['id']; ?>">
                                        <select name="status" class="form-select form-select-sm" onchange="if(confirm('Cập nhật trạng thái hoàn tiền?')) this.form.submit(); else this.value='<?php echo htmlspecialchars($refund['status']); ?>';">
                                            <option value="pending" <?php echo $refund['status'] === 'pending' ? 'selected' : ''; ?> disabled>Chờ xử lý</option>
                                            <option value="refunded" <?php echo $refund['status'] === 'refunded' ? 'selected' : ''; ?>>Đã hoàn tiền</option>
                                            <option value="rejected" <?php echo $refund['status'] === 'rejected' ? 'selected' : ''; ?>>Từ chối hoàn tiền</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="11" class="text-center py-4">Chưa có yêu cầu hoàn tiền.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="<?php echo $base_url; ?>/public/js/refunds.js"></script>
<?php include '../includes/footer.php'; ?>
