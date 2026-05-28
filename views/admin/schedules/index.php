<?php
require_once '../../../config/database.php';
include '../includes/header.php';

$db = new Database();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_available_group') {
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    if ($doctorId <= 0) {
        $error = 'Không tìm thấy bác sĩ cần xóa lịch.';
    } else {
        $sql = "DELETE s FROM schedules s INNER JOIN doctors d ON s.doctor_id = d.id WHERE s.doctor_id = :doctor_id AND s.status = 'available' AND s.work_date >= CURDATE()";
        if ($isHospitalAdmin) {
            $sql .= " AND d.hospital_id = :hospital_id";
        }
        $db->query($sql);
        $db->bind(':doctor_id', $doctorId);
        if ($isHospitalAdmin) {
            $db->bind(':hospital_id', $currentHospitalId);
        }
        $db->execute();
        $success = 'Đã xóa các lịch còn trống của bác sĩ này. Lịch đã được đặt vẫn được giữ lại.';
    }
}

$where = 'WHERE s.work_date >= CURDATE() AND s.work_date <= DATE_ADD(CURDATE(), INTERVAL COALESCE(h.booking_advance_days, 30) DAY)';
if ($isHospitalAdmin) {
    $where .= ' AND d.hospital_id = :hospital_id';
}

$db->query("SELECT s.*, u.full_name as doctor_name, h.name as hospital_name, sp.name as specialty_name, d.treatment_text
            FROM schedules s
            INNER JOIN doctors d ON s.doctor_id = d.id
            LEFT JOIN users u ON d.user_id = u.id
            LEFT JOIN hospitals h ON d.hospital_id = h.id
            LEFT JOIN specialties sp ON d.specialty_id = sp.id
            $where
            ORDER BY u.full_name ASC, s.work_date ASC, s.start_time ASC");
if ($isHospitalAdmin) {
    $db->bind(':hospital_id', $currentHospitalId);
}
$schedules = $db->resultSet();

$weekdayLabels = [
    1 => 'Thứ 2',
    2 => 'Thứ 3',
    3 => 'Thứ 4',
    4 => 'Thứ 5',
    5 => 'Thứ 6',
    6 => 'Thứ 7',
    0 => 'CN'
];

$scheduleGroups = [];
foreach ($schedules as $schedule) {
    $key = (int)$schedule['doctor_id'];
    if (!isset($scheduleGroups[$key])) {
        $scheduleGroups[$key] = [
            'doctor_id' => (int)$schedule['doctor_id'],
            'doctor_name' => $schedule['doctor_name'] ?? 'Chưa gán tài khoản',
            'hospital_name' => $schedule['hospital_name'] ?? '',
            'specialty_name' => $schedule['specialty_name'] ?? '',
            'treatment_text' => $schedule['treatment_text'] ?? '',
            'dates' => [],
            'weekdays' => [],
            'times' => [],
            'status_counts' => ['available' => 0, 'booked' => 0, 'cancelled' => 0],
            'available_count' => 0,
            'total_count' => 0
        ];
    }

    $dateValue = $schedule['work_date'];
    $weekdayIndex = (int)date('w', strtotime($dateValue));
    $timeText = date('H:i', strtotime($schedule['start_time'])) . ' - ' . date('H:i', strtotime($schedule['end_time']));
    $status = $schedule['status'] ?? 'available';

    $scheduleGroups[$key]['dates'][] = $dateValue;
    $scheduleGroups[$key]['weekdays'][$weekdayIndex] = $weekdayLabels[$weekdayIndex] ?? '';
    $scheduleGroups[$key]['times'][$timeText] = $timeText;
    $scheduleGroups[$key]['status_counts'][$status] = ($scheduleGroups[$key]['status_counts'][$status] ?? 0) + 1;
    $scheduleGroups[$key]['available_count'] += $status === 'available' ? 1 : 0;
    $scheduleGroups[$key]['total_count']++;
}

foreach ($scheduleGroups as &$group) {
    $group['dates'] = array_values(array_unique($group['dates']));
    sort($group['dates']);
    ksort($group['weekdays']);
    sort($group['times']);
}
unset($group);
?>

<div class="card border-0 shadow-sm mb-4 d-none" style="background: linear-gradient(135deg, #e8f8ff 0%, #ffffff 100%); border-left: 5px solid #00b5f1 !important;">
    <div class="card-body p-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; background:#00b5f1; color:#fff;">
                <i class="bi bi-grid-3x3-gap-fill fs-2"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1" style="color:#023f6d;">Các hình thức đặt khám ngoài website</h4>
                <div class="text-muted">Hospital tự thêm ô như Đặt khám theo chuyên khoa, Đặt khám theo bác sĩ, chọn icon và trang chuyển đến.</div>
            </div>
        </div>
        <a href="booking_forms.php" class="btn btn-primary px-4 py-3 fw-bold rounded-3"><i class="bi bi-plus-circle me-1"></i> Thêm / sửa hình thức</a>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Quản lý Lịch khám</h2>
    <div class="d-flex gap-2">
        <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Thêm lịch khám</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Bác sĩ</th>
                        <th>Ngày khám</th>
                        <th>Giờ khám</th>
                        <th>Bệnh viện</th>
                        <th>Trạng thái</th>
                        <th class="text-end pe-3">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($scheduleGroups) > 0): ?>
                        <?php foreach ($scheduleGroups as $group): ?>
                            <?php
                                $firstDate = $group['dates'][0] ?? null;
                                $lastDate = $group['dates'][count($group['dates']) - 1] ?? null;
                                $dateRange = $firstDate && $lastDate
                                    ? date('d/m/Y', strtotime($firstDate)) . ($firstDate !== $lastDate ? ' - ' . date('d/m/Y', strtotime($lastDate)) : '')
                                    : 'Đang cập nhật';
                                $statusText = [];
                                if (($group['status_counts']['available'] ?? 0) > 0) {
                                    $statusText[] = ($group['status_counts']['available'] ?? 0) . ' còn trống';
                                }
                                if (($group['status_counts']['booked'] ?? 0) > 0) {
                                    $statusText[] = ($group['status_counts']['booked'] ?? 0) . ' đã đặt';
                                }
                                if (($group['status_counts']['cancelled'] ?? 0) > 0) {
                                    $statusText[] = ($group['status_counts']['cancelled'] ?? 0) . ' đã hủy';
                                }
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold"><?php echo htmlspecialchars($group['doctor_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($group['treatment_text'] ?: 'Đang cập nhật chuyên trị'); ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars(implode(', ', array_filter($group['weekdays']))); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($dateRange); ?> · <?php echo (int)$group['total_count']; ?> lịch</small>
                                </td>
                                <td>
                                    <?php foreach ($group['times'] as $timeText): ?>
                                        <span class="badge bg-light text-dark border me-1 mb-1"><?php echo htmlspecialchars($timeText); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><?php echo htmlspecialchars($group['hospital_name']); ?></td>
                                <td>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><?php echo htmlspecialchars(implode(', ', $statusText)); ?></span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="create.php?doctor_id=<?php echo (int)$group['doctor_id']; ?>" class="btn btn-sm btn-outline-primary">Chỉnh sửa</a>
                                    <?php if ($group['available_count'] > 0): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Xóa các lịch còn trống của bác sĩ này? Lịch đã đặt sẽ được giữ lại.');">
                                            <input type="hidden" name="action" value="delete_available_group">
                                            <input type="hidden" name="doctor_id" value="<?php echo (int)$group['doctor_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Xóa lịch trống</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Chưa có lịch khám nào trong thời gian tới.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
