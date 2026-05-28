<?php
require_once 'config/database.php';
include 'includes/header.php';

$db = new Database();
$packageId = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;
$db->query("SELECT lp.*, h.name AS hospital_name, h.address, h.phone, h.logo_url, h.booking_advance_days, h.booking_time_slots
            FROM lab_packages lp
            INNER JOIN hospitals h ON h.id = lp.hospital_id
            WHERE lp.id = :id AND lp.is_active = 1 LIMIT 1");
$db->bind(':id', $packageId);
$package = $db->single();
if (!$package) {
    echo '<div class="alert alert-warning my-5">Không tìm thấy gói xét nghiệm.</div>';
    include 'includes/footer.php';
    exit();
}
$db->query("SELECT * FROM lab_package_services WHERE package_id = :package_id ORDER BY sort_order ASC, id ASC");
$db->bind(':package_id', $packageId);
$services = $db->resultSet();
$timeSlots = json_decode($package['booking_time_slots'] ?? '', true);
if (!is_array($timeSlots) || count($timeSlots) === 0) {
    $timeSlots = [
        ['period' => 'morning', 'start' => '07:00', 'end' => '08:00'],
        ['period' => 'morning', 'start' => '08:00', 'end' => '09:00'],
        ['period' => 'afternoon', 'start' => '13:00', 'end' => '14:00'],
        ['period' => 'afternoon', 'start' => '14:00', 'end' => '15:00']
    ];
}
?>

<div class="py-4" style="background:#eaf7ff; min-height:650px;">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb fw-semibold">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none" style="color:#023f6d;">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="lab_booking.php" class="text-decoration-none" style="color:#023f6d;">Đặt lịch xét nghiệm</a></li>
            <li class="breadcrumb-item active" style="color:#00b5f1;">Thông tin đặt lịch</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="bg-white rounded-4 shadow-sm overflow-hidden">
                <div class="p-4 text-white fw-bold" style="background:#023f6d;">Thông tin cơ sở y tế</div>
                <div class="p-4">
                    <h5 class="fw-bold" style="color:#023f6d;"><?php echo htmlspecialchars($package['hospital_name']); ?></h5>
                    <div class="small text-muted mb-2"><?php echo htmlspecialchars($package['address'] ?: 'Đang cập nhật địa chỉ'); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars($package['phone'] ?: 'Đang cập nhật điện thoại'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="bg-white rounded-4 shadow-sm p-4">
                <h3 class="fw-bold mb-4" style="color:#00a8f0;">Đặt lịch gói xét nghiệm</h3>
                <div class="mb-4 p-3 rounded-3" style="background:#f0fbff;">
                    <div class="fw-bold" style="color:#023f6d;"><?php echo htmlspecialchars($package['name']); ?></div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Dịch vụ <span class="text-danger">*</span></label>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($services as $service): ?>
                            <button type="button" class="lab-service-option rounded-3 p-3 border bg-white text-start" data-weekdays="<?php echo htmlspecialchars($service['schedule_text'] ?? ''); ?>" data-times='<?php echo htmlspecialchars($service['time_slots'] ?: '[]', ENT_QUOTES, 'UTF-8'); ?>' data-name="<?php echo htmlspecialchars($service['name']); ?>" data-price="<?php echo number_format((float)$service['price'], 0, ',', '.'); ?>đ">
                                <div class="fw-bold mb-1" style="color:#00a8f0;"><i class="bi <?php echo htmlspecialchars($service['service_icon'] ?? 'bi-clipboard2-pulse'); ?> me-2"></i><?php echo htmlspecialchars($service['name']); ?></div>
                                <div class="small text-muted mb-1">Giá: <?php echo number_format((float)$service['price'], 0, ',', '.'); ?>đ</div>
                                <?php if (!empty($service['description'])): ?><div class="small" style="color:#023f6d;"><?php echo nl2br(htmlspecialchars($service['description'])); ?></div><?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                        <?php if (count($services) === 0): ?><div class="text-muted">Gói này chưa có dịch vụ xét nghiệm.</div><?php endif; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Ngày xét nghiệm <span class="text-danger">*</span></label>
                    <div id="dateOptions" class="d-flex flex-wrap gap-2 text-muted">Chọn dịch vụ để hiển thị ngày xét nghiệm</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Giờ xét nghiệm <span class="text-danger">*</span></label>
                    <div id="timeOptions" class="d-flex flex-wrap gap-2 d-none">
                        <?php foreach ($timeSlots as $slot): ?>
                            <button type="button" class="btn btn-outline-info rounded-pill time-option"><?php echo htmlspecialchars(($slot['start'] ?? '') . ' - ' . ($slot['end'] ?? '')); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div id="timePlaceholder" class="text-muted">Chọn ngày để hiển thị giờ xét nghiệm</div>
                </div>

                <button id="continueBtn" class="btn btn-premium-primary w-100 py-3 rounded-3" disabled>Tiếp tục</button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedService = false;
let selectedDate = false;
let selectedTime = false;
function updateContinue() {
    document.getElementById('continueBtn').disabled = !(selectedService && selectedDate && selectedTime);
}
let selectedServiceTimes = [];
function renderTimes(times) {
    const container = document.getElementById('timeOptions');
    container.innerHTML = '';
    const list = Array.isArray(times) && times.length ? times : <?php echo json_encode($timeSlots); ?>;
    list.forEach(function (slot) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-info rounded-pill time-option';
        button.textContent = (slot.start || '') + ' - ' + (slot.end || '');
        button.addEventListener('click', function () {
            document.querySelectorAll('.time-option').forEach(item => item.classList.remove('active'));
            this.classList.add('active');
            selectedTime = true;
            updateContinue();
        });
        container.appendChild(button);
    });
}
function renderDates(weekdays) {
    const container = document.getElementById('dateOptions');
    container.className = 'd-flex flex-wrap gap-2';
    container.innerHTML = '';
    const allowed = weekdays ? weekdays.split(',').filter(Boolean).map(Number) : [];
    for (let i = 1; i <= 14; i++) {
        const date = new Date();
        date.setDate(date.getDate() + i);
        if (allowed.length && !allowed.includes(date.getDay())) continue;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-info rounded-pill date-option';
        button.textContent = date.toLocaleDateString('vi-VN');
        button.addEventListener('click', function () {
            document.querySelectorAll('.date-option').forEach(item => item.classList.remove('active'));
            this.classList.add('active');
            selectedDate = true;
            document.getElementById('timePlaceholder').classList.add('d-none');
            document.getElementById('timeOptions').classList.remove('d-none');
            renderTimes(selectedServiceTimes);
            updateContinue();
        });
        container.appendChild(button);
    }
    if (!container.children.length) container.textContent = 'Không có ngày phù hợp.';
}
document.querySelectorAll('.lab-service-option').forEach(function (button) {
    button.addEventListener('click', function () {
        document.querySelectorAll('.lab-service-option').forEach(item => { item.style.borderColor = ''; item.style.backgroundColor = ''; });
        this.style.borderColor = '#00a8f0';
        this.style.backgroundColor = '#eefcff';
        selectedService = true;
        try { selectedServiceTimes = JSON.parse(this.dataset.times || '[]'); } catch (e) { selectedServiceTimes = []; }
        selectedDate = false;
        selectedTime = false;
        document.getElementById('timeOptions').classList.add('d-none');
        document.getElementById('timePlaceholder').classList.remove('d-none');
        renderDates(this.dataset.weekdays);
        updateContinue();
    });
});
document.querySelectorAll('.time-option').forEach(function (button) {
    button.addEventListener('click', function () {
        document.querySelectorAll('.time-option').forEach(item => item.classList.remove('active'));
        this.classList.add('active');
        selectedTime = true;
        updateContinue();
    });
});
</script>

<?php include 'includes/footer.php'; ?>
